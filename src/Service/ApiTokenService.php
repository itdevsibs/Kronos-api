<?php

declare(strict_types=1);

namespace Sibs\KronosApi\Service;

use Closure;
use DateTimeImmutable;
use PDO;
use RuntimeException;
use Sibs\KronosApi\Exception\InvalidApiCredentialsException;

final class ApiTokenService
{
    private const DUMMY_SECRET_HASH = '$argon2id$v=19$m=65536,t=4,p=1$L09DUmlBdjNUUC80cmpVRw$cUt0FeoWC971G2gqusaA1lOQudTiXFj1SN04BS8Havg';

    private readonly Closure $passwordVerifier;

    /** @param null|Closure(string, string): bool $passwordVerifier */
    public function __construct(private readonly PDO $pdo, ?Closure $passwordVerifier = null)
    {
        $this->passwordVerifier = $passwordVerifier
            ?? static fn (string $secret, string $hash): bool => password_verify($secret, $hash);
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int, client_name: string}
     */
    public function authenticate(array $input): array
    {
        if (
            !isset($input['api_key'], $input['api_secret'])
            || !is_string($input['api_key'])
            || !is_string($input['api_secret'])
            || $input['api_key'] === ''
            || $input['api_secret'] === ''
        ) {
            throw new InvalidApiCredentialsException('Invalid API credentials.');
        }

        $statement = $this->pdo->prepare(
            'SELECT id, client_name, api_secret_hash, status, expires_at '
            . 'FROM api_clients WHERE api_key = :api_key LIMIT 1'
        );

        if ($statement === false) {
            throw new RuntimeException('Unable to prepare API client lookup.');
        }

        $statement->execute(['api_key' => $input['api_key']]);
        $client = $statement->fetch(PDO::FETCH_ASSOC);
        $storedHash = is_array($client) && isset($client['api_secret_hash']) && is_string($client['api_secret_hash'])
            && password_get_info($client['api_secret_hash'])['algoName'] === 'argon2id'
            ? $client['api_secret_hash']
            : self::DUMMY_SECRET_HASH;
        $secretIsValid = ($this->passwordVerifier)($input['api_secret'], $storedHash);

        if (!is_array($client) || !$this->isEligibleClient($client) || !$secretIsValid) {
            throw new InvalidApiCredentialsException('Invalid API credentials.');
        }

        return [
            'id' => (int) $client['id'],
            'client_name' => (string) $client['client_name'],
        ];
    }

    /** @param array<string, mixed> $client */
    private function isEligibleClient(array $client): bool
    {
        if (
            ($client['status'] ?? null) !== 'active'
            || !isset($client['api_secret_hash'])
            || !is_string($client['api_secret_hash'])
        ) {
            return false;
        }

        if ($client['expires_at'] !== null) {
            if (!is_string($client['expires_at'])) {
                return false;
            }

            try {
                $expiration = new DateTimeImmutable($client['expires_at']);
            } catch (\Throwable) {
                return false;
            }

            if ($expiration <= new DateTimeImmutable('now')) {
                return false;
            }
        }

        return true;
    }
}
