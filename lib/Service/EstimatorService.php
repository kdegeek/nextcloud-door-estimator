<?php

namespace OCA\DoorEstimator\Service;

use OCP\IDBConnection;
use OCP\IUserSession;
use OCP\Files\IAppData;
use OCP\IConfig;
use OCP\Files\NotFoundException;

class EstimatorService {
    
    private $db;
    private $userSession;
    private $appData;
    private $config;
    
    public function __construct(IDBConnection $db, IUserSession $userSession, IAppData $appData, IConfig $config) {
        $this->db = $db;
        $this->userSession = $userSession;
        $this->appData = $appData;
        $this->config = $config;
    }
    
    public function getAllPricingData(): array {
        $qb = $this->db->getQueryBuilder();
        
        $result = $qb->select('*')
            ->from('door_estimator_pricing')
            ->orderBy('category')
            ->addOrderBy('subcategory')
            ->addOrderBy('item_name')
            ->execute();
            
        $data = [];
        while ($row = $result->fetch()) {
            $category = $row['category'];
            
            if (!isset($data[$category])) {
                $data[$category] = [];
            }
            
            if ($row['subcategory']) {
                if (!isset($data[$category][$row['subcategory']])) {
                    $data[$category][$row['subcategory']] = [];
                }
                $data[$category][$row['subcategory']][] = [
                    'id' => (int)$row['id'],
                    'item' => $row['item_name'],
                    'price' => (float)$row['price'],
                    'stock_status' => $row['stock_status'],
                    'description' => $row['description']
                ];
            } else {
                $data[$category][] = [
                    'id' => (int)$row['id'],
                    'item' => $row['item_name'],
                    'price' => (float)$row['price'],
                    'stock_status' => $row['stock_status'],
                    'description' => $row['description']
                ];
            }
        }
        
        return $data;
    }
    
    public function getPricingByCategory(string $category): array {
        $qb = $this->db->getQueryBuilder();
        
        $result = $qb->select('*')
            ->from('door_estimator_pricing')
            ->where($qb->expr()->eq('category', $qb->createNamedParameter($category)))
            ->orderBy('subcategory')
            ->addOrderBy('item_name')
            ->execute();
            
        $items = [];
        while ($row = $result->fetch()) {
            $items[] = [
                'id' => (int)$row['id'],
                'item' => $row['item_name'],
                'price' => (float)$row['price'],
                'subcategory' => $row['subcategory'],
                'stock_status' => $row['stock_status'],
                'description' => $row['description']
            ];
        }
        
        return $items;
    }
    
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
    
    public function updatePricingItem(array $data): bool {
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
    
    public function saveQuote(array $quoteData, array $markups, ?string $quoteName = null, ?string $customerInfo = null): int {
        $userId = $this->userSession->getUser()->getUID();
        $qb = $this->db->getQueryBuilder();
        
        $totalAmount = $this->calculateQuoteTotal($quoteData, $markups);
        
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
    
    public function deleteQuote(int $quoteId): bool {
        $userId = $this->userSession->getUser()->getUID();
        $qb = $this->db->getQueryBuilder();
        
        $affected = $qb->delete('door_estimator_quotes')
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($quoteId)))
            ->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
            ->execute();
            
        return $affected > 0;
    }
    
    public function duplicateQuote(int $quoteId): ?int {
        $quote = $this->getQuote($quoteId);
        if (!$quote) {
            return null;
        }
        
        $newQuoteName = $quote['quote_name'] . ' (Copy)';
        return $this->saveQuote($quote['quote_data'], $quote['markups'], $newQuoteName, $quote['customer_info']);
    }
    
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
    
    public function getDefaultMarkups(): array {
        $appId = 'door_estimator';
        return [
            'doors' => (float)$this->config->getAppValue($appId, 'markup_doors', '15'),
            'frames' => (float)$this->config->getAppValue($appId, 'markup_frames', '12'),
            'hardware' => (float)$this->config->getAppValue($appId, 'markup_hardware', '18')
        ];
    }
    
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
                $sectionName = $this->formatSectionName($sectionKey);
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
                $markup = $this->getSectionMarkup($sectionKey, $markups);
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
    
    private function formatSectionName(string $sectionKey): string {
        $names = [
            'doors' => 'Doors',
            'doorOptions' => 'Door Options',
            'inserts' => 'Glass Inserts',
            'frames' => 'Frames',
            'frameOptions' => 'Frame Options',
            'hinges' => 'Hinges',
            'weatherstrip' => 'Weatherstrip',
            'closers' => 'Door Closers',
            'locksets' => 'Locksets',
            'exitDevices' => 'Exit Devices',
            'hardware' => 'Hardware'
        ];
        
        return $names[$sectionKey] ?? ucwords(str_replace(['_', 'Options'], [' ', ' Options'], $sectionKey));
    }
    
    private function getSectionMarkup(string $sectionKey, array $markups): float {
        if (in_array($sectionKey, ['doors', 'doorOptions', 'inserts'])) {
            return $markups['doors'] ?? 15;
        } elseif (in_array($sectionKey, ['frames', 'frameOptions'])) {
            return $markups['frames'] ?? 12;
        }
        return $markups['hardware'] ?? 18;
    }
    
    private function calculateQuoteTotal(array $quoteData, array $markups): float {
        $total = 0;
        
        foreach ($quoteData as $sectionKey => $items) {
            if (is_array($items)) {
                $sectionSubtotal = 0;
                foreach ($items as $item) {
                    $sectionSubtotal += ($item['qty'] ?? 0) * ($item['price'] ?? 0);
                }
                
                $markup = $this->getSectionMarkup($sectionKey, $markups);
                $total += $sectionSubtotal * (1 + $markup / 100);
            }
        }
        
        return $total;
    }
    
    public function importPricingFromUpload(array $uploadedFile): array {
        // Implementation for bulk import from uploaded CSV/Excel files
        // This would parse the uploaded file and import pricing data
        return ['imported' => 0, 'errors' => ['Not implemented yet']];
    }
}