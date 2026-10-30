<?php

declare(strict_types=1);

namespace Sibs\KronosApi\Repository;

use PDO;

final class EmployeeStatutoryPresenceRepository
{
    public function __construct(private readonly PDO $pdo) {}

    /** @return list<array<string, mixed>> */
    public function findAfter(int $afterId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT gy_emp_id, gy_emp_code, gy_gov_id, gy_gov_idnum '
            . 'FROM gy_employee WHERE gy_emp_id > :after_id '
            . 'ORDER BY gy_emp_id ASC LIMIT 501'
        );
        $statement->bindValue(':after_id', $afterId, PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}
