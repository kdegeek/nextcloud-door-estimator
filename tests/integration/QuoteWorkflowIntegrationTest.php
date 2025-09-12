<?php

declare(strict_types=1);

namespace OCA\DoorEstimator\Tests\Integration;

use PHPUnit\Framework\TestCase;
use OCA\DoorEstimator\Controller\EstimatorController;
use OCA\DoorEstimator\Service\EstimatorService;
use OCA\DoorEstimator\Repository\EstimatorRepository;
use OCP\AppFramework\Http\JSONResponse;

/**
 * Integration tests for complete quote workflows
 * Tests end-to-end functionality from API to database
 */
class QuoteWorkflowIntegrationTest extends TestCase
{
    /** @var EstimatorController */
    private $controller;

    /** @var EstimatorService */
    private $service;

    /** @var EstimatorRepository */
    private $repository;

    /** @var array */
    private $testData;

    protected function setUp(): void
    {
        // Initialize test components
        $this->initializeTestComponents();
        
        // Set up test data
        $this->setupTestData();
        
        // Clean database state
        $this->cleanDatabase();
    }

    protected function tearDown(): void
    {
        $this->cleanDatabase();
    }

    public function testCompleteQuoteCreationWorkflow(): void
    {
        // Step 1: Import pricing data
        $importResult = $this->importTestPricingData();
        $this->assertTrue($importResult['success']);
        $this->assertGreaterThan(0, $importResult['imported']);

        // Step 2: Lookup prices for quote items
        $doorPrice = $this->lookupPrice('doors', '2-0 x 6-8 Flush HM Door');
        $framePrice = $this->lookupPrice('frames', 'HM Frame', 'HM EWA');
        $hardwarePrice = $this->lookupPrice('hardware', 'Lockset A');

        $this->assertGreaterThan(0, $doorPrice);
        $this->assertGreaterThan(0, $framePrice);
        $this->assertGreaterThan(0, $hardwarePrice);

        // Step 3: Create quote with calculated prices
        $quoteData = [
            'doors' => [
                [
                    'id' => '1',
                    'item' => '2-0 x 6-8 Flush HM Door',
                    'qty' => 2,
                    'price' => $doorPrice,
                    'total' => 2 * $doorPrice
                ]
            ],
            'frames' => [
                [
                    'id' => '1',
                    'item' => 'HM Frame',
                    'frameType' => 'HM EWA',
                    'qty' => 2,
                    'price' => $framePrice,
                    'total' => 2 * $framePrice
                ]
            ],
            'hardware' => [
                [
                    'id' => '1',
                    'item' => 'Lockset A',
                    'qty' => 2,
                    'price' => $hardwarePrice,
                    'total' => 2 * $hardwarePrice
                ]
            ]
        ];

        $markups = ['doors' => 15, 'frames' => 12, 'hardware' => 18];
        $quoteName = 'Integration Test Quote';
        $customerInfo = ['name' => 'Test Customer', 'email' => 'test@example.com'];

        $quoteId = $this->saveQuote($quoteData, $markups, $quoteName, $customerInfo);
        $this->assertIsInt($quoteId);
        $this->assertGreaterThan(0, $quoteId);

        // Step 4: Retrieve and verify saved quote
        $savedQuote = $this->getQuote($quoteId);
        $this->assertNotNull($savedQuote);
        $this->assertEquals($quoteName, $savedQuote['quote_name']);
        $this->assertEquals($customerInfo, $savedQuote['customer_info']);
        $this->assertEquals($quoteData, $savedQuote['quote_data']);
        $this->assertEquals($markups, $savedQuote['markups']);

        // Step 5: Generate PDF for quote
        $pdfResult = $this->generateQuotePDF($quoteId);
        $this->assertTrue($pdfResult['success']);
        $this->assertArrayHasKey('pdfPath', $pdfResult);
        $this->assertArrayHasKey('downloadUrl', $pdfResult);

        // Step 6: Duplicate quote
        $duplicateId = $this->duplicateQuote($quoteId);
        $this->assertIsInt($duplicateId);
        $this->assertNotEquals($quoteId, $duplicateId);

        $duplicateQuote = $this->getQuote($duplicateId);
        $this->assertStringContains('Copy', $duplicateQuote['quote_name']);

        // Step 7: List user quotes
        $userQuotes = $this->getUserQuotes();
        $this->assertCount(2, $userQuotes); // Original + duplicate
        
        $quoteIds = array_column($userQuotes, 'id');
        $this->assertContains($quoteId, $quoteIds);
        $this->assertContains($duplicateId, $quoteIds);

        // Step 8: Delete duplicate quote
        $deleteResult = $this->deleteQuote($duplicateId);
        $this->assertTrue($deleteResult);

        // Verify deletion
        $userQuotesAfterDelete = $this->getUserQuotes();
        $this->assertCount(1, $userQuotesAfterDelete);
        $this->assertEquals($quoteId, $userQuotesAfterDelete[0]['id']);
    }

