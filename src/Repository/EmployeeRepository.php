<?php

declare(strict_types=1);

namespace Sibs\KronosApi\Repository;

use PDO;
use RuntimeException;

final class EmployeeRepository
{
    private const CURSOR_QUERY = 'SELECT gy_emp_id, gy_emp_code, gy_emp_type, gy_emp_schedtype, gy_emp_rate, '
        . 'gy_emp_email, gy_emp_lname, gy_emp_fname, gy_emp_mname, gy_emp_fullname, gy_acc_id, '
        . 'gy_emp_account, gy_emp_supervisor, gy_emp_om, gy_emp_leave_credits, gy_emp_hiredate, '
        . 'gy_emp_lastedit, gy_lastedit_by, gy_work_from, gy_gender, gy_dob, gy_civilstatus, '
        . 'gy_assignedloc, gy_tagumdate, gy_davaodate, gy_hybriddate, gy_accjoin, gy_nhodate, '
        . 'gy_fststartdate, gy_fstenddate, gy_pststartdate, gy_pstenddate, gy_certification, '
        . 'gy_gradbaystartdate, gy_gradbayenddate, gy_fullgolivedate, gy_promotiondate, '
        . 'gy_projempdate, gy_probempdate, gy_regempdate, gy_last_working_day '
        . 'FROM gy_employee WHERE gy_emp_id > :after_id ORDER BY gy_emp_id ASC LIMIT 101';

    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return list<array<string, mixed>> */
    public function findAfter(int $afterId): array
    {
        $statement = $this->pdo->prepare(self::CURSOR_QUERY);

        if ($statement === false) {
            throw new RuntimeException('Unable to prepare employee cursor query.');
        }

        $statement->bindValue(':after_id', $afterId, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}
