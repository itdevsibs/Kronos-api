<?php

declare(strict_types=1);

namespace Sibs\KronosApi\Repository;

use PDO;
use PDOStatement;
use RuntimeException;

final class HolidayRelationshipRepository
{
    private const HOLIDAY_FIELDS = 'h.gy_hol_id, h.gy_hol_type_id, h.gy_hol_reg, h.gy_hol_title, '
        . 'h.gy_hol_date, h.gy_a_year, h.gy_hol_lastday, h.gy_hol_loc';

    private const TYPE_FIELDS = 't.gy_hol_type_id, t.gy_hol_type_name, t.gy_hol_abbrv, '
        . 't.gy_daybonus, t.gy_nightbonus, t.lateut, t.absnt, t.leaves, t.gy_day_start, '
        . 't.gy_day_end, t.gy_night_start, t.gy_night_end, t.gy_hol_status';

    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array{parent_exists: bool, data: list<array<string, mixed>>} */
    public function findHolidaysByTypeId(int $typeId, int $afterId): array
    {
        $statement = $this->prepare(
            'SELECT t.gy_hol_type_id AS _parent_id, ' . self::HOLIDAY_FIELDS
                . ' FROM gy_holiday_types t LEFT JOIN gy_holiday_calendar h'
                . ' ON h.gy_hol_type_id = t.gy_hol_type_id AND h.gy_hol_id > :after_id'
                . ' WHERE t.gy_hol_type_id = :holiday_type_id'
                . ' ORDER BY h.gy_hol_id ASC LIMIT 101'
        );
        $statement->bindValue(':holiday_type_id', $typeId, PDO::PARAM_INT);
        $statement->bindValue(':after_id', $afterId, PDO::PARAM_INT);
        $statement->execute();
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        if ($rows === []) {
            return ['parent_exists' => false, 'data' => []];
        }

        $data = [];
        foreach ($rows as $row) {
            unset($row['_parent_id']);
            if (($row['gy_hol_id'] ?? null) !== null) {
                $data[] = $row;
            }
        }

        return ['parent_exists' => true, 'data' => $data];
    }

    /** @return array{parent_exists: bool, data: array<string, mixed>|null} */
    public function findTypeByHolidayId(int $holidayId): array
    {
        $statement = $this->prepare(
            'SELECT h.gy_hol_id AS _parent_id, t.gy_hol_type_id AS _related_id, ' . self::TYPE_FIELDS
                . ' FROM gy_holiday_calendar h LEFT JOIN gy_holiday_types t'
                . ' ON h.gy_hol_type_id = t.gy_hol_type_id'
                . ' WHERE h.gy_hol_id = :holiday_id LIMIT 1'
        );
        $statement->bindValue(':holiday_id', $holidayId, PDO::PARAM_INT);
        $statement->execute();
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return ['parent_exists' => false, 'data' => null];
        }

        $relatedExists = ($row['_related_id'] ?? null) !== null;
        unset($row['_parent_id'], $row['_related_id']);

        return ['parent_exists' => true, 'data' => $relatedExists ? $row : null];
    }

    private function prepare(string $sql): PDOStatement
    {
        $statement = $this->pdo->prepare($sql);
        if ($statement === false) {
            throw new RuntimeException('Unable to prepare holiday relationship query.');
        }

        return $statement;
    }
}
