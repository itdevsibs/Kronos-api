<?php

declare(strict_types=1);

namespace Sibs\KronosApi\Repository;

use PDO;
use RuntimeException;

final class DtrPublishRepository
{
    private const CURSOR_QUERY = 'SELECT dtr_publish_id, dtr_year, dtr_month, dtr_cutoff, gy_emp_code, dtr_noofhours, '
        . 'dtr_lateut, dtr_absences, dtr_regot, dtr_rdreg, dtr_rdot, dtr_shreg, dtr_shot, dtr_shrdreg, '
        . 'dtr_shrdot, dtr_lhreg, dtr_lhot, dtr_lhrdreg, dtr_lhrdot, dtr_ndreg, dtr_ndregot, dtr_ndrdreg, '
        . 'dtr_ndrdot, dtr_ndsh, dtr_ndshot, dtr_ndshrd, dtr_ndshrdot, dtr_ndlh, dtr_ndlhot, dtr_ndlhrd, '
        . 'dtr_ndlhrdot, dtr_publisher, dtr_mdrate, dtr_cmpute '
        . 'FROM dtr_publish WHERE dtr_publish_id > :after_id ORDER BY dtr_publish_id ASC LIMIT 101';

    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return list<array<string, mixed>> */
    public function findAfter(int $afterId): array
    {
        $statement = $this->pdo->prepare(self::CURSOR_QUERY);
        if ($statement === false) {
            throw new RuntimeException('Unable to prepare DTR publish cursor query.');
        }

        $statement->bindValue(':after_id', $afterId, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}
