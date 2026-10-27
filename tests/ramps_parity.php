<?php

declare(strict_types=1);

// Explicit live integration test; reads a bounded week and does not alter either database.
require __DIR__ . '/../vendor/autoload.php';

use Sibs\KronosApi\Database\Database;
use Sibs\KronosApi\Repository\RampsMetricsRepository;

Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
$pdo = Database::connect([
    'DB1_HOST' => $_ENV['DB1_HOST'] ?? null,
    'DB1_PORT' => $_ENV['DB1_PORT'] ?? null,
    'DB1_NAME' => $_ENV['DB1_NAME'] ?? null,
    'DB1_USER' => $_ENV['DB1_USER'] ?? null,
    'DB1_PASSWORD' => $_ENV['DB1_PASSWORD'] ?? null,
]);
$repository = new RampsMetricsRepository($pdo);
$from = '2026-08-27';
$to = '2026-10-07';
$completed = (new DateTimeImmutable('yesterday', new DateTimeZone('Asia/Manila')))->format('Y-m-d');

$oracle = static function (string $mode, string $weekFrom, string $weekTo, int $accountId, string $cutoff) use ($completed): array {
    $command = ['node', __DIR__ . '/ramps_hris_reference.mjs', $mode, $weekFrom, $weekTo,
        (string) $accountId, $cutoff, $completed];
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__));
    if (!is_resource($process)) {
        throw new RuntimeException('Unable to run HRIS reference process.');
    }
    $output = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    if (proc_close($process) !== 0 || !is_string($output)) {
        throw new RuntimeException('HRIS reference failed: ' . $errors);
    }
    return json_decode($output, true, 512, JSON_THROW_ON_ERROR);
};

$standardWeeks = $repository->weeklyMetrics($from, $to, [], 'standard', $completed);
foreach ($standardWeeks as $standard) {
    $cutoff = $standard['effectiveWeekEnd']
        ?? (new DateTimeImmutable($standard['weekStart']))->modify('-1 day')->format('Y-m-d');
    $standardSource = $oracle('standard', $standard['weekStart'], $standard['weekEnd'], 0, $cutoff);
    $byId = [];
    foreach ($standardSource as $row) {
        $byId[$row['accountId']] = $row['metrics'];
    }
    if (count($byId) !== count($standard['accounts'])) {
        throw new RuntimeException('Standard source and API have different account counts.');
    }
    foreach ($standard['accounts'] as $account) {
        $id = $account['accountId'];
        unset($account['accountId']);
        if (!isset($byId[$id]) || $account !== $byId[$id]) {
            throw new RuntimeException('Standard KPI parity mismatch for account ' . $id . '.');
        }
    }
    echo 'PASS standard HRIS parity: ' . $standard['weekStart'] . ', '
        . count($byId) . " account rows\n";
}

$ids = $pdo->query('SELECT gy_acc_id FROM gy_accounts WHERE gy_acc_status = 0 AND gy_acc_id > 0 '
    . 'ORDER BY gy_acc_id LIMIT 3')->fetchAll(PDO::FETCH_COLUMN);
$zeroHire = $pdo->query("SELECT a.gy_acc_id FROM gy_accounts a INNER JOIN gy_employee e "
    . "ON e.gy_acc_id = a.gy_acc_id WHERE a.gy_acc_status = 0 AND a.gy_acc_id > 0 "
    . "AND e.gy_emp_type = 1 AND LEFT(CAST(e.gy_emp_hiredate AS CHAR), 10) = '0000-00-00' "
    . "AND EXISTS (SELECT 1 FROM gy_user u WHERE u.gy_user_status = 0 "
    . "AND RTRIM(u.gy_user_code) = RTRIM(e.gy_emp_code)) "
    . 'ORDER BY a.gy_acc_id LIMIT 1')->fetchColumn();
if ($zeroHire !== false) {
    $ids[] = $zeroHire;
}
foreach (array_unique(array_map('intval', $ids)) as $id) {
    $fastFrom = '2026-10-01';
    $fastTo = '2026-10-07';
    $fast = $repository->weeklyMetrics($fastFrom, $fastTo, [$id], 'single_account_fast', $completed)[0];
    $source = $oracle('single_account_fast', $fastFrom, $fastTo, $id, $cutoff);
    if ($fast['effectiveWeekEnd'] !== $source['effectiveWeekEnd']) {
        throw new RuntimeException('Fast cutoff parity mismatch for account ' . $id . '.');
    }
    $metrics = $fast['accounts'][0] ?? null;
    if (!is_array($metrics)) {
        throw new RuntimeException('Fast API account missing for ID ' . $id . '.');
    }
    unset($metrics['accountId']);
    if ($metrics !== $source['metrics']) {
        throw new RuntimeException('Fast KPI parity mismatch for account ' . $id . '.');
    }
    echo 'PASS fast HRIS parity: account ' . $id . "\n";
}
