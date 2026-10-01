<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Sibs\KronosApi\Config\BatchTableConfig;
use Sibs\KronosApi\Controller\AccountController;
use Sibs\KronosApi\Controller\AnnouncementController;
use Sibs\KronosApi\Controller\ApiClientController;
use Sibs\KronosApi\Controller\AuthController;
use Sibs\KronosApi\Controller\BatchTableController;
use Sibs\KronosApi\Controller\ConfirmationController;
use Sibs\KronosApi\Controller\CronSettingController;
use Sibs\KronosApi\Controller\DepartmentController;
use Sibs\KronosApi\Controller\DobRecordController;
use Sibs\KronosApi\Controller\DtrPublishController;
use Sibs\KronosApi\Controller\EmployeeAccountAssignmentController;
use Sibs\KronosApi\Controller\EmployeeController;
use Sibs\KronosApi\Controller\EmployeeDirectoryController;
use Sibs\KronosApi\Controller\EmployeeRelationshipController;
use Sibs\KronosApi\Controller\IndividualResourceController;
use Sibs\KronosApi\Controller\UserController;
use Sibs\KronosApi\Database\Database;
use Sibs\KronosApi\Environment;
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
use Sibs\KronosApi\Repository\EmployeeDirectoryRepository;
use Sibs\KronosApi\Repository\EmployeeRelationshipRepository;
use Sibs\KronosApi\Repository\EmployeeRepository;
use Sibs\KronosApi\Repository\IndividualResourceRepository;
use Sibs\KronosApi\Repository\UserRepository;
use Sibs\KronosApi\Service\ApiClientService;
use Sibs\KronosApi\Service\ApiTokenService;
use Sibs\KronosApi\Service\JwtService;
use Slim\App;

