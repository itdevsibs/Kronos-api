<?php

declare(strict_types=1);

namespace Sibs\KronosApi\Repository;

use PDO;
use PDOStatement;
use RuntimeException;

final class WorkforceViewRepository
{
    private const TRACKER_FIELDS = 't.gy_tracker_id, t.gy_tracker_code, t.gy_tracker_date, t.gy_emp_code, '
        . 't.gy_emp_email, t.gy_emp_fullname, t.gy_account_id, t.gy_emp_account, t.gy_tracker_login, '
        . 't.gy_tracker_breakout, t.gy_tracker_breakin, t.gy_tracker_logout, t.gy_tracker_wh, '
        . 't.gy_tracker_bh, t.gy_tracker_ot, t.gy_tracker_ath, t.gy_tracker_status, '
        . 't.gy_tracker_request, t.gy_tracker_reason, t.gy_tracker_history, t.gy_tracker_remarks, '
        . 't.gy_tracker_loc';

    private const ESCALATION_FIELDS = 'x.gy_esc_id, x.gy_esc_type, x.gy_esc_reason, x.gy_esc_photodir, '
        . 'x.gy_esc_status, x.gy_esc_deny, x.gy_esc_date, '
        . 'x.gy_tracker_id AS escalation_tracker_id, x.gy_tracker_date AS escalation_tracker_date, '
        . 'x.gy_tracker_login AS escalation_tracker_login, x.gy_tracker_breakout AS escalation_tracker_breakout, '
        . 'x.gy_tracker_breakin AS escalation_tracker_breakin, x.gy_tracker_logout AS escalation_tracker_logout, '
        . 'x.gy_tracker_wh AS escalation_tracker_wh, x.gy_tracker_bh AS escalation_tracker_bh, '
        . 'x.gy_tracker_ot AS escalation_tracker_ot, x.gy_publish, x.gy_usercode, x.old_tracker_date, '
        . 'x.old_tracker_login, x.old_tracker_breakout, x.old_tracker_breakin, x.old_tracker_logout, x.msg_usercode';

    private const EMPLOYEE_FIELDS = 'e.gy_emp_id AS employee_id, e.gy_emp_code AS employee_code, '
        . 'e.gy_emp_type AS employee_type, e.gy_emp_schedtype AS employee_schedule_type, '
        . 'e.gy_emp_rate AS employee_rate, e.gy_emp_email AS employee_email, '
        . 'e.gy_emp_lname AS employee_last_name, e.gy_emp_fname AS employee_first_name, '
        . 'e.gy_emp_mname AS employee_middle_name, e.gy_emp_fullname AS employee_full_name, '
        . 'e.gy_acc_id AS employee_account_id, e.gy_emp_account AS employee_account, '
        . 'e.gy_emp_om AS employee_operations_manager_code, '
        . 'e.gy_emp_leave_credits AS employee_leave_credits, e.gy_emp_hiredate AS employee_hire_date, '
        . 'e.gy_emp_lastedit AS employee_last_edit, e.gy_lastedit_by AS employee_last_edited_by, '
        . 'e.gy_work_from AS employee_work_from, e.gy_gender AS employee_gender, e.gy_dob AS employee_birthdate, '
        . 'e.gy_civilstatus AS employee_civil_status, e.gy_assignedloc AS employee_assigned_location, '
        . 'e.gy_tagumdate AS employee_tagum_date, e.gy_davaodate AS employee_davao_date, '
        . 'e.gy_hybriddate AS employee_hybrid_date, e.gy_accjoin AS employee_account_join_date, '
        . 'e.gy_nhodate AS employee_nho_date, e.gy_fststartdate AS employee_fst_start_date, '
        . 'e.gy_fstenddate AS employee_fst_end_date, e.gy_pststartdate AS employee_pst_start_date, '
        . 'e.gy_pstenddate AS employee_pst_end_date, e.gy_certification AS employee_certification_date, '
        . 'e.gy_gradbaystartdate AS employee_grad_bay_start_date, e.gy_gradbayenddate AS employee_grad_bay_end_date, '
        . 'e.gy_fullgolivedate AS employee_full_go_live_date, e.gy_promotiondate AS employee_promotion_date, '
        . 'e.gy_projempdate AS employee_project_date, e.gy_probempdate AS employee_probationary_date, '
        . 'e.gy_regempdate AS employee_regularization_date, e.gy_last_working_day AS employee_last_working_day';

