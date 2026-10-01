<?php

declare(strict_types=1);

namespace Sibs\KronosApi\Repository;

use PDO;
use RuntimeException;

final class IndividualResourceRepository
{
    private const EMPLOYEE_QUERY = 'SELECT gy_emp_id, gy_emp_code, gy_emp_type, gy_emp_schedtype, gy_emp_rate, '
        . 'gy_emp_email, gy_emp_lname, gy_emp_fname, gy_emp_mname, gy_emp_fullname, gy_acc_id, '
        . 'gy_emp_account, gy_emp_supervisor, gy_emp_om, gy_emp_leave_credits, gy_emp_hiredate, '
        . 'gy_emp_lastedit, gy_lastedit_by, gy_work_from, gy_gender, gy_dob, gy_civilstatus, '
        . 'gy_assignedloc, gy_tagumdate, gy_davaodate, gy_hybriddate, gy_accjoin, gy_nhodate, '
        . 'gy_fststartdate, gy_fstenddate, gy_pststartdate, gy_pstenddate, gy_certification, '
        . 'gy_gradbaystartdate, gy_gradbayenddate, gy_fullgolivedate, gy_promotiondate, '
        . 'gy_projempdate, gy_probempdate, gy_regempdate, gy_last_working_day '
        . 'FROM gy_employee WHERE TRIM(gy_emp_code) = TRIM(:employee_code) LIMIT 1';

    private const ACCOUNT_QUERY = 'SELECT gy_acc_id, gy_acc_name, gy_acc_ghl_name, gy_dept_id, gy_acc_status '
        . 'FROM gy_accounts WHERE gy_acc_id = :account_id LIMIT 1';

    private const DEPARTMENT_QUERY = 'SELECT id_department, name_department FROM gy_department '
        . 'WHERE id_department = :department_id LIMIT 1';

    private const USER_QUERY = 'SELECT gy_user_id, gy_user_code, ghl_contact_id, gy_full_name, gy_username, '
        . 'gy_user_type, gy_user_function, gy_head_code, gy_script_code, gy_user_status '
        . 'FROM gy_user WHERE TRIM(gy_user_code) = TRIM(:employee_code) LIMIT 1';

    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<string, mixed>|null */
    public function findEmployeeByCode(string $employeeCode): ?array
    {
        return $this->findOne(self::EMPLOYEE_QUERY, ':employee_code', $employeeCode, PDO::PARAM_STR);
    }

    /** @return array<string, mixed>|null */
    public function findAccountById(int $accountId): ?array
    {
        return $this->findOne(self::ACCOUNT_QUERY, ':account_id', $accountId, PDO::PARAM_INT);
    }

    /** @return array<string, mixed>|null */
    public function findDepartmentById(int $departmentId): ?array
    {
        return $this->findOne(self::DEPARTMENT_QUERY, ':department_id', $departmentId, PDO::PARAM_INT);
    }

    /** @return array<string, mixed>|null */
    public function findUserByEmployeeCode(string $employeeCode): ?array
    {
        return $this->findOne(self::USER_QUERY, ':employee_code', $employeeCode, PDO::PARAM_STR);
    }

    /** @return array<string, mixed>|null */
    private function findOne(string $query, string $placeholder, int|string $value, int $type): ?array
    {
        $statement = $this->pdo->prepare($query);

        if ($statement === false) {
            throw new RuntimeException('Unable to prepare individual resource query.');
        }

        $statement->bindValue($placeholder, $value, $type);
        $statement->execute();
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }
}
