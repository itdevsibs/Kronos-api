<?php

declare(strict_types=1);

namespace Sibs\KronosApi\Controller;

use Closure;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Sibs\KronosApi\Repository\EmployeeStatutoryPresenceRepository;
use Sibs\KronosApi\Service\GovernmentIdPresence;
use Throwable;

/** JWT-protected bulk completeness feed. NEVER returns actual government IDs. */
final class EmployeeStatutoryPresenceController
{
    private const PAGE_SIZE = 500;

    /** @param Closure(): EmployeeStatutoryPresenceRepository $repositoryFactory */
    public function __construct(private readonly Closure $repositoryFactory) {}

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $rawAfterId = $request->getQueryParams()['after_id'] ?? 0;
        $afterId = filter_var($rawAfterId, FILTER_VALIDATE_INT);
        $afterId = $afterId === false ? 0 : max(0, $afterId);
        try {
            $rows = ($this->repositoryFactory)()->findAfter($afterId);
            $hasMore = count($rows) > self::PAGE_SIZE;
            $page = array_slice($rows, 0, self::PAGE_SIZE);
            $data = [];
            foreach ($page as $row) {
                $code = trim((string) ($row['gy_emp_code'] ?? ''));
                $data[] = [
                    'sibsId' => $code,
                    ...GovernmentIdPresence::fromLegacyValues(
                        $row['gy_gov_id'] ?? null,
                        $row['gy_gov_idnum'] ?? null
                    ),
                ];
            }
            $last = $page === [] ? null : $page[array_key_last($page)];
            $cursor = $last === null ? null : (int) $last['gy_emp_id'];
            return $this->json($response, 200, [
                'success' => true,
                'data' => $data,
                'pagination' => [
                    'limit' => self::PAGE_SIZE,
                    'count' => count($data),
                    'next_cursor' => $cursor,
                    'has_more' => $hasMore,
                ],
            ]);
        } catch (Throwable $exception) {
            error_log('[Kronos statutory presence] ' . $exception->getMessage());
            return $this->json($response, 500, [
                'success' => false,
                'message' => 'Statutory presence unavailable.',
            ]);
        }
    }

    private function json(ResponseInterface $response, int $status, array $payload): ResponseInterface
    {
        $response->getBody()->write(json_encode($payload, JSON_THROW_ON_ERROR));
        return $response->withStatus($status)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Cache-Control', 'private, no-store');
    }
}
