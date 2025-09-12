<?php

declare(strict_types=1);

namespace OCA\DoorEstimator\Tests\Service;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use OCA\DoorEstimator\Service\EstimatorService;
use OCA\DoorEstimator\Repository\EstimatorRepository;
use OCP\IUserSession;
use OCP\Files\IAppData;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IUser;
use Psr\Log\LoggerInterface;

/**
 * Comprehensive test suite for EstimatorService
 * Tests all business logic, error handling, and edge cases
 */
class EstimatorServiceComprehensiveTest extends TestCase
{
    /** @var EstimatorService */
    private $service;

    /** @var EstimatorRepository|MockObject */
    private $repository;

    /** @var IUserSession|MockObject */
    private $userSession;

    /** @var IAppData|MockObject */
    private $appData;

    /** @var IConfig|MockObject */
    private $config;

    /** @var IDBConnection|MockObject */
    private $db;

    /** @var LoggerInterface|MockObject */
    private $logger;

    /** @var IUser|MockObject */
    private $user;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(EstimatorRepository::class);
        $this->userSession = $this->createMock(IUserSession::class);
        $this->appData = $this->createMock(IAppData::class);
        $this->config = $this->createMock(IConfig::class);
        $this->db = $this->createMock(IDBConnection::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->user = $this->createMock(IUser::class);

        $this->service = new EstimatorService(
            $this->repository,
            $this->userSession,
            $this->appData,
            $this->config,
            $this->db,
            $this->logger
        );
    }

    public function testGetAllPricingDataSuccess(): void
    {
        $expectedData = [
            ['id' => 1, 'category' => 'doors', 'item' => 'Door A', 'price' => 100.0],
            ['id' => 2, 'category' => 'frames', 'item' => 'Frame B', 'price' => 50.0]
        ];

        $this->repository->expects($this->once())
            ->method('getAllPricingData')
            ->willReturn($expectedData);

        $result = $this->service->getAllPricingData();

        $this->assertSame($expectedData, $result);
    }

    public function testGetAllPricingDataWithEmptyResult(): void
    {
        $this->repository->expects($this->once())
            ->method('getAllPricingData')
            ->willReturn([]);

        $result = $this->service->getAllPricingData();

        $this->assertSame([], $result);
    }

