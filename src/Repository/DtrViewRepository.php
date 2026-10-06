<?php

declare(strict_types=1);

namespace Sibs\KronosApi\Repository;

use PDO;
use PDOStatement;
use RuntimeException;

final class DtrViewRepository
{
    private const DTR_FIELDS = 'd.dtr_publish_id, d.dtr_year, d.dtr_month, d.dtr_cutoff, d.gy_emp_code, '
        . 'd.dtr_noofhours, d.dtr_lateut, d.dtr_absences, d.dtr_regot, d.dtr_rdreg, d.dtr_rdot, '
        . 'd.dtr_shreg, d.dtr_shot, d.dtr_shrdreg, d.dtr_shrdot, d.dtr_lhreg, d.dtr_lhot, '
        . 'd.dtr_lhrdreg, d.dtr_lhrdot, d.dtr_ndreg, d.dtr_ndregot, d.dtr_ndrdreg, d.dtr_ndrdot, '
        . 'd.dtr_ndsh, d.dtr_ndshot, d.dtr_ndshrd, d.dtr_ndshrdot, d.dtr_ndlh, d.dtr_ndlhot, '
        . 'd.dtr_ndlhrd, d.dtr_ndlhrdot, d.dtr_mdrate, d.dtr_cmpute';

    private const ASSIGNMENT_FIELDS = 't.at_id, t.at_emp_code, t.at_account_id';

    private const EMPLOYEE_FIELDS = 'e.gy_emp_id AS employee_id, e.gy_emp_code AS employee_code, '
        . 'e.gy_emp_type AS employee_type, e.gy_emp_schedtype AS employee_schedule_type, '
        . 'e.gy_emp_rate AS employee_rate, e.gy_emp_email AS employee_email, '
        . 'e.gy_emp_lname AS employee_last_name, e.gy_emp_fname AS employee_first_name, '
        . 'e.gy_emp_mname AS employee_middle_name, e.gy_emp_fullname AS employee_full_name, '
        . 'e.gy_emp_account AS employee_account, e.gy_emp_om AS employee_operations_manager_code, '
        . 'e.gy_emp_hiredate AS employee_hire_date, e.gy_assignedloc AS employee_assigned_location';

    private const ACCOUNT_FIELDS = 'a.gy_acc_id AS account_id, a.gy_acc_name AS account_name, '
        . 'a.gy_acc_ghl_name AS account_ghl_name, a.gy_dept_id AS account_department_id, '
        . 'a.gy_acc_status AS account_status';

    private const PUBLISHER_FIELDS = 'publisher.gy_user_code AS publisher_user_code, '
        . 'publisher.gy_full_name AS publisher_full_name, publisher.gy_user_type AS publisher_user_type, '
        . 'publisher.gy_user_function AS publisher_user_function, publisher.gy_user_status AS publisher_user_status';

    private const ADDED_BY_FIELDS = 'addedBy.gy_user_code AS added_by_user_code, '
        . 'addedBy.gy_full_name AS added_by_full_name, addedBy.gy_user_type AS added_by_user_type, '
        . 'addedBy.gy_user_function AS added_by_user_function, addedBy.gy_user_status AS added_by_user_status';

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findEmployeeDtrPage(int $page, int $limit, array $filters): array
    {
        $where = [];
        $bindings = [];
        $this->textFilter($where, $bindings, $filters, 'gy_emp_code', 'TRIM(d.gy_emp_code)', ':gy_emp_code');
        $this->intFilter($where, $bindings, $filters, 'account_id', 'a.gy_acc_id', ':account_id');
        $this->intFilter($where, $bindings, $filters, 'year', 'd.dtr_year', ':year');
        $this->intFilter($where, $bindings, $filters, 'month', 'd.dtr_month', ':month');
        $this->search($where, $bindings, $filters['search'] ?? null, [
            'd.gy_emp_code', 'e.gy_emp_fullname', 'e.gy_emp_email', 'a.gy_acc_name',
            'a.gy_acc_ghl_name', 'publisher.gy_full_name', 'publisher.gy_user_code',
        ]);
        $from = 'FROM dtr_publish d INNER JOIN gy_employee e '
            . 'ON TRIM(d.gy_emp_code) = TRIM(e.gy_emp_code) '
            . 'LEFT JOIN gy_accounts a ON e.gy_acc_id = a.gy_acc_id '
            . 'LEFT JOIN gy_user publisher ON d.dtr_publisher = publisher.gy_user_id ';
        $select = self::DTR_FIELDS . ', ' . self::EMPLOYEE_FIELDS . ', ' . self::ACCOUNT_FIELDS . ', ' . self::PUBLISHER_FIELDS;
        return $this->page($select, $from, $where, $bindings, 'd.dtr_year DESC, d.dtr_month DESC, d.dtr_cutoff DESC, d.dtr_publish_id DESC', $page, $limit);
    }

