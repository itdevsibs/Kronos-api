<?php

declare(strict_types=1);

namespace Sibs\KronosApi\Repository;

use PDO;
use PDOStatement;
use RuntimeException;

final class QdsViewRepository
{
    private const ASSIGNMENT_FIELDS = 'q.qag_id, q.qag_sibsid, q.qag_account';

    private const EMPLOYEE_FIELDS = 'e.gy_emp_code AS employee_code, '
        . 'e.gy_emp_fullname AS employee_full_name, e.gy_emp_fname AS employee_first_name, '
        . 'e.gy_emp_mname AS employee_middle_name, e.gy_emp_lname AS employee_last_name, '
        . 'e.gy_emp_email AS employee_email, e.gy_emp_type AS employee_type, '
        . 'e.gy_emp_schedtype AS employee_schedule_type';

    private const ACCOUNT_FIELDS = 'a.gy_acc_id AS account_id, a.gy_acc_name AS account_name, '
        . 'a.gy_acc_ghl_name AS account_ghl_name, a.gy_dept_id AS account_department_id, '
        . 'a.gy_acc_status AS account_status';

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @param array{gy_emp_code?: string|null, account_id?: int|null, search?: string|null} $filters
     * @return array{data: list<array<string, mixed>>, total: int}
     */
    public function findAssignmentPage(int $page, int $limit, array $filters): array
    {
        $where = [];
        $bindings = [];
        if (($filters['gy_emp_code'] ?? null) !== null) {
            $where[] = 'TRIM(q.qag_sibsid) = :gy_emp_code';
            $bindings[':gy_emp_code'] = [(string) $filters['gy_emp_code'], PDO::PARAM_STR];
        }
        if (($filters['account_id'] ?? null) !== null) {
            $where[] = 'a.gy_acc_id = :account_id';
            $bindings[':account_id'] = [$filters['account_id'], PDO::PARAM_INT];
        }
        $this->search($where, $bindings, $filters['search'] ?? null);

        $from = 'FROM qds_assign_group q INNER JOIN gy_employee e '
            . 'ON TRIM(q.qag_sibsid) = TRIM(e.gy_emp_code) '
            . 'INNER JOIN gy_accounts a ON q.qag_account = a.gy_acc_id ';
        $whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where) . ' ';

        $count = $this->prepare('SELECT COUNT(*) AS total ' . $from . $whereSql);
        $this->bindAll($count, $bindings);
        $count->execute();
        $countRow = $count->fetch(PDO::FETCH_ASSOC);
        $total = is_array($countRow) ? (int) ($countRow['total'] ?? 0) : 0;

        $dataBindings = $bindings;
        $dataBindings[':limit'] = [$limit, PDO::PARAM_INT];
        $dataBindings[':offset'] = [($page - 1) * $limit, PDO::PARAM_INT];
        $data = $this->prepare(
            'SELECT ' . self::ASSIGNMENT_FIELDS . ', ' . self::EMPLOYEE_FIELDS . ', '
                . self::ACCOUNT_FIELDS . ' ' . $from . $whereSql
                . 'ORDER BY q.qag_id DESC LIMIT :limit OFFSET :offset'
        );
        $this->bindAll($data, $dataBindings);
        $data->execute();

        return ['data' => $data->fetchAll(PDO::FETCH_ASSOC), 'total' => $total];
    }

    /** @param list<string> $where @param array<string, array{mixed, int}> $bindings */
    private function search(array &$where, array &$bindings, mixed $search): void
    {
        if (!is_string($search) || $search === '') {
            return;
        }

        $fields = [
            'q.qag_sibsid',
            'e.gy_emp_fullname',
            'a.gy_acc_name',
            'a.gy_acc_ghl_name',
        ];
        $parts = [];
        foreach ($fields as $index => $field) {
            $placeholder = ':search_' . $index;
            $parts[] = $field . ' LIKE ' . $placeholder;
            $bindings[$placeholder] = ['%' . $search . '%', PDO::PARAM_STR];
        }
        $where[] = '(' . implode(' OR ', $parts) . ')';
    }

    /** @param array<string, array{mixed, int}> $bindings */
    private function bindAll(PDOStatement $statement, array $bindings): void
    {
        foreach ($bindings as $placeholder => [$value, $type]) {
            $statement->bindValue($placeholder, $value, $type);
        }
    }

    private function prepare(string $sql): PDOStatement
    {
        $statement = $this->pdo->prepare($sql);
        if ($statement === false) {
            throw new RuntimeException('Unable to prepare QDS assignment view query.');
        }

        return $statement;
    }
}
