<?php

declare(strict_types=1);

require_once __DIR__ . '/../../lib/bootstrap.php';

use PHPUnit\Framework\TestCase;
use OCA\DoorEstimator\Migration\Version001000Date20250124000000;
use OCA\DoorEstimator\Service\EstimatorService;
use OCA\DoorEstimator\Repository\EstimatorRepository;
use OCP\IDBConnection;

final class DoorEstimatorDatabaseIntegrationTest extends TestCase
{
    /** @var IDBConnection */
    private static $db;

    /** @var EstimatorRepository */
    private static $repo;

    /** @var EstimatorService */
    private static $service;

    public static function setUpBeforeClass(): void
    {
        // Use Nextcloud's DI container to get a real test DB connection
        self::$db = \OC::$server->getDatabaseConnection();
        self::$repo = new EstimatorRepository(self::$db);

        // Use real services or DI for dependencies if available
        $userSession = \OC::$server->getUserSession();
        $appData = \OC::$server->getAppData();
        $config = \OC::$server->getConfig();

        self::$service = new EstimatorService(
            self::$repo,
            $userSession,
            $appData,
            $config,
            self::$db
        );

        // Reset DB and apply migration
        self::resetDatabase();
        self::applyMigration();
    }

    public static function tearDownAfterClass(): void
    {
        self::resetDatabase();
    }

    private static function resetDatabase(): void
    {
        $qb = self::$db->getQueryBuilder();
        $qb->delete('door_estimator_pricing')->execute();
        $qb->delete('door_estimator_quotes')->execute();
    }

    private static function applyMigration(): void
    {
        $migration = new Version001000Date20250124000000();
        // Use a real migration output or PHPUnit's output, or a simple echo for test feedback
        $migration->changeSchema(
            new class implements \OCP\Migration\IOutput {
                public function info($msg) {}
                public function warning($msg) {}
                public function error($msg) {}
            },
            function () {
                return self::$db->getSchemaWrapper();
            },
            []
        );
    }

    public function testTableAndIndexCreation(): void
    {
        $schema = self::$db->getSchemaWrapper();
        $this->assertTrue($schema->hasTable('door_estimator_pricing'));
        $this->assertTrue($schema->hasTable('door_estimator_quotes'));

        $pricingTable = $schema->getTable('door_estimator_pricing');
        $quotesTable = $schema->getTable('door_estimator_quotes');

        $this->assertArrayHasKey('de_pricing_cat', $pricingTable->getIndexes());
        $this->assertArrayHasKey('de_pricing_cat_sub', $pricingTable->getIndexes());
        $this->assertArrayHasKey('de_pricing_item', $pricingTable->getIndexes());
        $this->assertArrayHasKey('de_quotes_usr', $quotesTable->getIndexes());
        $this->assertArrayHasKey('de_quotes_crt', $quotesTable->getIndexes());
    }

