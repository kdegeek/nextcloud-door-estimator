<?php

declare(strict_types=1);

namespace OCA\DoorEstimator\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Migration to create door estimator database tables
 * 
 * Creates two main tables:
 * - door_estimator_pricing: Stores pricing data for all product categories
 * - door_estimator_quotes: Stores user quotes with JSON data and markups
 */
class Version001000Date20250124000000 extends SimpleMigrationStep {
    
    /**
     * Create the database schema for door estimator tables
     * 
     * @param IOutput $output Migration output for logging
     * @param Closure $schemaClosure Schema closure to get current schema
     * @param array $options Migration options
     * @return ISchemaWrapper|null Modified schema or null if no changes
     */
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();
        
        $output->info('Creating door estimator database tables...');
        
        // Create pricing table with optimized indexes for lookups
        if (!$schema->hasTable('door_estimator_pricing')) {
            $output->info('Creating door_estimator_pricing table...');
            
            $table = $schema->createTable('door_estimator_pricing');
            
            // Primary key
            $table->addColumn('id', 'integer', [
                'autoincrement' => true,
                'notnull' => true,
            ]);
            
            // Core pricing fields
            $table->addColumn('category', 'string', [
                'notnull' => true,
                'length' => 50,
                'comment' => 'Product category (doors, frames, hardware, etc.)',
            ]);
            
            $table->addColumn('subcategory', 'string', [
                'notnull' => false,
                'length' => 100,
                'comment' => 'Subcategory for frame types (HM Drywall, HM EWA, HM USA)',
            ]);
            
            $table->addColumn('item_name', 'string', [
                'notnull' => true,
                'length' => 255,
                'comment' => 'Name/description of the pricing item',
            ]);
            
            $table->addColumn('price', 'decimal', [
                'notnull' => true,
                'precision' => 10,
                'scale' => 2,
                'comment' => 'Base price for the item',
            ]);
            
            // Additional metadata
            $table->addColumn('stock_status', 'string', [
                'notnull' => true,
                'length' => 20,
                'default' => 'stock',
                'comment' => 'Stock availability status',
            ]);
            
            $table->addColumn('description', 'text', [
                'notnull' => false,
                'comment' => 'Optional detailed description',
            ]);
            
            // Timestamps
            $table->addColumn('created_at', 'datetime', [
                'notnull' => true,
                'comment' => 'Record creation timestamp',
            ]);
            
            $table->addColumn('updated_at', 'datetime', [
                'notnull' => true,
                'comment' => 'Record last update timestamp',
            ]);
            
            // Set primary key
            $table->setPrimaryKey(['id']);
            
            // Performance indexes for common query patterns
            $table->addIndex(['category'], 'idx_de_pricing_category');
            $table->addIndex(['item_name'], 'idx_de_pricing_item_name');
            $table->addIndex(['category', 'subcategory'], 'idx_de_pricing_cat_subcat');
            $table->addIndex(['category', 'item_name'], 'idx_de_pricing_cat_item');
            $table->addIndex(['updated_at'], 'idx_de_pricing_updated');
            
            // Unique constraint to prevent duplicate items
            $table->addUniqueIndex(['category', 'subcategory', 'item_name'], 'uniq_de_pricing_item');
        }
        
        // Create quotes table with JSON columns and user isolation
        if (!$schema->hasTable('door_estimator_quotes')) {
            $output->info('Creating door_estimator_quotes table...');
            
            $table = $schema->createTable('door_estimator_quotes');
            
            // Primary key
            $table->addColumn('id', 'integer', [
                'autoincrement' => true,
                'notnull' => true,
            ]);
            
            // User association
            $table->addColumn('user_id', 'string', [
                'notnull' => true,
                'length' => 64,
                'comment' => 'Nextcloud user ID who owns this quote',
            ]);
            
            // Quote metadata
            $table->addColumn('quote_name', 'string', [
                'notnull' => true,
                'length' => 255,
                'comment' => 'User-defined name for the quote',
            ]);
            
            $table->addColumn('customer_info', 'text', [
                'notnull' => false,
                'comment' => 'JSON data containing customer information',
            ]);
            
            // Quote data stored as JSON
            $table->addColumn('quote_data', 'text', [
                'notnull' => true,
                'comment' => 'JSON data containing all quote line items by category',
            ]);
            
            $table->addColumn('markups', 'text', [
                'notnull' => true,
                'comment' => 'JSON data containing markup percentages for each category',
            ]);
            
            // Calculated totals
            $table->addColumn('total_amount', 'decimal', [
                'notnull' => true,
                'precision' => 12,
                'scale' => 2,
                'comment' => 'Final calculated quote total with markups',
            ]);
            
            // Timestamps
            $table->addColumn('created_at', 'datetime', [
                'notnull' => true,
                'comment' => 'Quote creation timestamp',
            ]);
            
            $table->addColumn('updated_at', 'datetime', [
                'notnull' => true,
                'comment' => 'Quote last modification timestamp',
            ]);
            
            // Set primary key
            $table->setPrimaryKey(['id']);
            
            // Performance indexes for user queries and sorting
            $table->addIndex(['user_id'], 'idx_de_quotes_user_id');
            $table->addIndex(['updated_at'], 'idx_de_quotes_updated_at');
            $table->addIndex(['user_id', 'updated_at'], 'idx_de_quotes_user_updated');
            $table->addIndex(['created_at'], 'idx_de_quotes_created_at');
            
            // Index for user-specific quote name searches
            $table->addIndex(['user_id', 'quote_name'], 'idx_de_quotes_user_name');
        }
        
        $output->info('Door estimator database tables created successfully');
        
        return $schema;
    }
    
    /**
     * Post-schema change operations
     * 
     * @param IOutput $output Migration output for logging
     * @param Closure $schemaClosure Schema closure
     * @param array $options Migration options
     */
    public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
        $output->info('Door estimator migration completed successfully');
    }
}