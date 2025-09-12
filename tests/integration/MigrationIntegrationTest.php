<?php

declare(strict_types=1);

namespace OCA\DoorEstimator\Tests\Integration;

use OCA\DoorEstimator\Migration\Version001000Date20250124000000;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;

/**
 * Integration test for database migration
 * 
 * Tests actual database operations and schema validation
 */
class MigrationIntegrationTest extends TestCase {
    
    /** @var IDBConnection */
    private $connection;
    
    /** @var Version001000Date20250124000000 */
    private $migration;
    
    /** @var IOutput */
    private $output;
    
    protected function setUp(): void {
        parent::setUp();
        
        // Get database connection from Nextcloud
        $this->connection = \OC::$server->getDatabaseConnection();
        $this->migration = new Version001000Date20250124000000();
        $this->output = new class implements IOutput {
            private array $messages = [];
            
            public function info($message): void {
                $this->messages[] = $message;
            }
            
            public function warning($message): void {
                $this->messages[] = "WARNING: $message";
            }
            
            public function startProgress($max = 0): void {}
            public function advance($step = 1, $description = ''): void {}
            public function finishProgress(): void {}
            
            public function getMessages(): array {
                return $this->messages;
            }
        };
    }
    
    protected function tearDown(): void {
        // Clean up test tables
        $this->dropTestTables();
        parent::tearDown();
    }
    
    /**
     * Test complete migration process
     */
    public function testMigrationCreatesTablesCorrectly(): void {
        // Ensure tables don't exist before migration
        $this->dropTestTables();
        
        // Run migration
        $schemaClosure = function() {
            return $this->connection->createSchema();
        };
        
        $schema = $this->migration->changeSchema($this->output, $schemaClosure, []);
        
        $this->assertInstanceOf(ISchemaWrapper::class, $schema);
        
        // Apply the schema changes
        $this->connection->migrateToSchema($schema->getWrappedSchema());
        
        // Verify tables were created
        $this->assertTrue($this->tableExists('door_estimator_pricing'));
        $this->assertTrue($this->tableExists('door_estimator_quotes'));
        
        // Verify table structures
        $this->verifyPricingTableStructure();
        $this->verifyQuotesTableStructure();
    }
    
    /**
     * Test that migration is idempotent (can be run multiple times)
     */
    public function testMigrationIsIdempotent(): void {
        // Run migration first time
        $schemaClosure = function() {
            return $this->connection->createSchema();
        };
        
        $schema1 = $this->migration->changeSchema($this->output, $schemaClosure, []);
        $this->connection->migrateToSchema($schema1->getWrappedSchema());
        
        // Run migration second time
        $schema2 = $this->migration->changeSchema($this->output, $schemaClosure, []);
        
        // Should not throw errors and tables should still exist
        $this->assertTrue($this->tableExists('door_estimator_pricing'));
        $this->assertTrue($this->tableExists('door_estimator_quotes'));
    }
    
    /**
     * Test data preservation during schema changes
     */
    public function testDataPreservationDuringMigration(): void {
        // Create tables first
        $schemaClosure = function() {
            return $this->connection->createSchema();
        };
        
        $schema = $this->migration->changeSchema($this->output, $schemaClosure, []);
        $this->connection->migrateToSchema($schema->getWrappedSchema());
        
        // Insert test data
        $this->insertTestPricingData();
        $this->insertTestQuoteData();
        
        // Verify data exists
        $pricingCount = $this->connection->getQueryBuilder()
            ->select('COUNT(*)')
            ->from('door_estimator_pricing')
            ->executeQuery()
            ->fetchOne();
        
        $quotesCount = $this->connection->getQueryBuilder()
            ->select('COUNT(*)')
            ->from('door_estimator_quotes')
            ->executeQuery()
            ->fetchOne();
        
        $this->assertGreaterThan(0, $pricingCount);
        $this->assertGreaterThan(0, $quotesCount);
        
        // Run migration again (simulating an update)
        $schema2 = $this->migration->changeSchema($this->output, $schemaClosure, []);
        
        // Data should still exist
        $pricingCountAfter = $this->connection->getQueryBuilder()
            ->select('COUNT(*)')
            ->from('door_estimator_pricing')
            ->executeQuery()
            ->fetchOne();
        
        $quotesCountAfter = $this->connection->getQueryBuilder()
            ->select('COUNT(*)')
            ->from('door_estimator_quotes')
            ->executeQuery()
            ->fetchOne();
        
        $this->assertEquals($pricingCount, $pricingCountAfter);
        $this->assertEquals($quotesCount, $quotesCountAfter);
    }
    
