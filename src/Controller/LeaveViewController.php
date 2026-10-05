<?php

declare(strict_types=1);

namespace Sibs\KronosApi\Controller;

use Closure;
use DateTimeImmutable;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Sibs\KronosApi\Repository\LeaveViewRepository;
use Throwable;

final class LeaveViewController
{
    private const DEFAULT_LIMIT = 15;
    private const MAX_LIMIT = 100;
    private readonly Closure $exceptionLogger;

    public function __construct(private readonly Closure $repositoryFactory, ?Closure $exceptionLogger = null)
    {
        $this->exceptionLogger = $exceptionLogger ?? static fn (Throwable $e) => error_log((string) $e);
    }

    public function employeeLeaves(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->handle($request, $response, false);
    }

    public function leaveManagement(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->handle($request, $response, true);
    }

    private function handle(ServerRequestInterface $request, ResponseInterface $response, bool $management): ResponseInterface
    {
        try {
            $query = $request->getQueryParams();
            $page = $this->positiveOrDefault($query['page'] ?? null, 1);
            $limit = min(self::MAX_LIMIT, $this->positiveOrDefault($query['limit'] ?? null, self::DEFAULT_LIMIT));
            if (($page - 1) > intdiv(PHP_INT_MAX, $limit)) { $page = 1; }
            $filters = [
                'gy_emp_code' => $this->optionalCode($query['gy_emp_code'] ?? null),
                'status' => $this->optionalStatus($query['status'] ?? null),
                'leave_type' => $this->optionalText($query['leave_type'] ?? null),
                'date_from' => $this->optionalDate($query['date_from'] ?? null),
                'date_to' => $this->optionalDate($query['date_to'] ?? null),
                'search' => $this->optionalText($query['search'] ?? null),
            ];
            if ($management) {
                $filters['account_id'] = $this->optionalPositiveId($query['account_id'] ?? null);
                $filters['department_id'] = $this->optionalPositiveId($query['department_id'] ?? null);
            }
            if ($filters['date_from'] !== null && $filters['date_to'] !== null && $filters['date_from'] > $filters['date_to']) {
                throw new InvalidArgumentException();
            }
            $method = $management ? 'findLeaveManagementPage' : 'findEmployeeLeavesPage';
            $result = ($this->repositoryFactory)()->{$method}($page, $limit, $filters);
            $total = $result['total'];
            $totalPages = max(1, (int) ceil($total / $limit));
            return $this->json($response, 200, [
                'success' => true,
                'data' => $result['data'],
                'filters' => $this->responseFilters($filters),
                'pagination' => [
                    'currentPage' => $page, 'totalPages' => $totalPages, 'totalRecords' => $total,
                    'total' => $total, 'limit' => $limit, 'hasPreviousPage' => $page > 1,
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
        $map = ['gy_emp_code' => 'gyEmpCode', 'status' => 'status', 'leave_type' => 'leaveType',
            'date_from' => 'dateFrom', 'date_to' => 'dateTo', 'search' => 'search',
            'account_id' => 'accountId', 'department_id' => 'departmentId'];
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

    private function optionalStatus(mixed $value): ?string
    {
        $status = $this->optionalNonNegativeId($value);
        return $status === null ? null : (string) $status;
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
