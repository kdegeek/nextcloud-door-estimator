<?php

declare(strict_types=1);

namespace OCA\DoorEstimator\Service;

use OCP\IDBConnection;
use OCP\Files\IAppData;
use OCP\ICacheFactory;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

/**
 * Health monitoring service for Door Estimator app
 * 
 * Provides system health checks, performance monitoring,
 * and status reporting for deployment and maintenance.
 */
class HealthMonitoringService {
    
    private const APP_ID = 'door_estimator';
    
    public function __construct(
        private IDBConnection $db,
        private IAppData $appData,
        private ICacheFactory $cacheFactory,
        private IConfig $config,
        private ConfigurationService $configService,
        private LoggerInterface $logger
    ) {}
    
    /**
     * Perform comprehensive system health check
     */
    public function performHealthCheck(): array {
        $startTime = microtime(true);
        
        $checks = [
            'database' => $this->checkDatabase(),
            'file_system' => $this->checkFileSystem(),
            'cache' => $this->checkCache(),
            'configuration' => $this->checkConfiguration(),
            'dependencies' => $this->checkDependencies(),
            'performance' => $this->checkPerformance(),
        ];
        
        $overallStatus = $this->determineOverallStatus($checks);
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        
        $result = [
            'status' => $overallStatus,
            'timestamp' => date('Y-m-d H:i:s'),
            'duration_ms' => $duration,
            'checks' => $checks,
            'summary' => $this->generateHealthSummary($checks),
        ];
        
        $this->logger->info('Health check completed', [
            'status' => $overallStatus,
            'duration_ms' => $duration
        ]);
        
        return $result;
    }
    
