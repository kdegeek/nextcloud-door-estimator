<?php

declare(strict_types=1);

namespace OCA\DoorEstimator\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Performance optimization migration - adds indexes for better query performance
 */
class Version001001Date20250912000000 extends SimpleMigrationStep {

    /**
     * @param IOutput $output
     * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
     * @param array $options
     * @return null|ISchemaWrapper
     */
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        // Add performance indexes to pricing table
        if ($schema->hasTable('door_estimator_pricing')) {
            $table = $schema->getTable('door_estimator_pricing');
            
            // Add search optimization index (item_name + category)
            if (!$table->hasIndex('idx_door_estimator_pricing_search')) {
                $table->addIndex(['item_name', 'category'], 'idx_door_estimator_pricing_search');
                $output->info('Added search optimization index to pricing table');
            }
            
            // Add price sorting index
            if (!$table->hasIndex('idx_door_estimator_pricing_price')) {
                $table->addIndex(['price'], 'idx_door_estimator_pricing_price');
                $output->info('Added price sorting index to pricing table');
            }
            
            // Add updated_at index for sorting
            if (!$table->hasIndex('idx_door_estimator_pricing_updated_at')) {
                $table->addIndex(['updated_at'], 'idx_door_estimator_pricing_updated_at');
                $output->info('Added updated_at index to pricing table');
            }
        }

        // Optimize quotes table indexes
        if ($schema->hasTable('door_estimator_quotes')) {
            $table = $schema->getTable('door_estimator_quotes');
            
            // Add composite index for user quotes with date sorting
            if (!$table->hasIndex('idx_door_estimator_quotes_user_updated')) {
                $table->addIndex(['user_id', 'updated_at'], 'idx_door_estimator_quotes_user_updated');
                $output->info('Added user+date composite index to quotes table');
            }
        }

        return $schema;
    }
}