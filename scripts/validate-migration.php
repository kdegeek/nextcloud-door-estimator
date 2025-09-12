<?php

declare(strict_types=1);

/**
 * Migration validation script
 * 
 * Validates that the database schema and migration files are properly structured
 * for the Door Estimator application.
 */

require_once __DIR__ . '/../vendor/autoload.php';

class MigrationValidator {
    
    private array $errors = [];
    private array $warnings = [];
    
    public function validate(): bool {
        echo "Validating Door Estimator database migration...\n\n";
        
        $this->validateDatabaseXml();
        $this->validateMigrationFile();
        $this->validateTableStructure();
        $this->validateIndexes();
        $this->validateConstraints();
        
        $this->printResults();
        
        return empty($this->errors);
    }
    
    private function validateDatabaseXml(): void {
        echo "Checking database.xml structure...\n";
        
        $xmlFile = __DIR__ . '/../appinfo/database.xml';
        if (!file_exists($xmlFile)) {
            $this->errors[] = "Database XML file not found: $xmlFile";
            return;
        }
        
        $content = file_get_contents($xmlFile);
        
        // Check for required tables
        $requiredTables = ['door_estimator_pricing', 'door_estimator_quotes'];
        foreach ($requiredTables as $table) {
            if (strpos($content, $table) === false) {
                $this->errors[] = "Missing table definition: $table";
            } else {
                echo "  ✓ Found table: $table\n";
            }
        }
        
        // Check for required indexes
        $requiredIndexes = [
            'idx_door_estimator_pricing_category',
            'idx_door_estimator_pricing_item_name',
            'idx_door_estimator_quotes_user_id'
        ];
        
        foreach ($requiredIndexes as $index) {
            if (strpos($content, $index) === false) {
                $this->errors[] = "Missing index definition: $index";
            } else {
                echo "  ✓ Found index: $index\n";
            }
        }
    }
    
    private function validateMigrationFile(): void {
        echo "\nChecking migration file structure...\n";
        
        $migrationFile = __DIR__ . '/../lib/Migration/Version001000Date20250124000000.php';
        if (!file_exists($migrationFile)) {
            $this->errors[] = "Migration file not found: $migrationFile";
            return;
        }
        
        $content = file_get_contents($migrationFile);
        
        // Check class structure
        $requiredElements = [
            'class Version001000Date20250124000000' => 'Migration class declaration',
            'extends SimpleMigrationStep' => 'Proper inheritance',
            'public function changeSchema' => 'changeSchema method',
            'public function postSchemaChange' => 'postSchemaChange method',
            'door_estimator_pricing' => 'Pricing table creation',
            'door_estimator_quotes' => 'Quotes table creation'
        ];
        
        foreach ($requiredElements as $element => $description) {
            if (strpos($content, $element) === false) {
                $this->errors[] = "Missing in migration: $description ($element)";
            } else {
                echo "  ✓ Found: $description\n";
            }
        }
    }
    
    private function validateTableStructure(): void {
        echo "\nValidating table structure definitions...\n";
        
        $migrationFile = __DIR__ . '/../lib/Migration/Version001000Date20250124000000.php';
        $content = file_get_contents($migrationFile);
        
        // Pricing table columns
        $pricingColumns = [
            'id' => 'Primary key',
            'category' => 'Product category',
            'subcategory' => 'Product subcategory',
            'item_name' => 'Item name',
            'price' => 'Item price',
            'stock_status' => 'Stock status',
            'description' => 'Item description',
            'created_at' => 'Creation timestamp',
            'updated_at' => 'Update timestamp'
        ];
        
        echo "  Pricing table columns:\n";
        foreach ($pricingColumns as $column => $description) {
            if (strpos($content, "'$column'") === false) {
                $this->errors[] = "Missing pricing table column: $column ($description)";
            } else {
                echo "    ✓ $column - $description\n";
            }
        }
        
        // Quotes table columns
        $quotesColumns = [
            'id' => 'Primary key',
            'user_id' => 'User association',
            'quote_name' => 'Quote name',
            'customer_info' => 'Customer information (JSON)',
            'quote_data' => 'Quote line items (JSON)',
            'markups' => 'Markup percentages (JSON)',
            'total_amount' => 'Final quote total',
            'created_at' => 'Creation timestamp',
            'updated_at' => 'Update timestamp'
        ];
        
        echo "  Quotes table columns:\n";
        foreach ($quotesColumns as $column => $description) {
            if (strpos($content, "'$column'") === false) {
                $this->errors[] = "Missing quotes table column: $column ($description)";
            } else {
                echo "    ✓ $column - $description\n";
            }
        }
    }
    
    private function validateIndexes(): void {
        echo "\nValidating index definitions...\n";
        
        $migrationFile = __DIR__ . '/../lib/Migration/Version001000Date20250124000000.php';
        $content = file_get_contents($migrationFile);
        
        $requiredIndexes = [
            'idx_de_pricing_category' => 'Category lookup index',
            'idx_de_pricing_item_name' => 'Item name search index',
            'idx_de_pricing_cat_subcat' => 'Category+subcategory index',
            'idx_de_quotes_user_id' => 'User quotes lookup index',
            'idx_de_quotes_updated_at' => 'Quote sorting index',
            'uniq_de_pricing_item' => 'Unique pricing constraint'
        ];
        
        foreach ($requiredIndexes as $index => $description) {
            if (strpos($content, $index) === false) {
                $this->errors[] = "Missing index: $index ($description)";
            } else {
                echo "  ✓ $index - $description\n";
            }
        }
    }
    
    private function validateConstraints(): void {
        echo "\nValidating data constraints...\n";
        
        $migrationFile = __DIR__ . '/../lib/Migration/Version001000Date20250124000000.php';
        $content = file_get_contents($migrationFile);
        
        // Check for proper data types and constraints
        $constraints = [
            "'precision' => 10" => 'Decimal precision for prices',
            "'scale' => 2" => 'Decimal scale for currency',
            "'length' => 50" => 'Category length constraint',
            "'length' => 255" => 'Item name length constraint',
            "'notnull' => true" => 'NOT NULL constraints',
            'setPrimaryKey' => 'Primary key definitions'
        ];
        
        foreach ($constraints as $constraint => $description) {
            if (strpos($content, $constraint) === false) {
                $this->warnings[] = "Missing constraint: $description ($constraint)";
            } else {
                echo "  ✓ $description\n";
            }
        }
    }
    
    private function printResults(): void {
        echo "\n" . str_repeat("=", 60) . "\n";
        echo "VALIDATION RESULTS\n";
        echo str_repeat("=", 60) . "\n";
        
        if (empty($this->errors) && empty($this->warnings)) {
            echo "✅ All validations passed! Migration is ready.\n";
        } else {
            if (!empty($this->errors)) {
                echo "❌ ERRORS FOUND:\n";
                foreach ($this->errors as $error) {
                    echo "  • $error\n";
                }
                echo "\n";
            }
            
            if (!empty($this->warnings)) {
                echo "⚠️  WARNINGS:\n";
                foreach ($this->warnings as $warning) {
                    echo "  • $warning\n";
                }
                echo "\n";
            }
        }
        
        echo "Summary:\n";
        echo "  Errors: " . count($this->errors) . "\n";
        echo "  Warnings: " . count($this->warnings) . "\n";
    }
}

// Run validation
$validator = new MigrationValidator();
$success = $validator->validate();

exit($success ? 0 : 1);