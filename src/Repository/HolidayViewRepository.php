<?php

declare(strict_types=1);

namespace Sibs\KronosApi\Repository;

use PDO;
use PDOStatement;
use RuntimeException;

final class HolidayViewRepository
{
    private const HOLIDAY_FIELDS = 'h.gy_hol_id, h.gy_hol_type_id, h.gy_hol_reg, h.gy_hol_title, '
        . 'h.gy_hol_date, h.gy_a_year, h.gy_hol_lastday, h.gy_hol_loc';

    private const TYPE_FIELDS = 't.gy_hol_type_name, t.gy_hol_abbrv, t.gy_daybonus, '
        . 't.gy_nightbonus, t.lateut, t.absnt, t.leaves, t.gy_day_start, t.gy_day_end, '
        . 't.gy_night_start, t.gy_night_end';

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @param array{year?: int|null, date_from?: string|null, date_to?: string|null, location?: int|null, holiday_type_id?: int|null, search?: string|null} $filters
     * @return array{data: list<array<string, mixed>>, total: int}
     */
    public function findHolidayCalendarPage(int $page, int $limit, array $filters): array
    {
        $where = [];
        $bindings = [];
        $this->intFilter($where, $bindings, $filters, 'year', 'h.gy_a_year', ':year');
        $this->dateFilter($where, $bindings, $filters, 'date_from', 'h.gy_hol_date >= :date_from', ':date_from');
        $this->dateFilter($where, $bindings, $filters, 'date_to', 'h.gy_hol_date <= :date_to', ':date_to');
        $this->intFilter($where, $bindings, $filters, 'location', 'h.gy_hol_loc', ':location');
        $this->intFilter(
            $where,
            $bindings,
            $filters,
            'holiday_type_id',
            'h.gy_hol_type_id',
            ':holiday_type_id'
        );
        $this->search($where, $bindings, $filters['search'] ?? null);

        $from = 'FROM gy_holiday_calendar h INNER JOIN gy_holiday_types t '
            . 'ON h.gy_hol_type_id = t.gy_hol_type_id ';
        $whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where) . ' ';

        $count = $this->prepare('SELECT COUNT(*) AS total ' . $from . $whereSql);
        $this->bindAll($count, $bindings);
        $count->execute();
        $countRow = $count->fetch(PDO::FETCH_ASSOC);
        $total = is_array($countRow) ? (int) ($countRow['total'] ?? 0) : 0;

        $dataBindings = $bindings;
        $dataBindings[':limit'] = [$limit, PDO::PARAM_INT];
        $dataBindings[':offset'] = [($page - 1) * $limit, PDO::PARAM_INT];
        $data = $this->prepare(
            'SELECT ' . self::HOLIDAY_FIELDS . ', ' . self::TYPE_FIELDS . ' ' . $from . $whereSql
                . 'ORDER BY h.gy_hol_date ASC, h.gy_hol_id ASC LIMIT :limit OFFSET :offset'
        );
        $this->bindAll($data, $dataBindings);
        $data->execute();

        return ['data' => $data->fetchAll(PDO::FETCH_ASSOC), 'total' => $total];
    }

    /** @param list<string> $where @param array<string, array{mixed, int}> $bindings */
    private function intFilter(
        array &$where,
        array &$bindings,
        array $filters,
        string $key,
        string $field,
        string $placeholder
    ): void {
        if (($filters[$key] ?? null) !== null) {
            $where[] = $field . ' = ' . $placeholder;
            $bindings[$placeholder] = [$filters[$key], PDO::PARAM_INT];
        }
    }

    /** @param list<string> $where @param array<string, array{mixed, int}> $bindings */
    private function dateFilter(
        array &$where,
        array &$bindings,
        array $filters,
        string $key,
        string $condition,
        string $placeholder
    ): void {
        if (($filters[$key] ?? null) !== null) {
            $where[] = $condition;
            $bindings[$placeholder] = [(string) $filters[$key], PDO::PARAM_STR];
        }
    }

    /** @param list<string> $where @param array<string, array{mixed, int}> $bindings */
    private function search(array &$where, array &$bindings, mixed $search): void
    {
        if (!is_string($search) || $search === '') {
            return;
        }

        $fields = ['h.gy_hol_title', 't.gy_hol_type_name', 't.gy_hol_abbrv'];
        $parts = [];
        foreach ($fields as $index => $field) {
            $placeholder = ':search_' . $index;
            $parts[] = $field . ' LIKE ' . $placeholder;
            $bindings[$placeholder] = ['%' . $search . '%', PDO::PARAM_STR];
        }
        $where[] = '(' . implode(' OR ', $parts) . ')';
    }

    /** @param array<string, array{mixed, int}> $bindings */
    private function bindAll(PDOStatement $statement, array $bindings): void
    {
        foreach ($bindings as $placeholder => [$value, $type]) {
            $statement->bindValue($placeholder, $value, $type);
        }
    }

    private function prepare(string $sql): PDOStatement
    {
        $statement = $this->pdo->prepare($sql);
        if ($statement === false) {
            throw new RuntimeException('Unable to prepare holiday calendar query.');
        }

        return $statement;
    }
}