    private const ACCOUNT_FIELDS = 'a.gy_acc_id AS account_id, a.gy_acc_name AS account_name, '
        . 'a.gy_acc_ghl_name AS account_ghl_name, a.gy_dept_id AS account_department_id, '
        . 'a.gy_acc_status AS account_status';

    private const DEPARTMENT_FIELDS = 'd.id_department AS department_id, d.name_department AS department_name';

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findEmployeeAttendancePage(int $page, int $limit, array $filters): array
    {
        [$where, $bindings] = $this->trackerFilters($filters, true);
        $from = 'FROM gy_tracker t INNER JOIN gy_employee e '
            . 'ON TRIM(t.gy_emp_code) = TRIM(e.gy_emp_code) '
            . 'INNER JOIN gy_accounts a ON t.gy_account_id = a.gy_acc_id '
            . 'INNER JOIN gy_department d ON a.gy_dept_id = d.id_department ';
        $select = self::TRACKER_FIELDS . ', ' . self::EMPLOYEE_FIELDS . ', '
            . self::ACCOUNT_FIELDS . ', ' . self::DEPARTMENT_FIELDS;

        return $this->page($select, $from, $where, $bindings, 't.gy_tracker_date DESC, t.gy_tracker_id DESC', $page, $limit);
    }

    public function findEmployeeTrackerHistoryPage(int $page, int $limit, array $filters): array
    {
        [$where, $bindings] = $this->trackerFilters($filters, false);
        $from = 'FROM gy_tracker t INNER JOIN gy_employee e '
            . 'ON TRIM(t.gy_emp_code) = TRIM(e.gy_emp_code) '
            . 'INNER JOIN gy_accounts a ON t.gy_account_id = a.gy_acc_id ';
        $select = self::TRACKER_FIELDS . ', ' . self::EMPLOYEE_FIELDS . ', ' . self::ACCOUNT_FIELDS;

        return $this->page($select, $from, $where, $bindings, 't.gy_tracker_date DESC, t.gy_tracker_id DESC', $page, $limit);
    }

    public function findEscalationQueuePage(int $page, int $limit, array $filters): array
    {
        $where = [];
        $bindings = [];
        $this->addIntFilter($where, $bindings, $filters, 'status', 'x.gy_esc_status', ':status');
        $this->addTextFilter($where, $bindings, $filters, 'gy_emp_code', 'TRIM(e.gy_emp_code)', ':gy_emp_code');
        $this->addIntFilter($where, $bindings, $filters, 'account_id', 'a.gy_acc_id', ':account_id');
        $this->addTextFilter($where, $bindings, $filters, 'submitted_by', 'TRIM(submittedBy.gy_user_code)', ':submitted_by');
        $this->addTextFilter($where, $bindings, $filters, 'recipient', 'TRIM(recipient.gy_user_code)', ':recipient');
        $this->addDateFilters($where, $bindings, $filters, 'x.gy_esc_date');
        $this->addSearch($where, $bindings, $filters['search'] ?? null, [
            'x.gy_esc_reason', 'x.gy_esc_deny', 'e.gy_emp_code', 'e.gy_emp_fullname',
            'a.gy_acc_name', 'a.gy_acc_ghl_name', 'submittedBy.gy_full_name',
            'recipient.gy_full_name', 'supervisor.gy_full_name',
        ]);
        $from = 'FROM gy_escalate x INNER JOIN gy_tracker t ON x.gy_tracker_id = t.gy_tracker_id '
            . 'INNER JOIN gy_user submittedBy ON x.gy_esc_by = submittedBy.gy_user_id '
            . 'INNER JOIN gy_user recipient ON x.gy_esc_to = recipient.gy_user_id '
            . 'INNER JOIN gy_user supervisor ON x.gy_sup = supervisor.gy_user_id '
            . 'INNER JOIN gy_employee e ON TRIM(t.gy_emp_code) = TRIM(e.gy_emp_code) '
            . 'INNER JOIN gy_accounts a ON t.gy_account_id = a.gy_acc_id ';
        $select = self::ESCALATION_FIELDS . ', ' . self::TRACKER_FIELDS . ', '
            . self::EMPLOYEE_FIELDS . ', ' . self::ACCOUNT_FIELDS . ', '
            . $this->userFields('submittedBy', 'submitted_by') . ', '
            . $this->userFields('recipient', 'recipient') . ', '
            . $this->userFields('supervisor', 'supervisor');

        return $this->page($select, $from, $where, $bindings, 'x.gy_esc_date DESC, x.gy_esc_id DESC', $page, $limit);
    }

