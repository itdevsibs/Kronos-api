<?php

declare(strict_types=1);

namespace Sibs\KronosApi\Repository;

use PDO;
use RuntimeException;

final class EmployeeRelationshipRepository
{
    private const EMPLOYEE_FIELDS = 'e.gy_emp_id, e.gy_emp_code, e.gy_emp_type, e.gy_emp_schedtype, '
        . 'e.gy_emp_rate, e.gy_emp_email, e.gy_emp_lname, e.gy_emp_fname, e.gy_emp_mname, '
        . 'e.gy_emp_fullname, e.gy_acc_id, e.gy_emp_account, e.gy_emp_supervisor, e.gy_emp_om, '
        . 'e.gy_emp_leave_credits, e.gy_emp_hiredate, e.gy_emp_lastedit, e.gy_lastedit_by, '
        . 'e.gy_work_from, e.gy_gender, e.gy_dob, e.gy_civilstatus, e.gy_assignedloc, '
        . 'e.gy_tagumdate, e.gy_davaodate, e.gy_hybriddate, e.gy_accjoin, e.gy_nhodate, '
        . 'e.gy_fststartdate, e.gy_fstenddate, e.gy_pststartdate, e.gy_pstenddate, '
        . 'e.gy_certification, e.gy_gradbaystartdate, e.gy_gradbayenddate, e.gy_fullgolivedate, '
        . 'e.gy_promotiondate, e.gy_projempdate, e.gy_probempdate, e.gy_regempdate, '
        . 'e.gy_last_working_day';

    private const ACCOUNT_FIELDS = 'a.gy_acc_id, a.gy_acc_name, a.gy_acc_ghl_name, '
        . 'a.gy_dept_id, a.gy_acc_status';

    private const DEPARTMENT_FIELDS = 'd.id_department, d.name_department';

    private const USER_FIELDS = 'u.gy_user_id, u.gy_user_code, u.ghl_contact_id, u.gy_full_name, '
        . 'u.gy_username, u.gy_user_type, u.gy_user_function, u.gy_head_code, '
        . 'u.gy_script_code, u.gy_user_status';

    private const PUBLIC_USER_FIELDS = 'u.gy_user_code, u.ghl_contact_id, u.gy_full_name, '
        . 'u.gy_username, u.gy_user_type, u.gy_user_function, u.gy_head_code, '
        . 'u.gy_script_code, u.gy_user_status';

    private const SCHEDULE_FIELDS = 's.gy_sched_id, s.gy_emp_id, s.gy_sched_day, s.gy_sched_mode, '
        . 's.gy_sched_login, s.gy_sched_breakout, s.gy_sched_breakin, s.gy_sched_logout, '
        . 's.gy_sched_reg, s.gy_sched_by';

    private const PUBLIC_SCHEDULE_FIELDS = 's.gy_sched_id, s.gy_emp_id, s.gy_sched_day, s.gy_sched_mode, '
        . 's.gy_sched_login, s.gy_sched_breakout, s.gy_sched_breakin, s.gy_sched_logout, '
        . 's.gy_sched_reg';

    private const SCHEDULE_ESCALATION_FIELDS = 's.gy_sched_esc_id, s.gy_sched_esc_code, s.gy_req_date, '
        . 's.gy_req_status, s.gy_req_deny, s.gy_req_by, s.gy_req_to, s.gy_sup, s.gy_emp_code, '
        . 's.gy_emp_fullname, s.gy_sched_day, s.gy_sched_mode, s.gy_sched_login, s.gy_sched_breakout, '
        . 's.gy_sched_breakin, s.gy_sched_logout, s.gy_tracker_login, s.gy_tracker_logout, '
        . 's.gy_req_reason, s.gy_req_photodir, s.gy_publish, s.old_sched_mode, s.old_sched_login, '
        . 's.old_sched_breakout, s.old_sched_breakin, s.old_sched_logout, s.old_tracker_login, '
        . 's.old_tracker_logout, s.msg_usercode';