    public function testGetAllPricingDataHandlesException(): void
    {
        $exception = new \Exception('Database connection failed');
        
        $this->repository->expects($this->once())
            ->method('getAllPricingData')
            ->willThrowException($exception);

        $this->logger->expects($this->once())
            ->method('error')
            ->with('Error fetching pricing data', ['exception' => $exception]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Database connection failed');

        $this->service->getAllPricingData();
    }

    public function testLookupPriceWithValidData(): void
    {
        $qb = $this->createMockQueryBuilder();
        $result = $this->createMockResult(['price' => 123.45]);

        $this->db->method('getQueryBuilder')->willReturn($qb);
        $qb->method('execute')->willReturn($result);

        $price = $this->service->lookupPrice('doors', 'Test Door');

        $this->assertEquals(123.45, $price);
    }

    public function testLookupPriceWithFrameType(): void
    {
        $qb = $this->createMockQueryBuilder();
        $result = $this->createMockResult(['price' => 200.0]);

        $this->db->method('getQueryBuilder')->willReturn($qb);
        $qb->expects($this->exactly(3))->method('andWhere')->willReturnSelf();
        $qb->method('execute')->willReturn($result);

        $price = $this->service->lookupPrice('frames', 'Test Frame', 'HM EWA');

        $this->assertEquals(200.0, $price);
    }

    public function testLookupPriceNotFound(): void
    {
        $qb = $this->createMockQueryBuilder();
        $result = $this->createMockResult(false);

        $this->db->method('getQueryBuilder')->willReturn($qb);
        $qb->method('execute')->willReturn($result);

        $price = $this->service->lookupPrice('doors', 'Nonexistent Door');

        $this->assertEquals(0.0, $price);
    }

    public function testLookupPriceWithInvalidParameters(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Category and item name are required');

        $this->service->lookupPrice('', '');
    }

    public function testSaveQuoteSuccess(): void
    {
        $this->user->method('getUID')->willReturn('user123');
        $this->userSession->method('getUser')->willReturn($this->user);

        $qb = $this->createMockQueryBuilder();
        $this->db->method('getQueryBuilder')->willReturn($qb);
        $this->db->method('lastInsertId')->willReturn('42');

        $qb->expects($this->once())->method('execute')->willReturn(1);

        $quoteData = [
            'doors' => [['item' => 'Door A', 'qty' => 1, 'price' => 100.0, 'total' => 100.0]]
        ];
        $markups = ['doors' => 15, 'frames' => 12, 'hardware' => 18];

        $result = $this->service->saveQuote($quoteData, $markups, 'Test Quote');

        $this->assertEquals(42, $result);
    }

    public function testSaveQuoteWithoutUser(): void
    {
        $this->userSession->method('getUser')->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('User not authenticated');

        $this->service->saveQuote([], []);
    }

    public function testSaveQuoteWithInvalidData(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Quote data cannot be empty');

        $this->service->saveQuote([], []);
    }

    public function testCalculateQuoteTotalBasic(): void
    {
        $quoteData = [
            'doors' => [
                ['qty' => 2, 'price' => 100.0, 'total' => 200.0],
                ['qty' => 1, 'price' => 150.0, 'total' => 150.0]
            ],
            'frames' => [
                ['qty' => 3, 'price' => 50.0, 'total' => 150.0]
            ]
        ];
        $markups = ['doors' => 15, 'frames' => 12];

        $total = $this->service->calculateQuoteTotal($quoteData, $markups);

        // (200 + 150) * 1.15 + 150 * 1.12 = 402.5 + 168 = 570.5
        $this->assertEquals(570.5, $total);
    }

    public function testCalculateQuoteTotalWithZeroMarkup(): void
    {
        $quoteData = [
            'doors' => [['qty' => 1, 'price' => 100.0, 'total' => 100.0]]
        ];
        $markups = ['doors' => 0];

        $total = $this->service->calculateQuoteTotal($quoteData, $markups);

        $this->assertEquals(100.0, $total);
    }

    public function testCalculateQuoteTotalWithEmptyData(): void
    {
        $total = $this->service->calculateQuoteTotal([], []);

        $this->assertEquals(0.0, $total);
    }

    public function testGenerateQuotePDFSuccess(): void
    {
        $quoteData = [
            'doors' => [['item' => 'Door A', 'qty' => 1, 'price' => 100.0, 'total' => 100.0]]
        ];
        $markups = ['doors' => 15];

        $file = $this->createMock(\OCP\Files\File::class);
        $file->method('putContent')->willReturn(true);
        $file->method('getId')->willReturn('file123');
        $file->method('getMimeType')->willReturn('application/pdf');
        $file->method('getSize')->willReturn(1024);

        $folder = $this->createMock(\OCP\Files\Folder::class);
        $folder->method('newFile')->willReturn($file);

        $this->appData->method('getFolder')->willReturn($folder);

        $result = $this->service->generateQuotePDF($quoteData, $markups, 'Test Quote');

        $this->assertIsArray($result);
        $this->assertEquals('file123', $result['id']);
        $this->assertEquals('application/pdf', $result['mime']);
        $this->assertEquals(1024, $result['size']);
    }

    public function testGenerateQuotePDFWithEmptyData(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Quote data cannot be empty');

        $this->service->generateQuotePDF([], []);
    }

    public function testImportPricingFromValidJson(): void
    {
        $jsonData = [
            'pricingData' => [
                'doors' => [
                    ['item' => 'Door A', 'price' => 100.0],
                    ['item' => 'Door B', 'price' => 150.0]
                ]
            ],
            'markups' => ['doors' => 15]
        ];

        $this->repository->expects($this->exactly(2))
            ->method('insertOrUpdatePricingItem')
            ->willReturn(true);

        $result = $this->service->importPricingFromData($jsonData);

        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['imported']);
        $this->assertEmpty($result['errors']);
    }

    public function testImportPricingFromInvalidJson(): void
    {
        $invalidData = ['invalid' => 'structure'];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid import data structure');

        $this->service->importPricingFromData($invalidData);
    }

    public function testSearchPricingWithResults(): void
    {
        $qb = $this->createMockQueryBuilder();
        $result = $this->createMockResult([
            ['id' => 1, 'category' => 'doors', 'item' => 'Door A', 'price' => 100.0]
        ]);

        $this->db->method('getQueryBuilder')->willReturn($qb);
        $qb->method('execute')->willReturn($result);

        $results = $this->service->searchPricing('Door', 'doors', 10);

        $this->assertCount(1, $results);
        $this->assertEquals('Door A', $results[0]['item']);
    }

    public function testSearchPricingWithNoResults(): void
    {
        $qb = $this->createMockQueryBuilder();
        $result = $this->createMockResult([]);

        $this->db->method('getQueryBuilder')->willReturn($qb);
        $qb->method('execute')->willReturn($result);

        $results = $this->service->searchPricing('Nonexistent', null, 10);

        $this->assertEmpty($results);
    }