    private function trackerFilters(array $filters, bool $withDepartment): array
    {
        $where = [];
        $bindings = [];
        $this->addTextFilter($where, $bindings, $filters, 'gy_emp_code', 'TRIM(e.gy_emp_code)', ':gy_emp_code');
        $this->addIntFilter($where, $bindings, $filters, 'account_id', 'a.gy_acc_id', ':account_id');
        if ($withDepartment) {
            $this->addIntFilter($where, $bindings, $filters, 'department_id', 'a.gy_dept_id', ':department_id');
        }
        $this->addDateFilters($where, $bindings, $filters, 't.gy_tracker_date');
        $this->addSearch($where, $bindings, $filters['search'] ?? null, [
            't.gy_emp_code', 't.gy_emp_fullname', 't.gy_emp_email', 't.gy_emp_account',
            't.gy_tracker_reason', 't.gy_tracker_remarks', 'e.gy_emp_fullname',
            'a.gy_acc_name', 'a.gy_acc_ghl_name',
        ]);

        return [$where, $bindings];
    }

    private function addIntFilter(array &$where, array &$bindings, array $filters, string $key, string $field, string $placeholder): void
    {
        if (array_key_exists($key, $filters) && $filters[$key] !== null) {
            $where[] = $field . ' = ' . $placeholder;
            $bindings[$placeholder] = [$filters[$key], PDO::PARAM_INT];
        }
    }

    private function addTextFilter(array &$where, array &$bindings, array $filters, string $key, string $field, string $placeholder): void
    {
        if (array_key_exists($key, $filters) && $filters[$key] !== null) {
            $where[] = $field . ' = ' . $placeholder;
            $bindings[$placeholder] = [$filters[$key], PDO::PARAM_STR];
        }
    }

    private function addDateFilters(array &$where, array &$bindings, array $filters, string $field): void
    {
        if (($filters['date_from'] ?? null) !== null) {
            $where[] = $field . ' >= :date_from';
            $bindings[':date_from'] = [$filters['date_from'], PDO::PARAM_STR];
        }
        if (($filters['date_to'] ?? null) !== null) {
            $where[] = $field . ' < DATE_ADD(:date_to, INTERVAL 1 DAY)';
            $bindings[':date_to'] = [$filters['date_to'], PDO::PARAM_STR];
        }
    }

    private function addSearch(array &$where, array &$bindings, mixed $search, array $fields): void
    {
        if (!is_string($search) || $search === '') {
            return;
        }
        $conditions = [];
        foreach ($fields as $index => $field) {
            $placeholder = ':search_' . $index;
            $conditions[] = $field . ' LIKE ' . $placeholder;
            $bindings[$placeholder] = ['%' . $search . '%', PDO::PARAM_STR];
        }
        $where[] = '(' . implode(' OR ', $conditions) . ')';
    }

    private function userFields(string $alias, string $prefix): string
    {
        return $alias . '.gy_user_code AS ' . $prefix . '_user_code, '
            . $alias . '.ghl_contact_id AS ' . $prefix . '_ghl_contact_id, '
            . $alias . '.gy_full_name AS ' . $prefix . '_full_name, '
            . $alias . '.gy_username AS ' . $prefix . '_username, '
            . $alias . '.gy_user_type AS ' . $prefix . '_user_type, '
            . $alias . '.gy_user_function AS ' . $prefix . '_user_function, '
            . $alias . '.gy_head_code AS ' . $prefix . '_head_code, '
            . $alias . '.gy_script_code AS ' . $prefix . '_script_code, '
            . $alias . '.gy_user_status AS ' . $prefix . '_user_status';
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
        $data = $this->prepare('SELECT ' . $select . ' ' . $from . $whereSql
            . 'ORDER BY ' . $order . ' LIMIT :limit OFFSET :offset');
        $this->bindAll($data, $dataBindings);
        $data->execute();

        return ['data' => $data->fetchAll(PDO::FETCH_ASSOC), 'total' => $total];
    }

    private function prepare(string $sql): PDOStatement
    {
        $statement = $this->pdo->prepare($sql);
        if ($statement === false) {
            throw new RuntimeException('Unable to prepare workforce view query.');
        }
        return $statement;
    }

    private function bindAll(PDOStatement $statement, array $bindings): void
    {
        foreach ($bindings as $placeholder => [$value, $type]) {
            $statement->bindValue($placeholder, $value, $type);
        }
    }
}
