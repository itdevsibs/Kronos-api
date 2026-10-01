<?php

declare(strict_types=1);

namespace Sibs\KronosApi\Repository;

use PDO;
use PDOStatement;
use RuntimeException;

final class EmployeeDirectoryRepository
{
    private const SITE_EXPRESSION = "CASE "
        . "WHEN TRIM(CAST(e.gy_assignedloc AS CHAR)) = '0' THEN 'Tagum' "
        . "WHEN TRIM(CAST(e.gy_assignedloc AS CHAR)) = '1' THEN 'Davao' "
        . "WHEN TRIM(CAST(e.gy_assignedloc AS CHAR)) = '2' THEN 'Both Tagum and Davao' "
        . "WHEN TRIM(CAST(e.gy_assignedloc AS CHAR)) = '3' THEN 'Hybrid' "
        . "ELSE '' END";

    private const ACCOUNT_EXPRESSION = "COALESCE(NULLIF(TRIM(a.gy_acc_name), ''), "
        . "NULLIF(TRIM(e.gy_emp_account), ''), "
        . "NULLIF(TRIM(a.gy_acc_ghl_name), ''))";

    private const FROM_JOINS = 'FROM gy_employee e '
        . 'INNER JOIN gy_user u ON TRIM(u.gy_user_code) = TRIM(e.gy_emp_code) '
        . 'INNER JOIN gy_accounts a ON e.gy_acc_id = a.gy_acc_id '
        . 'LEFT JOIN gy_department d ON a.gy_dept_id = d.id_department '
        . 'LEFT JOIN gy_user managerUser ON e.gy_emp_supervisor = managerUser.gy_user_id '
        . 'LEFT JOIN gy_employee managerEmployee '
        . 'ON TRIM(managerEmployee.gy_emp_code) = TRIM(managerUser.gy_user_code) ';

