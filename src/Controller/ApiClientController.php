<?php

declare(strict_types=1);

namespace Sibs\KronosApi\Controller;

use Closure;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Sibs\KronosApi\Exception\ApiKeyCollisionException;
use Sibs\KronosApi\Service\ApiClientService;
use Throwable;

final class ApiClientController
{
    /** @param Closure(): ApiClientService $serviceFactory */
    public function __construct(private readonly Closure $serviceFactory)
    {
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $input = $request->getParsedBody();

        if (!is_array($input)) {
            return $this->json($response, 400, [
                'success' => false,
                'message' => 'Invalid JSON input.',
            ]);
        }

        try {
            $service = ($this->serviceFactory)();
            $client = $service->create($input);

            return $this->json($response, 201, [
                'success' => true,
                'message' => 'API client created successfully.',
                'client' => $client,
                'warning' => 'Save the API secret now. It cannot be retrieved again.',
            ]);
        } catch (InvalidArgumentException $exception) {
            return $this->json($response, 400, [
                'success' => false,
                'message' => $exception->getMessage(),
            ]);
        } catch (ApiKeyCollisionException) {
            return $this->json($response, 409, [
                'success' => false,
                'message' => 'API key collision. Please retry.',
            ]);
        } catch (Throwable) {
            return $this->json($response, 500, [
                'success' => false,
                'message' => 'Server error.',
            ]);
        }
    }

    /** @param array<string, mixed> $payload */
    private function json(ResponseInterface $response, int $status, array $payload): ResponseInterface
    {
        $response->getBody()->write((string) json_encode($payload, JSON_UNESCAPED_SLASHES));

        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json');
    }
}