    public function findTimesheetAssignmentPage(int $page, int $limit, array $filters): array
    {
        $where = [];
        $bindings = [];
        $this->textFilter($where, $bindings, $filters, 'gy_emp_code', 'TRIM(t.at_emp_code)', ':gy_emp_code');
        $this->intFilter($where, $bindings, $filters, 'account_id', 'a.gy_acc_id', ':account_id');
        $this->search($where, $bindings, $filters['search'] ?? null, [
            't.at_emp_code', 'e.gy_emp_fullname', 'e.gy_emp_email', 'a.gy_acc_name',
            'a.gy_acc_ghl_name', 'addedBy.gy_full_name', 'addedBy.gy_user_code',
        ]);
        $from = 'FROM assign_timesheet t INNER JOIN gy_employee e '
            . 'ON TRIM(t.at_emp_code) = TRIM(e.gy_emp_code) '
            . 'INNER JOIN gy_accounts a ON t.at_account_id = a.gy_acc_id '
            . 'LEFT JOIN gy_user addedBy ON t.at_added_by = addedBy.gy_user_id ';
        $select = self::ASSIGNMENT_FIELDS . ', ' . self::EMPLOYEE_FIELDS . ', '
            . self::ACCOUNT_FIELDS . ', ' . self::ADDED_BY_FIELDS;
        return $this->page($select, $from, $where, $bindings, 't.at_id DESC', $page, $limit);
    }

    private function page(string $select, string $from, array $where, array $bindings, string $order, int $page, int $limit): array
    {
        $whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where) . ' ';
        $count = $this->prepare('SELECT COUNT(*) AS total ' . $from . $whereSql);
        $this->bindAll($count, $bindings);
        $count->execute();
        $row = $count->fetch(PDO::FETCH_ASSOC);
        $total = is_array($row) ? (int) ($row['total'] ?? 0) : 0;
        $dataBindings = $bindings;
        $dataBindings[':limit'] = [$limit, PDO::PARAM_INT];
        $dataBindings[':offset'] = [($page - 1) * $limit, PDO::PARAM_INT];
        $data = $this->prepare('SELECT ' . $select . ' ' . $from . $whereSql . 'ORDER BY ' . $order . ' LIMIT :limit OFFSET :offset');
        $this->bindAll($data, $dataBindings);
        $data->execute();
        return ['data' => $data->fetchAll(PDO::FETCH_ASSOC), 'total' => $total];
    }

    private function intFilter(array &$where, array &$bindings, array $filters, string $key, string $field, string $placeholder): void { if (($filters[$key] ?? null) !== null) { $where[] = $field . ' = ' . $placeholder; $bindings[$placeholder] = [$filters[$key], PDO::PARAM_INT]; } }
    private function textFilter(array &$where, array &$bindings, array $filters, string $key, string $field, string $placeholder): void { if (($filters[$key] ?? null) !== null) { $where[] = $field . ' = ' . $placeholder; $bindings[$placeholder] = [(string) $filters[$key], PDO::PARAM_STR]; } }
    private function search(array &$where, array &$bindings, mixed $search, array $fields): void { if (!is_string($search) || $search === '') return; $parts = []; foreach ($fields as $index => $field) { $placeholder = ':search_' . $index; $parts[] = $field . ' LIKE ' . $placeholder; $bindings[$placeholder] = ['%' . $search . '%', PDO::PARAM_STR]; } $where[] = '(' . implode(' OR ', $parts) . ')'; }
    private function bindAll(PDOStatement $statement, array $bindings): void { foreach ($bindings as $placeholder => [$value, $type]) $statement->bindValue($placeholder, $value, $type); }
    private function prepare(string $sql): PDOStatement { $statement = $this->pdo->prepare($sql); if ($statement === false) throw new RuntimeException('Unable to prepare DTR view query.'); return $statement; }
}
