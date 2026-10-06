<?php

declare(strict_types=1);

namespace Sibs\KronosApi\Repository;

use PDO;
use RuntimeException;

final class WorkforceRelationshipRepository
{
    private const EMPLOYEE_FIELDS = 'e.gy_emp_id, e.gy_emp_code, e.gy_emp_type, e.gy_emp_schedtype, '
        . 'e.gy_emp_rate, e.gy_emp_email, e.gy_emp_lname, e.gy_emp_fname, e.gy_emp_mname, '
        . 'e.gy_emp_fullname, e.gy_acc_id, e.gy_emp_account, e.gy_emp_om, '
        . 'e.gy_emp_leave_credits, e.gy_emp_hiredate, e.gy_emp_lastedit, e.gy_lastedit_by, '
        . 'e.gy_work_from, e.gy_gender, e.gy_dob, e.gy_civilstatus, e.gy_assignedloc, '
        . 'e.gy_tagumdate, e.gy_davaodate, e.gy_hybriddate, e.gy_accjoin, e.gy_nhodate, '
        . 'e.gy_fststartdate, e.gy_fstenddate, e.gy_pststartdate, e.gy_pstenddate, '
        . 'e.gy_certification, e.gy_gradbaystartdate, e.gy_gradbayenddate, e.gy_fullgolivedate, '
        . 'e.gy_promotiondate, e.gy_projempdate, e.gy_probempdate, e.gy_regempdate, e.gy_last_working_day';

    private const USER_FIELDS = 'u.gy_user_code, u.ghl_contact_id, u.gy_full_name, u.gy_username, '
        . 'u.gy_user_type, u.gy_user_function, u.gy_head_code, u.gy_script_code, u.gy_user_status';

    private const ACCOUNT_FIELDS = 'a.gy_acc_id, a.gy_acc_name, a.gy_acc_ghl_name, a.gy_dept_id, a.gy_acc_status';

    private const TRACKER_FIELDS = 't.gy_tracker_id, t.gy_tracker_code, t.gy_tracker_date, t.gy_emp_code, '
        . 't.gy_emp_email, t.gy_emp_fullname, t.gy_account_id, t.gy_emp_account, t.gy_tracker_login, '
        . 't.gy_tracker_breakout, t.gy_tracker_breakin, t.gy_tracker_logout, t.gy_tracker_wh, '
        . 't.gy_tracker_bh, t.gy_tracker_ot, t.gy_tracker_ath, t.gy_tracker_status, '
        . 't.gy_tracker_request, t.gy_tracker_reason, t.gy_tracker_history, t.gy_tracker_remarks, '
        . 't.gy_tracker_loc';

    private const ESCALATION_FIELDS = 'x.gy_esc_id, x.gy_esc_type, x.gy_esc_reason, x.gy_esc_photodir, '
        . 'x.gy_esc_status, x.gy_esc_deny, x.gy_esc_date, '
        . 'x.gy_tracker_id, x.gy_tracker_date, x.gy_tracker_login, x.gy_tracker_breakout, '
        . 'x.gy_tracker_breakin, x.gy_tracker_logout, x.gy_tracker_wh, x.gy_tracker_bh, '
        . 'x.gy_tracker_ot, x.gy_publish, x.gy_usercode, x.old_tracker_date, x.old_tracker_login, '
        . 'x.old_tracker_breakout, x.old_tracker_breakin, x.old_tracker_logout, x.msg_usercode';

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findSupervisorUserByEmployeeCode(string $code): array
    {
        return $this->single('SELECT e.gy_emp_id AS _parent_id, u.gy_user_id AS _related_id, ' . self::USER_FIELDS
            . ' FROM gy_employee e LEFT JOIN gy_user u ON e.gy_emp_supervisor = u.gy_user_id'
            . ' WHERE TRIM(e.gy_emp_code) = :employee_code LIMIT 1', ':employee_code', $code, PDO::PARAM_STR);
    }

