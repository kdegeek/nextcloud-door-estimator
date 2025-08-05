<?php

namespace OCA\DoorEstimator\Service;

use OCP\IUserSession;
use OCP\Files\IAppData;
use OCP\IConfig;
use OCP\Files\NotFoundException;
use OCA\DoorEstimator\Service\EstimatorUtils;
use OCA\DoorEstimator\Repository\EstimatorRepository;
use OCP\IDBConnection;

/**
 * Service class for business logic related to pricing, quotes, and PDF generation.
 * Handles all database and file operations for the Door Estimator app.
 */
class EstimatorService {
    
    private $userSession;
    private $appData;
    private $config;
    private $repository;
    private $db;

    public function __construct(
        EstimatorRepository $repository,
        IUserSession $userSession,
        IAppData $appData,
        IConfig $config,
        IDBConnection $db
    ) {
        $this->repository = $repository;
        $this->userSession = $userSession;
        $this->appData = $appData;
        $this->config = $config;
        $this->db = $db;
    }
    
    /**
     * Get all pricing data from the repository.
     * @return array List of all pricing items.
     */
    public function getAllPricingData(): array {
        return $this->repository->getAllPricingData();
    }
    
    /**
     * Get pricing data for a specific category.
     * @param string $category Category name (e.g., 'doors', 'frames').
     * @return array List of pricing items in the category.
     */
    public function getPricingByCategory(string $category): array {
        return $this->repository->getPricingByCategory($category);
    }
    
    /**
     * Lookup the price for a given item in a category, optionally filtered by frame type.
     * Returns 0.0 if not found.
     * @param string $category
     * @param string $item
     * @param string|null $frameType
     * @return float
     */
    public function lookupPrice(string $category, string $item, ?string $frameType = null): float {
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
        
        return $row ? (float)$row['price'] : 0.0;
    }
    
    /**
     * Update or insert a pricing item.
     * @param array $data Must include 'item', 'price', and 'category'.
     *        - 'item': string, required
     *        - 'price': float, required
     *        - 'category': string, required
     *        - 'stock_status': string, optional, default 'stock'
     *        - 'description': string, optional
     *        - 'id': int, optional (if present, updates existing)
     * @return bool True if operation affected at least one row.
     * @throws \InvalidArgumentException if required fields are missing or invalid.
     */
    public function updatePricingItem(array $data): bool {
        // Service-level input validation (defense-in-depth)
        if (
            !isset($data['item']) || !is_string($data['item']) || trim($data['item']) === '' ||
            !isset($data['price']) || !is_numeric($data['price']) ||
            !isset($data['category']) || !is_string($data['category']) || trim($data['category']) === ''
        ) {
            throw new \InvalidArgumentException('Invalid input data');
        }

        $qb = $this->db->getQueryBuilder();
        
        if (isset($data['id']) && $data['id']) {
            // Update existing item
            $affected = $qb->update('door_estimator_pricing')
                ->set('item_name', $qb->createNamedParameter($data['item']))
                ->set('price', $qb->createNamedParameter($data['price']))
                ->set('stock_status', $qb->createNamedParameter($data['stock_status'] ?? 'stock'))
                ->set('description', $qb->createNamedParameter($data['description'] ?? ''))
                ->set('updated_at', $qb->createNamedParameter(date('Y-m-d H:i:s')))
                ->where($qb->expr()->eq('id', $qb->createNamedParameter($data['id'])))
                ->execute();
        } else {
            // Insert new item
            $affected = $qb->insert('door_estimator_pricing')
                ->values([
                    'category' => $qb->createNamedParameter($data['category']),
                    'subcategory' => $qb->createNamedParameter($data['subcategory'] ?? null),
                    'item_name' => $qb->createNamedParameter($data['item']),
                    'price' => $qb->createNamedParameter($data['price']),
                    'stock_status' => $qb->createNamedParameter($data['stock_status'] ?? 'stock'),
                    'description' => $qb->createNamedParameter($data['description'] ?? ''),
                    'created_at' => $qb->createNamedParameter(date('Y-m-d H:i:s')),
                    'updated_at' => $qb->createNamedParameter(date('Y-m-d H:i:s'))
                ])
                ->execute();
        }
        
        return $affected > 0;
    }
    
