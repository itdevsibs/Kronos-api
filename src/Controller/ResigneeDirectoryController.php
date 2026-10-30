<?php

declare(strict_types=1);

namespace Sibs\KronosApi\Controller;

use Closure;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Sibs\KronosApi\Repository\ResigneeDirectoryRepository;
use Throwable;

/** JWT-protected Resignee feed; source is gy_employee + gy_user only. */
final class ResigneeDirectoryController
{
    /** @param Closure(): ResigneeDirectoryRepository $repositoryFactory */
    public function __construct(private readonly Closure $repositoryFactory) {}

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $input = self::parseQuery($request->getQueryParams());
            $result = ($this->repositoryFactory)()->findPage($input['page'], $input['limit'], $input);
            return $this->json($response, 200, ['success' => true, ...$result]);
        } catch (InvalidArgumentException) {
            return $this->json($response, 400, ['success' => false, 'message' => 'Invalid resignee query parameters.']);
        } catch (Throwable $exception) {
            error_log('[Kronos resignees] ' . $exception->getMessage());
            return $this->json($response, 500, ['success' => false, 'message' => 'Resignee data unavailable.']);
        }
    }

    /** @param array<string, mixed> $input
     * @return array{page: int, limit: int, search: string, account: string, accountIds: list<int>, accountMatchIds: list<int>, searchAccountIds: list<int>}
     */
    public static function parseQuery(array $input): array
    {
        $positive = static function (mixed $value, int $fallback, int $maximum): int {
            if ($value === null || $value === '') return $fallback;
            if (!is_string($value) && !is_int($value)) throw new InvalidArgumentException('Invalid number.');
            $number = filter_var($value, FILTER_VALIDATE_INT);
            if ($number === false || $number < 1) throw new InvalidArgumentException('Invalid number.');
            return min($maximum, $number);
        };
        $short = static function (mixed $value): string {
            if ($value === null) return '';
            if (!is_string($value) || strlen($value) > 480) throw new InvalidArgumentException('Invalid string.');
            return substr(trim($value), 0, 120);
        };
        $ids = static function (mixed $value): array {
            if ($value === null || $value === '') return [];
            if (!is_string($value) || strlen($value) > 2048) throw new InvalidArgumentException('Invalid account identifiers.');
            $values = explode(',', $value);
            if (count($values) > 150) throw new InvalidArgumentException('Too many identifiers.');
            $parsed = [];
            foreach ($values as $item) {
                $item = trim($item);
                // 0 is a deliberate no-match sentinel for unknown departments.
                if ($item === '' || !ctype_digit($item)) throw new InvalidArgumentException('Invalid account identifier.');
                $parsed[] = (int) $item;
            }
            return array_values(array_unique($parsed));
        };
        return [
            'page' => $positive($input['page'] ?? null, 1, 1000000),
            'limit' => $positive($input['limit'] ?? null, 15, 50),
            'search' => $short($input['search'] ?? null),
            'account' => $short($input['account'] ?? null),
            'accountIds' => $ids($input['account_ids'] ?? null),
            'accountMatchIds' => $ids($input['account_match_ids'] ?? null),
            'searchAccountIds' => $ids($input['search_account_ids'] ?? null),
        ];
    }

    private function json(ResponseInterface $response, int $status, array $data): ResponseInterface
    {
        $response->getBody()->write(json_encode($data, JSON_THROW_ON_ERROR));
        return $response->withStatus($status)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Cache-Control', 'private, no-store');
    }
}
