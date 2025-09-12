<?php

declare(strict_types=1);

namespace OCA\DoorEstimator\Settings;

use OCA\DoorEstimator\Service\ConfigurationService;
use OCA\DoorEstimator\Service\DeploymentService;
use OCA\DoorEstimator\Service\HealthMonitoringService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IL10N;
use OCP\Settings\ISettings;
use Psr\Log\LoggerInterface;

/**
 * Admin settings for Door Estimator app
 * 
 * Provides configuration interface for administrators to manage
 * app settings, monitor health, and perform maintenance tasks.
 */
class AdminSettings implements ISettings {
    
    public function __construct(
        private IConfig $config,
        private IL10N $l10n,
        private ConfigurationService $configService,
        private DeploymentService $deploymentService,
        private HealthMonitoringService $healthService,
        private LoggerInterface $logger
    ) {}
    
    /**
     * Get the admin settings form
     */
    public function getForm(): TemplateResponse {
        try {
            // Get current configuration
            $markupDefaults = $this->configService->getMarkupDefaults();
            $healthConfig = $this->configService->getHealthConfig();
            $securityConfig = $this->configService->getSecurityConfig();
            $featureFlags = $this->configService->getFeatureFlags();
            
            // Get system information
            $systemInfo = $this->healthService->getSystemInfo();
            $installationStatus = $this->deploymentService->getInstallationStatus();
            
            // Perform quick health check
            $healthCheck = $this->healthService->performHealthCheck();
            
            $templateParams = [
                // Configuration
                'markup_defaults' => $markupDefaults,
                'health_config' => $healthConfig,
                'security_config' => $securityConfig,
                'feature_flags' => $featureFlags,
                
                // System information
                'system_info' => $systemInfo,
                'installation_status' => $installationStatus,
                'health_check' => $healthCheck,
                
                // Settings metadata
                'max_file_size' => $this->configService->getAppValue('max_import_file_size'),
                'cache_timeout' => $this->configService->getAppValue('cache_timeout'),
                'pdf_timeout' => $this->configService->getAppValue('pdf_generation_timeout'),
                
                // Localization
                'l10n' => $this->l10n,
            ];
            
            return new TemplateResponse('door_estimator', 'admin-settings', $templateParams);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to load admin settings', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Return minimal template with error message
            return new TemplateResponse('door_estimator', 'admin-settings', [
                'error' => $this->l10n->t('Failed to load admin settings: %s', [$e->getMessage()]),
                'l10n' => $this->l10n,
            ]);
        }
    }
    
    /**
     * Get the settings section ID
     */
    public function getSection(): string {
        return 'door_estimator';
    }
    
    /**
     * Get the priority for this settings page
     */
    public function getPriority(): int {
        return 50;
    }
}