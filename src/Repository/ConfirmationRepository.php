<?php

declare(strict_types=1);

namespace Sibs\KronosApi\Repository;

use PDO;
use RuntimeException;

final class ConfirmationRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return list<array<string, mixed>> */
    public function findAfter(int $afterId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT gy_conf_id, gy_conf_date, gy_conf_by, gy_ann_id '
            . 'FROM gy_confirm WHERE gy_conf_id > :after_id ORDER BY gy_conf_id ASC LIMIT 101'
        );
        if ($statement === false) {
            throw new RuntimeException('Unable to prepare confirmation cursor query.');
        }

        $statement->bindValue(':after_id', $afterId, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}
