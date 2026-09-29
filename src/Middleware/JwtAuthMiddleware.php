<?php

declare(strict_types=1);

namespace Sibs\KronosApi\Middleware;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sibs\KronosApi\Exception\InvalidTokenException;
use Sibs\KronosApi\Service\JwtService;
use Throwable;

final class JwtAuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly JwtService $jwtService,
        private readonly ResponseFactoryInterface $responseFactory
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $authorization = $request->getHeaderLine('Authorization');

        if (preg_match('/^Bearer\s+(\S+)$/i', $authorization, $matches) !== 1) {
            return $this->error(401, 'Unauthorized.');
        }

        try {
            $claims = $this->jwtService->validate($matches[1]);
        } catch (InvalidTokenException) {
            return $this->error(401, 'Unauthorized.');
        } catch (Throwable) {
            return $this->error(500, 'Server error.');
        }

        return $handler->handle($request->withAttribute('api_client', $claims));
    }

    private function error(int $status, string $message): ResponseInterface
    {
        $response = $this->responseFactory->createResponse($status);
        $response->getBody()->write((string) json_encode([
            'success' => false,
            'message' => $message,
        ]));

        $response = $response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Cache-Control', 'no-store');

        return $status === 401
            ? $response->withHeader('WWW-Authenticate', 'Bearer')
            : $response;
    }
}
