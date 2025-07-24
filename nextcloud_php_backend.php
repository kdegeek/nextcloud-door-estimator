<?php
// lib/Controller/EstimatorController.php
namespace OCA\DoorEstimator\Controller;

use OCP\IRequest;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCA\DoorEstimator\Service\EstimatorService;

class EstimatorController extends Controller {
    
    private $estimatorService;
    
    public function __construct($AppName, IRequest $request, EstimatorService $estimatorService) {
        parent::__construct($AppName, $request);
        $this->estimatorService = $estimatorService;
    }
    
    /**
     * @NoAdminRequired
     */
    public function getAllPricingData() {
        return new JSONResponse($this->estimatorService->getAllPricingData());
    }
    
    /**
     * @NoAdminRequired
     */
    public function getPricingByCategory($category) {
        return new JSONResponse($this->estimatorService->getPricingByCategory($category));
    }
    
    /**
     * @NoAdminRequired
     */
    public function updatePricingItem() {
        $data = $this->request->getParam('data');
        $result = $this->estimatorService->updatePricingItem($data);
        return new JSONResponse(['success' => $result]);
    }
    
    /**
     * @NoAdminRequired
     */
    public function saveQuote() {
        $quoteData = $this->request->getParam('quoteData');
        $quoteId = $this->estimatorService->saveQuote($quoteData);
        return new JSONResponse(['quoteId' => $quoteId]);
    }
    
    /**
     * @NoAdminRequired
     */
    public function getQuote($quoteId) {
        $quote = $this->estimatorService->getQuote($quoteId);
        return new JSONResponse($quote);
    }
    
    /**
     * @NoAdminRequired
     */
    public function getUserQuotes() {
        $quotes = $this->estimatorService->getUserQuotes();
        return new JSONResponse($quotes);
    }
    
    /**
     * @NoAdminRequired
     */
    public function generateQuotePDF($quoteId) {
        $pdfPath = $this->estimatorService->generateQuotePDF($quoteId);
        return new JSONResponse(['pdfPath' => $pdfPath]);
    }
    
    /**
     * @NoAdminRequired  
     */
    public function lookupPrice() {
        $category = $this->request->getParam('category');
        $item = $this->request->getParam('item');
        $frameType = $this->request->getParam('frameType');
        
        $price = $this->estimatorService->lookupPrice($category, $item, $frameType);
        return new JSONResponse(['price' => $price]);
    }
}

// lib/Service/EstimatorService.php
namespace OCA\DoorEstimator\Service;

use OCP\IDBConnection;
use OCP\IUserSession;
use OCP\Files\IAppData;

class EstimatorService {
    
    private $db;
    private $userSession;
    private $appData;
    
    public function __construct(IDBConnection $db, IUserSession $userSession, IAppData $appData) {
        $this->db = $db;
        $this->userSession = $userSession;
        $this->appData = $appData;
    }
    
    public function getAllPricingData() {
        $qb = $this->db->getQueryBuilder();
        
        $result = $qb->select('*')
            ->from('door_estimator_pricing')
            ->execute();
            
        $data = [];
        while ($row = $result->fetch()) {
            if (!isset($data[$row['category']])) {
                $data[$row['category']] = [];
            }
            
            if ($row['subcategory']) {
                if (!isset($data[$row['category']][$row['subcategory']])) {
                    $data[$row['category']][$row['subcategory']] = [];
                }
                $data[$row['category']][$row['subcategory']][] = [
                    'id' => $row['id'],
                    'item' => $row['item_name'],
                    'price' => floatval($row['price'])
                ];
            } else {
                $data[$row['category']][] = [
                    'id' => $row['id'],
                    'item' => $row['item_name'],
                    'price' => floatval($row['price'])
                ];
            }
        }
        
        return $data;
    }
    
    public function getPricingByCategory($category) {
        $qb = $this->db->getQueryBuilder();
        
        $result = $qb->select('*')
            ->from('door_estimator_pricing')
            ->where($qb->expr()->eq('category', $qb->createNamedParameter($category)))
            ->execute();
            
        $items = [];
        while ($row = $result->fetch()) {
            $items[] = [
                'id' => $row['id'],
                'item' => $row['item_name'],
                'price' => floatval($row['price']),
                'subcategory' => $row['subcategory']
            ];
        }
        
        return $items;
    }
    
    public function lookupPrice($category, $item, $frameType = null) {
        $qb = $this->db->getQueryBuilder();
        
        $qb->select('price')
            ->from('door_estimator_pricing')
            ->where($qb->expr()->eq('category', $qb->createNamedParameter($category)))
            ->andWhere($qb->expr()->eq('item_name', $qb->createNamedParameter($item)));
            
        if ($frameType) {
            $qb->andWhere($qb->expr()->eq('subcategory', $qb->createNamedParameter($frameType)));
        }
        
        $result = $qb->execute();
        $row = $result->fetch();
        
        return $row ? floatval($row['price']) : 0;
    }
    
