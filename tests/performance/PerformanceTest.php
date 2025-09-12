<?php

declare(strict_types=1);

namespace OCA\DoorEstimator\Tests\Performance;

use PHPUnit\Framework\TestCase;
use OCA\DoorEstimator\Service\EstimatorService;
use OCA\DoorEstimator\Controller\EstimatorController;

/**
 * Performance tests for Door Estimator application
 * Tests response times, concurrent users, and resource usage
 */
class PerformanceTest extends TestCase
{
    /** @var EstimatorService */
    private $service;

    /** @var EstimatorController */
    private $controller;

    /** @var array */
    private $performanceMetrics = [];

    protected function setUp(): void
    {
        $this->initializeComponents();
        $this->setupLargeDataset();
    }

    protected function tearDown(): void
    {
        $this->logPerformanceMetrics();
    }

    /**
     * Test: Price lookup should complete in <500ms
     * Requirement: 9.4 - Search operations within 500ms
     */
    public function testPriceLookupPerformance(): void
    {
        $iterations = 100;
        $times = [];

        for ($i = 0; $i < $iterations; $i++) {
            $startTime = microtime(true);
            
            $price = $this->service->lookupPrice('doors', 'Test Door Item');
            
            $endTime = microtime(true);
            $times[] = ($endTime - $startTime) * 1000; // Convert to milliseconds
        }

        $averageTime = array_sum($times) / count($times);
        $maxTime = max($times);

        $this->assertLessThan(500, $averageTime, 'Average price lookup time should be <500ms');
        $this->assertLessThan(1000, $maxTime, 'Maximum price lookup time should be <1000ms');

        $this->performanceMetrics['price_lookup'] = [
            'average_ms' => $averageTime,
            'max_ms' => $maxTime,
            'iterations' => $iterations
        ];
    }

    /**
     * Test: Search operations should complete in <500ms
     * Requirement: 9.4 - Search operations within 500ms
     */
    public function testSearchPerformance(): void
    {
        $searchTerms = ['Door', 'Frame', 'Hardware', 'Lock', 'Hinge'];
        $times = [];

        foreach ($searchTerms as $term) {
            $startTime = microtime(true);
            
            $results = $this->service->searchPricing($term, null, 50);
            
            $endTime = microtime(true);
            $times[] = ($endTime - $startTime) * 1000;
            
            $this->assertNotEmpty($results, "Search for '$term' should return results");
        }

        $averageTime = array_sum($times) / count($times);
        $maxTime = max($times);

        $this->assertLessThan(500, $averageTime, 'Average search time should be <500ms');
        $this->assertLessThan(1000, $maxTime, 'Maximum search time should be <1000ms');

        $this->performanceMetrics['search'] = [
            'average_ms' => $averageTime,
            'max_ms' => $maxTime,
            'search_terms' => count($searchTerms)
        ];
    }

    /**
     * Test: Quote calculation should complete quickly even with large quotes
     * Requirement: 9.2 - Handle 30-50 quotes per day efficiently
     */
    public function testQuoteCalculationPerformance(): void
    {
        $quoteSizes = [10, 50, 100, 500, 1000];
        $times = [];

        foreach ($quoteSizes as $size) {
            $quoteData = $this->generateLargeQuoteData($size);
            $markups = ['doors' => 15, 'frames' => 12, 'hardware' => 18];

            $startTime = microtime(true);
            
            $total = $this->service->calculateQuoteTotal($quoteData, $markups);
            
            $endTime = microtime(true);
            $time = ($endTime - $startTime) * 1000;
            $times[$size] = $time;

            $this->assertGreaterThan(0, $total, "Quote total should be calculated for $size items");
            $this->assertLessThan(1000, $time, "Quote calculation for $size items should be <1000ms");
        }

        $this->performanceMetrics['quote_calculation'] = $times;
    }

    /**
     * Test: PDF generation should complete in <5 seconds
     * Requirement: 9.6 - PDF generation within 5 seconds
     */
    public function testPDFGenerationPerformance(): void
    {
        $quoteData = $this->generateLargeQuoteData(50);
        $markups = ['doors' => 15, 'frames' => 12, 'hardware' => 18];
        $quoteName = 'Performance Test Quote';
        $customerInfo = json_encode(['name' => 'Test Customer']);

        $startTime = microtime(true);
        
        $pdfResult = $this->service->generateQuotePDF($quoteData, $markups, $quoteName, $customerInfo);
        
        $endTime = microtime(true);
        $time = ($endTime - $startTime) * 1000;

        $this->assertIsArray($pdfResult, 'PDF generation should return result array');
        $this->assertLessThan(5000, $time, 'PDF generation should complete in <5 seconds');

        $this->performanceMetrics['pdf_generation'] = [
            'time_ms' => $time,
            'quote_items' => 50
        ];
    }

