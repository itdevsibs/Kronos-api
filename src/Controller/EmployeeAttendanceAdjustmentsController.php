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

            
            // Kronos Daily Logs stays Pending for the first three calendar
            // dates, based on Philippine time. The fourth date may show the
            // validated result. A pending type-7 escalation still has priority.
            $philippineZone = new \DateTimeZone('Asia/Manila');
            $today = new DateTimeImmutable('today', $philippineZone);

            foreach ($result['data'] as &$row) {
                $requestStatus = strtolower(
                    trim((string) ($row['gy_tracker_request'] ?? ''))
                );
                $adjustmentType = $row['adjustment_type'] ?? null;
                $adjustmentStatus = $row['adjustment_status'] ?? null;

                // Pending type-7 adjustment always wins, including when
                // the original attendance tracker says "approve".
                if (
                    $adjustmentType !== null
                    && (int) $adjustmentType === 7
                    && $adjustmentStatus !== null
                    && (int) $adjustmentStatus === 0
                ) {
                    $row['attendance_status'] = 'Escalating';
                    $row['attendance_status_source'] = 'schedule_adjustment';
                    continue;
                }

                // Do not label today's, yesterday's, or the previous
                // calendar day's attendance as Approved merely because
                // its tracker is completed or an automatic decision exists.
                // The actual Kronos Daily Logs display is Pending throughout
                // this review window. We intentionally use midnight in PH
                // rather than elapsed 24-hour periods.
                $rawDate = (string) ($row['gy_tracker_date'] ?? '');
                $trackerDayText = substr($rawDate, 0, 10);
                $trackerDay = DateTimeImmutable::createFromFormat(
                    '!Y-m-d',
                    $trackerDayText,
                    $philippineZone
                );

                if (
                    $trackerDay instanceof DateTimeImmutable
                    && $trackerDay->format('Y-m-d') === $trackerDayText
                    && $trackerDay <= $today
                    && $today < $trackerDay->modify('+3 days')
                ) {
                    $row['attendance_status'] = 'Pending';
                    $row['attendance_status_source'] = 'kronos_three_day_review';
                    continue;
                }

                $decision = match ($requestStatus) {
                    'approve', 'approved' => 'Approved',
                    'pending' => 'Pending',
                    'escalating', 'escalated' => 'Escalating',
                    'reject', 'rejected', 'deny', 'denied' => 'Rejected',
                    default => null,
                };

                if ($decision !== null) {
                    $row['attendance_status'] = $decision;
                    $row['attendance_status_source'] = 'tracker_request';
                    continue;
                }

                // With no explicit decision Kronos can leave the request
                // blank. Preserve the existing HRIS display convention:
                // 0 ongoing -> Pending; 1 complete -> Approved.
                // This is a lifecycle-based fallback, not a separate
                // confirmed approval event.
                $trackerStatus = trim(
                    (string) ($row['gy_tracker_status'] ?? '')
                );
                $row['attendance_status'] = match ($trackerStatus) {
                    '0' => 'Pending',
                    '1' => 'Approved',
                    default => 'Unknown',
                };
                $row['attendance_status_source'] = 'tracker_lifecycle_fallback';
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