    private const PUBLIC_SCHEDULE_ESCALATION_FIELDS = 's.gy_sched_esc_id, s.gy_sched_esc_code, '
        . 's.gy_req_date, s.gy_req_status, s.gy_req_deny, s.gy_emp_code, s.gy_emp_fullname, '
        . 's.gy_sched_day, s.gy_sched_mode, s.gy_sched_login, s.gy_sched_breakout, '
        . 's.gy_sched_breakin, s.gy_sched_logout, s.gy_tracker_login, s.gy_tracker_logout, '
        . 's.gy_req_reason, s.gy_req_photodir, s.gy_publish, s.old_sched_mode, s.old_sched_login, '
        . 's.old_sched_breakout, s.old_sched_breakin, s.old_sched_logout, s.old_tracker_login, '
        . 's.old_tracker_logout, s.msg_usercode';

    private const SCHEDULE_RD_REQUEST_FIELDS = 'r.gy_rd_id, r.gy_rd_date, r.gy_rd_status, '
        . 'r.gy_tracker_id, r.gy_user_id, r.gy_rd_approved_by';

    private const TRACKER_FIELDS = 't.gy_tracker_id, t.gy_tracker_code, t.gy_tracker_date, t.gy_emp_code, '
        . 't.gy_emp_email, t.gy_emp_fullname, t.gy_account_id, t.gy_emp_account, t.gy_tracker_login, '
        . 't.gy_tracker_breakout, t.gy_tracker_breakin, t.gy_tracker_logout, t.gy_tracker_wh, '
        . 't.gy_tracker_bh, t.gy_tracker_ot, t.gy_tracker_ath, t.gy_tracker_status, '
        . 't.gy_tracker_request, t.gy_tracker_reason, t.gy_tracker_history, t.gy_tracker_remarks, '
        . 't.gy_tracker_loc';

    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array{parent_exists: bool, data: null|array<string, mixed>} */
    public function findAccountByEmployeeCode(string $employeeCode): array
    {
        return $this->findSingle(
            'SELECT e.gy_emp_id AS _parent_id, ' . self::ACCOUNT_FIELDS . ' '
                . 'FROM gy_employee e LEFT JOIN gy_accounts a ON e.gy_acc_id = a.gy_acc_id '
                . 'WHERE TRIM(e.gy_emp_code) = :employee_code ORDER BY e.gy_emp_id ASC LIMIT 1',
            ':employee_code',
            $employeeCode,
            'gy_acc_id',
            PDO::PARAM_STR
        );
    }

    /** @return array{parent_exists: bool, data: null|array<string, mixed>} */
    public function findDepartmentByEmployeeCode(string $employeeCode): array
    {
        return $this->findSingle(
            'SELECT e.gy_emp_id AS _parent_id, ' . self::DEPARTMENT_FIELDS . ' '
                . 'FROM gy_employee e LEFT JOIN gy_accounts a ON e.gy_acc_id = a.gy_acc_id '
                . 'LEFT JOIN gy_department d ON a.gy_dept_id = d.id_department '
                . 'WHERE TRIM(e.gy_emp_code) = :employee_code '
                . 'ORDER BY e.gy_emp_id ASC LIMIT 1',
            ':employee_code',
            $employeeCode,
            'id_department',
            PDO::PARAM_STR
        );
    }

    /** @return array{parent_exists: bool, data: null|array<string, mixed>} */
    public function findUserByEmployeeCode(string $employeeCode): array
    {
        return $this->findSingle(
            'SELECT e.gy_emp_id AS _parent_id, ' . self::USER_FIELDS . ' '
                . 'FROM gy_employee e LEFT JOIN gy_user u '
                . 'ON TRIM(u.gy_user_code) = TRIM(e.gy_emp_code) '
                . 'WHERE TRIM(e.gy_emp_code) = :employee_code '
                . 'ORDER BY u.gy_user_id ASC LIMIT 1',
            ':employee_code',
            $employeeCode,
            'gy_user_id',
            PDO::PARAM_STR
        );
    }

