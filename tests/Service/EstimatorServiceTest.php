<?php

use PHPUnit\Framework\TestCase;
use OCA\DoorEstimator\Service\EstimatorService;

class EstimatorServiceTest extends TestCase
{
    protected $service;
    protected $repository;
    protected $userSession;
    protected $appData;
    protected $config;
    protected $db;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(\OCA\DoorEstimator\Repository\EstimatorRepository::class);
        $this->userSession = $this->createMock(\OCP\IUserSession::class);
        $this->appData = $this->createMock(\OCP\Files\IAppData::class);
        $this->config = $this->createMock(\OCP\IConfig::class);
        $this->db = $this->createMock(\OCP\IDBConnection::class);

        $this->service = new EstimatorService(
            $this->repository,
            $this->userSession,
            $this->appData,
            $this->config,
            $this->db
        );
    }

    // --- Mock-only tests removed: replaced by real logic tests below ---

    // --- getAllPricingData/getPricingByCategory ---
    public function testGetAllPricingDataReturnsRepositoryData()
    {
        $expected = [
            ['id' => 1, 'item' => 'Door A', 'price' => 100.0],
            ['id' => 2, 'item' => 'Frame B', 'price' => 50.0]
        ];
        $this->repository->expects($this->once())
            ->method('getAllPricingData')
            ->willReturn($expected);

        $result = $this->service->getAllPricingData();
        $this->assertSame($expected, $result);
    }

    public function testGetAllPricingDataHandlesRepositoryException()
    {
        $this->repository->expects($this->once())
            ->method('getAllPricingData')
            ->will($this->throwException(new \Exception('DB error')));

        $this->expectException(\Exception::class);
        $this->service->getAllPricingData();
    }

    public function testGetPricingByCategoryReturnsRepositoryData()
    {
        $expected = [
            ['id' => 1, 'item' => 'Door A', 'price' => 100.0]
        ];
        $this->repository->expects($this->once())
            ->method('getPricingByCategory')
            ->with('doors')
            ->willReturn($expected);

        $result = $this->service->getPricingByCategory('doors');
        $this->assertSame($expected, $result);
    }

    public function testGetPricingByCategoryHandlesRepositoryException()
    {
        $this->repository->expects($this->once())
            ->method('getPricingByCategory')
            ->with('doors')
            ->will($this->throwException(new \Exception('DB error')));

        $this->expectException(\Exception::class);
        $this->service->getPricingByCategory('doors');
    }

    // --- lookupPrice ---
    public function testLookupPriceReturnsCorrectPrice()
    {
        $qb = $this->createMock(\stdClass::class);
        $resultMock = $this->createMock(\stdClass::class);

        $this->db->method('getQueryBuilder')->willReturn($qb);

        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('execute')->willReturn($resultMock);
        $resultMock->method('fetch')->willReturn(['price' => 123.45]);

        $qb->method('expr')->willReturn(new class {
            public function eq($a, $b) { return true; }
        });
        $qb->method('createNamedParameter')->willReturnArgument(0);

        $price = $this->service->lookupPrice('doors', 'Door A');
        $this->assertEquals(123.45, $price);
    }

    public function testLookupPriceWithFrameType()
    {
        $qb = $this->createMock(\stdClass::class);
        $resultMock = $this->createMock(\stdClass::class);

        $this->db->method('getQueryBuilder')->willReturn($qb);

        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->expects($this->atLeastOnce())->method('andWhere')->willReturnSelf();
        $qb->method('execute')->willReturn($resultMock);
        $resultMock->method('fetch')->willReturn(['price' => 77.77]);

        $qb->method('expr')->willReturn(new class {
            public function eq($a, $b) { return true; }
        });
        $qb->method('createNamedParameter')->willReturnArgument(0);

        $price = $this->service->lookupPrice('frames', 'Frame B', 'Steel');
        $this->assertEquals(77.77, $price);
    }

    public function testLookupPriceReturnsZeroIfNotFound()
    {
        $qb = $this->createMock(\stdClass::class);
        $resultMock = $this->createMock(\stdClass::class);

        $this->db->method('getQueryBuilder')->willReturn($qb);

        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('execute')->willReturn($resultMock);
        $resultMock->method('fetch')->willReturn(false);

        $qb->method('expr')->willReturn(new class {
            public function eq($a, $b) { return true; }
        });
        $qb->method('createNamedParameter')->willReturnArgument(0);

        $price = $this->service->lookupPrice('doors', 'Nonexistent');
        $this->assertEquals(0.0, $price);
    }

    public function testLookupPriceHandlesDbException()
    {
        $qb = $this->createMock(\stdClass::class);

        $this->db->method('getQueryBuilder')->willReturn($qb);

        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('execute')->will($this->throwException(new \Exception('DB error')));

        $qb->method('expr')->willReturn(new class {
            public function eq($a, $b) { return true; }
        });
        $qb->method('createNamedParameter')->willReturnArgument(0);

        $this->expectException(\Exception::class);
        $this->service->lookupPrice('doors', 'Door A');
    }

    // --- updatePricingItem ---
    public function testUpdatePricingItemThrowsOnMissingFields()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->updatePricingItem(['price' => 10.0, 'category' => 'doors']);
    }

    public function testUpdatePricingItemThrowsOnInvalidTypes()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->updatePricingItem(['item' => '', 'price' => 'not-a-number', 'category' => '']);
    }

    public function testUpdatePricingItemInsertSuccess()
    {
        $qb = $this->createMock(\stdClass::class);
        $qb->method('insert')->willReturnSelf();
        $qb->method('values')->willReturnSelf();
        $qb->method('execute')->willReturn(1);

        $this->db->method('getQueryBuilder')->willReturn($qb);

        $data = [
            'item' => 'Door A',
            'price' => 100.0,
            'category' => 'doors'
        ];
        $result = $this->service->updatePricingItem($data);
        $this->assertTrue($result);
    }

    public function testUpdatePricingItemUpdateSuccess()
    {
        $qb = $this->createMock(\stdClass::class);
        $qb->method('update')->willReturnSelf();
        $qb->method('set')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('execute')->willReturn(1);

        $this->db->method('getQueryBuilder')->willReturn($qb);

        $data = [
            'id' => 1,
            'item' => 'Door A',
            'price' => 100.0,
            'category' => 'doors'
        ];
        $result = $this->service->updatePricingItem($data);
        $this->assertTrue($result);
    }

    public function testUpdatePricingItemInsertConstraintViolation()
    {
        $qb = $this->createMock(\stdClass::class);
        $qb->method('insert')->willReturnSelf();
        $qb->method('values')->willReturnSelf();
        $qb->method('execute')->willReturn(0);

        $this->db->method('getQueryBuilder')->willReturn($qb);

        $data = [
            'item' => 'Door B',
            'price' => 50.0,
            'category' => 'doors'
        ];
        $result = $this->service->updatePricingItem($data);
        $this->assertFalse($result);
    }

    public function testUpdatePricingItemUpdateConstraintViolation()
    {
        $qb = $this->createMock(\stdClass::class);
        $qb->method('update')->willReturnSelf();
        $qb->method('set')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('execute')->willReturn(0);

        $this->db->method('getQueryBuilder')->willReturn($qb);

        $data = [
            'id' => 2,
            'item' => 'Frame A',
            'price' => 75.0,
            'category' => 'frames'
        ];
        $result = $this->service->updatePricingItem($data);
        $this->assertFalse($result);
    }

    public function testUpdatePricingItemHandlesDbException()
    {
        $qb = $this->createMock(\stdClass::class);
        $qb->method('insert')->willReturnSelf();
        $qb->method('values')->willReturnSelf();
        $qb->method('execute')->will($this->throwException(new \Exception('DB error')));

        $this->db->method('getQueryBuilder')->willReturn($qb);

        $data = [
            'item' => 'Door C',
            'price' => 120.0,
            'category' => 'doors'
        ];
        $this->expectException(\Exception::class);
        $this->service->updatePricingItem($data);
    }

    // --- saveQuote ---
    public function testSaveQuoteSuccess()
    {
        $userMock = $this->createMock(\stdClass::class);
        $userMock->method('getUID')->willReturn('user123');
        $this->userSession->method('getUser')->willReturn($userMock);

        $qb = $this->createMock(\stdClass::class);
        $qb->method('insert')->willReturnSelf();
        $qb->method('values')->willReturnSelf();
        $qb->method('execute')->willReturn(1);

        $this->db->method('getQueryBuilder')->willReturn($qb);
        $this->db->method('lastInsertId')->willReturn(42);

        $quoteData = [['item' => 'Door A', 'qty' => 1, 'price' => 100.0]];
        $markups = ['doors' => 10, 'frames' => 5, 'hardware' => 8];
        $result = $this->service->saveQuote($quoteData, $markups, 'Test Quote', '{"customer":"info"}');
        $this->assertEquals(42, $result);
    }

    public function testSaveQuoteHandlesDbException()
    {
        $userMock = $this->createMock(\stdClass::class);
        $userMock->method('getUID')->willReturn('user123');
        $this->userSession->method('getUser')->willReturn($userMock);

        $qb = $this->createMock(\stdClass::class);
        $qb->method('insert')->willReturnSelf();
        $qb->method('values')->willReturnSelf();
        $qb->method('execute')->will($this->throwException(new \Exception('DB error')));

        $this->db->method('getQueryBuilder')->willReturn($qb);

        $quoteData = [['item' => 'Door A', 'qty' => 1, 'price' => 100.0]];
        $markups = ['doors' => 10, 'frames' => 5, 'hardware' => 8];

        $this->expectException(\Exception::class);
        $this->service->saveQuote($quoteData, $markups);
    }

    public function testSaveQuoteThrowsOnMissingUser()
    {
        $this->userSession->method('getUser')->willReturn(null);

        $qb = $this->createMock(\stdClass::class);
        $qb->method('insert')->willReturnSelf();
        $qb->method('values')->willReturnSelf();
        $qb->method('execute')->willReturn(1);

        $this->db->method('getQueryBuilder')->willReturn($qb);
        $this->db->method('lastInsertId')->willReturn(1);

        $quoteData = [['item' => 'Door A', 'qty' => 1, 'price' => 100.0]];
        $markups = ['doors' => 10, 'frames' => 5, 'hardware' => 8];

        $this->expectException(\Error::class);
        $this->service->saveQuote($quoteData, $markups);
    }

    // --- getQuote/getUserQuotes ---
    public function testGetQuoteReturnsValidQuote()
    {
        $userMock = $this->createMock(\stdClass::class);
        $userMock->method('getUID')->willReturn('user123');
        $this->userSession->method('getUser')->willReturn($userMock);

        $qb = $this->createMock(\stdClass::class);
        $resultMock = $this->createMock(\stdClass::class);

        $this->db->method('getQueryBuilder')->willReturn($qb);

        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('execute')->willReturn($resultMock);

        $resultMock->method('fetch')->willReturn([
            'id' => 1,
            'quote_name' => 'Test Quote',
            'customer_info' => '{"name":"John"}',
            'quote_data' => '[{"item":"Door A"}]',
            'markups' => '{"doors":10}',
            'total_amount' => 100.0,
            'created_at' => '2025-01-01 12:00:00',
            'updated_at' => '2025-01-01 12:00:00'
        ]);

        $quote = $this->service->getQuote(1);
        $this->assertIsArray($quote);
        $this->assertEquals(1, $quote['id']);
        $this->assertEquals('Test Quote', $quote['quote_name']);
        $this->assertEquals(['name' => 'John'], $quote['customer_info']);
        $this->assertEquals([['item' => 'Door A']], $quote['quote_data']);
        $this->assertEquals(['doors' => 10], $quote['markups']);
        $this->assertEquals(100.0, $quote['total_amount']);
    }

    public function testGetQuoteReturnsNullIfNotFound()
    {
        $userMock = $this->createMock(\stdClass::class);
        $userMock->method('getUID')->willReturn('user123');
        $this->userSession->method('getUser')->willReturn($userMock);

        $qb = $this->createMock(\stdClass::class);
        $resultMock = $this->createMock(\stdClass::class);

        $this->db->method('getQueryBuilder')->willReturn($qb);

        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('execute')->willReturn($resultMock);

        $resultMock->method('fetch')->willReturn(false);

        $quote = $this->service->getQuote(999);
        $this->assertNull($quote);
    }

    public function testGetQuoteHandlesMalformedJson()
    {
        $userMock = $this->createMock(\stdClass::class);
        $userMock->method('getUID')->willReturn('user123');
        $this->userSession->method('getUser')->willReturn($userMock);

        $qb = $this->createMock(\stdClass::class);
        $resultMock = $this->createMock(\stdClass::class);

        $this->db->method('getQueryBuilder')->willReturn($qb);

        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('execute')->willReturn($resultMock);

        $resultMock->method('fetch')->willReturn([
            'id' => 2,
            'quote_name' => 'Bad Quote',
            'customer_info' => '{bad json}',
            'quote_data' => '[malformed]',
            'markups' => '{bad}',
            'total_amount' => 0.0,
            'created_at' => '2025-01-01 12:00:00',
            'updated_at' => '2025-01-01 12:00:00'
        ]);

        $quote = $this->service->getQuote(2);
        $this->assertIsArray($quote);
        $this->assertNull($quote['customer_info']);
        $this->assertNull($quote['quote_data']);
        $this->assertNull($quote['markups']);
    }

    public function testGetUserQuotesReturnsList()
    {
        $userMock = $this->createMock(\stdClass::class);
        $userMock->method('getUID')->willReturn('user123');
        $this->userSession->method('getUser')->willReturn($userMock);

        $qb = $this->createMock(\stdClass::class);
        $resultMock = $this->createMock(\stdClass::class);

        $this->db->method('getQueryBuilder')->willReturn($qb);

        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('execute')->willReturn($resultMock);

        $resultMock->method('fetch')->willReturnOnConsecutiveCalls(
            [
                'id' => 1,
                'quote_name' => 'Quote 1',
                'total_amount' => 100.0,
                'created_at' => '2025-01-01 12:00:00',
                'updated_at' => '2025-01-01 12:00:00'
            ],
            [
                'id' => 2,
                'quote_name' => 'Quote 2',
                'total_amount' => 200.0,
                'created_at' => '2025-01-02 12:00:00',
                'updated_at' => '2025-01-02 12:00:00'
            ],
            false
        );

        $quotes = $this->service->getUserQuotes();
        $this->assertCount(2, $quotes);
        $this->assertEquals(1, $quotes[0]['id']);
        $this->assertEquals(2, $quotes[1]['id']);
    }

    public function testGetUserQuotesHandlesDbException()
    {
        $userMock = $this->createMock(\stdClass::class);
        $userMock->method('getUID')->willReturn('user123');
        $this->userSession->method('getUser')->willReturn($userMock);

        $qb = $this->createMock(\stdClass::class);

        $this->db->method('getQueryBuilder')->willReturn($qb);

        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('execute')->will($this->throwException(new \Exception('DB error')));

        $this->expectException(\Exception::class);
        $this->service->getUserQuotes();
    }
