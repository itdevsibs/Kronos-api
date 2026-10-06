<?php

declare(strict_types=1);

namespace Sibs\KronosApi\Controller;

use Closure;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final class QdsViewController
{
    private const DEFAULT_LIMIT = 15;
    private const MAX_LIMIT = 100;
    private const EMPLOYEE_CODE_MAX_LENGTH = 11;
    private readonly Closure $exceptionLogger;

    public function __construct(private readonly Closure $repositoryFactory, ?Closure $exceptionLogger = null)
    {
        $this->exceptionLogger = $exceptionLogger ?? static fn (Throwable $exception): bool => error_log((string) $exception);
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $query = $request->getQueryParams();
            $page = $this->positiveOrDefault($query['page'] ?? null, 1);
            $limit = min(self::MAX_LIMIT, $this->positiveOrDefault($query['limit'] ?? null, self::DEFAULT_LIMIT));
            if (($page - 1) > intdiv(PHP_INT_MAX, $limit)) {
                $page = 1;
            }

            $filters = [
                'gy_emp_code' => $this->optionalCode($query['gy_emp_code'] ?? null),
                'account_id' => $this->optionalPositiveInt($query['account_id'] ?? null),
                'search' => $this->optionalText($query['search'] ?? null),
            ];
            $result = ($this->repositoryFactory)()->findAssignmentPage($page, $limit, $filters);
            $total = $result['total'];
            $totalPages = max(1, (int) ceil($total / $limit));

            return $this->json($response, 200, [
                'success' => true,
                'data' => $result['data'],
                'filters' => [
                    'gyEmpCode' => $filters['gy_emp_code'] ?? '',
                    'accountId' => $filters['account_id'] ?? '',
                    'search' => $filters['search'] ?? '',
                ],
                'pagination' => [
                    'currentPage' => $page,
                    'totalPages' => $totalPages,
                    'totalRecords' => $total,
                    'total' => $total,
                    'limit' => $limit,
                    'hasPreviousPage' => $page > 1,
                    'hasNextPage' => $page < $totalPages,
                ],
            ]);
        } catch (InvalidArgumentException) {
            return $this->json($response, 400, ['success' => false, 'message' => 'Invalid query parameters.']);
        } catch (Throwable $exception) {
            ($this->exceptionLogger)($exception);

            return $this->json($response, 500, ['success' => false, 'message' => 'Server error.']);
        }
    }

    private function positiveOrDefault(mixed $value, int $default): int
    {
        if ($value === null || (!is_int($value) && !is_string($value))) {
            return $default;
        }
        $validated = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $validated === false ? $default : $validated;
    }

    private function optionalPositiveInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_int($value) && !is_string($value)) {
            throw new InvalidArgumentException('Invalid integer filter.');
        }
        $validated = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($validated === false) {
            throw new InvalidArgumentException('Invalid integer filter.');
        }

        return $validated;
    }

    private function optionalCode(mixed $value): ?string
    {
        $code = $this->optionalText($value);
        if ($code !== null && mb_strlen($code) > self::EMPLOYEE_CODE_MAX_LENGTH) {
            throw new InvalidArgumentException('Invalid employee code.');
        }

        return $code;
    }

    private function optionalText(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value)) {
            throw new InvalidArgumentException('Invalid text filter.');
        }
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function json(ResponseInterface $response, int $status, array $payload): ResponseInterface
    {
        $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return $response->withStatus($status)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Cache-Control', 'private, no-store');
    }
}