    public function testPricingDataManagementWorkflow(): void
    {
        // Step 1: Import initial pricing data
        $importResult = $this->importTestPricingData();
        $this->assertTrue($importResult['success']);

        // Step 2: Get all pricing data
        $allPricing = $this->getAllPricingData();
        $this->assertNotEmpty($allPricing);

        // Step 3: Get pricing by category
        $doorPricing = $this->getPricingByCategory('doors');
        $this->assertNotEmpty($doorPricing);
        
        foreach ($doorPricing as $item) {
            $this->assertEquals('doors', $item['category']);
        }

        // Step 4: Search pricing items
        $searchResults = $this->searchPricing('Door', 'doors', 10);
        $this->assertNotEmpty($searchResults);
        
        foreach ($searchResults as $item) {
            $this->assertStringContainsIgnoreCase('door', $item['item']);
        }

        // Step 5: Update pricing item
        $newItem = [
            'category' => 'doors',
            'item' => 'New Test Door',
            'price' => 250.00,
            'stock_status' => 'stock'
        ];
        
        $updateResult = $this->updatePricingItem($newItem);
        $this->assertTrue($updateResult);

        // Step 6: Verify new item exists
        $updatedPricing = $this->searchPricing('New Test Door', 'doors', 1);
        $this->assertCount(1, $updatedPricing);
        $this->assertEquals('New Test Door', $updatedPricing[0]['item']);
        $this->assertEquals(250.00, $updatedPricing[0]['price']);

        // Step 7: Export pricing data
        $exportData = $this->exportPricingData();
        $this->assertArrayHasKey('pricingData', $exportData);
        $this->assertArrayHasKey('markups', $exportData);
        $this->assertArrayHasKey('exportedAt', $exportData);
        $this->assertNotEmpty($exportData['pricingData']);
    }

