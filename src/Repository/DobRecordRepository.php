<?php

declare(strict_types=1);

namespace Sibs\KronosApi\Repository;

use PDO;
use RuntimeException;

final class DobRecordRepository
{
    private const CURSOR_QUERY = 'SELECT dob_id, dob_reg_for, dob_reg_from, dob_message, dob_read, dob_date '
        . 'FROM dob_reg WHERE dob_id > :after_id ORDER BY dob_id ASC LIMIT 101';

    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return list<array<string, mixed>> */
    public function findAfter(int $afterId): array
    {
        $statement = $this->pdo->prepare(self::CURSOR_QUERY);

        if ($statement === false) {
            throw new RuntimeException('Unable to prepare DOB record cursor query.');
        }

        $statement->bindValue(':after_id', $afterId, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}
