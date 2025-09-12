<?php

declare(strict_types=1);

namespace OCA\DoorEstimator\Service;

use OCP\IDBConnection;
use OCP\Files\IAppData;
use OCP\IConfig;
use OCP\Migration\IRepairStep;
use Psr\Log\LoggerInterface;

/**
 * Deployment service for Door Estimator app
 * 
 * Handles installation, upgrade, and maintenance procedures
 * following Nextcloud app standards.
 */
class DeploymentService {
    
    private const APP_ID = 'door_estimator';
    private const CURRENT_VERSION = '1.0.1';
    
    public function __construct(
        private IDBConnection $db,
        private IAppData $appData,
        private IConfig $config,
        private ConfigurationService $configService,
        private HealthMonitoringService $healthService,
        private LoggerInterface $logger
    ) {}
    
    /**
     * Perform initial app installation
     */
    public function install(): array {
        $this->logger->info('Starting Door Estimator app installation');
        
        try {
            $steps = [
                'create_directories' => $this->createAppDirectories(),
                'initialize_config' => $this->initializeConfiguration(),
                'verify_dependencies' => $this->verifyDependencies(),
                'run_health_check' => $this->runPostInstallHealthCheck(),
            ];
            
            $this->setInstallationStatus('installed', self::CURRENT_VERSION);
            
            $this->logger->info('Door Estimator app installation completed successfully');
            
            return [
                'success' => true,
                'version' => self::CURRENT_VERSION,
                'steps' => $steps,
                'message' => 'Installation completed successfully',
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('Installation failed', ['error' => $e->getMessage()]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Installation failed',
            ];
        }
    }
    
    /**
     * Perform app upgrade
     */
    public function upgrade(string $fromVersion): array {
        $this->logger->info('Starting Door Estimator app upgrade', [
            'from_version' => $fromVersion,
            'to_version' => self::CURRENT_VERSION
        ]);
        
        try {
            $steps = [];
            
            // Version-specific upgrade steps
            if (version_compare($fromVersion, '1.0.1', '<')) {
                $steps['upgrade_to_1_0_1'] = $this->upgradeToVersion101();
            }
            
            // Common upgrade steps
            $steps['update_config'] = $this->updateConfiguration();
            $steps['verify_schema'] = $this->verifyDatabaseSchema();
            $steps['cleanup_old_files'] = $this->cleanupOldFiles();
            $steps['run_health_check'] = $this->runPostUpgradeHealthCheck();
            
            $this->setInstallationStatus('upgraded', self::CURRENT_VERSION);
            
            $this->logger->info('Door Estimator app upgrade completed successfully');
            
            return [
                'success' => true,
                'from_version' => $fromVersion,
                'to_version' => self::CURRENT_VERSION,
                'steps' => $steps,
                'message' => 'Upgrade completed successfully',
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('Upgrade failed', [
                'from_version' => $fromVersion,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'from_version' => $fromVersion,
                'to_version' => self::CURRENT_VERSION,
                'error' => $e->getMessage(),
                'message' => 'Upgrade failed',
            ];
        }
    }
    
    /**
     * Perform app uninstallation cleanup
     */
    public function uninstall(): array {
        $this->logger->info('Starting Door Estimator app uninstallation');
        
        try {
            $steps = [
                'backup_data' => $this->backupUserData(),
                'cleanup_files' => $this->cleanupAppFiles(),
                'remove_config' => $this->removeConfiguration(),
            ];
            
            $this->logger->info('Door Estimator app uninstallation completed');
            
            return [
                'success' => true,
                'steps' => $steps,
                'message' => 'Uninstallation completed successfully',
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('Uninstallation failed', ['error' => $e->getMessage()]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Uninstallation failed',
            ];
        }
    }
    
    /**
     * Create required app directories
     */
    private function createAppDirectories(): array {
        $directories = ['quotes', 'imports', 'exports', 'backups'];
        $created = [];
        
        foreach ($directories as $dir) {
            try {
                $this->appData->getFolder($dir);
                $created[$dir] = 'exists';
            } catch (\OCP\Files\NotFoundException $e) {
                $this->appData->newFolder($dir);
                $created[$dir] = 'created';
            }
        }
        
        return $created;
    }
    
    /**
     * Initialize default configuration
     */
    private function initializeConfiguration(): array {
        $this->configService->initializeDefaults();
        
        return [
            'status' => 'completed',
            'message' => 'Default configuration initialized',
        ];
    }
    
    /**
     * Verify required dependencies
     */
    private function verifyDependencies(): array {
        $healthCheck = $this->healthService->performHealthCheck();
        $dependencyCheck = $healthCheck['checks']['dependencies'];
        
        if ($dependencyCheck['status'] === 'error') {
            throw new \RuntimeException('Required dependencies missing: ' . implode(', ', $dependencyCheck['missing_extensions'] ?? []));
        }
        
        return [
            'status' => 'verified',
            'php_version' => $dependencyCheck['php_version'],
            'extensions' => 'all_present',
        ];
    }
    
    /**
     * Run post-installation health check
     */
    private function runPostInstallHealthCheck(): array {
        $healthCheck = $this->healthService->performHealthCheck();
        
        if ($healthCheck['status'] === 'error') {
            throw new \RuntimeException('Post-installation health check failed');
        }
        
        return [
            'status' => $healthCheck['status'],
            'duration_ms' => $healthCheck['duration_ms'],
        ];
    }
    
    /**
     * Upgrade to version 1.0.1
     */
    private function upgradeToVersion101(): array {
        // Add any version-specific upgrade logic here
        return [
            'status' => 'completed',
            'changes' => [
                'Added performance monitoring',
                'Enhanced configuration management',
                'Improved health checks',
            ],
        ];
    }
    
    /**
     * Update configuration during upgrade
     */
    private function updateConfiguration(): array {
        // Ensure all new config keys are present
        $this->configService->initializeDefaults();
        
        // Update app version
        $this->configService->setAppValue('app_version', self::CURRENT_VERSION);
        
        return [
            'status' => 'updated',
            'version' => self::CURRENT_VERSION,
        ];
    }
    
    /**
     * Verify database schema is up to date
     */
    private function verifyDatabaseSchema(): array {
        $healthCheck = $this->healthService->performHealthCheck();
        $dbCheck = $healthCheck['checks']['database'];
        
        if ($dbCheck['status'] === 'error') {
            throw new \RuntimeException('Database schema verification failed');
        }
        
        return [
            'status' => 'verified',
            'tables' => $dbCheck['statistics'] ?? [],
        ];
    }
    
    /**
     * Clean up old files during upgrade
     */
    private function cleanupOldFiles(): array {
        $cleaned = [];
        
        try {
            // Clean up temporary files older than 24 hours
            $importsFolder = $this->appData->getFolder('imports');
            $files = $importsFolder->getDirectoryListing();
            
            $cutoff = time() - (24 * 60 * 60); // 24 hours ago
            
            foreach ($files as $file) {
                if ($file->getMTime() < $cutoff && str_contains($file->getName(), 'temp_')) {
                    $file->delete();
                    $cleaned[] = $file->getName();
                }
            }
            
        } catch (\Exception $e) {
            $this->logger->warning('Could not clean up old files', ['error' => $e->getMessage()]);
        }
        
        return [
            'status' => 'completed',
            'files_cleaned' => count($cleaned),
            'files' => $cleaned,
        ];
    }
    
    /**
     * Run post-upgrade health check
     */
    private function runPostUpgradeHealthCheck(): array {
        return $this->runPostInstallHealthCheck();
    }
    
    /**
     * Backup user data before uninstall
     */
    private function backupUserData(): array {
        try {
            $backupData = [
                'timestamp' => date('Y-m-d H:i:s'),
                'version' => self::CURRENT_VERSION,
                'configuration' => $this->configService->exportConfiguration(),
            ];
            
            // Create backup file
            $backupsFolder = $this->appData->getFolder('backups');
            $backupFile = $backupsFolder->newFile('uninstall_backup_' . date('Y-m-d_H-i-s') . '.json');
            $backupFile->putContent(json_encode($backupData, JSON_PRETTY_PRINT));
            
            return [
                'status' => 'completed',
                'backup_file' => $backupFile->getName(),
                'size_bytes' => $backupFile->getSize(),
            ];
            
        } catch (\Exception $e) {
            $this->logger->warning('Could not create backup', ['error' => $e->getMessage()]);
            
            return [
                'status' => 'failed',
                'error' => $e->getMessage(),
            ];
        }
    }
    
    /**
     * Clean up app files during uninstall
     */
    private function cleanupAppFiles(): array {
        $cleaned = [];
        
        try {
            // Note: App data directory will be removed by Nextcloud automatically
            // This is just for any additional cleanup if needed
            
            $cleaned['app_data'] = 'will_be_removed_by_nextcloud';
            
        } catch (\Exception $e) {
            $this->logger->warning('Could not clean up app files', ['error' => $e->getMessage()]);
        }
        
        return [
            'status' => 'completed',
            'cleaned' => $cleaned,
        ];
    }
    
    /**
     * Remove configuration during uninstall
     */
    private function removeConfiguration(): array {
        try {
            // Remove all app configuration values
            $this->config->deleteAppValues(self::APP_ID);
            
            return [
                'status' => 'completed',
                'message' => 'All configuration removed',
            ];
            
        } catch (\Exception $e) {
            $this->logger->warning('Could not remove configuration', ['error' => $e->getMessage()]);
            
            return [
                'status' => 'failed',
                'error' => $e->getMessage(),
            ];
        }
    }
    
    /**
     * Set installation status
     */
    private function setInstallationStatus(string $status, string $version): void {
        $this->config->setAppValue(self::APP_ID, 'installation_status', $status);
        $this->config->setAppValue(self::APP_ID, 'installed_version', $version);
        $this->config->setAppValue(self::APP_ID, 'installation_date', date('Y-m-d H:i:s'));
    }
    
    /**
     * Get installation status
     */
    public function getInstallationStatus(): array {
        return [
            'status' => $this->config->getAppValue(self::APP_ID, 'installation_status', 'unknown'),
            'version' => $this->config->getAppValue(self::APP_ID, 'installed_version', 'unknown'),
            'date' => $this->config->getAppValue(self::APP_ID, 'installation_date', 'unknown'),
            'current_version' => self::CURRENT_VERSION,
        ];
    }
    
    /**
     * Check if upgrade is needed
     */
    public function isUpgradeNeeded(): bool {
        $installedVersion = $this->config->getAppValue(self::APP_ID, 'installed_version', '0.0.0');
        return version_compare($installedVersion, self::CURRENT_VERSION, '<');
    }
}