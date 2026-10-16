<?php

declare(strict_types=1);

namespace Sibs\KronosApi\Controller;

use Closure;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Sibs\KronosApi\Exception\InvalidUserCredentialsException;
use Sibs\KronosApi\Service\UserLoginService;
use Throwable;

final class UserLoginController
{
    private readonly Closure $exceptionLogger;

    /**
     * @param Closure(): UserLoginService $serviceFactory
     * @param null|Closure(Throwable): void $exceptionLogger
     */
    public function __construct(
        private readonly Closure $serviceFactory,
        ?Closure $exceptionLogger = null
    ) {
        $this->exceptionLogger = $exceptionLogger ?? static fn (Throwable $exception): bool => error_log((string) $exception);
    }

    public function login(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $input = $request->getParsedBody();
        if (
            !is_array($input)
            || !is_string($input['sibsId'] ?? null)
            || trim($input['sibsId']) === ''
            || !is_string($input['password'] ?? null)
            || $input['password'] === ''
        ) {
            return $this->json($response, 400, ['success' => false, 'message' => 'Missing credentials.']);
        }

        try {
            $user = ($this->serviceFactory)()->authenticate($input);

            return $this->json($response, 200, [
                'success' => true,
                'message' => 'Login successful.',
                'user' => $user,
            ]);
        } catch (InvalidUserCredentialsException) {
            return $this->json($response, 401, ['success' => false, 'message' => 'Invalid user credentials.']);
        } catch (Throwable $exception) {
            ($this->exceptionLogger)($exception);

            return $this->json($response, 500, ['success' => false, 'message' => 'Server error.']);
        }
    }

    /** @param array<string, mixed> $payload */
    private function json(ResponseInterface $response, int $status, array $payload): ResponseInterface
    {
        $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return $response->withStatus($status)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Cache-Control', 'no-store');
    }
}
