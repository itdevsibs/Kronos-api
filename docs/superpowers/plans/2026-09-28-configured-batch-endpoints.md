# Configured Batch Endpoints Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add 27 fixed-schema, JWT-protected, read-only cursor endpoints through one shared repository/controller and one server-side whitelist.

**Architecture:** `BatchTableConfig` is the sole source of SQL identifiers and contains the live-schema mapping approved in the spec. `BatchTableRepository` performs the prepared 101-row SELECT for one configuration entry, while `BatchTableController` applies the existing employee pagination response behavior. `routes/api.php` registers every configured route with the existing JWT middleware and lazy repository construction.

**Tech Stack:** PHP 8.3, Slim 4, PSR-7, PDO/MySQL, existing custom PHP test harness.

**Spec:** `docs/superpowers/specs/2026-09-28-configured-batch-endpoints-design.md`

## Global Constraints

- Do not change JWT issuance, validation, or middleware behavior.
- Do not change the database schema or add packages.
- Do not register an existing route twice.
- Do not register `/api/v1/qds-notifications`; `qds_notify` has no primary key or index.
- Do not register `/api/v1/sibs-accounts`; `sibs_accounts` is absent from configured DB1.
- All table names, cursor columns, and selected columns must come exclusively from the fixed server-side whitelist.
- Use explicit columns, a PDO prepared `:after_id`, ascending keyset pagination, and `LIMIT 101`; never use `SELECT *`, `OFFSET`, or `COUNT`.
- Return at most 100 rows and use the actual last returned primary-key value as `next_cursor`.
- Log exceptions server-side and return only `{"success":false,"message":"Server error."}` for HTTP 500.
- Existing endpoints and unrelated files remain unchanged.

## Review Focus

- A malformed configuration identifier must be rejected before SQL preparation.
- Omitted, malformed, and negative `after_id` values must bind integer `0`.
- Exactly 100 rows must set `has_more` to false; 101 must set it to true while returning 100.
- Missing JWT must return 401 without creating a PDO repository.
- Invalid UTF-8 from MySQL must be logged and converted to the generic HTTP 500 response.

---

### Task 1: Fixed schema whitelist

**Files:**
- Create: `src/Config/BatchTableConfig.php`
- Modify: `tests/run.php`

**Interfaces:**
- Produces: `BatchTableConfig::all(): array<string, array{route: string, table: string, primaryKey: string, columns: list<string>}>`
- Produces: `BatchTableConfig::get(string $route): array{route: string, table: string, primaryKey: string, columns: list<string>}`
- Throws: `InvalidArgumentException` for an unknown route.

- [ ] **Step 1: Write the failing whitelist contract test**

Add a test asserting that `all()` exactly equals the 27 route/table/primary-key/column mappings in the approved spec, that every primary key is present in its columns, and that both skipped routes are absent.

- [ ] **Step 2: Run the suite and verify RED**

Run: `php tests/run.php`

Expected: FAIL because `Sibs\KronosApi\Config\BatchTableConfig` does not exist.

- [ ] **Step 3: Implement the whitelist**

Create the final class with only static read methods and the exact schema mappings from the spec. No environment, request, or database metadata input is accepted.

- [ ] **Step 4: Run the suite and verify GREEN**

Run: `php tests/run.php`

Expected: all tests pass.

---

### Task 2: Shared prepared repository

**Files:**
- Create: `src/Repository/BatchTableRepository.php`
- Modify: `tests/run.php`

**Interfaces:**
- Consumes: one configuration shape returned by `BatchTableConfig` and an existing `PDO`.
- Produces: `BatchTableRepository::__construct(PDO $pdo, array $configuration)`.
- Produces: `BatchTableRepository::findAfter(int $afterId): array`.

- [ ] **Step 1: Write failing query and safety tests**

Test representative lowercase and mixed-case column configurations. Assert the exact explicit SELECT, configured table/key, `LIMIT 101`, one integer `:after_id` binding, and returned rows. Add cases asserting malformed table, primary-key, or column identifiers are rejected before `PDO::prepare()`.

- [ ] **Step 2: Run the suite and verify RED**

Run: `php tests/run.php`

Expected: FAIL because `BatchTableRepository` does not exist.

- [ ] **Step 3: Implement minimal repository behavior**

