<?php

declare(strict_types=1);

namespace Sibs\KronosApi\Controller;

use Closure;
use DateTimeImmutable;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Sibs\KronosApi\Repository\WorkforceViewRepository;
use Throwable;

final class EmployeeAttendanceAdjustmentsController
{
    public function __construct(
        private readonly Closure $repositoryFactory
    ) {
    }

    public function index(
        Request $request,
        Response $response
    ): Response {
        try {
            $query = $request->getQueryParams();

            $page = $this->positiveInteger(
                $query,
                'page',
                1
            );

            $limit = $this->positiveInteger(
                $query,
                'limit',
                15,
                100
            );

            $filters = [];

            foreach (['gy_emp_code', 'search'] as $key) {
                $value = $this->optionalString($query, $key);

                if ($value !== null) {
                    $filters[$key] = $value;
                }
            }

            foreach (['account_id', 'department_id'] as $key) {
                $value = $this->optionalPositiveInteger(
                    $query,
                    $key
                );

                if ($value !== null) {
                    $filters[$key] = $value;
                }
            }

            foreach (['date_from', 'date_to'] as $key) {
                $value = $this->optionalDate($query, $key);

                if ($value !== null) {
                    $filters[$key] = $value;
                }
            }

            if (
                isset($filters['date_from'], $filters['date_to'])
                && $filters['date_from'] > $filters['date_to']
            ) {
                throw new InvalidArgumentException(
                    'date_from must not be after date_to.'
                );
            }

            $repository = ($this->repositoryFactory)();

            if (!$repository instanceof WorkforceViewRepository) {
                throw new \RuntimeException(
                    'Invalid workforce repository configuration.'
                );
            }

            $result = $repository
                ->findEmployeeAttendanceWithAdjustmentsPage(
                    $page,
                    $limit,
                    $filters
                );

            
            foreach ($result['data'] as &$row) {
                $requestStatus = strtolower(
                    trim((string) ($row['gy_tracker_request'] ?? ''))
                );

                $adjustmentStatus = $row['adjustment_status'] ?? null;
                $adjustmentType = $row['adjustment_type'] ?? null;

                // Pending schedule adjustment takes highest priority.
                $isEscalating = $adjustmentType !== null
                    && (int) $adjustmentType === 7
                    && $adjustmentStatus !== null
                    && (int) $adjustmentStatus === 0;

                if ($isEscalating) {
                    $row['attendance_status'] = 'Escalating';
                } else {
                    $row['attendance_status'] = match ($requestStatus) {
                        'approve', 'approved' => 'Approved',
                        'pending' => 'Pending',
                        'reject', 'rejected',
                        'deny', 'denied' => 'Rejected',
                        default => 'Unknown',
                    };
                }
            }

            unset($row);


            $total = (int) ($result['total'] ?? 0);

            $totalPages = (int) ceil($total / $limit);

            return $this->json(
                $response,
                [
                    'success' => true,
                    'data' => $result['data'] ?? [],
                    'pagination' => [
                        'currentPage' => $page,
                        'totalPages' => $totalPages,
                        'totalRecords' => $total,
                        'total' => $total,
                        'limit' => $limit,
                        'hasPreviousPage' => $page > 1,
                        'hasNextPage' => $page < $totalPages,
                    ],
                    'filters' => $filters,
                ],
                200
            );
        } catch (InvalidArgumentException $error) {
            return $this->json(
                $response,
                [
                    'success' => false,
                    'message' => $error->getMessage(),
                ],
                400
            );
        } catch (Throwable $error) {
            error_log(
                'Attendance adjustments API error: '
                . $error->getMessage()
            );

            return $this->json(
                $response,
                [
                    'success' => false,
                    'message' => 'Unable to fetch attendance records.',
                ],
                500
            );
        }
    }

    private function positiveInteger(
        array $query,
        string $key,
        int $default,
        ?int $maximum = null
    ): int {
        if (!array_key_exists($key, $query)) {
            return $default;
        }

        $options = [
            'min_range' => 1,
        ];

        if ($maximum !== null) {
            $options['max_range'] = $maximum;
        }

        $value = filter_var(
            $query[$key],
            FILTER_VALIDATE_INT,
            ['options' => $options]
        );

        if ($value === false) {
            throw new InvalidArgumentException(
                'Invalid ' . $key . ' parameter.'
            );
        }

        return (int) $value;
    }

    private function optionalPositiveInteger(
        array $query,
        string $key
    ): ?int {
        if (!array_key_exists($key, $query)) {
            return null;
        }

        if ($query[$key] === '') {
            return null;
        }

        return $this->positiveInteger(
            $query,
            $key,
            1
        );
    }

    private function optionalString(
        array $query,
        string $key
    ): ?string {
        if (!array_key_exists($key, $query)) {
            return null;
        }

        if (!is_string($query[$key])) {
            throw new InvalidArgumentException(
                'Invalid ' . $key . ' parameter.'
            );
        }

        $value = trim($query[$key]);

        return $value !== '' ? $value : null;
    }

    private function optionalDate(
        array $query,
        string $key
    ): ?string {
        $value = $this->optionalString($query, $key);

        if ($value === null) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $value
        );

        if (
            $date === false
            || $date->format('Y-m-d') !== $value
        ) {
            throw new InvalidArgumentException(
                'Invalid ' . $key
                . '. Expected YYYY-MM-DD.'
            );
        }

        return $value;
    }

    private function json(
        Response $response,
        array $payload,
        int $status
    ): Response {
        $response->getBody()->write(
            json_encode(
                $payload,
                JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE
            )
        );

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}
