<?php

declare(strict_types=1);

namespace Sibs\KronosApi\Repository;

use PDO;
use RuntimeException;

final class LeaveRelationshipRepository
{
    private const USER_FIELDS = 'u.gy_user_code, u.ghl_contact_id, u.gy_full_name, u.gy_username, '
        . 'u.gy_user_type, u.gy_user_function, u.gy_head_code, u.gy_script_code, u.gy_user_status';

    private const ACCOUNT_FIELDS = 'a.gy_acc_id, a.gy_acc_name, a.gy_acc_ghl_name, a.gy_dept_id, a.gy_acc_status';

    private const EMPLOYEE_FIELDS = 'e.gy_emp_id, e.gy_emp_code, e.gy_emp_type, e.gy_emp_schedtype, '
        . 'e.gy_emp_rate, e.gy_emp_email, e.gy_emp_lname, e.gy_emp_fname, e.gy_emp_mname, '
        . 'e.gy_emp_fullname, e.gy_acc_id, e.gy_emp_account, e.gy_emp_supervisor, e.gy_emp_om, '
        . 'e.gy_emp_leave_credits, '
        . 'e.gy_emp_hiredate, e.gy_emp_lastedit, e.gy_lastedit_by, e.gy_work_from, e.gy_gender, '
        . 'e.gy_dob, e.gy_civilstatus, e.gy_assignedloc, e.gy_tagumdate, e.gy_davaodate, '
        . 'e.gy_hybriddate, e.gy_accjoin, e.gy_nhodate, e.gy_fststartdate, e.gy_fstenddate, '
        . 'e.gy_pststartdate, e.gy_pstenddate, e.gy_certification, e.gy_gradbaystartdate, '
        . 'e.gy_gradbayenddate, e.gy_fullgolivedate, e.gy_promotiondate, e.gy_projempdate, '
        . 'e.gy_probempdate, e.gy_regempdate, e.gy_last_working_day';

    private const LEAVE_FIELDS = 'l.gy_leave_id, l.gy_leave_filed, l.gy_leave_type, l.gy_leave_paid, '
        . 'l.gy_leave_day, l.gy_leave_period_one, l.gy_leave_period_two, l.gy_emp_rate, '
        . 'l.gy_old_credits, l.gy_new_credits, l.gy_leave_date_from, l.gy_leave_date_to, '
        . 'l.gy_leave_reason, l.gy_leave_status, l.gy_leave_date_approved, l.gy_leave_remarks, '
        . 'l.gy_leave_attachment, l.gy_publish, l.msg_usercode';

    private const AVAILABILITY_FIELDS = 'v.gy_leave_avail_id, v.gy_leave_avail_date, '
        . 'v.gy_leave_avail_dateto, v.gy_leave_avail_plotted, v.gy_leave_avail_approved, '
        . 'v.gy_leave_avail_justify';

    private const HISTORY_FIELDS = 'h.lch_id, h.lch_emp_code, h.lch_date, h.lch_old_credits, '
        . 'h.lch_new_credits, h.lch_type, h.lch_trigger_date_type, h.lch_trigger_amount, '
        . 'h.lch_trigger_affected_type, h.lch_updated_by, h.lch_daterecorded, h.lch_operation';

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findLeaveCreditHistoryByEmployeeCode(string $code, int $afterId): array
    {
        return $this->codeCollection(
            'SELECT e.gy_emp_id AS _parent_id, ' . self::HISTORY_FIELDS
                . ' FROM gy_employee e LEFT JOIN leave_credits_history h'
                . ' ON TRIM(h.lch_emp_code) = TRIM(e.gy_emp_code) AND h.lch_id > :after_id'
                . ' WHERE TRIM(e.gy_emp_code) = TRIM(:employee_code) ORDER BY h.lch_id ASC LIMIT 101',
            $code,
            $afterId,
            'lch_id'
        );
    }

    public function findLeavesByUserCode(string $code, int $afterId): array
    {
        return $this->userCollection($code, $afterId, 'gy_leave l', 'l.gy_user_id = u.gy_user_id', self::LEAVE_FIELDS, 'l.gy_leave_id', 'gy_leave_id');
    }

    public function findApprovedLeavesByUserCode(string $code, int $afterId): array
    {
        return $this->userCollection($code, $afterId, 'gy_leave l', 'l.gy_leave_approver = u.gy_user_id', self::LEAVE_FIELDS, 'l.gy_leave_id', 'gy_leave_id');
    }

    public function findLeaveAvailabilityByUserCode(string $code, int $afterId): array
    {
        return $this->userCollection($code, $afterId, 'gy_leave_available v', 'v.gy_user_id = u.gy_user_id', self::AVAILABILITY_FIELDS, 'v.gy_leave_avail_id', 'gy_leave_avail_id');
    }

    public function findLeaveCreditHistoryUpdatedByUserCode(string $code, int $afterId): array
    {
        return $this->userCollection($code, $afterId, 'leave_credits_history h', 'TRIM(h.lch_updated_by) = TRIM(u.gy_user_code)', self::HISTORY_FIELDS, 'h.lch_id', 'lch_id');
    }

    public function findLeavesByAccountId(int $id, int $afterId): array
    {
        return $this->accountCollection($id, $afterId, 'gy_leave l', 'l.gy_acc_id = a.gy_acc_id', self::LEAVE_FIELDS, 'l.gy_leave_id', 'gy_leave_id');
    }

