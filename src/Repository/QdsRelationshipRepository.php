<?php

declare(strict_types=1);

namespace Sibs\KronosApi\Repository;

use PDO;
use PDOStatement;
use RuntimeException;

final class QdsRelationshipRepository
{
    private const ASSIGNMENT_FIELDS = 'q.qag_id, q.qag_sibsid, q.qag_account';

    private const EMPLOYEE_FIELDS = 'e.gy_emp_id, e.gy_emp_code, e.gy_emp_type, e.gy_emp_schedtype, '
        . 'e.gy_emp_rate, e.gy_emp_email, e.gy_emp_lname, e.gy_emp_fname, e.gy_emp_mname, '
        . 'e.gy_emp_fullname, e.gy_acc_id, e.gy_emp_account, e.gy_emp_supervisor, e.gy_emp_om, '
        . 'e.gy_emp_leave_credits, e.gy_emp_hiredate, e.gy_emp_lastedit, e.gy_lastedit_by, '
        . 'e.gy_work_from, e.gy_gender, e.gy_dob, e.gy_civilstatus, e.gy_assignedloc, '
        . 'e.gy_tagumdate, e.gy_davaodate, e.gy_hybriddate, e.gy_accjoin, e.gy_nhodate, '
        . 'e.gy_fststartdate, e.gy_fstenddate, e.gy_pststartdate, e.gy_pstenddate, '
        . 'e.gy_certification, e.gy_gradbaystartdate, e.gy_gradbayenddate, e.gy_fullgolivedate, '
        . 'e.gy_promotiondate, e.gy_projempdate, e.gy_probempdate, e.gy_regempdate, e.gy_last_working_day';

    private const ACCOUNT_FIELDS = 'a.gy_acc_id, a.gy_acc_name, a.gy_acc_ghl_name, '
        . 'a.gy_dept_id, a.gy_acc_status';

    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array{parent_exists: bool, data: list<array<string, mixed>>} */
    public function findAssignmentsByEmployeeCode(string $code, int $afterId): array
    {
        $sql = 'SELECT e.gy_emp_id AS _parent_id, ' . self::ASSIGNMENT_FIELDS
            . ' FROM gy_employee e LEFT JOIN qds_assign_group q'
            . ' ON TRIM(q.qag_sibsid) = TRIM(e.gy_emp_code) AND q.qag_id > :after_id'
            . ' WHERE TRIM(e.gy_emp_code) = TRIM(:employee_code)'
            . ' ORDER BY q.qag_id ASC LIMIT 101';

        return $this->collection($sql, ':employee_code', $code, PDO::PARAM_STR, $afterId);
    }

    /** @return array{parent_exists: bool, data: list<array<string, mixed>>} */
    public function findAssignmentsByAccountId(int $accountId, int $afterId): array
    {
        $sql = 'SELECT a.gy_acc_id AS _parent_id, ' . self::ASSIGNMENT_FIELDS
            . ' FROM gy_accounts a LEFT JOIN qds_assign_group q'
            . ' ON q.qag_account = a.gy_acc_id AND q.qag_id > :after_id'
            . ' WHERE a.gy_acc_id = :account_id ORDER BY q.qag_id ASC LIMIT 101';

        return $this->collection($sql, ':account_id', $accountId, PDO::PARAM_INT, $afterId);
    }

    /** @return array{parent_exists: bool, data: array<string, mixed>|null} */
    public function findEmployeeByAssignmentId(int $assignmentId): array
    {
        return $this->single(
            'SELECT q.qag_id AS _parent_id, e.gy_emp_id AS _related_id, ' . self::EMPLOYEE_FIELDS
                . ' FROM qds_assign_group q LEFT JOIN gy_employee e'
                . ' ON TRIM(q.qag_sibsid) = TRIM(e.gy_emp_code)'
                . ' WHERE q.qag_id = :qds_assign_group_id LIMIT 1',
            $assignmentId
        );
    }

    /** @return array{parent_exists: bool, data: array<string, mixed>|null} */
    public function findAccountByAssignmentId(int $assignmentId): array
    {
        return $this->single(
            'SELECT q.qag_id AS _parent_id, a.gy_acc_id AS _related_id, ' . self::ACCOUNT_FIELDS
                . ' FROM qds_assign_group q LEFT JOIN gy_accounts a ON q.qag_account = a.gy_acc_id'
                . ' WHERE q.qag_id = :qds_assign_group_id LIMIT 1',
            $assignmentId
        );
    }

    /** @return array{parent_exists: bool, data: list<array<string, mixed>>} */
    private function collection(
        string $sql,
        string $placeholder,
        int|string $parent,
        int $type,
        int $afterId
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
            if (($row['qag_id'] ?? null) !== null) {
                $data[] = $row;
            }
        }

        return ['parent_exists' => true, 'data' => $data];
    }

    /** @return array{parent_exists: bool, data: array<string, mixed>|null} */
    private function single(string $sql, int $assignmentId): array
    {
        $statement = $this->prepare($sql);
        $statement->bindValue(':qds_assign_group_id', $assignmentId, PDO::PARAM_INT);
        $statement->execute();
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return ['parent_exists' => false, 'data' => null];
        }

        $relatedExists = ($row['_related_id'] ?? null) !== null;
        unset($row['_parent_id'], $row['_related_id']);

        return ['parent_exists' => true, 'data' => $relatedExists ? $row : null];
    }

    private function prepare(string $sql): PDOStatement
    {
        $statement = $this->pdo->prepare($sql);
        if ($statement === false) {
            throw new RuntimeException('Unable to prepare QDS relationship query.');
        }

        return $statement;
    }
}
