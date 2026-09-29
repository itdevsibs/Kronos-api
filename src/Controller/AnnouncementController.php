<?php

declare(strict_types=1);

namespace Sibs\KronosApi\Controller;

use Closure;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Sibs\KronosApi\Repository\AnnouncementRepository;
use Throwable;

final class AnnouncementController
{
    private const PAGE_SIZE = 100;
    private readonly Closure $exceptionLogger;

    /** @param Closure(): AnnouncementRepository $repositoryFactory @param null|Closure(Throwable): void $exceptionLogger */
    public function __construct(private readonly Closure $repositoryFactory, ?Closure $exceptionLogger = null)
    {
        $this->exceptionLogger = $exceptionLogger ?? static function (Throwable $exception): void { error_log((string) $exception); };
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $afterId = $this->normalizeAfterId($request->getQueryParams()['after_id'] ?? 0);
        try {
            $rows = ($this->repositoryFactory)()->findAfter($afterId);
            $hasMore = count($rows) > self::PAGE_SIZE;
            $data = array_slice($rows, 0, self::PAGE_SIZE);
            $lastRow = $data === [] ? null : $data[array_key_last($data)];
            return $this->json($response, 200, [
                'success' => true,
                'data' => $data,
                'pagination' => [
                    'limit' => self::PAGE_SIZE,
                    'count' => count($data),
                    'current_cursor' => $afterId,
                    'next_cursor' => $lastRow === null ? null : (int) $lastRow['gy_ann_id'],
                    'has_more' => $hasMore,
                ],
            ]);
        } catch (Throwable $exception) {
            ($this->exceptionLogger)($exception);
            return $this->json($response, 500, ['success' => false, 'message' => 'Server error.']);
        }
    }

    private function normalizeAfterId(mixed $value): int
    {
        if (!is_int($value) && !is_string($value)) { return 0; }
        $afterId = filter_var($value, FILTER_VALIDATE_INT);
        return $afterId === false ? 0 : max(0, $afterId);
    }

    /** @param array<string, mixed> $payload */
    private function json(ResponseInterface $response, int $status, array $payload): ResponseInterface
    {
        $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json')
            ->withHeader('Cache-Control', 'private, no-store');
    }
}
