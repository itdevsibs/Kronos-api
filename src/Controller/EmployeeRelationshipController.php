<?php

declare(strict_types=1);

namespace Sibs\KronosApi\Controller;

use Closure;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Sibs\KronosApi\Repository\EmployeeRelationshipRepository;
use Throwable;

final class EmployeeRelationshipController
{
    private const PAGE_SIZE = 100;

    private readonly Closure $exceptionLogger;

    /**
     * @param Closure(): EmployeeRelationshipRepository $repositoryFactory
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

    /** @param array<string, string> $arguments */
    public function employeeAccount(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $arguments
    ): ResponseInterface {
        return $this->single(
            $response,
            $arguments['employeeCode'] ?? null,
            'findAccountByEmployeeCode',
            true
        );
    }

    /** @param array<string, string> $arguments */
    public function employeeDepartment(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $arguments
    ): ResponseInterface {
        return $this->single(
            $response,
            $arguments['employeeCode'] ?? null,
            'findDepartmentByEmployeeCode',
            true
        );
    }

    /** @param array<string, string> $arguments */
    public function employeeUser(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $arguments
    ): ResponseInterface {
        return $this->single(
            $response,
            $arguments['employeeCode'] ?? null,
            'findUserByEmployeeCode',
            true
        );
    }

    /** @param array<string, string> $arguments */
    public function userEmployee(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $arguments
    ): ResponseInterface {
        return $this->single(
            $response,
            $arguments['employeeCode'] ?? null,
            'findEmployeeByUserCode',
            true
        );
    }

    /** @param array<string, string> $arguments */
    public function accountDepartment(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $arguments
    ): ResponseInterface {
        return $this->single($response, $arguments['accountId'] ?? null, 'findDepartmentByAccount');
    }

    /** @param array<string, string> $arguments */
    public function accountEmployees(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $arguments
    ): ResponseInterface {
        return $this->collection(
            $request,
            $response,
            $arguments['accountId'] ?? null,
            'findEmployeesByAccount',
            'gy_emp_id'
        );
    }

    /** @param array<string, string> $arguments */
    public function departmentAccounts(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $arguments
    ): ResponseInterface {
        return $this->collection(
            $request,
            $response,
            $arguments['departmentId'] ?? null,
            'findAccountsByDepartment',
            'gy_acc_id'
        );
    }

    /** @param array<string, string> $arguments */
    public function departmentEmployees(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $arguments
    ): ResponseInterface {
        return $this->collection(
            $request,
            $response,
            $arguments['departmentId'] ?? null,
            'findEmployeesByDepartment',
            'gy_emp_id'
        );
    }

    /** @param array<string, string> $arguments */
    public function employeeSchedules(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $arguments
    ): ResponseInterface {
        return $this->codeCollection($request, $response, $arguments['employeeCode'] ?? null, 'findSchedulesByEmployeeCode', 'gy_sched_id');
    }

    /** @param array<string, string> $arguments */
    public function employeeScheduleEscalations(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $arguments
    ): ResponseInterface {
        return $this->codeCollection($request, $response, $arguments['employeeCode'] ?? null, 'findScheduleEscalationsByEmployeeCode', 'gy_sched_esc_id');
    }

    /** @param array<string, string> $arguments */
    public function scheduleEscalationEmployee(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $arguments
    ): ResponseInterface {
        return $this->single($response, $arguments['scheduleEscalationId'] ?? null, 'findEmployeeByScheduleEscalationId');
    }

    /** @param array<string, string> $arguments */
    public function userScheduleRdRequests(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $arguments
    ): ResponseInterface {
        return $this->codeCollection($request, $response, $arguments['employeeCode'] ?? null, 'findScheduleRdRequestsByUserCode', 'gy_rd_id');
    }

