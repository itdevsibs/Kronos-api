<?php

declare(strict_types=1);

namespace Sibs\KronosApi\Controller;

use Closure;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Sibs\KronosApi\Repository\WorkforceRelationshipRepository;
use Throwable;

final class WorkforceRelationshipController
{
    private const PAGE_SIZE = 100;
    private readonly Closure $exceptionLogger;

    public function __construct(private readonly Closure $repositoryFactory, ?Closure $exceptionLogger = null)
    {
        $this->exceptionLogger = $exceptionLogger ?? static fn (Throwable $e) => error_log((string) $e);
    }

    public function employeeSupervisorUser(ServerRequestInterface $q, ResponseInterface $r, array $a): ResponseInterface { return $this->singleCode($r, $a, 'findSupervisorUserByEmployeeCode'); }
    public function employeeOperationsManagerUser(ServerRequestInterface $q, ResponseInterface $r, array $a): ResponseInterface { return $this->singleCode($r, $a, 'findOperationsManagerUserByEmployeeCode'); }
    public function employeeTrackers(ServerRequestInterface $q, ResponseInterface $r, array $a): ResponseInterface { return $this->collectionCode($q, $r, $a, 'findTrackersByEmployeeCode', 'gy_tracker_id'); }
    public function userSupervisedEmployees(ServerRequestInterface $q, ResponseInterface $r, array $a): ResponseInterface { return $this->collectionCode($q, $r, $a, 'findSupervisedEmployeesByUserCode', 'gy_emp_id'); }
    public function userSubmittedEscalations(ServerRequestInterface $q, ResponseInterface $r, array $a): ResponseInterface { return $this->collectionCode($q, $r, $a, 'findSubmittedEscalationsByUserCode', 'gy_esc_id'); }
    public function userReceivedEscalations(ServerRequestInterface $q, ResponseInterface $r, array $a): ResponseInterface { return $this->collectionCode($q, $r, $a, 'findReceivedEscalationsByUserCode', 'gy_esc_id'); }
    public function userSupervisedEscalations(ServerRequestInterface $q, ResponseInterface $r, array $a): ResponseInterface { return $this->collectionCode($q, $r, $a, 'findSupervisedEscalationsByUserCode', 'gy_esc_id'); }
    public function accountTrackers(ServerRequestInterface $q, ResponseInterface $r, array $a): ResponseInterface { return $this->collectionId($q, $r, $a['accountId'] ?? null, 'findTrackersByAccountId', 'gy_tracker_id'); }
    public function trackerEmployee(ServerRequestInterface $q, ResponseInterface $r, array $a): ResponseInterface { return $this->singleId($r, $a['trackerId'] ?? null, 'findEmployeeByTrackerId'); }
    public function trackerAccount(ServerRequestInterface $q, ResponseInterface $r, array $a): ResponseInterface { return $this->singleId($r, $a['trackerId'] ?? null, 'findAccountByTrackerId'); }
    public function trackerOperationsManagerUser(ServerRequestInterface $q, ResponseInterface $r, array $a): ResponseInterface { return $this->singleId($r, $a['trackerId'] ?? null, 'findOperationsManagerUserByTrackerId'); }
    public function trackerEscalations(ServerRequestInterface $q, ResponseInterface $r, array $a): ResponseInterface { return $this->collectionId($q, $r, $a['trackerId'] ?? null, 'findEscalationsByTrackerId', 'gy_esc_id'); }
    public function escalationTracker(ServerRequestInterface $q, ResponseInterface $r, array $a): ResponseInterface { return $this->singleId($r, $a['escalationId'] ?? null, 'findTrackerByEscalationId'); }
    public function escalationSubmittedByUser(ServerRequestInterface $q, ResponseInterface $r, array $a): ResponseInterface { return $this->singleId($r, $a['escalationId'] ?? null, 'findSubmittedByUserByEscalationId'); }
    public function escalationRecipientUser(ServerRequestInterface $q, ResponseInterface $r, array $a): ResponseInterface { return $this->singleId($r, $a['escalationId'] ?? null, 'findRecipientUserByEscalationId'); }
    public function escalationSupervisorUser(ServerRequestInterface $q, ResponseInterface $r, array $a): ResponseInterface { return $this->singleId($r, $a['escalationId'] ?? null, 'findSupervisorUserByEscalationId'); }

    private function singleCode(ResponseInterface $response, array $arguments, string $method): ResponseInterface
    {
        $code = $this->employeeCode($arguments['employeeCode'] ?? null);
        return $code === null ? $this->notFound($response) : $this->single($response, $method, $code);
    }

    private function singleId(ResponseInterface $response, mixed $rawId, string $method): ResponseInterface
    {
        $id = $this->positiveId($rawId);
        return $id === null ? $this->notFound($response) : $this->single($response, $method, $id);
    }

    private function single(ResponseInterface $response, string $method, int|string $id): ResponseInterface
    {
        try {
            $result = ($this->repositoryFactory)()->{$method}($id);
            if (!$result['parent_exists']) {
                return $this->notFound($response);
            }
            if ($result['data'] === null) {
                return $this->json($response, 404, ['success' => false, 'message' => 'Related resource not found.']);
            }
            return $this->json($response, 200, ['success' => true, 'data' => $result['data']]);
        } catch (Throwable $e) {
            return $this->serverError($response, $e);
        }
    }

    private function collectionCode(ServerRequestInterface $request, ResponseInterface $response, array $arguments, string $method, string $cursor): ResponseInterface
    {
        $code = $this->employeeCode($arguments['employeeCode'] ?? null);
        return $code === null ? $this->notFound($response) : $this->collection($request, $response, $method, $code, $cursor);
    }

    private function collectionId(ServerRequestInterface $request, ResponseInterface $response, mixed $rawId, string $method, string $cursor): ResponseInterface
    {
        $id = $this->positiveId($rawId);
        return $id === null ? $this->notFound($response) : $this->collection($request, $response, $method, $id, $cursor);
    }

    private function collection(ServerRequestInterface $request, ResponseInterface $response, string $method, int|string $parent, string $cursor): ResponseInterface
    {
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
                    'next_cursor' => $last === null ? null : (int) $last[$cursor],
                    'has_more' => $hasMore,
                ],
            ]);
        } catch (Throwable $e) {
            return $this->serverError($response, $e);
        }
    }

    private function employeeCode(mixed $value): ?string
    {
        if (!is_string($value)) { return null; }
        $value = trim($value);
        return $value === '' || mb_strlen($value) > 11 ? null : $value;
    }

    private function positiveId(mixed $value): ?int
    {
        if (!is_int($value) && !is_string($value)) { return null; }
        $id = filter_var($value, FILTER_VALIDATE_INT);
        return $id === false || $id <= 0 ? null : $id;
    }

    private function afterId(mixed $value): int
    {
        if (!is_int($value) && !is_string($value)) { return 0; }
        $id = filter_var($value, FILTER_VALIDATE_INT);
        return $id === false ? 0 : max(0, $id);
    }

    private function notFound(ResponseInterface $response): ResponseInterface
    {
        return $this->json($response, 404, ['success' => false, 'message' => 'Resource not found.']);
    }

    private function serverError(ResponseInterface $response, Throwable $e): ResponseInterface
    {
        ($this->exceptionLogger)($e);
        return $this->json($response, 500, ['success' => false, 'message' => 'Server error.']);
    }

    private function json(ResponseInterface $response, int $status, array $payload): ResponseInterface
    {
        $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json')->withHeader('Cache-Control', 'private, no-store');
    }
}
