<?php

declare(strict_types=1);

namespace Sibs\KronosApi\Repository;

use PDO;
use RuntimeException;

final class AccountRepository
{
    private const CURSOR_QUERY = 'SELECT gy_acc_id, gy_acc_name, gy_acc_ghl_name, gy_dept_id, gy_acc_status '
        . 'FROM gy_accounts WHERE gy_acc_id > :after_id ORDER BY gy_acc_id ASC LIMIT 101';

    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return list<array<string, mixed>> */
    public function findAfter(int $afterId): array
    {
        $statement = $this->pdo->prepare(self::CURSOR_QUERY);

        if ($statement === false) {
            throw new RuntimeException('Unable to prepare account cursor query.');
        }

        $statement->bindValue(':after_id', $afterId, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}
