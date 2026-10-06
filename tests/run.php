<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sibs\KronosApi\Controller\AccountController;
use Sibs\KronosApi\Controller\AnnouncementController;
use Sibs\KronosApi\Controller\AnnouncementRelationshipController;
use Sibs\KronosApi\Controller\AnnouncementViewController;
use Sibs\KronosApi\Controller\BatchTableController;
use Sibs\KronosApi\Controller\ConfirmationController;
use Sibs\KronosApi\Controller\ApiClientController;
use Sibs\KronosApi\Controller\AuthController;
use Sibs\KronosApi\Controller\CronSettingController;
use Sibs\KronosApi\Controller\DepartmentController;
use Sibs\KronosApi\Controller\DobRecordController;
use Sibs\KronosApi\Controller\DtrPublishController;
use Sibs\KronosApi\Controller\DtrRelationshipController;
use Sibs\KronosApi\Controller\DtrViewController;
use Sibs\KronosApi\Controller\EmployeeAccountAssignmentController;
use Sibs\KronosApi\Controller\EmployeeController;
use Sibs\KronosApi\Controller\EmployeeDirectoryController;
use Sibs\KronosApi\Controller\EmployeeRelationshipController;
use Sibs\KronosApi\Controller\EmployeeScheduleController;
use Sibs\KronosApi\Controller\IndividualResourceController;
use Sibs\KronosApi\Controller\HolidayRelationshipController;
use Sibs\KronosApi\Controller\HolidayViewController;
use Sibs\KronosApi\Controller\LeaveRelationshipController;
use Sibs\KronosApi\Controller\LeaveViewController;
use Sibs\KronosApi\Controller\QdsRelationshipController;
use Sibs\KronosApi\Controller\QdsViewController;
use Sibs\KronosApi\Controller\TeamToolRelationshipController;
use Sibs\KronosApi\Controller\ToolRelationshipController;
use Sibs\KronosApi\Controller\UserController;
use Sibs\KronosApi\Controller\WorkforceRelationshipController;
use Sibs\KronosApi\Controller\WorkforceViewController;
use Sibs\KronosApi\Config\BatchTableConfig;
use Sibs\KronosApi\Database\Database;
use Sibs\KronosApi\Environment;
use Sibs\KronosApi\Exception\ApiKeyCollisionException;
use Sibs\KronosApi\Exception\InvalidApiCredentialsException;
use Sibs\KronosApi\Exception\InvalidTokenException;
use Sibs\KronosApi\Middleware\AdminSetupKeyMiddleware;
use Sibs\KronosApi\Middleware\JwtAuthMiddleware;
use Sibs\KronosApi\Repository\AccountRepository;
use Sibs\KronosApi\Repository\AnnouncementRepository;
use Sibs\KronosApi\Repository\AnnouncementRelationshipRepository;
use Sibs\KronosApi\Repository\AnnouncementViewRepository;
use Sibs\KronosApi\Repository\BatchTableRepository;
use Sibs\KronosApi\Repository\ConfirmationRepository;
use Sibs\KronosApi\Repository\CronSettingRepository;
use Sibs\KronosApi\Repository\DepartmentRepository;
use Sibs\KronosApi\Repository\DobRecordRepository;
use Sibs\KronosApi\Repository\DtrPublishRepository;
use Sibs\KronosApi\Repository\DtrRelationshipRepository;
use Sibs\KronosApi\Repository\DtrViewRepository;
use Sibs\KronosApi\Repository\EmployeeAccountAssignmentRepository;
use Sibs\KronosApi\Repository\EmployeeRepository;
use Sibs\KronosApi\Repository\EmployeeDirectoryRepository;
use Sibs\KronosApi\Repository\EmployeeRelationshipRepository;
use Sibs\KronosApi\Repository\EmployeeScheduleRepository;
use Sibs\KronosApi\Repository\UserRepository;
use Sibs\KronosApi\Repository\IndividualResourceRepository;
use Sibs\KronosApi\Repository\HolidayRelationshipRepository;
use Sibs\KronosApi\Repository\HolidayViewRepository;
use Sibs\KronosApi\Repository\LeaveRelationshipRepository;
use Sibs\KronosApi\Repository\LeaveViewRepository;
use Sibs\KronosApi\Repository\QdsRelationshipRepository;
use Sibs\KronosApi\Repository\QdsViewRepository;
use Sibs\KronosApi\Repository\TeamToolRelationshipRepository;
use Sibs\KronosApi\Repository\ToolRelationshipRepository;
use Sibs\KronosApi\Repository\WorkforceRelationshipRepository;
use Sibs\KronosApi\Repository\WorkforceViewRepository;
use Sibs\KronosApi\Service\ApiClientService;
use Sibs\KronosApi\Service\ApiTokenService;
use Sibs\KronosApi\Service\JwtService;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

final class RecordingPdo extends PDO
{
    public ?string $query = null;

    public int $prepareCalls = 0;

    /** @var array<string, mixed>|null */
    public ?array $parameters = null;

    public ?Throwable $executeException = null;

    /** @var array<string, mixed>|false */
    public array|false $fetchResult = false;

    /** @var list<array<string, mixed>> */
    public array $fetchAllResult = [];

    /** @var array<string, array{value: mixed, type: int}> */
    public array $boundValues = [];

    public function __construct()
    {
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $this->prepareCalls++;
        $this->query = $query;

        return new RecordingPdoStatement($this);
    }

    public function lastInsertId(?string $name = null): string|false
    {
        return '42';
    }
}

final class RecordingPdoStatement extends PDOStatement
{
    public function __construct(private readonly RecordingPdo $pdo)
    {
    }

    public function execute(?array $params = null): bool
    {
        $this->pdo->parameters = $params;

        if ($this->pdo->executeException !== null) {
            throw $this->pdo->executeException;
        }

        return true;
    }

    public function bindValue(string|int $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        $this->pdo->boundValues[(string) $param] = ['value' => $value, 'type' => $type];

        return true;
    }

    public function fetch(
        int $mode = PDO::FETCH_DEFAULT,
        int $cursorOrientation = PDO::FETCH_ORI_NEXT,
        int $cursorOffset = 0
    ): mixed {
        return $this->pdo->fetchResult;
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        return $this->pdo->fetchAllResult;
    }
}

final class EmployeeDirectoryRecordingPdo extends PDO
{
    /** @var list<string> */
    public array $queries = [];

    /** @var array<int, array<string, array{value: mixed, type: int}>> */
    public array $bindings = [];

    /** @var array<int, array<string, mixed>|false> */
    public array $fetchResults = [];

    /** @var array<int, list<array<string, mixed>>> */
    public array $fetchAllResults = [];

    public function __construct()
    {
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $index = count($this->queries);
        $this->queries[] = $query;

        return new EmployeeDirectoryRecordingPdoStatement($this, $index);
    }
}

final class EmployeeDirectoryRecordingPdoStatement extends PDOStatement
{
    public function __construct(
        private readonly EmployeeDirectoryRecordingPdo $pdo,
        private readonly int $index
    ) {
    }

    public function bindValue(string|int $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        $this->pdo->bindings[$this->index][(string) $param] = [
            'value' => $value,
            'type' => $type,
        ];

        return true;
    }

    public function execute(?array $params = null): bool
    {
        return true;
    }

    public function fetch(
        int $mode = PDO::FETCH_DEFAULT,
        int $cursorOrientation = PDO::FETCH_ORI_NEXT,
        int $cursorOffset = 0
    ): mixed {
        return $this->pdo->fetchResults[$this->index] ?? false;
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        return $this->pdo->fetchAllResults[$this->index] ?? [];
    }
}

function assertSameValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(sprintf(
            "%s\nExpected: %s\nActual: %s",
            $message,
            var_export($expected, true),
            var_export($actual, true)
        ));
    }
}

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** @return array<string, mixed> */
function responseJson(ResponseInterface $response): array
{
    $decoded = json_decode((string) $response->getBody(), true);

    if (!is_array($decoded)) {
        throw new RuntimeException('Response body is not a JSON object.');
    }

    return $decoded;
}

function test(string $name, Closure $test): void
{
    try {
        $test();
        echo "PASS {$name}\n";
    } catch (Throwable $exception) {
        fwrite(STDERR, "FAIL {$name}\n{$exception->getMessage()}\n");
        exit(1);
    }
}

/** @return array{private: string, public: string, private_path: string, public_path: string} */
function testRsaKeys(): array
{
    static $keys = null;

    if (is_array($keys)) {
        return $keys;
    }

    $key = openssl_pkey_new([
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ]);

    if ($key === false || !openssl_pkey_export($key, $privateKey)) {
        throw new RuntimeException('Unable to create test RSA private key.');
    }

    $details = openssl_pkey_get_details($key);

    if (!is_array($details) || !isset($details['key'])) {
        throw new RuntimeException('Unable to create test RSA public key.');
    }

    $directory = sys_get_temp_dir() . '/kronos-jwt-tests-' . bin2hex(random_bytes(8));
    mkdir($directory, 0700, true);
    $privatePath = $directory . '/private.pem';
    $publicPath = $directory . '/public.pem';
    file_put_contents($privatePath, $privateKey);
    file_put_contents($publicPath, $details['key']);
    register_shutdown_function(static function () use ($privatePath, $publicPath, $directory): void {
        @unlink($privatePath);
        @unlink($publicPath);
        @rmdir($directory);
    });

    $keys = [
        'private' => $privateKey,
        'public' => $details['key'],
        'private_path' => $privatePath,
        'public_path' => $publicPath,
    ];

    return $keys;
}

/** @param array<string, mixed> $input */
function assertInvalidInput(array $input, string $expectedMessage): void
{
    $service = new ApiClientService(new RecordingPdo());

    try {
        $service->create($input);
        throw new RuntimeException('Expected invalid input to be rejected.');
    } catch (InvalidArgumentException $exception) {
        assertSameValue($expectedMessage, $exception->getMessage(), 'Unexpected validation message.');
    }
}

test('client_name is required', function (): void {
    assertInvalidInput([], 'client_name is required.');
});

test('client_name must be a non-empty string after trimming', function (): void {
    assertInvalidInput(['client_name' => 123], 'client_name must be a string.');
    assertInvalidInput(['client_name' => " \t\n"], 'client_name is required.');
});

test('client_name cannot exceed 150 characters', function (): void {
    assertInvalidInput(['client_name' => str_repeat('ñ', 151)], 'client_name must not exceed 150 characters.');
});

test('expires_at must be a valid future MySQL DATETIME', function (): void {
    assertInvalidInput(
        ['client_name' => 'SiBS HRIS', 'expires_at' => 123],
        'expires_at must be a valid DATETIME in Y-m-d H:i:s format.'
    );
    assertInvalidInput(
        ['client_name' => 'SiBS HRIS', 'expires_at' => '2026-02-30 12:00:00'],
        'expires_at must be a valid DATETIME in Y-m-d H:i:s format.'
    );
    assertInvalidInput(
        ['client_name' => 'SiBS HRIS', 'expires_at' => '2000-01-01 00:00:00'],
        'expires_at must be in the future.'
    );
});

test('creates and stores secure credentials while returning the plaintext secret once', function (): void {
    $pdo = new RecordingPdo();
    $service = new ApiClientService($pdo);

    $client = $service->create([
        'client_name' => '  SiBS HRIS  ',
        'expires_at' => '2999-12-31 23:59:59',
    ]);

    assertSameValue(42, $client['id'], 'The inserted client ID was not returned.');
    assertSameValue('SiBS HRIS', $client['client_name'], 'The client name was not trimmed.');
    assertTrue(
        preg_match('/^kronos_live_[a-f0-9]{32}$/', $client['api_key']) === 1,
        'The API key format is invalid.'
    );
    assertTrue(
        preg_match('/^krs_[a-f0-9]{64}$/', $client['api_secret']) === 1,
        'The API secret format is invalid.'
    );
    assertSameValue('active', $client['status'], 'The status must be active.');
    assertSameValue('2999-12-31 23:59:59', $client['expires_at'], 'The expiration was not returned.');

    assertSameValue(
        'INSERT INTO api_clients (client_name, api_key, api_secret_hash, status, expires_at) '
        . 'VALUES (:client_name, :api_key, :api_secret_hash, :status, :expires_at)',
        $pdo->query,
        'The insert must use placeholders.'
    );
    assertSameValue('SiBS HRIS', $pdo->parameters['client_name'], 'The trimmed name was not stored.');
    assertSameValue($client['api_key'], $pdo->parameters['api_key'], 'The generated API key was not stored.');
    assertTrue(
        $pdo->parameters['api_secret_hash'] !== $client['api_secret'],
        'The plaintext API secret must never be stored.'
    );
    assertTrue(
        password_verify($client['api_secret'], $pdo->parameters['api_secret_hash']),
        'The stored hash does not verify with the generated API secret.'
    );
    assertSameValue(
        'argon2id',
        password_get_info($pdo->parameters['api_secret_hash'])['algoName'],
        'The API secret was not hashed with Argon2id.'
    );
    assertSameValue('active', $pdo->parameters['status'], 'The active status was not stored.');
    assertSameValue('2999-12-31 23:59:59', $pdo->parameters['expires_at'], 'The expiration was not stored.');
});

test('maps a duplicate API key database error to a collision', function (): void {
    $pdo = new RecordingPdo();
    $duplicate = new PDOException('Database details must stay private.');
    $duplicate->errorInfo = ['23000', 1062, 'Duplicate entry'];
    $pdo->executeException = $duplicate;

    try {
        (new ApiClientService($pdo))->create(['client_name' => 'SiBS HRIS']);
        throw new RuntimeException('Expected a duplicate API key collision.');
    } catch (ApiKeyCollisionException) {
        assertTrue(true, 'Duplicate error mapped to a collision.');
    }
});

test('admin setup key middleware rejects missing and invalid credentials', function (): void {
    $responseFactory = new ResponseFactory();
    $middleware = new AdminSetupKeyMiddleware('correct-secret', $responseFactory);
    $handler = new class ($responseFactory) implements RequestHandlerInterface {
        public function __construct(private readonly ResponseFactory $responseFactory)
        {
        }

        public function handle(ServerRequestInterface $request): ResponseInterface
        {
            return $this->responseFactory->createResponse(204);
        }
    };

    $missingRequest = (new ServerRequestFactory())->createServerRequest('POST', '/api/v1/api-clients');
    $invalidRequest = $missingRequest->withHeader('X-Admin-Setup-Key', 'wrong-secret');

    assertSameValue(401, $middleware->process($missingRequest, $handler)->getStatusCode(), 'Missing key was accepted.');
    assertSameValue(401, $middleware->process($invalidRequest, $handler)->getStatusCode(), 'Invalid key was accepted.');
});

test('admin setup key middleware permits the configured credential', function (): void {
    $responseFactory = new ResponseFactory();
    $middleware = new AdminSetupKeyMiddleware('correct-secret', $responseFactory);
    $handler = new class ($responseFactory) implements RequestHandlerInterface {
        public function __construct(private readonly ResponseFactory $responseFactory)
        {
        }

        public function handle(ServerRequestInterface $request): ResponseInterface
        {
            return $this->responseFactory->createResponse(204);
        }
    };
    $request = (new ServerRequestFactory())
        ->createServerRequest('POST', '/api/v1/api-clients')
        ->withHeader('X-Admin-Setup-Key', 'correct-secret');

    assertSameValue(204, $middleware->process($request, $handler)->getStatusCode(), 'Valid key was rejected.');
});

test('controller returns the one-time credential response with HTTP 201', function (): void {
    $pdo = new RecordingPdo();
    $controller = new ApiClientController(
        static fn (): ApiClientService => new ApiClientService($pdo)
    );
    $request = (new ServerRequestFactory())
        ->createServerRequest('POST', '/api/v1/api-clients')
        ->withParsedBody(['client_name' => ' SiBS HRIS ', 'expires_at' => null]);
    $response = (new ResponseFactory())->createResponse();

    $result = $controller->create($request, $response);
    $payload = responseJson($result);

    assertSameValue(201, $result->getStatusCode(), 'Successful creation did not return HTTP 201.');
    assertSameValue(true, $payload['success'], 'Success flag is incorrect.');
    assertSameValue('API client created successfully.', $payload['message'], 'Success message is incorrect.');
    assertSameValue('SiBS HRIS', $payload['client']['client_name'], 'Client data is incorrect.');
    assertTrue(isset($payload['client']['api_secret']), 'The one-time API secret is missing.');
    assertTrue(!isset($payload['client']['api_secret_hash']), 'The API secret hash was exposed.');
    assertSameValue(null, $payload['client']['expires_at'], 'Null expiration was not preserved.');
    assertSameValue(
        'Save the API secret now. It cannot be retrieved again.',
        $payload['warning'],
        'The one-time secret warning is missing.'
    );
});

test('controller returns HTTP 400 for invalid JSON input', function (): void {
    $controller = new ApiClientController(
        static fn (): ApiClientService => new ApiClientService(new RecordingPdo())
    );
    $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/v1/api-clients');

    $result = $controller->create($request, (new ResponseFactory())->createResponse());

    assertSameValue(400, $result->getStatusCode(), 'Invalid input did not return HTTP 400.');
    assertSameValue(false, responseJson($result)['success'], 'Invalid input reported success.');
});

test('controller returns sanitized HTTP 409 for an API key collision', function (): void {
    $pdo = new RecordingPdo();
    $duplicate = new PDOException('Duplicate entry from secret SQL.');
    $duplicate->errorInfo = ['23000', 1062, 'Duplicate entry from secret SQL'];
    $pdo->executeException = $duplicate;
    $controller = new ApiClientController(
        static fn (): ApiClientService => new ApiClientService($pdo)
    );
    $request = (new ServerRequestFactory())
        ->createServerRequest('POST', '/api/v1/api-clients')
        ->withParsedBody(['client_name' => 'SiBS HRIS']);

    $result = $controller->create($request, (new ResponseFactory())->createResponse());
    $body = (string) $result->getBody();

    assertSameValue(409, $result->getStatusCode(), 'Collision did not return HTTP 409.');
    assertTrue(!str_contains($body, 'secret SQL'), 'Database details leaked in the collision response.');
});

test('controller returns sanitized HTTP 500 for an unexpected database failure', function (): void {
    $pdo = new RecordingPdo();
    $pdo->executeException = new PDOException('SELECT private_data FROM secret_table');
    $controller = new ApiClientController(
        static fn (): ApiClientService => new ApiClientService($pdo)
    );
    $request = (new ServerRequestFactory())
        ->createServerRequest('POST', '/api/v1/api-clients')
        ->withParsedBody(['client_name' => 'SiBS HRIS']);

    $result = $controller->create($request, (new ResponseFactory())->createResponse());
    $body = (string) $result->getBody();

    assertSameValue(500, $result->getStatusCode(), 'Unexpected failure did not return HTTP 500.');
    assertTrue(!str_contains($body, 'private_data'), 'Database details leaked in the server error response.');
    assertSameValue('Server error.', responseJson($result)['message'], 'Generic error message is incorrect.');
});

test('POST API client route is protected and creates a client when authorized', function (): void {
    $_ENV['API_ADMIN_SETUP_KEY'] = 'route-secret';
    $factoryCalls = 0;
    $serviceFactory = static function () use (&$factoryCalls): ApiClientService {
        $factoryCalls++;

        return new ApiClientService(new RecordingPdo());
    };

    $app = AppFactory::create();
    $routes = require __DIR__ . '/../routes/api.php';
    $routes($app, $serviceFactory);
    $app->addRoutingMiddleware();

    $requestFactory = new ServerRequestFactory();
    $unauthorized = $requestFactory
        ->createServerRequest('POST', '/api/v1/api-clients')
        ->withParsedBody(['client_name' => 'SiBS HRIS']);

    $unauthorizedResponse = $app->handle($unauthorized);
    assertSameValue(401, $unauthorizedResponse->getStatusCode(), 'The route was not protected.');
    assertSameValue(0, $factoryCalls, 'Database service was created before authorization.');

    $authorized = $unauthorized->withHeader('X-Admin-Setup-Key', 'route-secret');
    $authorizedResponse = $app->handle($authorized);

    assertSameValue(201, $authorizedResponse->getStatusCode(), 'Authorized route did not create a client.');
    assertSameValue(1, $factoryCalls, 'Authorized route did not create the database service once.');
});

test('database connection rejects missing configuration before connecting', function (): void {
    try {
        Database::connect([]);
        throw new RuntimeException('Expected missing database configuration to be rejected.');
    } catch (RuntimeException $exception) {
        assertSameValue('Missing database configuration.', $exception->getMessage(), 'Unexpected configuration error.');
    }
});

test('bootstrap never exposes unexpected exception details', function (): void {
    $app = require __DIR__ . '/../src/bootstrap.php';
    $app->get('/__test/server-error', static function (): never {
        throw new RuntimeException('SELECT secret_column FROM private_table at /private/path.php:42');
    });
    $request = (new ServerRequestFactory())->createServerRequest('GET', '/__test/server-error');

    $response = $app->handle($request);
    $body = (string) $response->getBody();

    assertSameValue(500, $response->getStatusCode(), 'Unexpected exception did not return HTTP 500.');
    assertTrue(!str_contains($body, 'secret_column'), 'Exception or SQL details were exposed.');
    assertTrue(!str_contains($body, '/private/path.php'), 'Stack details were exposed.');
    assertSameValue('Server error.', responseJson($response)['message'], 'Server error was not generic JSON.');
});

test('bootstrap returns sanitized JSON for an unknown route', function (): void {
    $app = require __DIR__ . '/../src/bootstrap.php';
    $request = (new ServerRequestFactory())->createServerRequest('GET', '/__test/missing-route');

    $response = $app->handle($request);

    assertSameValue(404, $response->getStatusCode(), 'Unknown route did not return HTTP 404.');
    assertSameValue(
        ['success' => false, 'message' => 'Not Found.'],
        responseJson($response),
        'Unknown route did not return sanitized JSON.'
    );
});

test('configuration reads process-level environment variables when $_ENV is unavailable', function (): void {
    $name = 'KRONOS_TEST_PROCESS_ENV';
    $previous = getenv($name);
    unset($_ENV[$name]);
    putenv("{$name}=process-secret");

    try {
        assertSameValue('process-secret', Environment::get($name), 'Process-level environment value was ignored.');
    } finally {
        if ($previous === false) {
            putenv($name);
        } else {
            putenv("{$name}={$previous}");
        }
    }
});

test('API token credentials authenticate only an active unexpired client with the correct secret', function (): void {
    $secret = 'krs_test-secret';
    $pdo = new RecordingPdo();
    $pdo->fetchResult = [
        'id' => 7,
        'client_name' => 'SiBS HRIS',
        'api_secret_hash' => password_hash($secret, PASSWORD_ARGON2ID),
        'status' => 'active',
        'expires_at' => '2999-12-31 23:59:59',
    ];
    $service = new ApiTokenService($pdo);

    $client = $service->authenticate([
        'api_key' => 'kronos_live_test',
        'api_secret' => $secret,
    ]);

    assertSameValue(['id' => 7, 'client_name' => 'SiBS HRIS'], $client, 'Authenticated client is incorrect.');
    assertSameValue(
        'SELECT id, client_name, api_secret_hash, status, expires_at '
        . 'FROM api_clients WHERE api_key = :api_key LIMIT 1',
        $pdo->query,
        'Credential lookup must use the expected prepared query.'
    );
    assertSameValue(['api_key' => 'kronos_live_test'], $pdo->parameters, 'API key was not bound safely.');
});

test('all API credential failures are indistinguishable', function (): void {
    $validHash = password_hash('correct-secret', PASSWORD_ARGON2ID);
    $cases = [
        [[], false],
        [['api_key' => 'key', 'api_secret' => 'secret'], false],
        [['api_key' => 'key', 'api_secret' => 'secret'], [
            'id' => 1,
            'client_name' => 'Disabled',
            'api_secret_hash' => $validHash,
            'status' => 'disabled',
            'expires_at' => null,
        ]],
        [['api_key' => 'key', 'api_secret' => 'secret'], [
            'id' => 1,
            'client_name' => 'Expired',
            'api_secret_hash' => $validHash,
            'status' => 'active',
            'expires_at' => '2000-01-01 00:00:00',
        ]],
        [['api_key' => 'key', 'api_secret' => 'wrong-secret'], [
            'id' => 1,
            'client_name' => 'Wrong secret',
            'api_secret_hash' => $validHash,
            'status' => 'active',
            'expires_at' => null,
        ]],
    ];

    foreach ($cases as [$input, $row]) {
        $pdo = new RecordingPdo();
        $pdo->fetchResult = $row;

        try {
            (new ApiTokenService($pdo))->authenticate($input);
            throw new RuntimeException('Expected credentials to be rejected.');
        } catch (InvalidApiCredentialsException $exception) {
            assertSameValue('Invalid API credentials.', $exception->getMessage(), 'Credential failure leaked details.');
        }
    }
});

test('structurally valid credential attempts always perform one secret verification', function (): void {
    $rows = [
        false,
        [
            'id' => 1,
            'client_name' => 'Disabled',
            'api_secret_hash' => password_hash('secret', PASSWORD_ARGON2ID),
            'status' => 'disabled',
            'expires_at' => null,
        ],
        [
            'id' => 1,
            'client_name' => 'Expired',
            'api_secret_hash' => password_hash('secret', PASSWORD_ARGON2ID),
            'status' => 'active',
            'expires_at' => '2000-01-01 00:00:00',
        ],
    ];

    foreach ($rows as $row) {
        $pdo = new RecordingPdo();
        $pdo->fetchResult = $row;
        $verificationCalls = 0;
        $verifier = static function (string $secret, string $hash) use (&$verificationCalls): bool {
            $verificationCalls++;
            assertSameValue('provided-secret', $secret, 'Secret verifier received the wrong credential.');
            assertTrue(str_starts_with($hash, '$argon2id$'), 'Secret verifier did not receive an Argon2id hash.');

            return false;
        };

        try {
            (new ApiTokenService($pdo, $verifier))->authenticate([
                'api_key' => 'candidate-key',
                'api_secret' => 'provided-secret',
            ]);
        } catch (InvalidApiCredentialsException) {
        }

        assertSameValue(1, $verificationCalls, 'Credential failure exposed a password-verification timing oracle.');
    }
});

test('JWT service issues and validates an RS256 access token with required claims', function (): void {
    $keys = testRsaKeys();
    $now = 1_800_000_000;
    $service = new JwtService(
        'kronos-api',
        'kronos-api-clients',
        3600,
        $keys['private_path'],
        $keys['public_path'],
        static fn (): int => $now
    );

    $token = $service->issue(['id' => 7, 'client_name' => 'SiBS HRIS']);
    $claims = $service->validate($token);

    assertSameValue('kronos-api', $claims->iss, 'Issuer claim is incorrect.');
    assertSameValue('kronos-api-clients', $claims->aud, 'Audience claim is incorrect.');
    assertSameValue('7', $claims->sub, 'Subject claim must be the string client ID.');
    assertSameValue('SiBS HRIS', $claims->client_name, 'Client name claim is incorrect.');
    assertSameValue($now, $claims->iat, 'Issued-at claim is incorrect.');
    assertSameValue($now, $claims->nbf, 'Not-before claim is incorrect.');
    assertSameValue($now + 3600, $claims->exp, 'Token lifetime is not one hour.');
    assertTrue(preg_match('/^[a-f0-9]{32}$/', $claims->jti) === 1, 'JWT ID is not securely formatted.');
    assertSameValue('access', $claims->type, 'Token type is incorrect.');
});

test('JWT service rejects incorrect or incomplete access-token claims', function (): void {
    $keys = testRsaKeys();
    $now = 1_800_000_000;
    $service = new JwtService(
        'kronos-api',
        'kronos-api-clients',
        3600,
        $keys['private_path'],
        $keys['public_path'],
        static fn (): int => $now
    );
    $baseClaims = [
        'iss' => 'kronos-api',
        'aud' => 'kronos-api-clients',
        'sub' => '7',
        'client_name' => 'SiBS HRIS',
        'iat' => $now,
        'nbf' => $now,
        'exp' => $now + 3600,
        'jti' => str_repeat('a', 32),
        'type' => 'access',
    ];

    $invalidClaims = [
        array_replace($baseClaims, ['iss' => 'wrong-issuer']),
        array_replace($baseClaims, ['aud' => 'wrong-audience']),
        array_replace($baseClaims, ['type' => 'refresh']),
        array_diff_key($baseClaims, ['exp' => true]),
        array_diff_key($baseClaims, ['nbf' => true]),
        array_diff_key($baseClaims, ['iat' => true]),
        array_diff_key($baseClaims, ['sub' => true]),
        array_replace($baseClaims, ['exp' => $now + 7200]),
    ];

    foreach ($invalidClaims as $claims) {
        $token = Firebase\JWT\JWT::encode($claims, $keys['private'], 'RS256');

        try {
            $service->validate($token);
            throw new RuntimeException('Expected invalid claims to be rejected.');
        } catch (InvalidTokenException) {
            assertTrue(true, 'Invalid JWT claims were rejected.');
        }
    }
});

test('JWT service enforces the required one-hour access lifetime', function (): void {
    $keys = testRsaKeys();

    foreach ([3599, 7200] as $invalidTtl) {
        try {
            new JwtService(
                'kronos-api',
                'kronos-api-clients',
                $invalidTtl,
                $keys['private_path'],
                $keys['public_path']
            );
            throw new RuntimeException('Expected a non-one-hour JWT lifetime to be rejected.');
        } catch (RuntimeException $exception) {
            assertSameValue('Invalid JWT configuration.', $exception->getMessage(), 'Unexpected TTL error.');
        }
    }
});

test('auth controller returns a one-hour Bearer token for valid credentials', function (): void {
    $keys = testRsaKeys();
    $pdo = new RecordingPdo();
    $pdo->fetchResult = [
        'id' => 7,
        'client_name' => 'SiBS HRIS',
        'api_secret_hash' => password_hash('correct-secret', PASSWORD_ARGON2ID),
        'status' => 'active',
        'expires_at' => null,
    ];
    $jwtService = new JwtService(
        'kronos-api',
        'kronos-api-clients',
        3600,
        $keys['private_path'],
        $keys['public_path']
    );
    $controller = new AuthController(
        static fn (): ApiTokenService => new ApiTokenService($pdo),
        static fn (): JwtService => $jwtService,
        3600
    );
    $request = (new ServerRequestFactory())
        ->createServerRequest('POST', '/api/v1/auth/token')
        ->withParsedBody([
            'api_key' => 'kronos_live_test',
            'api_secret' => 'correct-secret',
        ]);

    $response = $controller->issue($request, (new ResponseFactory())->createResponse());
    $payload = responseJson($response);

    assertSameValue(200, $response->getStatusCode(), 'Valid credentials did not return HTTP 200.');
    assertSameValue(true, $payload['success'], 'Token response success flag is incorrect.');
    assertSameValue('Bearer', $payload['token_type'], 'Token type response is incorrect.');
    assertSameValue(3600, $payload['expires_in'], 'Token response lifetime is incorrect.');
    assertSameValue('7', $jwtService->validate($payload['access_token'])->sub, 'Returned JWT is invalid.');
    assertTrue(!str_contains((string) $response->getBody(), 'api_secret_hash'), 'Secret hash leaked in token response.');
});

test('auth controller returns only the generic HTTP 401 credential failure', function (): void {
    $keys = testRsaKeys();
    $pdo = new RecordingPdo();
    $pdo->fetchResult = false;
    $controller = new AuthController(
        static fn (): ApiTokenService => new ApiTokenService($pdo),
        static fn (): JwtService => new JwtService(
            'kronos-api',
            'kronos-api-clients',
            3600,
            $keys['private_path'],
            $keys['public_path']
        ),
        3600
    );
    $request = (new ServerRequestFactory())
        ->createServerRequest('POST', '/api/v1/auth/token')
        ->withParsedBody(['api_key' => 'unknown', 'api_secret' => 'secret']);

    $response = $controller->issue($request, (new ResponseFactory())->createResponse());

    assertSameValue(401, $response->getStatusCode(), 'Invalid credentials did not return HTTP 401.');
    assertSameValue(
        ['success' => false, 'message' => 'Invalid API credentials.'],
        responseJson($response),
        'Credential failure response exposed additional detail.'
    );
});

test('JWT middleware attaches validated claims to the api_client request attribute', function (): void {
    $keys = testRsaKeys();
    $jwtService = new JwtService(
        'kronos-api',
        'kronos-api-clients',
        3600,
        $keys['private_path'],
        $keys['public_path']
    );
    $token = $jwtService->issue(['id' => 7, 'client_name' => 'SiBS HRIS']);
    $middleware = new JwtAuthMiddleware($jwtService, new ResponseFactory());
    $handler = new class implements RequestHandlerInterface {
        public function handle(ServerRequestInterface $request): ResponseInterface
        {
            $claims = $request->getAttribute('api_client');
            $response = (new ResponseFactory())->createResponse(200);
            $response->getBody()->write((string) json_encode([
                'sub' => $claims->sub ?? null,
                'client_name' => $claims->client_name ?? null,
            ]));

            return $response;
        }
    };
    $request = (new ServerRequestFactory())
        ->createServerRequest('GET', '/api/v1/protected-test')
        ->withHeader('Authorization', 'Bearer ' . $token);

    $response = $middleware->process($request, $handler);

    assertSameValue(200, $response->getStatusCode(), 'Valid JWT was rejected.');
    assertSameValue(
        ['sub' => '7', 'client_name' => 'SiBS HRIS'],
        responseJson($response),
        'Decoded claims were not attached to api_client.'
    );
});

test('JWT middleware returns the same HTTP 401 for missing malformed tampered and expired tokens', function (): void {
    $keys = testRsaKeys();
    $now = 1_800_000_000;
    $issuer = new JwtService(
        'kronos-api',
        'kronos-api-clients',
        3600,
        $keys['private_path'],
        $keys['public_path'],
        static fn (): int => $now
    );
    $validToken = $issuer->issue(['id' => 7, 'client_name' => 'SiBS HRIS']);
    $expiredValidator = new JwtService(
        'kronos-api',
        'kronos-api-clients',
        3600,
        $keys['private_path'],
        $keys['public_path'],
        static fn (): int => $now + 3601
    );
    $responseFactory = new ResponseFactory();
    $tamperedParts = explode('.', $validToken);
    $tamperedParts[2][0] = $tamperedParts[2][0] === 'a' ? 'b' : 'a';
    $tamperedToken = implode('.', $tamperedParts);
    $handler = new class ($responseFactory) implements RequestHandlerInterface {
        public function __construct(private readonly ResponseFactory $responseFactory)
        {
        }

        public function handle(ServerRequestInterface $request): ResponseInterface
        {
            return $this->responseFactory->createResponse(204);
        }
    };
    $requests = [
        [(new ServerRequestFactory())->createServerRequest('GET', '/api/v1/protected-test'), $issuer],
        [(new ServerRequestFactory())->createServerRequest('GET', '/api/v1/protected-test')
            ->withHeader('Authorization', 'Basic abc'), $issuer],
        [(new ServerRequestFactory())->createServerRequest('GET', '/api/v1/protected-test')
            ->withHeader('Authorization', 'Bearer ' . $tamperedToken), $issuer],
        [(new ServerRequestFactory())->createServerRequest('GET', '/api/v1/protected-test')
            ->withHeader('Authorization', 'Bearer ' . $validToken), $expiredValidator],
    ];

    foreach ($requests as [$request, $validator]) {
        $response = (new JwtAuthMiddleware($validator, $responseFactory))->process($request, $handler);

        assertSameValue(401, $response->getStatusCode(), 'Invalid JWT did not return HTTP 401.');
        assertSameValue(
            ['success' => false, 'message' => 'Unauthorized.'],
            responseJson($response),
            'JWT failure response exposed additional detail.'
        );
        assertSameValue(
            'Bearer',
            $response->getHeaderLine('WWW-Authenticate'),
            'JWT 401 response omitted the Bearer authentication challenge.'
        );
    }
});

test('auth token route is public and protected-test requires its Bearer token', function (): void {
    $keys = testRsaKeys();
    $pdo = new RecordingPdo();
    $pdo->fetchResult = [
        'id' => 7,
        'client_name' => 'SiBS HRIS',
        'api_secret_hash' => password_hash('correct-secret', PASSWORD_ARGON2ID),
        'status' => 'active',
        'expires_at' => null,
    ];
    $jwtService = new JwtService(
        'kronos-api',
        'kronos-api-clients',
        3600,
        $keys['private_path'],
        $keys['public_path']
    );
    $app = AppFactory::create();
    $routes = require __DIR__ . '/../routes/api.php';
    $routes(
        $app,
        static fn (): ApiClientService => new ApiClientService(new RecordingPdo()),
        static fn (): ApiTokenService => new ApiTokenService($pdo),
        static fn (): JwtService => $jwtService
    );
    $app->addRoutingMiddleware();
    $requestFactory = new ServerRequestFactory();
    $tokenRequest = $requestFactory
        ->createServerRequest('POST', '/api/v1/auth/token')
        ->withParsedBody([
            'api_key' => 'kronos_live_test',
            'api_secret' => 'correct-secret',
        ]);

    $tokenResponse = $app->handle($tokenRequest);
    $tokenPayload = responseJson($tokenResponse);
    $databaseCallsAfterToken = $pdo->prepareCalls;

    assertSameValue(200, $tokenResponse->getStatusCode(), 'Public token route failed.');

    $missingTokenResponse = $app->handle(
        $requestFactory->createServerRequest('GET', '/api/v1/protected-test')
    );
    assertSameValue(401, $missingTokenResponse->getStatusCode(), 'Protected route accepted a missing token.');

    $protectedResponse = $app->handle(
        $requestFactory
            ->createServerRequest('GET', '/api/v1/protected-test')
            ->withHeader('Authorization', 'Bearer ' . $tokenPayload['access_token'])
    );

    assertSameValue(200, $protectedResponse->getStatusCode(), 'Protected route rejected a valid token.');
    assertSameValue(
        ['success' => true, 'message' => 'JWT is valid.'],
        responseJson($protectedResponse),
        'Protected route response is incorrect.'
    );
    assertSameValue(
        $databaseCallsAfterToken,
        $pdo->prepareCalls,
        'Protected JWT validation queried api_clients.'
    );
});

test('employee repository uses prepared cursor pagination and fetches 101 selected rows', function (): void {
    $pdo = new RecordingPdo();
    $pdo->fetchAllResult = [
        ['gy_emp_id' => 41, 'gy_emp_code' => 'EMP-41'],
    ];
    $repository = new EmployeeRepository($pdo);

    $rows = $repository->findAfter(37);

    $expectedSql = 'SELECT gy_emp_id, gy_emp_code, gy_emp_type, gy_emp_schedtype, gy_emp_rate, '
        . 'gy_emp_email, gy_emp_lname, gy_emp_fname, gy_emp_mname, gy_emp_fullname, gy_acc_id, '
        . 'gy_emp_account, gy_emp_supervisor, gy_emp_om, gy_emp_leave_credits, gy_emp_hiredate, '
        . 'gy_emp_lastedit, gy_lastedit_by, gy_work_from, gy_gender, gy_dob, gy_civilstatus, '
        . 'gy_assignedloc, gy_tagumdate, gy_davaodate, gy_hybriddate, gy_accjoin, gy_nhodate, '
        . 'gy_fststartdate, gy_fstenddate, gy_pststartdate, gy_pstenddate, gy_certification, '
        . 'gy_gradbaystartdate, gy_gradbayenddate, gy_fullgolivedate, gy_promotiondate, '
        . 'gy_projempdate, gy_probempdate, gy_regempdate, gy_last_working_day '
        . 'FROM gy_employee WHERE gy_emp_id > :after_id ORDER BY gy_emp_id ASC LIMIT 101';

    assertSameValue($expectedSql, $pdo->query, 'Employee cursor query is incorrect.');
    assertSameValue(
        ['value' => 37, 'type' => PDO::PARAM_INT],
        $pdo->boundValues[':after_id'] ?? null,
        'after_id was not bound as an integer.'
    );
    assertSameValue($pdo->fetchAllResult, $rows, 'Repository did not return fetched employee rows.');
});

test('employee controller returns 100 rows and uses the actual last employee ID as next cursor', function (): void {
    $pdo = new RecordingPdo();

    for ($index = 0; $index < 101; $index++) {
        $pdo->fetchAllResult[] = [
            'gy_emp_id' => 1000 + ($index * 3),
            'gy_emp_code' => 'EMP-' . $index,
        ];
    }

    $controller = new EmployeeController(
        static fn (): EmployeeRepository => new EmployeeRepository($pdo)
    );
    $request = (new ServerRequestFactory())
        ->createServerRequest('GET', '/api/v1/employees')
        ->withQueryParams(['after_id' => '12']);

    $response = $controller->index($request, (new ResponseFactory())->createResponse());
    $payload = responseJson($response);

    assertSameValue(200, $response->getStatusCode(), 'Employee endpoint did not return HTTP 200.');
    assertSameValue(
        'private, no-store',
        $response->getHeaderLine('Cache-Control'),
        'Employee response can be stored by private caches.'
    );
    assertSameValue(100, count($payload['data']), 'Employee endpoint did not cap data at 100 rows.');
    assertSameValue(1297, $payload['data'][99]['gy_emp_id'], 'Wrong final employee was returned.');
    assertSameValue([
        'limit' => 100,
        'count' => 100,
        'current_cursor' => 12,
        'next_cursor' => 1297,
        'has_more' => true,
    ], $payload['pagination'], 'Employee pagination metadata is incorrect.');
});

test('employee controller normalizes invalid cursors and handles short and empty pages', function (): void {
    $pdo = new RecordingPdo();
    $controller = new EmployeeController(
        static fn (): EmployeeRepository => new EmployeeRepository($pdo)
    );
    $responseFactory = new ResponseFactory();
    $requestFactory = new ServerRequestFactory();

    $pdo->fetchAllResult = [
        ['gy_emp_id' => 5],
        ['gy_emp_id' => 17],
    ];
    $negativeResponse = $controller->index(
        $requestFactory
            ->createServerRequest('GET', '/api/v1/employees')
            ->withQueryParams(['after_id' => '-20']),
        $responseFactory->createResponse()
    );
    $negativePayload = responseJson($negativeResponse);

    assertSameValue(0, $negativePayload['pagination']['current_cursor'], 'Negative cursor was not normalized.');
    assertSameValue(17, $negativePayload['pagination']['next_cursor'], 'Short-page cursor is incorrect.');
    assertSameValue(false, $negativePayload['pagination']['has_more'], 'Short page reported more rows.');
    assertSameValue(['value' => 0, 'type' => PDO::PARAM_INT], $pdo->boundValues[':after_id'], 'Normalized cursor was not queried.');

    $pdo->fetchAllResult = [];
    $emptyResponse = $controller->index(
        $requestFactory
            ->createServerRequest('GET', '/api/v1/employees')
            ->withQueryParams(['after_id' => 'invalid']),
        $responseFactory->createResponse()
    );
    $emptyPayload = responseJson($emptyResponse);

    assertSameValue([], $emptyPayload['data'], 'Empty page data is incorrect.');
    assertSameValue(null, $emptyPayload['pagination']['next_cursor'], 'Empty page cursor must be null.');
    assertSameValue(false, $emptyPayload['pagination']['has_more'], 'Empty page reported more rows.');
});

test('employee controller logs the exception and returns only a generic HTTP 500', function (): void {
    $pdo = new RecordingPdo();
    $failure = new PDOException('SELECT private_employee_data failed');
    $pdo->executeException = $failure;
    $loggedException = null;
    $controller = new EmployeeController(
        static fn (): EmployeeRepository => new EmployeeRepository($pdo),
        static function (Throwable $exception) use (&$loggedException): void {
            $loggedException = $exception;
        }
    );
    $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/employees');

    $response = $controller->index($request, (new ResponseFactory())->createResponse());

    assertSameValue(500, $response->getStatusCode(), 'Employee failure did not return HTTP 500.');
    assertSameValue(
        ['success' => false, 'message' => 'Server error.'],
        responseJson($response),
        'Employee failure response exposed details.'
    );
    assertSameValue($failure, $loggedException, 'Actual employee exception was not logged server-side.');
    assertTrue(!str_contains((string) $response->getBody(), 'private_employee_data'), 'Database error leaked to response.');
});

test('employee controller logs JSON serialization failures and returns a generic HTTP 500', function (): void {
    $pdo = new RecordingPdo();
    $pdo->fetchAllResult = [['gy_emp_id' => 1, 'gy_emp_code' => "\xB1\x31"]];
    $loggedException = null;
    $controller = new EmployeeController(
        static fn (): EmployeeRepository => new EmployeeRepository($pdo),
        static function (Throwable $exception) use (&$loggedException): void {
            $loggedException = $exception;
        }
    );

    $response = $controller->index(
        (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/employees'),
        (new ResponseFactory())->createResponse()
    );

    assertSameValue(500, $response->getStatusCode(), 'Invalid database text did not return HTTP 500.');
    assertTrue($loggedException instanceof JsonException, 'Serialization failure was not logged.');
    assertSameValue(
        ['success' => false, 'message' => 'Server error.'],
        responseJson($response),
        'Serialization failure response exposed details.'
    );
});

test('employee route requires JWT and does not create the repository before authorization', function (): void {
    $keys = testRsaKeys();
    $jwtService = new JwtService(
        'kronos-api',
        'kronos-api-clients',
        3600,
        $keys['private_path'],
        $keys['public_path']
    );
    $token = $jwtService->issue(['id' => 7, 'client_name' => 'SiBS HRIS']);
    $pdo = new RecordingPdo();
    $pdo->fetchAllResult = [['gy_emp_id' => 137, 'gy_emp_code' => 'EMP-137']];
    $repositoryFactoryCalls = 0;
    $employeeRepositoryFactory = static function () use ($pdo, &$repositoryFactoryCalls): EmployeeRepository {
        $repositoryFactoryCalls++;

        return new EmployeeRepository($pdo);
    };
    $app = AppFactory::create();
    $routes = require __DIR__ . '/../routes/api.php';
    $routes(
        $app,
        static fn (): ApiClientService => new ApiClientService(new RecordingPdo()),
        static fn (): ApiTokenService => new ApiTokenService(new RecordingPdo()),
        static fn (): JwtService => $jwtService,
        $employeeRepositoryFactory
    );
    $app->addRoutingMiddleware();
    $requestFactory = new ServerRequestFactory();
    $request = $requestFactory->createServerRequest('GET', '/api/v1/employees?after_id=100');

    $unauthorizedResponse = $app->handle($request);

    assertSameValue(401, $unauthorizedResponse->getStatusCode(), 'Employee route was not JWT protected.');
    assertSameValue(0, $repositoryFactoryCalls, 'Employee repository was created before JWT authorization.');

    $authorizedResponse = $app->handle($request->withHeader('Authorization', 'Bearer ' . $token));
    $payload = responseJson($authorizedResponse);

    assertSameValue(200, $authorizedResponse->getStatusCode(), 'Authorized employee route failed.');
    assertSameValue(1, $repositoryFactoryCalls, 'Employee repository was not created exactly once.');
    assertSameValue(137, $payload['pagination']['next_cursor'], 'Employee route returned the wrong cursor.');
});

test('user repository uses an explicit password-free prepared cursor query', function (): void {
    $pdo = new RecordingPdo();
    $pdo->fetchAllResult = [
        ['gy_user_id' => 41, 'gy_user_code' => 'USR-41'],
    ];
    $repository = new UserRepository($pdo);

    $rows = $repository->findAfter(37);

    $expectedSql = 'SELECT gy_user_id, gy_user_code, ghl_contact_id, gy_full_name, gy_username, '
        . 'gy_user_type, gy_user_function, gy_head_code, gy_script_code, gy_user_status '
        . 'FROM gy_user WHERE gy_user_id > :after_id ORDER BY gy_user_id ASC LIMIT 101';

    assertSameValue($expectedSql, $pdo->query, 'User cursor query is incorrect.');
    assertTrue(!str_contains((string) $pdo->query, 'gy_password'), 'User cursor query selected the password.');
    assertSameValue(
        ['value' => 37, 'type' => PDO::PARAM_INT],
        $pdo->boundValues[':after_id'] ?? null,
        'after_id was not bound as an integer.'
    );
    assertSameValue($pdo->fetchAllResult, $rows, 'Repository did not return fetched user rows.');
});

test('user controller returns 100 rows and uses the actual last user ID as next cursor', function (): void {
    $pdo = new RecordingPdo();

    for ($index = 0; $index < 101; $index++) {
        $pdo->fetchAllResult[] = [
            'gy_user_id' => 1000 + ($index * 3),
            'gy_user_code' => 'USR-' . $index,
        ];
    }

    $controller = new UserController(
        static fn (): UserRepository => new UserRepository($pdo)
    );
    $request = (new ServerRequestFactory())
        ->createServerRequest('GET', '/api/v1/users')
        ->withQueryParams(['after_id' => '12']);

    $response = $controller->index($request, (new ResponseFactory())->createResponse());
    $payload = responseJson($response);

    assertSameValue(200, $response->getStatusCode(), 'User endpoint did not return HTTP 200.');
    assertSameValue(
        'private, no-store',
        $response->getHeaderLine('Cache-Control'),
        'User response can be stored by private caches.'
    );
    assertSameValue(100, count($payload['data']), 'User endpoint did not cap data at 100 rows.');
    assertSameValue(1297, $payload['data'][99]['gy_user_id'], 'Wrong final user was returned.');
    assertSameValue([
        'limit' => 100,
        'count' => 100,
        'current_cursor' => 12,
        'next_cursor' => 1297,
        'has_more' => true,
    ], $payload['pagination'], 'User pagination metadata is incorrect.');
});

test('user controller normalizes invalid cursors and handles short and empty pages', function (): void {
    $pdo = new RecordingPdo();
    $controller = new UserController(
        static fn (): UserRepository => new UserRepository($pdo)
    );
    $responseFactory = new ResponseFactory();
    $requestFactory = new ServerRequestFactory();

    $pdo->fetchAllResult = [
        ['gy_user_id' => 5],
        ['gy_user_id' => 17],
    ];
    $negativeResponse = $controller->index(
        $requestFactory
            ->createServerRequest('GET', '/api/v1/users')
            ->withQueryParams(['after_id' => '-20']),
        $responseFactory->createResponse()
    );
    $negativePayload = responseJson($negativeResponse);

    assertSameValue(0, $negativePayload['pagination']['current_cursor'], 'Negative cursor was not normalized.');
    assertSameValue(17, $negativePayload['pagination']['next_cursor'], 'Short-page cursor is incorrect.');
    assertSameValue(false, $negativePayload['pagination']['has_more'], 'Short page reported more rows.');
    assertSameValue(['value' => 0, 'type' => PDO::PARAM_INT], $pdo->boundValues[':after_id'], 'Normalized cursor was not queried.');

    $pdo->fetchAllResult = [];
    $emptyResponse = $controller->index(
        $requestFactory
            ->createServerRequest('GET', '/api/v1/users')
            ->withQueryParams(['after_id' => 'invalid']),
        $responseFactory->createResponse()
    );
    $emptyPayload = responseJson($emptyResponse);

    assertSameValue([], $emptyPayload['data'], 'Empty page data is incorrect.');
    assertSameValue(0, $emptyPayload['pagination']['count'], 'Empty page count is incorrect.');
    assertSameValue(0, $emptyPayload['pagination']['current_cursor'], 'Invalid cursor was not normalized.');
    assertSameValue(null, $emptyPayload['pagination']['next_cursor'], 'Empty page cursor must be null.');
    assertSameValue(false, $emptyPayload['pagination']['has_more'], 'Empty page reported more rows.');
});

test('user controller logs the exception and returns only a generic HTTP 500', function (): void {
    $pdo = new RecordingPdo();
    $failure = new PDOException('SELECT gy_password failed');
    $pdo->executeException = $failure;
    $loggedException = null;
    $controller = new UserController(
        static fn (): UserRepository => new UserRepository($pdo),
        static function (Throwable $exception) use (&$loggedException): void {
            $loggedException = $exception;
        }
    );

    $response = $controller->index(
        (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/users'),
        (new ResponseFactory())->createResponse()
    );

    assertSameValue(500, $response->getStatusCode(), 'User failure did not return HTTP 500.');
    assertSameValue(
        ['success' => false, 'message' => 'Server error.'],
        responseJson($response),
        'User failure response exposed details.'
    );
    assertSameValue($failure, $loggedException, 'Actual user exception was not logged server-side.');
    assertTrue(!str_contains((string) $response->getBody(), 'gy_password'), 'Database error leaked to response.');
});

test('user controller logs JSON serialization failures and returns a generic HTTP 500', function (): void {
    $pdo = new RecordingPdo();
    $pdo->fetchAllResult = [['gy_user_id' => 1, 'gy_username' => "\xB1\x31"]];
    $loggedException = null;
    $controller = new UserController(
        static fn (): UserRepository => new UserRepository($pdo),
        static function (Throwable $exception) use (&$loggedException): void {
            $loggedException = $exception;
        }
    );

    $response = $controller->index(
        (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/users'),
        (new ResponseFactory())->createResponse()
    );

    assertSameValue(500, $response->getStatusCode(), 'Invalid user database text did not return HTTP 500.');
    assertTrue($loggedException instanceof JsonException, 'User serialization failure was not logged.');
    assertSameValue(
        ['success' => false, 'message' => 'Server error.'],
        responseJson($response),
        'User serialization failure response exposed details.'
    );
});

test('user route requires JWT and does not create the repository before authorization', function (): void {
    $keys = testRsaKeys();
    $jwtService = new JwtService(
        'kronos-api',
        'kronos-api-clients',
        3600,
        $keys['private_path'],
        $keys['public_path']
    );
    $token = $jwtService->issue(['id' => 7, 'client_name' => 'SiBS HRIS']);
    $pdo = new RecordingPdo();
    $pdo->fetchAllResult = [['gy_user_id' => 137, 'gy_username' => 'test.user']];
    $repositoryFactoryCalls = 0;
    $userRepositoryFactory = static function () use ($pdo, &$repositoryFactoryCalls): UserRepository {
        $repositoryFactoryCalls++;

        return new UserRepository($pdo);
    };
    $app = AppFactory::create();
    $routes = require __DIR__ . '/../routes/api.php';
    $routes(
        $app,
        static fn (): ApiClientService => new ApiClientService(new RecordingPdo()),
        static fn (): ApiTokenService => new ApiTokenService(new RecordingPdo()),
        static fn (): JwtService => $jwtService,
        null,
        $userRepositoryFactory
    );
    $app->addRoutingMiddleware();
    $requestFactory = new ServerRequestFactory();
    $request = $requestFactory->createServerRequest('GET', '/api/v1/users?after_id=100');

    $unauthorizedResponse = $app->handle($request);

    assertSameValue(401, $unauthorizedResponse->getStatusCode(), 'User route was not JWT protected.');
    assertSameValue(0, $repositoryFactoryCalls, 'User repository was created before JWT authorization.');

    $authorizedResponse = $app->handle($request->withHeader('Authorization', 'Bearer ' . $token));
    $payload = responseJson($authorizedResponse);

    assertSameValue(200, $authorizedResponse->getStatusCode(), 'Authorized user route failed.');
    assertSameValue(1, $repositoryFactoryCalls, 'User repository was not created exactly once.');
    assertSameValue(137, $payload['pagination']['next_cursor'], 'User route returned the wrong cursor.');
});

test('account repository uses an explicit prepared cursor query', function (): void {
    $pdo = new RecordingPdo();
    $pdo->fetchAllResult = [
        [
            'gy_acc_id' => 41,
            'gy_acc_name' => 'Account 41',
            'gy_acc_ghl_name' => 'GHL Account 41',
            'gy_dept_id' => 9,
            'gy_acc_status' => 1,
        ],
    ];
    $repository = new AccountRepository($pdo);

    $rows = $repository->findAfter(37);

    $expectedSql = 'SELECT gy_acc_id, gy_acc_name, gy_acc_ghl_name, gy_dept_id, gy_acc_status '
        . 'FROM gy_accounts WHERE gy_acc_id > :after_id ORDER BY gy_acc_id ASC LIMIT 101';

    assertSameValue($expectedSql, $pdo->query, 'Account cursor query is incorrect.');
    assertSameValue(
        ['value' => 37, 'type' => PDO::PARAM_INT],
        $pdo->boundValues[':after_id'] ?? null,
        'Account after_id was not bound as an integer.'
    );
    assertSameValue($pdo->fetchAllResult, $rows, 'Repository did not return fetched account rows.');
});

test('account controller returns 100 rows and uses the actual last account ID as next cursor', function (): void {
    $pdo = new RecordingPdo();

    for ($index = 0; $index < 101; $index++) {
        $pdo->fetchAllResult[] = [
            'gy_acc_id' => 1000 + ($index * 3),
            'gy_acc_name' => 'Account ' . $index,
            'gy_acc_ghl_name' => 'GHL Account ' . $index,
            'gy_dept_id' => 9,
            'gy_acc_status' => 1,
        ];
    }

    $controller = new AccountController(
        static fn (): AccountRepository => new AccountRepository($pdo)
    );
    $request = (new ServerRequestFactory())
        ->createServerRequest('GET', '/api/v1/accounts')
        ->withQueryParams(['after_id' => '12']);

    $response = $controller->index($request, (new ResponseFactory())->createResponse());
    $payload = responseJson($response);

    assertSameValue(200, $response->getStatusCode(), 'Account endpoint did not return HTTP 200.');
    assertSameValue(
        'private, no-store',
        $response->getHeaderLine('Cache-Control'),
        'Account response can be stored by private caches.'
    );
    assertSameValue(100, count($payload['data']), 'Account endpoint did not cap data at 100 rows.');
    assertSameValue(1297, $payload['data'][99]['gy_acc_id'], 'Wrong final account was returned.');
    assertSameValue([
        'limit' => 100,
        'count' => 100,
        'current_cursor' => 12,
        'next_cursor' => 1297,
        'has_more' => true,
    ], $payload['pagination'], 'Account pagination metadata is incorrect.');
});

test('account controller normalizes invalid cursors and handles short and empty pages', function (): void {
    $pdo = new RecordingPdo();
    $controller = new AccountController(
        static fn (): AccountRepository => new AccountRepository($pdo)
    );
    $responseFactory = new ResponseFactory();
    $requestFactory = new ServerRequestFactory();

    $pdo->fetchAllResult = [
        ['gy_acc_id' => 5],
        ['gy_acc_id' => 17],
    ];
    $negativeResponse = $controller->index(
        $requestFactory
            ->createServerRequest('GET', '/api/v1/accounts')
            ->withQueryParams(['after_id' => '-20']),
        $responseFactory->createResponse()
    );
    $negativePayload = responseJson($negativeResponse);

    assertSameValue(0, $negativePayload['pagination']['current_cursor'], 'Negative account cursor was not normalized.');
    assertSameValue(17, $negativePayload['pagination']['next_cursor'], 'Short-page account cursor is incorrect.');
    assertSameValue(false, $negativePayload['pagination']['has_more'], 'Short account page reported more rows.');
    assertSameValue(['value' => 0, 'type' => PDO::PARAM_INT], $pdo->boundValues[':after_id'], 'Normalized account cursor was not queried.');

    $pdo->fetchAllResult = [];
    $emptyResponse = $controller->index(
        $requestFactory
            ->createServerRequest('GET', '/api/v1/accounts')
            ->withQueryParams(['after_id' => 'invalid']),
        $responseFactory->createResponse()
    );
    $emptyPayload = responseJson($emptyResponse);

    assertSameValue([], $emptyPayload['data'], 'Empty account page data is incorrect.');
    assertSameValue(0, $emptyPayload['pagination']['count'], 'Empty account page count is incorrect.');
    assertSameValue(0, $emptyPayload['pagination']['current_cursor'], 'Invalid account cursor was not normalized.');
    assertSameValue(null, $emptyPayload['pagination']['next_cursor'], 'Empty account page cursor must be null.');
    assertSameValue(false, $emptyPayload['pagination']['has_more'], 'Empty account page reported more rows.');
});

test('account controller logs the exception and returns only a generic HTTP 500', function (): void {
    $pdo = new RecordingPdo();
    $failure = new PDOException('SELECT private_account_data failed');
    $pdo->executeException = $failure;
    $loggedException = null;
    $controller = new AccountController(
        static fn (): AccountRepository => new AccountRepository($pdo),
        static function (Throwable $exception) use (&$loggedException): void {
            $loggedException = $exception;
        }
    );

    $response = $controller->index(
        (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/accounts'),
        (new ResponseFactory())->createResponse()
    );

    assertSameValue(500, $response->getStatusCode(), 'Account failure did not return HTTP 500.');
    assertSameValue(
        ['success' => false, 'message' => 'Server error.'],
        responseJson($response),
        'Account failure response exposed details.'
    );
    assertSameValue($failure, $loggedException, 'Actual account exception was not logged server-side.');
    assertTrue(!str_contains((string) $response->getBody(), 'private_account_data'), 'Account database error leaked to response.');
});

test('account route requires JWT and does not create the repository before authorization', function (): void {
    $keys = testRsaKeys();
    $jwtService = new JwtService(
        'kronos-api',
        'kronos-api-clients',
        3600,
        $keys['private_path'],
        $keys['public_path']
    );
    $token = $jwtService->issue(['id' => 7, 'client_name' => 'SiBS HRIS']);
    $pdo = new RecordingPdo();
    $pdo->fetchAllResult = [[
        'gy_acc_id' => 137,
        'gy_acc_name' => 'Account 137',
        'gy_acc_ghl_name' => 'GHL Account 137',
        'gy_dept_id' => 9,
        'gy_acc_status' => 1,
    ]];
    $repositoryFactoryCalls = 0;
    $accountRepositoryFactory = static function () use ($pdo, &$repositoryFactoryCalls): AccountRepository {
        $repositoryFactoryCalls++;

        return new AccountRepository($pdo);
    };
    $app = AppFactory::create();
    $routes = require __DIR__ . '/../routes/api.php';
    $routes(
        $app,
        static fn (): ApiClientService => new ApiClientService(new RecordingPdo()),
        static fn (): ApiTokenService => new ApiTokenService(new RecordingPdo()),
        static fn (): JwtService => $jwtService,
        null,
        null,
        $accountRepositoryFactory
    );
    $app->addRoutingMiddleware();
    $requestFactory = new ServerRequestFactory();
    $request = $requestFactory->createServerRequest('GET', '/api/v1/accounts?after_id=100');

    $unauthorizedResponse = $app->handle($request);

    assertSameValue(401, $unauthorizedResponse->getStatusCode(), 'Account route was not JWT protected.');
    assertSameValue(0, $repositoryFactoryCalls, 'Account repository was created before JWT authorization.');

    $authorizedResponse = $app->handle($request->withHeader('Authorization', 'Bearer ' . $token));
    $payload = responseJson($authorizedResponse);

    assertSameValue(200, $authorizedResponse->getStatusCode(), 'Authorized account route failed.');
    assertSameValue(1, $repositoryFactoryCalls, 'Account repository was not created exactly once.');
    assertSameValue(137, $payload['pagination']['next_cursor'], 'Account route returned the wrong cursor.');
});

test('department repository uses an explicit prepared cursor query', function (): void {
    $pdo = new RecordingPdo();
    $pdo->fetchAllResult = [
        ['id_department' => 41, 'name_department' => 'Department 41'],
    ];
    $repository = new DepartmentRepository($pdo);

    $rows = $repository->findAfter(37);

    $expectedSql = 'SELECT id_department, name_department '
        . 'FROM gy_department WHERE id_department > :after_id ORDER BY id_department ASC LIMIT 101';

    assertSameValue($expectedSql, $pdo->query, 'Department cursor query is incorrect.');
    assertSameValue(
        ['value' => 37, 'type' => PDO::PARAM_INT],
        $pdo->boundValues[':after_id'] ?? null,
        'Department after_id was not bound as an integer.'
    );
    assertSameValue($pdo->fetchAllResult, $rows, 'Repository did not return fetched department rows.');
});

test('department controller returns 100 rows and uses the actual last department ID as next cursor', function (): void {
    $pdo = new RecordingPdo();

    for ($index = 0; $index < 101; $index++) {
        $pdo->fetchAllResult[] = [
            'id_department' => 1000 + ($index * 3),
            'name_department' => 'Department ' . $index,
        ];
    }

    $controller = new DepartmentController(
        static fn (): DepartmentRepository => new DepartmentRepository($pdo)
    );
    $request = (new ServerRequestFactory())
        ->createServerRequest('GET', '/api/v1/departments')
        ->withQueryParams(['after_id' => '12']);

    $response = $controller->index($request, (new ResponseFactory())->createResponse());
    $payload = responseJson($response);

    assertSameValue(200, $response->getStatusCode(), 'Department endpoint did not return HTTP 200.');
    assertSameValue(
        'private, no-store',
        $response->getHeaderLine('Cache-Control'),
        'Department response can be stored by private caches.'
    );
    assertSameValue(100, count($payload['data']), 'Department endpoint did not cap data at 100 rows.');
    assertSameValue(1297, $payload['data'][99]['id_department'], 'Wrong final department was returned.');
    assertSameValue([
        'limit' => 100,
        'count' => 100,
        'current_cursor' => 12,
        'next_cursor' => 1297,
        'has_more' => true,
    ], $payload['pagination'], 'Department pagination metadata is incorrect.');
});

test('department controller normalizes invalid cursors and handles short and empty pages', function (): void {
    $pdo = new RecordingPdo();
    $controller = new DepartmentController(
        static fn (): DepartmentRepository => new DepartmentRepository($pdo)
    );
    $responseFactory = new ResponseFactory();
    $requestFactory = new ServerRequestFactory();

    $pdo->fetchAllResult = [
        ['id_department' => 5, 'name_department' => 'Department 5'],
        ['id_department' => 17, 'name_department' => 'Department 17'],
    ];
    $negativeResponse = $controller->index(
        $requestFactory
            ->createServerRequest('GET', '/api/v1/departments')
            ->withQueryParams(['after_id' => '-20']),
        $responseFactory->createResponse()
    );
    $negativePayload = responseJson($negativeResponse);

    assertSameValue(0, $negativePayload['pagination']['current_cursor'], 'Negative department cursor was not normalized.');
    assertSameValue(17, $negativePayload['pagination']['next_cursor'], 'Short-page department cursor is incorrect.');
    assertSameValue(false, $negativePayload['pagination']['has_more'], 'Short department page reported more rows.');
    assertSameValue(['value' => 0, 'type' => PDO::PARAM_INT], $pdo->boundValues[':after_id'], 'Normalized department cursor was not queried.');

    $malformedResponse = $controller->index(
        $requestFactory
            ->createServerRequest('GET', '/api/v1/departments')
            ->withQueryParams(['after_id' => 'invalid']),
        $responseFactory->createResponse()
    );
    $malformedPayload = responseJson($malformedResponse);

    assertSameValue(0, $malformedPayload['pagination']['current_cursor'], 'Malformed department cursor was not normalized.');
    assertSameValue(['value' => 0, 'type' => PDO::PARAM_INT], $pdo->boundValues[':after_id'], 'Malformed department cursor was not queried as zero.');

    $defaultResponse = $controller->index(
        $requestFactory->createServerRequest('GET', '/api/v1/departments'),
        $responseFactory->createResponse()
    );
    $defaultPayload = responseJson($defaultResponse);

    assertSameValue(0, $defaultPayload['pagination']['current_cursor'], 'Omitted department cursor did not default to zero.');
    assertSameValue(['value' => 0, 'type' => PDO::PARAM_INT], $pdo->boundValues[':after_id'], 'Default department cursor was not queried as zero.');

    $pdo->fetchAllResult = [];
    $emptyResponse = $controller->index(
        $requestFactory
            ->createServerRequest('GET', '/api/v1/departments')
            ->withQueryParams(['after_id' => '123']),
        $responseFactory->createResponse()
    );
    $emptyPayload = responseJson($emptyResponse);

    assertSameValue([], $emptyPayload['data'], 'Empty department page data is incorrect.');
    assertSameValue(0, $emptyPayload['pagination']['count'], 'Empty department page count is incorrect.');
    assertSameValue(123, $emptyPayload['pagination']['current_cursor'], 'Empty department page cursor is incorrect.');
    assertSameValue(null, $emptyPayload['pagination']['next_cursor'], 'Empty department page next cursor must be null.');
    assertSameValue(false, $emptyPayload['pagination']['has_more'], 'Empty department page reported more rows.');
});

test('department controller logs the exception and returns only a generic HTTP 500', function (): void {
    $pdo = new RecordingPdo();
    $failure = new PDOException('SELECT private_department_data failed');
    $pdo->executeException = $failure;
    $loggedException = null;
    $controller = new DepartmentController(
        static fn (): DepartmentRepository => new DepartmentRepository($pdo),
        static function (Throwable $exception) use (&$loggedException): void {
            $loggedException = $exception;
        }
    );

    $response = $controller->index(
        (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/departments'),
        (new ResponseFactory())->createResponse()
    );

    assertSameValue(500, $response->getStatusCode(), 'Department failure did not return HTTP 500.');
    assertSameValue(
        ['success' => false, 'message' => 'Server error.'],
        responseJson($response),
        'Department failure response exposed details.'
    );
    assertSameValue($failure, $loggedException, 'Actual department exception was not logged server-side.');
    assertTrue(!str_contains((string) $response->getBody(), 'private_department_data'), 'Department database error leaked to response.');
});

test('department controller logs JSON serialization failures and returns a generic HTTP 500', function (): void {
    $pdo = new RecordingPdo();
    $pdo->fetchAllResult = [['id_department' => 1, 'name_department' => "\xB1\x31"]];
    $loggedException = null;
    $controller = new DepartmentController(
        static fn (): DepartmentRepository => new DepartmentRepository($pdo),
        static function (Throwable $exception) use (&$loggedException): void {
            $loggedException = $exception;
        }
    );

    $response = $controller->index(
        (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/departments'),
        (new ResponseFactory())->createResponse()
    );

    assertSameValue(500, $response->getStatusCode(), 'Invalid department database text did not return HTTP 500.');
    assertTrue($loggedException instanceof JsonException, 'Department serialization failure was not logged.');
    assertSameValue(
        ['success' => false, 'message' => 'Server error.'],
        responseJson($response),
        'Department serialization failure response exposed details.'
    );
});

test('department route requires JWT and does not create the repository before authorization', function (): void {
    $keys = testRsaKeys();
    $jwtService = new JwtService(
        'kronos-api',
        'kronos-api-clients',
        3600,
        $keys['private_path'],
        $keys['public_path']
    );
    $token = $jwtService->issue(['id' => 7, 'client_name' => 'SiBS HRIS']);
    $pdo = new RecordingPdo();
    $pdo->fetchAllResult = [[
        'id_department' => 137,
        'name_department' => 'Department 137',
    ]];
    $repositoryFactoryCalls = 0;
    $departmentRepositoryFactory = static function () use ($pdo, &$repositoryFactoryCalls): DepartmentRepository {
        $repositoryFactoryCalls++;

        return new DepartmentRepository($pdo);
    };
    $app = AppFactory::create();
    $routes = require __DIR__ . '/../routes/api.php';
    $routes(
        $app,
        static fn (): ApiClientService => new ApiClientService(new RecordingPdo()),
        static fn (): ApiTokenService => new ApiTokenService(new RecordingPdo()),
        static fn (): JwtService => $jwtService,
        null,
        null,
        null,
        $departmentRepositoryFactory
    );
    $app->addRoutingMiddleware();
    $requestFactory = new ServerRequestFactory();
    $request = $requestFactory->createServerRequest('GET', '/api/v1/departments?after_id=100');

    $unauthorizedResponse = $app->handle($request);

    assertSameValue(401, $unauthorizedResponse->getStatusCode(), 'Department route was not JWT protected.');
    assertSameValue(0, $repositoryFactoryCalls, 'Department repository was created before JWT authorization.');

    $authorizedResponse = $app->handle($request->withHeader('Authorization', 'Bearer ' . $token));
    $payload = responseJson($authorizedResponse);

    assertSameValue(200, $authorizedResponse->getStatusCode(), 'Authorized department route failed.');
    assertSameValue(1, $repositoryFactoryCalls, 'Department repository was not created exactly once.');
    assertSameValue(137, $payload['pagination']['next_cursor'], 'Department route returned the wrong cursor.');
});

test('employee account assignment repository uses an explicit prepared cursor query', function (): void {
    $pdo = new RecordingPdo();
    $pdo->fetchAllResult = [[
        'at_id' => 41,
        'at_emp_code' => 'EMP-41',
        'at_account_id' => 9,
        'at_added_by' => 'admin',
    ]];
    $repository = new EmployeeAccountAssignmentRepository($pdo);

    $rows = $repository->findAfter(37);

    $expectedSql = 'SELECT at_id, at_emp_code, at_account_id, at_added_by '
        . 'FROM assign_timesheet WHERE at_id > :after_id ORDER BY at_id ASC LIMIT 101';

    assertSameValue($expectedSql, $pdo->query, 'Employee account assignment cursor query is incorrect.');
    assertSameValue(
        ['value' => 37, 'type' => PDO::PARAM_INT],
        $pdo->boundValues[':after_id'] ?? null,
        'Employee account assignment after_id was not bound as an integer.'
    );
    assertSameValue($pdo->fetchAllResult, $rows, 'Repository did not return fetched employee account assignments.');
});

test('employee account assignment controller returns 100 rows with the actual next cursor', function (): void {
    $pdo = new RecordingPdo();

    for ($index = 0; $index < 101; $index++) {
        $pdo->fetchAllResult[] = [
            'at_id' => 1000 + ($index * 3),
            'at_emp_code' => 'EMP-' . $index,
            'at_account_id' => 9,
            'at_added_by' => 'admin',
        ];
    }

    $controller = new EmployeeAccountAssignmentController(
        static fn (): EmployeeAccountAssignmentRepository => new EmployeeAccountAssignmentRepository($pdo)
    );
    $request = (new ServerRequestFactory())
        ->createServerRequest('GET', '/api/v1/assign-timesheet')
        ->withQueryParams(['after_id' => '12']);

    $response = $controller->index($request, (new ResponseFactory())->createResponse());
    $payload = responseJson($response);

    assertSameValue(200, $response->getStatusCode(), 'Employee account assignment endpoint did not return HTTP 200.');
    assertSameValue(100, count($payload['data']), 'Employee account assignment endpoint did not cap data at 100 rows.');
    assertSameValue(1297, $payload['data'][99]['at_id'], 'Wrong final employee account assignment was returned.');
    assertSameValue([
        'limit' => 100,
        'count' => 100,
        'current_cursor' => 12,
        'next_cursor' => 1297,
        'has_more' => true,
    ], $payload['pagination'], 'Employee account assignment pagination metadata is incorrect.');
});

test('employee account assignment controller normalizes negative cursors and handles empty pages', function (): void {
    $pdo = new RecordingPdo();
    $controller = new EmployeeAccountAssignmentController(
        static fn (): EmployeeAccountAssignmentRepository => new EmployeeAccountAssignmentRepository($pdo)
    );
    $responseFactory = new ResponseFactory();
    $requestFactory = new ServerRequestFactory();

    $pdo->fetchAllResult = [['at_id' => 17]];
    $negativeResponse = $controller->index(
        $requestFactory->createServerRequest('GET', '/api/v1/assign-timesheet')
            ->withQueryParams(['after_id' => '-20']),
        $responseFactory->createResponse()
    );
    $negativePayload = responseJson($negativeResponse);

    assertSameValue(0, $negativePayload['pagination']['current_cursor'], 'Negative assignment cursor was not normalized.');
    assertSameValue(17, $negativePayload['pagination']['next_cursor'], 'Assignment next cursor is incorrect.');

    $pdo->fetchAllResult = [];
    $emptyResponse = $controller->index(
        $requestFactory->createServerRequest('GET', '/api/v1/assign-timesheet')
            ->withQueryParams(['after_id' => '123']),
        $responseFactory->createResponse()
    );
    $emptyPayload = responseJson($emptyResponse);

    assertSameValue([], $emptyPayload['data'], 'Empty assignment page data is incorrect.');
    assertSameValue(null, $emptyPayload['pagination']['next_cursor'], 'Empty assignment page next cursor must be null.');
    assertSameValue(false, $emptyPayload['pagination']['has_more'], 'Empty assignment page reported more rows.');
});

test('cron setting repository uses an explicit prepared cursor query', function (): void {
    $pdo = new RecordingPdo();
    $pdo->fetchAllResult = [[
        'cronid' => 41,
        'crondate' => '2026-09-28',
        'cronval' => 'daily',
        'leave_credits' => 1,
        'lastmonth_lc' => 0,
        'active_filter' => 1,
        'filter_id' => 9,
        'status' => 1,
        'name' => 'Attendance',
        'send_to' => 'to@example.com',
        'cc_to' => '',
        'bcc_to' => '',
        'attendance_report' => 1,
    ]];
    $repository = new CronSettingRepository($pdo);

    $rows = $repository->findAfter(37);

    $expectedSql = 'SELECT cronid, crondate, cronval, leave_credits, lastmonth_lc, active_filter, '
        . 'filter_id, status, name, send_to, cc_to, bcc_to, attendance_report '
        . 'FROM cronjob WHERE cronid > :after_id ORDER BY cronid ASC LIMIT 101';

    assertSameValue($expectedSql, $pdo->query, 'Cron setting cursor query is incorrect.');
    assertSameValue(
        ['value' => 37, 'type' => PDO::PARAM_INT],
        $pdo->boundValues[':after_id'] ?? null,
        'Cron setting after_id was not bound as an integer.'
    );
    assertSameValue($pdo->fetchAllResult, $rows, 'Repository did not return fetched cron settings.');
});

test('cron setting controller returns 100 rows with the actual next cursor', function (): void {
    $pdo = new RecordingPdo();

    for ($index = 0; $index < 101; $index++) {
        $pdo->fetchAllResult[] = [
            'cronid' => 1000 + ($index * 3),
            'crondate' => '2026-09-28',
            'cronval' => 'daily',
            'leave_credits' => 1,
            'lastmonth_lc' => 0,
            'active_filter' => 1,
            'filter_id' => 9,
            'status' => 1,
            'name' => 'Cron ' . $index,
            'send_to' => 'to@example.com',
            'cc_to' => '',
            'bcc_to' => '',
            'attendance_report' => 1,
        ];
    }

    $controller = new CronSettingController(
        static fn (): CronSettingRepository => new CronSettingRepository($pdo)
    );
    $request = (new ServerRequestFactory())
        ->createServerRequest('GET', '/api/v1/cronjob')
        ->withQueryParams(['after_id' => '12']);

    $response = $controller->index($request, (new ResponseFactory())->createResponse());
    $payload = responseJson($response);

    assertSameValue(100, count($payload['data']), 'Cron setting endpoint did not cap data at 100 rows.');
    assertSameValue(1297, $payload['data'][99]['cronid'], 'Wrong final cron setting was returned.');
    assertSameValue([
        'limit' => 100,
        'count' => 100,
        'current_cursor' => 12,
        'next_cursor' => 1297,
        'has_more' => true,
    ], $payload['pagination'], 'Cron setting pagination metadata is incorrect.');
});

test('cron setting controller normalizes negative cursors and handles empty pages', function (): void {
    $pdo = new RecordingPdo();
    $controller = new CronSettingController(
        static fn (): CronSettingRepository => new CronSettingRepository($pdo)
    );
    $responseFactory = new ResponseFactory();
    $requestFactory = new ServerRequestFactory();

    $pdo->fetchAllResult = [['cronid' => 17]];
    $negativePayload = responseJson($controller->index(
        $requestFactory->createServerRequest('GET', '/api/v1/cronjob')
            ->withQueryParams(['after_id' => '-20']),
        $responseFactory->createResponse()
    ));

    assertSameValue(0, $negativePayload['pagination']['current_cursor'], 'Negative cron cursor was not normalized.');
    assertSameValue(17, $negativePayload['pagination']['next_cursor'], 'Cron setting next cursor is incorrect.');

    $pdo->fetchAllResult = [];
    $emptyPayload = responseJson($controller->index(
        $requestFactory->createServerRequest('GET', '/api/v1/cronjob')
            ->withQueryParams(['after_id' => '123']),
        $responseFactory->createResponse()
    ));

    assertSameValue([], $emptyPayload['data'], 'Empty cron setting page data is incorrect.');
    assertSameValue(null, $emptyPayload['pagination']['next_cursor'], 'Empty cron setting next cursor must be null.');
    assertSameValue(false, $emptyPayload['pagination']['has_more'], 'Empty cron setting page reported more rows.');
});

test('DOB record repository uses an explicit prepared cursor query', function (): void {
    $pdo = new RecordingPdo();
    $pdo->fetchAllResult = [[
        'dob_id' => 41,
        'dob_reg_for' => 'EMP-41',
        'dob_reg_from' => 'EMP-9',
        'dob_message' => 'Happy birthday',
        'dob_read' => 0,
        'dob_date' => '2026-09-28',
    ]];
    $repository = new DobRecordRepository($pdo);

    $rows = $repository->findAfter(37);

    $expectedSql = 'SELECT dob_id, dob_reg_for, dob_reg_from, dob_message, dob_read, dob_date '
        . 'FROM dob_reg WHERE dob_id > :after_id ORDER BY dob_id ASC LIMIT 101';

    assertSameValue($expectedSql, $pdo->query, 'DOB record cursor query is incorrect.');
    assertSameValue(
        ['value' => 37, 'type' => PDO::PARAM_INT],
        $pdo->boundValues[':after_id'] ?? null,
        'DOB record after_id was not bound as an integer.'
    );
    assertSameValue($pdo->fetchAllResult, $rows, 'Repository did not return fetched DOB records.');
});

test('DOB record controller returns 100 rows with the actual next cursor', function (): void {
    $pdo = new RecordingPdo();

    for ($index = 0; $index < 101; $index++) {
        $pdo->fetchAllResult[] = [
            'dob_id' => 1000 + ($index * 3),
            'dob_reg_for' => 'EMP-' . $index,
            'dob_reg_from' => 'EMP-9',
            'dob_message' => 'Happy birthday',
            'dob_read' => 0,
            'dob_date' => '2026-09-28',
        ];
    }

    $controller = new DobRecordController(
        static fn (): DobRecordRepository => new DobRecordRepository($pdo)
    );
    $request = (new ServerRequestFactory())
        ->createServerRequest('GET', '/api/v1/dob-reg')
        ->withQueryParams(['after_id' => '12']);

    $response = $controller->index($request, (new ResponseFactory())->createResponse());
    $payload = responseJson($response);

    assertSameValue(100, count($payload['data']), 'DOB record endpoint did not cap data at 100 rows.');
    assertSameValue(1297, $payload['data'][99]['dob_id'], 'Wrong final DOB record was returned.');
    assertSameValue([
        'limit' => 100,
        'count' => 100,
        'current_cursor' => 12,
        'next_cursor' => 1297,
        'has_more' => true,
    ], $payload['pagination'], 'DOB record pagination metadata is incorrect.');
});

test('DOB record controller normalizes negative cursors and handles empty pages', function (): void {
    $pdo = new RecordingPdo();
    $controller = new DobRecordController(
        static fn (): DobRecordRepository => new DobRecordRepository($pdo)
    );
    $responseFactory = new ResponseFactory();
    $requestFactory = new ServerRequestFactory();

    $pdo->fetchAllResult = [['dob_id' => 17]];
    $negativePayload = responseJson($controller->index(
        $requestFactory->createServerRequest('GET', '/api/v1/dob-reg')
            ->withQueryParams(['after_id' => '-20']),
        $responseFactory->createResponse()
    ));

    assertSameValue(0, $negativePayload['pagination']['current_cursor'], 'Negative DOB cursor was not normalized.');
    assertSameValue(17, $negativePayload['pagination']['next_cursor'], 'DOB record next cursor is incorrect.');

    $pdo->fetchAllResult = [];
    $emptyPayload = responseJson($controller->index(
        $requestFactory->createServerRequest('GET', '/api/v1/dob-reg')
            ->withQueryParams(['after_id' => '123']),
        $responseFactory->createResponse()
    ));

    assertSameValue([], $emptyPayload['data'], 'Empty DOB record page data is incorrect.');
    assertSameValue(null, $emptyPayload['pagination']['next_cursor'], 'Empty DOB record next cursor must be null.');
    assertSameValue(false, $emptyPayload['pagination']['has_more'], 'Empty DOB record page reported more rows.');
});

test('new read controllers log database exceptions and return only generic HTTP 500 responses', function (): void {
    $cases = [
        [
            'controller' => EmployeeAccountAssignmentController::class,
            'repository' => EmployeeAccountAssignmentRepository::class,
            'path' => '/api/v1/assign-timesheet',
        ],
        [
            'controller' => CronSettingController::class,
            'repository' => CronSettingRepository::class,
            'path' => '/api/v1/cronjob',
        ],
        [
            'controller' => DobRecordController::class,
            'repository' => DobRecordRepository::class,
            'path' => '/api/v1/dob-reg',
        ],
    ];

    foreach ($cases as $case) {
        $pdo = new RecordingPdo();
        $failure = new PDOException('private database details');
        $pdo->executeException = $failure;
        $loggedException = null;
        $repository = new $case['repository']($pdo);
        $controller = new $case['controller'](
            static fn () => $repository,
            static function (Throwable $exception) use (&$loggedException): void {
                $loggedException = $exception;
            }
        );

        $response = $controller->index(
            (new ServerRequestFactory())->createServerRequest('GET', $case['path']),
            (new ResponseFactory())->createResponse()
        );

        assertSameValue(500, $response->getStatusCode(), $case['path'] . ' did not return HTTP 500.');
        assertSameValue(
            ['success' => false, 'message' => 'Server error.'],
            responseJson($response),
            $case['path'] . ' exposed exception details.'
        );
        assertSameValue($failure, $loggedException, $case['path'] . ' did not log the actual exception.');
    }
});

test('new read controllers default an omitted cursor to zero', function (): void {
    $cases = [
        [EmployeeAccountAssignmentController::class, EmployeeAccountAssignmentRepository::class, '/api/v1/assign-timesheet'],
        [CronSettingController::class, CronSettingRepository::class, '/api/v1/cronjob'],
        [DobRecordController::class, DobRecordRepository::class, '/api/v1/dob-reg'],
    ];

    foreach ($cases as [$controllerClass, $repositoryClass, $path]) {
        $pdo = new RecordingPdo();
        $repository = new $repositoryClass($pdo);
        $controller = new $controllerClass(static fn () => $repository);

        $response = $controller->index(
            (new ServerRequestFactory())->createServerRequest('GET', $path),
            (new ResponseFactory())->createResponse()
        );
        $payload = responseJson($response);

        assertSameValue(0, $payload['pagination']['current_cursor'], $path . ' did not default after_id to zero.');
        assertSameValue(
            ['value' => 0, 'type' => PDO::PARAM_INT],
            $pdo->boundValues[':after_id'] ?? null,
            $path . ' did not bind the default cursor as integer zero.'
        );
    }
});

test('new read controllers log JSON serialization failures and return generic HTTP 500 responses', function (): void {
    $cases = [
        [
            EmployeeAccountAssignmentController::class,
            EmployeeAccountAssignmentRepository::class,
            '/api/v1/assign-timesheet',
            ['at_id' => 1, 'at_emp_code' => "\xB1\x31"],
        ],
        [
            CronSettingController::class,
            CronSettingRepository::class,
            '/api/v1/cronjob',
            ['cronid' => 1, 'name' => "\xB1\x31"],
        ],
        [
            DobRecordController::class,
            DobRecordRepository::class,
            '/api/v1/dob-reg',
            ['dob_id' => 1, 'dob_message' => "\xB1\x31"],
        ],
    ];

    foreach ($cases as [$controllerClass, $repositoryClass, $path, $row]) {
        $pdo = new RecordingPdo();
        $pdo->fetchAllResult = [$row];
        $loggedException = null;
        $repository = new $repositoryClass($pdo);
        $controller = new $controllerClass(
            static fn () => $repository,
            static function (Throwable $exception) use (&$loggedException): void {
                $loggedException = $exception;
            }
        );

        $response = $controller->index(
            (new ServerRequestFactory())->createServerRequest('GET', $path),
            (new ResponseFactory())->createResponse()
        );

        assertSameValue(500, $response->getStatusCode(), $path . ' did not sanitize invalid database text.');
        assertTrue($loggedException instanceof JsonException, $path . ' did not log its serialization failure.');
        assertSameValue(
            ['success' => false, 'message' => 'Server error.'],
            responseJson($response),
            $path . ' exposed serialization failure details.'
        );
    }
});

test('all three new read routes require JWT before creating repositories', function (): void {
    $keys = testRsaKeys();
    $jwtService = new JwtService(
        'kronos-api',
        'kronos-api-clients',
        3600,
        $keys['private_path'],
        $keys['public_path']
    );
    $token = $jwtService->issue(['id' => 7, 'client_name' => 'SiBS HRIS']);

    $assignmentPdo = new RecordingPdo();
    $assignmentPdo->fetchAllResult = [['at_id' => 137]];
    $assignmentCalls = 0;
    $assignmentFactory = static function () use ($assignmentPdo, &$assignmentCalls): EmployeeAccountAssignmentRepository {
        $assignmentCalls++;
        return new EmployeeAccountAssignmentRepository($assignmentPdo);
    };

    $cronPdo = new RecordingPdo();
    $cronPdo->fetchAllResult = [['cronid' => 138]];
    $cronCalls = 0;
    $cronFactory = static function () use ($cronPdo, &$cronCalls): CronSettingRepository {
        $cronCalls++;
        return new CronSettingRepository($cronPdo);
    };

    $dobPdo = new RecordingPdo();
    $dobPdo->fetchAllResult = [['dob_id' => 139]];
    $dobCalls = 0;
    $dobFactory = static function () use ($dobPdo, &$dobCalls): DobRecordRepository {
        $dobCalls++;
        return new DobRecordRepository($dobPdo);
    };

    $app = AppFactory::create();
    $routes = require __DIR__ . '/../routes/api.php';
    $routes(
        $app,
        static fn (): ApiClientService => new ApiClientService(new RecordingPdo()),
        static fn (): ApiTokenService => new ApiTokenService(new RecordingPdo()),
        static fn (): JwtService => $jwtService,
        null,
        null,
        null,
        null,
        $assignmentFactory,
        $cronFactory,
        $dobFactory
    );
    $app->addRoutingMiddleware();
    $requestFactory = new ServerRequestFactory();
    $cases = [
        ['/api/v1/assign-timesheet?after_id=100', &$assignmentCalls, 137],
        ['/api/v1/cronjob?after_id=100', &$cronCalls, 138],
        ['/api/v1/dob-reg?after_id=100', &$dobCalls, 139],
    ];

    foreach ($cases as &$case) {
        $request = $requestFactory->createServerRequest('GET', $case[0]);
        $unauthorizedResponse = $app->handle($request);

        assertSameValue(401, $unauthorizedResponse->getStatusCode(), $case[0] . ' was not JWT protected.');
        assertSameValue(0, $case[1], $case[0] . ' created its repository before JWT authorization.');

        $authorizedResponse = $app->handle($request->withHeader('Authorization', 'Bearer ' . $token));
        $payload = responseJson($authorizedResponse);

        assertSameValue(200, $authorizedResponse->getStatusCode(), $case[0] . ' rejected a valid JWT.');
        assertSameValue(1, $case[1], $case[0] . ' did not create its repository exactly once.');
        assertSameValue($case[2], $payload['pagination']['next_cursor'], $case[0] . ' returned the wrong cursor.');
    }
    unset($case);
});

test('DTR publish repository uses the exact prepared cursor query', function (): void {
    $pdo = new RecordingPdo();
    $pdo->fetchAllResult = [['dtr_publish_id' => 41]];
    $repository = new DtrPublishRepository($pdo);

    $rows = $repository->findAfter(37);

    $expectedSql = 'SELECT dtr_publish_id, dtr_year, dtr_month, dtr_cutoff, gy_emp_code, dtr_noofhours, '
        . 'dtr_lateut, dtr_absences, dtr_regot, dtr_rdreg, dtr_rdot, dtr_shreg, dtr_shot, dtr_shrdreg, '
        . 'dtr_shrdot, dtr_lhreg, dtr_lhot, dtr_lhrdreg, dtr_lhrdot, dtr_ndreg, dtr_ndregot, dtr_ndrdreg, '
        . 'dtr_ndrdot, dtr_ndsh, dtr_ndshot, dtr_ndshrd, dtr_ndshrdot, dtr_ndlh, dtr_ndlhot, dtr_ndlhrd, '
        . 'dtr_ndlhrdot, dtr_publisher, dtr_mdrate, dtr_cmpute '
        . 'FROM dtr_publish WHERE dtr_publish_id > :after_id ORDER BY dtr_publish_id ASC LIMIT 101';

    assertSameValue($expectedSql, $pdo->query, 'DTR publish cursor query is incorrect.');
    assertSameValue(['value' => 37, 'type' => PDO::PARAM_INT], $pdo->boundValues[':after_id'] ?? null, 'DTR publish cursor binding is incorrect.');
    assertSameValue($pdo->fetchAllResult, $rows, 'Repository did not return fetched DTR publish rows.');
});

test('DTR publish controller returns 100 rows with the actual next cursor', function (): void {
    $pdo = new RecordingPdo();
    for ($index = 0; $index < 101; $index++) {
        $pdo->fetchAllResult[] = ['dtr_publish_id' => 1000 + ($index * 3)];
    }

    $controller = new DtrPublishController(static fn (): DtrPublishRepository => new DtrPublishRepository($pdo));
    $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/dtr-publish')
        ->withQueryParams(['after_id' => '12']);
    $payload = responseJson($controller->index($request, (new ResponseFactory())->createResponse()));

    assertSameValue(100, count($payload['data']), 'DTR publish endpoint did not cap data at 100 rows.');
    assertSameValue(1297, $payload['data'][99]['dtr_publish_id'], 'Wrong final DTR publish row was returned.');
    assertSameValue([
        'limit' => 100,
        'count' => 100,
        'current_cursor' => 12,
        'next_cursor' => 1297,
        'has_more' => true,
    ], $payload['pagination'], 'DTR publish pagination metadata is incorrect.');
});

test('announcement repository uses the exact prepared cursor query', function (): void {
    $pdo = new RecordingPdo();
    $pdo->fetchAllResult = [['gy_ann_id' => 41]];
    $repository = new AnnouncementRepository($pdo);
    $rows = $repository->findAfter(37);

    $expectedSql = 'SELECT gy_ann_id, gy_ann_serial, gy_ann_type, gy_ann_date, gy_ann_end, gy_ann_by, '
        . 'gy_ann_caption, gy_ann_attachment '
        . 'FROM gy_announce WHERE gy_ann_id > :after_id ORDER BY gy_ann_id ASC LIMIT 101';
    assertSameValue($expectedSql, $pdo->query, 'Announcement cursor query is incorrect.');
    assertSameValue(['value' => 37, 'type' => PDO::PARAM_INT], $pdo->boundValues[':after_id'] ?? null, 'Announcement cursor binding is incorrect.');
    assertSameValue($pdo->fetchAllResult, $rows, 'Repository did not return fetched announcements.');
});

test('announcement controller returns 100 rows with the actual next cursor', function (): void {
    $pdo = new RecordingPdo();
    for ($index = 0; $index < 101; $index++) {
        $pdo->fetchAllResult[] = ['gy_ann_id' => 1000 + ($index * 3)];
    }
    $controller = new AnnouncementController(static fn (): AnnouncementRepository => new AnnouncementRepository($pdo));
    $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/announcements')
        ->withQueryParams(['after_id' => '12']);
    $payload = responseJson($controller->index($request, (new ResponseFactory())->createResponse()));
    assertSameValue(100, count($payload['data']), 'Announcement endpoint did not cap data at 100 rows.');
    assertSameValue(1297, $payload['pagination']['next_cursor'], 'Announcement next cursor is incorrect.');
    assertSameValue(true, $payload['pagination']['has_more'], 'Announcement sentinel row was not detected.');
});

test('confirmation repository uses the exact prepared cursor query', function (): void {
    $pdo = new RecordingPdo();
    $pdo->fetchAllResult = [['gy_conf_id' => 41]];
    $repository = new ConfirmationRepository($pdo);
    $rows = $repository->findAfter(37);

    $expectedSql = 'SELECT gy_conf_id, gy_conf_date, gy_conf_by, gy_ann_id '
        . 'FROM gy_confirm WHERE gy_conf_id > :after_id ORDER BY gy_conf_id ASC LIMIT 101';
    assertSameValue($expectedSql, $pdo->query, 'Confirmation cursor query is incorrect.');
    assertSameValue(['value' => 37, 'type' => PDO::PARAM_INT], $pdo->boundValues[':after_id'] ?? null, 'Confirmation cursor binding is incorrect.');
    assertSameValue($pdo->fetchAllResult, $rows, 'Repository did not return fetched confirmations.');
});

test('confirmation controller returns 100 rows with the actual next cursor', function (): void {
    $pdo = new RecordingPdo();
    for ($index = 0; $index < 101; $index++) {
        $pdo->fetchAllResult[] = ['gy_conf_id' => 1000 + ($index * 3)];
    }
    $controller = new ConfirmationController(static fn (): ConfirmationRepository => new ConfirmationRepository($pdo));
    $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/confirmations')
        ->withQueryParams(['after_id' => '12']);
    $payload = responseJson($controller->index($request, (new ResponseFactory())->createResponse()));
    assertSameValue(100, count($payload['data']), 'Confirmation endpoint did not cap data at 100 rows.');
    assertSameValue(1297, $payload['pagination']['next_cursor'], 'Confirmation next cursor is incorrect.');
    assertSameValue(true, $payload['pagination']['has_more'], 'Confirmation sentinel row was not detected.');
});

test('new batch controllers cover cursor boundaries and sanitize failures', function (): void {
    $cases = [
        [DtrPublishController::class, DtrPublishRepository::class, '/api/v1/dtr-publish', 'dtr_publish_id'],
        [AnnouncementController::class, AnnouncementRepository::class, '/api/v1/announcements', 'gy_ann_id'],
        [ConfirmationController::class, ConfirmationRepository::class, '/api/v1/confirmations', 'gy_conf_id'],
    ];

    foreach ($cases as [$controllerClass, $repositoryClass, $path, $cursorField]) {
        $emptyPdo = new RecordingPdo();
        $emptyController = new $controllerClass(
            static fn () => new $repositoryClass($emptyPdo)
        );
        $emptyRequest = (new ServerRequestFactory())->createServerRequest('GET', $path)
            ->withQueryParams(['after_id' => '-25']);
        $emptyResponse = $emptyController->index(
            $emptyRequest,
            (new ResponseFactory())->createResponse()
        );
        $emptyPayload = responseJson($emptyResponse);
        assertSameValue(0, $emptyPayload['pagination']['current_cursor'], $path . ' did not normalize a negative cursor.');
        assertSameValue(null, $emptyPayload['pagination']['next_cursor'], $path . ' returned a cursor for an empty page.');
        assertSameValue(false, $emptyPayload['pagination']['has_more'], $path . ' reported more rows for an empty page.');

        $malformedPdo = new RecordingPdo();
        $malformedController = new $controllerClass(
            static fn () => new $repositoryClass($malformedPdo)
        );
        $malformedRequest = (new ServerRequestFactory())->createServerRequest('GET', $path)
            ->withQueryParams(['after_id' => 'not-an-id']);
        $malformedController->index(
            $malformedRequest,
            (new ResponseFactory())->createResponse()
        );
        assertSameValue(
            ['value' => 0, 'type' => PDO::PARAM_INT],
            $malformedPdo->boundValues[':after_id'] ?? null,
            $path . ' did not normalize a malformed cursor.'
        );

        $defaultPdo = new RecordingPdo();
        $defaultController = new $controllerClass(
            static fn () => new $repositoryClass($defaultPdo)
        );
        $defaultController->index(
            (new ServerRequestFactory())->createServerRequest('GET', $path),
            (new ResponseFactory())->createResponse()
        );
        assertSameValue(
            ['value' => 0, 'type' => PDO::PARAM_INT],
            $defaultPdo->boundValues[':after_id'] ?? null,
            $path . ' did not default an omitted cursor to zero.'
        );

        $fullPagePdo = new RecordingPdo();
        for ($index = 1; $index <= 100; $index++) {
            $fullPagePdo->fetchAllResult[] = [$cursorField => $index * 2];
        }
        $fullPageController = new $controllerClass(
            static fn () => new $repositoryClass($fullPagePdo)
        );
        $fullPagePayload = responseJson($fullPageController->index(
            (new ServerRequestFactory())->createServerRequest('GET', $path),
            (new ResponseFactory())->createResponse()
        ));
        assertSameValue(false, $fullPagePayload['pagination']['has_more'], $path . ' treated exactly 100 rows as 101.');
        assertSameValue(200, $fullPagePayload['pagination']['next_cursor'], $path . ' returned the wrong full-page cursor.');

        $failingPdo = new RecordingPdo();
        $failure = new RuntimeException('sensitive database detail');
        $failingPdo->executeException = $failure;
        $loggedException = null;
        $failingController = new $controllerClass(
            static fn () => new $repositoryClass($failingPdo),
            static function (Throwable $exception) use (&$loggedException): void {
                $loggedException = $exception;
            }
        );
        $errorResponse = $failingController->index(
            (new ServerRequestFactory())->createServerRequest('GET', $path),
            (new ResponseFactory())->createResponse()
        );
        assertSameValue(500, $errorResponse->getStatusCode(), $path . ' returned the wrong error status.');
        assertSameValue($failure, $loggedException, $path . ' did not log the database exception.');
        assertSameValue(
            ['success' => false, 'message' => 'Server error.'],
            responseJson($errorResponse),
            $path . ' exposed database error details.'
        );

        $invalidTextPdo = new RecordingPdo();
        $invalidTextPdo->fetchAllResult = [[$cursorField => 1, 'invalid_text' => "\xB1\x31"]];
        $serializationException = null;
        $invalidTextController = new $controllerClass(
            static fn () => new $repositoryClass($invalidTextPdo),
            static function (Throwable $exception) use (&$serializationException): void {
                $serializationException = $exception;
            }
        );
        $serializationResponse = $invalidTextController->index(
            (new ServerRequestFactory())->createServerRequest('GET', $path),
            (new ResponseFactory())->createResponse()
        );
        assertSameValue(500, $serializationResponse->getStatusCode(), $path . ' did not sanitize invalid database text.');
        assertTrue($serializationException instanceof JsonException, $path . ' did not log its serialization error.');
        assertSameValue(
            ['success' => false, 'message' => 'Server error.'],
            responseJson($serializationResponse),
            $path . ' exposed serialization details.'
        );
    }
});

test('DTR publish announcement and confirmation routes require JWT', function (): void {
    $keys = testRsaKeys();
    $jwtService = new JwtService(
        'kronos-api',
        'kronos-api-clients',
        3600,
        $keys['private_path'],
        $keys['public_path']
    );
    $token = $jwtService->issue(['id' => 7, 'client_name' => 'SiBS HRIS']);

    $dtrPdo = new RecordingPdo();
    $dtrPdo->fetchAllResult = [['dtr_publish_id' => 137]];
    $dtrCalls = 0;
    $dtrFactory = static function () use ($dtrPdo, &$dtrCalls): DtrPublishRepository {
        $dtrCalls++;
        return new DtrPublishRepository($dtrPdo);
    };

    $announcementPdo = new RecordingPdo();
    $announcementPdo->fetchAllResult = [['gy_ann_id' => 138]];
    $announcementCalls = 0;
    $announcementFactory = static function () use ($announcementPdo, &$announcementCalls): AnnouncementRepository {
        $announcementCalls++;
        return new AnnouncementRepository($announcementPdo);
    };

    $confirmationPdo = new RecordingPdo();
    $confirmationPdo->fetchAllResult = [['gy_conf_id' => 139]];
    $confirmationCalls = 0;
    $confirmationFactory = static function () use ($confirmationPdo, &$confirmationCalls): ConfirmationRepository {
        $confirmationCalls++;
        return new ConfirmationRepository($confirmationPdo);
    };

    $app = AppFactory::create();
    $routes = require __DIR__ . '/../routes/api.php';
    $routes(
        $app,
        static fn (): ApiClientService => new ApiClientService(new RecordingPdo()),
        static fn (): ApiTokenService => new ApiTokenService(new RecordingPdo()),
        static fn (): JwtService => $jwtService,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        $dtrFactory,
        $announcementFactory,
        $confirmationFactory
    );
    $app->addRoutingMiddleware();
    $requestFactory = new ServerRequestFactory();
    $cases = [
        ['/api/v1/dtr-publish?after_id=100', &$dtrCalls, 137],
        ['/api/v1/announcements?after_id=100', &$announcementCalls, 138],
        ['/api/v1/confirmations?after_id=100', &$confirmationCalls, 139],
    ];

    foreach ($cases as &$case) {
        $request = $requestFactory->createServerRequest('GET', $case[0]);
        $unauthorizedResponse = $app->handle($request);
        assertSameValue(401, $unauthorizedResponse->getStatusCode(), $case[0] . ' was not JWT protected.');
        assertSameValue(0, $case[1], $case[0] . ' created its repository before authorization.');

        $authorizedResponse = $app->handle(
            $request->withHeader('Authorization', 'Bearer ' . $token)
        );
        $payload = responseJson($authorizedResponse);
        assertSameValue(200, $authorizedResponse->getStatusCode(), $case[0] . ' rejected a valid JWT.');
        assertSameValue(1, $case[1], $case[0] . ' did not create its repository exactly once.');
        assertSameValue($case[2], $payload['pagination']['next_cursor'], $case[0] . ' returned the wrong cursor.');
    }
    unset($case);
});

test('batch table whitelist matches the approved live schema', function (): void {
    $schema = [
        'edit-logs' => ['gy_editlog', 'gy_editlog_id', 'gy_editlog_id,gy_emp_id,gy_edit_date'],
        'schedule-escalations' => ['gy_schedule_escalate', 'gy_sched_esc_id', 'gy_sched_esc_id,gy_sched_esc_code,gy_req_date,gy_req_status,gy_req_deny,gy_req_by,gy_req_to,gy_sup,gy_emp_code,gy_emp_fullname,gy_sched_day,gy_sched_mode,gy_sched_login,gy_sched_breakout,gy_sched_breakin,gy_sched_logout,gy_tracker_login,gy_tracker_logout,gy_req_reason,gy_req_photodir,gy_publish,old_sched_mode,old_sched_login,old_sched_breakout,old_sched_breakin,old_sched_logout,old_tracker_login,old_tracker_logout,msg_usercode'],
        'qds-query-keys' => ['qds_querykey', 'qdsqk_id', 'qdsqk_id,kronos_key_name,query_key,audit_col_id'],
        'team-data' => ['team_data', 'data_id', 'data_id,col_id,row_id,tool_id,data_value'],
        'holiday-types' => ['gy_holiday_types', 'gy_hol_type_id', 'gy_hol_type_id,gy_hol_type_name,gy_hol_abbrv,gy_daybonus,gy_nightbonus,lateut,absnt,leaves,gy_day_start,gy_day_end,gy_night_start,gy_night_end,gy_hol_status'],
        'team-column-list' => ['team_collist', 'col_id', 'col_id,team_id,col_val,col_type,col_status,col_order'],
        'logs' => ['gy_logs', 'gy_log_id', 'gy_log_id,gy_log_date,gy_emp_id,gy_log_code,gy_log_email,gy_log_fullname,gy_log_account,gy_log_status'],
        'schedules' => ['gy_schedule', 'gy_sched_id', 'gy_sched_id,gy_emp_id,gy_sched_day,gy_sched_mode,gy_sched_login,gy_sched_breakout,gy_sched_breakin,gy_sched_logout,gy_sched_reg,gy_sched_by'],
        'projects' => ['gy_my_project', 'gy_project', 'gy_project,gy_project_name,gy_project_address,gy_system_title,gy_year_origin,gy_url,gy_convert_to'],
        'tool-details' => ['tool_details', 'toold_id', 'toold_id,toold_sortid,toold_listid,toold_label,toold_type,toold_status'],
        'notifications' => ['gy_notification', 'gy_notif_id', 'gy_notif_id,gy_notif_type,gy_user_code,gy_notif_text,gy_notif_date,gy_notif_ip'],
        'holiday-calendar' => ['gy_holiday_calendar', 'gy_hol_id', 'gy_hol_id,gy_hol_type_id,gy_hol_reg,gy_hol_title,gy_hol_date,gy_a_year,gy_hol_lastday,gy_hol_loc'],
        'tools' => ['tool_list', 'tool_id', 'tool_id,tool_name,tool_status'],
        'requests' => ['gy_request', 'gy_req_id', 'gy_req_id,gy_req_code,gy_req_date,gy_req_status,gy_req_by,gy_emp_code,gy_emp_fullname,gy_sched_day,gy_sched_mode,gy_sched_login,gy_sched_breakout,gy_sched_breakin,gy_sched_logout,gy_req_reason'],
        'reasons' => ['gy_reason', 'gy_reason_id', 'gy_reason_id,gy_reason_name'],
        'schedule-rd-requests' => ['gy_schedule_rd_request', 'gy_rd_id', 'gy_rd_id,gy_rd_date,gy_rd_status,gy_tracker_id,gy_user_id,gy_rd_approved_by'],
        'leave-available' => ['gy_leave_available', 'gy_leave_avail_id', 'gy_leave_avail_id,gy_leave_avail_date,gy_leave_avail_dateto,gy_leave_avail_plotted,gy_leave_avail_approved,gy_user_id,gy_leave_avail_justify,gy_acc_id'],
        'qds-assign-groups' => ['qds_assign_group', 'qag_id', 'qag_id,qag_sibsid,qag_account'],
        'processes' => ['gy_process', 'gy_process_id', 'gy_process_id,gy_process_ref,gy_process_date_from,gy_process_date_to,EmployeeNumber,EmployeeName,NoOfHours,UnderTime,Absenses,RegularOT,RestDay,RestDayOT,SpecialHoliday,SpecialHolidayOT,SpecialHolidayRestDay,SpecialHolidayRestDayOT,LegalHoliday,LegalHolidayOT,LegalHolidayRestday,LegalHolidayRestdayOT,NightDiffRegular,NightDiffRegularOT,NightDiffRestDay,NightDiffRestDayOT,NightDiffSpecialHoliday,NightDiffSpecialHolidayOT,NightDiffSpecialHolidayRestDay,NightDiffSpecialHolidayRestDayOT,NightDiffLegalHoliday,NightDiffLegalHolidayOT,NightDiffLegalHolidayRestDay,NightDiffLegalHolidayRestDayOT'],
        'team-tools' => ['team_toollist', 'team_id', 'team_id,team_name,team_owner,team_switch'],
        'tool-data' => ['tool_data', 'td_id', 'td_id,td_tooldid,td_emp_code,td_value,td_status'],
        'temp-supervisors' => ['gy_temp_sup', 'temp_sup_id', 'temp_sup_id,temp_sup_code,temp_sup_date,temp_sup_by'],
        'whitelist' => ['gy_whitelist', 'id', 'id,sibs_id,ip,details'],
        'leave-credit-history' => ['leave_credits_history', 'lch_id', 'lch_id,lch_emp_code,lch_date,lch_old_credits,lch_new_credits,lch_type,lch_trigger_date_type,lch_trigger_amount,lch_trigger_affected_type,lch_updated_by,lch_daterecorded,lch_operation'],
        'tracker' => ['gy_tracker', 'gy_tracker_id', 'gy_tracker_id,gy_tracker_code,gy_tracker_date,gy_emp_code,gy_emp_email,gy_emp_fullname,gy_account_id,gy_emp_account,gy_tracker_login,gy_tracker_breakout,gy_tracker_breakin,gy_tracker_logout,gy_tracker_wh,gy_tracker_bh,gy_tracker_ot,gy_tracker_ath,gy_tracker_status,gy_tracker_request,gy_tracker_reason,gy_tracker_history,gy_tracker_remarks,gy_tracker_om,gy_tracker_loc'],
        'leaves' => ['gy_leave', 'gy_leave_id', 'gy_leave_id,gy_user_id,gy_acc_id,gy_leave_filed,gy_leave_type,gy_leave_paid,gy_leave_day,gy_leave_period_one,gy_leave_period_two,gy_emp_rate,gy_old_credits,gy_new_credits,gy_leave_date_from,gy_leave_date_to,gy_leave_reason,gy_leave_status,gy_leave_approver,gy_leave_date_approved,gy_leave_remarks,gy_leave_attachment,gy_publish,msg_usercode'],
        'escalations' => ['gy_escalate', 'gy_esc_id', 'gy_esc_id,gy_esc_type,gy_esc_reason,gy_esc_photodir,gy_esc_status,gy_esc_deny,gy_esc_date,gy_esc_by,gy_esc_to,gy_sup,gy_tracker_id,gy_tracker_date,gy_tracker_login,gy_tracker_breakout,gy_tracker_breakin,gy_tracker_logout,gy_tracker_wh,gy_tracker_bh,gy_tracker_ot,gy_publish,gy_usercode,old_tracker_date,old_tracker_login,old_tracker_breakout,old_tracker_breakin,old_tracker_logout,msg_usercode'],
    ];
    $expected = [];
    foreach ($schema as $route => [$table, $primaryKey, $columns]) {
        $expected[$route] = [
            'route' => $route,
            'table' => $table,
            'primaryKey' => $primaryKey,
            'columns' => explode(',', $columns),
        ];
    }

    assertSameValue($expected, BatchTableConfig::all(), 'Batch table whitelist does not match the approved schema.');
    assertTrue(!isset(BatchTableConfig::all()['qds-notifications']), 'Unsafe qds_notify table was configured.');
    assertTrue(!isset(BatchTableConfig::all()['sibs-accounts']), 'Missing DB1 sibs_accounts table was configured.');
    assertSameValue($expected['tracker'], BatchTableConfig::get('tracker'), 'Configured route lookup failed.');

    $unknownRejected = false;
    try {
        BatchTableConfig::get('not-configured');
    } catch (InvalidArgumentException) {
        $unknownRejected = true;
    }
    assertTrue($unknownRejected, 'Unknown batch route was not rejected.');
});

test('shared batch repository builds an explicit prepared cursor query', function (): void {
    $pdo = new RecordingPdo();
    $pdo->fetchAllResult = [['gy_process_id' => 41, 'EmployeeName' => 'Ada']];
    $repository = new BatchTableRepository($pdo, [
        'route' => 'processes',
        'table' => 'gy_process',
        'primaryKey' => 'gy_process_id',
        'columns' => ['gy_process_id', 'EmployeeName', 'NoOfHours'],
    ]);

    $rows = $repository->findAfter(37);

    assertSameValue(
        'SELECT gy_process_id, EmployeeName, NoOfHours FROM gy_process '
            . 'WHERE gy_process_id > :after_id ORDER BY gy_process_id ASC LIMIT 101',
        $pdo->query,
        'Shared batch repository emitted the wrong SQL.'
    );
    assertSameValue(
        ['value' => 37, 'type' => PDO::PARAM_INT],
        $pdo->boundValues[':after_id'] ?? null,
        'Shared batch repository bound the cursor incorrectly.'
    );
    assertSameValue($pdo->fetchAllResult, $rows, 'Shared batch repository did not return fetched rows.');
});

test('shared batch repository rejects unsafe configured SQL identifiers', function (): void {
    $unsafeConfigurations = [
        ['route' => 'bad', 'table' => 'gy_logs; DROP TABLE gy_logs', 'primaryKey' => 'gy_log_id', 'columns' => ['gy_log_id']],
        ['route' => 'bad', 'table' => 'gy_logs', 'primaryKey' => 'gy_log_id DESC', 'columns' => ['gy_log_id']],
        ['route' => 'bad', 'table' => 'gy_logs', 'primaryKey' => 'gy_log_id', 'columns' => ['gy_log_id', 'password AS value']],
    ];

    foreach ($unsafeConfigurations as $configuration) {
        $pdo = new RecordingPdo();
        $rejected = false;
        try {
            new BatchTableRepository($pdo, $configuration);
        } catch (InvalidArgumentException) {
            $rejected = true;
        }

        assertTrue($rejected, 'Unsafe configured SQL identifier was accepted.');
        assertSameValue(0, $pdo->prepareCalls, 'Unsafe configuration reached PDO preparation.');
    }
});

test('shared batch controller returns 100 rows with the actual configured cursor', function (): void {
    $configuration = BatchTableConfig::get('tracker');
    $pdo = new RecordingPdo();
    for ($index = 0; $index < 101; $index++) {
        $pdo->fetchAllResult[] = ['gy_tracker_id' => 1000 + ($index * 3)];
    }
    $controller = new BatchTableController(
        static fn (): BatchTableRepository => new BatchTableRepository($pdo, $configuration),
        'gy_tracker_id'
    );
    $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/tracker')
        ->withQueryParams(['after_id' => '12']);
    $response = $controller->index($request, (new ResponseFactory())->createResponse());
    $payload = responseJson($response);

    assertSameValue(200, $response->getStatusCode(), 'Shared batch controller returned the wrong status.');
    assertSameValue('private, no-store', $response->getHeaderLine('Cache-Control'), 'Shared batch response can be cached.');
    assertSameValue(100, count($payload['data']), 'Shared batch controller did not cap rows at 100.');
    assertSameValue([
        'limit' => 100,
        'count' => 100,
        'current_cursor' => 12,
        'next_cursor' => 1297,
        'has_more' => true,
    ], $payload['pagination'], 'Shared batch pagination metadata is incorrect.');
});

test('shared batch controller handles cursor and page boundaries', function (): void {
    $configuration = BatchTableConfig::get('reasons');
    $fullPdo = new RecordingPdo();
    for ($index = 1; $index <= 100; $index++) {
        $fullPdo->fetchAllResult[] = ['gy_reason_id' => $index * 2];
    }
    $fullController = new BatchTableController(
        static fn (): BatchTableRepository => new BatchTableRepository($fullPdo, $configuration),
        'gy_reason_id'
    );
    $fullPayload = responseJson($fullController->index(
        (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/reasons'),
        (new ResponseFactory())->createResponse()
    ));
    assertSameValue(false, $fullPayload['pagination']['has_more'], 'Exactly 100 batch rows incorrectly reported more data.');
    assertSameValue(200, $fullPayload['pagination']['next_cursor'], 'Exactly 100 batch rows returned the wrong cursor.');

    foreach ([null, 'invalid', '-25'] as $cursor) {
        $pdo = new RecordingPdo();
        $controller = new BatchTableController(
            static fn (): BatchTableRepository => new BatchTableRepository($pdo, $configuration),
            'gy_reason_id'
        );
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/reasons');
        if ($cursor !== null) {
            $request = $request->withQueryParams(['after_id' => $cursor]);
        }
        $payload = responseJson($controller->index($request, (new ResponseFactory())->createResponse()));

        assertSameValue(0, $payload['pagination']['current_cursor'], 'Invalid batch cursor was not normalized.');
        assertSameValue(null, $payload['pagination']['next_cursor'], 'Empty batch page returned a cursor.');
        assertSameValue(false, $payload['pagination']['has_more'], 'Empty batch page reported more rows.');
        assertSameValue(['value' => 0, 'type' => PDO::PARAM_INT], $pdo->boundValues[':after_id'] ?? null, 'Normalized batch cursor was bound incorrectly.');
    }
});

test('shared batch controller logs failures and returns only a generic error', function (): void {
    $configuration = BatchTableConfig::get('reasons');
    $databasePdo = new RecordingPdo();
    $databaseFailure = new RuntimeException('sensitive database detail');
    $databasePdo->executeException = $databaseFailure;
    $loggedException = null;
    $databaseController = new BatchTableController(
        static fn (): BatchTableRepository => new BatchTableRepository($databasePdo, $configuration),
        'gy_reason_id',
        static function (Throwable $exception) use (&$loggedException): void {
            $loggedException = $exception;
        }
    );
    $databaseResponse = $databaseController->index(
        (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/reasons'),
        (new ResponseFactory())->createResponse()
    );
    assertSameValue(500, $databaseResponse->getStatusCode(), 'Batch database error returned the wrong status.');
    assertSameValue($databaseFailure, $loggedException, 'Batch database exception was not logged.');
    assertSameValue(['success' => false, 'message' => 'Server error.'], responseJson($databaseResponse), 'Batch database details were exposed.');

    $invalidTextPdo = new RecordingPdo();
    $invalidTextPdo->fetchAllResult = [['gy_reason_id' => 1, 'gy_reason_name' => "\xB1\x31"]];
    $serializationFailure = null;
    $invalidTextController = new BatchTableController(
        static fn (): BatchTableRepository => new BatchTableRepository($invalidTextPdo, $configuration),
        'gy_reason_id',
        static function (Throwable $exception) use (&$serializationFailure): void {
            $serializationFailure = $exception;
        }
    );
    $invalidTextResponse = $invalidTextController->index(
        (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/reasons'),
        (new ResponseFactory())->createResponse()
    );
    assertSameValue(500, $invalidTextResponse->getStatusCode(), 'Batch serialization error returned the wrong status.');
    assertTrue($serializationFailure instanceof JsonException, 'Batch serialization exception was not logged.');
    assertSameValue(['success' => false, 'message' => 'Server error.'], responseJson($invalidTextResponse), 'Batch serialization details were exposed.');
});

test('all configured batch routes require JWT before creating repositories', function (): void {
    $keys = testRsaKeys();
    $jwtService = new JwtService(
        'kronos-api',
        'kronos-api-clients',
        3600,
        $keys['private_path'],
        $keys['public_path']
    );
    $token = $jwtService->issue(['id' => 7, 'client_name' => 'SiBS HRIS']);
    $calls = [];
    $receivedConfigurations = [];
    $batchFactory = static function (array $configuration) use (&$calls, &$receivedConfigurations): BatchTableRepository {
        $route = $configuration['route'];
        $calls[$route] = ($calls[$route] ?? 0) + 1;
        $receivedConfigurations[$route] = $configuration;
        $pdo = new RecordingPdo();
        $pdo->fetchAllResult = [[$configuration['primaryKey'] => 9000 + count($calls)]];

        return new BatchTableRepository($pdo, $configuration);
    };

    $app = AppFactory::create();
    $routes = require __DIR__ . '/../routes/api.php';
    $routes(
        app: $app,
        serviceFactory: static fn (): ApiClientService => new ApiClientService(new RecordingPdo()),
        apiTokenServiceFactory: static fn (): ApiTokenService => new ApiTokenService(new RecordingPdo()),
        jwtServiceFactory: static fn (): JwtService => $jwtService,
        batchTableRepositoryFactory: $batchFactory
    );
    $app->addRoutingMiddleware();
    $requestFactory = new ServerRequestFactory();

    foreach (BatchTableConfig::all() as $route => $configuration) {
        $request = $requestFactory->createServerRequest(
            'GET',
            '/api/v1/' . $route . '?after_id=100'
        );
        $unauthorizedResponse = $app->handle($request);
        assertSameValue(401, $unauthorizedResponse->getStatusCode(), $route . ' was not JWT protected.');
        assertSameValue(0, $calls[$route] ?? 0, $route . ' created its repository before authorization.');

        $authorizedResponse = $app->handle(
            $request->withHeader('Authorization', 'Bearer ' . $token)
        );
        $payload = responseJson($authorizedResponse);
        assertSameValue(200, $authorizedResponse->getStatusCode(), $route . ' rejected a valid JWT.');
        assertSameValue(1, $calls[$route] ?? 0, $route . ' did not create its repository exactly once.');
        assertSameValue($configuration, $receivedConfigurations[$route] ?? null, $route . ' received the wrong server configuration.');
        assertSameValue(100, $payload['pagination']['current_cursor'], $route . ' received the wrong request cursor.');
    }

    $patterns = array_map(
        static fn ($route): string => $route->getPattern(),
        $app->getRouteCollector()->getRoutes()
    );
    assertTrue(!in_array('/api/v1/qds-notifications', $patterns, true), 'Unsafe qds-notifications route was registered.');
    assertTrue(!in_array('/api/v1/sibs-accounts', $patterns, true), 'Missing DB1 sibs-accounts route was registered.');
});

test('employee directory repository executes one count and one paginated explicit join query', function (): void {
    $pdo = new EmployeeDirectoryRecordingPdo();
    $pdo->fetchResults[0] = ['total' => 31];
    $pdo->fetchAllResults[1] = [[
        'sibsId' => 'SIBS-001',
        'firstName' => 'Juan',
        'middleName' => 'Santos',
        'lastName' => 'Cruz',
        'email' => 'juan@example.test',
        'gender' => 'Male',
        'birthdate' => '1990-01-01',
        'civilStatus' => 'Single',
        'contact' => '09170000000',
        'hireDate' => '2020-01-01',
        'gy_assignedloc' => 1,
        'site' => 'Davao',
        'nhodate' => '2020-02-01',
        'account' => 'Example Account',
        'accountId' => 12,
        'departmentId' => 5,
        'department' => 'Operations',
        'accountManagerSibsId' => 'SIBS-099',
        'accountManagerFirstName' => 'Maria',
        'accountManagerMiddleName' => '',
        'accountManagerLastName' => 'Reyes',
        'userFullName' => 'Juan Santos Cruz',
        'userType' => 0,
        'userStatus' => 0,
    ]];
    $repository = new EmployeeDirectoryRepository($pdo);

    $result = $repository->findPage(2, 15, null, null, []);

    assertSameValue(2, count($pdo->queries), 'Employee directory must execute exactly two queries.');
    assertTrue(str_starts_with($pdo->queries[0], 'SELECT COUNT(*) AS total FROM gy_employee e '), 'Count query is not explicit or uses the wrong base table.');
    assertTrue(str_contains($pdo->queries[0], 'INNER JOIN gy_user u ON TRIM(u.gy_user_code) = TRIM(e.gy_emp_code)'), 'Employee-user join is incorrect.');
    assertTrue(str_contains($pdo->queries[0], 'INNER JOIN gy_accounts a ON e.gy_acc_id = a.gy_acc_id'), 'Employee-account join is incorrect.');
    assertTrue(str_contains($pdo->queries[0], 'LEFT JOIN gy_department d ON a.gy_dept_id = d.id_department'), 'Account-department join is incorrect.');
    assertTrue(str_contains($pdo->queries[0], 'LEFT JOIN gy_user managerUser ON e.gy_emp_supervisor = managerUser.gy_user_id'), 'Supervisor-manager join is incorrect.');
    assertTrue(str_contains($pdo->queries[0], 'LEFT JOIN gy_employee managerEmployee ON TRIM(managerEmployee.gy_emp_code) = TRIM(managerUser.gy_user_code)'), 'Manager employee join is incorrect.');
    assertTrue(str_contains($pdo->queries[0], 'WHERE u.gy_user_status = 0 AND a.gy_acc_status = 0'), 'Required active filters are missing.');

    $dataSql = $pdo->queries[1];
    assertTrue(!str_contains(strtoupper($dataSql), 'SELECT *'), 'Employee directory used SELECT *.');
    foreach ([
        'AS sibsId',
        'AS firstName',
        'AS middleName',
        'AS lastName',
        'AS email',
        'AS gender',
        'AS birthdate',
        'AS civilStatus',
        'AS contact',
        'AS hireDate',
        'AS gy_assignedloc',
        'AS site',
        'AS nhodate',
        'AS accountId',
        'AS account',
        'AS departmentId',
        'AS department',
        'AS userFullName',
        'AS userType',
        'AS userStatus',
        'AS accountManagerSibsId',
        'AS accountManagerFirstName',
        'AS accountManagerMiddleName',
        'AS accountManagerLastName',
    ] as $alias) {
        assertTrue(str_contains($dataSql, $alias), 'Employee directory omitted explicit output ' . $alias . '.');
    }
    $projection = substr($dataSql, 0, strpos($dataSql, ' FROM '));
    foreach ([
        'u.gy_user_id',
        'u.gy_user_code',
        'u.gy_username',
        'u.ghl_contact_id',
        'u.gy_user_function',
        'u.gy_head_code',
        'u.gy_script_code',
        'AS userId',
    ] as $forbiddenProjection) {
        assertTrue(!str_contains($projection, $forbiddenProjection), 'Employee directory exposed ' . $forbiddenProjection . '.');
    }
    assertTrue(str_contains($dataSql, "WHEN TRIM(CAST(e.gy_assignedloc AS CHAR)) = '3' THEN 'Hybrid'"), 'Site mapping is incomplete.');
    assertTrue(str_contains($dataSql, "COALESCE(NULLIF(TRIM(a.gy_acc_name), ''), NULLIF(TRIM(e.gy_emp_account), ''), NULLIF(TRIM(a.gy_acc_ghl_name), '')) AS account"), 'Account precedence is incorrect.');
    assertTrue(str_contains($dataSql, 'ORDER BY u.gy_user_id DESC LIMIT :limit OFFSET :offset'), 'Default sorting or pagination is incorrect.');
    assertSameValue([
        ':limit' => ['value' => 15, 'type' => PDO::PARAM_INT],
        ':offset' => ['value' => 15, 'type' => PDO::PARAM_INT],
    ], $pdo->bindings[1] ?? [], 'Pagination values were not safely bound.');
    assertSameValue(['data' => $pdo->fetchAllResults[1], 'total' => 31], $result, 'Repository returned the wrong directory page.');
});

test('employee directory repository safely applies search department and account filters', function (): void {
    $pdo = new EmployeeDirectoryRecordingPdo();
    $pdo->fetchResults[0] = ['total' => 2];
    $repository = new EmployeeDirectoryRepository($pdo);

    $repository->findPage(1, 20, 'juan', 5, [12, 15, 18]);

    foreach ($pdo->queries as $query) {
        assertTrue(str_contains($query, 'a.gy_dept_id = :department_id'), 'Department filter is missing.');
        assertTrue(str_contains($query, 'a.gy_acc_id IN (:account_id_0, :account_id_1, :account_id_2)'), 'Account filter placeholders are unsafe or incorrect.');
        assertTrue(!str_contains($query, 'juan'), 'Raw search input was embedded in SQL.');
        assertTrue(!str_contains($query, '12, 15, 18'), 'Raw account IDs were embedded in SQL.');
        assertTrue(str_contains($query, 'managerEmployee.gy_emp_lname LIKE :search_filter_12'), 'Manager search field is missing.');
        assertTrue(str_contains($query, "THEN 'Both Tagum and Davao'"), 'Site text search mapping is missing.');
    }
    assertTrue(str_contains($pdo->queries[1], 'CASE WHEN e.gy_emp_fname LIKE :search_rank_0'), 'Search prioritization is missing.');
    assertTrue(str_contains($pdo->queries[1], 'END ASC, u.gy_user_id DESC'), 'Search ordering is incorrect.');
    assertSameValue(['value' => 5, 'type' => PDO::PARAM_INT], $pdo->bindings[0][':department_id'] ?? null, 'Department filter was not bound as an integer.');
    assertSameValue(['value' => 12, 'type' => PDO::PARAM_INT], $pdo->bindings[0][':account_id_0'] ?? null, 'First account filter was not bound safely.');
    assertSameValue(['value' => 18, 'type' => PDO::PARAM_INT], $pdo->bindings[1][':account_id_2'] ?? null, 'Last account filter was not bound safely.');
    assertSameValue(['value' => '%juan%', 'type' => PDO::PARAM_STR], $pdo->bindings[0][':search_filter_0'] ?? null, 'Search filter was not bound safely.');
    assertSameValue(['value' => '%juan%', 'type' => PDO::PARAM_STR], $pdo->bindings[1][':search_rank_4'] ?? null, 'Search ranking was not bound safely.');
});

test('employee directory controller returns numbered pagination with defaults', function (): void {
    $pdo = new EmployeeDirectoryRecordingPdo();
    $pdo->fetchResults[0] = ['total' => 31];
    $pdo->fetchAllResults[1] = [[
        'sibsId' => 'SIBS-001',
        'userFullName' => 'Juan Santos Cruz',
        'userType' => 0,
        'userStatus' => 0,
    ]];
    $controller = new EmployeeDirectoryController(
        static fn (): EmployeeDirectoryRepository => new EmployeeDirectoryRepository($pdo)
    );

    $response = $controller->index(
        (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/employee-directory'),
        (new ResponseFactory())->createResponse()
    );
    $payload = responseJson($response);

    assertSameValue(200, $response->getStatusCode(), 'Employee directory returned the wrong status.');
    assertSameValue('private, no-store', $response->getHeaderLine('Cache-Control'), 'Employee directory response can be cached.');
    assertSameValue($pdo->fetchAllResults[1], $payload['data'], 'Employee directory returned the wrong rows.');
    assertSameValue([
        'currentPage' => 1,
        'totalPages' => 3,
        'total' => 31,
        'limit' => 15,
    ], $payload['pagination'], 'Employee directory default pagination is incorrect.');
    assertSameValue(['value' => 15, 'type' => PDO::PARAM_INT], $pdo->bindings[1][':limit'] ?? null, 'Default directory limit is incorrect.');
    assertSameValue(['value' => 0, 'type' => PDO::PARAM_INT], $pdo->bindings[1][':offset'] ?? null, 'Default directory offset is incorrect.');
});

test('employee directory controller normalizes paging and parses combined filters', function (): void {
    $pdo = new EmployeeDirectoryRecordingPdo();
    $pdo->fetchResults[0] = ['total' => 201];
    $controller = new EmployeeDirectoryController(
        static fn (): EmployeeDirectoryRepository => new EmployeeDirectoryRepository($pdo)
    );
    $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/employee-directory')
        ->withQueryParams([
            'page' => '-4',
            'limit' => '999',
            'search' => '  juan  ',
            'department_id' => '5',
            'account_ids' => '12, 15,12',
        ]);

    $payload = responseJson($controller->index($request, (new ResponseFactory())->createResponse()));

    assertSameValue([
        'currentPage' => 1,
        'totalPages' => 3,
        'total' => 201,
        'limit' => 100,
    ], $payload['pagination'], 'Employee directory did not normalize page or cap limit.');
    assertTrue(str_contains($pdo->queries[0], 'a.gy_acc_id IN (:account_id_0, :account_id_1)'), 'Account IDs were not parsed and deduplicated.');
    assertSameValue(['value' => 5, 'type' => PDO::PARAM_INT], $pdo->bindings[0][':department_id'] ?? null, 'Department query value was not parsed.');
    assertSameValue(['value' => 12, 'type' => PDO::PARAM_INT], $pdo->bindings[0][':account_id_0'] ?? null, 'First account ID was not parsed.');
    assertSameValue(['value' => 15, 'type' => PDO::PARAM_INT], $pdo->bindings[0][':account_id_1'] ?? null, 'Second account ID was not parsed.');
    assertSameValue(['value' => '%juan%', 'type' => PDO::PARAM_STR], $pdo->bindings[0][':search_filter_0'] ?? null, 'Search text was not trimmed.');
});

test('employee directory controller normalizes pages whose offset would overflow', function (): void {
    $pdo = new EmployeeDirectoryRecordingPdo();
    $pdo->fetchResults[0] = ['total' => 1];
    $controller = new EmployeeDirectoryController(
        static fn (): EmployeeDirectoryRepository => new EmployeeDirectoryRepository($pdo)
    );
    $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/employee-directory')
        ->withQueryParams([
            'page' => (string) PHP_INT_MAX,
            'limit' => '100',
        ]);

    $payload = responseJson($controller->index($request, (new ResponseFactory())->createResponse()));

    assertSameValue(1, $payload['pagination']['currentPage'], 'Overflowing directory page was not normalized.');
    assertSameValue(['value' => 0, 'type' => PDO::PARAM_INT], $pdo->bindings[1][':offset'] ?? null, 'Overflowing directory offset reached PDO.');
});

test('employee directory controller rejects invalid filters safely', function (): void {
    $cases = [
        ['department_id' => 'not-an-id'],
        ['department_id' => '0'],
        ['account_ids' => '12,bad'],
        ['account_ids' => '12,,15'],
        ['account_ids' => ['12', '15']],
        ['search' => ['juan']],
    ];

    foreach ($cases as $query) {
        $repositoryCreated = false;
        $controller = new EmployeeDirectoryController(
            static function () use (&$repositoryCreated): EmployeeDirectoryRepository {
                $repositoryCreated = true;
                return new EmployeeDirectoryRepository(new EmployeeDirectoryRecordingPdo());
            }
        );
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/employee-directory')
            ->withQueryParams($query);
        $response = $controller->index($request, (new ResponseFactory())->createResponse());

        assertSameValue(400, $response->getStatusCode(), 'Invalid directory filter did not return HTTP 400.');
        assertSameValue(['success' => false, 'message' => 'Invalid query parameters.'], responseJson($response), 'Directory validation exposed details.');
        assertSameValue(false, $repositoryCreated, 'Directory repository was created for invalid input.');
    }
});

test('employee directory controller logs unexpected failures and returns generic HTTP 500', function (): void {
    $failure = new RuntimeException('sensitive database detail');
    $loggedException = null;
    $controller = new EmployeeDirectoryController(
        static function () use ($failure): EmployeeDirectoryRepository {
            throw $failure;
        },
        static function (Throwable $exception) use (&$loggedException): void {
            $loggedException = $exception;
        }
    );
    $response = $controller->index(
        (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/employee-directory'),
        (new ResponseFactory())->createResponse()
    );

    assertSameValue(500, $response->getStatusCode(), 'Directory failure returned the wrong status.');
    assertSameValue($failure, $loggedException, 'Directory failure was not logged server-side.');
    assertSameValue(['success' => false, 'message' => 'Server error.'], responseJson($response), 'Directory failure details were exposed.');
});

test('employee directory route requires JWT before creating its repository', function (): void {
    $keys = testRsaKeys();
    $jwtService = new JwtService(
        'kronos-api',
        'kronos-api-clients',
        3600,
        $keys['private_path'],
        $keys['public_path']
    );
    $token = $jwtService->issue(['id' => 7, 'client_name' => 'SiBS HRIS']);
    $pdo = new EmployeeDirectoryRecordingPdo();
    $pdo->fetchResults[0] = ['total' => 1];
    $pdo->fetchAllResults[1] = [[
        'sibsId' => 'SIBS-001',
        'userFullName' => 'Juan Santos Cruz',
        'userType' => 0,
        'userStatus' => 0,
    ]];
    $repositoryCalls = 0;
    $repositoryFactory = static function () use ($pdo, &$repositoryCalls): EmployeeDirectoryRepository {
        $repositoryCalls++;
        return new EmployeeDirectoryRepository($pdo);
    };

    $app = AppFactory::create();
    $routes = require __DIR__ . '/../routes/api.php';
    $routes(
        app: $app,
        serviceFactory: static fn (): ApiClientService => new ApiClientService(new RecordingPdo()),
        apiTokenServiceFactory: static fn (): ApiTokenService => new ApiTokenService(new RecordingPdo()),
        jwtServiceFactory: static fn (): JwtService => $jwtService,
        employeeDirectoryRepositoryFactory: $repositoryFactory
    );
    $app->addRoutingMiddleware();
    $request = (new ServerRequestFactory())->createServerRequest(
        'GET',
        '/api/v1/employee-directory?page=1&limit=15'
    );

    $unauthorizedResponse = $app->handle($request);
    assertSameValue(401, $unauthorizedResponse->getStatusCode(), 'Employee directory route is not JWT protected.');
    assertSameValue(0, $repositoryCalls, 'Employee directory repository was created before JWT authorization.');

    $authorizedResponse = $app->handle(
        $request->withHeader('Authorization', 'Bearer ' . $token)
    );
    assertSameValue(200, $authorizedResponse->getStatusCode(), 'Employee directory rejected a valid JWT.');
    assertSameValue(1, $repositoryCalls, 'Employee directory repository was not created exactly once.');
    assertSameValue([
        'currentPage' => 1,
        'totalPages' => 1,
        'total' => 1,
        'limit' => 15,
    ], responseJson($authorizedResponse)['pagination'], 'Employee directory route returned the wrong pagination.');
});

test('employee relationship repository resolves each single relationship with one explicit prepared query', function (): void {
    $cases = [
        ['findAccountByEmployeeCode', 'test1', ':employee_code', 'TRIM(e.gy_emp_code) = :employee_code', 'gy_acc_id', PDO::PARAM_STR],
        ['findDepartmentByEmployeeCode', 'test1', ':employee_code', 'TRIM(e.gy_emp_code) = :employee_code', 'id_department', PDO::PARAM_STR],
        ['findUserByEmployeeCode', 'test1', ':employee_code', 'TRIM(e.gy_emp_code) = :employee_code', 'gy_user_id', PDO::PARAM_STR],
        ['findEmployeeByUserCode', 'test1', ':employee_code', 'TRIM(u.gy_user_code) = :employee_code', 'gy_emp_id', PDO::PARAM_STR],
        ['findDepartmentByAccount', 13, ':account_id', 'FROM gy_accounts a LEFT JOIN gy_department d', 'id_department', PDO::PARAM_INT],
    ];

    foreach ($cases as [$method, $identifier, $placeholder, $join, $relatedKey, $parameterType]) {
        $pdo = new RecordingPdo();
        $pdo->fetchResult = ['_parent_id' => 7, $relatedKey => 91, 'name_department' => 'Operations'];
        $result = (new EmployeeRelationshipRepository($pdo))->{$method}($identifier);

        assertSameValue(1, $pdo->prepareCalls, "{$method} executed more than one query.");
        assertTrue(!str_contains(strtoupper((string) $pdo->query), 'SELECT *'), "{$method} used SELECT *.");
        assertTrue(str_contains((string) $pdo->query, $join), "{$method} used the wrong relationship join.");
        assertSameValue(['value' => $identifier, 'type' => $parameterType], $pdo->boundValues[$placeholder] ?? null, "{$method} did not bind its identifier safely.");
        assertSameValue(true, $result['parent_exists'], "{$method} lost the parent marker.");
        assertTrue(!array_key_exists('_parent_id', $result['data'] ?? []), "{$method} exposed its internal parent marker.");
        assertSameValue(91, $result['data'][$relatedKey] ?? null, "{$method} returned the wrong related resource.");
    }
});

test('employee relationship repository distinguishes a missing parent from a missing related resource', function (): void {
    $missingParentPdo = new RecordingPdo();
    $missingParentPdo->fetchResult = false;
    $missingParent = (new EmployeeRelationshipRepository($missingParentPdo))->findAccountByEmployeeCode('missing-code');

    assertSameValue(['parent_exists' => false, 'data' => null], $missingParent, 'Missing employee was not distinguished.');

    $missingRelatedPdo = new RecordingPdo();
    $missingRelatedPdo->fetchResult = ['_parent_id' => 7, 'gy_acc_id' => null, 'gy_acc_name' => null];
    $missingRelated = (new EmployeeRelationshipRepository($missingRelatedPdo))->findAccountByEmployeeCode('test1');

    assertSameValue(['parent_exists' => true, 'data' => null], $missingRelated, 'Missing employee account was not distinguished.');
});

test('employee relationship repository projections exactly match existing resource allowlists', function (): void {
    $employeeFields = [
        'gy_emp_id', 'gy_emp_code', 'gy_emp_type', 'gy_emp_schedtype', 'gy_emp_rate',
        'gy_emp_email', 'gy_emp_lname', 'gy_emp_fname', 'gy_emp_mname', 'gy_emp_fullname',
        'gy_acc_id', 'gy_emp_account', 'gy_emp_supervisor', 'gy_emp_om', 'gy_emp_leave_credits',
        'gy_emp_hiredate', 'gy_emp_lastedit', 'gy_lastedit_by', 'gy_work_from', 'gy_gender',
        'gy_dob', 'gy_civilstatus', 'gy_assignedloc', 'gy_tagumdate', 'gy_davaodate',
        'gy_hybriddate', 'gy_accjoin', 'gy_nhodate', 'gy_fststartdate', 'gy_fstenddate',
        'gy_pststartdate', 'gy_pstenddate', 'gy_certification', 'gy_gradbaystartdate',
        'gy_gradbayenddate', 'gy_fullgolivedate', 'gy_promotiondate', 'gy_projempdate',
        'gy_probempdate', 'gy_regempdate', 'gy_last_working_day',
    ];
    $accountFields = ['gy_acc_id', 'gy_acc_name', 'gy_acc_ghl_name', 'gy_dept_id', 'gy_acc_status'];
    $departmentFields = ['id_department', 'name_department'];
    $userFields = [
        'gy_user_id', 'gy_user_code', 'ghl_contact_id', 'gy_full_name', 'gy_username',
        'gy_user_type', 'gy_user_function', 'gy_head_code', 'gy_script_code', 'gy_user_status',
    ];
    $cases = [
        ['findAccountByEmployeeCode', ['test1'], $accountFields],
        ['findDepartmentByEmployeeCode', ['test1'], $departmentFields],
        ['findUserByEmployeeCode', ['test1'], $userFields],
        ['findEmployeeByUserCode', ['test1'], $employeeFields],
        ['findDepartmentByAccount', [12], $departmentFields],
        ['findEmployeesByAccount', [12, 0], $employeeFields],
        ['findAccountsByDepartment', [5, 0], $accountFields],
        ['findEmployeesByDepartment', [5, 0], $employeeFields],
    ];

    foreach ($cases as [$method, $arguments, $expectedFields]) {
        $pdo = new RecordingPdo();
        $pdo->fetchResult = false;
        $pdo->fetchAllResult = [];
        (new EmployeeRelationshipRepository($pdo))->{$method}(...$arguments);
        $query = (string) $pdo->query;
        $fromPosition = strpos($query, ' FROM ');
        assertTrue($fromPosition !== false, "{$method} query has no FROM clause.");
        $projection = substr($query, strlen('SELECT '), $fromPosition - strlen('SELECT '));
        $selectedFields = array_slice(explode(', ', $projection), 1);
        $selectedFields = array_map(
            static fn (string $field): string => substr($field, strpos($field, '.') + 1),
            $selectedFields
        );

        assertSameValue($expectedFields, $selectedFields, "{$method} projection changed its resource allowlist.");
    }
});

test('employee relationship repository collections use one 101-row cursor query and preserve empty parents', function (): void {
    $cases = [
        ['findEmployeesByAccount', 12, 100, ':account_id', 'gy_emp_id', 'FROM gy_accounts a LEFT JOIN gy_employee e'],
        ['findAccountsByDepartment', 5, 100, ':department_id', 'gy_acc_id', 'FROM gy_department d LEFT JOIN gy_accounts a'],
        ['findEmployeesByDepartment', 5, 100, ':department_id', 'gy_emp_id', 'FROM gy_department d LEFT JOIN'],
    ];

    foreach ($cases as [$method, $parentId, $afterId, $parentPlaceholder, $cursorKey, $join]) {
        $pdo = new RecordingPdo();
        $pdo->fetchAllResult = [['_parent_id' => $parentId, $cursorKey => 137]];
        $result = (new EmployeeRelationshipRepository($pdo))->{$method}($parentId, $afterId);

        assertSameValue(1, $pdo->prepareCalls, "{$method} executed more than one query.");
        assertTrue(str_contains((string) $pdo->query, $join), "{$method} used the wrong parent relationship.");
        assertTrue(str_contains((string) $pdo->query, "{$cursorKey} > :after_id"), "{$method} omitted keyset pagination.");
        assertTrue(str_contains((string) $pdo->query, 'LIMIT 101'), "{$method} did not request the look-ahead row.");
        assertTrue(!str_contains(strtoupper((string) $pdo->query), ' OFFSET '), "{$method} used OFFSET pagination.");
        assertTrue(!str_contains(strtoupper((string) $pdo->query), 'COUNT('), "{$method} issued a count query.");
        assertSameValue(['value' => $parentId, 'type' => PDO::PARAM_INT], $pdo->boundValues[$parentPlaceholder] ?? null, "{$method} did not bind the parent ID.");
        assertSameValue(['value' => $afterId, 'type' => PDO::PARAM_INT], $pdo->boundValues[':after_id'] ?? null, "{$method} did not bind the cursor.");
        assertSameValue([[$cursorKey => 137]], $result['data'], "{$method} exposed internal fields or changed rows.");
    }

    $emptyPdo = new RecordingPdo();
    $emptyPdo->fetchAllResult = [['_parent_id' => 12, 'gy_emp_id' => null]];
    $empty = (new EmployeeRelationshipRepository($emptyPdo))->findEmployeesByAccount(12, 0);
    assertSameValue(['parent_exists' => true, 'data' => []], $empty, 'Existing account with no employees was not preserved.');
});

test('employee relationship controller returns compact single-resource success and distinct 404 responses', function (): void {
    $successPdo = new RecordingPdo();
    $successPdo->fetchResult = [
        '_parent_id' => 7,
        'gy_acc_id' => 12,
        'gy_acc_name' => 'Example',
        'gy_acc_ghl_name' => 'Example GHL',
        'gy_dept_id' => 5,
        'gy_acc_status' => 0,
    ];
    $controller = new EmployeeRelationshipController(
        static fn (): EmployeeRelationshipRepository => new EmployeeRelationshipRepository($successPdo)
    );
    $response = $controller->employeeAccount(
        (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/employees/test1/account'),
        (new ResponseFactory())->createResponse(),
        ['employeeCode' => '  test1  ']
    );

    assertSameValue(200, $response->getStatusCode(), 'Single relationship success returned the wrong status.');
    assertSameValue([
        'success' => true,
        'data' => [
            'gy_acc_id' => 12,
            'gy_acc_name' => 'Example',
            'gy_acc_ghl_name' => 'Example GHL',
            'gy_dept_id' => 5,
            'gy_acc_status' => 0,
        ],
    ], responseJson($response), 'Single relationship response was not compact.');
    assertSameValue(
        ['value' => 'test1', 'type' => PDO::PARAM_STR],
        $successPdo->boundValues[':employee_code'] ?? null,
        'Employee code was not trimmed and bound as a string.'
    );

    $missingParentPdo = new RecordingPdo();
    $missingParentPdo->fetchResult = false;
    $missingParentController = new EmployeeRelationshipController(
        static fn (): EmployeeRelationshipRepository => new EmployeeRelationshipRepository($missingParentPdo)
    );
    $missingParentResponse = $missingParentController->employeeAccount(
        (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/employees/missing-code/account'),
        (new ResponseFactory())->createResponse(),
        ['employeeCode' => 'missing-code']
    );
    assertSameValue(404, $missingParentResponse->getStatusCode(), 'Missing parent did not return HTTP 404.');
    assertSameValue(['success' => false, 'message' => 'Resource not found.'], responseJson($missingParentResponse), 'Missing parent response is incorrect.');

    $missingRelatedPdo = new RecordingPdo();
    $missingRelatedPdo->fetchResult = ['_parent_id' => 7, 'gy_acc_id' => null];
    $missingRelatedController = new EmployeeRelationshipController(
        static fn (): EmployeeRelationshipRepository => new EmployeeRelationshipRepository($missingRelatedPdo)
    );
    $missingRelatedResponse = $missingRelatedController->employeeAccount(
        (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/employees/test1/account'),
        (new ResponseFactory())->createResponse(),
        ['employeeCode' => 'test1']
    );
    assertSameValue(404, $missingRelatedResponse->getStatusCode(), 'Missing relationship did not return HTTP 404.');
    assertSameValue(['success' => false, 'message' => 'Related resource not found.'], responseJson($missingRelatedResponse), 'Missing relationship response is incorrect.');
});

test('employee relationship controller uses the actual last ID for 100-row cursor pagination', function (): void {
    $pdo = new RecordingPdo();
    $pdo->fetchAllResult = [];
    for ($index = 0; $index < 101; $index++) {
        $pdo->fetchAllResult[] = [
            '_parent_id' => 12,
            'gy_emp_id' => 500 + ($index * 3),
            'gy_emp_code' => 'SIBS-' . $index,
        ];
    }
    $controller = new EmployeeRelationshipController(
        static fn (): EmployeeRelationshipRepository => new EmployeeRelationshipRepository($pdo)
    );
    $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/accounts/12/employees')
        ->withQueryParams(['after_id' => '-5']);
    $response = $controller->accountEmployees(
        $request,
        (new ResponseFactory())->createResponse(),
        ['accountId' => '12']
    );
    $payload = responseJson($response);

    assertSameValue(200, $response->getStatusCode(), 'Relationship collection returned the wrong status.');
    assertSameValue(100, count($payload['data']), 'Relationship collection returned more than 100 rows.');
    assertSameValue([
        'limit' => 100,
        'count' => 100,
        'current_cursor' => 0,
        'next_cursor' => 797,
        'has_more' => true,
    ], $payload['pagination'], 'Relationship cursor pagination is incorrect.');
});

test('employee relationship controller logs failures and returns only a generic server error', function (): void {
    $failure = new RuntimeException('private relationship SQL');
    $logged = null;
    $controller = new EmployeeRelationshipController(
        static function () use ($failure): EmployeeRelationshipRepository {
            throw $failure;
        },
        static function (Throwable $exception) use (&$logged): void {
            $logged = $exception;
        }
    );
    $response = $controller->employeeAccount(
        (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/employees/test1/account'),
        (new ResponseFactory())->createResponse(),
        ['employeeCode' => 'test1']
    );

    assertSameValue(500, $response->getStatusCode(), 'Relationship failure returned the wrong status.');
    assertSameValue($failure, $logged, 'Relationship failure was not logged.');
    assertSameValue(['success' => false, 'message' => 'Server error.'], responseJson($response), 'Relationship error exposed details.');
});

test('all employee relationship routes require JWT before repository creation', function (): void {
    $keys = testRsaKeys();
    $jwtService = new JwtService(
        'kronos-api',
        'kronos-api-clients',
        3600,
        $keys['private_path'],
        $keys['public_path']
    );
    $repositoryCalls = 0;
    $recordingPdos = [];
    $repositoryFactory = static function () use (&$repositoryCalls, &$recordingPdos): EmployeeRelationshipRepository {
        $repositoryCalls++;
        $pdo = new RecordingPdo();
        $pdo->fetchResult = [
            '_parent_id' => 7,
            'gy_acc_id' => 12,
            'id_department' => 5,
            'gy_user_id' => 11,
            'gy_emp_id' => 7,
        ];
        $pdo->fetchAllResult = [[
            '_parent_id' => 7,
            'gy_acc_id' => 12,
            'gy_emp_id' => 7,
        ]];
        $recordingPdos[] = $pdo;
        return new EmployeeRelationshipRepository($pdo);
    };
    $app = AppFactory::create();
    $routes = require __DIR__ . '/../routes/api.php';
    $routes(
        app: $app,
        serviceFactory: static fn (): ApiClientService => new ApiClientService(new RecordingPdo()),
        apiTokenServiceFactory: static fn (): ApiTokenService => new ApiTokenService(new RecordingPdo()),
        jwtServiceFactory: static fn (): JwtService => $jwtService,
        employeeRelationshipRepositoryFactory: $repositoryFactory
    );
    $app->addRoutingMiddleware();

    $routesUnderTest = [
        ['/api/v1/employees/test1/account', 'WHERE TRIM(e.gy_emp_code) = :employee_code'],
        ['/api/v1/employees/test1/department', 'WHERE TRIM(e.gy_emp_code) = :employee_code'],
        ['/api/v1/employees/test1/user', 'WHERE TRIM(e.gy_emp_code) = :employee_code'],
        ['/api/v1/accounts/12/employees', 'FROM gy_accounts a LEFT JOIN gy_employee e'],
        ['/api/v1/accounts/12/department', 'FROM gy_accounts a LEFT JOIN gy_department d'],
        ['/api/v1/departments/5/accounts', 'FROM gy_department d LEFT JOIN gy_accounts a'],
        ['/api/v1/departments/5/employees', 'FROM gy_department d LEFT JOIN'],
        ['/api/v1/users/test1/employee', 'WHERE TRIM(u.gy_user_code) = :employee_code'],
    ];

    foreach ($routesUnderTest as [$path]) {
        $response = $app->handle((new ServerRequestFactory())->createServerRequest('GET', $path));
        assertSameValue(401, $response->getStatusCode(), "{$path} was not JWT protected.");
    }
    assertSameValue(0, $repositoryCalls, 'Relationship repository was created before JWT authorization.');

    $authorization = 'Bearer ' . $jwtService->issue(['id' => 7, 'client_name' => 'SiBS HRIS']);

    foreach ($routesUnderTest as $index => [$path, $expectedQueryFragment]) {
        $authorized = (new ServerRequestFactory())
            ->createServerRequest('GET', $path)
            ->withHeader('Authorization', $authorization);
        assertSameValue(200, $app->handle($authorized)->getStatusCode(), "Authorized {$path} request failed.");
        assertTrue(
            str_contains((string) ($recordingPdos[$index]->query ?? ''), $expectedQueryFragment),
            "{$path} dispatched to the wrong relationship method."
        );
    }
    assertSameValue(
        ['value' => 'test1', 'type' => PDO::PARAM_STR],
        $recordingPdos[7]->boundValues[':employee_code'] ?? null,
        'User-to-employee route did not bind gy_emp_code as a string.'
    );
    assertSameValue(8, $repositoryCalls, 'Authorized relationship routes did not create one repository each.');
});

test('schedule relationship repository uses one explicit joined query per relationship', function (): void {
    $singleCases = [
        ['findEmployeeByScheduleEscalationId', 22, ':schedule_escalation_id', 'TRIM(e.gy_emp_code) = TRIM(s.gy_emp_code)', 'gy_emp_id'],
        ['findEmployeeByScheduleId', 21, ':schedule_id', 's.gy_emp_id = e.gy_emp_id', 'gy_emp_id'],
        ['findCreatedByUserByScheduleId', 21, ':schedule_id', 's.gy_sched_by = u.gy_user_id', '_related_id'],
        ['findRequestedByUserByScheduleEscalationId', 22, ':schedule_escalation_id', 's.gy_req_by = u.gy_user_id', '_related_id'],
        ['findRequestedToUserByScheduleEscalationId', 22, ':schedule_escalation_id', 's.gy_req_to = u.gy_user_id', '_related_id'],
        ['findSupervisorUserByScheduleEscalationId', 22, ':schedule_escalation_id', 's.gy_sup = u.gy_user_id', '_related_id'],
        ['findTrackerByScheduleRdRequestId', 23, ':schedule_rd_request_id', 'r.gy_tracker_id = t.gy_tracker_id', 'gy_tracker_id'],
        ['findUserByScheduleRdRequestId', 23, ':schedule_rd_request_id', 'r.gy_user_id = u.gy_user_id', '_related_id'],
    ];

    foreach ($singleCases as [$method, $identifier, $placeholder, $join, $relatedKey]) {
        $pdo = new RecordingPdo();
        $pdo->fetchResult = ['_parent_id' => $identifier, $relatedKey => 91, 'gy_user_code' => 'test1'];
        $result = (new EmployeeRelationshipRepository($pdo))->{$method}($identifier);

        assertSameValue(1, $pdo->prepareCalls, "{$method} executed more than one query.");
        assertTrue(str_contains((string) $pdo->query, $join), "{$method} used the wrong join.");
        assertTrue(!str_contains(strtoupper((string) $pdo->query), 'SELECT *'), "{$method} used SELECT *.");
        assertSameValue(['value' => $identifier, 'type' => PDO::PARAM_INT], $pdo->boundValues[$placeholder] ?? null, "{$method} did not bind its ID.");
        assertTrue($result['data'] !== null, "{$method} returned no related resource.");
        assertTrue(!array_key_exists('_related_id', $result['data']), "{$method} exposed an internal user ID.");
        assertTrue(!str_contains((string) $pdo->query, 'u.gy_password'), "{$method} selected a password.");
    }

    $collectionCases = [
        ['findSchedulesByEmployeeCode', 'test1', 'gy_sched_id', 'e.gy_emp_id = s.gy_emp_id', []],
        ['findScheduleEscalationsByEmployeeCode', 'test1', 'gy_sched_esc_id', 'TRIM(s.gy_emp_code) = TRIM(e.gy_emp_code)', []],
        ['findScheduleRdRequestsByUserCode', 'test1', 'gy_rd_id', 'u.gy_user_id = r.gy_user_id', []],
        ['findSchedulesCreatedByUserCode', 'test1', 'gy_sched_id', 's.gy_sched_by = u.gy_user_id', ['s.gy_sched_by']],
        ['findSubmittedScheduleEscalationsByUserCode', 'test1', 'gy_sched_esc_id', 's.gy_req_by = u.gy_user_id', ['s.gy_req_by', 's.gy_req_to', 's.gy_sup']],
        ['findReceivedScheduleEscalationsByUserCode', 'test1', 'gy_sched_esc_id', 's.gy_req_to = u.gy_user_id', ['s.gy_req_by', 's.gy_req_to', 's.gy_sup']],
        ['findSupervisedScheduleEscalationsByUserCode', 'test1', 'gy_sched_esc_id', 's.gy_sup = u.gy_user_id', ['s.gy_req_by', 's.gy_req_to', 's.gy_sup']],
    ];

    foreach ($collectionCases as [$method, $employeeCode, $cursorKey, $join, $forbiddenProjectionFields]) {
        $pdo = new RecordingPdo();
        $pdo->fetchAllResult = [['_parent_id' => 7, $cursorKey => 137]];
        $result = (new EmployeeRelationshipRepository($pdo))->{$method}($employeeCode, 100);

        assertSameValue(1, $pdo->prepareCalls, "{$method} executed more than one query.");
        assertTrue(str_contains((string) $pdo->query, $join), "{$method} used the wrong join.");
        assertTrue(str_contains((string) $pdo->query, 'LIMIT 101'), "{$method} did not fetch 101 rows.");
        assertSameValue(['value' => 'test1', 'type' => PDO::PARAM_STR], $pdo->boundValues[':employee_code'] ?? null, "{$method} did not bind gy_emp_code.");
        assertSameValue(['value' => 100, 'type' => PDO::PARAM_INT], $pdo->boundValues[':after_id'] ?? null, "{$method} did not bind its cursor.");
        assertSameValue([[$cursorKey => 137]], $result['data'], "{$method} exposed an internal marker.");
        $projection = substr((string) $pdo->query, 0, strpos((string) $pdo->query, ' FROM '));
        foreach ($forbiddenProjectionFields as $field) {
            assertTrue(!str_contains($projection, $field), "{$method} exposes internal user ID {$field}.");
        }
    }
});

test('schedule relationship routes are JWT protected and dispatch the confirmed joins', function (): void {
    $keys = testRsaKeys();
    $jwtService = new JwtService(
        'kronos-api',
        'kronos-api-clients',
        3600,
        $keys['private_path'],
        $keys['public_path']
    );
    $repositoryCalls = 0;
    $recordingPdos = [];
    $repositoryFactory = static function () use (&$repositoryCalls, &$recordingPdos): EmployeeRelationshipRepository {
        $repositoryCalls++;
        $pdo = new RecordingPdo();
        $pdo->fetchResult = ['_parent_id' => 7, '_related_id' => 11, 'gy_emp_id' => 7, 'gy_tracker_id' => 31, 'gy_user_code' => 'test1'];
        $pdo->fetchAllResult = [['_parent_id' => 7, 'gy_sched_id' => 21, 'gy_sched_esc_id' => 22, 'gy_rd_id' => 23]];
        $recordingPdos[] = $pdo;

        return new EmployeeRelationshipRepository($pdo);
    };
    $app = AppFactory::create();
    $routes = require __DIR__ . '/../routes/api.php';
    $routes(
        app: $app,
        serviceFactory: static fn (): ApiClientService => new ApiClientService(new RecordingPdo()),
        apiTokenServiceFactory: static fn (): ApiTokenService => new ApiTokenService(new RecordingPdo()),
        jwtServiceFactory: static fn (): JwtService => $jwtService,
        employeeRelationshipRepositoryFactory: $repositoryFactory
    );
    $app->addRoutingMiddleware();
    $cases = [
        ['/api/v1/employees/test1/schedules', 'FROM gy_employee e LEFT JOIN gy_schedule s'],
        ['/api/v1/employees/test1/schedule-escalations', 'LEFT JOIN gy_schedule_escalate s'],
        ['/api/v1/schedule-escalations/22/employee', 'FROM gy_schedule_escalate s LEFT JOIN gy_employee e'],
        ['/api/v1/users/test1/schedule-rd-requests', 'FROM gy_user u LEFT JOIN gy_schedule_rd_request r'],
        ['/api/v1/users/test1/created-schedules', 's.gy_sched_by = u.gy_user_id'],
        ['/api/v1/users/test1/submitted-schedule-escalations', 's.gy_req_by = u.gy_user_id'],
        ['/api/v1/users/test1/received-schedule-escalations', 's.gy_req_to = u.gy_user_id'],
        ['/api/v1/users/test1/supervised-schedule-escalations', 's.gy_sup = u.gy_user_id'],
        ['/api/v1/schedules/21/employee', 's.gy_emp_id = e.gy_emp_id'],
        ['/api/v1/schedules/21/created-by-user', 's.gy_sched_by = u.gy_user_id'],
        ['/api/v1/schedule-escalations/22/requested-by-user', 's.gy_req_by = u.gy_user_id'],
        ['/api/v1/schedule-escalations/22/requested-to-user', 's.gy_req_to = u.gy_user_id'],
        ['/api/v1/schedule-escalations/22/supervisor-user', 's.gy_sup = u.gy_user_id'],
        ['/api/v1/schedule-rd-requests/23/tracker', 'r.gy_tracker_id = t.gy_tracker_id'],
        ['/api/v1/schedule-rd-requests/23/user', 'r.gy_user_id = u.gy_user_id'],
    ];

    foreach ($cases as [$path]) {
        $response = $app->handle((new ServerRequestFactory())->createServerRequest('GET', $path));
        assertSameValue(401, $response->getStatusCode(), "{$path} was not JWT protected.");
    }
    assertSameValue(0, $repositoryCalls, 'Schedule relationship repository was created before JWT authorization.');

    $authorization = 'Bearer ' . $jwtService->issue(['id' => 7, 'client_name' => 'SiBS HRIS']);
    foreach ($cases as $index => [$path, $queryFragment]) {
        $request = (new ServerRequestFactory())->createServerRequest('GET', $path)
            ->withHeader('Authorization', $authorization);
        assertSameValue(200, $app->handle($request)->getStatusCode(), "Authorized {$path} failed.");
        assertTrue(str_contains((string) $recordingPdos[$index]->query, $queryFragment), "{$path} dispatched to the wrong join.");
    }
    assertSameValue(15, $repositoryCalls, 'Schedule relationship routes did not issue one query each.');
});

test('unsupported schedule relationship routes are not registered', function (): void {
    $keys = testRsaKeys();
    $jwtService = new JwtService(
        'kronos-api',
        'kronos-api-clients',
        3600,
        $keys['private_path'],
        $keys['public_path']
    );
    $app = AppFactory::create();
    $routes = require __DIR__ . '/../routes/api.php';
    $routes(
        app: $app,
        serviceFactory: static fn (): ApiClientService => new ApiClientService(new RecordingPdo()),
        apiTokenServiceFactory: static fn (): ApiTokenService => new ApiTokenService(new RecordingPdo()),
        jwtServiceFactory: static fn (): JwtService => $jwtService
    );
    $patterns = array_map(
        static fn ($route): string => $route->getPattern(),
        $app->getRouteCollector()->getRoutes()
    );

    foreach ([
        '/api/v1/schedule-rd-requests/{gy_rd_id}/approved-by-user',
        '/api/v1/schedules/{gy_sched_id}/schedule-escalations',
    ] as $unsupportedPattern) {
        assertTrue(
            !in_array($unsupportedPattern, $patterns, true),
            "Unsupported relationship route {$unsupportedPattern} is registered."
        );
    }
});

test('workforce relationship repository uses confirmed explicit prepared joins', function (): void {
    $singleCases = [
        ['findSupervisorUserByEmployeeCode', 'test1', ':employee_code', PDO::PARAM_STR, 'e.gy_emp_supervisor = u.gy_user_id'],
        ['findOperationsManagerUserByEmployeeCode', 'test1', ':employee_code', PDO::PARAM_STR, 'TRIM(e.gy_emp_om) = TRIM(u.gy_user_code)'],
        ['findEmployeeByTrackerId', 31, ':tracker_id', PDO::PARAM_INT, 'TRIM(t.gy_emp_code) = TRIM(e.gy_emp_code)'],
        ['findAccountByTrackerId', 31, ':tracker_id', PDO::PARAM_INT, 't.gy_account_id = a.gy_acc_id'],
        ['findOperationsManagerUserByTrackerId', 31, ':tracker_id', PDO::PARAM_INT, 't.gy_tracker_om = u.gy_user_id'],
        ['findTrackerByEscalationId', 32, ':escalation_id', PDO::PARAM_INT, 'x.gy_tracker_id = t.gy_tracker_id'],
        ['findSubmittedByUserByEscalationId', 32, ':escalation_id', PDO::PARAM_INT, 'x.gy_esc_by = u.gy_user_id'],
        ['findRecipientUserByEscalationId', 32, ':escalation_id', PDO::PARAM_INT, 'x.gy_esc_to = u.gy_user_id'],
        ['findSupervisorUserByEscalationId', 32, ':escalation_id', PDO::PARAM_INT, 'x.gy_sup = u.gy_user_id'],
    ];
    foreach ($singleCases as [$method, $identifier, $placeholder, $type, $join]) {
        $pdo = new RecordingPdo();
        $pdo->fetchResult = ['_parent_id' => 1, '_related_id' => 2, 'resource_code' => 'safe'];
        $result = (new WorkforceRelationshipRepository($pdo))->{$method}($identifier);

        assertSameValue(1, $pdo->prepareCalls, "{$method} executed more than one query.");
        assertTrue(str_contains((string) $pdo->query, $join), "{$method} used the wrong join.");
        assertTrue(!str_contains(strtoupper((string) $pdo->query), 'SELECT *'), "{$method} used SELECT *.");
        assertTrue(!str_contains((string) $pdo->query, 'gy_password'), "{$method} selected a password.");
        assertSameValue(['value' => $identifier, 'type' => $type], $pdo->boundValues[$placeholder] ?? null, "{$method} did not bind its identifier.");
        assertSameValue(['resource_code' => 'safe'], $result['data'], "{$method} exposed internal relationship IDs.");
    }

    $collectionCases = [
        ['findTrackersByEmployeeCode', 'test1', ':employee_code', PDO::PARAM_STR, 'gy_tracker_id', 'TRIM(t.gy_emp_code) = TRIM(e.gy_emp_code)'],
        ['findSupervisedEmployeesByUserCode', 'test1', ':employee_code', PDO::PARAM_STR, 'gy_emp_id', 'e.gy_emp_supervisor = u.gy_user_id'],
        ['findSubmittedEscalationsByUserCode', 'test1', ':employee_code', PDO::PARAM_STR, 'gy_esc_id', 'x.gy_esc_by = u.gy_user_id'],
        ['findReceivedEscalationsByUserCode', 'test1', ':employee_code', PDO::PARAM_STR, 'gy_esc_id', 'x.gy_esc_to = u.gy_user_id'],
        ['findSupervisedEscalationsByUserCode', 'test1', ':employee_code', PDO::PARAM_STR, 'gy_esc_id', 'x.gy_sup = u.gy_user_id'],
        ['findTrackersByAccountId', 12, ':account_id', PDO::PARAM_INT, 'gy_tracker_id', 't.gy_account_id = a.gy_acc_id'],
        ['findEscalationsByTrackerId', 31, ':tracker_id', PDO::PARAM_INT, 'gy_esc_id', 'x.gy_tracker_id = t.gy_tracker_id'],
    ];
    foreach ($collectionCases as [$method, $identifier, $placeholder, $type, $cursor, $join]) {
        $pdo = new RecordingPdo();
        $pdo->fetchAllResult = [['_parent_id' => 1, $cursor => 137]];
        $result = (new WorkforceRelationshipRepository($pdo))->{$method}($identifier, 100);

        assertSameValue(1, $pdo->prepareCalls, "{$method} executed more than one query.");
        assertTrue(str_contains((string) $pdo->query, $join), "{$method} used the wrong join.");
        assertTrue(str_contains((string) $pdo->query, 'LIMIT 101'), "{$method} did not fetch 101 rows.");
        assertSameValue(['value' => $identifier, 'type' => $type], $pdo->boundValues[$placeholder] ?? null, "{$method} did not bind its parent.");
        assertSameValue(['value' => 100, 'type' => PDO::PARAM_INT], $pdo->boundValues[':after_id'] ?? null, "{$method} did not bind its cursor.");
        assertSameValue([[$cursor => 137]], $result['data'], "{$method} exposed its parent marker.");
    }
});

test('all workforce relationship routes require JWT and dispatch once', function (): void {
    $keys = testRsaKeys();
    $jwtService = new JwtService('kronos-api', 'kronos-api-clients', 3600, $keys['private_path'], $keys['public_path']);
    $calls = 0;
    $pdos = [];
    $factory = static function () use (&$calls, &$pdos): WorkforceRelationshipRepository {
        $calls++;
        $pdo = new RecordingPdo();
        $pdo->fetchResult = ['_parent_id' => 1, '_related_id' => 2, 'gy_user_code' => 'test1'];
        $pdo->fetchAllResult = [['_parent_id' => 1, 'gy_tracker_id' => 31, 'gy_emp_id' => 7, 'gy_esc_id' => 32]];
        $pdos[] = $pdo;
        return new WorkforceRelationshipRepository($pdo);
    };
    $app = AppFactory::create();
    $routes = require __DIR__ . '/../routes/api.php';
    $routes(
        app: $app,
        serviceFactory: static fn (): ApiClientService => new ApiClientService(new RecordingPdo()),
        apiTokenServiceFactory: static fn (): ApiTokenService => new ApiTokenService(new RecordingPdo()),
        jwtServiceFactory: static fn (): JwtService => $jwtService,
        workforceRelationshipRepositoryFactory: $factory
    );
    $app->addRoutingMiddleware();
    $paths = [
        '/api/v1/employees/test1/supervisor-user',
        '/api/v1/employees/test1/operations-manager-user',
        '/api/v1/employees/test1/trackers',
        '/api/v1/users/test1/supervised-employees',
        '/api/v1/users/test1/submitted-escalations',
        '/api/v1/users/test1/received-escalations',
        '/api/v1/users/test1/supervised-escalations',
        '/api/v1/accounts/12/trackers',
        '/api/v1/trackers/31/employee',
        '/api/v1/trackers/31/account',
        '/api/v1/trackers/31/operations-manager-user',
        '/api/v1/trackers/31/escalations',
        '/api/v1/escalations/32/tracker',
        '/api/v1/escalations/32/submitted-by-user',
        '/api/v1/escalations/32/recipient-user',
        '/api/v1/escalations/32/supervisor-user',
    ];
    foreach ($paths as $path) {
        assertSameValue(401, $app->handle((new ServerRequestFactory())->createServerRequest('GET', $path))->getStatusCode(), "{$path} is not JWT protected.");
    }
    assertSameValue(0, $calls, 'Workforce repository ran before JWT authorization.');

    $authorization = 'Bearer ' . $jwtService->issue(['id' => 7, 'client_name' => 'SiBS HRIS']);
    foreach ($paths as $path) {
        $request = (new ServerRequestFactory())->createServerRequest('GET', $path)->withHeader('Authorization', $authorization);
        assertSameValue(200, $app->handle($request)->getStatusCode(), "Authorized {$path} failed.");
    }
    assertSameValue(16, $calls, 'Workforce routes did not dispatch exactly once each.');
});

test('individual resource repository uses one exact explicit prepared query per lookup', function (): void {
    $employeeFields = 'gy_emp_id, gy_emp_code, gy_emp_type, gy_emp_schedtype, gy_emp_rate, '
        . 'gy_emp_email, gy_emp_lname, gy_emp_fname, gy_emp_mname, gy_emp_fullname, gy_acc_id, '
        . 'gy_emp_account, gy_emp_supervisor, gy_emp_om, gy_emp_leave_credits, gy_emp_hiredate, '
        . 'gy_emp_lastedit, gy_lastedit_by, gy_work_from, gy_gender, gy_dob, gy_civilstatus, '
        . 'gy_assignedloc, gy_tagumdate, gy_davaodate, gy_hybriddate, gy_accjoin, gy_nhodate, '
        . 'gy_fststartdate, gy_fstenddate, gy_pststartdate, gy_pstenddate, gy_certification, '
        . 'gy_gradbaystartdate, gy_gradbayenddate, gy_fullgolivedate, gy_promotiondate, '
        . 'gy_projempdate, gy_probempdate, gy_regempdate, gy_last_working_day';
    $cases = [
        [
            'findEmployeeByCode',
            'test1',
            'SELECT ' . $employeeFields . ' FROM gy_employee '
                . 'WHERE TRIM(gy_emp_code) = TRIM(:employee_code) LIMIT 1',
            ':employee_code',
            PDO::PARAM_STR,
        ],
        [
            'findAccountById',
            12,
            'SELECT gy_acc_id, gy_acc_name, gy_acc_ghl_name, gy_dept_id, gy_acc_status '
                . 'FROM gy_accounts WHERE gy_acc_id = :account_id LIMIT 1',
            ':account_id',
            PDO::PARAM_INT,
        ],
        [
            'findDepartmentById',
            5,
            'SELECT id_department, name_department FROM gy_department '
                . 'WHERE id_department = :department_id LIMIT 1',
            ':department_id',
            PDO::PARAM_INT,
        ],
        [
            'findUserByEmployeeCode',
            'test1',
            'SELECT gy_user_id, gy_user_code, ghl_contact_id, gy_full_name, gy_username, '
                . 'gy_user_type, gy_user_function, gy_head_code, gy_script_code, gy_user_status '
                . 'FROM gy_user WHERE TRIM(gy_user_code) = TRIM(:employee_code) LIMIT 1',
            ':employee_code',
            PDO::PARAM_STR,
        ],
        [
            'findScheduleById',
            21,
            'SELECT gy_sched_id, gy_emp_id, gy_sched_day, gy_sched_mode, gy_sched_login, '
                . 'gy_sched_breakout, gy_sched_breakin, gy_sched_logout, gy_sched_reg, gy_sched_by '
                . 'FROM gy_schedule WHERE gy_sched_id = :schedule_id LIMIT 1',
            ':schedule_id',
            PDO::PARAM_INT,
        ],
        [
            'findScheduleEscalationById',
            22,
            'SELECT gy_sched_esc_id, gy_sched_esc_code, gy_req_date, gy_req_status, gy_req_deny, '
                . 'gy_req_by, gy_req_to, gy_sup, gy_emp_code, gy_emp_fullname, gy_sched_day, '
                . 'gy_sched_mode, gy_sched_login, gy_sched_breakout, gy_sched_breakin, gy_sched_logout, '
                . 'gy_tracker_login, gy_tracker_logout, gy_req_reason, gy_req_photodir, gy_publish, '
                . 'old_sched_mode, old_sched_login, old_sched_breakout, old_sched_breakin, '
                . 'old_sched_logout, old_tracker_login, old_tracker_logout, msg_usercode '
                . 'FROM gy_schedule_escalate WHERE gy_sched_esc_id = :schedule_escalation_id LIMIT 1',
            ':schedule_escalation_id',
            PDO::PARAM_INT,
        ],
        [
            'findScheduleRdRequestById',
            23,
            'SELECT gy_rd_id, gy_rd_date, gy_rd_status, gy_tracker_id, gy_user_id, gy_rd_approved_by '
                . 'FROM gy_schedule_rd_request WHERE gy_rd_id = :schedule_rd_request_id LIMIT 1',
            ':schedule_rd_request_id',
            PDO::PARAM_INT,
        ],
        [
            'findTrackerById',
            31,
            'SELECT gy_tracker_id, gy_tracker_code, gy_tracker_date, gy_emp_code, gy_emp_email, '
                . 'gy_emp_fullname, gy_account_id, gy_emp_account, gy_tracker_login, gy_tracker_breakout, '
                . 'gy_tracker_breakin, gy_tracker_logout, gy_tracker_wh, gy_tracker_bh, gy_tracker_ot, '
                . 'gy_tracker_ath, gy_tracker_status, gy_tracker_request, gy_tracker_reason, '
                . 'gy_tracker_history, gy_tracker_remarks, gy_tracker_loc '
                . 'FROM gy_tracker WHERE gy_tracker_id = :tracker_id LIMIT 1',
            ':tracker_id',
            PDO::PARAM_INT,
        ],
        [
            'findEscalationById',
            32,
            'SELECT gy_esc_id, gy_esc_type, gy_esc_reason, gy_esc_photodir, gy_esc_status, gy_esc_deny, '
                . 'gy_esc_date, gy_tracker_id, gy_tracker_date, '
                . 'gy_tracker_login, gy_tracker_breakout, gy_tracker_breakin, gy_tracker_logout, '
                . 'gy_tracker_wh, gy_tracker_bh, gy_tracker_ot, gy_publish, gy_usercode, '
                . 'old_tracker_date, old_tracker_login, old_tracker_breakout, old_tracker_breakin, '
                . 'old_tracker_logout, msg_usercode FROM gy_escalate '
                . 'WHERE gy_esc_id = :escalation_id LIMIT 1',
            ':escalation_id',
            PDO::PARAM_INT,
        ],
    ];

    foreach ($cases as [$method, $identifier, $expectedQuery, $placeholder, $type]) {
        $pdo = new RecordingPdo();
        $pdo->fetchResult = ['resource_id' => 99];
        $result = (new IndividualResourceRepository($pdo))->{$method}($identifier);

        assertSameValue(1, $pdo->prepareCalls, "{$method} executed more than one query.");
        assertSameValue($expectedQuery, $pdo->query, "{$method} changed its explicit field allowlist or lookup.");
        assertTrue(!str_contains(strtoupper((string) $pdo->query), 'SELECT *'), "{$method} used SELECT *.");
        assertSameValue(['value' => $identifier, 'type' => $type], $pdo->boundValues[$placeholder] ?? null, "{$method} did not safely bind its identifier.");
        assertSameValue(['resource_id' => 99], $result, "{$method} returned the wrong row.");
    }
});

test('individual resource controller returns one compact resource and normalizes employee codes', function (): void {
    $cases = [
        ['employee', ['gy_emp_code' => '  test1  '], ':employee_code', 'test1'],
        ['account', ['gy_acc_id' => '12'], ':account_id', 12],
        ['department', ['id_department' => '5'], ':department_id', 5],
        ['user', ['gy_emp_code' => '  test1  '], ':employee_code', 'test1'],
        ['schedule', ['gy_sched_id' => '21'], ':schedule_id', 21],
        ['scheduleEscalation', ['gy_sched_esc_id' => '22'], ':schedule_escalation_id', 22],
        ['scheduleRdRequest', ['gy_rd_id' => '23'], ':schedule_rd_request_id', 23],
        ['tracker', ['gy_tracker_id' => '31'], ':tracker_id', 31],
        ['escalation', ['gy_esc_id' => '32'], ':escalation_id', 32],
    ];

    foreach ($cases as [$method, $arguments, $placeholder, $expectedValue]) {
        $pdo = new RecordingPdo();
        $pdo->fetchResult = ['resource_id' => 99];
        $controller = new IndividualResourceController(
            static fn (): IndividualResourceRepository => new IndividualResourceRepository($pdo)
        );
        $response = $controller->{$method}(
            (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/resource/value'),
            (new ResponseFactory())->createResponse(),
            $arguments
        );

        assertSameValue(200, $response->getStatusCode(), "{$method} lookup returned the wrong status.");
        assertSameValue(['success' => true, 'data' => ['resource_id' => 99]], responseJson($response), "{$method} lookup returned the wrong payload.");
        assertSameValue($expectedValue, $pdo->boundValues[$placeholder]['value'] ?? null, "{$method} lookup did not normalize its identifier.");
        assertSameValue('private, no-store', $response->getHeaderLine('Cache-Control'), "{$method} response can be cached.");
    }
});

test('individual resource controller rejects invalid identifiers before creating a repository', function (): void {
    $cases = [
        ['employee', ['gy_emp_code' => '   ']],
        ['employee', ['gy_emp_code' => '123456789012']],
        ['account', ['gy_acc_id' => '0']],
        ['account', ['gy_acc_id' => 'not-an-id']],
        ['department', ['id_department' => '-1']],
        ['user', ['gy_emp_code' => '']],
        ['schedule', ['gy_sched_id' => '0']],
        ['scheduleEscalation', ['gy_sched_esc_id' => 'bad']],
        ['scheduleRdRequest', ['gy_rd_id' => '-2']],
        ['tracker', ['gy_tracker_id' => '0']],
        ['escalation', ['gy_esc_id' => 'bad']],
    ];

    foreach ($cases as [$method, $arguments]) {
        $repositoryCreated = false;
        $controller = new IndividualResourceController(
            static function () use (&$repositoryCreated): IndividualResourceRepository {
                $repositoryCreated = true;
                return new IndividualResourceRepository(new RecordingPdo());
            }
        );
        $response = $controller->{$method}(
            (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/resource/invalid'),
            (new ResponseFactory())->createResponse(),
            $arguments
        );

        assertSameValue(400, $response->getStatusCode(), "{$method} accepted an invalid identifier.");
        assertSameValue(['success' => false, 'message' => 'Invalid resource identifier.'], responseJson($response), "{$method} returned the wrong validation response.");
        assertSameValue(false, $repositoryCreated, "{$method} created a repository before validation.");
    }
});

test('individual resource controller returns sanitized 404 and logged 500 responses', function (): void {
    $notFoundPdo = new RecordingPdo();
    $notFoundPdo->fetchResult = false;
    $notFoundController = new IndividualResourceController(
        static fn (): IndividualResourceRepository => new IndividualResourceRepository($notFoundPdo)
    );
    $notFound = $notFoundController->employee(
        (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/employees/missing'),
        (new ResponseFactory())->createResponse(),
        ['gy_emp_code' => 'missing']
    );
    assertSameValue(404, $notFound->getStatusCode(), 'Missing individual resource did not return HTTP 404.');
    assertSameValue(['success' => false, 'message' => 'Resource not found.'], responseJson($notFound), 'Missing resource response is incorrect.');

    $failure = new RuntimeException('private individual lookup SQL');
    $logged = null;
    $failureController = new IndividualResourceController(
        static function () use ($failure): IndividualResourceRepository {
            throw $failure;
        },
        static function (Throwable $exception) use (&$logged): void {
            $logged = $exception;
        }
    );
    $serverError = $failureController->account(
        (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/accounts/12'),
        (new ResponseFactory())->createResponse(),
        ['gy_acc_id' => '12']
    );
    assertSameValue(500, $serverError->getStatusCode(), 'Individual lookup failure returned the wrong status.');
    assertSameValue($failure, $logged, 'Individual lookup failure was not logged.');
    assertSameValue(['success' => false, 'message' => 'Server error.'], responseJson($serverError), 'Individual lookup exposed failure details.');
});

test('all individual resource routes require JWT and dispatch one lookup each', function (): void {
    $keys = testRsaKeys();
    $jwtService = new JwtService(
        'kronos-api',
        'kronos-api-clients',
        3600,
        $keys['private_path'],
        $keys['public_path']
    );
    $repositoryCalls = 0;
    $recordingPdos = [];
    $repositoryFactory = static function () use (&$repositoryCalls, &$recordingPdos): IndividualResourceRepository {
        $repositoryCalls++;
        $pdo = new RecordingPdo();
        $pdo->fetchResult = ['resource_id' => 99];
        $recordingPdos[] = $pdo;
        return new IndividualResourceRepository($pdo);
    };
    $app = AppFactory::create();
    $routes = require __DIR__ . '/../routes/api.php';
    $routes(
        app: $app,
        serviceFactory: static fn (): ApiClientService => new ApiClientService(new RecordingPdo()),
        apiTokenServiceFactory: static fn (): ApiTokenService => new ApiTokenService(new RecordingPdo()),
        jwtServiceFactory: static fn (): JwtService => $jwtService,
        individualResourceRepositoryFactory: $repositoryFactory
    );
    $app->addRoutingMiddleware();
    $routesUnderTest = [
        ['/api/v1/employees/test1', 'FROM gy_employee'],
        ['/api/v1/accounts/12', 'FROM gy_accounts'],
        ['/api/v1/departments/5', 'FROM gy_department'],
        ['/api/v1/users/test1', 'FROM gy_user'],
        ['/api/v1/schedule/21', 'FROM gy_schedule'],
        ['/api/v1/schedule-escalations/22', 'FROM gy_schedule_escalate'],
        ['/api/v1/schedule-rd-requests/23', 'FROM gy_schedule_rd_request'],
        ['/api/v1/trackers/31', 'FROM gy_tracker'],
        ['/api/v1/escalations/32', 'FROM gy_escalate'],
    ];

    foreach ($routesUnderTest as [$path]) {
        $response = $app->handle((new ServerRequestFactory())->createServerRequest('GET', $path));
        assertSameValue(401, $response->getStatusCode(), "{$path} was not JWT protected.");
    }
    assertSameValue(0, $repositoryCalls, 'Individual repository was created before JWT authorization.');

    $authorization = 'Bearer ' . $jwtService->issue(['id' => 7, 'client_name' => 'SiBS HRIS']);
    foreach ($routesUnderTest as $index => [$path, $queryFragment]) {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', $path)
            ->withHeader('Authorization', $authorization);
        $response = $app->handle($request);
        assertSameValue(200, $response->getStatusCode(), "Authorized {$path} request failed.");
        assertTrue(str_contains((string) ($recordingPdos[$index]->query ?? ''), $queryFragment), "{$path} dispatched to the wrong lookup.");
    }
    assertSameValue(9, $repositoryCalls, 'Individual routes did not create exactly one repository each.');
});

test('employee schedule repository runs one count and one explicit paginated query', function (): void {
    $pdo = new EmployeeDirectoryRecordingPdo();
    $pdo->fetchResults[0] = ['total' => 16];
    $pdo->fetchAllResults[1] = [['gy_sched_id' => 21, 'gy_emp_id' => 7]];
    $repository = new EmployeeScheduleRepository($pdo);

    $result = $repository->findPage(7, 2, 15, 'night', '2026-09-01', '2026-09-30');

    assertSameValue(2, count($pdo->queries), 'Employee schedules must execute exactly two queries.');
    foreach ($pdo->queries as $query) {
        assertTrue(str_contains($query, 'FROM gy_schedule WHERE gy_emp_id = :employee_id'), 'Employee schedule filter is missing.');
        assertTrue(str_contains($query, 'DATE(gy_sched_day) >= :date_from'), 'Start-date filter is missing.');
        assertTrue(str_contains($query, 'DATE(gy_sched_day) <= :date_to'), 'End-date filter is missing.');
        assertTrue(str_contains($query, "DATE_FORMAT(gy_sched_day, '%M %e, %Y') LIKE :search_1"), 'Formatted date search is missing.');
        assertTrue(!str_contains(strtoupper($query), 'SELECT *'), 'Employee schedules used SELECT *.');
    }
    assertTrue(str_starts_with($pdo->queries[0], 'SELECT COUNT(*) AS total '), 'Employee schedule count query is incorrect.');
    assertTrue(str_starts_with($pdo->queries[1], 'SELECT gy_sched_id, gy_emp_id, gy_sched_day'), 'Employee schedule projection is not explicit.');
    assertTrue(str_contains($pdo->queries[1], 'ORDER BY gy_sched_day DESC, gy_sched_id DESC LIMIT :limit OFFSET :offset'), 'Employee schedule ordering or pagination is incorrect.');
    assertSameValue(['value' => 7, 'type' => PDO::PARAM_INT], $pdo->bindings[0][':employee_id'] ?? null, 'Employee ID was not bound safely.');
    assertSameValue(['value' => '%night%', 'type' => PDO::PARAM_STR], $pdo->bindings[1][':search_0'] ?? null, 'Search was not bound safely.');
    assertSameValue(['value' => 15, 'type' => PDO::PARAM_INT], $pdo->bindings[1][':limit'] ?? null, 'Limit was not bound safely.');
    assertSameValue(['value' => 15, 'type' => PDO::PARAM_INT], $pdo->bindings[1][':offset'] ?? null, 'Offset was not calculated correctly.');
    assertSameValue(['data' => $pdo->fetchAllResults[1], 'total' => 16], $result, 'Employee schedule page is incorrect.');
});

test('employee schedule controller validates filters and returns the required pagination shape', function (): void {
    $pdo = new EmployeeDirectoryRecordingPdo();
    $pdo->fetchResults[0] = ['total' => 16];
    $pdo->fetchAllResults[1] = [['gy_sched_id' => 21, 'gy_emp_id' => 7]];
    $controller = new EmployeeScheduleController(
        static fn (): EmployeeScheduleRepository => new EmployeeScheduleRepository($pdo)
    );
    $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/employee-schedules')
        ->withQueryParams(['gy_emp_id' => '7']);
    $response = $controller->index($request, (new ResponseFactory())->createResponse());

    assertSameValue(200, $response->getStatusCode(), 'Employee schedule view returned the wrong status.');
    assertSameValue([
        'success' => true,
        'data' => $pdo->fetchAllResults[1],
        'filters' => ['search' => '', 'dateFrom' => '', 'dateTo' => ''],
        'pagination' => [
            'currentPage' => 1,
            'totalPages' => 2,
            'totalRecords' => 16,
            'total' => 16,
            'limit' => 15,
            'hasPreviousPage' => false,
            'hasNextPage' => true,
        ],
    ], responseJson($response), 'Employee schedule response shape is incorrect.');

    foreach ([
        [],
        ['gy_emp_id' => '0'],
        ['gy_emp_id' => 'bad'],
        ['gy_emp_id' => '7', 'date_from' => '2026-02-30'],
        ['gy_emp_id' => '7', 'date_to' => '09/30/2026'],
    ] as $query) {
        $repositoryCreated = false;
        $invalidController = new EmployeeScheduleController(
            static function () use (&$repositoryCreated): EmployeeScheduleRepository {
                $repositoryCreated = true;
                return new EmployeeScheduleRepository(new EmployeeDirectoryRecordingPdo());
            }
        );
        $invalidRequest = (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/employee-schedules')
            ->withQueryParams($query);
        $invalid = $invalidController->index($invalidRequest, (new ResponseFactory())->createResponse());

        assertSameValue(400, $invalid->getStatusCode(), 'Invalid employee schedule filters were accepted.');
        assertSameValue(['success' => false, 'message' => 'Invalid query parameters.'], responseJson($invalid), 'Validation details were exposed.');
        assertSameValue(false, $repositoryCreated, 'Repository was created before employee schedule validation.');
    }
});

test('employee schedules route requires JWT before running its two queries', function (): void {
    $keys = testRsaKeys();
    $jwtService = new JwtService('kronos-api', 'kronos-api-clients', 3600, $keys['private_path'], $keys['public_path']);
    $pdo = new EmployeeDirectoryRecordingPdo();
    $pdo->fetchResults[0] = ['total' => 0];
    $repositoryCalls = 0;
    $factory = static function () use (&$repositoryCalls, $pdo): EmployeeScheduleRepository {
        $repositoryCalls++;
        return new EmployeeScheduleRepository($pdo);
    };
    $app = AppFactory::create();
    $routes = require __DIR__ . '/../routes/api.php';
    $routes(
        app: $app,
        serviceFactory: static fn (): ApiClientService => new ApiClientService(new RecordingPdo()),
        apiTokenServiceFactory: static fn (): ApiTokenService => new ApiTokenService(new RecordingPdo()),
        jwtServiceFactory: static fn (): JwtService => $jwtService,
        employeeScheduleRepositoryFactory: $factory
    );
    $app->addRoutingMiddleware();
    $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/employee-schedules?gy_emp_id=7');

    assertSameValue(401, $app->handle($request)->getStatusCode(), 'Employee schedules route is not JWT protected.');
    assertSameValue(0, $repositoryCalls, 'Employee schedule repository ran before authorization.');

    $authorized = $request->withHeader('Authorization', 'Bearer ' . $jwtService->issue(['id' => 7, 'client_name' => 'SiBS HRIS']));
    assertSameValue(200, $app->handle($authorized)->getStatusCode(), 'Employee schedules rejected a valid JWT.');
    assertSameValue(1, $repositoryCalls, 'Employee schedules did not create one repository.');
    assertSameValue(2, count($pdo->queries), 'Employee schedules did not run exactly two queries.');
});

test('workforce optimized views use complete joins and exactly two prepared queries', function (): void {
    $cases = [
        [
            'findEmployeeAttendancePage',
            ['gy_emp_code' => 'test1', 'account_id' => 12, 'department_id' => 5, 'date_from' => '2026-09-01', 'date_to' => '2026-09-30', 'search' => 'late'],
            ['JOIN gy_employee e', 'JOIN gy_accounts a', 'JOIN gy_department d'],
            ['TRIM(e.gy_emp_code) = :gy_emp_code', 'a.gy_acc_id = :account_id', 'a.gy_dept_id = :department_id'],
        ],
        [
            'findEmployeeTrackerHistoryPage',
            ['gy_emp_code' => 'test1', 'account_id' => 12, 'date_from' => '2026-09-01', 'date_to' => '2026-09-30', 'search' => 'late'],
            ['JOIN gy_employee e', 'JOIN gy_accounts a'],
            ['TRIM(e.gy_emp_code) = :gy_emp_code', 'a.gy_acc_id = :account_id'],
        ],
        [
            'findEscalationQueuePage',
            ['status' => 0, 'gy_emp_code' => 'test1', 'account_id' => 12, 'submitted_by' => 'lead1', 'recipient' => 'lead2', 'date_from' => '2026-09-01', 'date_to' => '2026-09-30', 'search' => 'late'],
            ['JOIN gy_tracker t', 'JOIN gy_user submittedBy', 'JOIN gy_user recipient', 'JOIN gy_user supervisor', 'JOIN gy_employee e', 'JOIN gy_accounts a'],
            ['x.gy_esc_status = :status', 'TRIM(submittedBy.gy_user_code) = :submitted_by', 'TRIM(recipient.gy_user_code) = :recipient'],
        ],
    ];

    foreach ($cases as [$method, $filters, $joins, $conditions]) {
        $pdo = new EmployeeDirectoryRecordingPdo();
        $pdo->fetchResults[0] = ['total' => 17];
        $pdo->fetchAllResults[1] = [['record_id' => 1]];
        $result = (new WorkforceViewRepository($pdo))->{$method}(2, 15, $filters);

        assertSameValue(2, count($pdo->queries), "{$method} did not execute exactly two queries.");
        assertTrue(str_starts_with($pdo->queries[0], 'SELECT COUNT(*) AS total '), "{$method} count query is incorrect.");
        assertTrue(!str_contains(strtoupper($pdo->queries[1]), 'SELECT *'), "{$method} used SELECT *.");
        foreach ($joins as $join) {
            assertTrue(str_contains($pdo->queries[1], $join), "{$method} omitted {$join}.");
        }
        foreach ($conditions as $condition) {
            assertTrue(str_contains($pdo->queries[1], $condition), "{$method} omitted filter {$condition}.");
        }
        assertTrue(!str_contains($pdo->queries[1], 'DATE(t.gy_tracker_date)'), "{$method} wraps the tracker date column in a function.");
        assertTrue(!str_contains($pdo->queries[1], 'DATE(x.gy_esc_date)'), "{$method} wraps the escalation date column in a function.");
        assertTrue(str_contains($pdo->queries[1], 'LIMIT :limit OFFSET :offset'), "{$method} omitted server-side pagination.");
        assertSameValue(['value' => 15, 'type' => PDO::PARAM_INT], $pdo->bindings[1][':limit'] ?? null, "{$method} did not bind its limit.");
        assertSameValue(['value' => 15, 'type' => PDO::PARAM_INT], $pdo->bindings[1][':offset'] ?? null, "{$method} calculated the wrong offset.");
        assertSameValue(['data' => [['record_id' => 1]], 'total' => 17], $result, "{$method} returned the wrong page.");
    }
});

test('Batch 1 projections never expose join-only user IDs', function (): void {
    $relationshipCases = [
        ['findSupervisedEmployeesByUserCode', ['test1', 0], ['e.gy_emp_supervisor']],
        ['findTrackersByEmployeeCode', ['test1', 0], ['t.gy_tracker_om']],
        ['findSubmittedEscalationsByUserCode', ['test1', 0], ['x.gy_esc_by', 'x.gy_esc_to', 'x.gy_sup']],
    ];
    foreach ($relationshipCases as [$method, $arguments, $forbidden]) {
        $pdo = new RecordingPdo();
        $pdo->fetchAllResult = [['_parent_id' => 1]];
        (new WorkforceRelationshipRepository($pdo))->{$method}(...$arguments);
        $projection = substr((string) $pdo->query, 0, strpos((string) $pdo->query, ' FROM '));
        foreach ($forbidden as $field) {
            assertTrue(!str_contains($projection, $field), "{$method} exposes {$field}.");
        }
    }

    $viewPdo = new EmployeeDirectoryRecordingPdo();
    $viewPdo->fetchResults[0] = ['total' => 0];
    $views = new WorkforceViewRepository($viewPdo);
    $views->findEmployeeAttendancePage(1, 15, []);
    $attendanceProjection = substr($viewPdo->queries[1], 0, strpos($viewPdo->queries[1], ' FROM '));
    assertTrue(!str_contains($attendanceProjection, 'employee_supervisor_id'), 'Attendance exposes the supervisor user ID.');
    assertTrue(!str_contains($attendanceProjection, 't.gy_tracker_om'), 'Attendance exposes the tracker manager user ID.');

    $queuePdo = new EmployeeDirectoryRecordingPdo();
    $queuePdo->fetchResults[0] = ['total' => 0];
    (new WorkforceViewRepository($queuePdo))->findEscalationQueuePage(1, 15, []);
    $queueProjection = substr($queuePdo->queries[1], 0, strpos($queuePdo->queries[1], ' FROM '));
    foreach (['x.gy_esc_by', 'x.gy_esc_to', 'x.gy_sup', 't.gy_tracker_om', 'employee_supervisor_id'] as $field) {
        assertTrue(!str_contains($queueProjection, $field), "Escalation queue exposes {$field}.");
    }
    assertTrue(str_contains($queueProjection, 'submittedBy.gy_user_code AS submitted_by_user_code'), 'Escalation queue omitted public submitter identity.');
    assertTrue(str_contains($queueProjection, 'recipient.gy_user_code AS recipient_user_code'), 'Escalation queue omitted public recipient identity.');
    assertTrue(str_contains($queueProjection, 'supervisor.gy_user_code AS supervisor_user_code'), 'Escalation queue omitted public supervisor identity.');
});

test('workforce optimized view controller validates filters and returns numbered pagination', function (): void {
    $pdo = new EmployeeDirectoryRecordingPdo();
    $pdo->fetchResults[0] = ['total' => 16];
    $pdo->fetchAllResults[1] = [['gy_tracker_id' => 31]];
    $controller = new WorkforceViewController(static fn (): WorkforceViewRepository => new WorkforceViewRepository($pdo));
    $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/employee-attendance')
        ->withQueryParams(['page' => '1', 'limit' => '15', 'gy_emp_code' => ' test1 ']);
    $response = $controller->employeeAttendance($request, (new ResponseFactory())->createResponse());
    $payload = responseJson($response);

    assertSameValue(200, $response->getStatusCode(), 'Employee attendance returned the wrong status.');
    assertSameValue(16, $payload['pagination']['totalRecords'], 'Workforce view total is incorrect.');
    assertSameValue(2, $payload['pagination']['totalPages'], 'Workforce view page count is incorrect.');
    assertSameValue(true, $payload['pagination']['hasNextPage'], 'Workforce view next-page flag is incorrect.');
    assertSameValue('test1', $payload['filters']['gyEmpCode'], 'Employee code filter was not normalized.');

    foreach ([
        ['account_id' => 'bad'],
        ['gy_emp_code' => '123456789012'],
        ['date_from' => '2026-02-30'],
        ['status' => '-1'],
        ['submitted_by' => ['bad']],
    ] as $query) {
        $created = false;
        $invalidController = new WorkforceViewController(static function () use (&$created): WorkforceViewRepository {
            $created = true;
            return new WorkforceViewRepository(new EmployeeDirectoryRecordingPdo());
        });
        $invalidRequest = (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/escalation-queue')->withQueryParams($query);
        $invalid = $invalidController->escalationQueue($invalidRequest, (new ResponseFactory())->createResponse());
        assertSameValue(400, $invalid->getStatusCode(), 'Invalid optimized-view filter was accepted.');
        assertSameValue(false, $created, 'Optimized-view repository ran before validation.');
    }
});

test('all workforce optimized view routes require JWT', function (): void {
    $keys = testRsaKeys();
    $jwtService = new JwtService('kronos-api', 'kronos-api-clients', 3600, $keys['private_path'], $keys['public_path']);
    $calls = 0;
    $factory = static function () use (&$calls): WorkforceViewRepository {
        $calls++;
        $pdo = new EmployeeDirectoryRecordingPdo();
        $pdo->fetchResults[0] = ['total' => 0];
        return new WorkforceViewRepository($pdo);
    };
    $app = AppFactory::create();
    $routes = require __DIR__ . '/../routes/api.php';
    $routes(
        app: $app,
        serviceFactory: static fn (): ApiClientService => new ApiClientService(new RecordingPdo()),
        apiTokenServiceFactory: static fn (): ApiTokenService => new ApiTokenService(new RecordingPdo()),
        jwtServiceFactory: static fn (): JwtService => $jwtService,
        workforceViewRepositoryFactory: $factory
    );
    $app->addRoutingMiddleware();
    $paths = ['/api/v1/employee-attendance', '/api/v1/employee-tracker-history', '/api/v1/escalation-queue'];
    foreach ($paths as $path) {
        assertSameValue(401, $app->handle((new ServerRequestFactory())->createServerRequest('GET', $path))->getStatusCode(), "{$path} is not JWT protected.");
    }
    assertSameValue(0, $calls, 'Workforce view repository ran before authorization.');
    $auth = 'Bearer ' . $jwtService->issue(['id' => 7, 'client_name' => 'SiBS HRIS']);
    foreach ($paths as $path) {
        $request = (new ServerRequestFactory())->createServerRequest('GET', $path)->withHeader('Authorization', $auth);
        assertSameValue(200, $app->handle($request)->getStatusCode(), "Authorized {$path} failed.");
    }
    assertSameValue(3, $calls, 'Workforce views did not dispatch once each.');
});

test('Batch 3 individual leave resources use one explicit prepared lookup', function (): void {
    $cases = [
        ['findLeaveById', 41, ':leave_id', 'SELECT gy_leave_id, gy_acc_id, gy_leave_filed, gy_leave_type, gy_leave_paid, gy_leave_day, gy_leave_period_one, gy_leave_period_two, gy_emp_rate, gy_old_credits, gy_new_credits, gy_leave_date_from, gy_leave_date_to, gy_leave_reason, gy_leave_status, gy_leave_date_approved, gy_leave_remarks, gy_leave_attachment, gy_publish, msg_usercode FROM gy_leave WHERE gy_leave_id = :leave_id LIMIT 1'],
        ['findLeaveAvailabilityById', 42, ':leave_availability_id', 'SELECT gy_leave_avail_id, gy_leave_avail_date, gy_leave_avail_dateto, gy_leave_avail_plotted, gy_leave_avail_approved, gy_leave_avail_justify, gy_acc_id FROM gy_leave_available WHERE gy_leave_avail_id = :leave_availability_id LIMIT 1'],
        ['findLeaveCreditHistoryById', 43, ':leave_credit_history_id', 'SELECT lch_id, lch_emp_code, lch_date, lch_old_credits, lch_new_credits, lch_type, lch_trigger_date_type, lch_trigger_amount, lch_trigger_affected_type, lch_updated_by, lch_daterecorded, lch_operation FROM leave_credits_history WHERE lch_id = :leave_credit_history_id LIMIT 1'],
    ];

    foreach ($cases as [$method, $id, $placeholder, $expectedQuery]) {
        $pdo = new RecordingPdo();
        $pdo->fetchResult = ['resource_id' => $id];
        $result = (new IndividualResourceRepository($pdo))->{$method}($id);
        assertSameValue($expectedQuery, $pdo->query, "{$method} used the wrong query.");
        assertSameValue(['value' => $id, 'type' => PDO::PARAM_INT], $pdo->boundValues[$placeholder] ?? null, "{$method} did not bind its identifier.");
        assertSameValue(['resource_id' => $id], $result, "{$method} returned the wrong record.");
    }
});

test('Batch 3 individual leave routes are unique and JWT protected', function (): void {
    $keys = testRsaKeys();
    $jwtService = new JwtService('kronos-api', 'kronos-api-clients', 3600, $keys['private_path'], $keys['public_path']);
    $calls = 0;
    $factory = static function () use (&$calls): IndividualResourceRepository {
        $calls++;
        $pdo = new RecordingPdo();
        $pdo->fetchResult = ['resource_id' => 1];
        return new IndividualResourceRepository($pdo);
    };
    $app = AppFactory::create();
    $routes = require __DIR__ . '/../routes/api.php';
    $routes(
        app: $app,
        serviceFactory: static fn (): ApiClientService => new ApiClientService(new RecordingPdo()),
        apiTokenServiceFactory: static fn (): ApiTokenService => new ApiTokenService(new RecordingPdo()),
        jwtServiceFactory: static fn (): JwtService => $jwtService,
        individualResourceRepositoryFactory: $factory
    );
    $app->addRoutingMiddleware();
    $paths = [
        '/api/v1/leaves/41',
        '/api/v1/leave-availability/42',
        '/api/v1/leave-credit-history/43',
    ];
    foreach ($paths as $path) {
        assertSameValue(401, $app->handle((new ServerRequestFactory())->createServerRequest('GET', $path))->getStatusCode(), "{$path} is not JWT protected.");
    }
    assertSameValue(0, $calls, 'Individual leave repository ran before authorization.');

    $auth = 'Bearer ' . $jwtService->issue(['id' => 7, 'client_name' => 'SiBS HRIS']);
    foreach ($paths as $path) {
        $request = (new ServerRequestFactory())->createServerRequest('GET', $path)->withHeader('Authorization', $auth);
        assertSameValue(200, $app->handle($request)->getStatusCode(), "Authorized {$path} failed.");
    }
    assertSameValue(3, $calls, 'Individual leave routes did not dispatch once each.');
});

test('Batch 3 leave relationships use one explicit prepared join per request', function (): void {
    $singleCases = [
        ['findUserByLeaveId', 41, ':leave_id', PDO::PARAM_INT, 'l.gy_user_id = u.gy_user_id'],
        ['findAccountByLeaveId', 41, ':leave_id', PDO::PARAM_INT, 'l.gy_acc_id = a.gy_acc_id'],
        ['findApproverUserByLeaveId', 41, ':leave_id', PDO::PARAM_INT, 'l.gy_leave_approver = u.gy_user_id'],
        ['findUserByLeaveAvailabilityId', 42, ':leave_availability_id', PDO::PARAM_INT, 'v.gy_user_id = u.gy_user_id'],
        ['findAccountByLeaveAvailabilityId', 42, ':leave_availability_id', PDO::PARAM_INT, 'v.gy_acc_id = a.gy_acc_id'],
        ['findEmployeeByLeaveCreditHistoryId', 43, ':leave_credit_history_id', PDO::PARAM_INT, 'TRIM(h.lch_emp_code) = TRIM(e.gy_emp_code)'],
        ['findUpdatedByUserByLeaveCreditHistoryId', 43, ':leave_credit_history_id', PDO::PARAM_INT, 'TRIM(h.lch_updated_by) = TRIM(u.gy_user_code)'],
    ];
    foreach ($singleCases as [$method, $id, $placeholder, $type, $join]) {
        $pdo = new RecordingPdo();
        $pdo->fetchResult = ['_parent_id' => $id, '_related_id' => 7, 'public_code' => 'safe'];
        $result = (new LeaveRelationshipRepository($pdo))->{$method}($id);
        assertSameValue(1, $pdo->prepareCalls, "{$method} executed more than one query.");
        assertTrue(str_contains((string) $pdo->query, $join), "{$method} used the wrong join.");
        assertTrue(!str_contains(strtoupper((string) $pdo->query), 'SELECT *'), "{$method} used SELECT *.");
        assertTrue(!str_contains((string) $pdo->query, 'gy_password'), "{$method} selected a password.");
        assertSameValue(['value' => $id, 'type' => $type], $pdo->boundValues[$placeholder] ?? null, "{$method} did not bind its identifier.");
        assertSameValue(['public_code' => 'safe'], $result['data'], "{$method} exposed an internal relationship ID.");
    }

    $collectionCases = [
        ['findLeaveCreditHistoryByEmployeeCode', 'test1', ':employee_code', PDO::PARAM_STR, [], 'lch_id', 'TRIM(h.lch_emp_code) = TRIM(e.gy_emp_code)'],
        ['findLeavesByUserCode', 'test1', ':employee_code', PDO::PARAM_STR, ['l.gy_user_id', 'l.gy_leave_approver'], 'gy_leave_id', 'l.gy_user_id = u.gy_user_id'],
        ['findApprovedLeavesByUserCode', 'test1', ':employee_code', PDO::PARAM_STR, ['l.gy_user_id', 'l.gy_leave_approver'], 'gy_leave_id', 'l.gy_leave_approver = u.gy_user_id'],
        ['findLeaveAvailabilityByUserCode', 'test1', ':employee_code', PDO::PARAM_STR, ['v.gy_user_id'], 'gy_leave_avail_id', 'v.gy_user_id = u.gy_user_id'],
        ['findLeaveCreditHistoryUpdatedByUserCode', 'test1', ':employee_code', PDO::PARAM_STR, [], 'lch_id', 'TRIM(h.lch_updated_by) = TRIM(u.gy_user_code)'],
        ['findLeavesByAccountId', 12, ':account_id', PDO::PARAM_INT, ['l.gy_user_id', 'l.gy_leave_approver'], 'gy_leave_id', 'l.gy_acc_id = a.gy_acc_id'],
        ['findLeaveAvailabilityByAccountId', 12, ':account_id', PDO::PARAM_INT, ['v.gy_user_id'], 'gy_leave_avail_id', 'v.gy_acc_id = a.gy_acc_id'],
    ];
    foreach ($collectionCases as [$method, $parent, $placeholder, $type, $internalFields, $cursor, $join]) {
        $pdo = new RecordingPdo();
        $pdo->fetchAllResult = [['_parent_id' => 7, $cursor => 137]];
        $result = (new LeaveRelationshipRepository($pdo))->{$method}($parent, 100);
        assertSameValue(1, $pdo->prepareCalls, "{$method} executed more than one query.");
        assertTrue(str_contains((string) $pdo->query, $join), "{$method} used the wrong join.");
        assertTrue(str_contains((string) $pdo->query, 'LIMIT 101'), "{$method} did not fetch 101 rows.");
        assertSameValue(['value' => $parent, 'type' => $type], $pdo->boundValues[$placeholder] ?? null, "{$method} did not bind its public parent identifier.");
        assertSameValue(['value' => 100, 'type' => PDO::PARAM_INT], $pdo->boundValues[':after_id'] ?? null, "{$method} did not bind its cursor.");
        $projection = substr((string) $pdo->query, 0, strpos((string) $pdo->query, ' FROM '));
        foreach ($internalFields as $internalField) {
            assertTrue(!str_contains($projection, $internalField), "{$method} exposes internal relationship field {$internalField}.");
        }
        assertSameValue([[$cursor => 137]], $result['data'], "{$method} exposed an internal marker.");
    }
});

test('Batch 3 leave relationship routes require JWT and dispatch the confirmed joins', function (): void {
    $keys = testRsaKeys();
    $jwtService = new JwtService('kronos-api', 'kronos-api-clients', 3600, $keys['private_path'], $keys['public_path']);
    $calls = 0;
    $pdos = [];
    $factory = static function () use (&$calls, &$pdos): LeaveRelationshipRepository {
        $calls++;
        $pdo = new RecordingPdo();
        $pdo->fetchResult = ['_parent_id' => 1, '_related_id' => 2, 'public_code' => 'safe'];
        $pdo->fetchAllResult = [['_parent_id' => 1, 'gy_leave_id' => 41, 'gy_leave_avail_id' => 42, 'lch_id' => 43]];
        $pdos[] = $pdo;
        return new LeaveRelationshipRepository($pdo);
    };
    $app = AppFactory::create();
    $routes = require __DIR__ . '/../routes/api.php';
    $routes(
        app: $app,
        serviceFactory: static fn (): ApiClientService => new ApiClientService(new RecordingPdo()),
        apiTokenServiceFactory: static fn (): ApiTokenService => new ApiTokenService(new RecordingPdo()),
        jwtServiceFactory: static fn (): JwtService => $jwtService,
        leaveRelationshipRepositoryFactory: $factory
    );
    $app->addRoutingMiddleware();
    $cases = [
        ['/api/v1/employees/test1/leave-credit-history', 'TRIM(h.lch_emp_code) = TRIM(e.gy_emp_code)'],
        ['/api/v1/users/test1/leaves', 'l.gy_user_id = u.gy_user_id'],
        ['/api/v1/users/test1/approved-leaves', 'l.gy_leave_approver = u.gy_user_id'],
        ['/api/v1/users/test1/leave-availability', 'v.gy_user_id = u.gy_user_id'],
        ['/api/v1/users/test1/leave-credit-history-updated', 'TRIM(h.lch_updated_by) = TRIM(u.gy_user_code)'],
        ['/api/v1/accounts/12/leaves', 'l.gy_acc_id = a.gy_acc_id'],
        ['/api/v1/accounts/12/leave-availability', 'v.gy_acc_id = a.gy_acc_id'],
        ['/api/v1/leaves/41/user', 'l.gy_user_id = u.gy_user_id'],
        ['/api/v1/leaves/41/account', 'l.gy_acc_id = a.gy_acc_id'],
        ['/api/v1/leaves/41/approver-user', 'l.gy_leave_approver = u.gy_user_id'],
        ['/api/v1/leave-availability/42/user', 'v.gy_user_id = u.gy_user_id'],
        ['/api/v1/leave-availability/42/account', 'v.gy_acc_id = a.gy_acc_id'],
        ['/api/v1/leave-credit-history/43/employee', 'TRIM(h.lch_emp_code) = TRIM(e.gy_emp_code)'],
        ['/api/v1/leave-credit-history/43/updated-by-user', 'TRIM(h.lch_updated_by) = TRIM(u.gy_user_code)'],
    ];
    foreach ($cases as [$path]) {
        assertSameValue(401, $app->handle((new ServerRequestFactory())->createServerRequest('GET', $path))->getStatusCode(), "{$path} is not JWT protected.");
    }
    assertSameValue(0, $calls, 'Leave relationship repository ran before authorization.');
    $auth = 'Bearer ' . $jwtService->issue(['id' => 7, 'client_name' => 'SiBS HRIS']);
    foreach ($cases as $index => [$path, $join]) {
        $request = (new ServerRequestFactory())->createServerRequest('GET', $path)->withHeader('Authorization', $auth);
        assertSameValue(200, $app->handle($request)->getStatusCode(), "Authorized {$path} failed.");
        assertTrue(str_contains((string) $pdos[$index]->query, $join), "{$path} dispatched to the wrong join.");
    }
    assertSameValue(14, $calls, 'Leave relationship routes did not dispatch once each.');
});

test('Batch 3 optimized leave views use complete joins and exactly two prepared queries', function (): void {
    $cases = [
        [
            'findEmployeeLeavesPage',
            ['gy_emp_code' => 'test1', 'status' => 0, 'leave_type' => 'VL', 'date_from' => '2026-10-01', 'date_to' => '2026-10-31', 'search' => 'annual'],
            ['JOIN gy_user u', 'LEFT JOIN gy_employee e', 'LEFT JOIN gy_accounts a', 'LEFT JOIN gy_user approver'],
            ['TRIM(u.gy_user_code) = :gy_emp_code', 'l.gy_leave_status = :status', 'l.gy_leave_type = :leave_type'],
            false,
        ],
        [
            'findLeaveManagementPage',
            ['gy_emp_code' => 'test1', 'account_id' => 12, 'department_id' => 5, 'status' => 0, 'leave_type' => 'VL', 'date_from' => '2026-10-01', 'date_to' => '2026-10-31', 'search' => 'annual'],
            ['JOIN gy_user u', 'LEFT JOIN gy_employee e', 'LEFT JOIN gy_accounts a', 'LEFT JOIN gy_user approver', 'LEFT JOIN gy_department d'],
            ['a.gy_acc_id = :account_id', 'a.gy_dept_id = :department_id'],
            true,
        ],
    ];
    foreach ($cases as [$method, $filters, $joins, $conditions, $department]) {
        $pdo = new EmployeeDirectoryRecordingPdo();
        $pdo->fetchResults[0] = ['total' => 17];
        $pdo->fetchAllResults[1] = [['leave_id' => 41]];
        $result = (new LeaveViewRepository($pdo))->{$method}(2, 15, $filters);
        assertSameValue(2, count($pdo->queries), "{$method} did not execute exactly two queries.");
        assertTrue(str_starts_with($pdo->queries[0], 'SELECT COUNT(*) AS total '), "{$method} count query is incorrect.");
        assertTrue(!str_contains(strtoupper($pdo->queries[1]), 'SELECT *'), "{$method} used SELECT *.");
        foreach ($joins as $join) { assertTrue(str_contains($pdo->queries[1], $join), "{$method} omitted {$join}."); }
        foreach ($conditions as $condition) { assertTrue(str_contains($pdo->queries[1], $condition), "{$method} omitted {$condition}."); }
        assertTrue(str_contains($pdo->queries[1], 'l.gy_leave_date_to >= :date_from'), "{$method} omitted overlapping start-date filtering.");
        assertTrue(str_contains($pdo->queries[1], 'l.gy_leave_date_from < DATE_ADD(:date_to, INTERVAL 1 DAY)'), "{$method} omitted overlapping end-date filtering.");
        assertTrue(str_contains($pdo->queries[1], 'ORDER BY l.gy_leave_filed DESC, l.gy_leave_id DESC LIMIT :limit OFFSET :offset'), "{$method} uses the wrong ordering or pagination.");
        $projection = substr($pdo->queries[1], 0, strpos($pdo->queries[1], ' FROM '));
        foreach (['l.gy_user_id', 'l.gy_leave_approver', 'u.gy_user_id', 'approver.gy_user_id', 'gy_password'] as $forbidden) {
            assertTrue(!str_contains($projection, $forbidden), "{$method} exposes {$forbidden}.");
        }
        assertSameValue($department, str_contains($projection, 'department_name'), "{$method} returned the wrong department projection.");
        assertSameValue(['value' => 15, 'type' => PDO::PARAM_INT], $pdo->bindings[1][':limit'] ?? null, "{$method} did not bind limit.");
        assertSameValue(['value' => 15, 'type' => PDO::PARAM_INT], $pdo->bindings[1][':offset'] ?? null, "{$method} calculated the wrong offset.");
        assertSameValue(['value' => '0', 'type' => PDO::PARAM_STR], $pdo->bindings[1][':status'] ?? null, "{$method} did not bind the VARCHAR status safely.");
        assertSameValue(['data' => [['leave_id' => 41]], 'total' => 17], $result, "{$method} returned the wrong page.");
    }
});

test('Batch 3 history employee relationship matches the safe employee resource fields', function (): void {
    $pdo = new RecordingPdo();
    $pdo->fetchResult = ['_parent_id' => 43, '_related_id' => 7, 'gy_emp_id' => 7];
    (new LeaveRelationshipRepository($pdo))->findEmployeeByLeaveCreditHistoryId(43);
    $projection = substr((string) $pdo->query, 0, strpos((string) $pdo->query, ' FROM '));
    assertTrue(str_contains($projection, 'e.gy_emp_supervisor'), 'History employee response omitted the canonical supervisor field.');
});

test('Batch 3 leave view controller validates filters and returns numbered pagination', function (): void {
    $pdo = new EmployeeDirectoryRecordingPdo();
    $pdo->fetchResults[0] = ['total' => 16];
    $pdo->fetchAllResults[1] = [['leave_id' => 41]];
    $controller = new LeaveViewController(static fn (): LeaveViewRepository => new LeaveViewRepository($pdo));
    $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/leave-management')
        ->withQueryParams(['page' => '1', 'limit' => '15', 'gy_emp_code' => ' test1 ', 'department_id' => '5']);
    $response = $controller->leaveManagement($request, (new ResponseFactory())->createResponse());
    $payload = responseJson($response);
    assertSameValue(200, $response->getStatusCode(), 'Leave management returned the wrong status.');
    assertSameValue(16, $payload['pagination']['totalRecords'], 'Leave management returned the wrong total.');
    assertSameValue(2, $payload['pagination']['totalPages'], 'Leave management returned the wrong page count.');
    assertSameValue('test1', $payload['filters']['gyEmpCode'], 'Leave management did not normalize gy_emp_code.');
    assertSameValue(5, $payload['filters']['departmentId'], 'Leave management omitted department filtering.');

    foreach ([['account_id' => 'bad'], ['status' => '-1'], ['date_from' => '2026-02-30'], ['date_from' => '2026-10-31', 'date_to' => '2026-10-01'], ['leave_type' => ['bad']]] as $query) {
        $created = false;
        $invalidController = new LeaveViewController(static function () use (&$created): LeaveViewRepository {
            $created = true;
            return new LeaveViewRepository(new EmployeeDirectoryRecordingPdo());
        });
        $invalid = $invalidController->leaveManagement(
            (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/leave-management')->withQueryParams($query),
            (new ResponseFactory())->createResponse()
        );
        assertSameValue(400, $invalid->getStatusCode(), 'Invalid leave view filter was accepted.');
        assertSameValue(false, $created, 'Leave view repository ran before validation.');
    }
});

test('Batch 3 optimized leave view routes require JWT', function (): void {
    $keys = testRsaKeys();
    $jwtService = new JwtService('kronos-api', 'kronos-api-clients', 3600, $keys['private_path'], $keys['public_path']);
    $calls = 0;
    $factory = static function () use (&$calls): LeaveViewRepository {
        $calls++;
        $pdo = new EmployeeDirectoryRecordingPdo();
        $pdo->fetchResults[0] = ['total' => 0];
        return new LeaveViewRepository($pdo);
    };
    $app = AppFactory::create();
    $routes = require __DIR__ . '/../routes/api.php';
    $routes(
        app: $app,
        serviceFactory: static fn (): ApiClientService => new ApiClientService(new RecordingPdo()),
        apiTokenServiceFactory: static fn (): ApiTokenService => new ApiTokenService(new RecordingPdo()),
        jwtServiceFactory: static fn (): JwtService => $jwtService,
        leaveViewRepositoryFactory: $factory
    );
    $app->addRoutingMiddleware();
    $paths = ['/api/v1/employee-leaves', '/api/v1/leave-management'];
    foreach ($paths as $path) {
        assertSameValue(401, $app->handle((new ServerRequestFactory())->createServerRequest('GET', $path))->getStatusCode(), "{$path} is not JWT protected.");
    }
    assertSameValue(0, $calls, 'Leave view repository ran before authorization.');
    $auth = 'Bearer ' . $jwtService->issue(['id' => 7, 'client_name' => 'SiBS HRIS']);
    foreach ($paths as $path) {
        $request = (new ServerRequestFactory())->createServerRequest('GET', $path)->withHeader('Authorization', $auth);
        assertSameValue(200, $app->handle($request)->getStatusCode(), "Authorized {$path} failed.");
    }
    assertSameValue(2, $calls, 'Leave view routes did not dispatch once each.');
});

test('Batch 4 individual DTR resources use one safe explicit prepared lookup', function (): void {
    $cases = [
        ['findEmployeeLogById', 51, ':employee_log_id', 'SELECT gy_log_id, gy_log_date, gy_log_code, gy_log_email, gy_log_fullname, gy_log_account, gy_log_status FROM gy_logs WHERE gy_log_id = :employee_log_id LIMIT 1'],
        ['findEmployeeEditLogById', 52, ':employee_edit_log_id', 'SELECT l.gy_editlog_id, e.gy_emp_code, l.gy_edit_date FROM gy_editlog l LEFT JOIN gy_employee e ON l.gy_emp_id = e.gy_emp_id WHERE l.gy_editlog_id = :employee_edit_log_id LIMIT 1'],
        ['findDtrPublishById', 53, ':dtr_publish_id', 'SELECT dtr_publish_id, dtr_year, dtr_month, dtr_cutoff, gy_emp_code, dtr_noofhours, dtr_lateut, dtr_absences, dtr_regot, dtr_rdreg, dtr_rdot, dtr_shreg, dtr_shot, dtr_shrdreg, dtr_shrdot, dtr_lhreg, dtr_lhot, dtr_lhrdreg, dtr_lhrdot, dtr_ndreg, dtr_ndregot, dtr_ndrdreg, dtr_ndrdot, dtr_ndsh, dtr_ndshot, dtr_ndshrd, dtr_ndshrdot, dtr_ndlh, dtr_ndlhot, dtr_ndlhrd, dtr_ndlhrdot, dtr_mdrate, dtr_cmpute FROM dtr_publish WHERE dtr_publish_id = :dtr_publish_id LIMIT 1'],
        ['findTimesheetAssignmentById', 54, ':timesheet_assignment_id', 'SELECT at_id, at_emp_code, at_account_id FROM assign_timesheet WHERE at_id = :timesheet_assignment_id LIMIT 1'],
    ];
    foreach ($cases as [$method, $id, $placeholder, $expectedQuery]) {
        $pdo = new RecordingPdo();
        $pdo->fetchResult = ['resource_id' => $id];
        $result = (new IndividualResourceRepository($pdo))->{$method}($id);
        assertSameValue($expectedQuery, $pdo->query, "{$method} used the wrong query.");
        assertSameValue(['value' => $id, 'type' => PDO::PARAM_INT], $pdo->boundValues[$placeholder] ?? null, "{$method} did not bind its identifier.");
        assertSameValue(['resource_id' => $id], $result, "{$method} returned the wrong record.");
    }
});

test('Batch 4 individual DTR routes are JWT protected', function (): void {
    $keys = testRsaKeys();
    $jwtService = new JwtService('kronos-api', 'kronos-api-clients', 3600, $keys['private_path'], $keys['public_path']);
    $calls = 0;
    $factory = static function () use (&$calls): IndividualResourceRepository {
        $calls++;
        $pdo = new RecordingPdo();
        $pdo->fetchResult = ['resource_id' => 1];
        return new IndividualResourceRepository($pdo);
    };
    $app = AppFactory::create();
    $routes = require __DIR__ . '/../routes/api.php';
    $routes(app: $app, serviceFactory: static fn (): ApiClientService => new ApiClientService(new RecordingPdo()), apiTokenServiceFactory: static fn (): ApiTokenService => new ApiTokenService(new RecordingPdo()), jwtServiceFactory: static fn (): JwtService => $jwtService, individualResourceRepositoryFactory: $factory);
    $app->addRoutingMiddleware();
    $paths = ['/api/v1/employee-logs/51', '/api/v1/employee-edit-logs/52', '/api/v1/dtr-publish/53', '/api/v1/timesheet-assignments/54'];
    foreach ($paths as $path) { assertSameValue(401, $app->handle((new ServerRequestFactory())->createServerRequest('GET', $path))->getStatusCode(), "{$path} is not JWT protected."); }
    assertSameValue(0, $calls, 'Batch 4 individual repository ran before authorization.');
    $auth = 'Bearer ' . $jwtService->issue(['id' => 7, 'client_name' => 'SiBS HRIS']);
    foreach ($paths as $path) {
        $request = (new ServerRequestFactory())->createServerRequest('GET', $path)->withHeader('Authorization', $auth);
        assertSameValue(200, $app->handle($request)->getStatusCode(), "Authorized {$path} failed.");
    }
    assertSameValue(4, $calls, 'Batch 4 individual routes did not dispatch once each.');
});

test('Batch 4 DTR relationships use one explicit prepared join per request', function (): void {
    $singleCases = [
        ['findEmployeeByLogId', 51, ':employee_log_id', 'l.gy_emp_id = e.gy_emp_id'],
        ['findEmployeeByEditLogId', 52, ':employee_edit_log_id', 'l.gy_emp_id = e.gy_emp_id'],
        ['findEmployeeByDtrPublishId', 53, ':dtr_publish_id', 'TRIM(d.gy_emp_code) = TRIM(e.gy_emp_code)'],
        ['findPublisherUserByDtrPublishId', 53, ':dtr_publish_id', 'd.dtr_publisher = u.gy_user_id'],
        ['findEmployeeByAssignmentId', 54, ':assignment_id', 'TRIM(t.at_emp_code) = TRIM(e.gy_emp_code)'],
        ['findAccountByAssignmentId', 54, ':assignment_id', 't.at_account_id = a.gy_acc_id'],
        ['findAddedByUserByAssignmentId', 54, ':assignment_id', 't.at_added_by = u.gy_user_id'],
    ];
    foreach ($singleCases as [$method, $id, $placeholder, $join]) {
        $pdo = new RecordingPdo();
        $pdo->fetchResult = ['_parent_id' => $id, '_related_id' => 7, 'public_code' => 'safe'];
        $result = (new DtrRelationshipRepository($pdo))->{$method}($id);
        assertSameValue(1, $pdo->prepareCalls, "{$method} executed more than one query.");
        assertTrue(str_contains((string) $pdo->query, $join), "{$method} used the wrong join.");
        assertTrue(!str_contains(strtoupper((string) $pdo->query), 'SELECT *'), "{$method} used SELECT *.");
        assertTrue(!str_contains((string) $pdo->query, 'gy_password'), "{$method} selected a password.");
        assertSameValue(['value' => $id, 'type' => PDO::PARAM_INT], $pdo->boundValues[$placeholder] ?? null, "{$method} did not bind its ID.");
        assertSameValue(['public_code' => 'safe'], $result['data'], "{$method} exposed an internal relationship ID.");
    }

    $collectionCases = [
        ['findLogsByEmployeeCode', 'test1', ':employee_code', PDO::PARAM_STR, 'gy_log_id', 'l.gy_emp_id = e.gy_emp_id', ['l.gy_emp_id']],
        ['findEditLogsByEmployeeCode', 'test1', ':employee_code', PDO::PARAM_STR, 'gy_editlog_id', 'l.gy_emp_id = e.gy_emp_id', ['l.gy_emp_id']],
        ['findDtrPublicationsByEmployeeCode', 'test1', ':employee_code', PDO::PARAM_STR, 'dtr_publish_id', 'TRIM(d.gy_emp_code) = TRIM(e.gy_emp_code)', ['d.dtr_publisher']],
        ['findAssignmentsByEmployeeCode', 'test1', ':employee_code', PDO::PARAM_STR, 'at_id', 'TRIM(t.at_emp_code) = TRIM(e.gy_emp_code)', ['t.at_added_by']],
        ['findDtrPublicationsByUserCode', 'test1', ':employee_code', PDO::PARAM_STR, 'dtr_publish_id', 'd.dtr_publisher = u.gy_user_id', ['d.dtr_publisher']],
        ['findAssignmentsAddedByUserCode', 'test1', ':employee_code', PDO::PARAM_STR, 'at_id', 't.at_added_by = u.gy_user_id', ['t.at_added_by']],
        ['findAssignmentsByAccountId', 12, ':account_id', PDO::PARAM_INT, 'at_id', 't.at_account_id = a.gy_acc_id', ['t.at_added_by']],
    ];
    foreach ($collectionCases as [$method, $parent, $placeholder, $type, $cursor, $join, $forbidden]) {
        $pdo = new RecordingPdo();
        $pdo->fetchAllResult = [['_parent_id' => 7, $cursor => 137]];
        $result = (new DtrRelationshipRepository($pdo))->{$method}($parent, 100);
        assertSameValue(1, $pdo->prepareCalls, "{$method} executed more than one query.");
        assertTrue(str_contains((string) $pdo->query, $join), "{$method} used the wrong join.");
        assertTrue(str_contains((string) $pdo->query, 'LIMIT 101'), "{$method} did not fetch 101 rows.");
        assertSameValue(['value' => $parent, 'type' => $type], $pdo->boundValues[$placeholder] ?? null, "{$method} did not bind its public parent identifier.");
        assertSameValue(['value' => 100, 'type' => PDO::PARAM_INT], $pdo->boundValues[':after_id'] ?? null, "{$method} did not bind its cursor.");
        $projection = substr((string) $pdo->query, 0, strpos((string) $pdo->query, ' FROM '));
        foreach ($forbidden as $field) { assertTrue(!str_contains($projection, $field), "{$method} exposes internal field {$field}."); }
        assertSameValue([[$cursor => 137]], $result['data'], "{$method} exposed an internal marker.");
    }
});

test('Batch 4 DTR relationship routes require JWT and dispatch confirmed joins', function (): void {
    $keys = testRsaKeys();
    $jwtService = new JwtService('kronos-api', 'kronos-api-clients', 3600, $keys['private_path'], $keys['public_path']);
    $calls = 0;
    $pdos = [];
    $factory = static function () use (&$calls, &$pdos): DtrRelationshipRepository {
        $calls++;
        $pdo = new RecordingPdo();
        $pdo->fetchResult = ['_parent_id' => 1, '_related_id' => 2, 'public_code' => 'safe'];
        $pdo->fetchAllResult = [['_parent_id' => 1, 'gy_log_id' => 51, 'gy_editlog_id' => 52, 'dtr_publish_id' => 53, 'at_id' => 54]];
        $pdos[] = $pdo;
        return new DtrRelationshipRepository($pdo);
    };
    $app = AppFactory::create();
    $routes = require __DIR__ . '/../routes/api.php';
    $routes(app: $app, serviceFactory: static fn (): ApiClientService => new ApiClientService(new RecordingPdo()), apiTokenServiceFactory: static fn (): ApiTokenService => new ApiTokenService(new RecordingPdo()), jwtServiceFactory: static fn (): JwtService => $jwtService, dtrRelationshipRepositoryFactory: $factory);
    $app->addRoutingMiddleware();
    $cases = [
        ['/api/v1/employees/test1/logs', 'l.gy_emp_id = e.gy_emp_id'],
        ['/api/v1/employees/test1/edit-logs', 'l.gy_emp_id = e.gy_emp_id'],
        ['/api/v1/employees/test1/dtr-publish', 'TRIM(d.gy_emp_code) = TRIM(e.gy_emp_code)'],
        ['/api/v1/employees/test1/timesheet-assignments', 'TRIM(t.at_emp_code) = TRIM(e.gy_emp_code)'],
        ['/api/v1/users/test1/dtr-publications', 'd.dtr_publisher = u.gy_user_id'],
        ['/api/v1/users/test1/timesheet-assignments-added', 't.at_added_by = u.gy_user_id'],
        ['/api/v1/accounts/12/timesheet-assignments', 't.at_account_id = a.gy_acc_id'],
        ['/api/v1/employee-logs/51/employee', 'l.gy_emp_id = e.gy_emp_id'],
        ['/api/v1/employee-edit-logs/52/employee', 'l.gy_emp_id = e.gy_emp_id'],
        ['/api/v1/dtr-publish/53/employee', 'TRIM(d.gy_emp_code) = TRIM(e.gy_emp_code)'],
        ['/api/v1/dtr-publish/53/publisher-user', 'd.dtr_publisher = u.gy_user_id'],
        ['/api/v1/timesheet-assignments/54/employee', 'TRIM(t.at_emp_code) = TRIM(e.gy_emp_code)'],
        ['/api/v1/timesheet-assignments/54/account', 't.at_account_id = a.gy_acc_id'],
        ['/api/v1/timesheet-assignments/54/added-by-user', 't.at_added_by = u.gy_user_id'],
    ];
    foreach ($cases as [$path]) { assertSameValue(401, $app->handle((new ServerRequestFactory())->createServerRequest('GET', $path))->getStatusCode(), "{$path} is not JWT protected."); }
    assertSameValue(0, $calls, 'DTR relationship repository ran before authorization.');
    $auth = 'Bearer ' . $jwtService->issue(['id' => 7, 'client_name' => 'SiBS HRIS']);
    foreach ($cases as $index => [$path, $join]) {
        $request = (new ServerRequestFactory())->createServerRequest('GET', $path)->withHeader('Authorization', $auth);
        assertSameValue(200, $app->handle($request)->getStatusCode(), "Authorized {$path} failed.");
        assertTrue(str_contains((string) $pdos[$index]->query, $join), "{$path} dispatched to the wrong join.");
    }
    assertSameValue(14, $calls, 'DTR relationship routes did not dispatch once each.');
});

test('Batch 4 optimized views use exact schema filters and two prepared queries', function (): void {
    $cases = [
        [
            'findEmployeeDtrPage',
            ['gy_emp_code' => 'test1', 'account_id' => 12, 'year' => 2026, 'month' => 9, 'search' => 'connect'],
            ['JOIN gy_employee e', 'LEFT JOIN gy_accounts a', 'LEFT JOIN gy_user publisher'],
            ['TRIM(d.gy_emp_code) = :gy_emp_code', 'a.gy_acc_id = :account_id', 'd.dtr_year = :year', 'd.dtr_month = :month'],
            'ORDER BY d.dtr_year DESC, d.dtr_month DESC, d.dtr_cutoff DESC, d.dtr_publish_id DESC',
            ['d.dtr_publisher', 'publisher.gy_user_id'],
        ],
        [
            'findTimesheetAssignmentPage',
            ['gy_emp_code' => 'test1', 'account_id' => 12, 'search' => 'connect'],
            ['JOIN gy_employee e', 'JOIN gy_accounts a', 'LEFT JOIN gy_user addedBy'],
            ['TRIM(t.at_emp_code) = :gy_emp_code', 'a.gy_acc_id = :account_id'],
            'ORDER BY t.at_id DESC',
            ['t.at_added_by', 'addedBy.gy_user_id'],
        ],
    ];
    foreach ($cases as [$method, $filters, $joins, $conditions, $order, $forbidden]) {
        $pdo = new EmployeeDirectoryRecordingPdo();
        $pdo->fetchResults[0] = ['total' => 17];
        $pdo->fetchAllResults[1] = [['record_id' => 1]];
        $result = (new DtrViewRepository($pdo))->{$method}(2, 15, $filters);
        assertSameValue(2, count($pdo->queries), "{$method} did not execute exactly two queries.");
        assertTrue(str_starts_with($pdo->queries[0], 'SELECT COUNT(*) AS total '), "{$method} count query is incorrect.");
        assertTrue(!str_contains(strtoupper($pdo->queries[1]), 'SELECT *'), "{$method} used SELECT *.");
        foreach ($joins as $join) { assertTrue(str_contains($pdo->queries[1], $join), "{$method} omitted {$join}."); }
        foreach ($conditions as $condition) { assertTrue(str_contains($pdo->queries[1], $condition), "{$method} omitted {$condition}."); }
        assertTrue(str_contains($pdo->queries[1], $order . ' LIMIT :limit OFFSET :offset'), "{$method} uses the wrong ordering.");
        assertTrue(!str_contains($pdo->queries[1], ':date_from') && !str_contains($pdo->queries[1], ':date_to'), "{$method} invented a date filter.");
        assertTrue(!str_contains($pdo->queries[1], ':status'), "{$method} invented a status filter.");
        $projection = substr($pdo->queries[1], 0, strpos($pdo->queries[1], ' FROM '));
        foreach ($forbidden as $field) { assertTrue(!str_contains($projection, $field), "{$method} exposes internal user ID {$field}."); }
        assertSameValue(['value' => 15, 'type' => PDO::PARAM_INT], $pdo->bindings[1][':limit'] ?? null, "{$method} did not bind limit.");
        assertSameValue(['value' => 15, 'type' => PDO::PARAM_INT], $pdo->bindings[1][':offset'] ?? null, "{$method} calculated the wrong offset.");
        assertSameValue(['data' => [['record_id' => 1]], 'total' => 17], $result, "{$method} returned the wrong page.");
    }
});

test('Batch 4 view controller validates only schema-backed filters', function (): void {
    $pdo = new EmployeeDirectoryRecordingPdo();
    $pdo->fetchResults[0] = ['total' => 16];
    $pdo->fetchAllResults[1] = [['dtr_publish_id' => 53]];
    $controller = new DtrViewController(static fn (): DtrViewRepository => new DtrViewRepository($pdo));
    $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/employee-dtr')
        ->withQueryParams(['page' => '1', 'limit' => '15', 'gy_emp_code' => ' test1 ', 'year' => '2026', 'month' => '9']);
    $response = $controller->employeeDtr($request, (new ResponseFactory())->createResponse());
    $payload = responseJson($response);
    assertSameValue(200, $response->getStatusCode(), 'Employee DTR returned the wrong status.');
    assertSameValue(16, $payload['pagination']['totalRecords'], 'Employee DTR returned the wrong total.');
    assertSameValue('test1', $payload['filters']['gyEmpCode'], 'Employee DTR did not normalize gy_emp_code.');
    assertSameValue(2026, $payload['filters']['year'], 'Employee DTR omitted year filtering.');
    assertSameValue(9, $payload['filters']['month'], 'Employee DTR omitted month filtering.');
    assertTrue(!array_key_exists('dateFrom', $payload['filters']), 'Employee DTR exposed an invented date filter.');

    foreach ([['account_id' => 'bad'], ['year' => '0'], ['month' => '13'], ['gy_emp_code' => '123456789012'], ['search' => ['bad']]] as $query) {
        $created = false;
        $invalidController = new DtrViewController(static function () use (&$created): DtrViewRepository { $created = true; return new DtrViewRepository(new EmployeeDirectoryRecordingPdo()); });
        $invalid = $invalidController->employeeDtr((new ServerRequestFactory())->createServerRequest('GET', '/api/v1/employee-dtr')->withQueryParams($query), (new ResponseFactory())->createResponse());
        assertSameValue(400, $invalid->getStatusCode(), 'Invalid DTR filter was accepted.');
        assertSameValue(false, $created, 'DTR repository ran before validation.');
    }
});

test('Batch 4 optimized view routes require JWT', function (): void {
    $keys = testRsaKeys();
    $jwtService = new JwtService('kronos-api', 'kronos-api-clients', 3600, $keys['private_path'], $keys['public_path']);
    $calls = 0;
    $factory = static function () use (&$calls): DtrViewRepository { $calls++; $pdo = new EmployeeDirectoryRecordingPdo(); $pdo->fetchResults[0] = ['total' => 0]; return new DtrViewRepository($pdo); };
    $app = AppFactory::create();
    $routes = require __DIR__ . '/../routes/api.php';
    $routes(app: $app, serviceFactory: static fn (): ApiClientService => new ApiClientService(new RecordingPdo()), apiTokenServiceFactory: static fn (): ApiTokenService => new ApiTokenService(new RecordingPdo()), jwtServiceFactory: static fn (): JwtService => $jwtService, dtrViewRepositoryFactory: $factory);
    $app->addRoutingMiddleware();
    $paths = ['/api/v1/employee-dtr', '/api/v1/timesheet-assignment-view'];
    foreach ($paths as $path) { assertSameValue(401, $app->handle((new ServerRequestFactory())->createServerRequest('GET', $path))->getStatusCode(), "{$path} is not JWT protected."); }
    assertSameValue(0, $calls, 'DTR view repository ran before authorization.');
    $auth = 'Bearer ' . $jwtService->issue(['id' => 7, 'client_name' => 'SiBS HRIS']);
    foreach ($paths as $path) { $request = (new ServerRequestFactory())->createServerRequest('GET', $path)->withHeader('Authorization', $auth); assertSameValue(200, $app->handle($request)->getStatusCode(), "Authorized {$path} failed."); }
    assertSameValue(2, $calls, 'DTR view routes did not dispatch once each.');
});

test('Batch 5 individual announcement resources use one safe explicit prepared lookup', function (): void {
    $cases = [
        ['findAnnouncementById', 61, ':announcement_id', 'SELECT gy_ann_id, gy_ann_serial, gy_ann_type, gy_ann_date, gy_ann_end, gy_ann_caption, gy_ann_attachment FROM gy_announce WHERE gy_ann_id = :announcement_id LIMIT 1'],
        ['findConfirmationById', 62, ':confirmation_id', 'SELECT gy_conf_id, gy_conf_date, gy_conf_by, gy_ann_id FROM gy_confirm WHERE gy_conf_id = :confirmation_id LIMIT 1'],
        ['findNotificationById', 63, ':notification_id', 'SELECT gy_notif_id, gy_notif_type, gy_user_code, gy_notif_text, gy_notif_date, gy_notif_ip FROM gy_notification WHERE gy_notif_id = :notification_id LIMIT 1'],
    ];

    foreach ($cases as [$method, $id, $placeholder, $expectedQuery]) {
        $pdo = new RecordingPdo();
        $pdo->fetchResult = ['resource_id' => $id];
        $result = (new IndividualResourceRepository($pdo))->{$method}($id);
        assertSameValue(1, $pdo->prepareCalls, "{$method} executed more than one query.");
        assertSameValue($expectedQuery, $pdo->query, "{$method} used the wrong field allowlist or lookup.");
        assertTrue(!str_contains(strtoupper((string) $pdo->query), 'SELECT *'), "{$method} used SELECT *.");
        assertTrue(!str_contains((string) $pdo->query, 'gy_password'), "{$method} selected a password.");
        assertSameValue(['value' => $id, 'type' => PDO::PARAM_INT], $pdo->boundValues[$placeholder] ?? null, "{$method} did not bind its identifier.");
        assertSameValue(['resource_id' => $id], $result, "{$method} returned the wrong record.");
    }
});

test('Batch 5 individual announcement routes are unique and JWT protected', function (): void {
    $keys = testRsaKeys();
    $jwtService = new JwtService('kronos-api', 'kronos-api-clients', 3600, $keys['private_path'], $keys['public_path']);
    $calls = 0;
    $factory = static function () use (&$calls): IndividualResourceRepository {
        $calls++;
        $pdo = new RecordingPdo();
        $pdo->fetchResult = ['resource_id' => 1];
        return new IndividualResourceRepository($pdo);
    };
    $app = AppFactory::create();
    $routes = require __DIR__ . '/../routes/api.php';
    $routes(app: $app, serviceFactory: static fn (): ApiClientService => new ApiClientService(new RecordingPdo()), apiTokenServiceFactory: static fn (): ApiTokenService => new ApiTokenService(new RecordingPdo()), jwtServiceFactory: static fn (): JwtService => $jwtService, individualResourceRepositoryFactory: $factory);
    $app->addRoutingMiddleware();
    $paths = ['/api/v1/announcements/61', '/api/v1/confirmations/62', '/api/v1/notifications/63'];
    foreach ($paths as $path) {
        assertSameValue(401, $app->handle((new ServerRequestFactory())->createServerRequest('GET', $path))->getStatusCode(), "{$path} is not JWT protected.");
    }
    assertSameValue(0, $calls, 'Batch 5 individual repository ran before authorization.');
    $auth = 'Bearer ' . $jwtService->issue(['id' => 7, 'client_name' => 'SiBS HRIS']);
    foreach ($paths as $path) {
        $request = (new ServerRequestFactory())->createServerRequest('GET', $path)->withHeader('Authorization', $auth);
        assertSameValue(200, $app->handle($request)->getStatusCode(), "Authorized {$path} failed.");
    }
    assertSameValue(3, $calls, 'Batch 5 individual routes did not dispatch once each.');
});

test('Batch 5 announcement relationships use one explicit prepared join per request', function (): void {
    $singleCases = [
        ['findCreatedByUserByAnnouncementId', 61, ':announcement_id', 'a.gy_ann_by = u.gy_user_id'],
        ['findAnnouncementByConfirmationId', 62, ':confirmation_id', 'c.gy_ann_id = a.gy_ann_id'],
        ['findUserByConfirmationId', 62, ':confirmation_id', 'TRIM(c.gy_conf_by) = TRIM(u.gy_user_code)'],
        ['findUserByNotificationId', 63, ':notification_id', 'TRIM(n.gy_user_code) = TRIM(u.gy_user_code)'],
    ];
    foreach ($singleCases as [$method, $id, $placeholder, $join]) {
        $pdo = new RecordingPdo();
        $pdo->fetchResult = ['_parent_id' => $id, '_related_id' => 7, 'public_code' => 'safe'];
        $result = (new AnnouncementRelationshipRepository($pdo))->{$method}($id);
        assertSameValue(1, $pdo->prepareCalls, "{$method} executed more than one query.");
        assertTrue(str_contains((string) $pdo->query, $join), "{$method} used the wrong join.");
        assertTrue(!str_contains(strtoupper((string) $pdo->query), 'SELECT *'), "{$method} used SELECT *.");
        assertTrue(!str_contains((string) $pdo->query, 'gy_password'), "{$method} selected a password.");
        assertSameValue(['value' => $id, 'type' => PDO::PARAM_INT], $pdo->boundValues[$placeholder] ?? null, "{$method} did not bind its identifier.");
        assertSameValue(['public_code' => 'safe'], $result['data'], "{$method} exposed an internal relationship ID.");
    }

    $collectionCases = [
        ['findAnnouncementsByUserCode', 'test1', 'gy_ann_id', 'a.gy_ann_by = u.gy_user_id'],
        ['findConfirmationsByUserCode', 'test1', 'gy_conf_id', 'TRIM(c.gy_conf_by) = TRIM(u.gy_user_code)'],
        ['findNotificationsByUserCode', 'test1', 'gy_notif_id', 'TRIM(n.gy_user_code) = TRIM(u.gy_user_code)'],
        ['findConfirmationsByAnnouncementId', 61, 'gy_conf_id', 'c.gy_ann_id = a.gy_ann_id'],
    ];
    foreach ($collectionCases as [$method, $parent, $cursor, $join]) {
        $pdo = new RecordingPdo();
        $pdo->fetchAllResult = [['_parent_id' => 7, $cursor => 137]];
        $result = (new AnnouncementRelationshipRepository($pdo))->{$method}($parent, 100);
        assertSameValue(1, $pdo->prepareCalls, "{$method} executed more than one query.");
        assertTrue(str_contains((string) $pdo->query, $join), "{$method} used the wrong join.");
        assertTrue(str_contains((string) $pdo->query, 'LIMIT 101'), "{$method} did not fetch 101 rows.");
        assertSameValue(['value' => 100, 'type' => PDO::PARAM_INT], $pdo->boundValues[':after_id'] ?? null, "{$method} did not bind its cursor.");
        assertSameValue([[$cursor => 137]], $result['data'], "{$method} exposed an internal marker.");
    }
});

test('Batch 5 announcement relationship routes require JWT and dispatch confirmed joins', function (): void {
    $keys = testRsaKeys();
    $jwtService = new JwtService('kronos-api', 'kronos-api-clients', 3600, $keys['private_path'], $keys['public_path']);
    $calls = 0;
    $pdos = [];
    $factory = static function () use (&$calls, &$pdos): AnnouncementRelationshipRepository {
        $calls++;
        $pdo = new RecordingPdo();
        $pdo->fetchResult = ['_parent_id' => 1, '_related_id' => 2, 'public_code' => 'safe'];
        $pdo->fetchAllResult = [['_parent_id' => 1, 'gy_ann_id' => 61, 'gy_conf_id' => 62, 'gy_notif_id' => 63]];
        $pdos[] = $pdo;
        return new AnnouncementRelationshipRepository($pdo);
    };
    $app = AppFactory::create();
    $routes = require __DIR__ . '/../routes/api.php';
    $routes(app: $app, serviceFactory: static fn (): ApiClientService => new ApiClientService(new RecordingPdo()), apiTokenServiceFactory: static fn (): ApiTokenService => new ApiTokenService(new RecordingPdo()), jwtServiceFactory: static fn (): JwtService => $jwtService, announcementRelationshipRepositoryFactory: $factory);
    $app->addRoutingMiddleware();
    $cases = [
        ['/api/v1/users/test1/announcements', 'a.gy_ann_by = u.gy_user_id'],
        ['/api/v1/users/test1/confirmations', 'TRIM(c.gy_conf_by) = TRIM(u.gy_user_code)'],
        ['/api/v1/users/test1/notifications', 'TRIM(n.gy_user_code) = TRIM(u.gy_user_code)'],
        ['/api/v1/announcements/61/created-by-user', 'a.gy_ann_by = u.gy_user_id'],
        ['/api/v1/announcements/61/confirmations', 'c.gy_ann_id = a.gy_ann_id'],
        ['/api/v1/confirmations/62/announcement', 'c.gy_ann_id = a.gy_ann_id'],
        ['/api/v1/confirmations/62/user', 'TRIM(c.gy_conf_by) = TRIM(u.gy_user_code)'],
        ['/api/v1/notifications/63/user', 'TRIM(n.gy_user_code) = TRIM(u.gy_user_code)'],
    ];
    foreach ($cases as [$path]) {
        assertSameValue(401, $app->handle((new ServerRequestFactory())->createServerRequest('GET', $path))->getStatusCode(), "{$path} is not JWT protected.");
    }
    assertSameValue(0, $calls, 'Announcement relationship repository ran before authorization.');
    $auth = 'Bearer ' . $jwtService->issue(['id' => 7, 'client_name' => 'SiBS HRIS']);
    foreach ($cases as $index => [$path, $join]) {
        $request = (new ServerRequestFactory())->createServerRequest('GET', $path)->withHeader('Authorization', $auth);
        assertSameValue(200, $app->handle($request)->getStatusCode(), "Authorized {$path} failed.");
        assertTrue(str_contains((string) $pdos[$index]->query, $join), "{$path} dispatched to the wrong join.");
    }
    assertSameValue(8, $calls, 'Announcement relationship routes did not dispatch once each.');
});

test('Batch 5 announcement feed uses confirmed filters aggregation and exactly two prepared queries', function (): void {
    $pdo = new EmployeeDirectoryRecordingPdo();
    $pdo->fetchResults[0] = ['total' => 17];
    $pdo->fetchAllResults[1] = [['announcement_id' => 61]];
    $filters = ['gy_emp_code' => 'test1', 'search' => 'policy', 'date_from' => '2026-10-01', 'date_to' => '2026-10-31'];
    $result = (new AnnouncementViewRepository($pdo))->findAnnouncementFeedPage(2, 15, $filters);

    assertSameValue(2, count($pdo->queries), 'Announcement feed did not execute exactly two queries.');
    assertTrue(str_starts_with($pdo->queries[0], 'SELECT COUNT(*) AS total FROM gy_announce a'), 'Announcement feed count query is incorrect.');
    assertTrue(!str_contains(strtoupper($pdo->queries[1]), 'SELECT *'), 'Announcement feed used SELECT *.');
    assertTrue(str_contains($pdo->queries[1], 'LEFT JOIN gy_user creator ON a.gy_ann_by = creator.gy_user_id'), 'Announcement feed omitted creator data.');
    assertTrue(str_contains($pdo->queries[1], 'COUNT(*) AS confirmation_count'), 'Announcement feed omitted SQL confirmation aggregation.');
    assertTrue(str_contains($pdo->queries[1], 'TRIM(gy_conf_by) = TRIM(:gy_emp_code)'), 'Announcement feed omitted target-user confirmation status.');
    assertTrue(str_contains($pdo->queries[1], 'a.gy_ann_date >= :date_from'), 'Announcement feed omitted start-date filtering.');
    assertTrue(str_contains($pdo->queries[1], 'a.gy_ann_date < DATE_ADD(:date_to, INTERVAL 1 DAY)'), 'Announcement feed omitted inclusive end-date filtering.');
    assertTrue(str_contains($pdo->queries[1], 'ORDER BY a.gy_ann_date DESC, a.gy_ann_id DESC LIMIT :limit OFFSET :offset'), 'Announcement feed uses the wrong ordering or pagination.');
    assertTrue(!str_contains($pdo->queries[1], ':status'), 'Announcement feed invented a status filter.');
    $projection = substr($pdo->queries[1], 0, strpos($pdo->queries[1], ' FROM '));
    foreach (['a.gy_ann_by', 'creator.gy_user_id', 'gy_password'] as $forbidden) {
        assertTrue(!str_contains($projection, $forbidden), "Announcement feed exposes {$forbidden}.");
    }
    assertSameValue(['value' => 'test1', 'type' => PDO::PARAM_STR], $pdo->bindings[1][':gy_emp_code'] ?? null, 'Announcement feed did not bind the target user code.');
    assertSameValue(['value' => 15, 'type' => PDO::PARAM_INT], $pdo->bindings[1][':limit'] ?? null, 'Announcement feed did not bind limit.');
    assertSameValue(['value' => 15, 'type' => PDO::PARAM_INT], $pdo->bindings[1][':offset'] ?? null, 'Announcement feed calculated the wrong offset.');
    assertSameValue(['data' => [['announcement_id' => 61]], 'total' => 17], $result, 'Announcement feed returned the wrong page.');
});

test('Batch 5 announcement feed controller validates filters and route requires JWT', function (): void {
    $pdo = new EmployeeDirectoryRecordingPdo();
    $pdo->fetchResults[0] = ['total' => 16];
    $pdo->fetchAllResults[1] = [['announcement_id' => 61]];
    $controller = new AnnouncementViewController(static fn (): AnnouncementViewRepository => new AnnouncementViewRepository($pdo));
    $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/announcement-feed')
        ->withQueryParams(['page' => '1', 'limit' => '15', 'gy_emp_code' => ' test1 ', 'search' => ' policy ', 'date_from' => '2026-10-01', 'date_to' => '2026-10-31']);
    $response = $controller->index($request, (new ResponseFactory())->createResponse());
    $payload = responseJson($response);
    assertSameValue(200, $response->getStatusCode(), 'Announcement feed returned the wrong status.');
    assertSameValue('test1', $payload['filters']['gyEmpCode'], 'Announcement feed did not normalize gy_emp_code.');
    assertSameValue(16, $payload['pagination']['totalRecords'], 'Announcement feed returned the wrong total.');
    assertTrue(!array_key_exists('status', $payload['filters']), 'Announcement feed exposed an invented status filter.');

    foreach ([['gy_emp_code' => '123456789012'], ['search' => ['bad']], ['date_from' => '2026-02-30'], ['date_to' => '10/31/2026'], ['date_from' => '2026-10-31', 'date_to' => '2026-10-01']] as $query) {
        $created = false;
        $invalidController = new AnnouncementViewController(static function () use (&$created): AnnouncementViewRepository {
            $created = true;
            return new AnnouncementViewRepository(new EmployeeDirectoryRecordingPdo());
        });
        $invalid = $invalidController->index((new ServerRequestFactory())->createServerRequest('GET', '/api/v1/announcement-feed')->withQueryParams($query), (new ResponseFactory())->createResponse());
        assertSameValue(400, $invalid->getStatusCode(), 'Invalid announcement feed filter was accepted.');
        assertSameValue(false, $created, 'Announcement feed repository ran before validation.');
    }

    $keys = testRsaKeys();
    $jwtService = new JwtService('kronos-api', 'kronos-api-clients', 3600, $keys['private_path'], $keys['public_path']);
    $calls = 0;
    $factory = static function () use (&$calls): AnnouncementViewRepository {
        $calls++;
        $recording = new EmployeeDirectoryRecordingPdo();
        $recording->fetchResults[0] = ['total' => 0];
        return new AnnouncementViewRepository($recording);
    };
    $app = AppFactory::create();
    $routes = require __DIR__ . '/../routes/api.php';
    $routes(app: $app, serviceFactory: static fn (): ApiClientService => new ApiClientService(new RecordingPdo()), apiTokenServiceFactory: static fn (): ApiTokenService => new ApiTokenService(new RecordingPdo()), jwtServiceFactory: static fn (): JwtService => $jwtService, announcementViewRepositoryFactory: $factory);
    $app->addRoutingMiddleware();
    $routeRequest = (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/announcement-feed');
    assertSameValue(401, $app->handle($routeRequest)->getStatusCode(), 'Announcement feed is not JWT protected.');
    assertSameValue(0, $calls, 'Announcement feed repository ran before authorization.');
    $authorized = $routeRequest->withHeader('Authorization', 'Bearer ' . $jwtService->issue(['id' => 7, 'client_name' => 'SiBS HRIS']));
    assertSameValue(200, $app->handle($authorized)->getStatusCode(), 'Announcement feed rejected a valid JWT.');
    assertSameValue(1, $calls, 'Announcement feed did not dispatch once.');
});

test('Batch 6 individual holiday resources use one explicit prepared lookup', function (): void {
    $cases = [
        ['findHolidayTypeById', 71, ':holiday_type_id', 'SELECT gy_hol_type_id, gy_hol_type_name, gy_hol_abbrv, gy_daybonus, gy_nightbonus, lateut, absnt, leaves, gy_day_start, gy_day_end, gy_night_start, gy_night_end, gy_hol_status FROM gy_holiday_types WHERE gy_hol_type_id = :holiday_type_id LIMIT 1'],
        ['findHolidayById', 72, ':holiday_id', 'SELECT gy_hol_id, gy_hol_type_id, gy_hol_reg, gy_hol_title, gy_hol_date, gy_a_year, gy_hol_lastday, gy_hol_loc FROM gy_holiday_calendar WHERE gy_hol_id = :holiday_id LIMIT 1'],
    ];

    foreach ($cases as [$method, $id, $placeholder, $expectedQuery]) {
        $pdo = new RecordingPdo();
        $pdo->fetchResult = ['resource_id' => $id];
        $result = (new IndividualResourceRepository($pdo))->{$method}($id);
        assertSameValue(1, $pdo->prepareCalls, "{$method} executed more than one query.");
        assertSameValue($expectedQuery, $pdo->query, "{$method} used the wrong field allowlist or lookup.");
        assertTrue(!str_contains(strtoupper((string) $pdo->query), 'SELECT *'), "{$method} used SELECT *.");
        assertSameValue(['value' => $id, 'type' => PDO::PARAM_INT], $pdo->boundValues[$placeholder] ?? null, "{$method} did not bind its identifier.");
        assertSameValue(['resource_id' => $id], $result, "{$method} returned the wrong record.");
    }
});

test('Batch 6 individual holiday routes are unique and JWT protected', function (): void {
    $keys = testRsaKeys();
    $jwtService = new JwtService('kronos-api', 'kronos-api-clients', 3600, $keys['private_path'], $keys['public_path']);
    $calls = 0;
    $factory = static function () use (&$calls): IndividualResourceRepository {
        $calls++;
        $pdo = new RecordingPdo();
        $pdo->fetchResult = ['resource_id' => 1];
        return new IndividualResourceRepository($pdo);
    };
    $app = AppFactory::create();
    $routes = require __DIR__ . '/../routes/api.php';
    $routes(app: $app, serviceFactory: static fn (): ApiClientService => new ApiClientService(new RecordingPdo()), apiTokenServiceFactory: static fn (): ApiTokenService => new ApiTokenService(new RecordingPdo()), jwtServiceFactory: static fn (): JwtService => $jwtService, individualResourceRepositoryFactory: $factory);
    $app->addRoutingMiddleware();
    $paths = ['/api/v1/holiday-types/71', '/api/v1/holidays/72'];
    foreach ($paths as $path) {
        assertSameValue(401, $app->handle((new ServerRequestFactory())->createServerRequest('GET', $path))->getStatusCode(), "{$path} is not JWT protected.");
    }
    assertSameValue(0, $calls, 'Batch 6 individual repository ran before authorization.');
    $auth = 'Bearer ' . $jwtService->issue(['id' => 7, 'client_name' => 'SiBS HRIS']);
    foreach ($paths as $path) {
        $request = (new ServerRequestFactory())->createServerRequest('GET', $path)->withHeader('Authorization', $auth);
        assertSameValue(200, $app->handle($request)->getStatusCode(), "Authorized {$path} failed.");
    }
    assertSameValue(2, $calls, 'Batch 6 individual routes did not dispatch once each.');
});

test('Batch 6 holiday relationships use confirmed explicit prepared joins', function (): void {
    $collectionPdo = new RecordingPdo();
    $collectionPdo->fetchAllResult = [['_parent_id' => 71, 'gy_hol_id' => 72]];
    $collection = (new HolidayRelationshipRepository($collectionPdo))->findHolidaysByTypeId(71, 50);
    assertSameValue(1, $collectionPdo->prepareCalls, 'Holiday type collection executed more than one query.');
    assertTrue(str_contains((string) $collectionPdo->query, 'h.gy_hol_type_id = t.gy_hol_type_id'), 'Holiday type collection used the wrong join.');
    assertTrue(str_contains((string) $collectionPdo->query, 'h.gy_hol_id > :after_id'), 'Holiday type collection omitted cursor filtering.');
    assertTrue(str_contains((string) $collectionPdo->query, 'LIMIT 101'), 'Holiday type collection did not fetch 101 rows.');
    assertSameValue(['value' => 71, 'type' => PDO::PARAM_INT], $collectionPdo->boundValues[':holiday_type_id'] ?? null, 'Holiday type ID was not bound.');
    assertSameValue(['value' => 50, 'type' => PDO::PARAM_INT], $collectionPdo->boundValues[':after_id'] ?? null, 'Holiday cursor was not bound.');
    assertSameValue([['gy_hol_id' => 72]], $collection['data'], 'Holiday relationship exposed an internal marker.');

    $singlePdo = new RecordingPdo();
    $singlePdo->fetchResult = ['_parent_id' => 72, '_related_id' => 71, 'gy_hol_type_id' => 71];
    $single = (new HolidayRelationshipRepository($singlePdo))->findTypeByHolidayId(72);
    assertSameValue(1, $singlePdo->prepareCalls, 'Holiday type lookup executed more than one query.');
    assertTrue(str_contains((string) $singlePdo->query, 'h.gy_hol_type_id = t.gy_hol_type_id'), 'Holiday type lookup used the wrong join.');
    assertTrue(!str_contains(strtoupper((string) $singlePdo->query), 'SELECT *'), 'Holiday type lookup used SELECT *.');
    assertSameValue(['gy_hol_type_id' => 71], $single['data'], 'Holiday type lookup exposed an internal marker.');
});

test('Batch 6 holiday relationship routes require JWT and dispatch confirmed joins', function (): void {
    $keys = testRsaKeys();
    $jwtService = new JwtService('kronos-api', 'kronos-api-clients', 3600, $keys['private_path'], $keys['public_path']);
    $calls = 0;
    $pdos = [];
    $factory = static function () use (&$calls, &$pdos): HolidayRelationshipRepository {
        $calls++;
        $pdo = new RecordingPdo();
        $pdo->fetchResult = ['_parent_id' => 72, '_related_id' => 71, 'gy_hol_type_id' => 71];
        $pdo->fetchAllResult = [['_parent_id' => 71, 'gy_hol_id' => 72]];
        $pdos[] = $pdo;
        return new HolidayRelationshipRepository($pdo);
    };
    $app = AppFactory::create();
    $routes = require __DIR__ . '/../routes/api.php';
    $routes(app: $app, serviceFactory: static fn (): ApiClientService => new ApiClientService(new RecordingPdo()), apiTokenServiceFactory: static fn (): ApiTokenService => new ApiTokenService(new RecordingPdo()), jwtServiceFactory: static fn (): JwtService => $jwtService, holidayRelationshipRepositoryFactory: $factory);
    $app->addRoutingMiddleware();
    $paths = ['/api/v1/holiday-types/71/holidays', '/api/v1/holidays/72/type'];
    foreach ($paths as $path) {
        assertSameValue(401, $app->handle((new ServerRequestFactory())->createServerRequest('GET', $path))->getStatusCode(), "{$path} is not JWT protected.");
    }
    assertSameValue(0, $calls, 'Holiday relationship repository ran before authorization.');
    $auth = 'Bearer ' . $jwtService->issue(['id' => 7, 'client_name' => 'SiBS HRIS']);
    foreach ($paths as $index => $path) {
        $request = (new ServerRequestFactory())->createServerRequest('GET', $path)->withHeader('Authorization', $auth);
        assertSameValue(200, $app->handle($request)->getStatusCode(), "Authorized {$path} failed.");
        assertTrue(str_contains((string) $pdos[$index]->query, 'h.gy_hol_type_id = t.gy_hol_type_id'), "{$path} dispatched to the wrong join.");
    }
    assertSameValue(2, $calls, 'Holiday relationship routes did not dispatch once each.');
});

test('Batch 6 holiday calendar view uses schema-backed filters and exactly two prepared queries', function (): void {
    $pdo = new EmployeeDirectoryRecordingPdo();
    $pdo->fetchResults[0] = ['total' => 17];
    $pdo->fetchAllResults[1] = [['holiday_id' => 72]];
    $filters = ['year' => 2026, 'date_from' => '2026-01-01', 'date_to' => '2026-12-31', 'location' => 1, 'holiday_type_id' => 71, 'search' => 'regular'];
    $result = (new HolidayViewRepository($pdo))->findHolidayCalendarPage(2, 15, $filters);

    assertSameValue(2, count($pdo->queries), 'Holiday calendar view did not execute exactly two queries.');
    assertTrue(str_starts_with($pdo->queries[0], 'SELECT COUNT(*) AS total FROM gy_holiday_calendar h INNER JOIN gy_holiday_types t'), 'Holiday calendar count query is incorrect.');
    assertTrue(!str_contains(strtoupper($pdo->queries[1]), 'SELECT *'), 'Holiday calendar view used SELECT *.');
    $expectedProjection = 'SELECT h.gy_hol_id, h.gy_hol_type_id, h.gy_hol_reg, h.gy_hol_title, '
        . 'h.gy_hol_date, h.gy_a_year, h.gy_hol_lastday, h.gy_hol_loc, '
        . 't.gy_hol_type_name, t.gy_hol_abbrv, t.gy_daybonus, t.gy_nightbonus, '
        . 't.lateut, t.absnt, t.leaves, t.gy_day_start, t.gy_day_end, '
        . 't.gy_night_start, t.gy_night_end ';
    assertTrue(str_starts_with($pdo->queries[1], $expectedProjection), 'Holiday calendar view changed the requested response field names.');
    assertTrue(str_contains($pdo->queries[1], 'h.gy_hol_type_id = t.gy_hol_type_id'), 'Holiday calendar view omitted its confirmed join.');
    foreach (['h.gy_a_year = :year', 'h.gy_hol_date >= :date_from', 'h.gy_hol_date <= :date_to', 'h.gy_hol_loc = :location', 'h.gy_hol_type_id = :holiday_type_id'] as $condition) {
        assertTrue(str_contains($pdo->queries[1], $condition), "Holiday calendar view omitted {$condition}.");
    }
    foreach (['h.gy_hol_title LIKE :search_0', 't.gy_hol_type_name LIKE :search_1', 't.gy_hol_abbrv LIKE :search_2'] as $condition) {
        assertTrue(str_contains($pdo->queries[1], $condition), "Holiday calendar search omitted {$condition}.");
    }
    assertTrue(str_contains($pdo->queries[1], 'ORDER BY h.gy_hol_date ASC, h.gy_hol_id ASC LIMIT :limit OFFSET :offset'), 'Holiday calendar view uses the wrong ordering or pagination.');
    assertSameValue(['value' => 15, 'type' => PDO::PARAM_INT], $pdo->bindings[1][':limit'] ?? null, 'Holiday calendar view did not bind limit.');
    assertSameValue(['value' => 15, 'type' => PDO::PARAM_INT], $pdo->bindings[1][':offset'] ?? null, 'Holiday calendar view calculated the wrong offset.');
    assertSameValue(['data' => [['holiday_id' => 72]], 'total' => 17], $result, 'Holiday calendar view returned the wrong page.');
});

test('Batch 6 holiday calendar controller validates filters and conflict-safe route requires JWT', function (): void {
    $pdo = new EmployeeDirectoryRecordingPdo();
    $pdo->fetchResults[0] = ['total' => 16];
    $pdo->fetchAllResults[1] = [['holiday_id' => 72]];
    $controller = new HolidayViewController(static fn (): HolidayViewRepository => new HolidayViewRepository($pdo));
    $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/holiday-calendar-view')
        ->withQueryParams(['page' => '1', 'limit' => '15', 'year' => '2026', 'location' => '1', 'holiday_type_id' => '71', 'search' => ' regular ', 'date_from' => '2026-01-01', 'date_to' => '2026-12-31']);
    $response = $controller->index($request, (new ResponseFactory())->createResponse());
    $payload = responseJson($response);
    assertSameValue(200, $response->getStatusCode(), 'Holiday calendar view returned the wrong status.');
    assertSameValue(2026, $payload['filters']['year'], 'Holiday calendar view omitted year filtering.');
    assertSameValue(1, $payload['filters']['location'], 'Holiday calendar view omitted location filtering.');
    assertSameValue(16, $payload['pagination']['totalRecords'], 'Holiday calendar view returned the wrong total.');

    foreach ([['year' => '0'], ['location' => '-1'], ['holiday_type_id' => 'bad'], ['search' => ['bad']], ['date_from' => '2026-02-30'], ['date_from' => '2026-12-31', 'date_to' => '2026-01-01']] as $query) {
        $created = false;
        $invalidController = new HolidayViewController(static function () use (&$created): HolidayViewRepository {
            $created = true;
            return new HolidayViewRepository(new EmployeeDirectoryRecordingPdo());
        });
        $invalid = $invalidController->index((new ServerRequestFactory())->createServerRequest('GET', '/api/v1/holiday-calendar-view')->withQueryParams($query), (new ResponseFactory())->createResponse());
        assertSameValue(400, $invalid->getStatusCode(), 'Invalid holiday calendar filter was accepted.');
        assertSameValue(false, $created, 'Holiday calendar repository ran before validation.');
    }

    $keys = testRsaKeys();
    $jwtService = new JwtService('kronos-api', 'kronos-api-clients', 3600, $keys['private_path'], $keys['public_path']);
    $calls = 0;
    $factory = static function () use (&$calls): HolidayViewRepository {
        $calls++;
        $recording = new EmployeeDirectoryRecordingPdo();
        $recording->fetchResults[0] = ['total' => 0];
        return new HolidayViewRepository($recording);
    };
    $app = AppFactory::create();
    $routes = require __DIR__ . '/../routes/api.php';
    $routes(app: $app, serviceFactory: static fn (): ApiClientService => new ApiClientService(new RecordingPdo()), apiTokenServiceFactory: static fn (): ApiTokenService => new ApiTokenService(new RecordingPdo()), jwtServiceFactory: static fn (): JwtService => $jwtService, holidayViewRepositoryFactory: $factory);
    $app->addRoutingMiddleware();
    $routeRequest = (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/holiday-calendar-view');
    assertSameValue(401, $app->handle($routeRequest)->getStatusCode(), 'Holiday calendar view is not JWT protected.');
    assertSameValue(0, $calls, 'Holiday calendar repository ran before authorization.');
    $authorized = $routeRequest->withHeader('Authorization', 'Bearer ' . $jwtService->issue(['id' => 7, 'client_name' => 'SiBS HRIS']));
    assertSameValue(200, $app->handle($authorized)->getStatusCode(), 'Holiday calendar view rejected a valid JWT.');
    assertSameValue(1, $calls, 'Holiday calendar view did not dispatch once.');
});

test('Batch 7 individual QDS resources use one explicit prepared lookup', function (): void {
    $cases = [
        ['findQdsAssignGroupById', 81, ':qds_assign_group_id', 'SELECT qag_id, qag_sibsid, qag_account FROM qds_assign_group WHERE qag_id = :qds_assign_group_id LIMIT 1'],
        ['findQdsQueryKeyById', 82, ':qds_query_key_id', 'SELECT qdsqk_id, kronos_key_name, query_key, audit_col_id FROM qds_querykey WHERE qdsqk_id = :qds_query_key_id LIMIT 1'],
    ];

    foreach ($cases as [$method, $id, $placeholder, $expectedQuery]) {
        $pdo = new RecordingPdo();
        $pdo->fetchResult = ['resource_id' => $id];
        $result = (new IndividualResourceRepository($pdo))->{$method}($id);
        assertSameValue(1, $pdo->prepareCalls, "{$method} executed more than one query.");
        assertSameValue($expectedQuery, $pdo->query, "{$method} used the wrong field allowlist or lookup.");
        assertTrue(!str_contains(strtoupper((string) $pdo->query), 'SELECT *'), "{$method} used SELECT *.");
        assertSameValue(['value' => $id, 'type' => PDO::PARAM_INT], $pdo->boundValues[$placeholder] ?? null, "{$method} did not bind its identifier.");
        assertSameValue(['resource_id' => $id], $result, "{$method} returned the wrong record.");
    }
});

test('Batch 7 individual QDS routes are unique and JWT protected', function (): void {
    $keys = testRsaKeys();
    $jwtService = new JwtService('kronos-api', 'kronos-api-clients', 3600, $keys['private_path'], $keys['public_path']);
    $calls = 0;
    $factory = static function () use (&$calls): IndividualResourceRepository {
        $calls++;
        $pdo = new RecordingPdo();
        $pdo->fetchResult = ['resource_id' => 1];
        return new IndividualResourceRepository($pdo);
    };
    $app = AppFactory::create();
    $routes = require __DIR__ . '/../routes/api.php';
    $routes(app: $app, serviceFactory: static fn (): ApiClientService => new ApiClientService(new RecordingPdo()), apiTokenServiceFactory: static fn (): ApiTokenService => new ApiTokenService(new RecordingPdo()), jwtServiceFactory: static fn (): JwtService => $jwtService, individualResourceRepositoryFactory: $factory);
    $app->addRoutingMiddleware();
    $paths = ['/api/v1/qds-assign-groups/81', '/api/v1/qds-query-keys/82'];
    foreach ($paths as $path) {
        assertSameValue(401, $app->handle((new ServerRequestFactory())->createServerRequest('GET', $path))->getStatusCode(), "{$path} is not JWT protected.");
    }
    assertSameValue(0, $calls, 'Batch 7 individual repository ran before authorization.');
    $auth = 'Bearer ' . $jwtService->issue(['id' => 7, 'client_name' => 'SiBS HRIS']);
    foreach ($paths as $path) {
        $request = (new ServerRequestFactory())->createServerRequest('GET', $path)->withHeader('Authorization', $auth);
        assertSameValue(200, $app->handle($request)->getStatusCode(), "Authorized {$path} failed.");
    }
    assertSameValue(2, $calls, 'Batch 7 individual routes did not dispatch once each.');
});

test('Batch 7 QDS relationships use confirmed explicit prepared joins', function (): void {
    $collectionCases = [
        ['findAssignmentsByEmployeeCode', 'test1', ':employee_code', PDO::PARAM_STR, 'TRIM(q.qag_sibsid) = TRIM(e.gy_emp_code)'],
        ['findAssignmentsByAccountId', 12, ':account_id', PDO::PARAM_INT, 'q.qag_account = a.gy_acc_id'],
    ];
    foreach ($collectionCases as [$method, $parent, $placeholder, $type, $join]) {
        $pdo = new RecordingPdo();
        $pdo->fetchAllResult = [['_parent_id' => 7, 'qag_id' => 81]];
        $result = (new QdsRelationshipRepository($pdo))->{$method}($parent, 50);
        assertSameValue(1, $pdo->prepareCalls, "{$method} executed more than one query.");
        assertTrue(str_contains((string) $pdo->query, $join), "{$method} used the wrong join.");
        assertTrue(str_contains((string) $pdo->query, 'q.qag_id > :after_id'), "{$method} omitted cursor filtering.");
        assertTrue(str_contains((string) $pdo->query, 'LIMIT 101'), "{$method} did not fetch 101 rows.");
        assertSameValue(['value' => $parent, 'type' => $type], $pdo->boundValues[$placeholder] ?? null, "{$method} did not bind its parent.");
        assertSameValue(['value' => 50, 'type' => PDO::PARAM_INT], $pdo->boundValues[':after_id'] ?? null, "{$method} did not bind its cursor.");
        assertSameValue([['qag_id' => 81]], $result['data'], "{$method} exposed an internal marker.");
    }

    $singleCases = [
        ['findEmployeeByAssignmentId', 'TRIM(q.qag_sibsid) = TRIM(e.gy_emp_code)', 'gy_emp_code'],
        ['findAccountByAssignmentId', 'q.qag_account = a.gy_acc_id', 'gy_acc_id'],
    ];
    foreach ($singleCases as [$method, $join, $relatedKey]) {
        $pdo = new RecordingPdo();
        $pdo->fetchResult = ['_parent_id' => 81, '_related_id' => 7, $relatedKey => 'safe'];
        $result = (new QdsRelationshipRepository($pdo))->{$method}(81);
        assertSameValue(1, $pdo->prepareCalls, "{$method} executed more than one query.");
        assertTrue(str_contains((string) $pdo->query, $join), "{$method} used the wrong join.");
        assertTrue(!str_contains(strtoupper((string) $pdo->query), 'SELECT *'), "{$method} used SELECT *.");
        assertSameValue(['value' => 81, 'type' => PDO::PARAM_INT], $pdo->boundValues[':qds_assign_group_id'] ?? null, "{$method} did not bind qag_id.");
        assertSameValue([$relatedKey => 'safe'], $result['data'], "{$method} exposed an internal marker.");
    }
});

test('Batch 7 QDS relationship routes require JWT and dispatch confirmed joins', function (): void {
    $keys = testRsaKeys();
    $jwtService = new JwtService('kronos-api', 'kronos-api-clients', 3600, $keys['private_path'], $keys['public_path']);
    $calls = 0;
    $pdos = [];
    $factory = static function () use (&$calls, &$pdos): QdsRelationshipRepository {
        $calls++;
        $pdo = new RecordingPdo();
        $pdo->fetchResult = ['_parent_id' => 81, '_related_id' => 7, 'gy_emp_code' => 'test1', 'gy_acc_id' => 12];
        $pdo->fetchAllResult = [['_parent_id' => 7, 'qag_id' => 81]];
        $pdos[] = $pdo;
        return new QdsRelationshipRepository($pdo);
    };
    $app = AppFactory::create();
    $routes = require __DIR__ . '/../routes/api.php';
    $routes(app: $app, serviceFactory: static fn (): ApiClientService => new ApiClientService(new RecordingPdo()), apiTokenServiceFactory: static fn (): ApiTokenService => new ApiTokenService(new RecordingPdo()), jwtServiceFactory: static fn (): JwtService => $jwtService, qdsRelationshipRepositoryFactory: $factory);
    $app->addRoutingMiddleware();
    $cases = [
        ['/api/v1/employees/test1/qds-assign-groups', 'TRIM(q.qag_sibsid) = TRIM(e.gy_emp_code)'],
        ['/api/v1/accounts/12/qds-assign-groups', 'q.qag_account = a.gy_acc_id'],
        ['/api/v1/qds-assign-groups/81/employee', 'TRIM(q.qag_sibsid) = TRIM(e.gy_emp_code)'],
        ['/api/v1/qds-assign-groups/81/account', 'q.qag_account = a.gy_acc_id'],
    ];
    foreach ($cases as [$path]) {
        assertSameValue(401, $app->handle((new ServerRequestFactory())->createServerRequest('GET', $path))->getStatusCode(), "{$path} is not JWT protected.");
    }
    assertSameValue(0, $calls, 'QDS relationship repository ran before authorization.');
    $auth = 'Bearer ' . $jwtService->issue(['id' => 7, 'client_name' => 'SiBS HRIS']);
    foreach ($cases as $index => [$path, $join]) {
        $request = (new ServerRequestFactory())->createServerRequest('GET', $path)->withHeader('Authorization', $auth);
        assertSameValue(200, $app->handle($request)->getStatusCode(), "Authorized {$path} failed.");
        assertTrue(str_contains((string) $pdos[$index]->query, $join), "{$path} dispatched to the wrong join.");
    }
    assertSameValue(4, $calls, 'QDS relationship routes did not dispatch once each.');
});

test('Batch 7 QDS assignment view uses confirmed filters and exactly two prepared queries', function (): void {
    $pdo = new EmployeeDirectoryRecordingPdo();
    $pdo->fetchResults[0] = ['total' => 17];
    $pdo->fetchAllResults[1] = [['qag_id' => 81]];
    $filters = ['gy_emp_code' => 'test1', 'account_id' => 12, 'search' => 'connect'];
    $result = (new QdsViewRepository($pdo))->findAssignmentPage(2, 15, $filters);

    assertSameValue(2, count($pdo->queries), 'QDS assignment view did not execute exactly two queries.');
    assertTrue(str_starts_with($pdo->queries[0], 'SELECT COUNT(*) AS total FROM qds_assign_group q INNER JOIN gy_employee e'), 'QDS assignment count query is incorrect.');
    assertTrue(!str_contains(strtoupper($pdo->queries[1]), 'SELECT *'), 'QDS assignment view used SELECT *.');
    assertTrue(str_contains($pdo->queries[1], 'TRIM(q.qag_sibsid) = TRIM(e.gy_emp_code)'), 'QDS assignment view omitted the employee join.');
    assertTrue(str_contains($pdo->queries[1], 'q.qag_account = a.gy_acc_id'), 'QDS assignment view omitted the account join.');
    assertTrue(str_contains($pdo->queries[1], 'TRIM(q.qag_sibsid) = :gy_emp_code'), 'QDS assignment view omitted employee filtering.');
    assertTrue(str_contains($pdo->queries[1], 'a.gy_acc_id = :account_id'), 'QDS assignment view omitted account filtering.');
    foreach (['q.qag_sibsid LIKE :search_0', 'e.gy_emp_fullname LIKE :search_1', 'a.gy_acc_name LIKE :search_2'] as $condition) {
        assertTrue(str_contains($pdo->queries[1], $condition), "QDS assignment search omitted {$condition}.");
    }
    assertTrue(str_contains($pdo->queries[1], 'ORDER BY q.qag_id DESC LIMIT :limit OFFSET :offset'), 'QDS assignment view uses the wrong ordering or pagination.');
    assertTrue(!str_contains($pdo->queries[1], ':status') && !str_contains($pdo->queries[1], ':date'), 'QDS assignment view invented unsupported filters.');
    assertSameValue(['value' => 15, 'type' => PDO::PARAM_INT], $pdo->bindings[1][':limit'] ?? null, 'QDS assignment view did not bind limit.');
    assertSameValue(['value' => 15, 'type' => PDO::PARAM_INT], $pdo->bindings[1][':offset'] ?? null, 'QDS assignment view calculated the wrong offset.');
    assertSameValue(['data' => [['qag_id' => 81]], 'total' => 17], $result, 'QDS assignment view returned the wrong page.');
});

test('Batch 7 QDS view controller validates filters and route requires JWT', function (): void {
    $pdo = new EmployeeDirectoryRecordingPdo();
    $pdo->fetchResults[0] = ['total' => 16];
    $pdo->fetchAllResults[1] = [['qag_id' => 81]];
    $controller = new QdsViewController(static fn (): QdsViewRepository => new QdsViewRepository($pdo));
    $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/qds-assignment-view')
        ->withQueryParams(['page' => '1', 'limit' => '15', 'gy_emp_code' => ' test1 ', 'account_id' => '12', 'search' => ' connect ']);
    $response = $controller->index($request, (new ResponseFactory())->createResponse());
    $payload = responseJson($response);
    assertSameValue(200, $response->getStatusCode(), 'QDS assignment view returned the wrong status.');
    assertSameValue('test1', $payload['filters']['gyEmpCode'], 'QDS assignment view did not normalize employee code.');
    assertSameValue(12, $payload['filters']['accountId'], 'QDS assignment view omitted account filtering.');
    assertSameValue(16, $payload['pagination']['totalRecords'], 'QDS assignment view returned the wrong total.');

    foreach ([['gy_emp_code' => '123456789012'], ['account_id' => 'bad'], ['search' => ['bad']]] as $query) {
        $created = false;
        $invalidController = new QdsViewController(static function () use (&$created): QdsViewRepository {
            $created = true;
            return new QdsViewRepository(new EmployeeDirectoryRecordingPdo());
        });
        $invalid = $invalidController->index((new ServerRequestFactory())->createServerRequest('GET', '/api/v1/qds-assignment-view')->withQueryParams($query), (new ResponseFactory())->createResponse());
        assertSameValue(400, $invalid->getStatusCode(), 'Invalid QDS assignment filter was accepted.');
        assertSameValue(false, $created, 'QDS assignment repository ran before validation.');
    }

    $keys = testRsaKeys();
    $jwtService = new JwtService('kronos-api', 'kronos-api-clients', 3600, $keys['private_path'], $keys['public_path']);
    $calls = 0;
    $factory = static function () use (&$calls): QdsViewRepository {
        $calls++;
        $recording = new EmployeeDirectoryRecordingPdo();
        $recording->fetchResults[0] = ['total' => 0];
        return new QdsViewRepository($recording);
    };
    $app = AppFactory::create();
    $routes = require __DIR__ . '/../routes/api.php';
    $routes(app: $app, serviceFactory: static fn (): ApiClientService => new ApiClientService(new RecordingPdo()), apiTokenServiceFactory: static fn (): ApiTokenService => new ApiTokenService(new RecordingPdo()), jwtServiceFactory: static fn (): JwtService => $jwtService, qdsViewRepositoryFactory: $factory);
    $app->addRoutingMiddleware();
    $routeRequest = (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/qds-assignment-view');
    assertSameValue(401, $app->handle($routeRequest)->getStatusCode(), 'QDS assignment view is not JWT protected.');
    assertSameValue(0, $calls, 'QDS assignment repository ran before authorization.');
    $authorized = $routeRequest->withHeader('Authorization', 'Bearer ' . $jwtService->issue(['id' => 7, 'client_name' => 'SiBS HRIS']));
    assertSameValue(200, $app->handle($authorized)->getStatusCode(), 'QDS assignment view rejected a valid JWT.');
    assertSameValue(1, $calls, 'QDS assignment view did not dispatch once.');
});

test('Batch 8 individual team resources use one explicit prepared lookup', function (): void {
    $cases = [
        ['findTeamToolById', 91, ':team_id', 'SELECT team_id, team_name, team_owner, team_switch FROM team_toollist WHERE team_id = :team_id LIMIT 1'],
        ['findTeamColumnById', 92, ':col_id', 'SELECT col_id, team_id, col_val, col_type, col_status, col_order FROM team_collist WHERE col_id = :col_id LIMIT 1'],
        ['findTeamDataById', 93, ':data_id', 'SELECT data_id, col_id, row_id, tool_id, data_value FROM team_data WHERE data_id = :data_id LIMIT 1'],
    ];

    foreach ($cases as [$method, $id, $placeholder, $expectedQuery]) {
        $pdo = new RecordingPdo();
        $pdo->fetchResult = ['resource_id' => $id];
        $result = (new IndividualResourceRepository($pdo))->{$method}($id);
        assertSameValue(1, $pdo->prepareCalls, "{$method} executed more than one query.");
        assertSameValue($expectedQuery, $pdo->query, "{$method} used the wrong field allowlist or lookup.");
        assertTrue(!str_contains(strtoupper((string) $pdo->query), 'SELECT *'), "{$method} used SELECT *.");
        assertSameValue(['value' => $id, 'type' => PDO::PARAM_INT], $pdo->boundValues[$placeholder] ?? null, "{$method} did not bind its identifier.");
        assertSameValue(['resource_id' => $id], $result, "{$method} returned the wrong record.");
    }
});

test('Batch 8 individual team routes are JWT protected and dispatch once', function (): void {
    $keys = testRsaKeys();
    $jwtService = new JwtService('kronos-api', 'kronos-api-clients', 3600, $keys['private_path'], $keys['public_path']);
    $calls = 0;
    $factory = static function () use (&$calls): IndividualResourceRepository {
        $calls++;
        $pdo = new RecordingPdo();
        $pdo->fetchResult = ['resource_id' => 1];
        return new IndividualResourceRepository($pdo);
    };
    $app = AppFactory::create();
    $routes = require __DIR__ . '/../routes/api.php';
    $routes(app: $app, serviceFactory: static fn (): ApiClientService => new ApiClientService(new RecordingPdo()), apiTokenServiceFactory: static fn (): ApiTokenService => new ApiTokenService(new RecordingPdo()), jwtServiceFactory: static fn (): JwtService => $jwtService, individualResourceRepositoryFactory: $factory);
    $app->addRoutingMiddleware();
    $paths = ['/api/v1/team-tools/91', '/api/v1/team-columns/92', '/api/v1/team-data/93'];

    foreach ($paths as $path) {
        assertSameValue(401, $app->handle((new ServerRequestFactory())->createServerRequest('GET', $path))->getStatusCode(), "{$path} is not JWT protected.");
    }
    assertSameValue(0, $calls, 'Batch 8 individual repository ran before authorization.');

    $auth = 'Bearer ' . $jwtService->issue(['id' => 7, 'client_name' => 'SiBS HRIS']);
    foreach ($paths as $path) {
        $request = (new ServerRequestFactory())->createServerRequest('GET', $path)->withHeader('Authorization', $auth);
        assertSameValue(200, $app->handle($request)->getStatusCode(), "Authorized {$path} failed.");
    }
    assertSameValue(3, $calls, 'Batch 8 individual routes did not dispatch once each.');
});

test('Batch 8 team relationships use only the six confirmed explicit prepared joins', function (): void {
    $collectionCases = [
        ['findColumnsByTeamId', 91, 'c.team_id = t.team_id', 'c.col_id', 'col_id'],
        ['findDataByTeamId', 91, 'd.tool_id = t.team_id', 'd.data_id', 'data_id'],
        ['findDataByColumnId', 92, 'd.col_id = c.col_id', 'd.data_id', 'data_id'],
    ];
    foreach ($collectionCases as [$method, $parentId, $join, $cursorSql, $resultKey]) {
        $pdo = new RecordingPdo();
        $pdo->fetchAllResult = [['_parent_id' => $parentId, $resultKey => 101]];
        $result = (new TeamToolRelationshipRepository($pdo))->{$method}($parentId, 50);
        assertSameValue(1, $pdo->prepareCalls, "{$method} executed more than one query.");
        assertTrue(str_contains((string) $pdo->query, $join), "{$method} used the wrong join.");
        assertTrue(str_contains((string) $pdo->query, "{$cursorSql} > :after_id"), "{$method} omitted cursor filtering.");
        assertTrue(str_contains((string) $pdo->query, 'LIMIT 101'), "{$method} did not fetch 101 rows.");
        assertTrue(!str_contains(strtoupper((string) $pdo->query), 'SELECT *'), "{$method} used SELECT *.");
        assertSameValue(['value' => $parentId, 'type' => PDO::PARAM_INT], $pdo->boundValues[':parent_id'] ?? null, "{$method} did not bind its parent.");
        assertSameValue(['value' => 50, 'type' => PDO::PARAM_INT], $pdo->boundValues[':after_id'] ?? null, "{$method} did not bind its cursor.");
        assertSameValue([[$resultKey => 101]], $result['data'], "{$method} exposed an internal marker.");
    }

    $singleCases = [
        ['findTeamToolByColumnId', 92, 'c.team_id = t.team_id', 'team_id'],
        ['findTeamToolByDataId', 93, 'd.tool_id = t.team_id', 'team_id'],
        ['findColumnByDataId', 93, 'd.col_id = c.col_id', 'col_id'],
    ];
    foreach ($singleCases as [$method, $parentId, $join, $relatedKey]) {
        $pdo = new RecordingPdo();
        $pdo->fetchResult = ['_parent_id' => $parentId, '_related_id' => 7, $relatedKey => 7];
        $result = (new TeamToolRelationshipRepository($pdo))->{$method}($parentId);
        assertSameValue(1, $pdo->prepareCalls, "{$method} executed more than one query.");
        assertTrue(str_contains((string) $pdo->query, $join), "{$method} used the wrong join.");
        assertTrue(!str_contains(strtoupper((string) $pdo->query), 'SELECT *'), "{$method} used SELECT *.");
        assertSameValue(['value' => $parentId, 'type' => PDO::PARAM_INT], $pdo->boundValues[':parent_id'] ?? null, "{$method} did not bind its parent.");
        assertSameValue([$relatedKey => 7], $result['data'], "{$method} exposed an internal marker.");
    }
});

test('Batch 8 team relationship pagination uses actual IDs and all routes require JWT', function (): void {
    $paginationPdo = new RecordingPdo();
    for ($index = 0; $index < 101; $index++) {
        $paginationPdo->fetchAllResult[] = ['_parent_id' => 91, 'col_id' => 200 + ($index * 3)];
    }
    $controller = new TeamToolRelationshipController(
        static fn (): TeamToolRelationshipRepository => new TeamToolRelationshipRepository($paginationPdo)
    );
    $response = $controller->teamToolColumns(
        (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/team-tools/91/columns?after_id=-4')->withQueryParams(['after_id' => '-4']),
        (new ResponseFactory())->createResponse(),
        ['team_id' => '91']
    );
    $payload = responseJson($response);
    assertSameValue(100, count($payload['data']), 'Team column relationship returned more than 100 rows.');
    assertSameValue(497, $payload['pagination']['next_cursor'], 'Team column relationship did not use the actual last col_id.');
    assertSameValue(true, $payload['pagination']['has_more'], 'Team column relationship did not detect row 101.');
    assertSameValue(0, $payload['pagination']['current_cursor'], 'Negative cursor was not normalized.');

    $keys = testRsaKeys();
    $jwtService = new JwtService('kronos-api', 'kronos-api-clients', 3600, $keys['private_path'], $keys['public_path']);
    $calls = 0;
    $pdos = [];
    $factory = static function () use (&$calls, &$pdos): TeamToolRelationshipRepository {
        $calls++;
        $pdo = new RecordingPdo();
        $pdo->fetchAllResult = [['_parent_id' => 91, 'col_id' => 92, 'data_id' => 93]];
        $pdo->fetchResult = ['_parent_id' => 93, '_related_id' => 91, 'team_id' => 91, 'col_id' => 92];
        $pdos[] = $pdo;
        return new TeamToolRelationshipRepository($pdo);
    };
    $app = AppFactory::create();
    $routes = require __DIR__ . '/../routes/api.php';
    $routes(app: $app, serviceFactory: static fn (): ApiClientService => new ApiClientService(new RecordingPdo()), apiTokenServiceFactory: static fn (): ApiTokenService => new ApiTokenService(new RecordingPdo()), jwtServiceFactory: static fn (): JwtService => $jwtService, teamToolRelationshipRepositoryFactory: $factory);
    $app->addRoutingMiddleware();
    $paths = [
        '/api/v1/team-tools/91/columns',
        '/api/v1/team-tools/91/data',
        '/api/v1/team-columns/92/team-tool',
        '/api/v1/team-columns/92/data',
        '/api/v1/team-data/93/team-tool',
        '/api/v1/team-data/93/column',
    ];
    foreach ($paths as $path) {
        assertSameValue(401, $app->handle((new ServerRequestFactory())->createServerRequest('GET', $path))->getStatusCode(), "{$path} is not JWT protected.");
    }
    assertSameValue(0, $calls, 'Team relationship repository ran before authorization.');

    $auth = 'Bearer ' . $jwtService->issue(['id' => 7, 'client_name' => 'SiBS HRIS']);
    foreach ($paths as $path) {
        $request = (new ServerRequestFactory())->createServerRequest('GET', $path)->withHeader('Authorization', $auth);
        assertSameValue(200, $app->handle($request)->getStatusCode(), "Authorized {$path} failed.");
    }
    assertSameValue(6, $calls, 'Team relationship routes did not dispatch once each.');

    foreach ($pdos as $pdo) {
        assertTrue(!str_contains((string) $pdo->query, 'tool_list'), 'Team data was incorrectly joined to tool_list.');
        assertTrue(!str_contains((string) $pdo->query, 'tool_details'), 'Team relationship touched tool_details.');
        assertTrue(!str_contains((string) $pdo->query, 'tool_data'), 'Team relationship touched tool_data.');
    }
});

test('Batch 9 individual generic tool resources use one explicit prepared lookup', function (): void {
    $cases = [
        ['findToolById', 101, ':tool_id', 'SELECT tool_id, tool_name, tool_status FROM tool_list WHERE tool_id = :tool_id LIMIT 1'],
        ['findToolDetailById', 102, ':toold_id', 'SELECT toold_id, toold_sortid, toold_listid, toold_label, toold_type, toold_status FROM tool_details WHERE toold_id = :toold_id LIMIT 1'],
        ['findToolDataById', 103, ':td_id', 'SELECT td_id, td_tooldid, td_emp_code, td_value, td_status FROM tool_data WHERE td_id = :td_id LIMIT 1'],
    ];

    foreach ($cases as [$method, $id, $placeholder, $expectedQuery]) {
        $pdo = new RecordingPdo();
        $pdo->fetchResult = ['resource_id' => $id];
        $result = (new IndividualResourceRepository($pdo))->{$method}($id);
        assertSameValue(1, $pdo->prepareCalls, "{$method} executed more than one query.");
        assertSameValue($expectedQuery, $pdo->query, "{$method} used the wrong field allowlist or lookup.");
        assertTrue(!str_contains(strtoupper((string) $pdo->query), 'SELECT *'), "{$method} used SELECT *.");
        assertSameValue(['value' => $id, 'type' => PDO::PARAM_INT], $pdo->boundValues[$placeholder] ?? null, "{$method} did not bind its identifier.");
        assertSameValue(['resource_id' => $id], $result, "{$method} returned the wrong record.");
    }
});

test('Batch 9 individual generic tool routes are JWT protected and dispatch once', function (): void {
    $keys = testRsaKeys();
    $jwtService = new JwtService('kronos-api', 'kronos-api-clients', 3600, $keys['private_path'], $keys['public_path']);
    $calls = 0;
    $factory = static function () use (&$calls): IndividualResourceRepository {
        $calls++;
        $pdo = new RecordingPdo();
        $pdo->fetchResult = ['resource_id' => 1];
        return new IndividualResourceRepository($pdo);
    };
    $app = AppFactory::create();
    $routes = require __DIR__ . '/../routes/api.php';
    $routes(app: $app, serviceFactory: static fn (): ApiClientService => new ApiClientService(new RecordingPdo()), apiTokenServiceFactory: static fn (): ApiTokenService => new ApiTokenService(new RecordingPdo()), jwtServiceFactory: static fn (): JwtService => $jwtService, individualResourceRepositoryFactory: $factory);
    $app->addRoutingMiddleware();
    $paths = ['/api/v1/tools/101', '/api/v1/tool-details/102', '/api/v1/tool-data/103'];

    foreach ($paths as $path) {
        assertSameValue(401, $app->handle((new ServerRequestFactory())->createServerRequest('GET', $path))->getStatusCode(), "{$path} is not JWT protected.");
    }
    assertSameValue(0, $calls, 'Batch 9 individual repository ran before authorization.');

    $auth = 'Bearer ' . $jwtService->issue(['id' => 7, 'client_name' => 'SiBS HRIS']);
    foreach ($paths as $path) {
        $request = (new ServerRequestFactory())->createServerRequest('GET', $path)->withHeader('Authorization', $auth);
        assertSameValue(200, $app->handle($request)->getStatusCode(), "Authorized {$path} failed.");
    }
    assertSameValue(3, $calls, 'Batch 9 individual routes did not dispatch once each.');
});

test('Batch 9 generic tool relationships use only confirmed explicit prepared joins', function (): void {
    $collectionCases = [
        ['findDataByEmployeeCode', 'test1', ':employee_code', PDO::PARAM_STR, 'TRIM(td.td_emp_code) = TRIM(e.gy_emp_code)', 'td.td_id', 'td_id'],
        ['findDetailsByToolId', 101, ':parent_id', PDO::PARAM_INT, 'd.toold_listid = t.tool_id', 'd.toold_id', 'toold_id'],
        ['findDataByToolDetailId', 102, ':parent_id', PDO::PARAM_INT, 'td.td_tooldid = d.toold_id', 'td.td_id', 'td_id'],
    ];
    foreach ($collectionCases as [$method, $parent, $placeholder, $type, $join, $cursorSql, $resultKey]) {
        $pdo = new RecordingPdo();
        $pdo->fetchAllResult = [['_parent_id' => 1, $resultKey => 111]];
        $result = (new ToolRelationshipRepository($pdo))->{$method}($parent, 50);
        assertSameValue(1, $pdo->prepareCalls, "{$method} executed more than one query.");
        assertTrue(str_contains((string) $pdo->query, $join), "{$method} used the wrong join.");
        assertTrue(str_contains((string) $pdo->query, "{$cursorSql} > :after_id"), "{$method} omitted cursor filtering.");
        assertTrue(str_contains((string) $pdo->query, 'LIMIT 101'), "{$method} did not fetch 101 rows.");
        assertTrue(!str_contains(strtoupper((string) $pdo->query), 'SELECT *'), "{$method} used SELECT *.");
        assertSameValue(['value' => $parent, 'type' => $type], $pdo->boundValues[$placeholder] ?? null, "{$method} did not bind its parent.");
        assertSameValue(['value' => 50, 'type' => PDO::PARAM_INT], $pdo->boundValues[':after_id'] ?? null, "{$method} did not bind its cursor.");
        assertSameValue([[$resultKey => 111]], $result['data'], "{$method} exposed an internal marker.");
    }

    $singleCases = [
        ['findToolByDetailId', 102, 'd.toold_listid = t.tool_id', 'tool_id'],
        ['findDetailByDataId', 103, 'td.td_tooldid = d.toold_id', 'toold_id'],
        ['findEmployeeByDataId', 103, 'TRIM(td.td_emp_code) = TRIM(e.gy_emp_code)', 'gy_emp_code'],
    ];
    foreach ($singleCases as [$method, $parentId, $join, $relatedKey]) {
        $pdo = new RecordingPdo();
        $pdo->fetchResult = ['_parent_id' => $parentId, '_related_id' => 7, $relatedKey => 'safe'];
        $result = (new ToolRelationshipRepository($pdo))->{$method}($parentId);
        assertSameValue(1, $pdo->prepareCalls, "{$method} executed more than one query.");
        assertTrue(str_contains((string) $pdo->query, $join), "{$method} used the wrong join.");
        assertTrue(!str_contains(strtoupper((string) $pdo->query), 'SELECT *'), "{$method} used SELECT *.");
        assertSameValue(['value' => $parentId, 'type' => PDO::PARAM_INT], $pdo->boundValues[':parent_id'] ?? null, "{$method} did not bind its parent.");
        assertSameValue([$relatedKey => 'safe'], $result['data'], "{$method} exposed an internal marker.");

        $query = (string) $pdo->query;
        assertTrue(!str_contains($query, 'team_toollist') && !str_contains($query, 'team_collist') && !str_contains($query, 'team_data'), "{$method} crossed into the team tool model.");
    }

    $employeePdo = new RecordingPdo();
    $employeePdo->fetchResult = ['_parent_id' => 103, '_related_id' => 7, 'gy_emp_code' => 'test1'];
    (new ToolRelationshipRepository($employeePdo))->findEmployeeByDataId(103);
    $employeeProjection = substr((string) $employeePdo->query, 0, strpos((string) $employeePdo->query, ' FROM '));
    foreach (['gy_emp_code', 'gy_emp_email', 'gy_emp_lname', 'gy_emp_fname', 'gy_emp_mname', 'gy_emp_fullname', 'gy_last_working_day'] as $field) {
        assertTrue(str_contains($employeeProjection, $field), "Tool data employee omitted safe field {$field}.");
    }
    foreach (['password', 'secret', 'token', 'gy_username'] as $forbidden) {
        assertTrue(!str_contains(strtolower($employeeProjection), $forbidden), "Tool data employee exposed {$forbidden}.");
    }
});

test('Batch 9 generic tool pagination uses actual IDs and all relationship routes require JWT', function (): void {
    $paginationPdo = new RecordingPdo();
    for ($index = 0; $index < 101; $index++) {
        $paginationPdo->fetchAllResult[] = ['_parent_id' => 101, 'toold_id' => 300 + ($index * 4)];
    }
    $controller = new ToolRelationshipController(
        static fn (): ToolRelationshipRepository => new ToolRelationshipRepository($paginationPdo)
    );
    $response = $controller->toolDetails(
        (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/tools/101/details?after_id=-9')->withQueryParams(['after_id' => '-9']),
        (new ResponseFactory())->createResponse(),
        ['tool_id' => '101']
    );
    $payload = responseJson($response);
    assertSameValue(100, count($payload['data']), 'Tool details relationship returned more than 100 rows.');
    assertSameValue(696, $payload['pagination']['next_cursor'], 'Tool details relationship did not use the actual last toold_id.');
    assertSameValue(true, $payload['pagination']['has_more'], 'Tool details relationship did not detect row 101.');
    assertSameValue(0, $payload['pagination']['current_cursor'], 'Negative cursor was not normalized.');

    $keys = testRsaKeys();
    $jwtService = new JwtService('kronos-api', 'kronos-api-clients', 3600, $keys['private_path'], $keys['public_path']);
    $calls = 0;
    $pdos = [];
    $factory = static function () use (&$calls, &$pdos): ToolRelationshipRepository {
        $calls++;
        $pdo = new RecordingPdo();
        $pdo->fetchAllResult = [['_parent_id' => 101, 'toold_id' => 102, 'td_id' => 103]];
        $pdo->fetchResult = ['_parent_id' => 103, '_related_id' => 101, 'tool_id' => 101, 'toold_id' => 102, 'gy_emp_code' => 'test1'];
        $pdos[] = $pdo;
        return new ToolRelationshipRepository($pdo);
    };
    $app = AppFactory::create();
    $routes = require __DIR__ . '/../routes/api.php';
    $routes(app: $app, serviceFactory: static fn (): ApiClientService => new ApiClientService(new RecordingPdo()), apiTokenServiceFactory: static fn (): ApiTokenService => new ApiTokenService(new RecordingPdo()), jwtServiceFactory: static fn (): JwtService => $jwtService, toolRelationshipRepositoryFactory: $factory);
    $app->addRoutingMiddleware();
    $paths = [
        '/api/v1/employees/test1/tool-data',
        '/api/v1/tools/101/details',
        '/api/v1/tool-details/102/tool',
        '/api/v1/tool-details/102/data',
        '/api/v1/tool-data/103/tool-detail',
        '/api/v1/tool-data/103/employee',
    ];
    foreach ($paths as $path) {
        assertSameValue(401, $app->handle((new ServerRequestFactory())->createServerRequest('GET', $path))->getStatusCode(), "{$path} is not JWT protected.");
    }
    assertSameValue(0, $calls, 'Tool relationship repository ran before authorization.');

    $auth = 'Bearer ' . $jwtService->issue(['id' => 7, 'client_name' => 'SiBS HRIS']);
    foreach ($paths as $path) {
        $request = (new ServerRequestFactory())->createServerRequest('GET', $path)->withHeader('Authorization', $auth);
        assertSameValue(200, $app->handle($request)->getStatusCode(), "Authorized {$path} failed.");
    }
    assertSameValue(6, $calls, 'Tool relationship routes did not dispatch once each.');

    foreach ($pdos as $pdo) {
        $query = (string) $pdo->query;
        assertTrue(!str_contains($query, 'team_toollist') && !str_contains($query, 'team_collist') && !str_contains($query, 'team_data'), 'Generic tool relationship crossed into the team tool model.');
    }
});

test('Batch 10 standalone resources use one explicit prepared lookup', function (): void {
    $cases = [
        [
            'findRequestById',
            111,
            ':request_id',
            'SELECT gy_req_id, gy_req_code, gy_req_date, gy_req_status, gy_req_by, gy_emp_code, '
                . 'gy_emp_fullname, gy_sched_day, gy_sched_mode, gy_sched_login, gy_sched_breakout, '
                . 'gy_sched_breakin, gy_sched_logout, gy_req_reason FROM gy_request '
                . 'WHERE gy_req_id = :request_id LIMIT 1',
        ],
        [
            'findTemporarySupervisorById',
            112,
            ':temporary_supervisor_id',
            'SELECT temp_sup_id, temp_sup_code, temp_sup_date, temp_sup_by FROM gy_temp_sup '
                . 'WHERE temp_sup_id = :temporary_supervisor_id LIMIT 1',
        ],
        [
            'findDobRegistrationById',
            113,
            ':dob_registration_id',
            'SELECT dob_id, dob_reg_for, dob_reg_from, dob_message, dob_read, dob_date FROM dob_reg '
                . 'WHERE dob_id = :dob_registration_id LIMIT 1',
        ],
        [
            'findWhitelistEntryById',
            114,
            ':whitelist_id',
            'SELECT id, sibs_id, ip, details FROM gy_whitelist WHERE id = :whitelist_id LIMIT 1',
        ],
        [
            'findReasonById',
            115,
            ':reason_id',
            'SELECT gy_reason_id, gy_reason_name FROM gy_reason WHERE gy_reason_id = :reason_id LIMIT 1',
        ],
    ];

    foreach ($cases as [$method, $id, $placeholder, $expectedQuery]) {
        $pdo = new RecordingPdo();
        $pdo->fetchResult = ['resource_id' => $id];
        $result = (new IndividualResourceRepository($pdo))->{$method}($id);
        assertSameValue(1, $pdo->prepareCalls, "{$method} executed more than one query.");
        assertSameValue($expectedQuery, $pdo->query, "{$method} used the wrong field allowlist or lookup.");
        assertTrue(!str_contains(strtoupper((string) $pdo->query), 'SELECT *'), "{$method} used SELECT *.");
        assertSameValue(['value' => $id, 'type' => PDO::PARAM_INT], $pdo->boundValues[$placeholder] ?? null, "{$method} did not bind its identifier.");
        assertSameValue(['resource_id' => $id], $result, "{$method} returned the wrong record.");
    }
});

test('Batch 10 standalone routes validate identifiers and are JWT protected', function (): void {
    $invalidCases = [
        ['request', ['gy_req_id' => '0']],
        ['temporarySupervisor', ['temp_sup_id' => '-1']],
        ['dobRegistration', ['dob_id' => 'bad']],
        ['whitelistEntry', ['id' => '0']],
        ['reason', ['gy_reason_id' => 'invalid']],
    ];
    foreach ($invalidCases as [$method, $arguments]) {
        $created = false;
        $controller = new IndividualResourceController(
            static function () use (&$created): IndividualResourceRepository {
                $created = true;
                return new IndividualResourceRepository(new RecordingPdo());
            }
        );
        $response = $controller->{$method}(
            (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/resource/invalid'),
            (new ResponseFactory())->createResponse(),
            $arguments
        );
        assertSameValue(400, $response->getStatusCode(), "{$method} accepted an invalid identifier.");
        assertSameValue(false, $created, "{$method} queried before validation.");
    }

    $keys = testRsaKeys();
    $jwtService = new JwtService('kronos-api', 'kronos-api-clients', 3600, $keys['private_path'], $keys['public_path']);
    $calls = 0;
    $pdos = [];
    $factory = static function () use (&$calls, &$pdos): IndividualResourceRepository {
        $calls++;
        $pdo = new RecordingPdo();
        $pdo->fetchResult = ['resource_id' => 1];
        $pdos[] = $pdo;
        return new IndividualResourceRepository($pdo);
    };
    $app = AppFactory::create();
    $routes = require __DIR__ . '/../routes/api.php';
    $routes(app: $app, serviceFactory: static fn (): ApiClientService => new ApiClientService(new RecordingPdo()), apiTokenServiceFactory: static fn (): ApiTokenService => new ApiTokenService(new RecordingPdo()), jwtServiceFactory: static fn (): JwtService => $jwtService, individualResourceRepositoryFactory: $factory);
    $app->addRoutingMiddleware();
    $paths = [
        ['/api/v1/requests/111', 'FROM gy_request'],
        ['/api/v1/temporary-supervisors/112', 'FROM gy_temp_sup'],
        ['/api/v1/dob-registrations/113', 'FROM dob_reg'],
        ['/api/v1/whitelist/114', 'FROM gy_whitelist'],
        ['/api/v1/reasons/115', 'FROM gy_reason'],
    ];

    foreach ($paths as [$path]) {
        assertSameValue(401, $app->handle((new ServerRequestFactory())->createServerRequest('GET', $path))->getStatusCode(), "{$path} is not JWT protected.");
    }
    assertSameValue(0, $calls, 'Batch 10 repository ran before authorization.');

    $auth = 'Bearer ' . $jwtService->issue(['id' => 7, 'client_name' => 'SiBS HRIS']);
    foreach ($paths as $index => [$path, $tableFragment]) {
        $request = (new ServerRequestFactory())->createServerRequest('GET', $path)->withHeader('Authorization', $auth);
        assertSameValue(200, $app->handle($request)->getStatusCode(), "Authorized {$path} failed.");
        assertTrue(str_contains((string) $pdos[$index]->query, $tableFragment), "{$path} dispatched to the wrong lookup.");
    }
    assertSameValue(5, $calls, 'Batch 10 routes did not dispatch exactly once each.');
});

test('Batch 10 missing standalone resources return sanitized 404 responses', function (): void {
    $cases = [
        ['request', ['gy_req_id' => '111']],
        ['temporarySupervisor', ['temp_sup_id' => '112']],
        ['dobRegistration', ['dob_id' => '113']],
        ['whitelistEntry', ['id' => '114']],
        ['reason', ['gy_reason_id' => '115']],
    ];

    foreach ($cases as [$method, $arguments]) {
        $pdo = new RecordingPdo();
        $pdo->fetchResult = false;
        $controller = new IndividualResourceController(
            static fn (): IndividualResourceRepository => new IndividualResourceRepository($pdo)
        );
        $response = $controller->{$method}(
            (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/resource/missing'),
            (new ResponseFactory())->createResponse(),
            $arguments
        );
        assertSameValue(404, $response->getStatusCode(), "{$method} did not return 404 for a missing record.");
        assertSameValue(['success' => false, 'message' => 'Resource not found.'], responseJson($response), "{$method} exposed unexpected details.");
    }
});
