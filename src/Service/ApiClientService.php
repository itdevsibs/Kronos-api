<?php

declare(strict_types=1);

namespace Sibs\KronosApi\Service;

use DateTimeImmutable;
use InvalidArgumentException;
use PDO;
use PDOException;
use RuntimeException;
use Sibs\KronosApi\Exception\ApiKeyCollisionException;

final class ApiClientService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function create(array $input): array
    {
        if (!array_key_exists('client_name', $input)) {
            throw new InvalidArgumentException('client_name is required.');
        }

        if (!is_string($input['client_name'])) {
            throw new InvalidArgumentException('client_name must be a string.');
        }

        $clientName = trim($input['client_name']);

        if ($clientName === '') {
            throw new InvalidArgumentException('client_name is required.');
        }

        if (mb_strlen($clientName) > 150) {
            throw new InvalidArgumentException('client_name must not exceed 150 characters.');
        }

        $expiresAt = $this->validateExpiresAt($input['expires_at'] ?? null);

        $apiKey = 'kronos_live_' . bin2hex(random_bytes(16));
        $apiSecret = 'krs_' . bin2hex(random_bytes(32));
        $apiSecretHash = password_hash($apiSecret, PASSWORD_ARGON2ID);

        if ($apiSecretHash === false) {
            throw new RuntimeException('Unable to hash API secret.');
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO api_clients (client_name, api_key, api_secret_hash, status, expires_at) '
            . 'VALUES (:client_name, :api_key, :api_secret_hash, :status, :expires_at)'
        );

        if ($statement === false) {
            throw new RuntimeException('Unable to prepare API client insert.');
        }

        try {
            $statement->execute([
                'client_name' => $clientName,
                'api_key' => $apiKey,
                'api_secret_hash' => $apiSecretHash,
                'status' => 'active',
                'expires_at' => $expiresAt,
            ]);
        } catch (PDOException $exception) {
            if (
                ($exception->getCode() === '23000' || ($exception->errorInfo[0] ?? null) === '23000')
                && (int) ($exception->errorInfo[1] ?? 0) === 1062
            ) {
                throw new ApiKeyCollisionException('API key collision.', 0, $exception);
            }

            throw $exception;
        }

        return [
            'id' => (int) $this->pdo->lastInsertId(),
            'client_name' => $clientName,
            'api_key' => $apiKey,
            'api_secret' => $apiSecret,
            'status' => 'active',
            'expires_at' => $expiresAt,
        ];
    }

    private function validateExpiresAt(mixed $expiresAt): ?string
    {
        if ($expiresAt === null) {
            return null;
        }

        if (!is_string($expiresAt)) {
            throw new InvalidArgumentException('expires_at must be a valid DATETIME in Y-m-d H:i:s format.');
        }

        $expiration = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $expiresAt);
        $errors = DateTimeImmutable::getLastErrors();

        if (
            $expiration === false
            || $expiration->format('Y-m-d H:i:s') !== $expiresAt
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
        ) {
            throw new InvalidArgumentException('expires_at must be a valid DATETIME in Y-m-d H:i:s format.');
        }

        if ($expiration <= new DateTimeImmutable('now')) {
            throw new InvalidArgumentException('expires_at must be in the future.');
        }

        return $expiresAt;
    }
}
