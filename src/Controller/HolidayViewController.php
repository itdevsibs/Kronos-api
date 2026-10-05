<?php

declare(strict_types=1);

namespace Sibs\KronosApi\Controller;

use Closure;
use DateTimeImmutable;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final class HolidayViewController
{
    private const DEFAULT_LIMIT = 15;
    private const MAX_LIMIT = 100;
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
                'year' => $this->optionalPositiveInt($query['year'] ?? null),
                'date_from' => $this->optionalDate($query['date_from'] ?? null),
                'date_to' => $this->optionalDate($query['date_to'] ?? null),
                'location' => $this->optionalNonNegativeInt($query['location'] ?? null),
                'holiday_type_id' => $this->optionalPositiveInt($query['holiday_type_id'] ?? null),
                'search' => $this->optionalText($query['search'] ?? null),
            ];
            if ($filters['date_from'] !== null && $filters['date_to'] !== null
                && $filters['date_from'] > $filters['date_to']) {
                throw new InvalidArgumentException('Invalid date range.');
            }

            $result = ($this->repositoryFactory)()->findHolidayCalendarPage($page, $limit, $filters);
            $total = $result['total'];
            $totalPages = max(1, (int) ceil($total / $limit));

            return $this->json($response, 200, [
                'success' => true,
                'data' => $result['data'],
                'filters' => [
                    'year' => $filters['year'] ?? '',
                    'dateFrom' => $filters['date_from'] ?? '',
                    'dateTo' => $filters['date_to'] ?? '',
                    'location' => $filters['location'] ?? '',
                    'holidayTypeId' => $filters['holiday_type_id'] ?? '',
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

    private function optionalNonNegativeInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_int($value) && !is_string($value)) {
            throw new InvalidArgumentException('Invalid location filter.');
        }
        $validated = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($validated === false) {
            throw new InvalidArgumentException('Invalid location filter.');
        }

        return $validated;
    }

    private function optionalText(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value)) {
            throw new InvalidArgumentException('Invalid search filter.');
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
            throw new InvalidArgumentException('Invalid date filter.');
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException('Invalid date filter.');
        }

        return $value;
    }

    private function json(ResponseInterface $response, int $status, array $payload): ResponseInterface
    {
        $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return $response->withStatus($status)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Cache-Control', 'private, no-store');
    }
}