    /**
     * Test index performance for common queries
     */
    public function testIndexPerformance(): void {
        // Create tables
        $schemaClosure = function() {
            return $this->connection->createSchema();
        };
        
        $schema = $this->migration->changeSchema($this->output, $schemaClosure, []);
        $this->connection->migrateToSchema($schema->getWrappedSchema());
        
        // Insert test data for performance testing
        $this->insertBulkTestData();
        
        // Test category lookup performance
        $start = microtime(true);
        $result = $this->connection->getQueryBuilder()
            ->select('*')
            ->from('door_estimator_pricing')
            ->where('category = :category')
            ->setParameter('category', 'doors')
            ->executeQuery()
            ->fetchAll();
        $categoryTime = microtime(true) - $start;
        
        // Test user quotes lookup performance
        $start = microtime(true);
        $result = $this->connection->getQueryBuilder()
            ->select('*')
            ->from('door_estimator_quotes')
            ->where('user_id = :user_id')
            ->orderBy('updated_at', 'DESC')
            ->setParameter('user_id', 'test_user')
            ->executeQuery()
            ->fetchAll();
        $userQuotesTime = microtime(true) - $start;
        
        // Verify queries complete within reasonable time (< 100ms for test data)
        $this->assertLessThan(0.1, $categoryTime, 'Category lookup should be fast with proper indexing');
        $this->assertLessThan(0.1, $userQuotesTime, 'User quotes lookup should be fast with proper indexing');
    }
    
    /**
     * Verify pricing table structure and constraints
     */
    private function verifyPricingTableStructure(): void {
        $columns = $this->getTableColumns('door_estimator_pricing');
        
        $expectedColumns = [
            'id', 'category', 'subcategory', 'item_name', 'price',
            'stock_status', 'description', 'created_at', 'updated_at'
        ];
        
        foreach ($expectedColumns as $column) {
            $this->assertArrayHasKey($column, $columns, "Column $column should exist in pricing table");
        }
        
        // Verify indexes exist
        $indexes = $this->getTableIndexes('door_estimator_pricing');
        $this->assertNotEmpty($indexes, 'Pricing table should have indexes');
    }
    
    /**
     * Verify quotes table structure and constraints
     */
    private function verifyQuotesTableStructure(): void {
        $columns = $this->getTableColumns('door_estimator_quotes');
        
        $expectedColumns = [
            'id', 'user_id', 'quote_name', 'customer_info', 'quote_data',
            'markups', 'total_amount', 'created_at', 'updated_at'
        ];
        
        foreach ($expectedColumns as $column) {
            $this->assertArrayHasKey($column, $columns, "Column $column should exist in quotes table");
        }
        
        // Verify indexes exist
        $indexes = $this->getTableIndexes('door_estimator_quotes');
        $this->assertNotEmpty($indexes, 'Quotes table should have indexes');
    }
    
    /**
     * Insert test pricing data
     */
    private function insertTestPricingData(): void {
        $qb = $this->connection->getQueryBuilder();
        $qb->insert('door_estimator_pricing')
            ->values([
                'category' => $qb->createNamedParameter('doors'),
                'subcategory' => $qb->createNamedParameter(null),
                'item_name' => $qb->createNamedParameter('Test Door'),
                'price' => $qb->createNamedParameter(100.00),
                'stock_status' => $qb->createNamedParameter('stock'),
                'created_at' => $qb->createNamedParameter(date('Y-m-d H:i:s')),
                'updated_at' => $qb->createNamedParameter(date('Y-m-d H:i:s'))
            ])
            ->executeStatement();
    }
    
