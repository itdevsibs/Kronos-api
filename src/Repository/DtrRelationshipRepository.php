<?php

declare(strict_types=1);

namespace Sibs\KronosApi\Repository;

use PDO;
use RuntimeException;

final class DtrRelationshipRepository
{
    private const EMPLOYEE_FIELDS = 'e.gy_emp_id, e.gy_emp_code, e.gy_emp_type, e.gy_emp_schedtype, '
        . 'e.gy_emp_rate, e.gy_emp_email, e.gy_emp_lname, e.gy_emp_fname, e.gy_emp_mname, '
        . 'e.gy_emp_fullname, e.gy_acc_id, e.gy_emp_account, e.gy_emp_supervisor, e.gy_emp_om, '
        . 'e.gy_emp_leave_credits, e.gy_emp_hiredate, e.gy_emp_lastedit, e.gy_lastedit_by, '
        . 'e.gy_work_from, e.gy_gender, e.gy_dob, e.gy_civilstatus, e.gy_assignedloc, '
        . 'e.gy_tagumdate, e.gy_davaodate, e.gy_hybriddate, e.gy_accjoin, e.gy_nhodate, '
        . 'e.gy_fststartdate, e.gy_fstenddate, e.gy_pststartdate, e.gy_pstenddate, '
        . 'e.gy_certification, e.gy_gradbaystartdate, e.gy_gradbayenddate, e.gy_fullgolivedate, '
        . 'e.gy_promotiondate, e.gy_projempdate, e.gy_probempdate, e.gy_regempdate, e.gy_last_working_day';

    private const USER_FIELDS = 'u.gy_user_code, u.ghl_contact_id, u.gy_full_name, u.gy_username, '
        . 'u.gy_user_type, u.gy_user_function, u.gy_head_code, u.gy_script_code, u.gy_user_status';

    private const ACCOUNT_FIELDS = 'a.gy_acc_id, a.gy_acc_name, a.gy_acc_ghl_name, a.gy_dept_id, a.gy_acc_status';

    private const LOG_FIELDS = 'l.gy_log_id, l.gy_log_date, l.gy_log_code, l.gy_log_email, '
        . 'l.gy_log_fullname, l.gy_log_account, l.gy_log_status';

    private const EDIT_LOG_FIELDS = 'l.gy_editlog_id, l.gy_edit_date';

    private const DTR_FIELDS = 'd.dtr_publish_id, d.dtr_year, d.dtr_month, d.dtr_cutoff, d.gy_emp_code, '
        . 'd.dtr_noofhours, d.dtr_lateut, d.dtr_absences, d.dtr_regot, d.dtr_rdreg, d.dtr_rdot, '
        . 'd.dtr_shreg, d.dtr_shot, d.dtr_shrdreg, d.dtr_shrdot, d.dtr_lhreg, d.dtr_lhot, '
        . 'd.dtr_lhrdreg, d.dtr_lhrdot, d.dtr_ndreg, d.dtr_ndregot, d.dtr_ndrdreg, d.dtr_ndrdot, '
        . 'd.dtr_ndsh, d.dtr_ndshot, d.dtr_ndshrd, d.dtr_ndshrdot, d.dtr_ndlh, d.dtr_ndlhot, '
        . 'd.dtr_ndlhrd, d.dtr_ndlhrdot, d.dtr_mdrate, d.dtr_cmpute';

    private const ASSIGNMENT_FIELDS = 't.at_id, t.at_emp_code, t.at_account_id';

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findLogsByEmployeeCode(string $code, int $afterId): array { return $this->employeeCollection($code, $afterId, 'gy_logs l', 'l.gy_emp_id = e.gy_emp_id', self::LOG_FIELDS, 'l.gy_log_id', 'gy_log_id'); }
    public function findEditLogsByEmployeeCode(string $code, int $afterId): array { return $this->employeeCollection($code, $afterId, 'gy_editlog l', 'l.gy_emp_id = e.gy_emp_id', self::EDIT_LOG_FIELDS, 'l.gy_editlog_id', 'gy_editlog_id'); }
    public function findDtrPublicationsByEmployeeCode(string $code, int $afterId): array { return $this->employeeCollection($code, $afterId, 'dtr_publish d', 'TRIM(d.gy_emp_code) = TRIM(e.gy_emp_code)', self::DTR_FIELDS, 'd.dtr_publish_id', 'dtr_publish_id'); }
    public function findAssignmentsByEmployeeCode(string $code, int $afterId): array { return $this->employeeCollection($code, $afterId, 'assign_timesheet t', 'TRIM(t.at_emp_code) = TRIM(e.gy_emp_code)', self::ASSIGNMENT_FIELDS, 't.at_id', 'at_id'); }
    public function findDtrPublicationsByUserCode(string $code, int $afterId): array { return $this->userCollection($code, $afterId, 'dtr_publish d', 'd.dtr_publisher = u.gy_user_id', self::DTR_FIELDS, 'd.dtr_publish_id', 'dtr_publish_id'); }
    public function findAssignmentsAddedByUserCode(string $code, int $afterId): array { return $this->userCollection($code, $afterId, 'assign_timesheet t', 't.at_added_by = u.gy_user_id', self::ASSIGNMENT_FIELDS, 't.at_id', 'at_id'); }
    public function findAssignmentsByAccountId(int $id, int $afterId): array { return $this->accountCollection($id, $afterId); }