    public function updatePricingItem($data) {
        $qb = $this->db->getQueryBuilder();
        
        if (isset($data['id']) && $data['id']) {
            // Update existing item
            $qb->update('door_estimator_pricing')
                ->set('item_name', $qb->createNamedParameter($data['item']))
                ->set('price', $qb->createNamedParameter($data['price']))
                ->set('updated_at', $qb->createNamedParameter(date('Y-m-d H:i:s')))
                ->where($qb->expr()->eq('id', $qb->createNamedParameter($data['id'])));
        } else {
            // Insert new item
            $qb->insert('door_estimator_pricing')
                ->values([
                    'category' => $qb->createNamedParameter($data['category']),
                    'subcategory' => $qb->createNamedParameter($data['subcategory'] ?? null),
                    'item_name' => $qb->createNamedParameter($data['item']),
                    'price' => $qb->createNamedParameter($data['price']),
                    'created_at' => $qb->createNamedParameter(date('Y-m-d H:i:s')),
                    'updated_at' => $qb->createNamedParameter(date('Y-m-d H:i:s'))
                ]);
        }
        
        return $qb->execute() > 0;
    }
    
    public function saveQuote($quoteData) {
        $userId = $this->userSession->getUser()->getUID();
        $qb = $this->db->getQueryBuilder();
        
        // Save quote header
        $qb->insert('door_estimator_quotes')
            ->values([
                'user_id' => $qb->createNamedParameter($userId),
                'quote_data' => $qb->createNamedParameter(json_encode($quoteData)),
                'total_amount' => $qb->createNamedParameter($this->calculateQuoteTotal($quoteData)),
                'created_at' => $qb->createNamedParameter(date('Y-m-d H:i:s')),
                'updated_at' => $qb->createNamedParameter(date('Y-m-d H:i:s'))
            ]);
            
        $qb->execute();
        return $this->db->lastInsertId();
    }
    
    public function getQuote($quoteId) {
        $userId = $this->userSession->getUser()->getUID();
        $qb = $this->db->getQueryBuilder();
        
        $result = $qb->select('*')
            ->from('door_estimator_quotes')
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($quoteId)))
            ->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
            ->execute();
            
        $row = $result->fetch();
        if ($row) {
            $row['quote_data'] = json_decode($row['quote_data'], true);
            return $row;
        }
        
        return null;
    }
    
    public function getUserQuotes() {
        $userId = $this->userSession->getUser()->getUID();
        $qb = $this->db->getQueryBuilder();
        
        $result = $qb->select('id', 'total_amount', 'created_at', 'updated_at')
            ->from('door_estimator_quotes')
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
            ->orderBy('updated_at', 'DESC')
            ->execute();
            
        $quotes = [];
        while ($row = $result->fetch()) {
            $quotes[] = $row;
        }
        
        return $quotes;
    }
    
    public function generateQuotePDF($quoteId) {
        $quote = $this->getQuote($quoteId);
        if (!$quote) {
            return null;
        }
        
        // Use TCPDF or similar library to generate PDF
        require_once __DIR__ . '/../../vendor/tecnickcom/tcpdf/tcpdf.php';
        
        $pdf = new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('Door Estimator');
        $pdf->SetTitle('Door & Hardware Quote #' . $quoteId);
        
        $pdf->AddPage();
        
        // Generate PDF content
        $html = $this->generateQuoteHTML($quote);
        $pdf->writeHTML($html, true, false, true, false, '');
        
        // Save to NextCloud storage
        $fileName = 'quote_' . $quoteId . '.pdf';
        $folder = $this->appData->getDirectoryListing();
        $file = $folder->newFile($fileName);
        $file->putContent($pdf->Output('', 'S'));
        
        return $fileName;
    }
    
    private function generateQuoteHTML($quote) {
        $quoteData = $quote['quote_data'];
        $html = '
        <style>
            body { font-family: Arial, sans-serif; }
            .header { text-align: center; margin-bottom: 30px; }
            .section { margin-bottom: 20px; }
            .section-title { font-weight: bold; font-size: 14px; margin-bottom: 10px; }
            table { width: 100%; border-collapse: collapse; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; }
            .total { font-weight: bold; background-color: #e8f5e8; }
        </style>
        
        <div class="header">
            <h1>Door & Hardware Quote</h1>
            <p>Quote #' . $quote['id'] . ' - ' . date('F j, Y', strtotime($quote['created_at'])) . '</p>
        </div>';
        
        foreach ($quoteData as $section => $items) {
            if (!empty($items) && is_array($items)) {
                $html .= '<div class="section">';
                $html .= '<div class="section-title">' . ucwords(str_replace(['_', 'Options'], [' ', ' Options'], $section)) . '</div>';
                $html .= '<table>';
                $html .= '<tr><th>Item</th><th>Qty</th><th>Price</th><th>Total</th></tr>';
                
                foreach ($items as $item) {
                    if ($item['qty'] > 0) {
                        $html .= '<tr>';
                        $html .= '<td>' . htmlspecialchars($item['item']) . '</td>';
                        $html .= '<td>' . $item['qty'] . '</td>';
                        $html .= '<td>$' . number_format($item['price'], 2) . '</td>';
                        $html .= '<td>$' . number_format($item['total'], 2) . '</td>';
                        $html .= '</tr>';
                    }
                }
                
                $html .= '</table></div>';
            }
        }
        
        $html .= '<div class="section">';
        $html .= '<table>';
        $html .= '<tr class="total"><td colspan="3"><strong>Grand Total</strong></td><td><strong>$' . number_format($quote['total_amount'], 2) . '</strong></td></tr>';
        $html .= '</table></div>';
        
        return $html;
    }
    
    private function calculateQuoteTotal($quoteData) {
        $total = 0;
        foreach ($quoteData as $section) {
            if (is_array($section)) {
                foreach ($section as $item) {
                    $total += $item['total'] ?? 0;
                }
            }
        }
        return $total;
    }
}

