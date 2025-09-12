<?php
declare(strict_types=1);

namespace OCA\DoorEstimator\AppInfo;

use OCA\DoorEstimator\Controller\EstimatorController;
use OCA\DoorEstimator\Controller\PageController;
use OCA\DoorEstimator\Repository\EstimatorRepository;
use OCA\DoorEstimator\Service\EstimatorService;
use OCA\DoorEstimator\Service\AuthService;
use OCA\DoorEstimator\Service\ConfigurationService;
use OCA\DoorEstimator\Service\DeploymentService;
use OCA\DoorEstimator\Service\ErrorHandlerService;
use OCA\DoorEstimator\Service\HealthMonitoringService;
use OCA\DoorEstimator\Service\RetryService;
use OCA\DoorEstimator\Settings\AdminSettings;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Files\IAppData;
use OCP\ICacheFactory;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class Application extends App implements IBootstrap {
    public const APP_ID = 'door_estimator';

    public function __construct() {
        parent::__construct(self::APP_ID);
    }

    public function register(IRegistrationContext $context): void {
        // Register core services with dependency injection
        $context->registerService(EstimatorRepository::class, function ($c) {
            return new EstimatorRepository(
                $c->get(IDBConnection::class),
                $c->get(LoggerInterface::class)
            );
        });

        $context->registerService(ConfigurationService::class, function ($c) {
            return new ConfigurationService(
                $c->get(IConfig::class),
                $c->get(IUserSession::class),
                $c->get(LoggerInterface::class)
            );
        });

        $context->registerService(HealthMonitoringService::class, function ($c) {
            return new HealthMonitoringService(
                $c->get(IDBConnection::class),
                $c->get(IAppData::class),
                $c->get(ICacheFactory::class),
                $c->get(IConfig::class),
                $c->get(ConfigurationService::class),
                $c->get(LoggerInterface::class)
            );
        });

        $context->registerService(DeploymentService::class, function ($c) {
            return new DeploymentService(
                $c->get(IDBConnection::class),
                $c->get(IAppData::class),
                $c->get(IConfig::class),
                $c->get(ConfigurationService::class),
                $c->get(HealthMonitoringService::class),
                $c->get(LoggerInterface::class)
            );
        });

        $context->registerService(EstimatorService::class, function ($c) {
            return new EstimatorService(
                $c->get(EstimatorRepository::class),
                $c->get(IUserSession::class),
                $c->get(IAppData::class),
                $c->get(IConfig::class),
                $c->get(IDBConnection::class),
                $c->get(LoggerInterface::class),
                $c->get(ICacheFactory::class)
            );
        });

        $context->registerService(AuthService::class, function ($c) {
            return new AuthService(
                $c->get(IUserSession::class),
                $c->get(IGroupManager::class),
                $c->get(IConfig::class),
                $c->get(IRequest::class),
                $c->get(LoggerInterface::class)
            );
        });

        $context->registerService(ErrorHandlerService::class, function ($c) {
            return new ErrorHandlerService(
                $c->get(LoggerInterface::class)
            );
        });

        $context->registerService(RetryService::class, function ($c) {
            return new RetryService(
                $c->get(LoggerInterface::class)
            );
        });

        // EstimatorUtils is a static utility class, no registration needed

        // Register controllers
        $context->registerService(EstimatorController::class, function ($c) {
            return new EstimatorController(
                self::APP_ID,
                $c->get(IRequest::class),
                $c->get(EstimatorService::class),
                $c->get(LoggerInterface::class),
                $c->get(ICacheFactory::class),
                $c->get(IUserSession::class),
                $c->get(ErrorHandlerService::class),
                $c->get(RetryService::class),
                $c->get(AuthService::class)
            );
        });

        $context->registerService(PageController::class, function ($c) {
            return new PageController(
                self::APP_ID,
                $c->get('Request'),
                $c->get(IConfig::class),
                $c->get(IL10N::class)
            );
        });

        // Register settings
        $context->registerService(AdminSettings::class, function ($c) {
            return new AdminSettings(
                $c->get(IConfig::class),
                $c->get(IL10N::class),
                $c->get(ConfigurationService::class),
                $c->get(DeploymentService::class),
                $c->get(HealthMonitoringService::class),
                $c->get(LoggerInterface::class)
            );
        });

        // Register middleware for CSRF protection and authentication
        $context->registerMiddleware('OCA\DoorEstimator\Middleware\AuthMiddleware');
    }

    public function boot(IBootContext $context): void {
        // Initialize app data directory
        $appData = $context->getAppContainer()->get(IAppData::class);
        $requiredFolders = ['quotes', 'imports', 'exports', 'backups'];
        
        foreach ($requiredFolders as $folder) {
            try {
                $appData->getFolder($folder);
            } catch (\OCP\Files\NotFoundException $e) {
                $appData->newFolder($folder);
            }
        }

        // Initialize configuration using ConfigurationService
        $configService = $context->getAppContainer()->get(ConfigurationService::class);
        $configService->initializeDefaults();
    }
}