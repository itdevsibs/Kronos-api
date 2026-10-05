<?php

declare(strict_types=1);

namespace Sibs\KronosApi\Repository;

use PDO;
use PDOStatement;
use RuntimeException;

final class AnnouncementViewRepository
{
    private const ANNOUNCEMENT_FIELDS = 'a.gy_ann_id AS announcement_id, '
        . 'a.gy_ann_serial AS announcement_serial, a.gy_ann_type AS announcement_type, '
        . 'a.gy_ann_date AS announcement_date, a.gy_ann_end AS announcement_end, '
        . 'a.gy_ann_caption AS announcement_caption, a.gy_ann_attachment AS announcement_attachment';

    private const CREATOR_FIELDS = 'creator.gy_user_code AS creator_user_code, '
        . 'creator.ghl_contact_id AS creator_contact_id, creator.gy_full_name AS creator_full_name, '
        . 'creator.gy_username AS creator_username, creator.gy_user_type AS creator_user_type, '
        . 'creator.gy_user_function AS creator_user_function, creator.gy_head_code AS creator_head_code, '
        . 'creator.gy_script_code AS creator_script_code, creator.gy_user_status AS creator_user_status';

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @param array{gy_emp_code?: string|null, search?: string|null, date_from?: string|null, date_to?: string|null} $filters
     * @return array{data: list<array<string, mixed>>, total: int}
     */
    public function findAnnouncementFeedPage(int $page, int $limit, array $filters): array
    {
        $where = [];
        $bindings = [];
        $this->dateFilter($where, $bindings, $filters, 'date_from', 'a.gy_ann_date >= :date_from', ':date_from');
        $this->dateFilter(
            $where,
            $bindings,
            $filters,
            'date_to',
            'a.gy_ann_date < DATE_ADD(:date_to, INTERVAL 1 DAY)',
            ':date_to'
        );
        $this->search($where, $bindings, $filters['search'] ?? null);

        $baseFrom = 'FROM gy_announce a LEFT JOIN gy_user creator ON a.gy_ann_by = creator.gy_user_id ';
        $whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where) . ' ';

        $count = $this->prepare('SELECT COUNT(*) AS total ' . $baseFrom . $whereSql);
        $this->bindAll($count, $bindings);
        $count->execute();
        $countRow = $count->fetch(PDO::FETCH_ASSOC);
        $total = is_array($countRow) ? (int) ($countRow['total'] ?? 0) : 0;

        $targetCode = $filters['gy_emp_code'] ?? null;
        $targetJoin = '';
        $targetField = 'NULL AS confirmed_by_target_user';
        $dataBindings = $bindings;
        if (is_string($targetCode) && $targetCode !== '') {
            $targetJoin = 'LEFT JOIN (SELECT gy_ann_id, 1 AS is_confirmed FROM gy_confirm '
                . 'WHERE TRIM(gy_conf_by) = TRIM(:gy_emp_code) GROUP BY gy_ann_id) targetConfirmation '
                . 'ON targetConfirmation.gy_ann_id = a.gy_ann_id ';
            $targetField = 'COALESCE(targetConfirmation.is_confirmed, 0) AS confirmed_by_target_user';
            $dataBindings[':gy_emp_code'] = [$targetCode, PDO::PARAM_STR];
        }

        $confirmationJoin = 'LEFT JOIN (SELECT gy_ann_id, COUNT(*) AS confirmation_count '
            . 'FROM gy_confirm GROUP BY gy_ann_id) confirmationSummary '
            . 'ON confirmationSummary.gy_ann_id = a.gy_ann_id ';
        $select = self::ANNOUNCEMENT_FIELDS . ', ' . self::CREATOR_FIELDS
            . ', COALESCE(confirmationSummary.confirmation_count, 0) AS confirmation_count, ' . $targetField;
        $dataBindings[':limit'] = [$limit, PDO::PARAM_INT];
        $dataBindings[':offset'] = [($page - 1) * $limit, PDO::PARAM_INT];

        $data = $this->prepare(
            'SELECT ' . $select . ' ' . $baseFrom . $confirmationJoin . $targetJoin . $whereSql
                . 'ORDER BY a.gy_ann_date DESC, a.gy_ann_id DESC LIMIT :limit OFFSET :offset'
        );
        $this->bindAll($data, $dataBindings);
        $data->execute();

        return ['data' => $data->fetchAll(PDO::FETCH_ASSOC), 'total' => $total];
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

        $fields = ['a.gy_ann_serial', 'a.gy_ann_type', 'a.gy_ann_caption'];
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
            throw new RuntimeException('Unable to prepare announcement feed query.');
        }

        return $statement;
    }
}