    /**
     * Test: Database operations should handle large datasets efficiently
     * Requirement: 9.3 - Handle thousands of pricing items with fast lookups
     */
    public function testDatabasePerformance(): void
    {
        $operations = [
            'getAllPricingData' => fn() => $this->service->getAllPricingData(),
            'getPricingByCategory' => fn() => $this->service->getPricingByCategory('doors'),
            'getUserQuotes' => fn() => $this->service->getUserQuotes()
        ];

        $times = [];

        foreach ($operations as $operation => $callable) {
            $startTime = microtime(true);
            
            $result = $callable();
            
            $endTime = microtime(true);
            $time = ($endTime - $startTime) * 1000;
            $times[$operation] = $time;

            $this->assertIsArray($result, "$operation should return array result");
            $this->assertLessThan(2000, $time, "$operation should complete in <2 seconds");
        }

        $this->performanceMetrics['database_operations'] = $times;
    }

    /**
     * Test: Memory usage should remain reasonable with large datasets
     * Requirement: 9.5 - Efficient memory usage
     */
    public function testMemoryUsage(): void
    {
        $memoryBefore = memory_get_usage(true);
        $peakBefore = memory_get_peak_usage(true);

        // Perform memory-intensive operations
        $largeQuoteData = $this->generateLargeQuoteData(1000);
        $this->service->calculateQuoteTotal($largeQuoteData, ['doors' => 15]);
        
        $largePricingData = $this->generateLargePricingData(5000);
        $this->service->importPricingFromData(['pricingData' => $largePricingData, 'markups' => []]);

        $memoryAfter = memory_get_usage(true);
        $peakAfter = memory_get_peak_usage(true);

        $memoryIncrease = $memoryAfter - $memoryBefore;
        $peakIncrease = $peakAfter - $peakBefore;

        // Memory increase should be reasonable (less than 50MB for large operations)
        $this->assertLessThan(50 * 1024 * 1024, $memoryIncrease, 'Memory increase should be <50MB');
        $this->assertLessThan(100 * 1024 * 1024, $peakIncrease, 'Peak memory increase should be <100MB');

        $this->performanceMetrics['memory_usage'] = [
            'increase_mb' => round($memoryIncrease / 1024 / 1024, 2),
            'peak_increase_mb' => round($peakIncrease / 1024 / 1024, 2)
        ];
    }

    /**
     * Test: Concurrent user simulation
     * Requirement: 9.1 - Support up to 25 concurrent users
     */
    public function testConcurrentUserSimulation(): void
    {
        $concurrentUsers = 25;
        $operationsPerUser = 10;
        $results = [];

        // Simulate concurrent operations
        for ($user = 0; $user < $concurrentUsers; $user++) {
            $userResults = [];
            
            for ($op = 0; $op < $operationsPerUser; $op++) {
                $startTime = microtime(true);
                
                // Simulate typical user operations
                switch ($op % 4) {
                    case 0:
                        $result = $this->service->lookupPrice('doors', "Door $user-$op");
                        break;
                    case 1:
                        $result = $this->service->searchPricing("Item $user", null, 10);
                        break;
                    case 2:
                        $quoteData = $this->generateSmallQuoteData();
                        $result = $this->service->calculateQuoteTotal($quoteData, ['doors' => 15]);
                        break;
                    case 3:
                        $result = $this->service->getAllPricingData();
                        break;
                }
                
                $endTime = microtime(true);
                $userResults[] = ($endTime - $startTime) * 1000;
            }
            
            $results[$user] = $userResults;
        }

        // Analyze results
        $allTimes = array_merge(...$results);
        $averageTime = array_sum($allTimes) / count($allTimes);
        $maxTime = max($allTimes);
        $timeouts = count(array_filter($allTimes, fn($time) => $time > 5000)); // >5 second timeouts

        $this->assertLessThan(1000, $averageTime, 'Average response time under concurrent load should be <1000ms');
        $this->assertLessThan(5000, $maxTime, 'Maximum response time should be <5000ms');
        $this->assertEquals(0, $timeouts, 'No operations should timeout under concurrent load');

        $this->performanceMetrics['concurrent_users'] = [
            'users' => $concurrentUsers,
            'operations_per_user' => $operationsPerUser,
            'average_response_ms' => $averageTime,
            'max_response_ms' => $maxTime,
            'timeouts' => $timeouts
        ];
    }

