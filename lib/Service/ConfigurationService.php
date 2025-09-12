<?php

declare(strict_types=1);

namespace OCA\DoorEstimator\Service;

use OCP\IConfig;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * Configuration management service for Door Estimator app
 * 
 * Handles both app-level and user-level configuration settings
 * with proper defaults and validation.
 */
class ConfigurationService {
    
    private const APP_ID = 'door_estimator';
    
    // Default configuration values
    private const DEFAULT_CONFIG = [
        // Markup percentages
        'default_door_markup' => '15',
        'default_frame_markup' => '12', 
        'default_hardware_markup' => '18',
        'default_door_options_markup' => '15',
        'default_inserts_markup' => '15',
        'default_frame_options_markup' => '12',
        'default_hinges_markup' => '18',
        'default_weatherstrip_markup' => '18',
        'default_closers_markup' => '18',
        'default_locksets_markup' => '18',
        'default_exit_devices_markup' => '18',
        
        // File upload settings
        'max_import_file_size' => '5242880', // 5MB
        'allowed_import_formats' => 'json,csv,xlsx,xls',
        'import_rate_limit' => '1', // 1 import per second
        
        // Performance settings
        'cache_timeout' => '3600', // 1 hour
        'search_result_limit' => '1000',
        'quote_list_page_size' => '50',
        'pdf_generation_timeout' => '30', // 30 seconds
        
        // System settings
        'enable_debug_logging' => 'false',
        'enable_performance_monitoring' => 'true',
        'maintenance_mode' => 'false',
        'app_version' => '1.0.1',
        
        // Security settings
        'enable_csrf_protection' => 'true',
        'session_timeout' => '3600', // 1 hour
        'max_login_attempts' => '5',
        
        // Feature flags
        'enable_pdf_generation' => 'true',
        'enable_data_import' => 'true',
        'enable_data_export' => 'true',
        'enable_quote_sharing' => 'false',
    ];
    
    // User preference defaults
    private const DEFAULT_USER_PREFERENCES = [
        'theme' => 'auto', // auto, light, dark
        'default_quote_name_pattern' => 'Quote {date}',
        'auto_save_quotes' => 'true',
        'show_advanced_options' => 'false',
        'preferred_currency' => 'USD',
        'decimal_places' => '2',
        'date_format' => 'Y-m-d',
        'time_format' => 'H:i:s',
    ];
    
    public function __construct(
        private IConfig $config,
        private IUserSession $userSession,
        private LoggerInterface $logger
    ) {}
    
    /**
     * Get app configuration value with fallback to default
     */
    public function getAppValue(string $key, ?string $default = null): string {
        $value = $this->config->getAppValue(self::APP_ID, $key, '');
        
        if ($value === '') {
            $value = $default ?? self::DEFAULT_CONFIG[$key] ?? '';
        }
        
        return $value;
    }
    
    /**
     * Set app configuration value
     */
    public function setAppValue(string $key, string $value): void {
        $this->config->setAppValue(self::APP_ID, $key, $value);
        $this->logger->info('App configuration updated', [
            'key' => $key,
            'value' => $value
        ]);
    }
    
    /**
     * Get user preference with fallback to default
     */
    public function getUserValue(string $key, ?string $default = null): string {
        $user = $this->userSession->getUser();
        if (!$user) {
            return $default ?? self::DEFAULT_USER_PREFERENCES[$key] ?? '';
        }
        
        $value = $this->config->getUserValue($user->getUID(), self::APP_ID, $key, '');
        
        if ($value === '') {
            $value = $default ?? self::DEFAULT_USER_PREFERENCES[$key] ?? '';
        }
        
        return $value;
    }
    
    /**
     * Set user preference
     */
    public function setUserValue(string $key, string $value): void {
        $user = $this->userSession->getUser();
        if (!$user) {
            throw new \RuntimeException('No user session available');
        }
        
        $this->config->setUserValue($user->getUID(), self::APP_ID, $key, $value);
        $this->logger->debug('User preference updated', [
            'user' => $user->getUID(),
            'key' => $key,
            'value' => $value
        ]);
    }
    
    /**
     * Get all markup defaults as array
     */
    public function getMarkupDefaults(): array {
        return [
            'doors' => (float) $this->getAppValue('default_door_markup'),
            'doorOptions' => (float) $this->getAppValue('default_door_options_markup'),
            'inserts' => (float) $this->getAppValue('default_inserts_markup'),
            'frames' => (float) $this->getAppValue('default_frame_markup'),
            'frameOptions' => (float) $this->getAppValue('default_frame_options_markup'),
            'hinges' => (float) $this->getAppValue('default_hinges_markup'),
            'weatherstrip' => (float) $this->getAppValue('default_weatherstrip_markup'),
            'closers' => (float) $this->getAppValue('default_closers_markup'),
            'locksets' => (float) $this->getAppValue('default_locksets_markup'),
            'exitDevices' => (float) $this->getAppValue('default_exit_devices_markup'),
            'hardware' => (float) $this->getAppValue('default_hardware_markup'),
        ];
    }
    
