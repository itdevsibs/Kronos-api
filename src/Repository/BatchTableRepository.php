<?php

declare(strict_types=1);

namespace Sibs\KronosApi\Repository;

use InvalidArgumentException;
use PDO;
use RuntimeException;

final class BatchTableRepository
{
    private readonly string $query;

    /**
     * @param array{
     *     route: string,
     *     table: string,
     *     primaryKey: string,
     *     columns: list<string>
     * } $configuration
     */
    public function __construct(
        private readonly PDO $pdo,
        array $configuration
    ) {
        $table = $configuration['table'];
        $primaryKey = $configuration['primaryKey'];
        $columns = $configuration['columns'];

        $this->assertIdentifier($table);
        $this->assertIdentifier($primaryKey);

        if ($columns === [] || !in_array($primaryKey, $columns, true)) {
            throw new InvalidArgumentException(
                'Batch table columns must include the primary key.'
            );
        }

        foreach ($columns as $column) {
            $this->assertIdentifier($column);
        }

        $this->query = sprintf(
            'SELECT %s FROM %s WHERE %s > :after_id ORDER BY %s ASC LIMIT 101',
            implode(', ', $columns),
            $table,
            $primaryKey,
            $primaryKey
        );
    }

    /** @return list<array<string, mixed>> */
    public function findAfter(int $afterId): array
    {
        $statement = $this->pdo->prepare($this->query);

        if ($statement === false) {
            throw new RuntimeException('Unable to prepare batch cursor query.');
        }

        $statement->bindValue(':after_id', $afterId, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    private function assertIdentifier(string $identifier): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $identifier) !== 1) {
            throw new InvalidArgumentException(
                'Invalid configured batch table identifier.'
            );
        }
    }
}