    /** @return array{parent_exists: bool, data: null|array<string, mixed>} */
    public function findEmployeeByUserCode(string $employeeCode): array
    {
        return $this->findSingle(
            'SELECT u.gy_user_id AS _parent_id, ' . self::EMPLOYEE_FIELDS . ' '
                . 'FROM gy_user u LEFT JOIN gy_employee e '
                . 'ON TRIM(e.gy_emp_code) = TRIM(u.gy_user_code) '
                . 'WHERE TRIM(u.gy_user_code) = :employee_code '
                . 'ORDER BY e.gy_emp_id ASC LIMIT 1',
            ':employee_code',
            $employeeCode,
            'gy_emp_id',
            PDO::PARAM_STR
        );
    }

    /** @return array{parent_exists: bool, data: null|array<string, mixed>} */
    public function findDepartmentByAccount(int $accountId): array
    {
        return $this->findSingle(
            'SELECT a.gy_acc_id AS _parent_id, ' . self::DEPARTMENT_FIELDS . ' '
                . 'FROM gy_accounts a LEFT JOIN gy_department d ON a.gy_dept_id = d.id_department '
                . 'WHERE a.gy_acc_id = :account_id LIMIT 1',
            ':account_id',
            $accountId,
            'id_department',
            PDO::PARAM_INT
        );
    }

    /** @return array{parent_exists: bool, data: list<array<string, mixed>>} */
    public function findEmployeesByAccount(int $accountId, int $afterId): array
    {
        return $this->findCollection(
            'SELECT a.gy_acc_id AS _parent_id, ' . self::EMPLOYEE_FIELDS . ' '
                . 'FROM gy_accounts a LEFT JOIN gy_employee e '
                . 'ON a.gy_acc_id = e.gy_acc_id AND e.gy_emp_id > :after_id '
                . 'WHERE a.gy_acc_id = :account_id ORDER BY e.gy_emp_id ASC LIMIT 101',
            ':account_id',
            $accountId,
            $afterId,
            'gy_emp_id'
        );
    }

    /** @return array{parent_exists: bool, data: list<array<string, mixed>>} */
    public function findAccountsByDepartment(int $departmentId, int $afterId): array
    {
        return $this->findCollection(
            'SELECT d.id_department AS _parent_id, ' . self::ACCOUNT_FIELDS . ' '
                . 'FROM gy_department d LEFT JOIN gy_accounts a '
                . 'ON d.id_department = a.gy_dept_id AND a.gy_acc_id > :after_id '
                . 'WHERE d.id_department = :department_id ORDER BY a.gy_acc_id ASC LIMIT 101',
            ':department_id',
            $departmentId,
            $afterId,
            'gy_acc_id'
        );
    }

    /** @return array{parent_exists: bool, data: list<array<string, mixed>>} */
    public function findEmployeesByDepartment(int $departmentId, int $afterId): array
    {
        return $this->findCollection(
            'SELECT d.id_department AS _parent_id, ' . self::EMPLOYEE_FIELDS . ' '
                . 'FROM gy_department d LEFT JOIN '
                . '(gy_accounts a INNER JOIN gy_employee e '
                . 'ON a.gy_acc_id = e.gy_acc_id AND e.gy_emp_id > :after_id) '
                . 'ON d.id_department = a.gy_dept_id '
                . 'WHERE d.id_department = :department_id ORDER BY e.gy_emp_id ASC LIMIT 101',
            ':department_id',
            $departmentId,
            $afterId,
            'gy_emp_id'
        );
    }