    /** @param array<string, string> $arguments */
    public function userCreatedSchedules(ServerRequestInterface $request, ResponseInterface $response, array $arguments): ResponseInterface
    {
        return $this->codeCollection($request, $response, $arguments['gy_emp_code'] ?? null, 'findSchedulesCreatedByUserCode', 'gy_sched_id');
    }

    /** @param array<string, string> $arguments */
    public function userSubmittedScheduleEscalations(ServerRequestInterface $request, ResponseInterface $response, array $arguments): ResponseInterface
    {
        return $this->codeCollection($request, $response, $arguments['gy_emp_code'] ?? null, 'findSubmittedScheduleEscalationsByUserCode', 'gy_sched_esc_id');
    }

    /** @param array<string, string> $arguments */
    public function userReceivedScheduleEscalations(ServerRequestInterface $request, ResponseInterface $response, array $arguments): ResponseInterface
    {
        return $this->codeCollection($request, $response, $arguments['gy_emp_code'] ?? null, 'findReceivedScheduleEscalationsByUserCode', 'gy_sched_esc_id');
    }

    /** @param array<string, string> $arguments */
    public function userSupervisedScheduleEscalations(ServerRequestInterface $request, ResponseInterface $response, array $arguments): ResponseInterface
    {
        return $this->codeCollection($request, $response, $arguments['gy_emp_code'] ?? null, 'findSupervisedScheduleEscalationsByUserCode', 'gy_sched_esc_id');
    }

    /** @param array<string, string> $arguments */
    public function scheduleEmployee(ServerRequestInterface $request, ResponseInterface $response, array $arguments): ResponseInterface
    {
        return $this->single($response, $arguments['gy_sched_id'] ?? null, 'findEmployeeByScheduleId');
    }

    /** @param array<string, string> $arguments */
    public function scheduleCreatedByUser(ServerRequestInterface $request, ResponseInterface $response, array $arguments): ResponseInterface
    {
        return $this->single($response, $arguments['gy_sched_id'] ?? null, 'findCreatedByUserByScheduleId');
    }

    /** @param array<string, string> $arguments */
    public function scheduleEscalationRequestedByUser(ServerRequestInterface $request, ResponseInterface $response, array $arguments): ResponseInterface
    {
        return $this->single($response, $arguments['gy_sched_esc_id'] ?? null, 'findRequestedByUserByScheduleEscalationId');
    }

    /** @param array<string, string> $arguments */
    public function scheduleEscalationRequestedToUser(ServerRequestInterface $request, ResponseInterface $response, array $arguments): ResponseInterface
    {
        return $this->single($response, $arguments['gy_sched_esc_id'] ?? null, 'findRequestedToUserByScheduleEscalationId');
    }

    /** @param array<string, string> $arguments */
    public function scheduleEscalationSupervisorUser(ServerRequestInterface $request, ResponseInterface $response, array $arguments): ResponseInterface
    {
        return $this->single($response, $arguments['gy_sched_esc_id'] ?? null, 'findSupervisorUserByScheduleEscalationId');
    }

    /** @param array<string, string> $arguments */
    public function scheduleRdRequestTracker(ServerRequestInterface $request, ResponseInterface $response, array $arguments): ResponseInterface
    {
        return $this->single($response, $arguments['gy_rd_id'] ?? null, 'findTrackerByScheduleRdRequestId');
    }

    /** @param array<string, string> $arguments */
    public function scheduleRdRequestUser(ServerRequestInterface $request, ResponseInterface $response, array $arguments): ResponseInterface
    {
        return $this->single($response, $arguments['gy_rd_id'] ?? null, 'findUserByScheduleRdRequestId');
    }

    private function codeCollection(
        ServerRequestInterface $request,
        ResponseInterface $response,
        mixed $rawEmployeeCode,
        string $repositoryMethod,
        string $cursorColumn
    ): ResponseInterface {
        $employeeCode = $this->employeeCode($rawEmployeeCode);

        if ($employeeCode === null) {
            return $this->notFound($response, 'Resource not found.');
        }

        return $this->collectionResponse($request, $response, $employeeCode, $repositoryMethod, $cursorColumn);
    }

