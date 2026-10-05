<?php

declare(strict_types=1);

namespace Sibs\KronosApi\Repository;

use PDO;
use PDOStatement;
use RuntimeException;

final class EmployeeScheduleRepository
{
    private const FIELDS = 'gy_sched_id, gy_emp_id, gy_sched_day, gy_sched_mode, gy_sched_login, '
        . 'gy_sched_breakout, gy_sched_breakin, gy_sched_logout, gy_sched_reg, gy_sched_by';

    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array{data: list<array<string, mixed>>, total: int} */
    public function findPage(
        int $employeeId,
        int $page,
        int $limit,
        ?string $search,
        ?string $dateFrom,
        ?string $dateTo
    ): array {
        $where = ['gy_emp_id = :employee_id'];
        $bindings = [':employee_id' => [$employeeId, PDO::PARAM_INT]];

        if ($search !== null && $search !== '') {
            $searchFields = [
                "DATE_FORMAT(gy_sched_day, '%Y-%m-%d')",
                "DATE_FORMAT(gy_sched_day, '%M %e, %Y')",
                'gy_sched_mode',
                'gy_sched_login',
                'gy_sched_breakout',
                'gy_sched_breakin',
                'gy_sched_logout',
                "DATE_FORMAT(gy_sched_reg, '%Y-%m-%d')",
                "DATE_FORMAT(gy_sched_reg, '%M %e, %Y')",
            ];
            $searchConditions = [];
            foreach ($searchFields as $index => $field) {
                $placeholder = ':search_' . $index;
                $searchConditions[] = $field . ' LIKE ' . $placeholder;
                $bindings[$placeholder] = ['%' . $search . '%', PDO::PARAM_STR];
            }
            $where[] = '(' . implode(' OR ', $searchConditions) . ')';
        }

        if ($dateFrom !== null) {
            $where[] = 'DATE(gy_sched_day) >= :date_from';
            $bindings[':date_from'] = [$dateFrom, PDO::PARAM_STR];
        }
        if ($dateTo !== null) {
            $where[] = 'DATE(gy_sched_day) <= :date_to';
            $bindings[':date_to'] = [$dateTo, PDO::PARAM_STR];
        }

        $whereSql = 'FROM gy_schedule WHERE ' . implode(' AND ', $where);
        $countStatement = $this->prepare('SELECT COUNT(*) AS total ' . $whereSql);
        $this->bindAll($countStatement, $bindings);
        $countStatement->execute();
        $countRow = $countStatement->fetch(PDO::FETCH_ASSOC);
        $total = is_array($countRow) ? (int) ($countRow['total'] ?? 0) : 0;

        $dataBindings = $bindings;
        $dataBindings[':limit'] = [$limit, PDO::PARAM_INT];
        $dataBindings[':offset'] = [($page - 1) * $limit, PDO::PARAM_INT];
        $dataStatement = $this->prepare(
            'SELECT ' . self::FIELDS . ' ' . $whereSql
                . ' ORDER BY gy_sched_day DESC, gy_sched_id DESC LIMIT :limit OFFSET :offset'
        );
        $this->bindAll($dataStatement, $dataBindings);
        $dataStatement->execute();

        return [
            'data' => $dataStatement->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
        ];
    }

    private function prepare(string $query): PDOStatement
    {
        $statement = $this->pdo->prepare($query);
        if ($statement === false) {
            throw new RuntimeException('Unable to prepare employee schedule query.');
        }

        return $statement;
    }

    /** @param array<string, array{0: mixed, 1: int}> $bindings */
    private function bindAll(PDOStatement $statement, array $bindings): void
    {
        foreach ($bindings as $placeholder => [$value, $type]) {
            $statement->bindValue($placeholder, $value, $type);
        }
    }
}