    /** @return array{parent_exists: bool, data: list<array<string, mixed>>} */
    public function findSchedulesByEmployeeCode(string $employeeCode, int $afterId): array
    {
        return $this->findCodeCollection(
            'SELECT e.gy_emp_id AS _parent_id, ' . self::SCHEDULE_FIELDS . ' '
                . 'FROM gy_employee e LEFT JOIN gy_schedule s '
                . 'ON e.gy_emp_id = s.gy_emp_id AND s.gy_sched_id > :after_id '
                . 'WHERE TRIM(e.gy_emp_code) = :employee_code '
                . 'ORDER BY s.gy_sched_id ASC LIMIT 101',
            $employeeCode,
            $afterId,
            'gy_sched_id'
        );
    }

    /** @return array{parent_exists: bool, data: list<array<string, mixed>>} */
    public function findScheduleEscalationsByEmployeeCode(string $employeeCode, int $afterId): array
    {
        return $this->findCodeCollection(
            'SELECT e.gy_emp_id AS _parent_id, ' . self::SCHEDULE_ESCALATION_FIELDS . ' '
                . 'FROM gy_employee e LEFT JOIN gy_schedule_escalate s '
                . 'ON TRIM(s.gy_emp_code) = TRIM(e.gy_emp_code) AND s.gy_sched_esc_id > :after_id '
                . 'WHERE TRIM(e.gy_emp_code) = :employee_code '
                . 'ORDER BY s.gy_sched_esc_id ASC LIMIT 101',
            $employeeCode,
            $afterId,
            'gy_sched_esc_id'
        );
    }

    /** @return array{parent_exists: bool, data: null|array<string, mixed>} */
    public function findEmployeeByScheduleEscalationId(int $scheduleEscalationId): array
    {
        return $this->findSingle(
            'SELECT s.gy_sched_esc_id AS _parent_id, ' . self::EMPLOYEE_FIELDS . ' '
                . 'FROM gy_schedule_escalate s LEFT JOIN gy_employee e '
                . 'ON TRIM(e.gy_emp_code) = TRIM(s.gy_emp_code) '
                . 'WHERE s.gy_sched_esc_id = :schedule_escalation_id LIMIT 1',
            ':schedule_escalation_id',
            $scheduleEscalationId,
            'gy_emp_id',
            PDO::PARAM_INT
        );
    }

    /** @return array{parent_exists: bool, data: list<array<string, mixed>>} */
    public function findScheduleRdRequestsByUserCode(string $employeeCode, int $afterId): array
    {
        return $this->findCodeCollection(
            'SELECT u.gy_user_id AS _parent_id, ' . self::SCHEDULE_RD_REQUEST_FIELDS . ' '
                . 'FROM gy_user u LEFT JOIN gy_schedule_rd_request r '
                . 'ON u.gy_user_id = r.gy_user_id AND r.gy_rd_id > :after_id '
                . 'WHERE TRIM(u.gy_user_code) = :employee_code '
                . 'ORDER BY r.gy_rd_id ASC LIMIT 101',
            $employeeCode,
            $afterId,
            'gy_rd_id'
        );
    }

    /** @return array{parent_exists: bool, data: list<array<string, mixed>>} */
    public function findSchedulesCreatedByUserCode(string $employeeCode, int $afterId): array
    {
        return $this->findCodeCollection(
            'SELECT u.gy_user_id AS _parent_id, ' . self::PUBLIC_SCHEDULE_FIELDS . ' '
                . 'FROM gy_user u LEFT JOIN gy_schedule s '
                . 'ON s.gy_sched_by = u.gy_user_id AND s.gy_sched_id > :after_id '
                . 'WHERE TRIM(u.gy_user_code) = TRIM(:employee_code) '
                . 'ORDER BY s.gy_sched_id ASC LIMIT 101',
            $employeeCode,
            $afterId,
            'gy_sched_id'
        );
    }

    /** @return array{parent_exists: bool, data: list<array<string, mixed>>} */
    public function findSubmittedScheduleEscalationsByUserCode(string $employeeCode, int $afterId): array
    {
        return $this->findUserScheduleEscalations($employeeCode, $afterId, 's.gy_req_by = u.gy_user_id');
    }

