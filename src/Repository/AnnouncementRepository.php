<?php

declare(strict_types=1);

namespace Sibs\KronosApi\Repository;

use PDO;
use RuntimeException;

final class AnnouncementRepository
{
    private const CURSOR_QUERY = 'SELECT gy_ann_id, gy_ann_serial, gy_ann_type, gy_ann_date, gy_ann_end, gy_ann_by, '
        . 'gy_ann_caption, gy_ann_attachment '
        . 'FROM gy_announce WHERE gy_ann_id > :after_id ORDER BY gy_ann_id ASC LIMIT 101';

    public function __construct(private readonly PDO $pdo) {}

    /** @return list<array<string, mixed>> */
    public function findAfter(int $afterId): array
    {
        $statement = $this->pdo->prepare(self::CURSOR_QUERY);
        if ($statement === false) {
            throw new RuntimeException('Unable to prepare announcement cursor query.');
        }
        $statement->bindValue(':after_id', $afterId, PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}