    public function findEmployeeByLogId(int $id): array { return $this->singleEmployee('gy_logs l', 'l.gy_emp_id = e.gy_emp_id', 'l.gy_log_id', ':employee_log_id', $id); }
    public function findEmployeeByEditLogId(int $id): array { return $this->singleEmployee('gy_editlog l', 'l.gy_emp_id = e.gy_emp_id', 'l.gy_editlog_id', ':employee_edit_log_id', $id); }
    public function findEmployeeByDtrPublishId(int $id): array { return $this->singleEmployee('dtr_publish d', 'TRIM(d.gy_emp_code) = TRIM(e.gy_emp_code)', 'd.dtr_publish_id', ':dtr_publish_id', $id); }
    public function findPublisherUserByDtrPublishId(int $id): array { return $this->singleUser('dtr_publish d', 'd.dtr_publisher = u.gy_user_id', 'd.dtr_publish_id', ':dtr_publish_id', $id); }
    public function findEmployeeByAssignmentId(int $id): array { return $this->singleEmployee('assign_timesheet t', 'TRIM(t.at_emp_code) = TRIM(e.gy_emp_code)', 't.at_id', ':assignment_id', $id); }
    public function findAccountByAssignmentId(int $id): array { return $this->singleAccount('assign_timesheet t', 't.at_account_id = a.gy_acc_id', 't.at_id', ':assignment_id', $id); }
    public function findAddedByUserByAssignmentId(int $id): array { return $this->singleUser('assign_timesheet t', 't.at_added_by = u.gy_user_id', 't.at_id', ':assignment_id', $id); }

    private function employeeCollection(string $code, int $afterId, string $source, string $join, string $fields, string $key, string $cursor): array
    {
        return $this->collection('SELECT e.gy_emp_id AS _parent_id, ' . $fields . ' FROM gy_employee e LEFT JOIN '
            . $source . ' ON ' . $join . ' AND ' . $key . ' > :after_id WHERE TRIM(e.gy_emp_code) = TRIM(:employee_code)'
            . ' ORDER BY ' . $key . ' ASC LIMIT 101', ':employee_code', $code, PDO::PARAM_STR, $afterId, $cursor);
    }

    private function userCollection(string $code, int $afterId, string $source, string $join, string $fields, string $key, string $cursor): array
    {
        return $this->collection('SELECT u.gy_user_id AS _parent_id, ' . $fields . ' FROM gy_user u LEFT JOIN '
            . $source . ' ON ' . $join . ' AND ' . $key . ' > :after_id WHERE TRIM(u.gy_user_code) = TRIM(:employee_code)'
            . ' ORDER BY ' . $key . ' ASC LIMIT 101', ':employee_code', $code, PDO::PARAM_STR, $afterId, $cursor);
    }

    private function accountCollection(int $id, int $afterId): array
    {
        return $this->collection('SELECT a.gy_acc_id AS _parent_id, ' . self::ASSIGNMENT_FIELDS
            . ' FROM gy_accounts a LEFT JOIN assign_timesheet t ON t.at_account_id = a.gy_acc_id'
            . ' AND t.at_id > :after_id WHERE a.gy_acc_id = :account_id ORDER BY t.at_id ASC LIMIT 101',
            ':account_id', $id, PDO::PARAM_INT, $afterId, 'at_id');
    }

    private function singleEmployee(string $source, string $join, string $key, string $placeholder, int $id): array
    {
        return $this->single('SELECT ' . $key . ' AS _parent_id, e.gy_emp_id AS _related_id, ' . self::EMPLOYEE_FIELDS
            . ' FROM ' . $source . ' LEFT JOIN gy_employee e ON ' . $join . ' WHERE ' . $key . ' = ' . $placeholder
            . ' LIMIT 1', $placeholder, $id);
    }

    private function singleUser(string $source, string $join, string $key, string $placeholder, int $id): array
    {
        return $this->single('SELECT ' . $key . ' AS _parent_id, u.gy_user_id AS _related_id, ' . self::USER_FIELDS
            . ' FROM ' . $source . ' LEFT JOIN gy_user u ON ' . $join . ' WHERE ' . $key . ' = ' . $placeholder
            . ' LIMIT 1', $placeholder, $id);
    }

    private function singleAccount(string $source, string $join, string $key, string $placeholder, int $id): array
    {
        return $this->single('SELECT ' . $key . ' AS _parent_id, a.gy_acc_id AS _related_id, ' . self::ACCOUNT_FIELDS
            . ' FROM ' . $source . ' LEFT JOIN gy_accounts a ON ' . $join . ' WHERE ' . $key . ' = ' . $placeholder
            . ' LIMIT 1', $placeholder, $id);
    }

    private function single(string $sql, string $placeholder, int $id): array
    {
        $statement = $this->prepare($sql);
        $statement->bindValue($placeholder, $id, PDO::PARAM_INT);
        $statement->execute();
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) { return ['parent_exists' => false, 'data' => null]; }
        $related = ($row['_related_id'] ?? null) !== null;
        unset($row['_parent_id'], $row['_related_id']);
        return ['parent_exists' => true, 'data' => $related ? $row : null];
    }

    private function collection(string $sql, string $placeholder, int|string $parent, int $type, int $afterId, string $cursor): array
    {
        $statement = $this->prepare($sql);
        $statement->bindValue($placeholder, $parent, $type);
        $statement->bindValue(':after_id', $afterId, PDO::PARAM_INT);
        $statement->execute();
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        if ($rows === []) { return ['parent_exists' => false, 'data' => []]; }
        $data = [];
        foreach ($rows as $row) { unset($row['_parent_id']); if (($row[$cursor] ?? null) !== null) { $data[] = $row; } }
        return ['parent_exists' => true, 'data' => $data];
    }

    private function prepare(string $sql): \PDOStatement
    {
        $statement = $this->pdo->prepare($sql);
        if ($statement === false) { throw new RuntimeException('Unable to prepare DTR relationship query.'); }
        return $statement;
    }
}