    private function single(
        ResponseInterface $response,
        mixed $rawIdentifier,
        string $repositoryMethod,
        bool $usesEmployeeCode = false
    ): ResponseInterface {
        $identifier = $usesEmployeeCode
            ? $this->employeeCode($rawIdentifier)
            : $this->positiveId($rawIdentifier);

        if ($identifier === null) {
            return $this->notFound($response, 'Resource not found.');
        }

        try {
            $result = ($this->repositoryFactory)()->{$repositoryMethod}($identifier);

            if (!$result['parent_exists']) {
                return $this->notFound($response, 'Resource not found.');
            }

            if ($result['data'] === null) {
                return $this->notFound($response, 'Related resource not found.');
            }

            return $this->json($response, 200, [
                'success' => true,
                'data' => $result['data'],
            ]);
        } catch (Throwable $exception) {
            return $this->serverError($response, $exception);
        }
    }

    private function collection(
        ServerRequestInterface $request,
        ResponseInterface $response,
        mixed $rawParentId,
        string $repositoryMethod,
        string $cursorColumn
    ): ResponseInterface {
        $parentId = $this->positiveId($rawParentId);

        if ($parentId === null) {
            return $this->notFound($response, 'Resource not found.');
        }

        return $this->collectionResponse($request, $response, $parentId, $repositoryMethod, $cursorColumn);
    }

    private function collectionResponse(
        ServerRequestInterface $request,
        ResponseInterface $response,
        int|string $parentIdentifier,
        string $repositoryMethod,
        string $cursorColumn
    ): ResponseInterface {
        $afterId = $this->normalizeAfterId($request->getQueryParams()['after_id'] ?? 0);

        try {
            $result = ($this->repositoryFactory)()->{$repositoryMethod}($parentIdentifier, $afterId);

            if (!$result['parent_exists']) {
                return $this->notFound($response, 'Resource not found.');
            }

            $hasMore = count($result['data']) > self::PAGE_SIZE;
            $data = array_slice($result['data'], 0, self::PAGE_SIZE);
            $lastRow = $data === [] ? null : $data[array_key_last($data)];

            return $this->json($response, 200, [
                'success' => true,
                'data' => $data,
                'pagination' => [
                    'limit' => self::PAGE_SIZE,
                    'count' => count($data),
                    'current_cursor' => $afterId,
                    'next_cursor' => $lastRow === null ? null : (int) $lastRow[$cursorColumn],
                    'has_more' => $hasMore,
                ],
            ]);
        } catch (Throwable $exception) {
            return $this->serverError($response, $exception);
        }
    }

    private function positiveId(mixed $value): ?int
    {
        if (!is_int($value) && !is_string($value)) {
            return null;
        }

        $id = filter_var($value, FILTER_VALIDATE_INT);

        return $id === false || $id <= 0 ? null : $id;
    }

    private function employeeCode(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $employeeCode = trim($value);

        return $employeeCode === '' ? null : $employeeCode;
    }

    private function normalizeAfterId(mixed $value): int
    {
        if (!is_int($value) && !is_string($value)) {
            return 0;
        }

        $afterId = filter_var($value, FILTER_VALIDATE_INT);

        return $afterId === false ? 0 : max(0, $afterId);
    }

    private function notFound(ResponseInterface $response, string $message): ResponseInterface
    {
        return $this->json($response, 404, [
            'success' => false,
            'message' => $message,
        ]);
    }

    private function serverError(ResponseInterface $response, Throwable $exception): ResponseInterface
    {
        ($this->exceptionLogger)($exception);

        return $this->json($response, 500, [
            'success' => false,
            'message' => 'Server error.',
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function json(ResponseInterface $response, int $status, array $payload): ResponseInterface
    {
        $response->getBody()->write(json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        ));

        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Cache-Control', 'private, no-store');
    }
}
