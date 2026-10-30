<?php

declare(strict_types=1);

namespace Sibs\KronosApi\Repository;

use PDO;
use RuntimeException;

/** Read-only, database-side search and pagination for inactive Kronos users. */
final class ResigneeDirectoryRepository
{
    private const MANILA_TODAY = "DATE(CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', '+08:00'))";
    private const LAST_WORKING_DAY = 'DATE(e.gy_last_working_day)';
    private const FROM_TABLES = 'FROM gy_employee e '
        . 'INNER JOIN gy_user u ON TRIM(u.gy_user_code) = TRIM(e.gy_emp_code) ';

    public function __construct(private readonly PDO $pdo) {}

    /**
     * Build parameterized WHERE, including one latest row per SIBS ID.
     * Account IDs are passed by HRIS after mapping its master departments;
     * they never become SQL identifiers.
     *
     * @param array{search: string, account: string, accountIds: list<int>, accountMatchIds: list<int>, searchAccountIds: list<int>} $filters
     * @return array{sql: string, values: list<mixed>}
     */
    public static function buildConditions(array $filters): array
    {
        $where = [
            'u.gy_user_status = 1',
            "TRIM(COALESCE(e.gy_emp_code, '')) <> ''",
            'e.gy_last_working_day IS NOT NULL',
            self::LAST_WORKING_DAY . " > '1900-01-01'",
            self::LAST_WORKING_DAY . ' <= ' . self::MANILA_TODAY,
            'e.gy_emp_id = (SELECT MAX(ee.gy_emp_id) FROM gy_employee ee '
                . 'WHERE TRIM(ee.gy_emp_code) = TRIM(e.gy_emp_code))',
            'u.gy_user_id = (SELECT MAX(uu.gy_user_id) FROM gy_user uu '
                . 'WHERE TRIM(uu.gy_user_code) = TRIM(e.gy_emp_code))',
        ];
        $values = [];
        $search = trim($filters['search'] ?? '');
        if ($search !== '') {
            $match = '%' . $search . '%';
            $terms = [
                'TRIM(e.gy_emp_code) LIKE ?',
                'e.gy_emp_fname LIKE ?',
                'e.gy_emp_mname LIKE ?',
                'e.gy_emp_lname LIKE ?',
                "CONCAT_WS(' ', e.gy_emp_fname, e.gy_emp_mname, e.gy_emp_lname) LIKE ?",
                'u.gy_full_name LIKE ?',
                'e.gy_emp_account LIKE ?',
                "CONCAT(TRIM(e.gy_emp_lname), ', ', TRIM(e.gy_emp_fname)) LIKE ?",
            ];
            array_push($values, ...array_fill(0, count($terms), $match));
            $searchAccountIds = $filters['searchAccountIds'] ?? [];
            if ($searchAccountIds !== []) {
                $terms[] = 'e.gy_acc_id IN (' . implode(', ', array_fill(0, count($searchAccountIds), '?')) . ')';
                array_push($values, ...$searchAccountIds);
            }
            $where[] = '(' . implode(' OR ', $terms) . ')';
        }

        $account = strtolower(trim($filters['account'] ?? ''));
        if ($account !== '' && $account !== 'all') {
            // This fallback also supports older Kronos rows whose gy_acc_id
            // is missing but gy_emp_account still contains the assigned name.
            $accountMatchIds = $filters['accountMatchIds'] ?? [];
            if ($accountMatchIds !== []) {
                $where[] = '(e.gy_acc_id IN ('
                    . implode(', ', array_fill(0, count($accountMatchIds), '?'))
                    . ') OR LOWER(TRIM(e.gy_emp_account)) = ?)';
                array_push($values, ...$accountMatchIds);
            } else {
                $where[] = 'LOWER(TRIM(e.gy_emp_account)) = ?';
            }
            $values[] = $account;
        }
        if (($filters['accountIds'] ?? []) !== []) {
            $accountIds = $filters['accountIds'];
            $where[] = 'e.gy_acc_id IN (' . implode(', ', array_fill(0, count($accountIds), '?')) . ')';
            array_push($values, ...$accountIds);
        }
        return ['sql' => 'WHERE ' . implode(' AND ', $where), 'values' => $values];
    }

    /** @param array{search: string, account: string, accountIds: list<int>, accountMatchIds: list<int>, searchAccountIds: list<int>} $filters
     * @return array{data: list<array<string, mixed>>, stats: array<string, int>, pagination: array<string, int>}
     */
    public function findPage(int $page, int $limit, array $filters): array
    {
        $condition = self::buildConditions($filters);
        $base = self::FROM_TABLES . $condition['sql'];
        $last = self::LAST_WORKING_DAY;
        $today = self::MANILA_TODAY;
        $summary = 'SELECT COUNT(*) AS total, '
            . "COALESCE(SUM(CASE WHEN $last >= DATE_FORMAT($today, '%Y-%m-01') THEN 1 ELSE 0 END), 0) AS thisMonth, "
            . "COALESCE(SUM(CASE WHEN $last >= DATE_SUB($today, INTERVAL 29 DAY) THEN 1 ELSE 0 END), 0) AS last30Days, "
            . "COUNT(DISTINCT COALESCE(NULLIF(TRIM(CAST(e.gy_acc_id AS CHAR)), ''), NULLIF(TRIM(e.gy_emp_account), ''))) AS accounts "
            . $base;
        $statsRow = $this->run($summary, $condition['values'])->fetch(PDO::FETCH_ASSOC) ?: [];
        $stats = [
            'total' => (int) ($statsRow['total'] ?? 0),
            'thisMonth' => (int) ($statsRow['thisMonth'] ?? 0),
            'last30Days' => (int) ($statsRow['last30Days'] ?? 0),
            'accounts' => (int) ($statsRow['accounts'] ?? 0),
        ];
        $totalPages = max(1, (int) ceil($stats['total'] / $limit));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $limit;
        $sql = 'SELECT e.gy_emp_id AS employeeId, TRIM(e.gy_emp_code) AS sibsId, '
            . 'e.gy_emp_fname AS firstName, e.gy_emp_mname AS middleName, '
            . 'e.gy_emp_lname AS lastName, NULLIF(TRIM(u.gy_full_name), \'\') AS employeeName, '
            . 'e.gy_acc_id AS accountId, e.gy_emp_account AS fallbackAccount, '
            . 'e.gy_emp_hiredate AS hireDate, ' . $last . ' AS lastWorkingDate, '
            . 'u.gy_user_status AS userStatus ' . $base
            . ' ORDER BY ' . $last . ' DESC, e.gy_emp_id DESC LIMIT ? OFFSET ?';
        $data = $this->run($sql, [...$condition['values'], $limit, $offset])->fetchAll(PDO::FETCH_ASSOC);
        return [
            'data' => $data,
            'stats' => $stats,
            'pagination' => [
                'currentPage' => $page,
                'totalPages' => $totalPages,
                'total' => $stats['total'],
                'limit' => $limit,
            ],
        ];
    }

    /** @param list<mixed> $values */
    private function run(string $sql, array $values): \PDOStatement
    {
        $statement = $this->pdo->prepare($sql);
        if ($statement === false) {
            throw new RuntimeException('Failed to prepare Kronos resignee query.');
        }
        foreach ($values as $index => $value) {
            $statement->bindValue($index + 1, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $statement->execute();
        return $statement;
    }
}
