<?php

declare(strict_types=1);

namespace Sibs\KronosApi\Middleware;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class AdminSetupKeyMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly string $setupKey,
        private readonly ResponseFactoryInterface $responseFactory
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->setupKey === '') {
            return $this->jsonError(500, 'Server configuration error.');
        }

        $providedKey = $request->getHeaderLine('X-Admin-Setup-Key');

        if ($providedKey === '' || !hash_equals($this->setupKey, $providedKey)) {
            return $this->jsonError(401, 'Unauthorized.');
        }

        return $handler->handle($request);
    }

    private function jsonError(int $status, string $message): ResponseInterface
    {
        $response = $this->responseFactory->createResponse($status);
        $response->getBody()->write((string) json_encode([
            'success' => false,
            'message' => $message,
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
