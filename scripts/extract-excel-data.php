<?php
/**
 * Excel Data Extraction Script for Door Estimator
 * Extracts data from all 12 sheets in Estimator 050825.xlsx
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

class ExcelDataExtractor {
    private $excelFile;
    private $extractedData = [];
    
    public function __construct($excelFile) {
        $this->excelFile = $excelFile;
    }
    
    public function extractAllData() {
        $spreadsheet = IOFactory::load($this->excelFile);
        
        // Extract data from each sheet
        $this->extractSimplePricingSheet($spreadsheet, 'Doors', 'doors');
        $this->extractSimplePricingSheet($spreadsheet, 'Inserts', 'inserts');
        $this->extractFrameSheet($spreadsheet);
        $this->extractSimplePricingSheet($spreadsheet, 'Hinges', 'hinges');
        $this->extractSimplePricingSheet($spreadsheet, 'WSTRP', 'weatherstrip');
        $this->extractSimplePricingSheet($spreadsheet, 'Locksets', 'locksets');
        $this->extractSimplePricingSheet($spreadsheet, 'Exit Devices', 'exitDevices');
        $this->extractSimplePricingSheet($spreadsheet, 'Closers', 'closers');
        $this->extractSimplePricingSheet($spreadsheet, 'Hardware', 'hardware');
        $this->extractWoodDoorSheet($spreadsheet, 'SCwood', 'scwood');
        $this->extractWoodDoorSheet($spreadsheet, 'SCfire', 'scfire');
        
        return $this->extractedData;
    }
    
    private function extractSimplePricingSheet($spreadsheet, $sheetName, $category) {
        $worksheet = $spreadsheet->getSheetByName($sheetName);
        if (!$worksheet) {
            echo "Warning: Sheet '$sheetName' not found\n";
            return;
        }
        
        $data = [];
        $highestRow = $worksheet->getHighestRow();
        
        // Start from row 2 to skip headers, adjust based on sheet structure
        $startRow = ($sheetName === 'Doors') ? 5 : 2;
        
        for ($row = $startRow; $row <= $highestRow; $row++) {
            $itemName = $worksheet->getCell('A' . $row)->getCalculatedValue();
            $price = $worksheet->getCell('B' . $row)->getCalculatedValue();
            
            // Skip empty rows
            if (empty($itemName) || empty($price) || !is_numeric($price)) {
                continue;
            }
            
            // Check for stock status in column C or other indicators
            $stockStatus = $worksheet->getCell('C' . $row)->getCalculatedValue();
            if ($stockStatus === 'Stock' || $stockStatus === 'stock') {
                $stockStatus = 'stock';
            } else {
                $stockStatus = 'special_order';
            }
            
            $data[] = [
                'category' => $category,
                'subcategory' => null,
                'item_name' => trim($itemName),
                'price' => floatval($price),
                'stock_status' => $stockStatus,
                'description' => ''
            ];
        }
        
        $this->extractedData[$category] = $data;
        echo "Extracted " . count($data) . " items from $sheetName sheet\n";
    }
    
    private function extractFrameSheet($spreadsheet) {
        $worksheet = $spreadsheet->getSheetByName('Frames');
        if (!$worksheet) {
            echo "Warning: Frames sheet not found\n";
            return;
        }
        
        $data = [];
        $highestRow = $worksheet->getHighestRow();
        
        // Frames sheet has different subcategories
        for ($row = 2; $row <= $highestRow; $row++) {
            $itemName = $worksheet->getCell('A' . $row)->getCalculatedValue();
            $price = $worksheet->getCell('B' . $row)->getCalculatedValue();
            
            if (empty($itemName) || empty($price) || !is_numeric($price)) {
                continue;
            }
            
            // Determine subcategory based on item name
            $subcategory = 'HM Drywall'; // Default
            if (strpos($itemName, 'EWA') !== false) {
                $subcategory = 'HM EWA';
            } elseif (strpos($itemName, 'USA') !== false) {
                $subcategory = 'HM USA';
            }
            
            $data[] = [
                'category' => 'frames',
                'subcategory' => $subcategory,
                'item_name' => trim($itemName),
                'price' => floatval($price),
                'stock_status' => 'stock',
                'description' => ''
            ];
        }
        
        $this->extractedData['frames'] = $data;
        echo "Extracted " . count($data) . " items from Frames sheet\n";
    }
    
    private function extractWoodDoorSheet($spreadsheet, $sheetName, $category) {
        $worksheet = $spreadsheet->getSheetByName($sheetName);
        if (!$worksheet) {
            echo "Warning: Sheet '$sheetName' not found\n";
            return;
        }
        
        $data = [];
        $highestRow = $worksheet->getHighestRow();
        
        // Wood door sheets have complex pricing matrix
        // Species columns: Lauan, Birch, Oak, Raw HB, Legacy
        $speciesColumns = ['J' => 'Lauan', 'K' => 'Birch', 'L' => 'Oak', 'M' => 'Raw HB', 'N' => 'Legacy'];
        
        for ($row = 7; $row <= $highestRow; $row++) {
            $doorSize = $worksheet->getCell('I' . $row)->getCalculatedValue();
            
            if (empty($doorSize)) {
                continue;
            }
            
            foreach ($speciesColumns as $col => $species) {
                $price = $worksheet->getCell($col . $row)->getCalculatedValue();
                
                if (!empty($price) && is_numeric($price)) {
                    $itemName = $doorSize . ' Solid Core Wood Door - ' . $species;
                    if ($category === 'scfire') {
                        $itemName .= ' Fire Rated';
                    }
                    
                    $data[] = [
                        'category' => $category,
                        'subcategory' => $species,
                        'item_name' => trim($itemName),
                        'price' => floatval($price),
                        'stock_status' => 'special_order',
                        'description' => ''
                    ];
                }
            }
        }
        
        $this->extractedData[$category] = $data;
        echo "Extracted " . count($data) . " items from $sheetName sheet\n";
    }
    
    public function saveToJson($outputFile) {
        file_put_contents($outputFile, json_encode($this->extractedData, JSON_PRETTY_PRINT));
        echo "Data saved to $outputFile\n";
    }
    
    public function saveToSQL($outputFile) {
        $sql = "-- Door Estimator Pricing Data Import\n";
        $sql .= "-- Generated on " . date('Y-m-d H:i:s') . "\n\n";
        
        foreach ($this->extractedData as $category => $items) {
            $sql .= "-- $category items\n";
            foreach ($items as $item) {
                $itemName = addslashes($item['item_name']);
                $subcategory = $item['subcategory'] ? "'" . addslashes($item['subcategory']) . "'" : 'NULL';
                $stockStatus = $item['stock_status'] ? "'" . $item['stock_status'] . "'" : 'NULL';
                $description = $item['description'] ? "'" . addslashes($item['description']) . "'" : 'NULL';
                
                $sql .= "INSERT INTO door_estimator_pricing (category, subcategory, item_name, price, stock_status, description, created_at, updated_at) VALUES (";
                $sql .= "'{$item['category']}', $subcategory, '$itemName', {$item['price']}, $stockStatus, $description, NOW(), NOW());\n";
            }
            $sql .= "\n";
        }
        
        file_put_contents($outputFile, $sql);
        echo "SQL script saved to $outputFile\n";
    }
}

// Usage
if (php_sapi_name() === 'cli') {
    $excelFile = __DIR__ . '/../Estimator 050825.xlsx';
    
    if (!file_exists($excelFile)) {
        echo "Error: Excel file not found at $excelFile\n";
        exit(1);
    }
    
    echo "Extracting data from $excelFile...\n";
    
    $extractor = new ExcelDataExtractor($excelFile);
    $data = $extractor->extractAllData();
    
    // Save as JSON for inspection
    $extractor->saveToJson(__DIR__ . '/extracted_pricing_data.json');
    
    // Save as SQL for import
    $extractor->saveToSQL(__DIR__ . '/pricing_data_import.sql');
    
    // Print summary
    echo "\nExtraction Summary:\n";
    $total = 0;
    foreach ($data as $category => $items) {
        $count = count($items);
        echo "  $category: $count items\n";
        $total += $count;
    }
    echo "  Total: $total items\n";
}
?>