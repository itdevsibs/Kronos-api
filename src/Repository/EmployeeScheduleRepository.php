<?php

declare(strict_types=1);

namespace Sibs\KronosApi\Repository;

use PDO;
use PDOStatement;
use RuntimeException;

final class EmployeeScheduleRepository
{
    private const FIELDS = 's.gy_sched_id, s.gy_emp_id, s.gy_sched_day, s.gy_sched_mode, s.gy_sched_login, '
        . 's.gy_sched_breakout, s.gy_sched_breakin, s.gy_sched_logout, s.gy_sched_reg, s.gy_sched_by';

    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array{data: list<array<string, mixed>>, total: int} */
    public function findPage(
        string $employeeCode,
        int $page,
        int $limit,
        ?string $search,
        ?string $dateFrom,
        ?string $dateTo
    ): array {
        $where = ['TRIM(e.gy_emp_code) = :employee_code'];
        $bindings = [':employee_code' => [$employeeCode, PDO::PARAM_STR]];

        if ($search !== null && $search !== '') {
            $searchFields = [
                "DATE_FORMAT(s.gy_sched_day, '%Y-%m-%d')",
                "DATE_FORMAT(s.gy_sched_day, '%M %e, %Y')",
                's.gy_sched_mode',
                's.gy_sched_login',
                's.gy_sched_breakout',
                's.gy_sched_breakin',
                's.gy_sched_logout',
                "DATE_FORMAT(s.gy_sched_reg, '%Y-%m-%d')",
                "DATE_FORMAT(s.gy_sched_reg, '%M %e, %Y')",
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
            $where[] = 'DATE(s.gy_sched_day) >= :date_from';
            $bindings[':date_from'] = [$dateFrom, PDO::PARAM_STR];
        }
        if ($dateTo !== null) {
            $where[] = 'DATE(s.gy_sched_day) <= :date_to';
            $bindings[':date_to'] = [$dateTo, PDO::PARAM_STR];
        }

        $whereSql = 'FROM gy_schedule s INNER JOIN gy_employee e ON s.gy_emp_id = e.gy_emp_id WHERE '
            . implode(' AND ', $where);
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
                . ' ORDER BY s.gy_sched_day DESC, s.gy_sched_id DESC LIMIT :limit OFFSET :offset'
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