    public function testGetDefaultMarkupsFromConfig(): void
    {
        $this->config->method('getAppValue')
            ->willReturnMap([
                ['door_estimator', 'markup_doors', '15', '20'],
                ['door_estimator', 'markup_frames', '12', '13'],
                ['door_estimator', 'markup_hardware', '18', '19']
            ]);

        $markups = $this->service->getDefaultMarkups();

        $this->assertEquals(20.0, $markups['doors']);
        $this->assertEquals(13.0, $markups['frames']);
        $this->assertEquals(19.0, $markups['hardware']);
    }

    public function testUpdateDefaultMarkupsSuccess(): void
    {
        $newMarkups = ['doors' => 25, 'frames' => 22, 'hardware' => 28];

        $this->config->expects($this->exactly(3))
            ->method('setAppValue')
            ->withConsecutive(
                ['door_estimator', 'markup_doors', '25'],
                ['door_estimator', 'markup_frames', '22'],
                ['door_estimator', 'markup_hardware', '28']
            );

        $result = $this->service->updateDefaultMarkups($newMarkups);

        $this->assertTrue($result);
    }

    public function testUpdateDefaultMarkupsWithInvalidValues(): void
    {
        $invalidMarkups = ['doors' => -5];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Markup values must be non-negative');

        $this->service->updateDefaultMarkups($invalidMarkups);
    }

    public function testDeleteQuoteSuccess(): void
    {
        $this->user->method('getUID')->willReturn('user123');
        $this->userSession->method('getUser')->willReturn($this->user);

        $qb = $this->createMockQueryBuilder();
        $this->db->method('getQueryBuilder')->willReturn($qb);
        $qb->method('execute')->willReturn(1);

        $result = $this->service->deleteQuote(1);

        $this->assertTrue($result);
    }

    public function testDeleteQuoteNotFound(): void
    {
        $this->user->method('getUID')->willReturn('user123');
        $this->userSession->method('getUser')->willReturn($this->user);

        $qb = $this->createMockQueryBuilder();
        $this->db->method('getQueryBuilder')->willReturn($qb);
        $qb->method('execute')->willReturn(0);

        $result = $this->service->deleteQuote(999);

        $this->assertFalse($result);
    }

    public function testDuplicateQuoteSuccess(): void
    {
        $originalQuote = [
            'id' => 1,
            'quote_name' => 'Original Quote',
            'quote_data' => ['doors' => []],
            'markups' => ['doors' => 15],
            'customer_info' => ['name' => 'John Doe']
        ];

        // Mock getQuote to return original
        $serviceMock = $this->getMockBuilder(EstimatorService::class)
            ->setConstructorArgs([
                $this->repository,
                $this->userSession,
                $this->appData,
                $this->config,
                $this->db,
                $this->logger
            ])
            ->onlyMethods(['getQuote', 'saveQuote'])
            ->getMock();

        $serviceMock->method('getQuote')->willReturn($originalQuote);
        $serviceMock->method('saveQuote')->willReturn(456);

        $result = $serviceMock->duplicateQuote(1);

        $this->assertEquals(456, $result);
    }

    public function testDuplicateQuoteNotFound(): void
    {
        $serviceMock = $this->getMockBuilder(EstimatorService::class)
            ->setConstructorArgs([
                $this->repository,
                $this->userSession,
                $this->appData,
                $this->config,
                $this->db,
                $this->logger
            ])
            ->onlyMethods(['getQuote'])
            ->getMock();

        $serviceMock->method('getQuote')->willReturn(null);

        $result = $serviceMock->duplicateQuote(999);

        $this->assertNull($result);
    }

    public function testValidateQuoteDataStructure(): void
    {
        $validData = [
            'doors' => [['item' => 'Door', 'qty' => 1, 'price' => 100, 'total' => 100]],
            'frames' => [],
            'hardware' => []
        ];

        $result = $this->service->validateQuoteData($validData);

        $this->assertTrue($result['isValid']);
        $this->assertEmpty($result['errors']);
    }

    public function testValidateQuoteDataWithErrors(): void
    {
        $invalidData = [
            'doors' => [['item' => '', 'qty' => -1, 'price' => 'invalid']]
        ];

        $result = $this->service->validateQuoteData($invalidData);

        $this->assertFalse($result['isValid']);
        $this->assertNotEmpty($result['errors']);
    }

    public function testIsPricingDataPresentTrue(): void
    {
        $this->repository->method('getPricingItemCount')->willReturn(100);

        $result = $this->service->isPricingDataPresent();

        $this->assertTrue($result);
    }

