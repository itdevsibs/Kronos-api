<?php

declare(strict_types=1);

namespace Sibs\KronosApi\Service;

use Closure;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use RuntimeException;
use Sibs\KronosApi\Exception\InvalidTokenException;
use stdClass;
use Throwable;

final class JwtService
{
    private readonly Closure $clock;

    /** @param null|Closure(): int $clock */
    public function __construct(
        private readonly string $issuer,
        private readonly string $audience,
        private readonly int $accessTtl,
        private readonly string $privateKeyPath,
        private readonly string $publicKeyPath,
        ?Closure $clock = null
    ) {
        if ($this->issuer === '' || $this->audience === '' || $this->accessTtl !== 3600) {
            throw new RuntimeException('Invalid JWT configuration.');
        }

        $this->clock = $clock ?? static fn (): int => time();
    }

    /** @param array{id: int, client_name: string} $client */
    public function issue(array $client): string
    {
        $now = ($this->clock)();

        return JWT::encode([
            'iss' => $this->issuer,
            'aud' => $this->audience,
            'sub' => (string) $client['id'],
            'client_name' => $client['client_name'],
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $this->accessTtl,
            'jti' => bin2hex(random_bytes(16)),
            'type' => 'access',
        ], $this->readKey($this->privateKeyPath), 'RS256');
    }

    public function validate(#[\SensitiveParameter] string $token): stdClass
    {
        $publicKey = $this->readKey($this->publicKeyPath);
        $previousTimestamp = JWT::$timestamp;
        JWT::$timestamp = ($this->clock)();

        try {
            $claims = JWT::decode($token, new Key($publicKey, 'RS256'));
        } catch (Throwable $exception) {
            throw new InvalidTokenException('Invalid token.', 0, $exception);
        } finally {
            JWT::$timestamp = $previousTimestamp;
        }

        if (
            ($claims->iss ?? null) !== $this->issuer
            || ($claims->aud ?? null) !== $this->audience
            || ($claims->type ?? null) !== 'access'
            || !isset($claims->sub, $claims->client_name, $claims->iat, $claims->nbf, $claims->exp, $claims->jti)
            || !is_string($claims->sub)
            || $claims->sub === ''
            || !is_string($claims->client_name)
            || $claims->client_name === ''
            || !is_int($claims->iat)
            || !is_int($claims->nbf)
            || !is_int($claims->exp)
            || $claims->nbf !== $claims->iat
            || $claims->exp - $claims->iat !== $this->accessTtl
            || !is_string($claims->jti)
            || preg_match('/^[a-f0-9]{32}$/', $claims->jti) !== 1
        ) {
            throw new InvalidTokenException('Invalid token.');
        }

        return $claims;
    }

    private function readKey(string $path): string
    {
        $resolvedPath = str_starts_with($path, DIRECTORY_SEPARATOR)
            ? $path
            : dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
        $key = is_readable($resolvedPath) ? file_get_contents($resolvedPath) : false;

        if (!is_string($key) || trim($key) === '') {
            throw new RuntimeException('JWT key is unavailable.');
        }

        return $key;
    }
}