return function (
    App $app,
    ?\Closure $serviceFactory = null,
    ?\Closure $apiTokenServiceFactory = null,
    ?\Closure $jwtServiceFactory = null,
    ?\Closure $employeeRepositoryFactory = null,
    ?\Closure $userRepositoryFactory = null,
    ?\Closure $accountRepositoryFactory = null,
    ?\Closure $departmentRepositoryFactory = null,
    ?\Closure $employeeAccountAssignmentRepositoryFactory = null,
    ?\Closure $cronSettingRepositoryFactory = null,
    ?\Closure $dobRecordRepositoryFactory = null,
    ?\Closure $dtrPublishRepositoryFactory = null,
    ?\Closure $announcementRepositoryFactory = null,
    ?\Closure $confirmationRepositoryFactory = null,
    ?\Closure $batchTableRepositoryFactory = null,
    ?\Closure $employeeDirectoryRepositoryFactory = null,
    ?\Closure $employeeRelationshipRepositoryFactory = null,
    ?\Closure $individualResourceRepositoryFactory = null
): void {

    $serviceFactory ??= static fn (): ApiClientService => new ApiClientService(
        Database::connect([
            'DB1_HOST' => Environment::get('DB1_HOST'),
            'DB1_PORT' => Environment::get('DB1_PORT'),
            'DB1_NAME' => Environment::get('DB1_NAME'),
            'DB1_USER' => Environment::get('DB1_USER'),
            'DB1_PASSWORD' => Environment::get('DB1_PASSWORD'),
        ])
    );

    $apiClientController = new ApiClientController($serviceFactory);

    $apiTokenServiceFactory ??= static fn (): ApiTokenService => new ApiTokenService(
        Database::connect([
            'DB1_HOST' => Environment::get('DB1_HOST'),
            'DB1_PORT' => Environment::get('DB1_PORT'),
            'DB1_NAME' => Environment::get('DB1_NAME'),
            'DB1_USER' => Environment::get('DB1_USER'),
            'DB1_PASSWORD' => Environment::get('DB1_PASSWORD'),
        ])
    );

    $accessTtl = (int) (Environment::get('JWT_ACCESS_TTL') ?? '3600');
    $jwtServiceFactory ??= static fn (): JwtService => new JwtService(
        Environment::get('JWT_ISSUER') ?? 'kronos-api',
        Environment::get('JWT_AUDIENCE') ?? 'kronos-api-clients',
        $accessTtl,
        Environment::get('JWT_PRIVATE_KEY') ?? 'storage/keys/jwt-private.pem',
        Environment::get('JWT_PUBLIC_KEY') ?? 'storage/keys/jwt-public.pem'
    );
    $jwtService = $jwtServiceFactory();
    $authController = new AuthController(
        $apiTokenServiceFactory,
        $jwtServiceFactory,
        $accessTtl
    );

    $employeeRepositoryFactory ??= static fn (): EmployeeRepository => new EmployeeRepository(
        Database::connect([
            'DB1_HOST' => Environment::get('DB1_HOST'),
            'DB1_PORT' => Environment::get('DB1_PORT'),
            'DB1_NAME' => Environment::get('DB1_NAME'),
            'DB1_USER' => Environment::get('DB1_USER'),
            'DB1_PASSWORD' => Environment::get('DB1_PASSWORD'),
        ])
    );
    $employeeController = new EmployeeController($employeeRepositoryFactory);

    $userRepositoryFactory ??= static fn (): UserRepository => new UserRepository(
        Database::connect([
            'DB1_HOST' => Environment::get('DB1_HOST'),
            'DB1_PORT' => Environment::get('DB1_PORT'),
            'DB1_NAME' => Environment::get('DB1_NAME'),
            'DB1_USER' => Environment::get('DB1_USER'),
            'DB1_PASSWORD' => Environment::get('DB1_PASSWORD'),
        ])
    );
    $userController = new UserController($userRepositoryFactory);

    $accountRepositoryFactory ??= static fn (): AccountRepository => new AccountRepository(
        Database::connect([
            'DB1_HOST' => Environment::get('DB1_HOST'),
            'DB1_PORT' => Environment::get('DB1_PORT'),
            'DB1_NAME' => Environment::get('DB1_NAME'),
            'DB1_USER' => Environment::get('DB1_USER'),
            'DB1_PASSWORD' => Environment::get('DB1_PASSWORD'),
        ])
    );
    $accountController = new AccountController($accountRepositoryFactory);

    $departmentRepositoryFactory ??= static fn (): DepartmentRepository => new DepartmentRepository(
        Database::connect([
            'DB1_HOST' => Environment::get('DB1_HOST'),
            'DB1_PORT' => Environment::get('DB1_PORT'),
            'DB1_NAME' => Environment::get('DB1_NAME'),
            'DB1_USER' => Environment::get('DB1_USER'),
            'DB1_PASSWORD' => Environment::get('DB1_PASSWORD'),
        ])
    );
    $departmentController = new DepartmentController($departmentRepositoryFactory);

    $employeeAccountAssignmentRepositoryFactory ??= static fn (): EmployeeAccountAssignmentRepository => new EmployeeAccountAssignmentRepository(
        Database::connect([
            'DB1_HOST' => Environment::get('DB1_HOST'),
            'DB1_PORT' => Environment::get('DB1_PORT'),
            'DB1_NAME' => Environment::get('DB1_NAME'),
            'DB1_USER' => Environment::get('DB1_USER'),
            'DB1_PASSWORD' => Environment::get('DB1_PASSWORD'),
        ])
    );
    $employeeAccountAssignmentController = new EmployeeAccountAssignmentController(
        $employeeAccountAssignmentRepositoryFactory
    );

    $cronSettingRepositoryFactory ??= static fn (): CronSettingRepository => new CronSettingRepository(
        Database::connect([
            'DB1_HOST' => Environment::get('DB1_HOST'),
            'DB1_PORT' => Environment::get('DB1_PORT'),
            'DB1_NAME' => Environment::get('DB1_NAME'),
            'DB1_USER' => Environment::get('DB1_USER'),
            'DB1_PASSWORD' => Environment::get('DB1_PASSWORD'),
        ])
    );
    $cronSettingController = new CronSettingController($cronSettingRepositoryFactory);

    $dobRecordRepositoryFactory ??= static fn (): DobRecordRepository => new DobRecordRepository(
        Database::connect([
            'DB1_HOST' => Environment::get('DB1_HOST'),
            'DB1_PORT' => Environment::get('DB1_PORT'),
            'DB1_NAME' => Environment::get('DB1_NAME'),
            'DB1_USER' => Environment::get('DB1_USER'),
            'DB1_PASSWORD' => Environment::get('DB1_PASSWORD'),
        ])
    );
    $dobRecordController = new DobRecordController($dobRecordRepositoryFactory);

    $dtrPublishRepositoryFactory ??= static fn (): DtrPublishRepository => new DtrPublishRepository(
        Database::connect([
            'DB1_HOST' => Environment::get('DB1_HOST'),
            'DB1_PORT' => Environment::get('DB1_PORT'),
            'DB1_NAME' => Environment::get('DB1_NAME'),
            'DB1_USER' => Environment::get('DB1_USER'),
            'DB1_PASSWORD' => Environment::get('DB1_PASSWORD'),
        ])
    );
    $dtrPublishController = new DtrPublishController($dtrPublishRepositoryFactory);

    $announcementRepositoryFactory ??= static fn (): AnnouncementRepository => new AnnouncementRepository(
        Database::connect([
            'DB1_HOST' => Environment::get('DB1_HOST'),
            'DB1_PORT' => Environment::get('DB1_PORT'),
            'DB1_NAME' => Environment::get('DB1_NAME'),
            'DB1_USER' => Environment::get('DB1_USER'),
            'DB1_PASSWORD' => Environment::get('DB1_PASSWORD'),
        ])
    );
    $announcementController = new AnnouncementController($announcementRepositoryFactory);

    $confirmationRepositoryFactory ??= static fn (): ConfirmationRepository => new ConfirmationRepository(
        Database::connect([
            'DB1_HOST' => Environment::get('DB1_HOST'),
            'DB1_PORT' => Environment::get('DB1_PORT'),
            'DB1_NAME' => Environment::get('DB1_NAME'),
            'DB1_USER' => Environment::get('DB1_USER'),
            'DB1_PASSWORD' => Environment::get('DB1_PASSWORD'),
        ])
    );
    $confirmationController = new ConfirmationController($confirmationRepositoryFactory);

    $batchTableRepositoryFactory ??= static fn (array $configuration): BatchTableRepository => new BatchTableRepository(
        Database::connect([
            'DB1_HOST' => Environment::get('DB1_HOST'),
            'DB1_PORT' => Environment::get('DB1_PORT'),
            'DB1_NAME' => Environment::get('DB1_NAME'),
            'DB1_USER' => Environment::get('DB1_USER'),
            'DB1_PASSWORD' => Environment::get('DB1_PASSWORD'),
        ]),
        $configuration
    );

    $employeeDirectoryRepositoryFactory ??= static fn (): EmployeeDirectoryRepository => new EmployeeDirectoryRepository(
        Database::connect([
            'DB1_HOST' => Environment::get('DB1_HOST'),
            'DB1_PORT' => Environment::get('DB1_PORT'),
            'DB1_NAME' => Environment::get('DB1_NAME'),
            'DB1_USER' => Environment::get('DB1_USER'),
            'DB1_PASSWORD' => Environment::get('DB1_PASSWORD'),
        ])
    );
    $employeeDirectoryController = new EmployeeDirectoryController(
        $employeeDirectoryRepositoryFactory
    );

    $employeeRelationshipRepositoryFactory ??= static fn (): EmployeeRelationshipRepository => new EmployeeRelationshipRepository(
        Database::connect([
            'DB1_HOST' => Environment::get('DB1_HOST'),
            'DB1_PORT' => Environment::get('DB1_PORT'),
            'DB1_NAME' => Environment::get('DB1_NAME'),
            'DB1_USER' => Environment::get('DB1_USER'),
            'DB1_PASSWORD' => Environment::get('DB1_PASSWORD'),
        ])
    );
    $employeeRelationshipController = new EmployeeRelationshipController(
        $employeeRelationshipRepositoryFactory
    );

    $individualResourceRepositoryFactory ??= static fn (): IndividualResourceRepository => new IndividualResourceRepository(
        Database::connect([
            'DB1_HOST' => Environment::get('DB1_HOST'),
            'DB1_PORT' => Environment::get('DB1_PORT'),
            'DB1_NAME' => Environment::get('DB1_NAME'),
            'DB1_USER' => Environment::get('DB1_USER'),
            'DB1_PASSWORD' => Environment::get('DB1_PASSWORD'),
        ])
    );
    $individualResourceController = new IndividualResourceController(
        $individualResourceRepositoryFactory
    );

    $app->post(
        '/api/v1/api-clients',
        [$apiClientController, 'create']
    )->add(new AdminSetupKeyMiddleware(
        Environment::get('API_ADMIN_SETUP_KEY') ?? '',
        $app->getResponseFactory()
    ));

    $app->post('/api/v1/auth/token', [$authController, 'issue']);

    $app->get('/api/v1/protected-test', function (
        Request $request,
        Response $response
    ): Response {
        $response->getBody()->write((string) json_encode([
            'success' => true,
            'message' => 'JWT is valid.',
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    })->add(new JwtAuthMiddleware(
        $jwtService,
        $app->getResponseFactory()
    ));

    $app->get(
        '/api/v1/employees',
        [$employeeController, 'index']
    )->add(new JwtAuthMiddleware(
        $jwtService,
        $app->getResponseFactory()
    ));

    $app->get(
        '/api/v1/employee-directory',
        [$employeeDirectoryController, 'index']
    )->add(new JwtAuthMiddleware(
        $jwtService,
        $app->getResponseFactory()
    ));

    $app->get(
        '/api/v1/users',
        [$userController, 'index']
    )->add(new JwtAuthMiddleware(
        $jwtService,
        $app->getResponseFactory()
    ));

    $app->get(
        '/api/v1/accounts',
        [$accountController, 'index']
    )->add(new JwtAuthMiddleware(
        $jwtService,
        $app->getResponseFactory()
    ));

    $app->get(
        '/api/v1/departments',
        [$departmentController, 'index']
    )->add(new JwtAuthMiddleware(
        $jwtService,
        $app->getResponseFactory()
    ));

    $individualResourceRoutes = [
        ['/api/v1/employees/{gy_emp_code}', 'employee'],
        ['/api/v1/accounts/{gy_acc_id}', 'account'],
        ['/api/v1/departments/{id_department}', 'department'],
        ['/api/v1/users/{gy_emp_code}', 'user'],
    ];

    foreach ($individualResourceRoutes as [$path, $method]) {
        $app->get($path, [$individualResourceController, $method])
            ->add(new JwtAuthMiddleware(
                $jwtService,
                $app->getResponseFactory()
            ));
    }

    $relationshipRoutes = [
        ['/api/v1/employees/{employeeCode}/account', 'employeeAccount'],
        ['/api/v1/employees/{employeeCode}/department', 'employeeDepartment'],
        ['/api/v1/employees/{employeeCode}/user', 'employeeUser'],
        ['/api/v1/accounts/{accountId}/employees', 'accountEmployees'],
        ['/api/v1/accounts/{accountId}/department', 'accountDepartment'],
        ['/api/v1/departments/{departmentId}/accounts', 'departmentAccounts'],
        ['/api/v1/departments/{departmentId}/employees', 'departmentEmployees'],
        ['/api/v1/users/{employeeCode}/employee', 'userEmployee'],
    ];

    foreach ($relationshipRoutes as [$path, $method]) {
        $app->get($path, [$employeeRelationshipController, $method])
            ->add(new JwtAuthMiddleware(
                $jwtService,
                $app->getResponseFactory()
            ));
    }

    $app->get(
        '/api/v1/assign-timesheet',
        [$employeeAccountAssignmentController, 'index']
    )->add(new JwtAuthMiddleware(
        $jwtService,
        $app->getResponseFactory()
    ));

    $app->get(
        '/api/v1/cronjob',
        [$cronSettingController, 'index']
    )->add(new JwtAuthMiddleware(
        $jwtService,
        $app->getResponseFactory()
    ));

    $app->get(
        '/api/v1/dob-reg',
        [$dobRecordController, 'index']
    )->add(new JwtAuthMiddleware(
        $jwtService,
        $app->getResponseFactory()
    ));

    $app->get(
        '/api/v1/dtr-publish',
        [$dtrPublishController, 'index']
    )->add(new JwtAuthMiddleware(
        $jwtService,
        $app->getResponseFactory()
    ));

    $app->get(
        '/api/v1/announcements',
        [$announcementController, 'index']
    )->add(new JwtAuthMiddleware(
        $jwtService,
        $app->getResponseFactory()
    ));

    $app->get(
        '/api/v1/confirmations',
        [$confirmationController, 'index']
    )->add(new JwtAuthMiddleware(
        $jwtService,
        $app->getResponseFactory()
    ));

    foreach (BatchTableConfig::all() as $configuration) {
        $batchTableController = new BatchTableController(
            static fn (): BatchTableRepository => $batchTableRepositoryFactory($configuration),
            $configuration['primaryKey']
        );

        $app->get(
            '/api/v1/' . $configuration['route'],
            [$batchTableController, 'index']
        )->add(new JwtAuthMiddleware(
            $jwtService,
            $app->getResponseFactory()
        ));
    }

    $app->get('/', function (
        Request $request,
        Response $response
    ): Response {

        $data = [
            'success' => true,
            'message' => 'Kronos API is running'
        ];

        $response->getBody()->write(
            json_encode($data, JSON_PRETTY_PRINT)
        );

        return $response
            ->withHeader('Content-Type', 'application/json');
    });

    $app->get('/health', function (
        Request $request,
        Response $response
    ): Response {

        $data = [
            'success' => true,
            'service' => 'Kronos API',
            'status' => 'healthy',
            'timestamp' => date('c')
        ];

        $response->getBody()->write(
            json_encode($data, JSON_PRETTY_PRINT)
        );

        return $response
            ->withHeader('Content-Type', 'application/json');
    });
};
