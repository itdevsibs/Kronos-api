<?php

declare(strict_types=1);

namespace Sibs\KronosApi\Controller;

use Closure;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final class QdsRelationshipController
{
    private const PAGE_SIZE = 100;
    private const EMPLOYEE_CODE_MAX_LENGTH = 11;
    private readonly Closure $exceptionLogger;

    public function __construct(private readonly Closure $repositoryFactory, ?Closure $exceptionLogger = null)
    {
        $this->exceptionLogger = $exceptionLogger ?? static fn (Throwable $exception): bool => error_log((string) $exception);
    }

    public function employeeAssignments(ServerRequestInterface $request, ResponseInterface $response, array $arguments): ResponseInterface
    {
        $code = is_string($arguments['gy_emp_code'] ?? null) ? trim($arguments['gy_emp_code']) : '';
        if ($code === '' || mb_strlen($code) > self::EMPLOYEE_CODE_MAX_LENGTH) {
            return $this->notFound($response);
        }

        return $this->collection($request, $response, $code, 'findAssignmentsByEmployeeCode');
    }

    public function accountAssignments(ServerRequestInterface $request, ResponseInterface $response, array $arguments): ResponseInterface
    {
        $accountId = $this->positiveId($arguments['gy_acc_id'] ?? null);
        if ($accountId === null) {
            return $this->notFound($response);
        }

        return $this->collection($request, $response, $accountId, 'findAssignmentsByAccountId');
    }

    public function assignmentEmployee(ServerRequestInterface $request, ResponseInterface $response, array $arguments): ResponseInterface
    {
        return $this->single($response, $arguments['qag_id'] ?? null, 'findEmployeeByAssignmentId');
    }

    public function assignmentAccount(ServerRequestInterface $request, ResponseInterface $response, array $arguments): ResponseInterface
    {
        return $this->single($response, $arguments['qag_id'] ?? null, 'findAccountByAssignmentId');
    }

    private function collection(
        ServerRequestInterface $request,
        ResponseInterface $response,
        int|string $parent,
        string $method
    ): ResponseInterface {
        $afterId = $this->afterId($request->getQueryParams()['after_id'] ?? 0);

        try {
            $result = ($this->repositoryFactory)()->{$method}($parent, $afterId);
            if (!$result['parent_exists']) {
                return $this->notFound($response);
            }

            $hasMore = count($result['data']) > self::PAGE_SIZE;
            $data = array_slice($result['data'], 0, self::PAGE_SIZE);
            $last = $data === [] ? null : $data[array_key_last($data)];

            return $this->json($response, 200, [
                'success' => true,
                'data' => $data,
                'pagination' => [
                    'limit' => self::PAGE_SIZE,
                    'count' => count($data),
                    'current_cursor' => $afterId,
                    'next_cursor' => $last === null ? null : (int) $last['qag_id'],
                    'has_more' => $hasMore,
                ],
            ]);
        } catch (Throwable $exception) {
            return $this->serverError($response, $exception);
        }
    }

    private function single(ResponseInterface $response, mixed $rawId, string $method): ResponseInterface
    {
        $assignmentId = $this->positiveId($rawId);
        if ($assignmentId === null) {
            return $this->notFound($response);
        }

        try {
            $result = ($this->repositoryFactory)()->{$method}($assignmentId);
            if (!$result['parent_exists']) {
                return $this->notFound($response);
            }
            if ($result['data'] === null) {
                return $this->notFound($response, 'Related resource not found.');
            }

            return $this->json($response, 200, ['success' => true, 'data' => $result['data']]);
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

    private function afterId(mixed $value): int
    {
        if (!is_int($value) && !is_string($value)) {
            return 0;
        }
        $id = filter_var($value, FILTER_VALIDATE_INT);

        return $id === false ? 0 : max(0, $id);
    }

    private function notFound(ResponseInterface $response, string $message = 'Resource not found.'): ResponseInterface
    {
        return $this->json($response, 404, ['success' => false, 'message' => $message]);
    }

    private function serverError(ResponseInterface $response, Throwable $exception): ResponseInterface
    {
        ($this->exceptionLogger)($exception);

        return $this->json($response, 500, ['success' => false, 'message' => 'Server error.']);
    }

    private function json(ResponseInterface $response, int $status, array $payload): ResponseInterface
    {
        $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return $response->withStatus($status)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Cache-Control', 'private, no-store');
    }
}
