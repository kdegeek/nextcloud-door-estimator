<?php

declare(strict_types=1);

namespace OCA\DoorEstimator\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version001000Date20250124000000 extends SimpleMigrationStep {
    
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();
        
        // Create pricing table
        if (!$schema->hasTable('door_estimator_pricing')) {
            $table = $schema->createTable('door_estimator_pricing');
            $table->addColumn('id', 'integer', [
                'autoincrement' => true,
                'notnull' => true,
            ]);
            $table->addColumn('category', 'string', [
                'notnull' => true,
                'length' => 50,
            ]);
            $table->addColumn('subcategory', 'string', [
                'notnull' => false,
                'length' => 100,
            ]);
            $table->addColumn('item_name', 'text', [
                'notnull' => true,
            ]);
            $table->addColumn('price', 'decimal', [
                'notnull' => true,
                'precision' => 10,
                'scale' => 2,
            ]);
            $table->addColumn('stock_status', 'string', [
                'notnull' => false,
                'length' => 20,
            ]);
            $table->addColumn('description', 'text', [
                'notnull' => false,
            ]);
            $table->addColumn('created_at', 'datetime', [
                'notnull' => true,
            ]);
            $table->addColumn('updated_at', 'datetime', [
                'notnull' => true,
            ]);
            
            $table->setPrimaryKey(['id']);
            $table->addIndex(['category'], 'de_pricing_cat');
            $table->addIndex(['category', 'subcategory'], 'de_pricing_cat_sub');
            $table->addIndex(['item_name'], 'de_pricing_item');
        }
        
        // Create quotes table
        if (!$schema->hasTable('door_estimator_quotes')) {
            $table = $schema->createTable('door_estimator_quotes');
            $table->addColumn('id', 'integer', [
                'autoincrement' => true,
                'notnull' => true,
            ]);
            $table->addColumn('user_id', 'string', [
                'notnull' => true,
                'length' => 64,
            ]);
            $table->addColumn('quote_name', 'string', [
                'notnull' => false,
                'length' => 255,
            ]);
            $table->addColumn('customer_info', 'text', [
                'notnull' => false,
            ]);
            $table->addColumn('quote_data', 'text', [
                'notnull' => true,
            ]);
            $table->addColumn('markups', 'text', [
                'notnull' => true,
            ]);
            $table->addColumn('total_amount', 'decimal', [
                'notnull' => true,
                'precision' => 10,
                'scale' => 2,
            ]);
            $table->addColumn('created_at', 'datetime', [
                'notnull' => true,
            ]);
            $table->addColumn('updated_at', 'datetime', [
                'notnull' => true,
            ]);
            
            $table->setPrimaryKey(['id']);
            $table->addIndex(['user_id'], 'de_quotes_usr');
            $table->addIndex(['created_at'], 'de_quotes_crt');

            // Attempt to add a foreign key constraint to the users table.
            // Note: Nextcloud user table name may vary by installation (e.g., oc_users, nc_users, etc.).
            // For portability, this is commented out by default. Uncomment and adjust as needed for your deployment.
            // $table->addForeignKeyConstraint('oc_users', ['user_id'], ['uid'], [], 'fk_quotes_user');
        }
        
        return $schema;
    }
}