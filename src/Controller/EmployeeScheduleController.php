<?php

declare(strict_types=1);

namespace Sibs\KronosApi\Controller;

use Closure;
use DateTimeImmutable;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Sibs\KronosApi\Repository\EmployeeScheduleRepository;
use Throwable;

final class EmployeeScheduleController
{
    private const DEFAULT_PAGE = 1;
    private const DEFAULT_LIMIT = 15;
    private const MAX_LIMIT = 100;

    private readonly Closure $exceptionLogger;

    /**
     * @param Closure(): EmployeeScheduleRepository $repositoryFactory
     * @param null|Closure(Throwable): void $exceptionLogger
     */
    public function __construct(
        private readonly Closure $repositoryFactory,
        ?Closure $exceptionLogger = null
    ) {
        $this->exceptionLogger = $exceptionLogger ?? static function (Throwable $exception): void {
            error_log((string) $exception);
        };
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $query = $request->getQueryParams();
            $employeeId = $this->requiredPositiveId($query['gy_emp_id'] ?? null);
            $page = $this->positiveIntegerOrDefault($query['page'] ?? null, self::DEFAULT_PAGE);
            $limit = min(
                self::MAX_LIMIT,
                $this->positiveIntegerOrDefault($query['limit'] ?? null, self::DEFAULT_LIMIT)
            );
            if (($page - 1) > intdiv(PHP_INT_MAX, $limit)) {
                $page = self::DEFAULT_PAGE;
            }
            $search = $this->optionalText($query['search'] ?? null);
            $dateFrom = $this->optionalDate($query['date_from'] ?? null);
            $dateTo = $this->optionalDate($query['date_to'] ?? null);

            $result = ($this->repositoryFactory)()->findPage(
                $employeeId,
                $page,
                $limit,
                $search,
                $dateFrom,
                $dateTo
            );
            $total = $result['total'];
            $totalPages = max(1, (int) ceil($total / $limit));

            return $this->json($response, 200, [
                'success' => true,
                'data' => $result['data'],
                'filters' => [
                    'search' => $search ?? '',
                    'dateFrom' => $dateFrom ?? '',
                    'dateTo' => $dateTo ?? '',
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
            return $this->json($response, 400, [
                'success' => false,
                'message' => 'Invalid query parameters.',
            ]);
        } catch (Throwable $exception) {
            ($this->exceptionLogger)($exception);

            return $this->json($response, 500, [
                'success' => false,
                'message' => 'Server error.',
            ]);
        }
    }

    private function requiredPositiveId(mixed $value): int
    {
        if (!is_int($value) && !is_string($value)) {
            throw new InvalidArgumentException('Invalid employee ID.');
        }
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw new InvalidArgumentException('Invalid employee ID.');
        }

        return $id;
    }

    private function positiveIntegerOrDefault(mixed $value, int $default): int
    {
        if ($value === null || (!is_int($value) && !is_string($value))) {
            return $default;
        }
        $validated = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $validated === false ? $default : $validated;
    }

    private function optionalText(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value)) {
            throw new InvalidArgumentException('Invalid search.');
        }
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function optionalDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value)) {
            throw new InvalidArgumentException('Invalid date.');
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException('Invalid date.');
        }

        return $value;
    }

    /** @param array<string, mixed> $payload */
    private function json(ResponseInterface $response, int $status, array $payload): ResponseInterface
    {
        $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Cache-Control', 'private, no-store');
    }
}