    public function findOperationsManagerUserByEmployeeCode(string $code): array
    {
        return $this->single('SELECT e.gy_emp_id AS _parent_id, u.gy_user_id AS _related_id, ' . self::USER_FIELDS
            . ' FROM gy_employee e LEFT JOIN gy_user u ON TRIM(e.gy_emp_om) = TRIM(u.gy_user_code)'
            . ' WHERE TRIM(e.gy_emp_code) = :employee_code LIMIT 1', ':employee_code', $code, PDO::PARAM_STR);
    }

    public function findTrackersByEmployeeCode(string $code, int $afterId): array
    {
        return $this->collection('SELECT e.gy_emp_id AS _parent_id, ' . self::TRACKER_FIELDS
            . ' FROM gy_employee e LEFT JOIN gy_tracker t ON TRIM(t.gy_emp_code) = TRIM(e.gy_emp_code)'
            . ' AND t.gy_tracker_id > :after_id WHERE TRIM(e.gy_emp_code) = :employee_code'
            . ' ORDER BY t.gy_tracker_id ASC LIMIT 101', ':employee_code', $code, PDO::PARAM_STR, $afterId, 'gy_tracker_id');
    }

    public function findSupervisedEmployeesByUserCode(string $code, int $afterId): array
    {
        return $this->collection('SELECT u.gy_user_id AS _parent_id, ' . self::EMPLOYEE_FIELDS
            . ' FROM gy_user u LEFT JOIN gy_employee e ON e.gy_emp_supervisor = u.gy_user_id'
            . ' AND e.gy_emp_id > :after_id WHERE TRIM(u.gy_user_code) = :employee_code'
            . ' ORDER BY e.gy_emp_id ASC LIMIT 101', ':employee_code', $code, PDO::PARAM_STR, $afterId, 'gy_emp_id');
    }

    public function findSubmittedEscalationsByUserCode(string $code, int $afterId): array
    {
        return $this->userEscalations($code, $afterId, 'x.gy_esc_by = u.gy_user_id');
    }

    public function findReceivedEscalationsByUserCode(string $code, int $afterId): array
    {
        return $this->userEscalations($code, $afterId, 'x.gy_esc_to = u.gy_user_id');
    }

    public function findSupervisedEscalationsByUserCode(string $code, int $afterId): array
    {
        return $this->userEscalations($code, $afterId, 'x.gy_sup = u.gy_user_id');
    }

    public function findTrackersByAccountId(int $accountId, int $afterId): array
    {
        return $this->collection('SELECT a.gy_acc_id AS _parent_id, ' . self::TRACKER_FIELDS
            . ' FROM gy_accounts a LEFT JOIN gy_tracker t ON t.gy_account_id = a.gy_acc_id'
            . ' AND t.gy_tracker_id > :after_id WHERE a.gy_acc_id = :account_id'
            . ' ORDER BY t.gy_tracker_id ASC LIMIT 101', ':account_id', $accountId, PDO::PARAM_INT, $afterId, 'gy_tracker_id');
    }

    public function findEmployeeByTrackerId(int $trackerId): array
    {
        return $this->single('SELECT t.gy_tracker_id AS _parent_id, e.gy_emp_id AS _related_id, ' . self::EMPLOYEE_FIELDS
            . ' FROM gy_tracker t LEFT JOIN gy_employee e ON TRIM(t.gy_emp_code) = TRIM(e.gy_emp_code)'
            . ' WHERE t.gy_tracker_id = :tracker_id LIMIT 1', ':tracker_id', $trackerId, PDO::PARAM_INT);
    }

    public function findAccountByTrackerId(int $trackerId): array
    {
        return $this->single('SELECT t.gy_tracker_id AS _parent_id, a.gy_acc_id AS _related_id, ' . self::ACCOUNT_FIELDS
            . ' FROM gy_tracker t LEFT JOIN gy_accounts a ON t.gy_account_id = a.gy_acc_id'
            . ' WHERE t.gy_tracker_id = :tracker_id LIMIT 1', ':tracker_id', $trackerId, PDO::PARAM_INT);
    }

    public function findOperationsManagerUserByTrackerId(int $trackerId): array
    {
        return $this->single('SELECT t.gy_tracker_id AS _parent_id, u.gy_user_id AS _related_id, ' . self::USER_FIELDS
            . ' FROM gy_tracker t LEFT JOIN gy_user u ON t.gy_tracker_om = u.gy_user_id'
            . ' WHERE t.gy_tracker_id = :tracker_id LIMIT 1', ':tracker_id', $trackerId, PDO::PARAM_INT);
    }