    /**
     * Save a new quote for the current user.
     * @param array $quoteData Array of quote line items (see OpenAPI spec for structure).
     * @param array $markups Associative array of markups by section (e.g., ['doors' => 10]).
     * @param string|null $quoteName Optional quote name.
     * @param string|null $customerInfo Optional customer info (JSON-serializable).
     * @return int The new quote's ID.
     */
    public function saveQuote(array $quoteData, array $markups, ?string $quoteName = null, ?string $customerInfo = null): int {
        $userId = $this->userSession->getUser()->getUID();
        $qb = $this->db->getQueryBuilder();
        
        $totalAmount = EstimatorUtils::calculateQuoteTotal($quoteData, $markups);
        
        $qb->insert('door_estimator_quotes')
            ->values([
                'user_id' => $qb->createNamedParameter($userId),
                'quote_name' => $qb->createNamedParameter($quoteName ?? 'Quote ' . date('Y-m-d H:i:s')),
                'customer_info' => $qb->createNamedParameter($customerInfo ? json_encode($customerInfo) : null),
                'quote_data' => $qb->createNamedParameter(json_encode($quoteData)),
                'markups' => $qb->createNamedParameter(json_encode($markups)),
                'total_amount' => $qb->createNamedParameter($totalAmount),
                'created_at' => $qb->createNamedParameter(date('Y-m-d H:i:s')),
                'updated_at' => $qb->createNamedParameter(date('Y-m-d H:i:s'))
            ]);
            
        $qb->execute();
        return (int)$this->db->lastInsertId();
    }
    
