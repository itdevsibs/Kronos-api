<?php

declare(strict_types=1);

namespace Sibs\KronosApi\Repository;

use PDO;
use PDOStatement;
use RuntimeException;

final class ToolRelationshipRepository
{
    private const TOOL_FIELDS = 't.tool_id, t.tool_name, t.tool_status';
    private const DETAIL_FIELDS = 'd.toold_id, d.toold_sortid, d.toold_listid, '
        . 'd.toold_label, d.toold_type, d.toold_status';
    private const DATA_FIELDS = 'td.td_id, td.td_tooldid, td.td_emp_code, td.td_value, td.td_status';
    private const EMPLOYEE_FIELDS = 'e.gy_emp_id, e.gy_emp_code, e.gy_emp_type, e.gy_emp_schedtype, '
        . 'e.gy_emp_rate, e.gy_emp_email, e.gy_emp_lname, e.gy_emp_fname, e.gy_emp_mname, '
        . 'e.gy_emp_fullname, e.gy_acc_id, e.gy_emp_account, e.gy_emp_supervisor, e.gy_emp_om, '
        . 'e.gy_emp_leave_credits, e.gy_emp_hiredate, e.gy_emp_lastedit, e.gy_lastedit_by, '
        . 'e.gy_work_from, e.gy_gender, e.gy_dob, e.gy_civilstatus, e.gy_assignedloc, '
        . 'e.gy_tagumdate, e.gy_davaodate, e.gy_hybriddate, e.gy_accjoin, e.gy_nhodate, '
        . 'e.gy_fststartdate, e.gy_fstenddate, e.gy_pststartdate, e.gy_pstenddate, '
        . 'e.gy_certification, e.gy_gradbaystartdate, e.gy_gradbayenddate, e.gy_fullgolivedate, '
        . 'e.gy_promotiondate, e.gy_projempdate, e.gy_probempdate, e.gy_regempdate, e.gy_last_working_day';

    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array{parent_exists: bool, data: list<array<string, mixed>>} */
    public function findDataByEmployeeCode(string $employeeCode, int $afterId): array
    {
        $sql = 'SELECT e.gy_emp_id AS _parent_id, ' . self::DATA_FIELDS
            . ' FROM gy_employee e LEFT JOIN tool_data td'
            . ' ON TRIM(td.td_emp_code) = TRIM(e.gy_emp_code) AND td.td_id > :after_id'
            . ' WHERE TRIM(e.gy_emp_code) = TRIM(:employee_code)'
            . ' ORDER BY td.td_id ASC LIMIT 101';

        return $this->collection($sql, ':employee_code', $employeeCode, PDO::PARAM_STR, $afterId, 'td_id');
    }

    /** @return array{parent_exists: bool, data: list<array<string, mixed>>} */
    public function findDetailsByToolId(int $toolId, int $afterId): array
    {
        $sql = 'SELECT t.tool_id AS _parent_id, ' . self::DETAIL_FIELDS
            . ' FROM tool_list t LEFT JOIN tool_details d'
            . ' ON d.toold_listid = t.tool_id AND d.toold_id > :after_id'
            . ' WHERE t.tool_id = :parent_id ORDER BY d.toold_id ASC LIMIT 101';

        return $this->collection($sql, ':parent_id', $toolId, PDO::PARAM_INT, $afterId, 'toold_id');
    }

    /** @return array{parent_exists: bool, data: array<string, mixed>|null} */
    public function findToolByDetailId(int $toolDetailId): array
    {
        $sql = 'SELECT d.toold_id AS _parent_id, t.tool_id AS _related_id, ' . self::TOOL_FIELDS
            . ' FROM tool_details d LEFT JOIN tool_list t ON d.toold_listid = t.tool_id'
            . ' WHERE d.toold_id = :parent_id LIMIT 1';

        return $this->single($sql, $toolDetailId);
    }

    /** @return array{parent_exists: bool, data: list<array<string, mixed>>} */
    public function findDataByToolDetailId(int $toolDetailId, int $afterId): array
    {
        $sql = 'SELECT d.toold_id AS _parent_id, ' . self::DATA_FIELDS
            . ' FROM tool_details d LEFT JOIN tool_data td'
            . ' ON td.td_tooldid = d.toold_id AND td.td_id > :after_id'
            . ' WHERE d.toold_id = :parent_id ORDER BY td.td_id ASC LIMIT 101';

        return $this->collection($sql, ':parent_id', $toolDetailId, PDO::PARAM_INT, $afterId, 'td_id');
    }

    /** @return array{parent_exists: bool, data: array<string, mixed>|null} */
    public function findDetailByDataId(int $toolDataId): array
    {
        $sql = 'SELECT td.td_id AS _parent_id, d.toold_id AS _related_id, ' . self::DETAIL_FIELDS
            . ' FROM tool_data td LEFT JOIN tool_details d ON td.td_tooldid = d.toold_id'
            . ' WHERE td.td_id = :parent_id LIMIT 1';

        return $this->single($sql, $toolDataId);
    }

    /** @return array{parent_exists: bool, data: array<string, mixed>|null} */
    public function findEmployeeByDataId(int $toolDataId): array
    {
        $sql = 'SELECT td.td_id AS _parent_id, e.gy_emp_id AS _related_id, ' . self::EMPLOYEE_FIELDS
            . ' FROM tool_data td LEFT JOIN gy_employee e'
            . ' ON TRIM(td.td_emp_code) = TRIM(e.gy_emp_code)'
            . ' WHERE td.td_id = :parent_id LIMIT 1';

        return $this->single($sql, $toolDataId);
    }

    /** @return array{parent_exists: bool, data: list<array<string, mixed>>} */
    private function collection(
        string $sql,
        string $parentPlaceholder,
        int|string $parent,
        int $parentType,
        int $afterId,
        string $resourceKey
    ): array {
        $statement = $this->prepare($sql);
        $statement->bindValue($parentPlaceholder, $parent, $parentType);
        $statement->bindValue(':after_id', $afterId, PDO::PARAM_INT);
        $statement->execute();
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        if ($rows === []) {
            return ['parent_exists' => false, 'data' => []];
        }

        $data = [];
        foreach ($rows as $row) {
            unset($row['_parent_id']);
            if (($row[$resourceKey] ?? null) !== null) {
                $data[] = $row;
            }
        }

        return ['parent_exists' => true, 'data' => $data];
    }

    /** @return array{parent_exists: bool, data: array<string, mixed>|null} */
    private function single(string $sql, int $parentId): array
    {
        $statement = $this->prepare($sql);
        $statement->bindValue(':parent_id', $parentId, PDO::PARAM_INT);
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
            throw new RuntimeException('Unable to prepare generic tool relationship query.');
        }

        return $statement;
    }
}
