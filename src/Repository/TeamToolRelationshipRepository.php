<?php

declare(strict_types=1);

namespace Sibs\KronosApi\Repository;

use PDO;
use PDOStatement;
use RuntimeException;

final class TeamToolRelationshipRepository
{
    private const TEAM_TOOL_FIELDS = 't.team_id, t.team_name, t.team_owner, t.team_switch';
    private const TEAM_COLUMN_FIELDS = 'c.col_id, c.team_id, c.col_val, c.col_type, c.col_status, c.col_order';
    private const TEAM_DATA_FIELDS = 'd.data_id, d.col_id, d.row_id, d.tool_id, d.data_value';

    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array{parent_exists: bool, data: list<array<string, mixed>>} */
    public function findColumnsByTeamId(int $teamId, int $afterId): array
    {
        $sql = 'SELECT t.team_id AS _parent_id, ' . self::TEAM_COLUMN_FIELDS
            . ' FROM team_toollist t LEFT JOIN team_collist c'
            . ' ON c.team_id = t.team_id AND c.col_id > :after_id'
            . ' WHERE t.team_id = :parent_id ORDER BY c.col_id ASC LIMIT 101';

        return $this->collection($sql, $teamId, $afterId, 'col_id');
    }

    /** @return array{parent_exists: bool, data: list<array<string, mixed>>} */
    public function findDataByTeamId(int $teamId, int $afterId): array
    {
        $sql = 'SELECT t.team_id AS _parent_id, ' . self::TEAM_DATA_FIELDS
            . ' FROM team_toollist t LEFT JOIN team_data d'
            . ' ON d.tool_id = t.team_id AND d.data_id > :after_id'
            . ' WHERE t.team_id = :parent_id ORDER BY d.data_id ASC LIMIT 101';

        return $this->collection($sql, $teamId, $afterId, 'data_id');
    }

    /** @return array{parent_exists: bool, data: array<string, mixed>|null} */
    public function findTeamToolByColumnId(int $columnId): array
    {
        $sql = 'SELECT c.col_id AS _parent_id, t.team_id AS _related_id, ' . self::TEAM_TOOL_FIELDS
            . ' FROM team_collist c LEFT JOIN team_toollist t ON c.team_id = t.team_id'
            . ' WHERE c.col_id = :parent_id LIMIT 1';

        return $this->single($sql, $columnId);
    }

    /** @return array{parent_exists: bool, data: list<array<string, mixed>>} */
    public function findDataByColumnId(int $columnId, int $afterId): array
    {
        $sql = 'SELECT c.col_id AS _parent_id, ' . self::TEAM_DATA_FIELDS
            . ' FROM team_collist c LEFT JOIN team_data d'
            . ' ON d.col_id = c.col_id AND d.data_id > :after_id'
            . ' WHERE c.col_id = :parent_id ORDER BY d.data_id ASC LIMIT 101';

        return $this->collection($sql, $columnId, $afterId, 'data_id');
    }

    /** @return array{parent_exists: bool, data: array<string, mixed>|null} */
    public function findTeamToolByDataId(int $dataId): array
    {
        $sql = 'SELECT d.data_id AS _parent_id, t.team_id AS _related_id, ' . self::TEAM_TOOL_FIELDS
            . ' FROM team_data d LEFT JOIN team_toollist t ON d.tool_id = t.team_id'
            . ' WHERE d.data_id = :parent_id LIMIT 1';

        return $this->single($sql, $dataId);
    }

    /** @return array{parent_exists: bool, data: array<string, mixed>|null} */
    public function findColumnByDataId(int $dataId): array
    {
        $sql = 'SELECT d.data_id AS _parent_id, c.col_id AS _related_id, ' . self::TEAM_COLUMN_FIELDS
            . ' FROM team_data d LEFT JOIN team_collist c ON d.col_id = c.col_id'
            . ' WHERE d.data_id = :parent_id LIMIT 1';

        return $this->single($sql, $dataId);
    }

    /** @return array{parent_exists: bool, data: list<array<string, mixed>>} */
    private function collection(string $sql, int $parentId, int $afterId, string $resourceKey): array
    {
        $statement = $this->prepare($sql);
        $statement->bindValue(':parent_id', $parentId, PDO::PARAM_INT);
        $statement->bindValue(':after_id', $afterId, PDO::PARAM_INT);
        $statement->execute();
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        if ($rows === []) {
            return ['parent_exists' => false, 'data' => []];
        }

        $data = [];
        foreach ($rows as $row) {
            unset($row['_parent_id']);
            if (($row[$resourceKey] ?? null) !== null) {
                $data[] = $row;
            }
        }

        return ['parent_exists' => true, 'data' => $data];
    }

    /** @return array{parent_exists: bool, data: array<string, mixed>|null} */
    private function single(string $sql, int $parentId): array
    {
        $statement = $this->prepare($sql);
        $statement->bindValue(':parent_id', $parentId, PDO::PARAM_INT);
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
            throw new RuntimeException('Unable to prepare team tool relationship query.');
        }

        return $statement;
    }
}