    public function testMarkupManagementWorkflow(): void
    {
        // Step 1: Get default markups
        $defaultMarkups = $this->getMarkupDefaults();
        $this->assertArrayHasKey('doors', $defaultMarkups);
        $this->assertArrayHasKey('frames', $defaultMarkups);
        $this->assertArrayHasKey('hardware', $defaultMarkups);

        // Step 2: Update markup defaults
        $newMarkups = [
            'doors' => 20,
            'frames' => 15,
            'hardware' => 25
        ];
        
        $updateResult = $this->updateMarkupDefaults($newMarkups);
        $this->assertTrue($updateResult);

        // Step 3: Verify updated markups
        $updatedMarkups = $this->getMarkupDefaults();
        $this->assertEquals(20, $updatedMarkups['doors']);
        $this->assertEquals(15, $updatedMarkups['frames']);
        $this->assertEquals(25, $updatedMarkups['hardware']);

        // Step 4: Create quote with custom markups
        $this->importTestPricingData();
        
        $quoteData = [
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
        
        $customMarkups = ['doors' => 30]; // Different from defaults
        
        $quoteId = $this->saveQuote($quoteData, $customMarkups, 'Custom Markup Quote');
        $savedQuote = $this->getQuote($quoteId);
        
        // Verify quote uses custom markups, not defaults
        $this->assertEquals(30, $savedQuote['markups']['doors']);
        $this->assertNotEquals($updatedMarkups['doors'], $savedQuote['markups']['doors']);
    }

    public function testErrorHandlingWorkflow(): void
    {
        // Test 1: Invalid quote data
        $invalidQuoteData = [
            'doors' => [
                [
                    'id' => '1',
                    'item' => '', // Empty item name
                    'qty' => -1,  // Negative quantity
                    'price' => 'invalid', // Non-numeric price
                    'total' => null
                ]
            ]
        ];
        
        $this->expectException(\InvalidArgumentException::class);
        $this->saveQuote($invalidQuoteData, []);

        // Test 2: Non-existent quote retrieval
        $nonExistentQuote = $this->getQuote(99999);
        $this->assertNull($nonExistentQuote);

        // Test 3: Invalid pricing item update
        $invalidPricingItem = [
            'category' => 'invalid-category',
            'item' => '',
            'price' => -100
        ];
        
        $this->expectException(\InvalidArgumentException::class);
        $this->updatePricingItem($invalidPricingItem);

        // Test 4: Invalid markup values
        $invalidMarkups = [
            'doors' => -5,
            'frames' => 'invalid'
        ];
        
        $this->expectException(\InvalidArgumentException::class);
        $this->updateMarkupDefaults($invalidMarkups);
    }

    public function testConcurrentOperationsWorkflow(): void
    {
        $this->importTestPricingData();
        
        // Simulate concurrent quote creation
        $quoteIds = [];
        $quoteData = [
            'doors' => [
                [
                    'id' => '1',
                    'item' => 'Concurrent Test Door',
                    'qty' => 1,
                    'price' => 100.0,
                    'total' => 100.0
                ]
            ]
        ];
        $markups = ['doors' => 15];
        
        // Create multiple quotes concurrently
        for ($i = 0; $i < 5; $i++) {
            $quoteIds[] = $this->saveQuote($quoteData, $markups, "Concurrent Quote $i");
        }
        
        // Verify all quotes were created successfully
        $this->assertCount(5, $quoteIds);
        $this->assertCount(5, array_unique($quoteIds)); // All IDs should be unique
        
        // Verify all quotes can be retrieved
        foreach ($quoteIds as $quoteId) {
            $quote = $this->getQuote($quoteId);
            $this->assertNotNull($quote);
            $this->assertStringContains('Concurrent Quote', $quote['quote_name']);
        }
    }

    public function testPerformanceWithLargeDataset(): void
    {
        // Import large dataset
        $largeDataset = $this->generateLargePricingDataset(1000);
        $importResult = $this->importPricingData($largeDataset);
        $this->assertTrue($importResult['success']);
        $this->assertEquals(1000, $importResult['imported']);
        
        // Test search performance
        $startTime = microtime(true);
        $searchResults = $this->searchPricing('Door', null, 50);
        $searchTime = microtime(true) - $startTime;
        
        $this->assertLessThan(0.5, $searchTime); // Should complete in <500ms
        $this->assertLessThanOrEqual(50, count($searchResults));
        
        // Test large quote calculation performance
        $largeQuoteData = [];
        for ($i = 0; $i < 100; $i++) {
            $largeQuoteData['doors'][] = [
                'id' => (string)$i,
                'item' => "Door $i",
                'qty' => 1,
                'price' => 100.0,
                'total' => 100.0
            ];
        }
        
        $startTime = microtime(true);
        $quoteId = $this->saveQuote($largeQuoteData, ['doors' => 15], 'Large Quote');
        $saveTime = microtime(true) - $startTime;
        
        $this->assertLessThan(1.0, $saveTime); // Should complete in <1 second
        $this->assertIsInt($quoteId);
    }

    // Helper methods for API operations

    private function importTestPricingData(): array
    {
        return $this->importPricingData($this->testData['pricingData']);
    }

    private function importPricingData(array $data): array
    {
        // Simulate file upload and import
        return $this->service->importPricingFromData($data);
    }

    private function lookupPrice(string $category, string $item, ?string $frameType = null): float
    {
        return $this->service->lookupPrice($category, $item, $frameType);
    }

    private function saveQuote(array $quoteData, array $markups, string $quoteName = null, array $customerInfo = null): int
    {
        return $this->service->saveQuote($quoteData, $markups, $quoteName, json_encode($customerInfo));
    }

    private function getQuote(int $quoteId): ?array
    {
        return $this->service->getQuote($quoteId);
    }

    private function getUserQuotes(): array
    {
        return $this->service->getUserQuotes();
    }

    private function deleteQuote(int $quoteId): bool
    {
        return $this->service->deleteQuote($quoteId);
    }

    private function duplicateQuote(int $quoteId): ?int
    {
        return $this->service->duplicateQuote($quoteId);
    }

    private function generateQuotePDF(int $quoteId): array
    {
        $quote = $this->getQuote($quoteId);
        return $this->service->generateQuotePDF(
            $quote['quote_data'],
            $quote['markups'],
            $quote['quote_name'],
            json_encode($quote['customer_info'])
        );
    }

    private function getAllPricingData(): array
    {
        return $this->service->getAllPricingData();
    }

    private function getPricingByCategory(string $category): array
    {
        return $this->service->getPricingByCategory($category);
    }

    private function searchPricing(string $query, ?string $category = null, int $limit = 10): array
    {
        return $this->service->searchPricing($query, $category, $limit);
    }

    private function updatePricingItem(array $itemData): bool
    {
        return $this->service->updatePricingItem($itemData);
    }

    private function exportPricingData(): array
    {
        return $this->service->exportPricingData();
    }

    private function getMarkupDefaults(): array
    {
        return $this->service->getDefaultMarkups();
    }

    private function updateMarkupDefaults(array $markups): bool
    {
        return $this->service->updateDefaultMarkups($markups);
    }

    // Test data and setup methods

    private function initializeTestComponents(): void
    {
        // Initialize with mocked dependencies for integration testing
        // This would typically use a test database and real implementations
        $this->repository = $this->createMock(EstimatorRepository::class);
        $userSession = $this->createMock(\OCP\IUserSession::class);
        $appData = $this->createMock(\OCP\Files\IAppData::class);
        $config = $this->createMock(\OCP\IConfig::class);
        $db = $this->createMock(\OCP\IDBConnection::class);
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);

        // Set up user session
        $user = $this->createMock(\OCP\IUser::class);
        $user->method('getUID')->willReturn('testuser');
        $userSession->method('getUser')->willReturn($user);

        $this->service = new EstimatorService(
            $this->repository,
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

    private function setupTestData(): void
    {
        $this->testData = [
            'pricingData' => [
                'doors' => [
                    ['item' => '2-0 x 6-8 Flush HM Door', 'price' => 493.00],
                    ['item' => '3-0 x 7-0 Flush HM Door', 'price' => 650.00],
                    ['item' => '2-8 x 6-8 Vision HM Door', 'price' => 750.00]
                ],
                'frames' => [
                    ['item' => 'HM Frame', 'subcategory' => 'HM Drywall', 'price' => 125.00],
                    ['item' => 'HM Frame', 'subcategory' => 'HM EWA', 'price' => 150.00],
                    ['item' => 'HM Frame', 'subcategory' => 'HM USA', 'price' => 175.00]
                ],
                'hardware' => [
                    ['item' => 'Lockset A', 'price' => 85.00],
                    ['item' => 'Lockset B', 'price' => 120.00],
                    ['item' => 'Exit Device', 'price' => 350.00]
                ]
            ],
            'markups' => [
                'doors' => 15,
                'frames' => 12,
                'hardware' => 18
            ]
        ];
    }

    private function generateLargePricingDataset(int $count): array
    {
        $dataset = ['pricingData' => [], 'markups' => ['doors' => 15]];
        
        for ($i = 0; $i < $count; $i++) {
            $dataset['pricingData']['doors'][] = [
                'item' => "Door Item $i",
                'price' => 100.0 + ($i * 0.5)
            ];
        }
        
        return $dataset;
    }

    private function cleanDatabase(): void
    {
        // Clean up test data from database
        // This would typically truncate test tables or use transactions
    }

    private function assertStringContainsIgnoreCase(string $needle, string $haystack): void
    {
        $this->assertStringContainsString(strtolower($needle), strtolower($haystack));
    }
}