    /**
     * Update markup defaults
     */
    public function updateMarkupDefaults(array $markups): void {
        $validKeys = [
            'doors' => 'default_door_markup',
            'doorOptions' => 'default_door_options_markup',
            'inserts' => 'default_inserts_markup',
            'frames' => 'default_frame_markup',
            'frameOptions' => 'default_frame_options_markup',
            'hinges' => 'default_hinges_markup',
            'weatherstrip' => 'default_weatherstrip_markup',
            'closers' => 'default_closers_markup',
            'locksets' => 'default_locksets_markup',
            'exitDevices' => 'default_exit_devices_markup',
            'hardware' => 'default_hardware_markup',
        ];
        
        foreach ($markups as $category => $value) {
            if (isset($validKeys[$category])) {
                $this->setAppValue($validKeys[$category], (string) $value);
            }
        }
    }
    
    /**
     * Get system health configuration
     */
    public function getHealthConfig(): array {
        return [
            'enable_performance_monitoring' => $this->getAppValue('enable_performance_monitoring') === 'true',
            'enable_debug_logging' => $this->getAppValue('enable_debug_logging') === 'true',
            'maintenance_mode' => $this->getAppValue('maintenance_mode') === 'true',
            'cache_timeout' => (int) $this->getAppValue('cache_timeout'),
            'pdf_generation_timeout' => (int) $this->getAppValue('pdf_generation_timeout'),
        ];
    }
    
    /**
     * Get security configuration
     */
    public function getSecurityConfig(): array {
        return [
            'enable_csrf_protection' => $this->getAppValue('enable_csrf_protection') === 'true',
            'session_timeout' => (int) $this->getAppValue('session_timeout'),
            'max_login_attempts' => (int) $this->getAppValue('max_login_attempts'),
            'max_import_file_size' => (int) $this->getAppValue('max_import_file_size'),
            'import_rate_limit' => (int) $this->getAppValue('import_rate_limit'),
        ];
    }
    
    /**
     * Get feature flags
     */
    public function getFeatureFlags(): array {
        return [
            'pdf_generation' => $this->getAppValue('enable_pdf_generation') === 'true',
            'data_import' => $this->getAppValue('enable_data_import') === 'true',
            'data_export' => $this->getAppValue('enable_data_export') === 'true',
            'quote_sharing' => $this->getAppValue('enable_quote_sharing') === 'true',
        ];
    }
    
    /**
     * Initialize default configuration values
     */
    public function initializeDefaults(): void {
        foreach (self::DEFAULT_CONFIG as $key => $value) {
            if ($this->config->getAppValue(self::APP_ID, $key, '') === '') {
                $this->config->setAppValue(self::APP_ID, $key, $value);
            }
        }
        
        $this->logger->info('Default configuration initialized');
    }
    
    /**
     * Validate configuration values
     */
    public function validateConfiguration(): array {
        $errors = [];
        
        // Validate markup percentages
        $markups = $this->getMarkupDefaults();
        foreach ($markups as $category => $value) {
            if ($value < 0 || $value > 100) {
                $errors[] = "Invalid markup for {$category}: {$value}%";
            }
        }
        
        // Validate file size limits
        $maxSize = (int) $this->getAppValue('max_import_file_size');
        if ($maxSize <= 0 || $maxSize > 50 * 1024 * 1024) { // Max 50MB
            $errors[] = "Invalid max import file size: {$maxSize} bytes";
        }
        
        // Validate timeouts
        $cacheTimeout = (int) $this->getAppValue('cache_timeout');
        if ($cacheTimeout < 60 || $cacheTimeout > 86400) { // 1 minute to 24 hours
            $errors[] = "Invalid cache timeout: {$cacheTimeout} seconds";
        }
        
        return $errors;
    }
    
    /**
     * Export configuration for backup
     */
    public function exportConfiguration(): array {
        $config = [];
        
        foreach (array_keys(self::DEFAULT_CONFIG) as $key) {
            $config[$key] = $this->getAppValue($key);
        }
        
        return [
            'app_config' => $config,
            'exported_at' => date('Y-m-d H:i:s'),
            'app_version' => $this->getAppValue('app_version'),
        ];
    }
    
    /**
     * Import configuration from backup
     */
    public function importConfiguration(array $config): void {
        if (!isset($config['app_config']) || !is_array($config['app_config'])) {
            throw new \InvalidArgumentException('Invalid configuration format');
        }
        
        foreach ($config['app_config'] as $key => $value) {
            if (array_key_exists($key, self::DEFAULT_CONFIG)) {
                $this->setAppValue($key, (string) $value);
            }
        }
        
        $this->logger->info('Configuration imported from backup');
    }
}