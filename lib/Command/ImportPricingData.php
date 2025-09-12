<?php

declare(strict_types=1);

namespace OCA\DoorEstimator\Command;

use OCA\DoorEstimator\Service\ConfigurationService;
use OCA\DoorEstimator\Service\DeploymentService;
use OCA\DoorEstimator\Service\EstimatorService;
use OCA\DoorEstimator\Service\HealthMonitoringService;
use OCP\IConfig;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * CLI command for Door Estimator maintenance and data operations
 * 
 * Provides command-line interface for importing data, running health checks,
 * and performing maintenance tasks.
 */
class ImportPricingData extends Command {
    
    public function __construct(
        private EstimatorService $estimatorService,
        private ConfigurationService $configService,
        private DeploymentService $deploymentService,
        private HealthMonitoringService $healthService,
        private IConfig $config
    ) {
        parent::__construct();
    }
    
    protected function configure(): void {
        $this->setName('door-estimator:import')
            ->setDescription('Import pricing data from file')
            ->addArgument('file', InputArgument::REQUIRED, 'Path to the import file')
            ->addOption('format', 'f', InputOption::VALUE_OPTIONAL, 'File format (json, csv, xlsx)', 'json')
            ->addOption('validate-only', null, InputOption::VALUE_NONE, 'Only validate the file without importing')
            ->addOption('health-check', null, InputOption::VALUE_NONE, 'Run system health check')
            ->addOption('install', null, InputOption::VALUE_NONE, 'Run installation setup')
            ->addOption('upgrade', null, InputOption::VALUE_OPTIONAL, 'Run upgrade from specified version')
            ->addOption('export-config', null, InputOption::VALUE_NONE, 'Export current configuration')
            ->addOption('import-config', null, InputOption::VALUE_OPTIONAL, 'Import configuration from file');
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int {
        // Handle special operations first
        if ($input->getOption('health-check')) {
            return $this->runHealthCheck($output);
        }
        
        if ($input->getOption('install')) {
            return $this->runInstallation($output);
        }
        
        if ($input->getOption('upgrade')) {
            return $this->runUpgrade($input->getOption('upgrade'), $output);
        }
        
        if ($input->getOption('export-config')) {
            return $this->exportConfiguration($output);
        }
        
        if ($input->getOption('import-config')) {
            return $this->importConfiguration($input->getOption('import-config'), $output);
        }
        
        // Default: import pricing data
        return $this->importPricingData($input, $output);
    }
    
    /**
     * Import pricing data from file
     */
    private function importPricingData(InputInterface $input, OutputInterface $output): int {
        $filePath = $input->getArgument('file');
        $format = $input->getOption('format');
        $validateOnly = $input->getOption('validate-only');
        
        if (!file_exists($filePath)) {
            $output->writeln('<error>File not found: ' . $filePath . '</error>');
            return Command::FAILURE;
        }
        
        try {
            $output->writeln('Starting import process...');
            
            // Check file size
            $fileSize = filesize($filePath);
            $maxSize = (int) $this->configService->getAppValue('max_import_file_size');
            
            if ($fileSize > $maxSize) {
                $output->writeln('<error>File too large: ' . $fileSize . ' bytes (max: ' . $maxSize . ')</error>');
                return Command::FAILURE;
            }
            
            // Simulate file upload array structure
            $uploadedFile = [
                'tmp_name' => $filePath,
                'name' => basename($filePath),
                'size' => $fileSize,
                'type' => $this->getMimeType($filePath, $format),
                'error' => UPLOAD_ERR_OK
            ];
            
            if ($validateOnly) {
                $output->writeln('Validating file...');
                
                // Basic validation
                if (!$this->validateFileFormat($filePath, $format)) {
                    $output->writeln('<error>Invalid file format</error>');
                    return Command::FAILURE;
                }
                
                $output->writeln('<info>File validation completed successfully</info>');
                return Command::SUCCESS;
            }
            
            $result = $this->estimatorService->importPricingFromUpload($uploadedFile);
            
            if ($result['success']) {
                $output->writeln('<info>Import completed successfully</info>');
                $output->writeln('Items imported: ' . ($result['imported'] ?? 0));
                $output->writeln('Items updated: ' . ($result['updated'] ?? 0));
                
                if (!empty($result['errors'])) {
                    $output->writeln('<comment>Warnings:</comment>');
                    foreach ($result['errors'] as $error) {
                        $output->writeln('  - ' . $error);
                    }
                }
            } else {
                $output->writeln('<error>Import failed: ' . $result['message'] . '</error>');
                return Command::FAILURE;
            }
            
        } catch (\Exception $e) {
            $output->writeln('<error>Import failed: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
        
        return Command::SUCCESS;
    }
    
    /**
     * Run system health check
     */
    private function runHealthCheck(OutputInterface $output): int {
        $output->writeln('Running system health check...');
        
        try {
            $healthCheck = $this->healthService->performHealthCheck();
            
            $output->writeln('Overall Status: <info>' . strtoupper($healthCheck['status']) . '</info>');
            $output->writeln('Duration: ' . $healthCheck['duration_ms'] . 'ms');
            $output->writeln('');
            
            foreach ($healthCheck['checks'] as $checkName => $check) {
                $statusColor = match($check['status']) {
                    'healthy' => 'info',
                    'warning' => 'comment',
                    'error' => 'error',
                    default => 'info'
                };
                
                $output->writeln(sprintf(
                    '%s: <%s>%s</%s> (%sms)',
                    ucfirst(str_replace('_', ' ', $checkName)),
                    $statusColor,
                    strtoupper($check['status']),
                    $statusColor,
                    $check['duration_ms'] ?? 0
                ));
                
                if (!empty($check['message'])) {
                    $output->writeln('  ' . $check['message']);
                }
                
                if (!empty($check['errors'])) {
                    foreach ($check['errors'] as $error) {
                        $output->writeln('  <error>- ' . $error . '</error>');
                    }
                }
            }
            
            return $healthCheck['status'] === 'error' ? Command::FAILURE : Command::SUCCESS;
            
        } catch (\Exception $e) {
            $output->writeln('<error>Health check failed: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
    
    /**
     * Run installation setup
     */
    private function runInstallation(OutputInterface $output): int {
        $output->writeln('Running installation setup...');
        
        try {
            $result = $this->deploymentService->install();
            
            if ($result['success']) {
                $output->writeln('<info>Installation completed successfully</info>');
                $output->writeln('Version: ' . $result['version']);
                
                foreach ($result['steps'] as $step => $stepResult) {
                    $output->writeln('  ' . $step . ': ' . (is_array($stepResult) ? $stepResult['status'] ?? 'completed' : $stepResult));
                }
            } else {
                $output->writeln('<error>Installation failed: ' . $result['message'] . '</error>');
                return Command::FAILURE;
            }
            
        } catch (\Exception $e) {
            $output->writeln('<error>Installation failed: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
        
        return Command::SUCCESS;
    }
    
    /**
     * Run upgrade process
     */
    private function runUpgrade(string $fromVersion, OutputInterface $output): int {
        $output->writeln('Running upgrade from version ' . $fromVersion . '...');
        
        try {
            $result = $this->deploymentService->upgrade($fromVersion);
            
            if ($result['success']) {
                $output->writeln('<info>Upgrade completed successfully</info>');
                $output->writeln('From: ' . $result['from_version']);
                $output->writeln('To: ' . $result['to_version']);
                
                foreach ($result['steps'] as $step => $stepResult) {
                    $output->writeln('  ' . $step . ': ' . (is_array($stepResult) ? $stepResult['status'] ?? 'completed' : $stepResult));
                }
            } else {
                $output->writeln('<error>Upgrade failed: ' . $result['message'] . '</error>');
                return Command::FAILURE;
            }
            
        } catch (\Exception $e) {
            $output->writeln('<error>Upgrade failed: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
        
        return Command::SUCCESS;
    }
    
    /**
     * Export configuration to file
     */
    private function exportConfiguration(OutputInterface $output): int {
        try {
            $config = $this->configService->exportConfiguration();
            $filename = 'door_estimator_config_' . date('Y-m-d_H-i-s') . '.json';
            
            file_put_contents($filename, json_encode($config, JSON_PRETTY_PRINT));
            
            $output->writeln('<info>Configuration exported to: ' . $filename . '</info>');
            
        } catch (\Exception $e) {
            $output->writeln('<error>Configuration export failed: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
        
        return Command::SUCCESS;
    }
    
    /**
     * Import configuration from file
     */
    private function importConfiguration(string $filePath, OutputInterface $output): int {
        if (!file_exists($filePath)) {
            $output->writeln('<error>Configuration file not found: ' . $filePath . '</error>');
            return Command::FAILURE;
        }
        
        try {
            $configData = json_decode(file_get_contents($filePath), true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                $output->writeln('<error>Invalid JSON in configuration file</error>');
                return Command::FAILURE;
            }
            
            $this->configService->importConfiguration($configData);
            
            $output->writeln('<info>Configuration imported successfully</info>');
            
        } catch (\Exception $e) {
            $output->writeln('<error>Configuration import failed: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
        
        return Command::SUCCESS;
    }
    
    /**
     * Get MIME type for file format
     */
    private function getMimeType(string $filePath, string $format): string {
        $mimeTypes = [
            'json' => 'application/json',
            'csv' => 'text/csv',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xls' => 'application/vnd.ms-excel'
        ];
        
        return $mimeTypes[$format] ?? 'application/octet-stream';
    }
    
    /**
     * Validate file format
     */
    private function validateFileFormat(string $filePath, string $format): bool {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        
        $validExtensions = [
            'json' => ['json'],
            'csv' => ['csv'],
            'xlsx' => ['xlsx'],
            'xls' => ['xls']
        ];
        
        return in_array($extension, $validExtensions[$format] ?? []);
    }
}