    /**
     * Get a quote by ID for the current user.
     * @param int $quoteId
     * @return array|null Quote data or null if not found.
     */
    public function getQuote(int $quoteId): ?array {
        $userId = $this->userSession->getUser()->getUID();
        $qb = $this->db->getQueryBuilder();
        
        $result = $qb->select('*')
            ->from('door_estimator_quotes')
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($quoteId)))
            ->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
            ->execute();
            
        $row = $result->fetch();
        if ($row) {
            return [
                'id' => (int)$row['id'],
                'quote_name' => $row['quote_name'],
                'customer_info' => $row['customer_info'] ? json_decode($row['customer_info'], true) : null,
                'quote_data' => json_decode($row['quote_data'], true),
                'markups' => json_decode($row['markups'], true),
                'total_amount' => (float)$row['total_amount'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at']
            ];
        }
        
        return null;
    }
    
    /**
     * Get all quotes for the current user.
     * @return array List of quotes (id, quote_name, total_amount, created_at, updated_at).
     */
    public function getUserQuotes(): array {
        $userId = $this->userSession->getUser()->getUID();
        $qb = $this->db->getQueryBuilder();
        
        $result = $qb->select('id', 'quote_name', 'total_amount', 'created_at', 'updated_at')
            ->from('door_estimator_quotes')
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
            ->orderBy('updated_at', 'DESC')
            ->execute();
            
        $quotes = [];
        while ($row = $result->fetch()) {
            $quotes[] = [
                'id' => (int)$row['id'],
                'quote_name' => $row['quote_name'],
                'total_amount' => (float)$row['total_amount'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at']
            ];
        }
        
        return $quotes;
    }
    
    /**
     * Delete a quote by ID for the current user.
     * @param int $quoteId
     * @return bool True if a quote was deleted.
     */
    public function deleteQuote(int $quoteId): bool {
        $userId = $this->userSession->getUser()->getUID();
        $qb = $this->db->getQueryBuilder();
        
        $affected = $qb->delete('door_estimator_quotes')
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($quoteId)))
            ->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
            ->execute();
            
        return $affected > 0;
    }
    
    /**
     * Duplicate a quote by ID for the current user.
     * @param int $quoteId
     * @return int|null New quote ID or null if not found.
     */
    public function duplicateQuote(int $quoteId): ?int {
        $quote = $this->getQuote($quoteId);
        if (!$quote) {
            return null;
        }
        
        $newQuoteName = $quote['quote_name'] . ' (Copy)';
        return $this->saveQuote($quote['quote_data'], $quote['markups'], $newQuoteName, $quote['customer_info']);
    }
    
    /**
     * Search pricing items by name and optional category.
     * @param string $query Search string for item_name.
     * @param string|null $category Optional category filter.
     * @param int $limit Max results to return (default 50).
     * @return array List of matching pricing items.
     */
    public function searchPricing(string $query, ?string $category = null, int $limit = 50): array {
        $qb = $this->db->getQueryBuilder();
        
        $qb->select('*')
            ->from('door_estimator_pricing')
            ->where($qb->expr()->like('item_name', $qb->createNamedParameter('%' . $query . '%')));
            
        if ($category) {
            $qb->andWhere($qb->expr()->eq('category', $qb->createNamedParameter($category)));
        }
        
        $qb->orderBy('item_name')
            ->setMaxResults($limit);
        
        $result = $qb->execute();
        $items = [];
        
        while ($row = $result->fetch()) {
            $items[] = [
                'id' => (int)$row['id'],
                'category' => $row['category'],
                'subcategory' => $row['subcategory'],
                'item' => $row['item_name'],
                'price' => (float)$row['price'],
                'stock_status' => $row['stock_status']
            ];
        }
        
        return $items;
    }
    
    /**
     * Get default markups for doors, frames, and hardware.
     * Magic numbers: doors=15%, frames=12%, hardware=18% (defaults).
     * @return array Associative array of markups.
     */
    public function getDefaultMarkups(): array {
        $appId = 'door_estimator';
        return [
            'doors' => (float)$this->config->getAppValue($appId, 'markup_doors', '15'),
            'frames' => (float)$this->config->getAppValue($appId, 'markup_frames', '12'),
            'hardware' => (float)$this->config->getAppValue($appId, 'markup_hardware', '18')
        ];
    }
    
    /**
     * Update default markups for doors, frames, and hardware.
     * @param array $markups Associative array (keys: doors, frames, hardware).
     * @return bool Always true.
     */
    public function updateDefaultMarkups(array $markups): bool {
        $appId = 'door_estimator';
        
        if (isset($markups['doors'])) {
            $this->config->setAppValue($appId, 'markup_doors', (string)$markups['doors']);
        }
        if (isset($markups['frames'])) {
            $this->config->setAppValue($appId, 'markup_frames', (string)$markups['frames']);
        }
        if (isset($markups['hardware'])) {
            $this->config->setAppValue($appId, 'markup_hardware', (string)$markups['hardware']);
        }
        
        return true;
    }
    
    /**
     * Generate a PDF (HTML for now) for a quote by ID.
     * @param int $quoteId
     * @return array|null ['path' => string, 'downloadUrl' => string] or null if not found.
     * Note: In production, use a real PDF library (e.g., TCPDF).
     */
    public function generateQuotePDF(int $quoteId): ?array {
        $quote = $this->getQuote($quoteId);
        if (!$quote) {
            return null;
        }
        
        // Simple HTML-based PDF generation for now
        // In production, you'd use TCPDF or similar
        $html = $this->generateQuoteHTML($quote);
        
        try {
            $folder = $this->appData->getFolder('quotes');
        } catch (NotFoundException $e) {
            $folder = $this->appData->newFolder('quotes');
        }
        
        $fileName = 'quote_' . $quoteId . '_' . date('Y-m-d_H-i-s') . '.html';
        $file = $folder->newFile($fileName);
        $file->putContent($html);
        
        return [
            'path' => $fileName,
            'downloadUrl' => '/apps/door_estimator/quotes/' . $fileName
        ];
    }
    
    private function generateQuoteHTML(array $quote): string {
        $quoteData = $quote['quote_data'];
        $markups = $quote['markups'];
        
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Door & Hardware Quote #' . $quote['id'] . '</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 20px; }
        .company-name { font-size: 24px; font-weight: bold; color: #2c5282; }
        .quote-info { background: #f7fafc; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .section { margin-bottom: 25px; }
        .section-title { font-weight: bold; font-size: 16px; margin-bottom: 10px; color: #2d3748; border-bottom: 1px solid #e2e8f0; padding-bottom: 5px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        th, td { border: 1px solid #e2e8f0; padding: 8px; text-align: left; }
        th { background-color: #f7fafc; font-weight: bold; }
        .price { text-align: right; }
        .total-row { background-color: #e6fffa; font-weight: bold; }
        .grand-total { background-color: #bee3f8; font-weight: bold; font-size: 18px; }
        .markup-info { font-style: italic; color: #718096; font-size: 12px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="company-name">Millwork Products LLC</div>
        <p>Professional Door & Hardware Solutions</p>
    </div>
    
    <div class="quote-info">
        <strong>Quote #' . $quote['id'] . '</strong><br>
        <strong>Date:</strong> ' . date('F j, Y', strtotime($quote['created_at'])) . '<br>
        <strong>Quote Name:</strong> ' . htmlspecialchars($quote['quote_name']) . '
    </div>';
        
        $grandTotal = 0;
        
        foreach ($quoteData as $sectionKey => $items) {
            if (!empty($items) && is_array($items)) {
                $sectionName = EstimatorUtils::formatSectionName($sectionKey);
                $sectionSubtotal = 0;
                $hasItems = false;
                
                // Check if section has any items with quantity > 0
                foreach ($items as $item) {
                    if (isset($item['qty']) && $item['qty'] > 0) {
                        $hasItems = true;
                        break;
                    }
                }
                
                if (!$hasItems) continue;
                
                $html .= '<div class="section">';
                $html .= '<div class="section-title">' . $sectionName . '</div>';
                $html .= '<table>';
                $html .= '<tr><th>Item</th><th>Qty</th><th>Unit Price</th><th>Subtotal</th></tr>';
                
                foreach ($items as $item) {
                    if (isset($item['qty']) && $item['qty'] > 0) {
                        $itemTotal = ($item['qty'] ?? 0) * ($item['price'] ?? 0);
                        $sectionSubtotal += $itemTotal;
                        
                        $html .= '<tr>';
                        $html .= '<td>' . htmlspecialchars($item['item'] ?? '') . '</td>';
                        $html .= '<td class="price">' . ($item['qty'] ?? 0) . '</td>';
                        $html .= '<td class="price">$' . number_format($item['price'] ?? 0, 2) . '</td>';
                        $html .= '<td class="price">$' . number_format($itemTotal, 2) . '</td>';
                        $html .= '</tr>';
                    }
                }
                
                // Apply markup
                $markup = EstimatorUtils::getSectionMarkup($sectionKey, $markups);
                $sectionTotal = $sectionSubtotal * (1 + $markup / 100);
                $grandTotal += $sectionTotal;
                
                $html .= '<tr class="total-row">';
                $html .= '<td colspan="3"><strong>Section Total (with ' . $markup . '% markup)</strong></td>';
                $html .= '<td class="price"><strong>$' . number_format($sectionTotal, 2) . '</strong></td>';
                $html .= '</tr>';
                
                $html .= '</table></div>';
            }
        }
        
        $html .= '<div class="section">';
        $html .= '<table>';
        $html .= '<tr class="grand-total"><td colspan="3"><strong>GRAND TOTAL</strong></td><td class="price"><strong>$' . number_format($grandTotal, 2) . '</strong></td></tr>';
        $html .= '</table></div>';
        
        $html .= '<div class="markup-info">
            <p><strong>Markup Applied:</strong> Doors & Inserts: ' . $markups['doors'] . '%, Frames: ' . $markups['frames'] . '%, Hardware: ' . $markups['hardware'] . '%</p>
            <p><em>This quote is valid for 30 days from the date of issue. Prices subject to change without notice.</em></p>
        </div>';
        
        $html .= '</body></html>';
        
        return $html;
    }
    
    
    
    /**
     * Import pricing data from an uploaded file.
     * @param array $uploadedFile Must include 'type', 'size', 'tmp_name'.
     * Allowed types: text/csv, xls, xlsx. Max size: 5MB.
     * @return array ['imported' => int, 'errors' => array]
     */
    public function importPricingFromUpload(array $uploadedFile): array {
        // Validate file type, size, and content (defense-in-depth)
        $allowedTypes = [
            'text/csv',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/json'
        ];
        $maxSize = 5 * 1024 * 1024; // 5MB

        $fileType = $uploadedFile['type'] ?? '';
        $fileSize = $uploadedFile['size'] ?? 0;
        $tmpName = $uploadedFile['tmp_name'] ?? '';

        $errors = [];
        if (!in_array($fileType, $allowedTypes, true)) {
            $errors[] = 'Unsupported file type';
        }
        if ($fileSize <= 0 || $fileSize > $maxSize) {
            $errors[] = 'File size exceeds limit or is empty';
        }
        if (!is_readable($tmpName)) {
            $errors[] = 'Uploaded file is not readable';
        }
        if (!empty($errors)) {
            return ['imported' => 0, 'errors' => $errors];
        }

        $imported = 0;
        $rowErrors = [];

        // Helper: Validate and map a row to DB fields
        $validateRow = function($row, $rowNum) use (&$rowErrors) {
            $required = ['item', 'price', 'category'];
            foreach ($required as $field) {
                if (!isset($row[$field]) || (is_string($row[$field]) && trim($row[$field]) === '') || ($field === 'price' && !is_numeric($row[$field]))) {
                    $rowErrors[] = "Row $rowNum: Missing or invalid '$field'";
                    return false;
                }
            }
            return true;
        };

        // Helper: Insert or update a row, handle duplicates/DB errors
        $processRow = function($row, $rowNum) use (&$imported, &$rowErrors, $validateRow) {
            if (!$validateRow($row, $rowNum)) {
                return;
            }
            try {
                $result = $this->updatePricingItem($row);
                if ($result) {
                    $imported++;
                } else {
                    $rowErrors[] = "Row $rowNum: Duplicate or DB constraint violation";
                }
            } catch (\Exception $e) {
                $rowErrors[] = "Row $rowNum: " . $e->getMessage();
            }
        };

        // JSON import
        if ($fileType === 'application/json') {
            $jsonContent = file_get_contents($tmpName);
            $data = json_decode($jsonContent, true);

            // Validate and process 'markups' key
            if (!isset($data['markups']) || !is_array($data['markups'])) {
                throw new \InvalidArgumentException("The 'markups' key is required and must be an array in the imported JSON.");
            }

            // Optionally, further process or normalize 'markups' here if needed
            // For example, ensure each markup entry has required fields
            foreach ($data['markups'] as $markup) {
                if (!isset($markup['type']) || !isset($markup['value'])) {
                    throw new \InvalidArgumentException("Each markup must have 'type' and 'value' fields.");
                }
                // Additional normalization or transformation can be done here
            }
            $data = json_decode($jsonContent, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return ['imported' => 0, 'errors' => ['Invalid JSON file']];
            }
            if (!isset($data['pricingData']) || !is_array($data['pricingData'])) {
                return ['imported' => 0, 'errors' => ['JSON must contain pricingData (array)']];
            }
            foreach ($data['pricingData'] as $i => $row) {
                $rowNum = $i + 1;
                $processRow($row, $rowNum);
            }
            return ['imported' => $imported, 'errors' => $rowErrors];
        }

        // CSV/Excel import using PhpSpreadsheet
        try {
            $spreadsheet = null;
            if ($fileType === 'text/csv') {
                $reader = new \PhpOffice\PhpSpreadsheet\Reader\Csv();
                $reader->setDelimiter(',');
                $spreadsheet = $reader->load($tmpName);
            } elseif ($fileType === 'application/vnd.ms-excel' || $fileType === 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet') {
                $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
                $spreadsheet = $reader->load($tmpName);
            } else {
                return ['imported' => 0, 'errors' => ['Unsupported file type']];
            }

            $sheet = $spreadsheet->getActiveSheet();
            $header = [];
            foreach ($sheet->getRowIterator() as $rowIndex => $row) {
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);
                $rowData = [];
                foreach ($cellIterator as $cell) {
                    $rowData[] = $cell->getValue();
                }
                if ($rowIndex === 1) {
                    // First row is header
                    $header = array_map(function($h) {
                        return strtolower(trim($h));
                    }, $rowData);
                    continue;
                }
                if (empty(array_filter($rowData))) {
                    continue; // skip empty rows
                }
                $assoc = [];
                foreach ($header as $i => $col) {
                    $assoc[$col] = $rowData[$i] ?? null;
                }
                $processRow($assoc, $rowIndex);
            }
        } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
            return ['imported' => 0, 'errors' => ['Spreadsheet parse error: ' . $e->getMessage()]];
        } catch (\Exception $e) {
            return ['imported' => 0, 'errors' => ['Import error: ' . $e->getMessage()]];
        }

        return ['imported' => $imported, 'errors' => $rowErrors];
    }
    /**
     * Check if any pricing data exists in the database.
     * @return bool True if at least one pricing item exists, false otherwise.
     */
    public function isPricingDataPresent(): bool {
        $qb = $this->db->getQueryBuilder();
        $qb->select('id')
            ->from('door_estimator_pricing')
            ->setMaxResults(1);
        $result = $qb->execute();
        $row = $result->fetch();
        return $row !== false;
    }
}