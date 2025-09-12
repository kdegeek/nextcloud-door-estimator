<?php

declare(strict_types=1);

namespace OCA\DoorEstimator\Tests\Migration;

use PHPUnit\Framework\TestCase;

/**
 * Test class for the initial database migration
 * 
 * Tests migration class structure and validation logic
 */
class Version001000Date20250124000000Test extends TestCase {
    
    /**
     * Test that migration file exists and has correct structure
     */
    public function testMigrationFileStructure(): void {
        $migrationFile = 'lib/Migration/Version001000Date20250124000000.php';
        $this->assertFileExists($migrationFile, 'Migration file should exist');
        
        $content = file_get_contents($migrationFile);
        
        // Verify it extends SimpleMigrationStep
        $this->assertStringContainsString(
            'extends SimpleMigrationStep',
            $content,
            'Migration should extend SimpleMigrationStep'
        );
        
        // Verify it has changeSchema method
        $this->assertStringContainsString(
            'public function changeSchema',
            $content,
            'Migration should have changeSchema method'
        );
        
        // Verify it has postSchemaChange method
        $this->assertStringContainsString(
            'public function postSchemaChange',
            $content,
            'Migration should have postSchemaChange method'
        );
    }
    
    /**
     * Test that migration file has correct naming convention
     */
    public function testMigrationNamingConvention(): void {
        $migrationFile = 'lib/Migration/Version001000Date20250124000000.php';
        $this->assertFileExists($migrationFile, 'Migration file should exist');
        
        // Verify the file contains the correct class name
        $content = file_get_contents($migrationFile);
        $this->assertStringContainsString(
            'class Version001000Date20250124000000',
            $content,
            'Migration class should have correct name'
        );
    }
    
    /**
     * Test that database.xml contains required table definitions
     */
    public function testDatabaseXmlStructure(): void {
        $databaseXml = 'appinfo/database.xml';
        $this->assertFileExists($databaseXml, 'Database XML file should exist');
        
        $content = file_get_contents($databaseXml);
        
        // Verify pricing table definition
        $this->assertStringContainsString(
            'door_estimator_pricing',
            $content,
            'Database XML should contain pricing table definition'
        );
        
        // Verify quotes table definition
        $this->assertStringContainsString(
            'door_estimator_quotes',
            $content,
            'Database XML should contain quotes table definition'
        );
        
        // Verify required indexes
        $this->assertStringContainsString(
            'idx_door_estimator_pricing_category',
            $content,
            'Database XML should contain category index'
        );
        
        $this->assertStringContainsString(
            'idx_door_estimator_quotes_user_id',
            $content,
            'Database XML should contain user_id index'
        );
    }
    
    /**
     * Test that migration contains proper table structure definitions
     */
    public function testMigrationTableStructure(): void {
        $migrationFile = 'lib/Migration/Version001000Date20250124000000.php';
        $content = file_get_contents($migrationFile);
        
        // Verify pricing table creation
        $this->assertStringContainsString(
            'door_estimator_pricing',
            $content,
            'Migration should create pricing table'
        );
        
        // Verify quotes table creation
        $this->assertStringContainsString(
            'door_estimator_quotes',
            $content,
            'Migration should create quotes table'
        );
        
        // Verify required columns for pricing table
        $pricingColumns = ['category', 'subcategory', 'item_name', 'price', 'stock_status'];
        foreach ($pricingColumns as $column) {
            $this->assertStringContainsString(
                $column,
                $content,
                "Migration should include $column column"
            );
        }
        
        // Verify required columns for quotes table
        $quotesColumns = ['user_id', 'quote_name', 'quote_data', 'markups', 'total_amount'];
        foreach ($quotesColumns as $column) {
            $this->assertStringContainsString(
                $column,
                $content,
                "Migration should include $column column"
            );
        }
    }
    
    /**
     * Test that migration includes proper indexes for performance
     */
    public function testMigrationIndexes(): void {
        $migrationFile = 'lib/Migration/Version001000Date20250124000000.php';
        $content = file_get_contents($migrationFile);
        
        // Verify pricing table indexes
        $this->assertStringContainsString(
            'idx_de_pricing_category',
            $content,
            'Migration should create category index'
        );
        
        $this->assertStringContainsString(
            'idx_de_pricing_item_name',
            $content,
            'Migration should create item_name index'
        );
        
        $this->assertStringContainsString(
            'idx_de_pricing_cat_subcat',
            $content,
            'Migration should create category+subcategory index'
        );
        
        // Verify quotes table indexes
        $this->assertStringContainsString(
            'idx_de_quotes_user_id',
            $content,
            'Migration should create user_id index'
        );
        
        $this->assertStringContainsString(
            'idx_de_quotes_updated_at',
            $content,
            'Migration should create updated_at index'
        );
        
        // Verify unique constraint
        $this->assertStringContainsString(
            'uniq_de_pricing_item',
            $content,
            'Migration should create unique constraint for pricing items'
        );
    }
    
    /**
     * Test that migration includes proper data types and constraints
     */
    public function testMigrationDataTypes(): void {
        $migrationFile = 'lib/Migration/Version001000Date20250124000000.php';
        $content = file_get_contents($migrationFile);
        
        // Verify decimal precision for price fields
        $this->assertStringContainsString(
            "'precision' => 10",
            $content,
            'Migration should set proper decimal precision'
        );
        
        $this->assertStringContainsString(
            "'scale' => 2",
            $content,
            'Migration should set proper decimal scale'
        );
        
        // Verify string length constraints
        $this->assertStringContainsString(
            "'length' => 50",
            $content,
            'Migration should set category length constraint'
        );
        
        $this->assertStringContainsString(
            "'length' => 255",
            $content,
            'Migration should set item_name length constraint'
        );
        
        // Verify NOT NULL constraints
        $this->assertStringContainsString(
            "'notnull' => true",
            $content,
            'Migration should set NOT NULL constraints'
        );
    }
    
    /**
     * Test that migration includes proper comments for documentation
     */
    public function testMigrationComments(): void {
        $migrationFile = 'lib/Migration/Version001000Date20250124000000.php';
        $content = file_get_contents($migrationFile);
        
        // Verify table comments exist
        $this->assertStringContainsString(
            'Product category',
            $content,
            'Migration should include descriptive comments'
        );
        
        $this->assertStringContainsString(
            'JSON data containing',
            $content,
            'Migration should document JSON columns'
        );
        
        $this->assertStringContainsString(
            'Nextcloud user ID',
            $content,
            'Migration should document user association'
        );
        
        // Verify class documentation
        $this->assertStringContainsString(
            'Migration to create door estimator database tables',
            $content,
            'Migration should have class documentation'
        );
    }
}