<?php

declare(strict_types=1);

namespace OCA\DoorEstimator\Migration;

use OCA\DoorEstimator\Service\ConfigurationService;
use OCA\DoorEstimator\Service\DeploymentService;
use OCA\DoorEstimator\Service\HealthMonitoringService;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Psr\Log\LoggerInterface;

/**
 * Repair step for Door Estimator app
 * 
 * Runs after migrations to ensure proper app setup and configuration.
 * This step is executed during app installation and upgrades.
 */
class RepairStep implements IRepairStep {
    
    public function __construct(
        private ConfigurationService $configService,
        private DeploymentService $deploymentService,
        private HealthMonitoringService $healthService,
        private LoggerInterface $logger
    ) {}
    
    /**
     * Get the name of the repair step
     */
    public function getName(): string {
        return 'Door Estimator post-migration setup';
    }
    
    /**
     * Run the repair step
     */
    public function run(IOutput $output): void {
        $output->info('Running Door Estimator post-migration setup...');
        
        try {
            // Initialize default configuration
            $this->initializeConfiguration($output);
            
            // Verify system health
            $this->verifySystemHealth($output);
            
            // Update installation status
            $this->updateInstallationStatus($output);
            
            $output->info('Door Estimator post-migration setup completed successfully');
            
        } catch (\Exception $e) {
            $this->logger->error('Post-migration setup failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $output->warning('Door Estimator post-migration setup failed: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Initialize default configuration values
     */
    private function initializeConfiguration(IOutput $output): void {
        $output->info('Initializing default configuration...');
        
        try {
            $this->configService->initializeDefaults();
            
            // Validate configuration
            $errors = $this->configService->validateConfiguration();
            if (!empty($errors)) {
                $output->warning('Configuration validation found issues: ' . implode(', ', $errors));
            }
            
            $output->info('Configuration initialized successfully');
            
        } catch (\Exception $e) {
            $output->warning('Configuration initialization failed: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Verify system health after migration
     */
    private function verifySystemHealth(IOutput $output): void {
        $output->info('Verifying system health...');
        
        try {
            $healthCheck = $this->healthService->performHealthCheck();
            
            if ($healthCheck['status'] === 'error') {
                $errorChecks = array_filter($healthCheck['checks'], fn($check) => $check['status'] === 'error');
                $errorMessages = array_map(fn($check) => $check['message'], $errorChecks);
                
                $output->warning('Health check found errors: ' . implode(', ', $errorMessages));
                
                // Don't throw exception for health check failures during migration
                // as some issues might be temporary or non-critical
            } elseif ($healthCheck['status'] === 'warning') {
                $warningChecks = array_filter($healthCheck['checks'], fn($check) => $check['status'] === 'warning');
                $warningMessages = array_map(fn($check) => $check['message'], $warningChecks);
                
                $output->info('Health check found warnings: ' . implode(', ', $warningMessages));
            } else {
                $output->info('System health check passed');
            }
            
        } catch (\Exception $e) {
            $output->warning('Health check failed: ' . $e->getMessage());
            // Don't throw exception for health check failures
        }
    }
    
    /**
     * Update installation status
     */
    private function updateInstallationStatus(IOutput $output): void {
        $output->info('Updating installation status...');
        
        try {
            $status = $this->deploymentService->getInstallationStatus();
            
            if ($status['status'] === 'unknown') {
                // This is likely a fresh installation
                $result = $this->deploymentService->install();
                
                if ($result['success']) {
                    $output->info('Installation completed successfully');
                } else {
                    $output->warning('Installation had issues: ' . $result['message']);
                }
            } elseif ($this->deploymentService->isUpgradeNeeded()) {
                // This is an upgrade
                $result = $this->deploymentService->upgrade($status['version']);
                
                if ($result['success']) {
                    $output->info('Upgrade completed successfully from ' . $result['from_version'] . ' to ' . $result['to_version']);
                } else {
                    $output->warning('Upgrade had issues: ' . $result['message']);
                }
            } else {
                $output->info('App is up to date (version ' . $status['version'] . ')');
            }
            
        } catch (\Exception $e) {
            $output->warning('Installation status update failed: ' . $e->getMessage());
            throw $e;
        }
    }
}