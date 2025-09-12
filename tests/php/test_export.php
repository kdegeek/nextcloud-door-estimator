<?php
/**
 * Simple test script to verify export functionality
 */

require_once 'vendor/autoload.php';

// Mock the required dependencies for testing
class MockUserSession {
    public function getUser() {
        return new class {
            public function getUID() { return 'test_user'; }
        };
    }
}

class MockAppData {
    public function getFolder($name) { return $this; }
    public function newFolder($name) { return $this; }
    public function newFile($name) { return $this; }
    public function putContent($content) { return true; }
}

class MockConfig {
    private $values = [
        'markup_doors' => '15',
        'markup_frames' => '12', 
        'markup_hardware' => '18'
    ];
    
    public function getAppValue($app, $key, $default) {
        return $this->values[$key] ?? $default;
    }
}

class MockDBConnection {
    public function getQueryBuilder() {
        return new class {
            private $table = '';
            private $columns = [];
            
            public function select($columns) {
                $this->columns = is_array($columns) ? $columns : [$columns];
                return $this;
            }
            
            public function from($table) {
                $this->table = $table;
                return $this;
            }
            
            public function executeQuery() {
                // Mock some sample data
                return new class {
                    private $data = [
                        [
                            'id' => 1,
                            'category' => 'doors',
                            'subcategory' => null,
                            'item' => 'Standard Door',
                            'price' => 150.00,
                            'stock_status' => 'stock',
                            'description' => 'Standard interior door'
                        ],
                        [
                            'id' => 2,
                            'category' => 'frames',
                            'subcategory' => 'HM Drywall',
                            'item' => 'Frame Type A',
                            'price' => 75.00,
                            'stock_status' => 'stock',
                            'description' => 'Metal frame for drywall'
                        ]
                    ];
                    private $index = 0;
                    
                    public function fetch() {
                        if ($this->index < count($this->data)) {
                            return $this->data[$this->index++];
                        }
                        return false;
                    }
                };
            }
        };
    }
}

class MockLogger {
    public function info($message, $context = []) {
        echo "INFO: $message\n";
    }
    
    public function error($message, $context = []) {
        echo "ERROR: $message\n";
    }
}

class MockRepository {
    public function getAllPricingData() {
        return [
            [
                'id' => 1,
                'category' => 'doors',
                'subcategory' => null,
                'item' => 'Standard Door',
                'price' => 150.00,
                'stock_status' => 'stock',
                'description' => 'Standard interior door'
            ],
            [
                'id' => 2,
                'category' => 'frames',
                'subcategory' => 'HM Drywall',
                'item' => 'Frame Type A',
                'price' => 75.00,
                'stock_status' => 'stock',
                'description' => 'Metal frame for drywall'
            ]
        ];
    }
}

// Test the export functionality
try {
    $repository = new MockRepository();
    $userSession = new MockUserSession();
    $appData = new MockAppData();
    $config = new MockConfig();
    $db = new MockDBConnection();
    $logger = new MockLogger();
    
    $service = new \OCA\DoorEstimator\Service\EstimatorService(
        $repository,
        $userSession,
        $appData,
        $config,
        $db,
        $logger
    );
    
    echo "Testing export functionality...\n";
    $exportData = $service->exportPricingData();
    
    echo "Export successful!\n";
    echo "Items exported: " . count($exportData['pricingData']) . "\n";
    echo "Markups: " . json_encode($exportData['markups']) . "\n";
    echo "Export structure: " . json_encode(array_keys($exportData)) . "\n";
    
    // Verify the structure
    $requiredKeys = ['pricingData', 'markups', 'exportedAt', 'version'];
    foreach ($requiredKeys as $key) {
        if (!isset($exportData[$key])) {
            throw new Exception("Missing required key: $key");
        }
    }
    
    echo "✅ Export functionality test passed!\n";
    
} catch (Exception $e) {
    echo "❌ Export test failed: " . $e->getMessage() . "\n";
    exit(1);
}