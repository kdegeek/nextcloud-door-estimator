<?php

namespace OCA\DoorEstimator\Repository;

use OCP\IDBConnection;

class EstimatorRepository
{
    private $db;

    public function __construct(IDBConnection $db)
    {
        $this->db = $db;
    }

    // Pricing CRUD
    public function getAllPricingData(): array
    {
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

    public function getPricingByCategory(string $category): array
    {
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

    public function lookupPrice(string $category, string $item, ?string $frameType = null): float
    {
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

    public function updatePricingItem(array $data): bool
    {
        $qb = $this->db->getQueryBuilder();
        if (isset($data['id']) && $data['id']) {
            $affected = $qb->update('door_estimator_pricing')
                ->set('item_name', $qb->createNamedParameter($data['item']))
                ->set('price', $qb->createNamedParameter($data['price']))
                ->set('stock_status', $qb->createNamedParameter($data['stock_status'] ?? 'stock'))
                ->set('description', $qb->createNamedParameter($data['description'] ?? ''))
                ->set('updated_at', $qb->createNamedParameter(date('Y-m-d H:i:s')))
                ->where($qb->expr()->eq('id', $qb->createNamedParameter($data['id'])))
                ->execute();
        } else {
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

    // Quotes CRUD
    public function saveQuote(array $quoteData, array $markups, string $userId, ?string $quoteName = null, ?string $customerInfo = null, float $totalAmount = 0.0): int
    {
        $qb = $this->db->getQueryBuilder();
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

    public function getQuote(int $quoteId, string $userId): ?array
    {
        $qb = $this->db->getQueryBuilder();
        $result = $qb->select('*')
            ->from('door_estimator_quotes')
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($quoteId)))
            ->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
            ->execute();
        $row = $result->fetch();
        return $row ?: null;
    }

    public function getUserQuotes(string $userId): array
    {
        $qb = $this->db->getQueryBuilder();
        $result = $qb->select('id', 'quote_name', 'total_amount', 'created_at', 'updated_at')
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

    public function deleteQuote(int $quoteId, string $userId): bool
    {
        $qb = $this->db->getQueryBuilder();
        $affected = $qb->delete('door_estimator_quotes')
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($quoteId)))
            ->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
            ->execute();
        return $affected > 0;
    }
}