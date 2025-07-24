<?php

namespace OCA\DoorEstimator\Command;

use OCP\IDBConnection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ImportPricingData extends Command {
    
    private $db;
    
    public function __construct(IDBConnection $db) {
        parent::__construct();
        $this->db = $db;
    }
    
    protected function configure() {
        $this->setName('door-estimator:import-pricing')
             ->setDescription('Import pricing data from extracted Excel data')
             ->addOption('json-file', 'j', InputOption::VALUE_OPTIONAL, 
                        'Path to JSON file with pricing data', 
                        'scripts/extracted_pricing_data.json');
    }
    
    protected function execute(InputInterface $input, OutputInterface $output) {
        $jsonFile = $input->getOption('json-file');
        
        // Try absolute path first, then relative to app root
        if (!file_exists($jsonFile)) {
            $jsonFile = __DIR__ . '/../../' . $jsonFile;
        }
        
        if (!file_exists($jsonFile)) {
            $output->writeln("<error>Error: JSON file not found at $jsonFile</error>");
            return 1;
        }
        
        $output->writeln("Importing pricing data from $jsonFile...");
        
        $jsonData = json_decode(file_get_contents($jsonFile), true);
        if (!$jsonData) {
            $output->writeln("<error>Error: Invalid JSON data</error>");
            return 1;
        }
        
        // Clear existing data
        $output->writeln("Clearing existing pricing data...");
        $this->db->getQueryBuilder()
                 ->delete('door_estimator_pricing')
                 ->execute();
        
        $qb = $this->db->getQueryBuilder();
        $totalImported = 0;
        
        foreach ($jsonData as $category => $items) {
            $output->writeln("Importing $category items...");
            $categoryCount = 0;
            
            foreach ($items as $item) {
                try {
                    $qb->insert('door_estimator_pricing')
                        ->values([
                            'category' => $qb->createNamedParameter($item['category']),
                            'subcategory' => $qb->createNamedParameter($item['subcategory']),
                            'item_name' => $qb->createNamedParameter($item['item_name']),
                            'price' => $qb->createNamedParameter($item['price']),
                            'stock_status' => $qb->createNamedParameter($item['stock_status']),
                            'description' => $qb->createNamedParameter($item['description'] ?? ''),
                            'created_at' => $qb->createNamedParameter(date('Y-m-d H:i:s')),
                            'updated_at' => $qb->createNamedParameter(date('Y-m-d H:i:s'))
                        ]);
                    $qb->execute();
                    $categoryCount++;
                    
                } catch (\Exception $e) {
                    $output->writeln("<error>Error importing item: " . $item['item_name'] . " - " . $e->getMessage() . "</error>");
                }
            }
            
            $output->writeln("  Imported $categoryCount items for $category");
            $totalImported += $categoryCount;
        }
        
        $output->writeln("<info>Successfully imported $totalImported pricing items!</info>");
        
        // Generate sample data for door options, frame options, etc.
        $this->importAdditionalData($output);
        
        return 0;
    }
    
    private function importAdditionalData(OutputInterface $output) {
        $output->writeln("Adding additional pricing data...");
        
        $additionalData = [
            // Door options
            ['category' => 'doorOptions', 'subcategory' => null, 'item_name' => 'Deadbolt Bore', 'price' => 52.00, 'stock_status' => 'stock'],
            ['category' => 'doorOptions', 'subcategory' => null, 'item_name' => 'Z-Ast w/ASA strike prep attached', 'price' => 103.00, 'stock_status' => 'stock'],
            ['category' => 'doorOptions', 'subcategory' => null, 'item_name' => 'Z-Ast w/flush bolt prep attached', 'price' => 173.00, 'stock_status' => 'stock'],
            ['category' => 'doorOptions', 'subcategory' => null, 'item_name' => 'Louver (specify size)', 'price' => 85.00, 'stock_status' => 'special_order'],
            
            // Frame options
            ['category' => 'frameOptions', 'subcategory' => null, 'item_name' => 'Face Weld & Finish', 'price' => 32.00, 'stock_status' => 'stock'],
            ['category' => 'frameOptions', 'subcategory' => null, 'item_name' => 'Deadbolt Strike Prep', 'price' => 35.00, 'stock_status' => 'stock'],
            ['category' => 'frameOptions', 'subcategory' => null, 'item_name' => 'Jamb reinf. for Rim exit device', 'price' => 12.00, 'stock_status' => 'stock'],
            ['category' => 'frameOptions', 'subcategory' => null, 'item_name' => 'Mullion (specify height)', 'price' => 65.00, 'stock_status' => 'special_order'],
        ];
        
        $qb = $this->db->getQueryBuilder();
        
        foreach ($additionalData as $item) {
            try {
                $qb->insert('door_estimator_pricing')
                    ->values([
                        'category' => $qb->createNamedParameter($item['category']),
                        'subcategory' => $qb->createNamedParameter($item['subcategory']),
                        'item_name' => $qb->createNamedParameter($item['item_name']),
                        'price' => $qb->createNamedParameter($item['price']),
                        'stock_status' => $qb->createNamedParameter($item['stock_status']),
                        'description' => $qb->createNamedParameter(''),
                        'created_at' => $qb->createNamedParameter(date('Y-m-d H:i:s')),
                        'updated_at' => $qb->createNamedParameter(date('Y-m-d H:i:s'))
                    ]);
                $qb->execute();
                
            } catch (\Exception $e) {
                $output->writeln("<error>Error importing additional item: " . $item['item_name'] . " - " . $e->getMessage() . "</error>");
            }
        }
        
        $output->writeln("  Added " . count($additionalData) . " additional items");
    }
}