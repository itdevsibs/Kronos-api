<?php

declare(strict_types=1);

namespace Sibs\KronosApi\Controller;

use Closure;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final class DtrRelationshipController
{
    private const PAGE_SIZE = 100;
    private readonly Closure $exceptionLogger;

    public function __construct(private readonly Closure $repositoryFactory, ?Closure $exceptionLogger = null) { $this->exceptionLogger = $exceptionLogger ?? static fn (Throwable $e) => error_log((string) $e); }

    public function employeeLogs(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface { return $this->codeCollection($request, $response, $args['gy_emp_code'] ?? null, 'findLogsByEmployeeCode', 'gy_log_id'); }
    public function employeeEditLogs(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface { return $this->codeCollection($request, $response, $args['gy_emp_code'] ?? null, 'findEditLogsByEmployeeCode', 'gy_editlog_id'); }
    public function employeeDtrPublish(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface { return $this->codeCollection($request, $response, $args['gy_emp_code'] ?? null, 'findDtrPublicationsByEmployeeCode', 'dtr_publish_id'); }
    public function employeeAssignments(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface { return $this->codeCollection($request, $response, $args['gy_emp_code'] ?? null, 'findAssignmentsByEmployeeCode', 'at_id'); }
    public function userDtrPublications(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface { return $this->codeCollection($request, $response, $args['gy_emp_code'] ?? null, 'findDtrPublicationsByUserCode', 'dtr_publish_id'); }
    public function userAssignmentsAdded(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface { return $this->codeCollection($request, $response, $args['gy_emp_code'] ?? null, 'findAssignmentsAddedByUserCode', 'at_id'); }
    public function accountAssignments(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface { return $this->idCollection($request, $response, $args['gy_acc_id'] ?? null, 'findAssignmentsByAccountId', 'at_id'); }
    public function logEmployee(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface { return $this->single($response, $args['gy_log_id'] ?? null, 'findEmployeeByLogId'); }
    public function editLogEmployee(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface { return $this->single($response, $args['gy_editlog_id'] ?? null, 'findEmployeeByEditLogId'); }
    public function dtrEmployee(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface { return $this->single($response, $args['dtr_publish_id'] ?? null, 'findEmployeeByDtrPublishId'); }
    public function dtrPublisherUser(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface { return $this->single($response, $args['dtr_publish_id'] ?? null, 'findPublisherUserByDtrPublishId'); }
    public function assignmentEmployee(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface { return $this->single($response, $args['at_id'] ?? null, 'findEmployeeByAssignmentId'); }
    public function assignmentAccount(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface { return $this->single($response, $args['at_id'] ?? null, 'findAccountByAssignmentId'); }
    public function assignmentAddedByUser(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface { return $this->single($response, $args['at_id'] ?? null, 'findAddedByUserByAssignmentId'); }

    private function codeCollection(ServerRequestInterface $request, ResponseInterface $response, mixed $raw, string $method, string $cursor): ResponseInterface { $code = is_string($raw) ? trim($raw) : ''; return $code === '' || mb_strlen($code) > 11 ? $this->notFound($response) : $this->collection($request, $response, $code, $method, $cursor); }
    private function idCollection(ServerRequestInterface $request, ResponseInterface $response, mixed $raw, string $method, string $cursor): ResponseInterface { $id = $this->positiveId($raw); return $id === null ? $this->notFound($response) : $this->collection($request, $response, $id, $method, $cursor); }

    private function collection(ServerRequestInterface $request, ResponseInterface $response, int|string $parent, string $method, string $cursor): ResponseInterface
    {
        $afterId = $this->afterId($request->getQueryParams()['after_id'] ?? 0);
        try {
            $result = ($this->repositoryFactory)()->{$method}($parent, $afterId);
            if (!$result['parent_exists']) { return $this->notFound($response); }
            $hasMore = count($result['data']) > self::PAGE_SIZE;
            $data = array_slice($result['data'], 0, self::PAGE_SIZE);
            $last = $data === [] ? null : $data[array_key_last($data)];
            return $this->json($response, 200, ['success' => true, 'data' => $data, 'pagination' => ['limit' => self::PAGE_SIZE, 'count' => count($data), 'current_cursor' => $afterId, 'next_cursor' => $last === null ? null : (int) $last[$cursor], 'has_more' => $hasMore]]);
        } catch (Throwable $e) { return $this->serverError($response, $e); }
    }

    private function single(ResponseInterface $response, mixed $raw, string $method): ResponseInterface
    {
        $id = $this->positiveId($raw);
        if ($id === null) { return $this->notFound($response); }
        try {
            $result = ($this->repositoryFactory)()->{$method}($id);
            if (!$result['parent_exists']) { return $this->notFound($response); }
            if ($result['data'] === null) { return $this->notFound($response, 'Related resource not found.'); }
            return $this->json($response, 200, ['success' => true, 'data' => $result['data']]);
        } catch (Throwable $e) { return $this->serverError($response, $e); }
    }

    private function positiveId(mixed $value): ?int { if (!is_int($value) && !is_string($value)) return null; $id = filter_var($value, FILTER_VALIDATE_INT); return $id === false || $id <= 0 ? null : $id; }
    private function afterId(mixed $value): int { if (!is_int($value) && !is_string($value)) return 0; $id = filter_var($value, FILTER_VALIDATE_INT); return $id === false ? 0 : max(0, $id); }
    private function notFound(ResponseInterface $response, string $message = 'Resource not found.'): ResponseInterface { return $this->json($response, 404, ['success' => false, 'message' => $message]); }
    private function serverError(ResponseInterface $response, Throwable $e): ResponseInterface { ($this->exceptionLogger)($e); return $this->json($response, 500, ['success' => false, 'message' => 'Server error.']); }
    private function json(ResponseInterface $response, int $status, array $payload): ResponseInterface { $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)); return $response->withStatus($status)->withHeader('Content-Type', 'application/json')->withHeader('Cache-Control', 'private, no-store'); }
}
