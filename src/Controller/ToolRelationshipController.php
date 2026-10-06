<?php

declare(strict_types=1);

namespace Sibs\KronosApi\Controller;

use Closure;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final class ToolRelationshipController
{
    private const PAGE_SIZE = 100;
    private const EMPLOYEE_CODE_MAX_LENGTH = 11;
    private readonly Closure $exceptionLogger;

    public function __construct(private readonly Closure $repositoryFactory, ?Closure $exceptionLogger = null)
    {
        $this->exceptionLogger = $exceptionLogger
            ?? static fn (Throwable $exception): bool => error_log((string) $exception);
    }

    public function employeeData(ServerRequestInterface $request, ResponseInterface $response, array $arguments): ResponseInterface
    {
        $employeeCode = $this->employeeCode($arguments['gy_emp_code'] ?? null);
        if ($employeeCode === null) {
            return $this->notFound($response);
        }

        return $this->collection($request, $response, $employeeCode, 'findDataByEmployeeCode', 'td_id');
    }

    public function toolDetails(ServerRequestInterface $request, ResponseInterface $response, array $arguments): ResponseInterface
    {
        return $this->collection($request, $response, $arguments['tool_id'] ?? null, 'findDetailsByToolId', 'toold_id');
    }

    public function detailTool(ServerRequestInterface $request, ResponseInterface $response, array $arguments): ResponseInterface
    {
        return $this->single($response, $arguments['toold_id'] ?? null, 'findToolByDetailId');
    }

    public function detailData(ServerRequestInterface $request, ResponseInterface $response, array $arguments): ResponseInterface
    {
        return $this->collection($request, $response, $arguments['toold_id'] ?? null, 'findDataByToolDetailId', 'td_id');
    }

    public function dataDetail(ServerRequestInterface $request, ResponseInterface $response, array $arguments): ResponseInterface
    {
        return $this->single($response, $arguments['td_id'] ?? null, 'findDetailByDataId');
    }

    public function dataEmployee(ServerRequestInterface $request, ResponseInterface $response, array $arguments): ResponseInterface
    {
        return $this->single($response, $arguments['td_id'] ?? null, 'findEmployeeByDataId');
    }

    private function collection(
        ServerRequestInterface $request,
        ResponseInterface $response,
        mixed $rawParent,
        string $method,
        string $cursorKey
    ): ResponseInterface {
        $parent = is_string($rawParent) && $method === 'findDataByEmployeeCode'
            ? $rawParent
            : $this->positiveId($rawParent);
        if ($parent === null) {
            return $this->notFound($response);
        }
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
                    'next_cursor' => $last === null ? null : (int) $last[$cursorKey],
                    'has_more' => $hasMore,
                ],
            ]);
        } catch (Throwable $exception) {
            return $this->serverError($response, $exception);
        }
    }

    private function single(ResponseInterface $response, mixed $rawParentId, string $method): ResponseInterface
    {
        $parentId = $this->positiveId($rawParentId);
        if ($parentId === null) {
            return $this->notFound($response);
        }

        try {
            $result = ($this->repositoryFactory)()->{$method}($parentId);
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

    private function employeeCode(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $employeeCode = trim($value);

        return $employeeCode === '' || mb_strlen($employeeCode) > self::EMPLOYEE_CODE_MAX_LENGTH
            ? null
            : $employeeCode;
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