    /** @return array{parent_exists: bool, data: list<array<string, mixed>>} */
    public function findReceivedScheduleEscalationsByUserCode(string $employeeCode, int $afterId): array
    {
        return $this->findUserScheduleEscalations($employeeCode, $afterId, 's.gy_req_to = u.gy_user_id');
    }

    /** @return array{parent_exists: bool, data: list<array<string, mixed>>} */
    public function findSupervisedScheduleEscalationsByUserCode(string $employeeCode, int $afterId): array
    {
        return $this->findUserScheduleEscalations($employeeCode, $afterId, 's.gy_sup = u.gy_user_id');
    }

    /** @return array{parent_exists: bool, data: null|array<string, mixed>} */
    public function findEmployeeByScheduleId(int $scheduleId): array
    {
        return $this->findSingle(
            'SELECT s.gy_sched_id AS _parent_id, ' . self::EMPLOYEE_FIELDS . ' '
                . 'FROM gy_schedule s LEFT JOIN gy_employee e ON s.gy_emp_id = e.gy_emp_id '
                . 'WHERE s.gy_sched_id = :schedule_id LIMIT 1',
            ':schedule_id',
            $scheduleId,
            'gy_emp_id',
            PDO::PARAM_INT
        );
    }

    /** @return array{parent_exists: bool, data: null|array<string, mixed>} */
    public function findCreatedByUserByScheduleId(int $scheduleId): array
    {
        return $this->findScheduleUser(
            'gy_schedule s',
            's.gy_sched_by = u.gy_user_id',
            's.gy_sched_id',
            ':schedule_id',
            $scheduleId
        );
    }

    /** @return array{parent_exists: bool, data: null|array<string, mixed>} */
    public function findRequestedByUserByScheduleEscalationId(int $scheduleEscalationId): array
    {
        return $this->findScheduleEscalationUser($scheduleEscalationId, 's.gy_req_by = u.gy_user_id');
    }

    /** @return array{parent_exists: bool, data: null|array<string, mixed>} */
    public function findRequestedToUserByScheduleEscalationId(int $scheduleEscalationId): array
    {
        return $this->findScheduleEscalationUser($scheduleEscalationId, 's.gy_req_to = u.gy_user_id');
    }

    /** @return array{parent_exists: bool, data: null|array<string, mixed>} */
    public function findSupervisorUserByScheduleEscalationId(int $scheduleEscalationId): array
    {
        return $this->findScheduleEscalationUser($scheduleEscalationId, 's.gy_sup = u.gy_user_id');
    }

    /** @return array{parent_exists: bool, data: null|array<string, mixed>} */
    public function findTrackerByScheduleRdRequestId(int $scheduleRdRequestId): array
    {
        return $this->findSingle(
            'SELECT r.gy_rd_id AS _parent_id, ' . self::TRACKER_FIELDS . ' '
                . 'FROM gy_schedule_rd_request r LEFT JOIN gy_tracker t '
                . 'ON r.gy_tracker_id = t.gy_tracker_id '
                . 'WHERE r.gy_rd_id = :schedule_rd_request_id LIMIT 1',
            ':schedule_rd_request_id',
            $scheduleRdRequestId,
            'gy_tracker_id',
            PDO::PARAM_INT
        );
    }

    /** @return array{parent_exists: bool, data: null|array<string, mixed>} */
    public function findUserByScheduleRdRequestId(int $scheduleRdRequestId): array
    {
        return $this->findScheduleUser(
            'gy_schedule_rd_request r',
            'r.gy_user_id = u.gy_user_id',
            'r.gy_rd_id',
            ':schedule_rd_request_id',
            $scheduleRdRequestId
        );
    }

    /** @return array{parent_exists: bool, data: list<array<string, mixed>>} */
    private function findUserScheduleEscalations(string $employeeCode, int $afterId, string $join): array
    {
        return $this->findCodeCollection(
            'SELECT u.gy_user_id AS _parent_id, ' . self::PUBLIC_SCHEDULE_ESCALATION_FIELDS . ' '
                . 'FROM gy_user u LEFT JOIN gy_schedule_escalate s ON ' . $join
                . ' AND s.gy_sched_esc_id > :after_id '
                . 'WHERE TRIM(u.gy_user_code) = TRIM(:employee_code) '
                . 'ORDER BY s.gy_sched_esc_id ASC LIMIT 101',
            $employeeCode,
            $afterId,
            'gy_sched_esc_id'
        );
    }