    /**
     * Insert test quote data
     */
    private function insertTestQuoteData(): void {
        $qb = $this->connection->getQueryBuilder();
        $qb->insert('door_estimator_quotes')
            ->values([
                'user_id' => $qb->createNamedParameter('test_user'),
                'quote_name' => $qb->createNamedParameter('Test Quote'),
                'quote_data' => $qb->createNamedParameter('{"doors": []}'),
                'markups' => $qb->createNamedParameter('{"doors": 15}'),
                'total_amount' => $qb->createNamedParameter(100.00),
                'created_at' => $qb->createNamedParameter(date('Y-m-d H:i:s')),
                'updated_at' => $qb->createNamedParameter(date('Y-m-d H:i:s'))
            ])
            ->executeStatement();
    }
    
    /**
     * Insert bulk test data for performance testing
     */
    private function insertBulkTestData(): void {
        $categories = ['doors', 'frames', 'hardware', 'locksets'];
        
        for ($i = 0; $i < 100; $i++) {
            $category = $categories[$i % count($categories)];
            
            $qb = $this->connection->getQueryBuilder();
            $qb->insert('door_estimator_pricing')
                ->values([
                    'category' => $qb->createNamedParameter($category),
                    'item_name' => $qb->createNamedParameter("Test Item $i"),
                    'price' => $qb->createNamedParameter(rand(50, 500)),
                    'stock_status' => $qb->createNamedParameter('stock'),
                    'created_at' => $qb->createNamedParameter(date('Y-m-d H:i:s')),
                    'updated_at' => $qb->createNamedParameter(date('Y-m-d H:i:s'))
                ])
                ->executeStatement();
        }
        
        // Insert test quotes
        for ($i = 0; $i < 20; $i++) {
            $qb = $this->connection->getQueryBuilder();
            $qb->insert('door_estimator_quotes')
                ->values([
                    'user_id' => $qb->createNamedParameter('test_user'),
                    'quote_name' => $qb->createNamedParameter("Test Quote $i"),
                    'quote_data' => $qb->createNamedParameter('{"doors": []}'),
                    'markups' => $qb->createNamedParameter('{"doors": 15}'),
                    'total_amount' => $qb->createNamedParameter(rand(100, 1000)),
                    'created_at' => $qb->createNamedParameter(date('Y-m-d H:i:s')),
                    'updated_at' => $qb->createNamedParameter(date('Y-m-d H:i:s'))
                ])
                ->executeStatement();
        }
    }
    
    /**
     * Check if table exists
     */
    private function tableExists(string $tableName): bool {
        try {
            $this->connection->getQueryBuilder()
                ->select('1')
                ->from($tableName)
                ->setMaxResults(1)
                ->executeQuery();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Get table columns
     */
    private function getTableColumns(string $tableName): array {
        $schemaManager = $this->connection->getSchemaManager();
        $columns = $schemaManager->listTableColumns($tableName);
        
        $result = [];
        foreach ($columns as $column) {
            $result[$column->getName()] = $column;
        }
        
        return $result;
    }
    
    /**
     * Get table indexes
     */
    private function getTableIndexes(string $tableName): array {
        $schemaManager = $this->connection->getSchemaManager();
        return $schemaManager->listTableIndexes($tableName);
    }
    
    /**
     * Drop test tables
     */
    private function dropTestTables(): void {
        try {
            $this->connection->executeStatement('DROP TABLE IF EXISTS door_estimator_quotes');
            $this->connection->executeStatement('DROP TABLE IF EXISTS door_estimator_pricing');
        } catch (\Exception $e) {
            // Ignore errors during cleanup
        }
    }
}