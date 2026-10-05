<?php

declare(strict_types=1);

namespace Sibs\KronosApi\Repository;

use PDO;
use PDOStatement;
use RuntimeException;

final class LeaveViewRepository
{
    private const LEAVE_FIELDS = 'l.gy_leave_id AS leave_id, l.gy_leave_filed AS leave_filed, '
        . 'l.gy_leave_type AS leave_type, l.gy_leave_paid AS leave_paid, l.gy_leave_day AS leave_day, '
        . 'l.gy_leave_period_one AS leave_period_one, l.gy_leave_period_two AS leave_period_two, '
        . 'l.gy_emp_rate AS employee_rate, l.gy_old_credits AS old_credits, '
        . 'l.gy_new_credits AS new_credits, l.gy_leave_date_from AS leave_date_from, '
        . 'l.gy_leave_date_to AS leave_date_to, l.gy_leave_reason AS leave_reason, '
        . 'l.gy_leave_status AS leave_status, l.gy_leave_date_approved AS leave_date_approved, '
        . 'l.gy_leave_remarks AS leave_remarks, l.gy_leave_attachment AS leave_attachment, '
        . 'l.gy_publish AS leave_publish, l.msg_usercode AS message_user_code';

    private const USER_FIELDS = 'u.gy_user_code AS user_code, u.ghl_contact_id AS user_ghl_contact_id, '
        . 'u.gy_full_name AS user_full_name, u.gy_username AS username, u.gy_user_type AS user_type, '
        . 'u.gy_user_function AS user_function, u.gy_head_code AS user_head_code, '
        . 'u.gy_script_code AS user_script_code, u.gy_user_status AS user_status';

    private const EMPLOYEE_FIELDS = 'e.gy_emp_id AS employee_id, e.gy_emp_code AS employee_code, '
        . 'e.gy_emp_type AS employee_type, e.gy_emp_schedtype AS employee_schedule_type, '
        . 'e.gy_emp_rate AS employee_current_rate, e.gy_emp_email AS employee_email, '
        . 'e.gy_emp_lname AS employee_last_name, e.gy_emp_fname AS employee_first_name, '
        . 'e.gy_emp_mname AS employee_middle_name, e.gy_emp_fullname AS employee_full_name, '
        . 'e.gy_emp_account AS employee_account, e.gy_emp_om AS employee_operations_manager_code, '
        . 'e.gy_emp_leave_credits AS employee_leave_credits, e.gy_emp_hiredate AS employee_hire_date, '
        . 'e.gy_work_from AS employee_work_from, e.gy_gender AS employee_gender, '
        . 'e.gy_dob AS employee_birthdate, e.gy_civilstatus AS employee_civil_status, '
        . 'e.gy_assignedloc AS employee_assigned_location, e.gy_nhodate AS employee_nho_date, '
        . 'e.gy_last_working_day AS employee_last_working_day';

    private const ACCOUNT_FIELDS = 'a.gy_acc_id AS account_id, a.gy_acc_name AS account_name, '
        . 'a.gy_acc_ghl_name AS account_ghl_name, a.gy_dept_id AS account_department_id, '
        . 'a.gy_acc_status AS account_status';

    private const APPROVER_FIELDS = 'approver.gy_user_code AS approver_user_code, '
        . 'approver.gy_full_name AS approver_full_name, approver.gy_user_type AS approver_user_type, '
        . 'approver.gy_user_function AS approver_user_function, '
        . 'approver.gy_user_status AS approver_user_status';

    private const DEPARTMENT_FIELDS = 'd.id_department AS department_id, d.name_department AS department_name';

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findEmployeeLeavesPage(int $page, int $limit, array $filters): array
    {
        return $this->page($page, $limit, $filters, false);
    }

    public function findLeaveManagementPage(int $page, int $limit, array $filters): array
    {
        return $this->page($page, $limit, $filters, true);
    }

