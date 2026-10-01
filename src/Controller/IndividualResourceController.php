<?php

declare(strict_types=1);

namespace Sibs\KronosApi\Controller;

use Closure;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Sibs\KronosApi\Repository\IndividualResourceRepository;
use Throwable;

final class IndividualResourceController
{
    private const EMPLOYEE_CODE_MAX_LENGTH = 11;

    private readonly Closure $exceptionLogger;

    /**
     * @param Closure(): IndividualResourceRepository $repositoryFactory
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
    public function employee(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $arguments
    ): ResponseInterface {
        $employeeCode = $this->employeeCode($arguments['gy_emp_code'] ?? null);

        return $employeeCode === null
            ? $this->invalidIdentifier($response)
            : $this->find($response, 'findEmployeeByCode', $employeeCode);
    }

    /** @param array<string, string> $arguments */
    public function account(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $arguments
    ): ResponseInterface {
        $accountId = $this->positiveId($arguments['gy_acc_id'] ?? null);

        return $accountId === null
            ? $this->invalidIdentifier($response)
            : $this->find($response, 'findAccountById', $accountId);
    }

    /** @param array<string, string> $arguments */
    public function department(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $arguments
    ): ResponseInterface {
        $departmentId = $this->positiveId($arguments['id_department'] ?? null);

        return $departmentId === null
            ? $this->invalidIdentifier($response)
            : $this->find($response, 'findDepartmentById', $departmentId);
    }

    /** @param array<string, string> $arguments */
    public function user(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $arguments
    ): ResponseInterface {
        $employeeCode = $this->employeeCode($arguments['gy_emp_code'] ?? null);

        return $employeeCode === null
            ? $this->invalidIdentifier($response)
            : $this->find($response, 'findUserByEmployeeCode', $employeeCode);
    }

    private function find(ResponseInterface $response, string $repositoryMethod, int|string $identifier): ResponseInterface
    {
        try {
            $data = ($this->repositoryFactory)()->{$repositoryMethod}($identifier);

            if ($data === null) {
                return $this->json($response, 404, [
                    'success' => false,
                    'message' => 'Resource not found.',
                ]);
            }

            return $this->json($response, 200, [
                'success' => true,
                'data' => $data,
            ]);
        } catch (Throwable $exception) {
            ($this->exceptionLogger)($exception);

            return $this->json($response, 500, [
                'success' => false,
                'message' => 'Server error.',
            ]);
        }
    }

    private function employeeCode(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $employeeCode = trim($value);

        if ($employeeCode === '' || mb_strlen($employeeCode) > self::EMPLOYEE_CODE_MAX_LENGTH) {
            return null;
        }

        return $employeeCode;
    }

    private function positiveId(mixed $value): ?int
    {
        if (!is_int($value) && !is_string($value)) {
            return null;
        }

        $id = filter_var($value, FILTER_VALIDATE_INT);

        return $id === false || $id <= 0 ? null : $id;
    }

    private function invalidIdentifier(ResponseInterface $response): ResponseInterface
    {
        return $this->json($response, 400, [
            'success' => false,
            'message' => 'Invalid resource identifier.',
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