Validate configured identifiers against `^[A-Za-z_][A-Za-z0-9_]*$`, build SQL only from validated configuration, prepare it, bind `:after_id` as `PDO::PARAM_INT`, execute, and fetch associative rows. Throw a server-side exception if preparation fails.

- [ ] **Step 4: Run the suite and verify GREEN**

Run: `php tests/run.php`

Expected: all tests pass.

---

### Task 3: Shared pagination controller

**Files:**
- Create: `src/Controller/BatchTableController.php`
- Modify: `tests/run.php`

**Interfaces:**
- Consumes: `Closure(): BatchTableRepository` and a configured primary-key string.
- Produces: `BatchTableController::index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface`.

- [ ] **Step 1: Write failing controller tests**

Add table-driven tests for 101 rows, exactly 100, short, and empty results; omitted, malformed, and negative cursors; non-contiguous primary-key values; repository exceptions; and invalid UTF-8 serialization. Assert response status, shape, actual final cursor, `has_more`, no-store header, logged exception, and generic HTTP 500 body.

- [ ] **Step 2: Run the suite and verify RED**

Run: `php tests/run.php`

Expected: FAIL because `BatchTableController` does not exist.

- [ ] **Step 3: Implement employee-compatible behavior**

Mirror `EmployeeController` with page size 100, fetch/slice behavior, configured primary-key lookup, normalized cursor, JSON response helper, and injected/default exception logger.

- [ ] **Step 4: Run the suite and verify GREEN**

Run: `php tests/run.php`

Expected: all tests pass.

---

### Task 4: Register all configured JWT routes

**Files:**
- Modify: `routes/api.php`
- Modify: `tests/run.php`

**Interfaces:**
- Consumes: `BatchTableConfig::all()`, the shared repository/controller, existing DB1 connection settings, and existing `JwtAuthMiddleware`/`JwtService`.
- Extends the routes closure with an optional test factory: `?Closure $batchTableRepositoryFactory = null`, invoked as `Closure(array $configuration): BatchTableRepository`.

- [ ] **Step 1: Write failing route integration tests**

For all 27 configured URLs, assert an unauthenticated request returns 401 without invoking the repository factory. Assert a valid test JWT returns 200, passes the correct configuration to the factory, and returns the configured primary key as `next_cursor`. Assert both skipped routes remain unregistered.

- [ ] **Step 2: Run the suite and verify RED**

Run: `php tests/run.php`

Expected: FAIL because the configured routes return 404.

- [ ] **Step 3: Implement lazy route registration**

Import the three shared classes, append the optional repository factory without changing existing parameter order, build one controller for each whitelist entry, and register `/api/v1/<route>` with a new instance of the existing JWT middleware.

- [ ] **Step 4: Run the suite and verify GREEN**

Run: `php tests/run.php`

Expected: all existing and new tests pass.

---

### Task 5: Schema and release verification

**Files:**
- Verify: `src/Config/BatchTableConfig.php`
- Verify: `src/Repository/BatchTableRepository.php`
- Verify: `src/Controller/BatchTableController.php`
- Verify: `routes/api.php`
- Verify: `tests/run.php`

- [ ] **Step 1: Compare the whitelist with live schema metadata**

Run a read-only `INFORMATION_SCHEMA.COLUMNS` and primary-key comparison for all 27 configured tables. Assert there are no missing, extra, reordered, or mismatched identifiers, that `qds_notify` still lacks a primary key, and that `sibs_accounts` remains absent from DB1.

- [ ] **Step 2: Run full automated verification**

Run: `php tests/run.php`

Expected: all tests pass with no failures or warnings.

- [ ] **Step 3: Run syntax validation**

Run: `find src routes public tests -name '*.php' -exec php -l '{}' \;`

Expected: every file reports no syntax errors.

- [ ] **Step 4: Validate Composer metadata**

Run: `composer validate --no-check-publish`

Expected: `composer.json` is valid.

- [ ] **Step 5: Request a focused read-only code review**

Review exact requirement coverage, dynamic-SQL isolation, cursor correctness, JWT-before-PDO ordering, error sanitization, existing-route preservation, and test completeness. Fix any Critical or Important findings and repeat verification.

## Repository note

This workspace is not a Git repository, so the usual per-task commit steps are intentionally omitted.