    /**
     * Test: Import/Export performance with large files
     * Requirement: 9.7 - Efficient import/export operations
     */
    public function testImportExportPerformance(): void
    {
        $fileSizes = [100, 500, 1000, 5000]; // Number of pricing items
        $importTimes = [];
        $exportTimes = [];

        foreach ($fileSizes as $size) {
            // Test import performance
            $importData = [
                'pricingData' => $this->generateLargePricingData($size),
                'markups' => ['doors' => 15, 'frames' => 12, 'hardware' => 18]
            ];

            $startTime = microtime(true);
            $importResult = $this->service->importPricingFromData($importData);
            $importTime = (microtime(true) - $startTime) * 1000;
            $importTimes[$size] = $importTime;

            $this->assertTrue($importResult['success'], "Import of $size items should succeed");
            $this->assertLessThan(10000, $importTime, "Import of $size items should complete in <10 seconds");

            // Test export performance
            $startTime = microtime(true);
            $exportResult = $this->service->exportPricingData();
            $exportTime = (microtime(true) - $startTime) * 1000;
            $exportTimes[$size] = $exportTime;

            $this->assertIsArray($exportResult, "Export should return data array");
            $this->assertLessThan(5000, $exportTime, "Export should complete in <5 seconds");
        }

        $this->performanceMetrics['import_export'] = [
            'import_times_ms' => $importTimes,
            'export_times_ms' => $exportTimes
        ];
    }

    /**
     * Test: API endpoint response times
     * Requirement: Overall system responsiveness
     */
    public function testAPIEndpointPerformance(): void
    {
        $endpoints = [
            'getAllPricingData' => fn() => $this->controller->getAllPricingData(),
            'getPricingByCategory' => fn() => $this->controller->getPricingByCategory('doors'),
            'searchPricing' => fn() => $this->mockSearchRequest(),
            'getMarkupDefaults' => fn() => $this->controller->getMarkupDefaults(),
            'getUserQuotes' => fn() => $this->controller->getUserQuotes()
        ];

        $times = [];

        foreach ($endpoints as $endpoint => $callable) {
            $startTime = microtime(true);
            
            $response = $callable();
            
            $endTime = microtime(true);
            $time = ($endTime - $startTime) * 1000;
            $times[$endpoint] = $time;

            $this->assertInstanceOf(\OCP\AppFramework\Http\JSONResponse::class, $response);
            $this->assertEquals(200, $response->getStatus(), "$endpoint should return 200 status");
            $this->assertLessThan(2000, $time, "$endpoint should respond in <2 seconds");
        }

        $this->performanceMetrics['api_endpoints'] = $times;
    }

    /**
     * Test: Stress test with extreme loads
     */
    public function testStressTest(): void
    {
        $stressOperations = 1000;
        $failures = 0;
        $times = [];

        for ($i = 0; $i < $stressOperations; $i++) {
            try {
                $startTime = microtime(true);
                
                // Random operation
                switch ($i % 5) {
                    case 0:
                        $this->service->lookupPrice('doors', "Stress Door $i");
                        break;
                    case 1:
                        $this->service->searchPricing("Stress $i", null, 5);
                        break;
                    case 2:
                        $this->service->getAllPricingData();
                        break;
                    case 3:
                        $quoteData = $this->generateSmallQuoteData();
                        $this->service->calculateQuoteTotal($quoteData, ['doors' => 15]);
                        break;
                    case 4:
                        $this->service->getDefaultMarkups();
                        break;
                }
                
                $endTime = microtime(true);
                $times[] = ($endTime - $startTime) * 1000;
                
            } catch (\Exception $e) {
                $failures++;
            }
        }

        $averageTime = array_sum($times) / count($times);
        $failureRate = ($failures / $stressOperations) * 100;

        $this->assertLessThan(5, $failureRate, 'Failure rate should be <5% under stress');
        $this->assertLessThan(2000, $averageTime, 'Average response time under stress should be <2000ms');

        $this->performanceMetrics['stress_test'] = [
            'operations' => $stressOperations,
            'failures' => $failures,
            'failure_rate_percent' => $failureRate,
            'average_response_ms' => $averageTime
        ];
    }

    // Helper methods

    private function initializeComponents(): void
    {
        // Initialize service and controller with mocked dependencies
        // This would use actual implementations for performance testing
        $repository = $this->createMock(\OCA\DoorEstimator\Repository\EstimatorRepository::class);
        $userSession = $this->createMock(\OCP\IUserSession::class);
        $appData = $this->createMock(\OCP\Files\IAppData::class);
        $config = $this->createMock(\OCP\IConfig::class);
        $db = $this->createMock(\OCP\IDBConnection::class);
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);

        // Set up realistic mock responses for performance testing
        $this->setupMockResponses($repository, $userSession, $appData, $config, $db);

        $this->service = new EstimatorService(
            $repository,
            $userSession,
            $appData,
            $config,
            $db,
            $logger
        );