    /**
     * Check database connectivity and table status
     */
    private function checkDatabase(): array {
        try {
            $startTime = microtime(true);
            
            // Test basic connectivity
            $this->db->executeQuery('SELECT 1');
            
            // Check required tables exist
            $tables = ['door_estimator_pricing', 'door_estimator_quotes'];
            $missingTables = [];
            
            foreach ($tables as $table) {
                try {
                    $this->db->executeQuery("SELECT COUNT(*) FROM `{$table}` LIMIT 1");
                } catch (\Exception $e) {
                    $missingTables[] = $table;
                }
            }
            
            // Get table statistics
            $stats = $this->getDatabaseStats();
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            
            return [
                'status' => empty($missingTables) ? 'healthy' : 'error',
                'duration_ms' => $duration,
                'missing_tables' => $missingTables,
                'statistics' => $stats,
                'message' => empty($missingTables) ? 'Database is healthy' : 'Missing required tables',
            ];
            
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'duration_ms' => 0,
                'error' => $e->getMessage(),
                'message' => 'Database connection failed',
            ];
        }
    }
    
    /**
     * Check file system access and app data directories
     */
    private function checkFileSystem(): array {
        try {
            $startTime = microtime(true);
            
            // Check app data directory access
            $requiredFolders = ['quotes', 'imports'];
            $issues = [];
            
            foreach ($requiredFolders as $folderName) {
                try {
                    $folder = $this->appData->getFolder($folderName);
                    
                    // Test write access
                    $testFile = $folder->newFile('health_check_' . time() . '.tmp');
                    $testFile->putContent('test');
                    $testFile->delete();
                    
                } catch (\Exception $e) {
                    $issues[] = "Cannot access {$folderName} folder: " . $e->getMessage();
                }
            }
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            
            return [
                'status' => empty($issues) ? 'healthy' : 'error',
                'duration_ms' => $duration,
                'issues' => $issues,
                'message' => empty($issues) ? 'File system is healthy' : 'File system issues detected',
            ];
            
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'duration_ms' => 0,
                'error' => $e->getMessage(),
                'message' => 'File system check failed',
            ];
        }
    }
    
    /**
     * Check cache system functionality
     */
    private function checkCache(): array {
        try {
            $startTime = microtime(true);
            
            $cache = $this->cacheFactory->createDistributed('door_estimator_health');
            
            // Test cache write/read
            $testKey = 'health_check_' . time();
            $testValue = 'test_value_' . rand(1000, 9999);
            
            $cache->set($testKey, $testValue, 60);
            $retrievedValue = $cache->get($testKey);
            
            $cache->remove($testKey);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            
            $isWorking = $retrievedValue === $testValue;
            
            return [
                'status' => $isWorking ? 'healthy' : 'warning',
                'duration_ms' => $duration,
                'message' => $isWorking ? 'Cache is working' : 'Cache read/write test failed',
            ];
            
        } catch (\Exception $e) {
            return [
                'status' => 'warning',
                'duration_ms' => 0,
                'error' => $e->getMessage(),
                'message' => 'Cache system unavailable',
            ];
        }
    }
    
    /**
     * Check configuration validity
     */
    private function checkConfiguration(): array {
        try {
            $startTime = microtime(true);
            
            $errors = $this->configService->validateConfiguration();
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            
            return [
                'status' => empty($errors) ? 'healthy' : 'warning',
                'duration_ms' => $duration,
                'errors' => $errors,
                'message' => empty($errors) ? 'Configuration is valid' : 'Configuration issues found',
            ];
            
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'duration_ms' => 0,
                'error' => $e->getMessage(),
                'message' => 'Configuration check failed',
            ];
        }
    }
    
    /**
     * Check required PHP extensions and dependencies
     */
    private function checkDependencies(): array {
        $startTime = microtime(true);
        
        $requiredExtensions = ['pdo', 'json', 'curl', 'mbstring', 'xml'];
        $missingExtensions = [];
        
        foreach ($requiredExtensions as $ext) {
            if (!extension_loaded($ext)) {
                $missingExtensions[] = $ext;
            }
        }
        
        // Check PHP version
        $phpVersion = PHP_VERSION;
        $minPhpVersion = '8.0.0';
        $phpVersionOk = version_compare($phpVersion, $minPhpVersion, '>=');
        
        // Check required classes
        $requiredClasses = [
            'TCPDF' => class_exists('TCPDF'),
            'PhpSpreadsheet' => class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet'),
        ];
        
        $missingClasses = array_keys(array_filter($requiredClasses, fn($exists) => !$exists));
        
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        
        $hasIssues = !empty($missingExtensions) || !$phpVersionOk || !empty($missingClasses);
        
        return [
            'status' => $hasIssues ? 'error' : 'healthy',
            'duration_ms' => $duration,
            'php_version' => $phpVersion,
            'php_version_ok' => $phpVersionOk,
            'min_php_version' => $minPhpVersion,
            'missing_extensions' => $missingExtensions,
            'missing_classes' => $missingClasses,
            'message' => $hasIssues ? 'Dependency issues found' : 'All dependencies available',
        ];
    }
    
    /**
     * Check system performance metrics
     */
    private function checkPerformance(): array {
        $startTime = microtime(true);
        
        try {
            // Test database query performance
            $dbStart = microtime(true);
            $this->db->executeQuery('SELECT COUNT(*) FROM `door_estimator_pricing`');
            $dbDuration = round((microtime(true) - $dbStart) * 1000, 2);
            
            // Test memory usage
            $memoryUsage = memory_get_usage(true);
            $memoryPeak = memory_get_peak_usage(true);
            
            // Performance thresholds
            $dbThreshold = 100; // 100ms
            $memoryThreshold = 128 * 1024 * 1024; // 128MB
            
            $issues = [];
            if ($dbDuration > $dbThreshold) {
                $issues[] = "Database query slow: {$dbDuration}ms";
            }
            if ($memoryUsage > $memoryThreshold) {
                $issues[] = "High memory usage: " . round($memoryUsage / 1024 / 1024, 2) . "MB";
            }
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            
            return [
                'status' => empty($issues) ? 'healthy' : 'warning',
                'duration_ms' => $duration,
                'database_query_ms' => $dbDuration,
                'memory_usage_mb' => round($memoryUsage / 1024 / 1024, 2),
                'memory_peak_mb' => round($memoryPeak / 1024 / 1024, 2),
                'issues' => $issues,
                'message' => empty($issues) ? 'Performance is good' : 'Performance issues detected',
            ];
            
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'duration_ms' => 0,
                'error' => $e->getMessage(),
                'message' => 'Performance check failed',
            ];
        }
    }
    
    /**
     * Get database statistics
     */
    private function getDatabaseStats(): array {
        try {
            $pricingCount = $this->db->executeQuery('SELECT COUNT(*) as count FROM `door_estimator_pricing`')
                ->fetchOne();
            
            $quotesCount = $this->db->executeQuery('SELECT COUNT(*) as count FROM `door_estimator_quotes`')
                ->fetchOne();
            
            return [
                'pricing_items' => (int) $pricingCount,
                'quotes' => (int) $quotesCount,
            ];
            
        } catch (\Exception $e) {
            return [
                'error' => 'Could not retrieve statistics: ' . $e->getMessage(),
            ];
        }
    }
    
    /**
     * Determine overall system status
     */
    private function determineOverallStatus(array $checks): string {
        $statuses = array_column($checks, 'status');
        
        if (in_array('error', $statuses)) {
            return 'error';
        }
        
        if (in_array('warning', $statuses)) {
            return 'warning';
        }
        
        return 'healthy';
    }
    
    /**
     * Generate health summary
     */
    private function generateHealthSummary(array $checks): array {
        $summary = [
            'healthy' => 0,
            'warning' => 0,
            'error' => 0,
            'total' => count($checks),
        ];
        
        foreach ($checks as $check) {
            $summary[$check['status']]++;
        }
        
        return $summary;
    }
    
    /**
     * Get system information
     */
    public function getSystemInfo(): array {
        return [
            'app_version' => $this->configService->getAppValue('app_version'),
            'php_version' => PHP_VERSION,
            'nextcloud_version' => $this->config->getSystemValue('version'),
            'database_type' => $this->config->getSystemValue('dbtype'),
            'maintenance_mode' => $this->configService->getAppValue('maintenance_mode') === 'true',
            'debug_mode' => $this->configService->getAppValue('enable_debug_logging') === 'true',
            'timestamp' => date('Y-m-d H:i:s'),
        ];
    }
    
    /**
     * Log performance metrics
     */
    public function logPerformanceMetrics(string $operation, float $duration, array $context = []): void {
        if ($this->configService->getAppValue('enable_performance_monitoring') === 'true') {
            $this->logger->info('Performance metric', [
                'operation' => $operation,
                'duration_ms' => round($duration * 1000, 2),
                'context' => $context,
            ]);
        }
    }
}