public function testGetDefaultMarkupsReturnsConfigValues()
{
    $this->config->method('getAppValue')
        ->willReturnMap([
            ['door_estimator', 'markup_doors', '15', '20'],
            ['door_estimator', 'markup_frames', '12', '13'],
            ['door_estimator', 'markup_hardware', '18', '19']
        ]);

    $markups = $this->service->getDefaultMarkups();
    $this->assertEquals(['doors' => 20.0, 'frames' => 13.0, 'hardware' => 19.0], $markups);
}

public function testGetDefaultMarkupsFallsBackToDefaults()
{
    $this->config->method('getAppValue')
        ->willReturnMap([
            ['door_estimator', 'markup_doors', '15', '15'],
            ['door_estimator', 'markup_frames', '12', '12'],
            ['door_estimator', 'markup_hardware', '18', '18']
        ]);

    $markups = $this->service->getDefaultMarkups();
    $this->assertEquals(['doors' => 15.0, 'frames' => 12.0, 'hardware' => 18.0], $markups);
}

public function testUpdateDefaultMarkupsSetsConfigValues()
{
    $this->config->expects($this->exactly(3))
        ->method('setAppValue')
        ->withConsecutive(
            ['door_estimator', 'markup_doors', '25'],
            ['door_estimator', 'markup_frames', '22'],
            ['door_estimator', 'markup_hardware', '28']
        );

    $result = $this->service->updateDefaultMarkups(['doors' => 25, 'frames' => 22, 'hardware' => 28]);
    $this->assertTrue($result);
}