    public function findEscalationsByTrackerId(int $trackerId, int $afterId): array
    {
        return $this->collection('SELECT t.gy_tracker_id AS _parent_id, ' . self::ESCALATION_FIELDS
            . ' FROM gy_tracker t LEFT JOIN gy_escalate x ON x.gy_tracker_id = t.gy_tracker_id'
            . ' AND x.gy_esc_id > :after_id WHERE t.gy_tracker_id = :tracker_id'
            . ' ORDER BY x.gy_esc_id ASC LIMIT 101', ':tracker_id', $trackerId, PDO::PARAM_INT, $afterId, 'gy_esc_id');
    }

    public function findTrackerByEscalationId(int $escalationId): array
    {
        return $this->single('SELECT x.gy_esc_id AS _parent_id, t.gy_tracker_id AS _related_id, ' . self::TRACKER_FIELDS
            . ' FROM gy_escalate x LEFT JOIN gy_tracker t ON x.gy_tracker_id = t.gy_tracker_id'
            . ' WHERE x.gy_esc_id = :escalation_id LIMIT 1', ':escalation_id', $escalationId, PDO::PARAM_INT);
    }

    public function findSubmittedByUserByEscalationId(int $id): array
    {
        return $this->escalationUser($id, 'x.gy_esc_by = u.gy_user_id');
    }

    public function findRecipientUserByEscalationId(int $id): array
    {
        return $this->escalationUser($id, 'x.gy_esc_to = u.gy_user_id');
    }

    public function findSupervisorUserByEscalationId(int $id): array
    {
        return $this->escalationUser($id, 'x.gy_sup = u.gy_user_id');
    }

    private function userEscalations(string $code, int $afterId, string $join): array
    {
        return $this->collection('SELECT u.gy_user_id AS _parent_id, ' . self::ESCALATION_FIELDS
            . ' FROM gy_user u LEFT JOIN gy_escalate x ON ' . $join
            . ' AND x.gy_esc_id > :after_id WHERE TRIM(u.gy_user_code) = :employee_code'
            . ' ORDER BY x.gy_esc_id ASC LIMIT 101', ':employee_code', $code, PDO::PARAM_STR, $afterId, 'gy_esc_id');
    }

    private function escalationUser(int $id, string $join): array
    {
        return $this->single('SELECT x.gy_esc_id AS _parent_id, u.gy_user_id AS _related_id, ' . self::USER_FIELDS
            . ' FROM gy_escalate x LEFT JOIN gy_user u ON ' . $join
            . ' WHERE x.gy_esc_id = :escalation_id LIMIT 1', ':escalation_id', $id, PDO::PARAM_INT);
    }

    private function single(string $sql, string $placeholder, int|string $id, int $type): array
    {
        $statement = $this->prepare($sql);
        $statement->bindValue($placeholder, $id, $type);
        $statement->execute();
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return ['parent_exists' => false, 'data' => null];
        }
        $relatedId = $row['_related_id'] ?? null;
        unset($row['_parent_id'], $row['_related_id']);

        return ['parent_exists' => true, 'data' => $relatedId === null ? null : $row];
    }

    private function collection(
        string $sql,
        string $placeholder,
        int|string $parent,
        int $type,
        int $afterId,
        string $cursor
    ): array {
        $statement = $this->prepare($sql);
        $statement->bindValue($placeholder, $parent, $type);
        $statement->bindValue(':after_id', $afterId, PDO::PARAM_INT);
        $statement->execute();
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        if ($rows === []) {
            return ['parent_exists' => false, 'data' => []];
        }
        $data = [];
        foreach ($rows as $row) {
            unset($row['_parent_id']);
            if (($row[$cursor] ?? null) !== null) {
                $data[] = $row;
            }
        }

        return ['parent_exists' => true, 'data' => $data];
    }

    private function prepare(string $sql): \PDOStatement
    {
        $statement = $this->pdo->prepare($sql);
        if ($statement === false) {
            throw new RuntimeException('Unable to prepare workforce relationship query.');
        }

        return $statement;
    }
}