    public function findLeaveAvailabilityByAccountId(int $id, int $afterId): array
    {
        return $this->accountCollection($id, $afterId, 'gy_leave_available v', 'v.gy_acc_id = a.gy_acc_id', self::AVAILABILITY_FIELDS, 'v.gy_leave_avail_id', 'gy_leave_avail_id');
    }

    public function findUserByLeaveId(int $id): array
    {
        return $this->singleUser('gy_leave l', 'l.gy_user_id = u.gy_user_id', 'l.gy_leave_id', ':leave_id', $id);
    }

    public function findAccountByLeaveId(int $id): array
    {
        return $this->singleAccount('gy_leave l', 'l.gy_acc_id = a.gy_acc_id', 'l.gy_leave_id', ':leave_id', $id);
    }

    public function findApproverUserByLeaveId(int $id): array
    {
        return $this->singleUser('gy_leave l', 'l.gy_leave_approver = u.gy_user_id', 'l.gy_leave_id', ':leave_id', $id);
    }

    public function findUserByLeaveAvailabilityId(int $id): array
    {
        return $this->singleUser('gy_leave_available v', 'v.gy_user_id = u.gy_user_id', 'v.gy_leave_avail_id', ':leave_availability_id', $id);
    }

    public function findAccountByLeaveAvailabilityId(int $id): array
    {
        return $this->singleAccount('gy_leave_available v', 'v.gy_acc_id = a.gy_acc_id', 'v.gy_leave_avail_id', ':leave_availability_id', $id);
    }

    public function findEmployeeByLeaveCreditHistoryId(int $id): array
    {
        return $this->single(
            'SELECT h.lch_id AS _parent_id, e.gy_emp_id AS _related_id, ' . self::EMPLOYEE_FIELDS
                . ' FROM leave_credits_history h LEFT JOIN gy_employee e'
                . ' ON TRIM(h.lch_emp_code) = TRIM(e.gy_emp_code)'
                . ' WHERE h.lch_id = :leave_credit_history_id LIMIT 1',
            ':leave_credit_history_id',
            $id
        );
    }

    public function findUpdatedByUserByLeaveCreditHistoryId(int $id): array
    {
        return $this->singleUser(
            'leave_credits_history h',
            'TRIM(h.lch_updated_by) = TRIM(u.gy_user_code)',
            'h.lch_id',
            ':leave_credit_history_id',
            $id
        );
    }

    private function singleUser(string $source, string $join, string $key, string $placeholder, int $id): array
    {
        return $this->single(
            'SELECT ' . $key . ' AS _parent_id, u.gy_user_id AS _related_id, ' . self::USER_FIELDS
                . ' FROM ' . $source . ' LEFT JOIN gy_user u ON ' . $join
                . ' WHERE ' . $key . ' = ' . $placeholder . ' LIMIT 1',
            $placeholder,
            $id
        );
    }

    private function singleAccount(string $source, string $join, string $key, string $placeholder, int $id): array
    {
        return $this->single(
            'SELECT ' . $key . ' AS _parent_id, a.gy_acc_id AS _related_id, ' . self::ACCOUNT_FIELDS
                . ' FROM ' . $source . ' LEFT JOIN gy_accounts a ON ' . $join
                . ' WHERE ' . $key . ' = ' . $placeholder . ' LIMIT 1',
            $placeholder,
            $id
        );
    }

    private function single(string $sql, string $placeholder, int $id): array
    {
        $statement = $this->prepare($sql);
        $statement->bindValue($placeholder, $id, PDO::PARAM_INT);
        $statement->execute();
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return ['parent_exists' => false, 'data' => null];
        }
        $relatedExists = ($row['_related_id'] ?? null) !== null;
        unset($row['_parent_id'], $row['_related_id']);
        return ['parent_exists' => true, 'data' => $relatedExists ? $row : null];
    }

    private function userCollection(string $code, int $afterId, string $source, string $join, string $fields, string $key, string $cursor): array
    {
        return $this->codeCollection(
            'SELECT u.gy_user_id AS _parent_id, ' . $fields . ' FROM gy_user u LEFT JOIN ' . $source
                . ' ON ' . $join . ' AND ' . $key . ' > :after_id'
                . ' WHERE TRIM(u.gy_user_code) = TRIM(:employee_code) ORDER BY ' . $key . ' ASC LIMIT 101',
            $code,
            $afterId,
            $cursor
        );
    }

    private function accountCollection(int $id, int $afterId, string $source, string $join, string $fields, string $key, string $cursor): array
    {
        return $this->collection(
            'SELECT a.gy_acc_id AS _parent_id, ' . $fields . ' FROM gy_accounts a LEFT JOIN ' . $source
                . ' ON ' . $join . ' AND ' . $key . ' > :after_id'
                . ' WHERE a.gy_acc_id = :account_id ORDER BY ' . $key . ' ASC LIMIT 101',
            ':account_id',
            $id,
            PDO::PARAM_INT,
            $afterId,
            $cursor
        );
    }

    private function codeCollection(string $sql, string $code, int $afterId, string $cursor): array
    {
        return $this->collection($sql, ':employee_code', $code, PDO::PARAM_STR, $afterId, $cursor);
    }

    private function collection(string $sql, string $placeholder, int|string $parent, int $type, int $afterId, string $cursor): array
    {
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
            throw new RuntimeException('Unable to prepare leave relationship query.');
        }
        return $statement;
    }
}