public function testUpdateDefaultMarkupsPartialUpdate()
{
    $this->config->expects($this->once())
        ->method('setAppValue')
        ->with('door_estimator', 'markup_doors', '30');

    $result = $this->service->updateDefaultMarkups(['doors' => 30]);
    $this->assertTrue($result);
}

public function testUpdateDefaultMarkupsHandlesConfigException()
{
    $this->config->method('setAppValue')
        ->will($this->throwException(new \Exception('Config error')));

    $this->expectException(\Exception::class);
    $this->service->updateDefaultMarkups(['doors' => 99]);
}

    // --- deleteQuote/duplicateQuote ---
    public function testDeleteQuoteSuccess()
    {
        $userMock = $this->createMock(\stdClass::class);
        $userMock->method('getUID')->willReturn('user123');
        $this->userSession->method('getUser')->willReturn($userMock);

        $qb = $this->createMock(\stdClass::class);
        $qb->method('delete')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('execute')->willReturn(1);

        $this->db->method('getQueryBuilder')->willReturn($qb);

        $result = $this->service->deleteQuote(1);
        $this->assertTrue($result);
    }

    public function testDeleteQuoteFailure()
    {
        $userMock = $this->createMock(\stdClass::class);
        $userMock->method('getUID')->willReturn('user123');
        $this->userSession->method('getUser')->willReturn($userMock);

        $qb = $this->createMock(\stdClass::class);
        $qb->method('delete')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('execute')->willReturn(0);

        $this->db->method('getQueryBuilder')->willReturn($qb);

        $result = $this->service->deleteQuote(999);
        $this->assertFalse($result);
    }

    public function testDeleteQuoteHandlesDbException()
    {
        $userMock = $this->createMock(\stdClass::class);
        $userMock->method('getUID')->willReturn('user123');
        $this->userSession->method('getUser')->willReturn($userMock);

        $qb = $this->createMock(\stdClass::class);
        $qb->method('delete')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('execute')->will($this->throwException(new \Exception('DB error')));

        $this->db->method('getQueryBuilder')->willReturn($qb);

        $this->expectException(\Exception::class);
        $this->service->deleteQuote(1);
    }

    public function testDuplicateQuoteSuccess()
    {
        $serviceMock = $this->getMockBuilder(EstimatorService::class)
            ->setConstructorArgs([
                $this->repository,
                $this->userSession,
                $this->appData,
                $this->config,
                $this->db
            ])
            ->onlyMethods(['getQuote', 'saveQuote'])
            ->getMock();

        $serviceMock->method('getQuote')->willReturn([
            'id' => 1,
            'quote_name' => 'Test Quote',
            'customer_info' => ['name' => 'John'],
            'quote_data' => [['item' => 'Door A']],
            'markups' => ['doors' => 10]
        ]);
        $serviceMock->method('saveQuote')->willReturn(99);

        $result = $serviceMock->duplicateQuote(1);
        $this->assertEquals(99, $result);
    }

    public function testDuplicateQuoteReturnsNullIfNotFound()
    {
        $serviceMock = $this->getMockBuilder(EstimatorService::class)
            ->setConstructorArgs([
                $this->repository,
                $this->userSession,
                $this->appData,
                $this->config,
                $this->db
            ])
            ->onlyMethods(['getQuote', 'saveQuote'])
            ->getMock();

        $serviceMock->method('getQuote')->willReturn(null);

        $result = $serviceMock->duplicateQuote(999);
        $this->assertNull($result);
    }

    // --- searchPricing ---
    public function testSearchPricingReturnsResults()
    {
        $qb = $this->createMock(\stdClass::class);
        $resultMock = $this->createMock(\stdClass::class);

        $this->db->method('getQueryBuilder')->willReturn($qb);

        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('setMaxResults')->willReturnSelf();
        $qb->method('execute')->willReturn($resultMock);

        $resultMock->method('fetch')->willReturnOnConsecutiveCalls(
            [
                'id' => 1,
                'category' => 'doors',
                'subcategory' => 'wood',
                'item_name' => 'Door A',
                'price' => 100.0,
                'stock_status' => 'stock'
            ],
            false
        );

        $results = $this->service->searchPricing('Door', 'doors', 10);
        $this->assertCount(1, $results);
        $this->assertEquals('Door A', $results[0]['item']);
    }

    public function testSearchPricingWithNoCategory()
    {
        $qb = $this->createMock(\stdClass::class);
        $resultMock = $this->createMock(\stdClass::class);

        $this->db->method('getQueryBuilder')->willReturn($qb);

        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('setMaxResults')->willReturnSelf();
        $qb->method('execute')->willReturn($resultMock);

        $resultMock->method('fetch')->willReturnOnConsecutiveCalls(
            [
                'id' => 2,
                'category' => 'frames',
                'subcategory' => 'steel',
                'item_name' => 'Frame B',
                'price' => 50.0,
                'stock_status' => 'stock'
            ],
            false
        );

        $results = $this->service->searchPricing('Frame', null, 5);
        $this->assertCount(1, $results);
        $this->assertEquals('Frame B', $results[0]['item']);
    }

    public function testSearchPricingHandlesDbException()
    {
        $qb = $this->createMock(\stdClass::class);

        $this->db->method('getQueryBuilder')->willReturn($qb);

        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('setMaxResults')->willReturnSelf();
        $qb->method('execute')->will($this->throwException(new \Exception('DB error')));

        $this->expectException(\Exception::class);
        $this->service->searchPricing('Door');
    }
    // --- generateQuotePDF ---
    public function testGenerateQuotePDFSuccess()
    {
        $quoteData = [['item' => 'Door A', 'qty' => 1, 'price' => 100.0]];
        $markups = ['doors' => 10, 'frames' => 5, 'hardware' => 8];
        $quoteName = 'Test Quote';
        $customerInfo = '{"customer":"info"}';

        $fileMock = $this->createMock(\stdClass::class);
        $fileMock->method('putContent')->willReturn(true);
        $fileMock->method('getId')->willReturn('fileid123');
        $fileMock->method('getMimeType')->willReturn('application/pdf');
        $fileMock->method('getSize')->willReturn(1024);

        $folderMock = $this->createMock(\stdClass::class);
        $folderMock->method('newFile')->willReturn($fileMock);

        $this->appData->method('getFolder')->willReturn($folderMock);

        $result = $this->service->generateQuotePDF($quoteData, $markups, $quoteName, $customerInfo);
        $this->assertIsArray($result);
        $this->assertEquals('fileid123', $result['id']);
        $this->assertEquals('application/pdf', $result['mime']);
        $this->assertEquals(1024, $result['size']);
    }

    public function testGenerateQuotePDFHandlesFileWriteError()
    {
        $quoteData = [['item' => 'Door A', 'qty' => 1, 'price' => 100.0]];
        $markups = ['doors' => 10, 'frames' => 5, 'hardware' => 8];
        $quoteName = 'Test Quote';
        $customerInfo = '{"customer":"info"}';

        $fileMock = $this->createMock(\stdClass::class);
        $fileMock->method('putContent')->will($this->throwException(new \Exception('Write error')));

        $folderMock = $this->createMock(\stdClass::class);
        $folderMock->method('newFile')->willReturn($fileMock);

        $this->appData->method('getFolder')->willReturn($folderMock);

        $this->expectException(\Exception::class);
        $this->service->generateQuotePDF($quoteData, $markups, $quoteName, $customerInfo);
    }
    // --- importPricingFromUpload ---

    public function testGenerateQuotePDFHandlesMissingFolder()
    {
        $quoteData = [['item' => 'Door A', 'qty' => 1, 'price' => 100.0]];
        $markups = ['doors' => 10, 'frames' => 5, 'hardware' => 8];
        $quoteName = 'Test Quote';
        $customerInfo = '{"customer":"info"}';

        $this->appData->method('getFolder')->will($this->throwException(new \Exception('Folder not found')));

        $this->expectException(\Exception::class);
        $this->service->generateQuotePDF($quoteData, $markups, $quoteName, $customerInfo);
    }
    // --- importPricingFromUpload ---
    // --- importPricingFromUpload ---
    public function testImportPricingFromUploadValidCsv()
    {
        $fileMock = $this->createMock(\stdClass::class);
        $fileMock->method('getMimeType')->willReturn('text/csv');
        $fileMock->method('getContent')->willReturn("item,price,category\nDoor A,100,doors\nFrame B,50,frames");

        $this->repository->expects($this->exactly(2))
            ->method('insertOrUpdatePricingItem')
            ->withConsecutive(
                [['item' => 'Door A', 'price' => 100.0, 'category' => 'doors']],
                [['item' => 'Frame B', 'price' => 50.0, 'category' => 'frames']]
            );

        $result = $this->service->importPricingFromUpload($fileMock);
        $this->assertTrue($result);
    }

    public function testImportPricingFromUploadHandlesInvalidMimeType()
    {
        $fileMock = $this->createMock(\stdClass::class);
        $fileMock->method('getMimeType')->willReturn('application/pdf');

        $this->expectException(\InvalidArgumentException::class);
        $this->service->importPricingFromUpload($fileMock);
    }

    public function testImportPricingFromUploadHandlesMalformedCsv()
    {
        $fileMock = $this->createMock(\stdClass::class);
        $fileMock->method('getMimeType')->willReturn('text/csv');
        $fileMock->method('getContent')->willReturn("item,price,category\nDoor A,notanumber,doors");

        $this->expectException(\Exception::class);
        $this->service->importPricingFromUpload($fileMock);
    }

    public function testImportPricingFromUploadHandlesEmptyFile()
    {
        $fileMock = $this->createMock(\stdClass::class);
        $fileMock->method('getMimeType')->willReturn('text/csv');
        $fileMock->method('getContent')->willReturn("");

        $this->expectException(\Exception::class);
        $this->service->importPricingFromUpload($fileMock);
    }

    public function testImportPricingFromUploadHandlesRepositoryException()
    {
        $fileMock = $this->createMock(\stdClass::class);
        $fileMock->method('getMimeType')->willReturn('text/csv');
        $fileMock->method('getContent')->willReturn("item,price,category\nDoor A,100,doors");

        $this->repository->method('insertOrUpdatePricingItem')
            ->will($this->throwException(new \Exception('DB error')));
        $this->expectException(\Exception::class);
        $this->service->importPricingFromUpload($fileMock);
    }

    public function testIsPricingDataPresentReturnsTrueIfDataExists()
    {
        $qb = $this->createMock(\stdClass::class);
        $resultMock = $this->createMock(\stdClass::class);

        $this->db->method('getQueryBuilder')->willReturn($qb);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('setMaxResults')->willReturnSelf();
        $qb->method('execute')->willReturn($resultMock);
        $resultMock->method('fetch')->willReturn(['id' => 1]);

        $this->assertTrue($this->service->isPricingDataPresent());
    }

    public function testIsPricingDataPresentReturnsFalseIfNoData()
    {
        $qb = $this->createMock(\stdClass::class);
        $resultMock = $this->createMock(\stdClass::class);

        $this->db->method('getQueryBuilder')->willReturn($qb);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('setMaxResults')->willReturnSelf();
        $qb->method('execute')->willReturn($resultMock);
        $resultMock->method('fetch')->willReturn(false);

        $this->assertFalse($this->service->isPricingDataPresent());
    }

    public function testIsPricingDataPresentHandlesDbException()
    {
        $qb = $this->createMock(\stdClass::class);

        $this->db->method('getQueryBuilder')->willReturn($qb);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('setMaxResults')->willReturnSelf();
        $qb->method('execute')->will($this->throwException(new \Exception('DB error')));

        $this->expectException(\Exception::class);
        $this->service->isPricingDataPresent();
    }
}