    private const SELECT_COLUMNS = 'e.gy_emp_code AS sibsId, '
        . 'e.gy_emp_fname AS firstName, '
        . 'e.gy_emp_mname AS middleName, '
        . 'e.gy_emp_lname AS lastName, '
        . 'e.gy_emp_email AS email, '
        . 'e.gy_gender AS gender, '
        . 'e.gy_dob AS birthdate, '
        . 'e.gy_civilstatus AS civilStatus, '
        . 'e.gy_contact_num AS contact, '
        . 'e.gy_emp_hiredate AS hireDate, '
        . 'e.gy_assignedloc AS gy_assignedloc, '
        . self::SITE_EXPRESSION . ' AS site, '
        . 'e.gy_nhodate AS nhodate, '
        . self::ACCOUNT_EXPRESSION . ' AS account, '
        . 'a.gy_acc_id AS accountId, '
        . 'a.gy_dept_id AS departmentId, '
        . 'd.name_department AS department, '
        . 'u.gy_full_name AS userFullName, '
        . 'u.gy_user_type AS userType, '
        . 'u.gy_user_status AS userStatus, '
        . 'managerEmployee.gy_emp_code AS accountManagerSibsId, '
        . 'managerEmployee.gy_emp_fname AS accountManagerFirstName, '
        . 'managerEmployee.gy_emp_mname AS accountManagerMiddleName, '
        . 'managerEmployee.gy_emp_lname AS accountManagerLastName ';

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @param list<int> $accountIds
     * @return array{data: list<array<string, mixed>>, total: int}
     */
    public function findPage(
        int $page,
        int $limit,
        ?string $search,
        ?int $departmentId,
        array $accountIds
    ): array {
        $where = [
            'u.gy_user_status = 0',
            'a.gy_acc_status = 0',
        ];
        $bindings = [];

        if ($departmentId !== null) {
            $where[] = 'a.gy_dept_id = :department_id';
            $bindings[':department_id'] = [$departmentId, PDO::PARAM_INT];
        }

        if ($accountIds !== []) {
            $placeholders = [];
            foreach ($accountIds as $index => $accountId) {
                $placeholder = ':account_id_' . $index;
                $placeholders[] = $placeholder;
                $bindings[$placeholder] = [$accountId, PDO::PARAM_INT];
            }
            $where[] = 'a.gy_acc_id IN (' . implode(', ', $placeholders) . ')';
        }

        $searchTerm = $search === null || $search === ''
            ? null
            : '%' . $search . '%';

        if ($searchTerm !== null) {
            $searchFields = [
                'e.gy_emp_code',
                'e.gy_emp_fname',
                'e.gy_emp_mname',
                'e.gy_emp_lname',
                'e.gy_emp_email',
                'a.gy_acc_name',
                'e.gy_emp_account',
                'a.gy_acc_ghl_name',
                'd.name_department',
                'managerEmployee.gy_emp_code',
                'managerEmployee.gy_emp_fname',
                'managerEmployee.gy_emp_mname',
                'managerEmployee.gy_emp_lname',
                self::SITE_EXPRESSION,
            ];
            $searchConditions = [];
            foreach ($searchFields as $index => $field) {
                $placeholder = ':search_filter_' . $index;
                $searchConditions[] = $field . ' LIKE ' . $placeholder;
                $bindings[$placeholder] = [$searchTerm, PDO::PARAM_STR];
            }
            $where[] = '(' . implode(' OR ', $searchConditions) . ')';
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);
        $countStatement = $this->prepare(
            'SELECT COUNT(*) AS total ' . self::FROM_JOINS . $whereSql
        );
        $this->bindAll($countStatement, $bindings);
        $countStatement->execute();
        $countRow = $countStatement->fetch(PDO::FETCH_ASSOC);
        $total = is_array($countRow) ? (int) ($countRow['total'] ?? 0) : 0;

        $dataBindings = $bindings;
        $orderBy = 'ORDER BY u.gy_user_id DESC';
        if ($searchTerm !== null) {
            $rankFields = [
                'e.gy_emp_fname',
                'e.gy_emp_mname',
                'e.gy_emp_lname',
                "CONCAT_WS(' ', e.gy_emp_fname, e.gy_emp_mname, e.gy_emp_lname)",
                "CONCAT_WS(', ', e.gy_emp_lname, CONCAT_WS(' ', e.gy_emp_fname, e.gy_emp_mname))",
            ];
            $rankConditions = [];
            foreach ($rankFields as $index => $field) {
                $placeholder = ':search_rank_' . $index;
                $rankConditions[] = $field . ' LIKE ' . $placeholder;
                $dataBindings[$placeholder] = [$searchTerm, PDO::PARAM_STR];
            }
            $orderBy = 'ORDER BY CASE WHEN ' . implode(' OR ', $rankConditions)
                . ' THEN 0 ELSE 1 END ASC, u.gy_user_id DESC';
        }

        $dataBindings[':limit'] = [$limit, PDO::PARAM_INT];
        $dataBindings[':offset'] = [($page - 1) * $limit, PDO::PARAM_INT];
        $dataStatement = $this->prepare(
            'SELECT ' . self::SELECT_COLUMNS . self::FROM_JOINS . $whereSql . ' '
            . $orderBy . ' LIMIT :limit OFFSET :offset'
        );
        $this->bindAll($dataStatement, $dataBindings);
        $dataStatement->execute();

        return [
            'data' => $dataStatement->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
        ];
    }

    private function prepare(string $query): PDOStatement
    {
        $statement = $this->pdo->prepare($query);

        if ($statement === false) {
            throw new RuntimeException('Unable to prepare employee directory query.');
        }

        return $statement;
    }

    /** @param array<string, array{0: mixed, 1: int}> $bindings */
    private function bindAll(PDOStatement $statement, array $bindings): void
    {
        foreach ($bindings as $placeholder => [$value, $type]) {
            $statement->bindValue($placeholder, $value, $type);
        }
    }
}