// Migration script for database tables
// lib/Migration/Version001000Date20250124000000.php
namespace OCA\DoorEstimator\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\ISimpleMigration;
use OCP\Migration\IOutput;

class Version001000Date20250124000000 implements ISimpleMigration {
    
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
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
                'length' => 50,
            ]);
            $table->addColumn('item_name', 'text', [
                'notnull' => true,
            ]);
            $table->addColumn('price', 'decimal', [
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
            $table->addIndex(['category'], 'door_estimator_pricing_category');
            $table->addIndex(['category', 'subcategory'], 'door_estimator_pricing_cat_subcat');
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
            $table->addColumn('quote_data', 'text', [
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
            $table->addIndex(['user_id'], 'door_estimator_quotes_user');
        }
        
        return $schema;
    }
}

// Sample data import script
// lib/Command/ImportPricingData.php
namespace OCA\DoorEstimator\Command;

use OCP\IDBConnection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ImportPricingData extends Command {
    
    private $db;
    
    public function __construct(IDBConnection $db) {
        parent::__construct();
        $this->db = $db;
    }
    
    protected function configure() {
        $this->setName('door-estimator:import-pricing')
             ->setDescription('Import pricing data from Excel export');
    }
    
    protected function execute(InputInterface $input, OutputInterface $output) {
        $output->writeln('Importing pricing data...');
        
        // Sample data - in real implementation, this would read from your Excel export
        $pricingData = [
            'doors' => [
                ['item' => '2-0 x 6-8 Flush HM 18ga. Cyl lock prep, 2-3/4 backset', 'price' => 493.00],
                ['item' => '2-4 x 6-8 Flush HM 18ga. Cyl lock prep, 2-3/4 backset', 'price' => 498.00],
                ['item' => '2-6 x 6-8 Flush HM 18ga. Cyl lock prep, 2-3/4 backset', 'price' => 510.00],
            ],
            'frames' => [
                'HM Drywall' => [
                    ['item' => '2-0 x 6-8 x 5-7/8 KD 16ga. HM Drywall Frame, UL Fire Label', 'price' => 228.00],
                    ['item' => '2-4 x 6-8 x 5-7/8 KD 16ga. HM Drywall Frame, UL Fire Label', 'price' => 233.00],
                ],
                'HM EWA' => [
                    ['item' => '2-0 x 6-8 x 4-9/16 KD 16ga. HM EWA Frame, UL Fire Label', 'price' => 198.00],
                    ['item' => '2-4 x 6-8 x 4-9/16 KD 16ga. HM EWA Frame, UL Fire Label', 'price' => 203.00],
                ]
            ]
        ];
        
        $qb = $this->db->getQueryBuilder();
        
        foreach ($pricingData as $category => $items) {
            if ($category === 'frames') {
                foreach ($items as $subcategory => $subitems) {
                    foreach ($subitems as $item) {
                        $qb->insert('door_estimator_pricing')
                            ->values([
                                'category' => $qb->createNamedParameter($category),
                                'subcategory' => $qb->createNamedParameter($subcategory),
                                'item_name' => $qb->createNamedParameter($item['item']),
                                'price' => $qb->createNamedParameter($item['price']),
                                'created_at' => $qb->createNamedParameter(date('Y-m-d H:i:s')),
                                'updated_at' => $qb->createNamedParameter(date('Y-m-d H:i:s'))
                            ]);
                        $qb->execute();
                    }
                }
            } else {
                foreach ($items as $item) {
                    $qb->insert('door_estimator_pricing')
                        ->values([
                            'category' => $qb->createNamedParameter($category),
                            'subcategory' => $qb->createNamedParameter(null),
                            'item_name' => $qb->createNamedParameter($item['item']),
                            'price' => $qb->createNamedParameter($item['price']),
                            'created_at' => $qb->createNamedParameter(date('Y-m-d H:i:s')),
                            'updated_at' => $qb->createNamedParameter(date('Y-m-d H:i:s'))
                        ]);
                    $qb->execute();
                }
            }
        }
        
        $output->writeln('Pricing data imported successfully!');
        return 0;
    }
}
?>