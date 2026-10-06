<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Sibs\KronosApi\Config\BatchTableConfig;
use Sibs\KronosApi\Controller\AccountController;
use Sibs\KronosApi\Controller\AnnouncementController;
use Sibs\KronosApi\Controller\AnnouncementRelationshipController;
use Sibs\KronosApi\Controller\AnnouncementViewController;
use Sibs\KronosApi\Controller\ApiClientController;
use Sibs\KronosApi\Controller\AuthController;
use Sibs\KronosApi\Controller\BatchTableController;
use Sibs\KronosApi\Controller\ConfirmationController;
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
use Sibs\KronosApi\Database\Database;
use Sibs\KronosApi\Environment;
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
use Sibs\KronosApi\Repository\EmployeeDirectoryRepository;
use Sibs\KronosApi\Repository\EmployeeRelationshipRepository;
use Sibs\KronosApi\Repository\EmployeeScheduleRepository;
use Sibs\KronosApi\Repository\EmployeeRepository;
use Sibs\KronosApi\Repository\IndividualResourceRepository;
use Sibs\KronosApi\Repository\HolidayRelationshipRepository;
use Sibs\KronosApi\Repository\HolidayViewRepository;
use Sibs\KronosApi\Repository\LeaveRelationshipRepository;
use Sibs\KronosApi\Repository\LeaveViewRepository;
use Sibs\KronosApi\Repository\QdsRelationshipRepository;
use Sibs\KronosApi\Repository\QdsViewRepository;
use Sibs\KronosApi\Repository\TeamToolRelationshipRepository;
use Sibs\KronosApi\Repository\ToolRelationshipRepository;
use Sibs\KronosApi\Repository\UserRepository;
use Sibs\KronosApi\Repository\WorkforceRelationshipRepository;
use Sibs\KronosApi\Repository\WorkforceViewRepository;
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
    ?\Closure $individualResourceRepositoryFactory = null,
    ?\Closure $employeeScheduleRepositoryFactory = null,
    ?\Closure $workforceRelationshipRepositoryFactory = null,
    ?\Closure $workforceViewRepositoryFactory = null,
    ?\Closure $leaveRelationshipRepositoryFactory = null,
    ?\Closure $leaveViewRepositoryFactory = null,
    ?\Closure $dtrRelationshipRepositoryFactory = null,
    ?\Closure $dtrViewRepositoryFactory = null,
    ?\Closure $announcementRelationshipRepositoryFactory = null,
    ?\Closure $announcementViewRepositoryFactory = null,
    ?\Closure $holidayRelationshipRepositoryFactory = null,
    ?\Closure $holidayViewRepositoryFactory = null,
    ?\Closure $qdsRelationshipRepositoryFactory = null,
    ?\Closure $qdsViewRepositoryFactory = null,
    ?\Closure $teamToolRelationshipRepositoryFactory = null,
    ?\Closure $toolRelationshipRepositoryFactory = null
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

    $employeeScheduleRepositoryFactory ??= static fn (): EmployeeScheduleRepository => new EmployeeScheduleRepository(
        Database::connect([
            'DB1_HOST' => Environment::get('DB1_HOST'),
            'DB1_PORT' => Environment::get('DB1_PORT'),
            'DB1_NAME' => Environment::get('DB1_NAME'),
            'DB1_USER' => Environment::get('DB1_USER'),
            'DB1_PASSWORD' => Environment::get('DB1_PASSWORD'),
        ])
    );
    $employeeScheduleController = new EmployeeScheduleController($employeeScheduleRepositoryFactory);

    $workforceRelationshipRepositoryFactory ??= static fn (): WorkforceRelationshipRepository => new WorkforceRelationshipRepository(
        Database::connect([
            'DB1_HOST' => Environment::get('DB1_HOST'),
            'DB1_PORT' => Environment::get('DB1_PORT'),
            'DB1_NAME' => Environment::get('DB1_NAME'),
            'DB1_USER' => Environment::get('DB1_USER'),
            'DB1_PASSWORD' => Environment::get('DB1_PASSWORD'),
        ])
    );
    $workforceRelationshipController = new WorkforceRelationshipController($workforceRelationshipRepositoryFactory);

    $workforceViewRepositoryFactory ??= static fn (): WorkforceViewRepository => new WorkforceViewRepository(
        Database::connect([
            'DB1_HOST' => Environment::get('DB1_HOST'),
            'DB1_PORT' => Environment::get('DB1_PORT'),
            'DB1_NAME' => Environment::get('DB1_NAME'),
            'DB1_USER' => Environment::get('DB1_USER'),
            'DB1_PASSWORD' => Environment::get('DB1_PASSWORD'),
        ])
    );
    $workforceViewController = new WorkforceViewController($workforceViewRepositoryFactory);

    $leaveRelationshipRepositoryFactory ??= static fn (): LeaveRelationshipRepository => new LeaveRelationshipRepository(
        Database::connect([
            'DB1_HOST' => Environment::get('DB1_HOST'),
            'DB1_PORT' => Environment::get('DB1_PORT'),
            'DB1_NAME' => Environment::get('DB1_NAME'),
            'DB1_USER' => Environment::get('DB1_USER'),
            'DB1_PASSWORD' => Environment::get('DB1_PASSWORD'),
        ])
    );
    $leaveRelationshipController = new LeaveRelationshipController($leaveRelationshipRepositoryFactory);

    $leaveViewRepositoryFactory ??= static fn (): LeaveViewRepository => new LeaveViewRepository(
        Database::connect([
            'DB1_HOST' => Environment::get('DB1_HOST'),
            'DB1_PORT' => Environment::get('DB1_PORT'),
            'DB1_NAME' => Environment::get('DB1_NAME'),
            'DB1_USER' => Environment::get('DB1_USER'),
            'DB1_PASSWORD' => Environment::get('DB1_PASSWORD'),
        ])
    );
    $leaveViewController = new LeaveViewController($leaveViewRepositoryFactory);

    $dtrRelationshipRepositoryFactory ??= static fn (): DtrRelationshipRepository => new DtrRelationshipRepository(
        Database::connect([
            'DB1_HOST' => Environment::get('DB1_HOST'), 'DB1_PORT' => Environment::get('DB1_PORT'),
            'DB1_NAME' => Environment::get('DB1_NAME'), 'DB1_USER' => Environment::get('DB1_USER'),
            'DB1_PASSWORD' => Environment::get('DB1_PASSWORD'),
        ])
    );
    $dtrRelationshipController = new DtrRelationshipController($dtrRelationshipRepositoryFactory);

    $dtrViewRepositoryFactory ??= static fn (): DtrViewRepository => new DtrViewRepository(
        Database::connect([
            'DB1_HOST' => Environment::get('DB1_HOST'), 'DB1_PORT' => Environment::get('DB1_PORT'),
            'DB1_NAME' => Environment::get('DB1_NAME'), 'DB1_USER' => Environment::get('DB1_USER'),
            'DB1_PASSWORD' => Environment::get('DB1_PASSWORD'),
        ])
    );
    $dtrViewController = new DtrViewController($dtrViewRepositoryFactory);

    $announcementRelationshipRepositoryFactory ??= static fn (): AnnouncementRelationshipRepository => new AnnouncementRelationshipRepository(
        Database::connect([
            'DB1_HOST' => Environment::get('DB1_HOST'), 'DB1_PORT' => Environment::get('DB1_PORT'),
            'DB1_NAME' => Environment::get('DB1_NAME'), 'DB1_USER' => Environment::get('DB1_USER'),
            'DB1_PASSWORD' => Environment::get('DB1_PASSWORD'),
        ])
    );
    $announcementRelationshipController = new AnnouncementRelationshipController(
        $announcementRelationshipRepositoryFactory
    );

    $announcementViewRepositoryFactory ??= static fn (): AnnouncementViewRepository => new AnnouncementViewRepository(
        Database::connect([
            'DB1_HOST' => Environment::get('DB1_HOST'), 'DB1_PORT' => Environment::get('DB1_PORT'),
            'DB1_NAME' => Environment::get('DB1_NAME'), 'DB1_USER' => Environment::get('DB1_USER'),
            'DB1_PASSWORD' => Environment::get('DB1_PASSWORD'),
        ])
    );
    $announcementViewController = new AnnouncementViewController($announcementViewRepositoryFactory);

    $holidayRelationshipRepositoryFactory ??= static fn (): HolidayRelationshipRepository => new HolidayRelationshipRepository(
        Database::connect([
            'DB1_HOST' => Environment::get('DB1_HOST'), 'DB1_PORT' => Environment::get('DB1_PORT'),
            'DB1_NAME' => Environment::get('DB1_NAME'), 'DB1_USER' => Environment::get('DB1_USER'),
            'DB1_PASSWORD' => Environment::get('DB1_PASSWORD'),
        ])
    );
    $holidayRelationshipController = new HolidayRelationshipController($holidayRelationshipRepositoryFactory);

    $holidayViewRepositoryFactory ??= static fn (): HolidayViewRepository => new HolidayViewRepository(
        Database::connect([
            'DB1_HOST' => Environment::get('DB1_HOST'), 'DB1_PORT' => Environment::get('DB1_PORT'),
            'DB1_NAME' => Environment::get('DB1_NAME'), 'DB1_USER' => Environment::get('DB1_USER'),
            'DB1_PASSWORD' => Environment::get('DB1_PASSWORD'),
        ])
    );
    $holidayViewController = new HolidayViewController($holidayViewRepositoryFactory);

    $qdsRelationshipRepositoryFactory ??= static fn (): QdsRelationshipRepository => new QdsRelationshipRepository(
        Database::connect([
            'DB1_HOST' => Environment::get('DB1_HOST'), 'DB1_PORT' => Environment::get('DB1_PORT'),
            'DB1_NAME' => Environment::get('DB1_NAME'), 'DB1_USER' => Environment::get('DB1_USER'),
            'DB1_PASSWORD' => Environment::get('DB1_PASSWORD'),
        ])
    );
    $qdsRelationshipController = new QdsRelationshipController($qdsRelationshipRepositoryFactory);

    $qdsViewRepositoryFactory ??= static fn (): QdsViewRepository => new QdsViewRepository(
        Database::connect([
            'DB1_HOST' => Environment::get('DB1_HOST'), 'DB1_PORT' => Environment::get('DB1_PORT'),
            'DB1_NAME' => Environment::get('DB1_NAME'), 'DB1_USER' => Environment::get('DB1_USER'),
            'DB1_PASSWORD' => Environment::get('DB1_PASSWORD'),
        ])
    );
    $qdsViewController = new QdsViewController($qdsViewRepositoryFactory);

    $teamToolRelationshipRepositoryFactory ??= static fn (): TeamToolRelationshipRepository => new TeamToolRelationshipRepository(
        Database::connect([
            'DB1_HOST' => Environment::get('DB1_HOST'), 'DB1_PORT' => Environment::get('DB1_PORT'),
            'DB1_NAME' => Environment::get('DB1_NAME'), 'DB1_USER' => Environment::get('DB1_USER'),
            'DB1_PASSWORD' => Environment::get('DB1_PASSWORD'),
        ])
    );
    $teamToolRelationshipController = new TeamToolRelationshipController($teamToolRelationshipRepositoryFactory);

    $toolRelationshipRepositoryFactory ??= static fn (): ToolRelationshipRepository => new ToolRelationshipRepository(
        Database::connect([
            'DB1_HOST' => Environment::get('DB1_HOST'), 'DB1_PORT' => Environment::get('DB1_PORT'),
            'DB1_NAME' => Environment::get('DB1_NAME'), 'DB1_USER' => Environment::get('DB1_USER'),
            'DB1_PASSWORD' => Environment::get('DB1_PASSWORD'),
        ])
    );
    $toolRelationshipController = new ToolRelationshipController($toolRelationshipRepositoryFactory);

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
        '/api/v1/employee-schedules',
        [$employeeScheduleController, 'index']
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
        ['/api/v1/schedule/{gy_sched_id}', 'schedule'],
        ['/api/v1/schedule-escalations/{gy_sched_esc_id}', 'scheduleEscalation'],
        ['/api/v1/schedule-rd-requests/{gy_rd_id}', 'scheduleRdRequest'],
        ['/api/v1/trackers/{gy_tracker_id}', 'tracker'],
        ['/api/v1/escalations/{gy_esc_id}', 'escalation'],
        ['/api/v1/leaves/{gy_leave_id}', 'leave'],
        ['/api/v1/leave-availability/{gy_leave_avail_id}', 'leaveAvailability'],
        ['/api/v1/leave-credit-history/{lch_id}', 'leaveCreditHistory'],
        ['/api/v1/employee-logs/{gy_log_id}', 'employeeLog'],
        ['/api/v1/employee-edit-logs/{gy_editlog_id}', 'employeeEditLog'],
        ['/api/v1/dtr-publish/{dtr_publish_id}', 'dtrPublish'],
        ['/api/v1/timesheet-assignments/{at_id}', 'timesheetAssignment'],
        ['/api/v1/announcements/{gy_ann_id}', 'announcement'],
        ['/api/v1/confirmations/{gy_conf_id}', 'confirmation'],
        ['/api/v1/notifications/{gy_notif_id}', 'notification'],
        ['/api/v1/holiday-types/{gy_hol_type_id}', 'holidayType'],
        ['/api/v1/holidays/{gy_hol_id}', 'holiday'],
        ['/api/v1/qds-assign-groups/{qag_id}', 'qdsAssignGroup'],
        ['/api/v1/qds-query-keys/{qdsqk_id}', 'qdsQueryKey'],
        ['/api/v1/team-tools/{team_id}', 'teamTool'],
        ['/api/v1/team-columns/{col_id}', 'teamColumn'],
        ['/api/v1/team-data/{data_id}', 'teamData'],
        ['/api/v1/tools/{tool_id}', 'tool'],
        ['/api/v1/tool-details/{toold_id}', 'toolDetail'],
        ['/api/v1/tool-data/{td_id}', 'toolData'],
        ['/api/v1/requests/{gy_req_id}', 'request'],
        ['/api/v1/temporary-supervisors/{temp_sup_id}', 'temporarySupervisor'],
        ['/api/v1/dob-registrations/{dob_id}', 'dobRegistration'],
        ['/api/v1/whitelist/{id}', 'whitelistEntry'],
        ['/api/v1/reasons/{gy_reason_id}', 'reason'],
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
        ['/api/v1/employees/{employeeCode}/schedules', 'employeeSchedules'],
        ['/api/v1/employees/{employeeCode}/schedule-escalations', 'employeeScheduleEscalations'],
        ['/api/v1/schedule-escalations/{scheduleEscalationId}/employee', 'scheduleEscalationEmployee'],
        ['/api/v1/users/{employeeCode}/schedule-rd-requests', 'userScheduleRdRequests'],
        ['/api/v1/users/{gy_emp_code}/created-schedules', 'userCreatedSchedules'],
        ['/api/v1/users/{gy_emp_code}/submitted-schedule-escalations', 'userSubmittedScheduleEscalations'],
        ['/api/v1/users/{gy_emp_code}/received-schedule-escalations', 'userReceivedScheduleEscalations'],
        ['/api/v1/users/{gy_emp_code}/supervised-schedule-escalations', 'userSupervisedScheduleEscalations'],
        ['/api/v1/schedules/{gy_sched_id}/employee', 'scheduleEmployee'],
        ['/api/v1/schedules/{gy_sched_id}/created-by-user', 'scheduleCreatedByUser'],
        ['/api/v1/schedule-escalations/{gy_sched_esc_id}/requested-by-user', 'scheduleEscalationRequestedByUser'],
        ['/api/v1/schedule-escalations/{gy_sched_esc_id}/requested-to-user', 'scheduleEscalationRequestedToUser'],
        ['/api/v1/schedule-escalations/{gy_sched_esc_id}/supervisor-user', 'scheduleEscalationSupervisorUser'],
        ['/api/v1/schedule-rd-requests/{gy_rd_id}/tracker', 'scheduleRdRequestTracker'],
        ['/api/v1/schedule-rd-requests/{gy_rd_id}/user', 'scheduleRdRequestUser'],
    ];

    foreach ($relationshipRoutes as [$path, $method]) {
        $app->get($path, [$employeeRelationshipController, $method])
            ->add(new JwtAuthMiddleware(
                $jwtService,
                $app->getResponseFactory()
            ));
    }

    $leaveRelationshipRoutes = [
        ['/api/v1/employees/{gy_emp_code}/leave-credit-history', 'employeeLeaveCreditHistory'],
        ['/api/v1/users/{gy_emp_code}/leaves', 'userLeaves'],
        ['/api/v1/users/{gy_emp_code}/approved-leaves', 'userApprovedLeaves'],
        ['/api/v1/users/{gy_emp_code}/leave-availability', 'userLeaveAvailability'],
        ['/api/v1/users/{gy_emp_code}/leave-credit-history-updated', 'userUpdatedLeaveCreditHistory'],
        ['/api/v1/accounts/{gy_acc_id}/leaves', 'accountLeaves'],
        ['/api/v1/accounts/{gy_acc_id}/leave-availability', 'accountLeaveAvailability'],
        ['/api/v1/leaves/{gy_leave_id}/user', 'leaveUser'],
        ['/api/v1/leaves/{gy_leave_id}/account', 'leaveAccount'],
        ['/api/v1/leaves/{gy_leave_id}/approver-user', 'leaveApproverUser'],
        ['/api/v1/leave-availability/{gy_leave_avail_id}/user', 'leaveAvailabilityUser'],
        ['/api/v1/leave-availability/{gy_leave_avail_id}/account', 'leaveAvailabilityAccount'],
        ['/api/v1/leave-credit-history/{lch_id}/employee', 'leaveCreditHistoryEmployee'],
        ['/api/v1/leave-credit-history/{lch_id}/updated-by-user', 'leaveCreditHistoryUpdatedByUser'],
    ];
    foreach ($leaveRelationshipRoutes as [$path, $method]) {
        $app->get($path, [$leaveRelationshipController, $method])->add(new JwtAuthMiddleware(
            $jwtService,
            $app->getResponseFactory()
        ));
    }

    $dtrRelationshipRoutes = [
        ['/api/v1/employees/{gy_emp_code}/logs', 'employeeLogs'],
        ['/api/v1/employees/{gy_emp_code}/edit-logs', 'employeeEditLogs'],
        ['/api/v1/employees/{gy_emp_code}/dtr-publish', 'employeeDtrPublish'],
        ['/api/v1/employees/{gy_emp_code}/timesheet-assignments', 'employeeAssignments'],
        ['/api/v1/users/{gy_emp_code}/dtr-publications', 'userDtrPublications'],
        ['/api/v1/users/{gy_emp_code}/timesheet-assignments-added', 'userAssignmentsAdded'],
        ['/api/v1/accounts/{gy_acc_id}/timesheet-assignments', 'accountAssignments'],
        ['/api/v1/employee-logs/{gy_log_id}/employee', 'logEmployee'],
        ['/api/v1/employee-edit-logs/{gy_editlog_id}/employee', 'editLogEmployee'],
        ['/api/v1/dtr-publish/{dtr_publish_id}/employee', 'dtrEmployee'],
        ['/api/v1/dtr-publish/{dtr_publish_id}/publisher-user', 'dtrPublisherUser'],
        ['/api/v1/timesheet-assignments/{at_id}/employee', 'assignmentEmployee'],
        ['/api/v1/timesheet-assignments/{at_id}/account', 'assignmentAccount'],
        ['/api/v1/timesheet-assignments/{at_id}/added-by-user', 'assignmentAddedByUser'],
    ];
    foreach ($dtrRelationshipRoutes as [$path, $method]) {
        $app->get($path, [$dtrRelationshipController, $method])->add(new JwtAuthMiddleware(
            $jwtService,
            $app->getResponseFactory()
        ));
    }

    $announcementRelationshipRoutes = [
        ['/api/v1/users/{gy_emp_code}/announcements', 'userAnnouncements'],
        ['/api/v1/users/{gy_emp_code}/confirmations', 'userConfirmations'],
        ['/api/v1/users/{gy_emp_code}/notifications', 'userNotifications'],
        ['/api/v1/announcements/{gy_ann_id}/created-by-user', 'announcementCreatedByUser'],
        ['/api/v1/announcements/{gy_ann_id}/confirmations', 'announcementConfirmations'],
        ['/api/v1/confirmations/{gy_conf_id}/announcement', 'confirmationAnnouncement'],
        ['/api/v1/confirmations/{gy_conf_id}/user', 'confirmationUser'],
        ['/api/v1/notifications/{gy_notif_id}/user', 'notificationUser'],
    ];
    foreach ($announcementRelationshipRoutes as [$path, $method]) {
        $app->get($path, [$announcementRelationshipController, $method])->add(new JwtAuthMiddleware(
            $jwtService,
            $app->getResponseFactory()
        ));
    }

    $app->get('/api/v1/announcement-feed', [$announcementViewController, 'index'])->add(new JwtAuthMiddleware(
        $jwtService,
        $app->getResponseFactory()
    ));

    $holidayRelationshipRoutes = [
        ['/api/v1/holiday-types/{gy_hol_type_id}/holidays', 'typeHolidays'],
        ['/api/v1/holidays/{gy_hol_id}/type', 'holidayType'],
    ];
    foreach ($holidayRelationshipRoutes as [$path, $method]) {
        $app->get($path, [$holidayRelationshipController, $method])->add(new JwtAuthMiddleware(
            $jwtService,
            $app->getResponseFactory()
        ));
    }

    $app->get('/api/v1/holiday-calendar-view', [$holidayViewController, 'index'])->add(new JwtAuthMiddleware(
        $jwtService,
        $app->getResponseFactory()
    ));

    $qdsRelationshipRoutes = [
        ['/api/v1/employees/{gy_emp_code}/qds-assign-groups', 'employeeAssignments'],
        ['/api/v1/accounts/{gy_acc_id}/qds-assign-groups', 'accountAssignments'],
        ['/api/v1/qds-assign-groups/{qag_id}/employee', 'assignmentEmployee'],
        ['/api/v1/qds-assign-groups/{qag_id}/account', 'assignmentAccount'],
    ];
    foreach ($qdsRelationshipRoutes as [$path, $method]) {
        $app->get($path, [$qdsRelationshipController, $method])->add(new JwtAuthMiddleware(
            $jwtService,
            $app->getResponseFactory()
        ));
    }

    $app->get('/api/v1/qds-assignment-view', [$qdsViewController, 'index'])->add(new JwtAuthMiddleware(
        $jwtService,
        $app->getResponseFactory()
    ));

    $teamToolRelationshipRoutes = [
        ['/api/v1/team-tools/{team_id}/columns', 'teamToolColumns'],
        ['/api/v1/team-tools/{team_id}/data', 'teamToolData'],
        ['/api/v1/team-columns/{col_id}/team-tool', 'columnTeamTool'],
        ['/api/v1/team-columns/{col_id}/data', 'columnData'],
        ['/api/v1/team-data/{data_id}/team-tool', 'dataTeamTool'],
        ['/api/v1/team-data/{data_id}/column', 'dataColumn'],
    ];
    foreach ($teamToolRelationshipRoutes as [$path, $method]) {
        $app->get($path, [$teamToolRelationshipController, $method])->add(new JwtAuthMiddleware(
            $jwtService,
            $app->getResponseFactory()
        ));
    }

    $toolRelationshipRoutes = [
        ['/api/v1/employees/{gy_emp_code}/tool-data', 'employeeData'],
        ['/api/v1/tools/{tool_id}/details', 'toolDetails'],
        ['/api/v1/tool-details/{toold_id}/tool', 'detailTool'],
        ['/api/v1/tool-details/{toold_id}/data', 'detailData'],
        ['/api/v1/tool-data/{td_id}/tool-detail', 'dataDetail'],
        ['/api/v1/tool-data/{td_id}/employee', 'dataEmployee'],
    ];
    foreach ($toolRelationshipRoutes as [$path, $method]) {
        $app->get($path, [$toolRelationshipController, $method])->add(new JwtAuthMiddleware(
            $jwtService,
            $app->getResponseFactory()
        ));
    }

    foreach ([
        ['/api/v1/employee-dtr', 'employeeDtr'],
        ['/api/v1/timesheet-assignment-view', 'timesheetAssignments'],
    ] as [$path, $method]) {
        $app->get($path, [$dtrViewController, $method])->add(new JwtAuthMiddleware(
            $jwtService,
            $app->getResponseFactory()
        ));
    }

    foreach ([
        ['/api/v1/employee-leaves', 'employeeLeaves'],
        ['/api/v1/leave-management', 'leaveManagement'],
    ] as [$path, $method]) {
        $app->get($path, [$leaveViewController, $method])->add(new JwtAuthMiddleware(
            $jwtService,
            $app->getResponseFactory()
        ));
    }

    $workforceRelationshipRoutes = [
        ['/api/v1/employees/{employeeCode}/supervisor-user', 'employeeSupervisorUser'],
        ['/api/v1/employees/{employeeCode}/operations-manager-user', 'employeeOperationsManagerUser'],
        ['/api/v1/employees/{employeeCode}/trackers', 'employeeTrackers'],
        ['/api/v1/users/{employeeCode}/supervised-employees', 'userSupervisedEmployees'],
        ['/api/v1/users/{employeeCode}/submitted-escalations', 'userSubmittedEscalations'],
        ['/api/v1/users/{employeeCode}/received-escalations', 'userReceivedEscalations'],
        ['/api/v1/users/{employeeCode}/supervised-escalations', 'userSupervisedEscalations'],
        ['/api/v1/accounts/{accountId}/trackers', 'accountTrackers'],
        ['/api/v1/trackers/{trackerId}/employee', 'trackerEmployee'],
        ['/api/v1/trackers/{trackerId}/account', 'trackerAccount'],
        ['/api/v1/trackers/{trackerId}/operations-manager-user', 'trackerOperationsManagerUser'],
        ['/api/v1/trackers/{trackerId}/escalations', 'trackerEscalations'],
        ['/api/v1/escalations/{escalationId}/tracker', 'escalationTracker'],
        ['/api/v1/escalations/{escalationId}/submitted-by-user', 'escalationSubmittedByUser'],
        ['/api/v1/escalations/{escalationId}/recipient-user', 'escalationRecipientUser'],
        ['/api/v1/escalations/{escalationId}/supervisor-user', 'escalationSupervisorUser'],
    ];
    foreach ($workforceRelationshipRoutes as [$path, $method]) {
        $app->get($path, [$workforceRelationshipController, $method])->add(new JwtAuthMiddleware(
            $jwtService,
            $app->getResponseFactory()
        ));
    }

    foreach ([
        ['/api/v1/employee-attendance', 'employeeAttendance'],
        ['/api/v1/employee-tracker-history', 'employeeTrackerHistory'],
        ['/api/v1/escalation-queue', 'escalationQueue'],
    ] as [$path, $method]) {
        $app->get($path, [$workforceViewController, $method])->add(new JwtAuthMiddleware(
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