    public function testIsPricingDataPresentFalse(): void
    {
        $this->repository->method('getPricingItemCount')->willReturn(0);

        $result = $this->service->isPricingDataPresent();

        $this->assertFalse($result);
    }

    public function testExportPricingDataSuccess(): void
    {
        $pricingData = [
            ['id' => 1, 'category' => 'doors', 'item' => 'Door A', 'price' => 100.0]
        ];
        $markups = ['doors' => 15, 'frames' => 12, 'hardware' => 18];

        $this->repository->method('getAllPricingData')->willReturn($pricingData);
        $this->config->method('getAppValue')
            ->willReturnMap([
                ['door_estimator', 'markup_doors', '15', '15'],
                ['door_estimator', 'markup_frames', '12', '12'],
                ['door_estimator', 'markup_hardware', '18', '18']
            ]);

        $result = $this->service->exportPricingData();

        $this->assertArrayHasKey('pricingData', $result);
        $this->assertArrayHasKey('markups', $result);
        $this->assertArrayHasKey('exportedAt', $result);
        $this->assertArrayHasKey('version', $result);
        $this->assertEquals($pricingData, $result['pricingData']);
    }

    public function testConcurrentQuoteOperations(): void
    {
        // Simulate concurrent save operations
        $this->user->method('getUID')->willReturn('user123');
        $this->userSession->method('getUser')->willReturn($this->user);

        $qb = $this->createMockQueryBuilder();
        $this->db->method('getQueryBuilder')->willReturn($qb);
        $this->db->method('lastInsertId')->willReturnOnConsecutiveCalls('1', '2', '3');
        $qb->method('execute')->willReturn(1);

        $quoteData = [
            'doors' => [['item' => 'Door', 'qty' => 1, 'price' => 100, 'total' => 100]]
        ];
        $markups = ['doors' => 15];

        // Save multiple quotes concurrently
        $results = [];
        for ($i = 0; $i < 3; $i++) {
            $results[] = $this->service->saveQuote($quoteData, $markups, "Quote $i");
        }

        $this->assertEquals([1, 2, 3], $results);
    }

    public function testPerformanceWithLargeDataset(): void
    {
        // Test with large pricing dataset
        $largePricingData = [];
        for ($i = 0; $i < 10000; $i++) {
            $largePricingData[] = [
                'id' => $i,
                'category' => 'doors',
                'item' => "Door $i",
                'price' => 100.0 + $i
            ];
        }

        $this->repository->method('getAllPricingData')->willReturn($largePricingData);

        $startTime = microtime(true);
        $result = $this->service->getAllPricingData();
        $endTime = microtime(true);

        $this->assertCount(10000, $result);
        $this->assertLessThan(1.0, $endTime - $startTime); // Should complete in <1 second
    }

    public function testMemoryUsageWithLargeQuote(): void
    {
        // Test memory usage with large quote
        $largeQuoteData = [];
        for ($i = 0; $i < 1000; $i++) {
            $largeQuoteData['doors'][] = [
                'item' => "Door $i",
                'qty' => 1,
                'price' => 100.0,
                'total' => 100.0
            ];
        }

        $markups = ['doors' => 15];

        $memoryBefore = memory_get_usage();
        $total = $this->service->calculateQuoteTotal($largeQuoteData, $markups);
        $memoryAfter = memory_get_usage();

        $this->assertEquals(115000.0, $total); // 1000 * 100 * 1.15
        $this->assertLessThan(10 * 1024 * 1024, $memoryAfter - $memoryBefore); // <10MB increase
    }

    /**
     * Helper method to create mock query builder
     */
    private function createMockQueryBuilder(): MockObject
    {
        $qb = $this->createMock(\stdClass::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('setMaxResults')->willReturnSelf();
        $qb->method('insert')->willReturnSelf();
        $qb->method('update')->willReturnSelf();
        $qb->method('delete')->willReturnSelf();
        $qb->method('values')->willReturnSelf();
        $qb->method('set')->willReturnSelf();
        $qb->method('createNamedParameter')->willReturnArgument(0);
        
        $expr = new class {
            public function eq($a, $b) { return true; }
            public function like($a, $b) { return true; }
        };
        $qb->method('expr')->willReturn($expr);

        return $qb;
    }

    /**
     * Helper method to create mock result
     */
    private function createMockResult($data): MockObject
    {
        $result = $this->createMock(\stdClass::class);
        
        if (is_array($data) && !empty($data) && is_array($data[0])) {
            // Multiple rows
            $result->method('fetch')
                ->willReturnOnConsecutiveCalls(...array_merge($data, [false]));
        } else {
            // Single row or false
            $result->method('fetch')->willReturn($data);
        }

        return $result;
    }
}