    /** @return array{parent_exists: bool, data: null|array<string, mixed>} */
    private function findScheduleEscalationUser(int $id, string $join): array
    {
        return $this->findScheduleUser(
            'gy_schedule_escalate s',
            $join,
            's.gy_sched_esc_id',
            ':schedule_escalation_id',
            $id
        );
    }

    /** @return array{parent_exists: bool, data: null|array<string, mixed>} */
    private function findScheduleUser(
        string $source,
        string $join,
        string $primaryKey,
        string $placeholder,
        int $id
    ): array {
        return $this->findSingle(
            'SELECT ' . $primaryKey . ' AS _parent_id, u.gy_user_id AS _related_id, '
                . self::PUBLIC_USER_FIELDS . ' FROM ' . $source . ' LEFT JOIN gy_user u ON ' . $join
                . ' WHERE ' . $primaryKey . ' = ' . $placeholder . ' LIMIT 1',
            $placeholder,
            $id,
            '_related_id',
            PDO::PARAM_INT
        );
    }

    /** @return array{parent_exists: bool, data: null|array<string, mixed>} */
    private function findSingle(
        string $query,
        string $idPlaceholder,
        int|string $id,
        string $relatedPrimaryKey,
        int $parameterType
    ): array {
        $statement = $this->prepare($query);
        $statement->bindValue($idPlaceholder, $id, $parameterType);
        $statement->execute();
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return ['parent_exists' => false, 'data' => null];
        }

        $relatedExists = ($row[$relatedPrimaryKey] ?? null) !== null;
        unset($row['_parent_id'], $row['_related_id']);

        return [
            'parent_exists' => true,
            'data' => $relatedExists ? $row : null,
        ];
    }

    /** @return array{parent_exists: bool, data: list<array<string, mixed>>} */
    private function findCollection(
        string $query,
        string $parentPlaceholder,
        int $parentId,
        int $afterId,
        string $relatedPrimaryKey
    ): array {
        $statement = $this->prepare($query);
        $statement->bindValue(':after_id', $afterId, PDO::PARAM_INT);
        $statement->bindValue($parentPlaceholder, $parentId, PDO::PARAM_INT);
        $statement->execute();
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        if ($rows === []) {
            return ['parent_exists' => false, 'data' => []];
        }

        $data = [];

        foreach ($rows as $row) {
            unset($row['_parent_id']);

            if (($row[$relatedPrimaryKey] ?? null) !== null) {
                $data[] = $row;
            }
        }

        return ['parent_exists' => true, 'data' => $data];
    }

    /** @return array{parent_exists: bool, data: list<array<string, mixed>>} */
    private function findCodeCollection(
        string $query,
        string $employeeCode,
        int $afterId,
        string $relatedPrimaryKey
    ): array {
        $statement = $this->prepare($query);
        $statement->bindValue(':after_id', $afterId, PDO::PARAM_INT);
        $statement->bindValue(':employee_code', $employeeCode, PDO::PARAM_STR);
        $statement->execute();
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        if ($rows === []) {
            return ['parent_exists' => false, 'data' => []];
        }

        $data = [];
        foreach ($rows as $row) {
            unset($row['_parent_id']);
            if (($row[$relatedPrimaryKey] ?? null) !== null) {
                $data[] = $row;
            }
        }

        return ['parent_exists' => true, 'data' => $data];
    }

    private function prepare(string $query): \PDOStatement
    {
        $statement = $this->pdo->prepare($query);

        if ($statement === false) {
            throw new RuntimeException('Unable to prepare employee relationship query.');
        }

        return $statement;
    }
}
