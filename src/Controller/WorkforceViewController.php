<?php

declare(strict_types=1);

namespace Sibs\KronosApi\Controller;

use Closure;
use DateTimeImmutable;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Sibs\KronosApi\Repository\WorkforceViewRepository;
use Throwable;

final class WorkforceViewController
{
    private const DEFAULT_LIMIT = 15;
    private const MAX_LIMIT = 100;
    private readonly Closure $exceptionLogger;

    public function __construct(private readonly Closure $repositoryFactory, ?Closure $exceptionLogger = null)
    {
        $this->exceptionLogger = $exceptionLogger ?? static fn (Throwable $e) => error_log((string) $e);
    }

    public function employeeAttendance(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->handle($request, $response, 'findEmployeeAttendancePage', true, false);
    }

    public function employeeTrackerHistory(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->handle($request, $response, 'findEmployeeTrackerHistoryPage', false, false);
    }

    public function escalationQueue(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->handle($request, $response, 'findEscalationQueuePage', false, true);
    }

    private function handle(ServerRequestInterface $request, ResponseInterface $response, string $method, bool $department, bool $escalation): ResponseInterface
    {
        try {
            $query = $request->getQueryParams();
            $page = $this->positiveOrDefault($query['page'] ?? null, 1);
            $limit = min(self::MAX_LIMIT, $this->positiveOrDefault($query['limit'] ?? null, self::DEFAULT_LIMIT));
            if (($page - 1) > intdiv(PHP_INT_MAX, $limit)) { $page = 1; }
            $filters = [
                'gy_emp_code' => $this->optionalCode($query['gy_emp_code'] ?? null),
                'account_id' => $this->optionalPositiveId($query['account_id'] ?? null),
                'date_from' => $this->optionalDate($query['date_from'] ?? null),
                'date_to' => $this->optionalDate($query['date_to'] ?? null),
                'search' => $this->optionalText($query['search'] ?? null),
            ];
            if ($department) {
                $filters['department_id'] = $this->optionalPositiveId($query['department_id'] ?? null);
            }
            if ($escalation) {
                $filters['status'] = $this->optionalNonNegativeId($query['status'] ?? null);
                $filters['submitted_by'] = $this->optionalCode($query['submitted_by'] ?? null);
                $filters['recipient'] = $this->optionalCode($query['recipient'] ?? null);
            }
            if ($filters['date_from'] !== null && $filters['date_to'] !== null && $filters['date_from'] > $filters['date_to']) {
                throw new InvalidArgumentException('Invalid date range.');
            }
            $result = ($this->repositoryFactory)()->{$method}($page, $limit, $filters);
            $total = $result['total'];
            $totalPages = max(1, (int) ceil($total / $limit));

            return $this->json($response, 200, [
                'success' => true,
                'data' => $result['data'],
                'filters' => $this->responseFilters($filters),
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
        } catch (Throwable $e) {
            ($this->exceptionLogger)($e);
            return $this->json($response, 500, ['success' => false, 'message' => 'Server error.']);
        }
    }

    private function responseFilters(array $filters): array
    {
        $map = [
            'gy_emp_code' => 'gyEmpCode', 'account_id' => 'accountId', 'department_id' => 'departmentId',
            'date_from' => 'dateFrom', 'date_to' => 'dateTo', 'search' => 'search', 'status' => 'status',
            'submitted_by' => 'submittedBy', 'recipient' => 'recipient',
        ];
        $result = [];
        foreach ($filters as $key => $value) { $result[$map[$key]] = $value ?? ''; }
        return $result;
    }

    private function positiveOrDefault(mixed $value, int $default): int
    {
        if ($value === null || (!is_int($value) && !is_string($value))) { return $default; }
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $id === false ? $default : $id;
    }

    private function optionalPositiveId(mixed $value): ?int
    {
        if ($value === null || $value === '') { return null; }
        if (!is_int($value) && !is_string($value)) { throw new InvalidArgumentException(); }
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) { throw new InvalidArgumentException(); }
        return $id;
    }

    private function optionalNonNegativeId(mixed $value): ?int
    {
        if ($value === null || $value === '') { return null; }
        if (!is_int($value) && !is_string($value)) { throw new InvalidArgumentException(); }
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($id === false) { throw new InvalidArgumentException(); }
        return $id;
    }

    private function optionalCode(mixed $value): ?string
    {
        $value = $this->optionalText($value);
        if ($value !== null && mb_strlen($value) > 11) { throw new InvalidArgumentException(); }
        return $value;
    }

    private function optionalText(mixed $value): ?string
    {
        if ($value === null || $value === '') { return null; }
        if (!is_string($value)) { throw new InvalidArgumentException(); }
        $value = trim($value);
        return $value === '' ? null : $value;
    }

    private function optionalDate(mixed $value): ?string
    {
        if ($value === null || $value === '') { return null; }
        if (!is_string($value)) { throw new InvalidArgumentException(); }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($date === false || $date->format('Y-m-d') !== $value) { throw new InvalidArgumentException(); }
        return $value;
    }

    private function json(ResponseInterface $response, int $status, array $payload): ResponseInterface
    {
        $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json')->withHeader('Cache-Control', 'private, no-store');
    }
}
