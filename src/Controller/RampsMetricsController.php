<?php

declare(strict_types=1);

namespace Sibs\KronosApi\Controller;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final class RampsMetricsController
{
    private readonly Closure $exceptionLogger;

    public function __construct(private readonly Closure $repositoryFactory, ?Closure $exceptionLogger = null)
    {
        $this->exceptionLogger = $exceptionLogger ?? static fn (Throwable $e) => error_log((string) $e);
    }

    public function weeklyAccountMetrics(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $query = $request->getQueryParams();
            $from = $this->date($query['date_from'] ?? null);
            $to = $this->date($query['date_to'] ?? null);
            if ($from->format('Y-m-d') < '1000-02-12' || $from > $to || $from->diff($to)->days > 730) {
                throw new InvalidArgumentException('Invalid reporting range.');
            }
            $mode = $query['calculation_mode'] ?? 'standard';
            if (!in_array($mode, ['standard', 'single_account_fast'], true)) {
                throw new InvalidArgumentException('Invalid calculation mode.');
            }
            $accountIds = $this->accountIds($query['account_ids'] ?? null);
            if ($mode === 'single_account_fast' && count($accountIds) !== 1) {
                throw new InvalidArgumentException('Fast mode requires one account.');
            }
            $yesterday = new DateTimeImmutable('yesterday', new DateTimeZone('Asia/Manila'));
            $weeks = ($this->repositoryFactory)()->weeklyMetrics(
                $from->format('Y-m-d'), $to->format('Y-m-d'), $accountIds,
                $mode, $yesterday->format('Y-m-d')
            );

            return $this->json($response, 200, [
                'success' => true,
                'data' => ['calculation_mode' => $mode, 'weeks' => $weeks],
            ]);
        } catch (InvalidArgumentException) {
            return $this->json($response, 400, ['success' => false, 'message' => 'Invalid query parameters.']);
        } catch (Throwable $e) {
            ($this->exceptionLogger)($e);
            return $this->json($response, 500, ['success' => false, 'message' => 'Server error.']);
        }
    }

    public function reportMetadata(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            return $this->json($response, 200, [
                'success' => true,
                'data' => ['firstAgentHireDate' => ($this->repositoryFactory)()->firstAgentHireDate()],
            ]);
        } catch (Throwable $e) {
            ($this->exceptionLogger)($e);
            return $this->json($response, 500, ['success' => false, 'message' => 'Server error.']);
        }
    }

    private function date(mixed $value): DateTimeImmutable
    {
        if (!is_string($value)) {
            throw new InvalidArgumentException('Date required.');
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('Asia/Manila'));
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException('Invalid date.');
        }
        return $date;
    }

    /** @return list<int> */
    private function accountIds(mixed $value): array
    {
        if ($value === null) {
            return [];
        }
        if (!is_string($value) || $value === '') {
            throw new InvalidArgumentException('Invalid account IDs.');
        }
        $parts = explode(',', $value);
        if (count($parts) > 100) {
            throw new InvalidArgumentException('Too many account IDs.');
        }
        $ids = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if (!preg_match('/^[1-9][0-9]*$/D', $part) || filter_var($part, FILTER_VALIDATE_INT) === false) {
                throw new InvalidArgumentException('Invalid account ID.');
            }
            $ids[] = (int) $part;
        }
        return array_values(array_unique($ids));
    }

    private function json(ResponseInterface $response, int $status, array $payload): ResponseInterface
    {
        $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json')
            ->withHeader('Cache-Control', 'private, no-store');
    }
}
