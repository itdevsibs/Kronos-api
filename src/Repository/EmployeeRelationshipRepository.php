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

        unset($row['_parent_id']);

        return [
            'parent_exists' => true,
            'data' => ($row[$relatedPrimaryKey] ?? null) === null ? null : $row,
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

    private function prepare(string $query): \PDOStatement
    {
        $statement = $this->pdo->prepare($query);

        if ($statement === false) {
            throw new RuntimeException('Unable to prepare employee relationship query.');
        }

        return $statement;
    }
}