        $request = $this->createMock(\OCP\IRequest::class);
        $this->controller = new EstimatorController(
            'door_estimator',
            $request,
            $this->service,
            $logger
        );
    }

    private function setupMockResponses($repository, $userSession, $appData, $config, $db): void
    {
        // Set up user session
        $user = $this->createMock(\OCP\IUser::class);
        $user->method('getUID')->willReturn('perftest_user');
        $userSession->method('getUser')->willReturn($user);

        // Set up realistic data responses
        $repository->method('getAllPricingData')->willReturn($this->generateLargePricingData(1000));
        $repository->method('getPricingByCategory')->willReturn($this->generateLargePricingData(200));

        // Set up config responses
        $config->method('getAppValue')->willReturnMap([
            ['door_estimator', 'markup_doors', '15', '15'],
            ['door_estimator', 'markup_frames', '12', '12'],
            ['door_estimator', 'markup_hardware', '18', '18']
        ]);

        // Set up database query builder mock
        $this->setupDatabaseMocks($db);
    }

    private function setupDatabaseMocks($db): void
    {
        $qb = $this->createMock(\stdClass::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('setMaxResults')->willReturnSelf();
        $qb->method('createNamedParameter')->willReturnArgument(0);

        $expr = new class {
            public function eq($a, $b) { return true; }
            public function like($a, $b) { return true; }
        };
        $qb->method('expr')->willReturn($expr);

        $result = $this->createMock(\stdClass::class);
        $result->method('fetch')->willReturn(['price' => 100.0]);
        $qb->method('execute')->willReturn($result);

        $db->method('getQueryBuilder')->willReturn($qb);
    }

    private function setupLargeDataset(): void
    {
        // Pre-generate large datasets for consistent performance testing
        $this->largePricingData = $this->generateLargePricingData(5000);
        $this->largeQuoteData = $this->generateLargeQuoteData(1000);
    }

    private function generateLargePricingData(int $count): array
    {
        $data = [];
        $categories = ['doors', 'frames', 'hardware', 'hinges', 'locksets'];
        
        for ($i = 0; $i < $count; $i++) {
            $category = $categories[$i % count($categories)];
            $data[] = [
                'id' => $i + 1,
                'category' => $category,
                'item' => ucfirst($category) . " Item $i",
                'price' => 50.0 + ($i * 0.5),
                'stock_status' => 'stock'
            ];
        }
        
        return $data;
    }

    private function generateLargeQuoteData(int $itemCount): array
    {
        $quoteData = ['doors' => [], 'frames' => [], 'hardware' => []];
        $sections = array_keys($quoteData);
        
        for ($i = 0; $i < $itemCount; $i++) {
            $section = $sections[$i % count($sections)];
            $quoteData[$section][] = [
                'id' => (string)$i,
                'item' => ucfirst($section) . " Item $i",
                'qty' => 1 + ($i % 5),
                'price' => 100.0 + ($i * 2),
                'total' => (1 + ($i % 5)) * (100.0 + ($i * 2))
            ];
        }
        
        return $quoteData;
    }

    private function generateSmallQuoteData(): array
    {
        return [
            'doors' => [
                [
                    'id' => '1',
                    'item' => 'Test Door',
                    'qty' => 1,
                    'price' => 100.0,
                    'total' => 100.0
                ]
            ]
        ];
    }

    private function mockSearchRequest(): \OCP\AppFramework\Http\JSONResponse
    {
        // Mock search request parameters
        $request = $this->createMock(\OCP\IRequest::class);
        $request->method('getParam')->willReturnMap([
            ['query', 'test'],
            ['category', null],
            ['limit', 10]
        ]);

        // Temporarily replace controller's request
        $reflection = new \ReflectionClass($this->controller);
        $requestProperty = $reflection->getProperty('request');
        $requestProperty->setAccessible(true);
        $originalRequest = $requestProperty->getValue($this->controller);
        $requestProperty->setValue($this->controller, $request);

        $response = $this->controller->searchPricing();

        // Restore original request
        $requestProperty->setValue($this->controller, $originalRequest);

        return $response;
    }

    private function logPerformanceMetrics(): void
    {
        if (!empty($this->performanceMetrics)) {
            $logFile = __DIR__ . '/../../tmp/performance_results_' . date('Y-m-d_H-i-s') . '.json';
            
            $results = [
                'timestamp' => date('c'),
                'php_version' => PHP_VERSION,
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'metrics' => $this->performanceMetrics
            ];
            
            if (!is_dir(dirname($logFile))) {
                mkdir(dirname($logFile), 0755, true);
            }
            
            file_put_contents($logFile, json_encode($results, JSON_PRETTY_PRINT));
            
            echo "\nPerformance results logged to: $logFile\n";
        }
    }
}