    private function page(int $page, int $limit, array $filters, bool $management): array
    {
        $where = [];
        $bindings = [];
        $this->textFilter($where, $bindings, $filters, 'gy_emp_code', 'TRIM(u.gy_user_code)', ':gy_emp_code');
        $this->textFilter($where, $bindings, $filters, 'status', 'l.gy_leave_status', ':status');
        $this->textFilter($where, $bindings, $filters, 'leave_type', 'l.gy_leave_type', ':leave_type');
        if ($management) {
            $this->intFilter($where, $bindings, $filters, 'account_id', 'a.gy_acc_id', ':account_id');
            $this->intFilter($where, $bindings, $filters, 'department_id', 'a.gy_dept_id', ':department_id');
        }
        if (($filters['date_from'] ?? null) !== null) {
            $where[] = 'l.gy_leave_date_to >= :date_from';
            $bindings[':date_from'] = [$filters['date_from'], PDO::PARAM_STR];
        }
        if (($filters['date_to'] ?? null) !== null) {
            $where[] = 'l.gy_leave_date_from < DATE_ADD(:date_to, INTERVAL 1 DAY)';
            $bindings[':date_to'] = [$filters['date_to'], PDO::PARAM_STR];
        }
        $this->search($where, $bindings, $filters['search'] ?? null);

        $from = 'FROM gy_leave l INNER JOIN gy_user u ON l.gy_user_id = u.gy_user_id '
            . 'LEFT JOIN gy_employee e ON TRIM(e.gy_emp_code) = TRIM(u.gy_user_code) '
            . 'LEFT JOIN gy_accounts a ON l.gy_acc_id = a.gy_acc_id '
            . 'LEFT JOIN gy_user approver ON l.gy_leave_approver = approver.gy_user_id ';
        $select = self::LEAVE_FIELDS . ', ' . self::USER_FIELDS . ', ' . self::EMPLOYEE_FIELDS . ', '
            . self::ACCOUNT_FIELDS . ', ' . self::APPROVER_FIELDS;
        if ($management) {
            $from .= 'LEFT JOIN gy_department d ON a.gy_dept_id = d.id_department ';
            $select .= ', ' . self::DEPARTMENT_FIELDS;
        }
        $whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where) . ' ';
        $count = $this->prepare('SELECT COUNT(*) AS total ' . $from . $whereSql);
        $this->bindAll($count, $bindings);
        $count->execute();
        $countRow = $count->fetch(PDO::FETCH_ASSOC);
        $total = is_array($countRow) ? (int) ($countRow['total'] ?? 0) : 0;

        $dataBindings = $bindings;
        $dataBindings[':limit'] = [$limit, PDO::PARAM_INT];
        $dataBindings[':offset'] = [($page - 1) * $limit, PDO::PARAM_INT];
        $data = $this->prepare('SELECT ' . $select . ' ' . $from . $whereSql
            . 'ORDER BY l.gy_leave_filed DESC, l.gy_leave_id DESC LIMIT :limit OFFSET :offset');
        $this->bindAll($data, $dataBindings);
        $data->execute();
        return ['data' => $data->fetchAll(PDO::FETCH_ASSOC), 'total' => $total];
    }

    private function intFilter(array &$where, array &$bindings, array $filters, string $key, string $field, string $placeholder): void
    {
        if (($filters[$key] ?? null) !== null) {
            $where[] = $field . ' = ' . $placeholder;
            $bindings[$placeholder] = [$filters[$key], PDO::PARAM_INT];
        }
    }

    private function textFilter(array &$where, array &$bindings, array $filters, string $key, string $field, string $placeholder): void
    {
        if (($filters[$key] ?? null) !== null) {
            $where[] = $field . ' = ' . $placeholder;
            $bindings[$placeholder] = [(string) $filters[$key], PDO::PARAM_STR];
        }
    }

    private function search(array &$where, array &$bindings, mixed $search): void
    {
        if (!is_string($search) || $search === '') { return; }
        $fields = ['u.gy_user_code', 'u.gy_full_name', 'e.gy_emp_email', 'l.gy_leave_reason',
            'l.gy_leave_remarks', 'a.gy_acc_name', 'a.gy_acc_ghl_name', 'approver.gy_full_name'];
        $parts = [];
        foreach ($fields as $index => $field) {
            $placeholder = ':search_' . $index;
            $parts[] = $field . ' LIKE ' . $placeholder;
            $bindings[$placeholder] = ['%' . $search . '%', PDO::PARAM_STR];
        }
        $where[] = '(' . implode(' OR ', $parts) . ')';
    }

    private function bindAll(PDOStatement $statement, array $bindings): void
    {
        foreach ($bindings as $placeholder => [$value, $type]) { $statement->bindValue($placeholder, $value, $type); }
    }

    private function prepare(string $sql): PDOStatement
    {
        $statement = $this->pdo->prepare($sql);
        if ($statement === false) { throw new RuntimeException('Unable to prepare leave view query.'); }
        return $statement;
    }
}