    public function testPricingCrudOperations(): void
    {
        // Insert
        $data = [
            'item' => 'Test Door',
            'price' => 123.45,
            'category' => 'doors',
            'stock_status' => 'stock',
            'description' => 'Test description'
        ];
        $result = self::$service->updatePricingItem($data);
        $this->assertTrue($result);

        // Select
        $allPricing = self::$service->getAllPricingData();
        $this->assertNotEmpty($allPricing);
        $this->assertArrayHasKey('doors', $allPricing);
        // Strengthen: check that inserted item exists and matches expected values
        $found = false;
        foreach ($allPricing['doors'] as $item) {
            if ($item['item'] === 'Test Door' && $item['price'] == 123.45) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Inserted pricing item not found or incorrect');

        // Update
        $pricingId = $allPricing['doors'][0]['id'];
        $data['id'] = $pricingId;
        $data['price'] = 200.00;
        $result = self::$service->updatePricingItem($data);
        $this->assertTrue($result);
        // Strengthen: check that price was updated
        $updatedPricing = self::$service->getAllPricingData();
        $updatedItem = null;
        foreach ($updatedPricing['doors'] as $item) {
            if ($item['id'] === $pricingId) {
                $updatedItem = $item;
                break;
            }
        }
        $this->assertNotNull($updatedItem, 'Updated pricing item not found');
        $this->assertEquals(200.00, $updatedItem['price'], 'Pricing item price not updated');

        // Delete
        $qb = self::$db->getQueryBuilder();
        $affected = $qb->delete('door_estimator_pricing')
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($pricingId)))
            ->execute();
        $this->assertGreaterThan(0, $affected);
        // Strengthen: check that item is deleted
        $deletedPricing = self::$service->getAllPricingData();
        $deleted = true;
        foreach ($deletedPricing['doors'] as $item) {
            if ($item['id'] === $pricingId) {
                $deleted = false;
                break;
            }
        }
        $this->assertTrue($deleted, 'Pricing item was not deleted');
    }

    public function testQuotesCrudOperations(): void
    {
        $quoteData = [
            'doors' => [
                ['item' => 'Test Door', 'qty' => 2, 'price' => 123.45]
            ]
        ];
        $markups = ['doors' => 10, 'frames' => 5, 'hardware' => 8];
        $quoteName = 'Integration Test Quote';
        $customerInfo = ['name' => 'Test Customer'];

        $quoteId = self::$service->saveQuote($quoteData, $markups, $quoteName, json_encode($customerInfo));
        $this->assertIsInt($quoteId);

        $quote = self::$service->getQuote($quoteId);
        $this->assertNotNull($quote);
        $this->assertEquals($quoteId, $quote['id']);

        $quotes = self::$service->getUserQuotes();
        $this->assertNotEmpty($quotes);

        $deleted = self::$service->deleteQuote($quoteId);
        $this->assertTrue($deleted);
        // Strengthen: check that quote is deleted
        $deletedQuote = self::$service->getQuote($quoteId);
        $this->assertNull($deletedQuote, 'Quote was not deleted');
    }

    public function testDataIntegrityAndForeignKeyConstraints(): void
    {
        // Insert quote with non-existent user_id (simulate FK violation)
        $qb = self::$db->getQueryBuilder();
        $qb->insert('door_estimator_quotes')
            ->values([
                'user_id' => 'nonexistent_user',
                'quote_name' => 'Bad Quote',
                'customer_info' => null,
                'quote_data' => json_encode([]),
                'markups' => json_encode([]),
                'total_amount' => 0.0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ])
            ->execute();

        // If FK constraint is enforced, this should fail; otherwise, check for orphaned data
        $qb = self::$db->getQueryBuilder();
        $result = $qb->select('*')
            ->from('door_estimator_quotes')
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter('nonexistent_user')))
            ->execute();
        $row = $result->fetch();
        $this->assertNotNull($row);
        // Strengthen: check that only one orphaned row exists
        $qb = self::$db->getQueryBuilder();
        $countResult = $qb->select($qb->createFunction('COUNT(*)', 'count'))
            ->from('door_estimator_quotes')
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter('nonexistent_user')))
            ->execute();
        $countRow = $countResult->fetch();
        $this->assertEquals(1, $countRow['count'], 'Unexpected number of orphaned quotes for nonexistent user');
    }

    public function testPerformanceWithLargeDatasets(): void
    {
        $start = microtime(true);
        $bulk = [];
        for ($i = 0; $i < 1000; $i++) {
            $bulk[] = [
                'item' => "Bulk Door $i",
                'price' => 100 + $i,
                'category' => 'doors',
                'stock_status' => 'stock',
                'description' => "Bulk insert $i"
            ];
        }
        foreach ($bulk as $row) {
            self::$service->updatePricingItem($row);
        }
        $duration = microtime(true) - $start;
        $this->assertLessThan(10, $duration, "Bulk insert should complete in <10s");

        $allPricing = self::$service->getAllPricingData();
        $this->assertGreaterThanOrEqual(1000, count($allPricing['doors']));
        // Strengthen: check that a sample of inserted data is correct
        $sample = $allPricing['doors'][500];
        $this->assertEquals("Bulk Door 500", $sample['item']);
        $this->assertEquals(600, $sample['price']);
        $this->assertEquals('doors', $sample['category']);
    }

    public function testPricingDataImportFunctionality(): void
    {
        // Simulate JSON import
        $file = [
            'type' => 'application/json',
            'size' => 100,
            'tmp_name' => __DIR__ . '/test_pricing.json'
        ];
        file_put_contents($file['tmp_name'], json_encode([
            'pricingData' => [
                ['item' => 'Import Door', 'price' => 99.99, 'category' => 'doors']
            ],
            'markups' => [
                ['type' => 'doors', 'value' => 10]
            ]
        ]));

        $result = self::$service->importPricingFromUpload($file);
        $this->assertEquals(1, $result['imported']);
        $this->assertEmpty($result['errors']);
        // Strengthen: check that imported item exists in DB
        $allPricing = self::$service->getAllPricingData();
        $found = false;
        foreach ($allPricing['doors'] as $item) {
            if ($item['item'] === 'Import Door' && $item['price'] == 99.99) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Imported pricing item not found in DB');
        unlink($file['tmp_name']);
    }
}

/* All stub classes removed: TestDbFactory, TestDbConnection, TestMigrationOutput, TestUserSession, TestAppData, TestConfig.
   The test now uses Nextcloud's real test infrastructure and DI container for all dependencies. */