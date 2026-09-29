<?php

declare(strict_types=1);

namespace Sibs\KronosApi\Repository;

use PDO;
use RuntimeException;

final class UserRepository
{
    private const CURSOR_QUERY = 'SELECT gy_user_id, gy_user_code, ghl_contact_id, gy_full_name, gy_username, '
        . 'gy_user_type, gy_user_function, gy_head_code, gy_script_code, gy_user_status '
        . 'FROM gy_user WHERE gy_user_id > :after_id ORDER BY gy_user_id ASC LIMIT 101';

    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return list<array<string, mixed>> */
    public function findAfter(int $afterId): array
    {
        $statement = $this->pdo->prepare(self::CURSOR_QUERY);

        if ($statement === false) {
            throw new RuntimeException('Unable to prepare user cursor query.');
        }

        $statement->bindValue(':after_id', $afterId, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}
