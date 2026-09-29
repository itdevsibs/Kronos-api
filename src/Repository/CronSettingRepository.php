<?php

declare(strict_types=1);

namespace Sibs\KronosApi\Repository;

use PDO;
use RuntimeException;

final class CronSettingRepository
{
    private const CURSOR_QUERY = 'SELECT cronid, crondate, cronval, leave_credits, lastmonth_lc, active_filter, '
        . 'filter_id, status, name, send_to, cc_to, bcc_to, attendance_report '
        . 'FROM cronjob WHERE cronid > :after_id ORDER BY cronid ASC LIMIT 101';

    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return list<array<string, mixed>> */
    public function findAfter(int $afterId): array
    {
        $statement = $this->pdo->prepare(self::CURSOR_QUERY);

        if ($statement === false) {
            throw new RuntimeException('Unable to prepare cron setting cursor query.');
        }

        $statement->bindValue(':after_id', $afterId, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}
