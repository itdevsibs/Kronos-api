<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sibs\KronosApi\Controller\AccountController;
use Sibs\KronosApi\Controller\AnnouncementController;
use Sibs\KronosApi\Controller\BatchTableController;
use Sibs\KronosApi\Controller\ConfirmationController;
use Sibs\KronosApi\Controller\ApiClientController;
use Sibs\KronosApi\Controller\AuthController;
use Sibs\KronosApi\Controller\CronSettingController;
use Sibs\KronosApi\Controller\DepartmentController;
use Sibs\KronosApi\Controller\DobRecordController;
use Sibs\KronosApi\Controller\DtrPublishController;
use Sibs\KronosApi\Controller\EmployeeAccountAssignmentController;
use Sibs\KronosApi\Controller\EmployeeController;
use Sibs\KronosApi\Controller\UserController;
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
use Sibs\KronosApi\Repository\BatchTableRepository;
use Sibs\KronosApi\Repository\ConfirmationRepository;
use Sibs\KronosApi\Repository\CronSettingRepository;
use Sibs\KronosApi\Repository\DepartmentRepository;
use Sibs\KronosApi\Repository\DobRecordRepository;
use Sibs\KronosApi\Repository\DtrPublishRepository;
use Sibs\KronosApi\Repository\EmployeeAccountAssignmentRepository;
use Sibs\KronosApi\Repository\EmployeeRepository;
use Sibs\KronosApi\Repository\UserRepository;
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
