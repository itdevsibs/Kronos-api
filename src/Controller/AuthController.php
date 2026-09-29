<?php

declare(strict_types=1);

namespace Sibs\KronosApi\Controller;

use Closure;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Sibs\KronosApi\Exception\InvalidApiCredentialsException;
use Sibs\KronosApi\Service\ApiTokenService;
use Sibs\KronosApi\Service\JwtService;
use Throwable;

final class AuthController
{
    /**
     * @param Closure(): ApiTokenService $apiTokenServiceFactory
     * @param Closure(): JwtService $jwtServiceFactory
     */
    public function __construct(
        private readonly Closure $apiTokenServiceFactory,
        private readonly Closure $jwtServiceFactory,
        private readonly int $accessTtl
    ) {
    }

    public function issue(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $input = $request->getParsedBody();

        if (!is_array($input)) {
            return $this->invalidCredentials($response);
        }

        try {
            $client = ($this->apiTokenServiceFactory)()->authenticate($input);
            $accessToken = ($this->jwtServiceFactory)()->issue($client);

            return $this->json($response, 200, [
                'success' => true,
                'token_type' => 'Bearer',
                'access_token' => $accessToken,
                'expires_in' => $this->accessTtl,
            ]);
        } catch (InvalidApiCredentialsException) {
            return $this->invalidCredentials($response);
        } catch (Throwable) {
            return $this->json($response, 500, [
                'success' => false,
                'message' => 'Server error.',
            ]);
        }
    }

    private function invalidCredentials(ResponseInterface $response): ResponseInterface
    {
        return $this->json($response, 401, [
            'success' => false,
            'message' => 'Invalid API credentials.',
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function json(ResponseInterface $response, int $status, array $payload): ResponseInterface
    {
        $response->getBody()->write((string) json_encode($payload, JSON_UNESCAPED_SLASHES));

        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Cache-Control', 'no-store');
    }
}
