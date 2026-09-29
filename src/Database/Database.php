<?php

declare(strict_types=1);

namespace Sibs\KronosApi\Database;

use PDO;
use RuntimeException;

final class Database
{
    /** @param array<string, mixed> $environment */
    public static function connect(array $environment): PDO
    {
        $host = self::requiredValue($environment, 'DB1_HOST');
        $port = self::requiredValue($environment, 'DB1_PORT');
        $database = self::requiredValue($environment, 'DB1_NAME');
        $username = self::requiredValue($environment, 'DB1_USER');
        $password = isset($environment['DB1_PASSWORD']) ? (string) $environment['DB1_PASSWORD'] : '';

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $host,
            $port,
            $database
        );

        return new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    /** @param array<string, mixed> $environment */
    private static function requiredValue(array $environment, string $name): string
    {
        $value = isset($environment[$name]) ? trim((string) $environment[$name]) : '';

        if ($value === '') {
            throw new RuntimeException('Missing database configuration.');
        }

        return $value;
    }
}
