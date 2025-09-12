<?php

namespace OCA\DoorEstimator\Service;

use OCP\IUserSession;
use OCP\Files\IAppData;
use OCP\IConfig;
use OCP\Files\NotFoundException;
use OCA\DoorEstimator\Service\EstimatorUtils;
use OCA\DoorEstimator\Repository\EstimatorRepository;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;
use OCA\DoorEstimator\Exception\ValidationException;
use OCA\DoorEstimator\Exception\QuoteNotFoundException;
use OCA\DoorEstimator\Exception\PricingItemNotFoundException;
use OCA\DoorEstimator\Exception\ImportException;
use OCP\ICacheFactory;
use OCP\ICache;

/**
 * Service class for business logic related to pricing, quotes, and PDF generation.
 * Handles all database and file operations for the Door Estimator app with comprehensive
 * input validation, error handling, and logging.
 */
class EstimatorService {
    
    private IUserSession $userSession;
    private IAppData $appData;
    private IConfig $config;
    private EstimatorRepository $repository;
    private IDBConnection $db;
    private LoggerInterface $logger;
    private ICacheFactory $cacheFactory;
    private ICache $cache;

    public function __construct(
        EstimatorRepository $repository,
        IUserSession $userSession,
        IAppData $appData,
        IConfig $config,
        IDBConnection $db,
        LoggerInterface $logger,
        ICacheFactory $cacheFactory
    ) {
        $this->repository = $repository;
        $this->userSession = $userSession;
        $this->appData = $appData;
        $this->config = $config;
        $this->db = $db;
        $this->logger = $logger;
        $this->cacheFactory = $cacheFactory;
        $this->cache = $cacheFactory->createDistributed('door_estimator');
    }
    
    /**
     * Get all pricing data from the repository with caching.
     * @return array List of all pricing items.
     */
    public function getAllPricingData(): array {
        $cacheKey = 'pricing_data_all';
        $cached = $this->cache->get($cacheKey);
        
        if ($cached !== null) {
            $this->logger->debug('Cache hit for all pricing data');
            return json_decode($cached, true);
        }
        
        $data = $this->repository->getAllPricingData();
        
        // Cache for 5 minutes (300 seconds)
        $this->cache->set($cacheKey, json_encode($data), 300);
        $this->logger->debug('Cached all pricing data', ['items_count' => count($data)]);
        
        return $data;
    }
    
    /**
     * Get pricing data for a specific category with caching.
     * @param string $category Category name (e.g., 'doors', 'frames').
     * @return array List of pricing items in the category.
     */
    public function getPricingByCategory(string $category): array {
        $cacheKey = 'pricing_data_category_' . md5($category);
        $cached = $this->cache->get($cacheKey);
        
        if ($cached !== null) {
            $this->logger->debug('Cache hit for category pricing data', ['category' => $category]);
            return json_decode($cached, true);
        }
        
        $data = $this->repository->getPricingByCategory($category);
        
        // Cache for 5 minutes (300 seconds)
        $this->cache->set($cacheKey, json_encode($data), 300);
        $this->logger->debug('Cached category pricing data', [
            'category' => $category,
            'items_count' => count($data)
        ]);
        
        return $data;
    }
    
    /**
     * Lookup the price for a given item in a category with caching and optimized query.
     * Supports subcategory filtering for frame types (HM Drywall, HM EWA, HM USA).
     * 
     * @param string $category Product category (doors, frames, hardware, etc.)
     * @param string $item Item name to lookup
     * @param string|null $frameType Optional frame type for subcategory filtering
     * @return float Price value, 0.0 if not found
     * @throws ValidationException If input parameters are invalid
     */
    public function lookupPrice(string $category, string $item, ?string $frameType = null): float {
        // Input validation
        $this->validatePriceLookupInput($category, $item, $frameType);
        
        // Create cache key based on lookup parameters
        $cacheKey = 'price_lookup_' . md5($category . '|' . $item . '|' . ($frameType ?? ''));
        $cached = $this->cache->get($cacheKey);
        
        if ($cached !== null) {
            $this->logger->debug('Cache hit for price lookup', [
                'category' => $category,
                'item' => $item,
                'frameType' => $frameType
            ]);
            return (float)$cached;
        }
        
        try {
            $qb = $this->db->getQueryBuilder();
            
            // Optimized query using the new composite index
            $qb->select('price')
                ->from('door_estimator_pricing')
                ->where($qb->expr()->eq('item_name', $qb->createNamedParameter($item)))
                ->andWhere($qb->expr()->eq('category', $qb->createNamedParameter($category)));
                
            if ($frameType) {
                $qb->andWhere($qb->expr()->eq('subcategory', $qb->createNamedParameter($frameType)));
            }
            
            // Add limit for performance
            $qb->setMaxResults(1);
            
            $result = $qb->executeQuery();
            $row = $result->fetch();
            
            $price = $row ? (float)$row['price'] : 0.0;
            
            // Cache the result for 10 minutes (600 seconds)
            $this->cache->set($cacheKey, $price, 600);
            
            if ($price === 0.0) {
                $this->logger->info('Price lookup returned 0.0', [
                    'category' => $category,
                    'item' => $item,
                    'frameType' => $frameType,
                    'found' => $row !== false
                ]);
            } else {
                $this->logger->debug('Price lookup successful', [
                    'category' => $category,
                    'item' => $item,
                    'frameType' => $frameType,
                    'price' => $price
                ]);
            }
            
            return $price;
            
        } catch (\Exception $e) {
            $this->logger->error('Price lookup failed', [
                'category' => $category,
                'item' => $item,
                'frameType' => $frameType,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Update or insert a pricing item with comprehensive validation and error handling.
     * 
     * @param array $data Must include 'item', 'price', and 'category'.
     *        - 'item': string, required (max 255 chars)
     *        - 'price': float, required (must be positive)
     *        - 'category': string, required (max 50 chars)
     *        - 'subcategory': string, optional (max 100 chars, for frame types)
     *        - 'stock_status': string, optional, default 'stock'
     *        - 'description': string, optional (max 2000 chars)
     *        - 'id': int, optional (if present, updates existing)
     * @return bool True if operation affected at least one row.
     * @throws ValidationException if required fields are missing or invalid.
     */
    public function updatePricingItem(array $data): bool {
        // Comprehensive input validation
        $this->validatePricingItemData($data);
        
        // Sanitize input data
        $sanitizedData = $this->sanitizePricingItemData($data);
        
        try {
            $qb = $this->db->getQueryBuilder();
            
            if (isset($sanitizedData['id']) && $sanitizedData['id']) {
                // Update existing item
                $this->logger->info('Updating pricing item', ['id' => $sanitizedData['id'], 'item' => $sanitizedData['item']]);
                
                $affected = $qb->update('door_estimator_pricing')
                    ->set('item_name', $qb->createNamedParameter($sanitizedData['item']))
                    ->set('price', $qb->createNamedParameter($sanitizedData['price']))
                    ->set('category', $qb->createNamedParameter($sanitizedData['category']))
                    ->set('subcategory', $qb->createNamedParameter($sanitizedData['subcategory'] ?? null))
                    ->set('stock_status', $qb->createNamedParameter($sanitizedData['stock_status'] ?? 'stock'))
                    ->set('description', $qb->createNamedParameter($sanitizedData['description'] ?? ''))
                    ->set('updated_at', $qb->createNamedParameter(date('Y-m-d H:i:s')))
                    ->where($qb->expr()->eq('id', $qb->createNamedParameter($sanitizedData['id'])))
                    ->executeStatement();
            } else {
                // Insert new item
                $this->logger->info('Creating new pricing item', ['item' => $sanitizedData['item'], 'category' => $sanitizedData['category']]);
                
                $affected = $qb->insert('door_estimator_pricing')
                    ->values([
                        'category' => $qb->createNamedParameter($sanitizedData['category']),
                        'subcategory' => $qb->createNamedParameter($sanitizedData['subcategory'] ?? null),
                        'item_name' => $qb->createNamedParameter($sanitizedData['item']),
                        'price' => $qb->createNamedParameter($sanitizedData['price']),
                        'stock_status' => $qb->createNamedParameter($sanitizedData['stock_status'] ?? 'stock'),
                        'description' => $qb->createNamedParameter($sanitizedData['description'] ?? ''),
                        'created_at' => $qb->createNamedParameter(date('Y-m-d H:i:s')),
                        'updated_at' => $qb->createNamedParameter(date('Y-m-d H:i:s'))
                    ])
                    ->executeStatement();
            }
            
            $success = $affected > 0;
            if (!$success) {
                $this->logger->warning('Pricing item operation affected 0 rows', $sanitizedData);
            } else {
                // Invalidate pricing caches when data is updated
                $this->invalidatePricingCaches($sanitizedData['category']);
            }
            
            return $success;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to update pricing item', [
                'data' => $sanitizedData,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Save a new quote for the current user with comprehensive validation and user isolation.
     * Applies configurable markup percentages: doors (15%), frames (12%), hardware (18%).
     * 
     * @param array $quoteData Array of quote line items organized by sections
     * @param array $markups Associative array of markups by section (doors, frames, hardware)
     * @param string|null $quoteName Optional quote name (max 255 chars)
     * @param mixed $customerInfo Optional customer info (will be JSON encoded)
     * @return int The new quote's ID
     * @throws ValidationException if quote data or markups are invalid
     */
    public function saveQuote(array $quoteData, array $markups, ?string $quoteName = null, $customerInfo = null): int {
        // Validate user session
        $user = $this->userSession->getUser();
        if (!$user) {
            throw new ValidationException('User not authenticated');
        }
        $userId = $user->getUID();
        
        // Validate and sanitize input
        $this->validateQuoteData($quoteData);
        $this->validateMarkups($markups);
        
        $sanitizedQuoteName = $this->sanitizeQuoteName($quoteName);
        $sanitizedCustomerInfo = $this->sanitizeCustomerInfo($customerInfo);
        
        try {
            $totalAmount = EstimatorUtils::calculateQuoteTotal($quoteData, $markups);
            
            $this->logger->info('Saving new quote', [
                'user_id' => $userId,
                'quote_name' => $sanitizedQuoteName,
                'total_amount' => $totalAmount,
                'sections' => array_keys($quoteData)
            ]);
            
            $this->db->beginTransaction();
            
            $qb = $this->db->getQueryBuilder();
            $qb->insert('door_estimator_quotes')
                ->values([
                    'user_id' => $qb->createNamedParameter($userId),
                    'quote_name' => $qb->createNamedParameter($sanitizedQuoteName),
                    'customer_info' => $qb->createNamedParameter($sanitizedCustomerInfo ? json_encode($sanitizedCustomerInfo) : null),
                    'quote_data' => $qb->createNamedParameter(json_encode($quoteData)),
                    'markups' => $qb->createNamedParameter(json_encode($markups)),
                    'total_amount' => $qb->createNamedParameter($totalAmount),
                    'created_at' => $qb->createNamedParameter(date('Y-m-d H:i:s')),
                    'updated_at' => $qb->createNamedParameter(date('Y-m-d H:i:s'))
                ]);
            
            $qb->executeStatement();
            $id = (int)$this->db->lastInsertId();
            
            $this->db->commit();
            
            // Invalidate user quotes cache
            $this->invalidateUserQuotesCache($userId);
            
            $this->logger->info('Quote saved successfully', ['quote_id' => $id, 'user_id' => $userId]);
            
            return $id;
            
        } catch (\Throwable $e) {
            $this->db->rollBack();
            $this->logger->error('Failed to save quote', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'quote_name' => $sanitizedQuoteName ?? 'unnamed'
            ]);
            throw $e;
        }
    }
    
    /**
     * Get a quote by ID for the current user with user isolation enforcement.
     * 
     * @param int $quoteId Quote ID to retrieve
     * @return array Quote data with all fields
     * @throws QuoteNotFoundException if quote not found or user doesn't have access
     * @throws ValidationException if user not authenticated
     */
    public function getQuote(int $quoteId): array {
        // Validate user session
        $user = $this->userSession->getUser();
        if (!$user) {
            throw new ValidationException('User not authenticated');
        }
        $userId = $user->getUID();
        
        // Validate quote ID
        if ($quoteId <= 0) {
            throw new ValidationException('Invalid quote ID');
        }
        
        try {
            $qb = $this->db->getQueryBuilder();
            
            $result = $qb->select('*')
                ->from('door_estimator_quotes')
                ->where($qb->expr()->eq('id', $qb->createNamedParameter($quoteId)))
                ->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
                ->executeQuery();
                
            $row = $result->fetch();
            
            if (!$row) {
                $this->logger->warning('Quote not found or access denied', [
                    'quote_id' => $quoteId,
                    'user_id' => $userId
                ]);
                throw new QuoteNotFoundException($quoteId);
            }
            
            $quote = [
                'id' => (int)$row['id'],
                'quote_name' => $row['quote_name'],
                'customer_info' => $row['customer_info'] ? json_decode($row['customer_info'], true) : null,
                'quote_data' => json_decode($row['quote_data'], true),
                'markups' => json_decode($row['markups'], true),
                'total_amount' => (float)$row['total_amount'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at']
            ];
            
            $this->logger->debug('Quote retrieved successfully', [
                'quote_id' => $quoteId,
                'user_id' => $userId,
                'quote_name' => $quote['quote_name']
            ]);
            
            return $quote;
            
        } catch (QuoteNotFoundException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('Failed to retrieve quote', [
                'quote_id' => $quoteId,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Get quotes for the current user with pagination and caching.
     * @param int $limit Maximum number of quotes to return (default 50).
     * @param int $offset Number of quotes to skip (default 0).
     * @return array List of quotes with pagination info.
     */
    public function getUserQuotes(int $limit = 50, int $offset = 0): array {
        $user = $this->userSession->getUser();
        if (!$user) {
            throw new ValidationException('User not authenticated');
        }
        $userId = $user->getUID();
        
        // Validate pagination parameters
        $limit = max(1, min(100, $limit)); // Limit between 1-100
        $offset = max(0, $offset);
        
        // Create cache key for user quotes
        $cacheKey = 'user_quotes_' . md5($userId . '|' . $limit . '|' . $offset);
        $cached = $this->cache->get($cacheKey);
        
        if ($cached !== null) {
            $this->logger->debug('Cache hit for user quotes', ['user_id' => $userId]);
            return json_decode($cached, true);
        }
        
        try {
            $qb = $this->db->getQueryBuilder();
            
            // Count total quotes for pagination
            $countQb = $this->db->getQueryBuilder();
            $totalResult = $countQb->select($countQb->func()->count('*', 'total'))
                ->from('door_estimator_quotes')
                ->where($countQb->expr()->eq('user_id', $countQb->createNamedParameter($userId)))
                ->executeQuery();
            $total = (int)$totalResult->fetchOne();
            
            // Get paginated quotes using the user_id index
            $result = $qb->select('id', 'quote_name', 'total_amount', 'created_at', 'updated_at')
                ->from('door_estimator_quotes')
                ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
                ->orderBy('updated_at', 'DESC')
                ->setMaxResults($limit)
                ->setFirstResult($offset)
                ->executeQuery();
                
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
            
            $hasMore = ($offset + $limit) < $total;
            $quotesResult = [
                'quotes' => $quotes,
                'total' => $total,
                'hasMore' => $hasMore,
                'limit' => $limit,
                'offset' => $offset
            ];
            
            // Cache for 2 minutes (120 seconds)
            $this->cache->set($cacheKey, json_encode($quotesResult), 120);
            
            $this->logger->debug('User quotes retrieved', [
                'user_id' => $userId,
                'quotes_count' => count($quotes),
                'total' => $total
            ]);
            
            return $quotesResult;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to retrieve user quotes', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Delete a quote by ID for the current user with user isolation enforcement.
     * 
     * @param int $quoteId Quote ID to delete
     * @return bool True if a quote was deleted
     * @throws ValidationException if user not authenticated or quote ID invalid
     */
    public function deleteQuote(int $quoteId): bool {
        // Validate user session
        $user = $this->userSession->getUser();
        if (!$user) {
            throw new ValidationException('User not authenticated');
        }
        $userId = $user->getUID();
        
        // Validate quote ID
        if ($quoteId <= 0) {
            throw new ValidationException('Invalid quote ID');
        }
        
        try {
            $this->logger->info('Attempting to delete quote', [
                'quote_id' => $quoteId,
                'user_id' => $userId
            ]);
            
            $qb = $this->db->getQueryBuilder();
            
            $affected = $qb->delete('door_estimator_quotes')
                ->where($qb->expr()->eq('id', $qb->createNamedParameter($quoteId)))
                ->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
                ->executeStatement();
                
            $success = $affected > 0;
            
            if ($success) {
                // Invalidate user quotes cache
                $this->invalidateUserQuotesCache($userId);
                
                $this->logger->info('Quote deleted successfully', [
                    'quote_id' => $quoteId,
                    'user_id' => $userId
                ]);
            } else {
                $this->logger->warning('Quote deletion failed - not found or access denied', [
                    'quote_id' => $quoteId,
                    'user_id' => $userId
                ]);
            }
            
            return $success;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to delete quote', [
                'quote_id' => $quoteId,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Duplicate a quote by ID for the current user with automatic name modification.
     * 
     * @param int $quoteId Quote ID to duplicate
     * @return int New quote ID
     * @throws QuoteNotFoundException if original quote not found or access denied
     * @throws ValidationException if user not authenticated or quote ID invalid
     */
    public function duplicateQuote(int $quoteId): int {
        // Validate quote ID
        if ($quoteId <= 0) {
            throw new ValidationException('Invalid quote ID');
        }
        
        try {
            $this->logger->info('Attempting to duplicate quote', ['quote_id' => $quoteId]);
            
            // Get the original quote (this will throw QuoteNotFoundException if not found)
            $quote = $this->getQuote($quoteId);
            
            // Create new quote name with "(Copy)" suffix
            $newQuoteName = $quote['quote_name'] . ' (Copy)';
            
            $newQuoteId = $this->saveQuote(
                $quote['quote_data'], 
                $quote['markups'], 
                $newQuoteName, 
                $quote['customer_info']
            );
            
            $this->logger->info('Quote duplicated successfully', [
                'original_quote_id' => $quoteId,
                'new_quote_id' => $newQuoteId,
                'new_quote_name' => $newQuoteName
            ]);
            
            return $newQuoteId;
            
        } catch (QuoteNotFoundException $e) {
            $this->logger->warning('Cannot duplicate quote - not found', ['quote_id' => $quoteId]);
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('Failed to duplicate quote', [
                'quote_id' => $quoteId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Search pricing items by name and optional category with caching and optimization.
     * @param string $query Search string for item_name.
     * @param string|null $category Optional category filter.
     * @param int $limit Max results to return (default 50).
     * @param int $offset Offset for pagination (default 0).
     * @return array List of matching pricing items with pagination info.
     */
    public function searchPricing(string $query, ?string $category = null, int $limit = 50, int $offset = 0): array {
        // Validate and sanitize inputs
        $query = trim($query);
        $limit = max(1, min(100, $limit)); // Limit between 1-100
        $offset = max(0, $offset);
        
        if (strlen($query) < 2) {
            return ['items' => [], 'total' => 0, 'hasMore' => false];
        }
        
        // Create cache key for search results
        $cacheKey = 'search_' . md5($query . '|' . ($category ?? '') . '|' . $limit . '|' . $offset);
        $cached = $this->cache->get($cacheKey);
        
        if ($cached !== null) {
            $this->logger->debug('Cache hit for pricing search', ['query' => $query, 'category' => $category]);
            return json_decode($cached, true);
        }
        
        try {
            $startTime = microtime(true);
            
            $qb = $this->db->getQueryBuilder();
            
            // Escape wildcards in search query
            $q = strtr($query, ['%' => '\\%', '_' => '\\_']);
            
            // Optimized search query using the new search index
            $qb->select('id', 'category', 'subcategory', 'item_name', 'price', 'stock_status', 'description')
                ->from('door_estimator_pricing')
                ->where($qb->expr()->like('item_name', $qb->createNamedParameter('%' . $q . '%')));
                
            if ($category) {
                $qb->andWhere($qb->expr()->eq('category', $qb->createNamedParameter($category)));
            }
            
            // Count total results for pagination
            $countQb = clone $qb;
            $countQb->select($qb->func()->count('*', 'total'));
            $totalResult = $countQb->executeQuery();
            $total = (int)$totalResult->fetchOne();
            
            // Apply ordering and pagination
            $qb->orderBy('item_name')
                ->setMaxResults($limit)
                ->setFirstResult($offset);
            
            $result = $qb->executeQuery();
            $items = [];
            
            while ($row = $result->fetch()) {
                $items[] = [
                    'id' => (int)$row['id'],
                    'category' => $row['category'],
                    'subcategory' => $row['subcategory'],
                    'item' => $row['item_name'],
                    'price' => (float)$row['price'],
                    'stock_status' => $row['stock_status'],
                    'description' => $row['description']
                ];
            }
            
            $hasMore = ($offset + $limit) < $total;
            $searchResult = [
                'items' => $items,
                'total' => $total,
                'hasMore' => $hasMore,
                'limit' => $limit,
                'offset' => $offset
            ];
            
            $executionTime = (microtime(true) - $startTime) * 1000;
            
            // Cache search results for 2 minutes (120 seconds)
            $this->cache->set($cacheKey, json_encode($searchResult), 120);
            
            $this->logger->debug('Pricing search completed', [
                'query' => $query,
                'category' => $category,
                'results_count' => count($items),
                'total' => $total,
                'execution_time_ms' => round($executionTime, 2)
            ]);
            
            // Log performance warning if search takes too long
            if ($executionTime > 500) {
                $this->logger->warning('Slow pricing search detected', [
                    'query' => $query,
                    'execution_time_ms' => round($executionTime, 2)
                ]);
            }
            
            return $searchResult;
            
        } catch (\Exception $e) {
            $this->logger->error('Pricing search failed', [
                'query' => $query,
                'category' => $category,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Invalidate pricing-related caches when data changes.
     * @param string|null $category Optional category to invalidate specific caches.
     */
    private function invalidatePricingCaches(?string $category = null): void {
        try {
            // Clear all pricing data cache
            $this->cache->remove('pricing_data_all');
            
            // Clear category-specific cache if provided
            if ($category) {
                $this->cache->remove('pricing_data_category_' . md5($category));
            }
            
            // Clear all price lookup caches (they contain category info)
            $this->cache->clear('price_lookup_');
            
            // Clear search caches
            $this->cache->clear('search_');
            
            $this->logger->debug('Pricing caches invalidated', ['category' => $category]);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to invalidate pricing caches', [
                'category' => $category,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Invalidate user quotes cache when quotes are modified.
     * @param string $userId User ID whose quotes cache should be invalidated.
     */
    private function invalidateUserQuotesCache(string $userId): void {
        try {
            // Clear all cached quote lists for this user
            $this->cache->clear('user_quotes_' . md5($userId));
            
            $this->logger->debug('User quotes cache invalidated', ['user_id' => $userId]);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to invalidate user quotes cache', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Build optimized HTML template for quote PDF.
     * 
     * @param array $quote Quote data
     * @return string HTML template
     */
    private function buildQuoteHTMLTemplate(array $quote): string {
        // Use string concatenation instead of complex templating for performance
        $html = '<!DOCTYPE html><html><head>';
        $html .= '<meta charset="UTF-8">';
        $html .= '<style>';
        $html .= $this->getOptimizedPDFStyles();
        $html .= '</style></head><body>';
        
        // Header section
        $html .= '<div class="header">';
        $html .= '<h1>Door & Hardware Quote</h1>';
        $html .= '<div class="quote-info">';
        $html .= '<p><strong>Quote #:</strong> ' . htmlspecialchars($quote['id']) . '</p>';
        $html .= '<p><strong>Date:</strong> ' . date('F j, Y', strtotime($quote['created_at'])) . '</p>';
        $html .= '<p><strong>Quote Name:</strong> ' . htmlspecialchars($quote['quote_name']) . '</p>';
        $html .= '</div></div>';
        
        // Quote content
        $html .= $this->buildQuoteContentHTML($quote);
        
        // Footer
        $html .= '<div class="footer">';
        $html .= '<p><em>This quote is valid for 30 days. Prices subject to change without notice.</em></p>';
        $html .= '</div>';
        
        $html .= '</body></html>';
        
        return $html;
    }
    
    /**
     * Get optimized CSS styles for PDF generation.
     * 
     * @return string CSS styles
     */
    private function getOptimizedPDFStyles(): string {
        return '
            body { font-family: Arial, sans-serif; font-size: 10pt; margin: 0; padding: 20px; }
            .header { border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
            .header h1 { margin: 0; color: #333; font-size: 18pt; }
            .quote-info { margin-top: 10px; }
            .quote-info p { margin: 2px 0; }
            .section { margin-bottom: 15px; }
            .section h3 { background: #f0f0f0; padding: 5px; margin: 0 0 5px 0; font-size: 12pt; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
            th, td { border: 1px solid #ddd; padding: 4px; text-align: left; font-size: 9pt; }
            th { background: #f5f5f5; font-weight: bold; }
            .total-row { font-weight: bold; background: #f9f9f9; }
            .grand-total { font-size: 12pt; font-weight: bold; color: #333; }
            .footer { margin-top: 30px; border-top: 1px solid #ccc; padding-top: 10px; font-size: 8pt; }
        ';
    }
    
    /**
     * Build quote content HTML with performance optimization.
     * 
     * @param array $quote Quote data
     * @return string HTML content
     */
    private function buildQuoteContentHTML(array $quote): string {
        $html = '';
        $quoteData = json_decode($quote['quote_data'], true);
        $markups = json_decode($quote['markups'], true);
        
        if (!is_array($quoteData)) {
            return '<p>No quote data available.</p>';
        }
        
        $grandTotal = 0;
        
        foreach ($quoteData as $sectionName => $items) {
            if (!is_array($items) || empty($items)) {
                continue;
            }
            
            $sectionTotal = 0;
            $html .= '<div class="section">';
            $html .= '<h3>' . htmlspecialchars(ucwords(str_replace('_', ' ', $sectionName))) . '</h3>';
            $html .= '<table>';
            $html .= '<thead><tr><th>Item</th><th>Qty</th><th>Unit Price</th><th>Total</th></tr></thead>';
            $html .= '<tbody>';
            
            foreach ($items as $item) {
                if (empty($item['item']) || $item['qty'] <= 0) {
                    continue;
                }
                
                $lineTotal = $item['qty'] * $item['price'];
                $sectionTotal += $lineTotal;
                
                $html .= '<tr>';
                $html .= '<td>' . htmlspecialchars($item['item']) . '</td>';
                $html .= '<td>' . number_format($item['qty']) . '</td>';
                $html .= '<td>$' . number_format($item['price'], 2) . '</td>';
                $html .= '<td>$' . number_format($lineTotal, 2) . '</td>';
                $html .= '</tr>';
            }
            
            // Apply markup
            $markup = $markups[$sectionName] ?? 0;
            $markupAmount = $sectionTotal * ($markup / 100);
            $sectionTotalWithMarkup = $sectionTotal + $markupAmount;
            $grandTotal += $sectionTotalWithMarkup;
            
            if ($markup > 0) {
                $html .= '<tr class="total-row">';
                $html .= '<td colspan="3">Subtotal + ' . number_format($markup, 1) . '% Markup</td>';
                $html .= '<td>$' . number_format($sectionTotalWithMarkup, 2) . '</td>';
                $html .= '</tr>';
            } else {
                $html .= '<tr class="total-row">';
                $html .= '<td colspan="3">Section Total</td>';
                $html .= '<td>$' . number_format($sectionTotal, 2) . '</td>';
                $html .= '</tr>';
            }
            
            $html .= '</tbody></table></div>';
        }
        
        // Grand total
        $html .= '<div class="grand-total">';
        $html .= '<p>Total Quote Amount: $' . number_format($grandTotal, 2) . '</p>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Optimize HTML content for PDF rendering.
     * 
     * @param string $html Raw HTML content
     * @return string Optimized HTML content
     */
    private function optimizeHTMLForPDF(string $html): string {
        // Remove unnecessary whitespace
        $html = preg_replace('/\s+/', ' ', $html);
        
        // Remove comments
        $html = preg_replace('/<!--.*?-->/', '', $html);
        
        // Optimize table structures for TCPDF
        $html = str_replace('<table>', '<table cellpadding="2" cellspacing="0">', $html);
        
        return trim($html);
    }
    
    /**
     * Get configuration values with caching.
     * @return array Configuration values.
     */
    public function getCachedConfiguration(): array {
        $cacheKey = 'app_configuration';
        $cached = $this->cache->get($cacheKey);
        
        if ($cached !== null) {
            return json_decode($cached, true);
        }
        
        $config = [
            'markups' => $this->getDefaultMarkups(),
            'app_version' => $this->config->getAppValue('door_estimator', 'version', '1.0.0'),
            'max_file_size' => $this->config->getAppValue('door_estimator', 'max_file_size', '5242880'), // 5MB
            'cache_ttl' => $this->config->getAppValue('door_estimator', 'cache_ttl', '300') // 5 minutes
        ];
        
        // Cache configuration for 15 minutes
        $this->cache->set($cacheKey, json_encode($config), 900);
        
        return $config;
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
     * Enhanced quote saving with flexible pricing and markup system.
     * Preserves base pricing data while allowing per-quote customization.
     * 
     * @param array $quoteData Array of quote line items organized by sections
     * @param array $markups Current markups for this quote
     * @param string|null $quoteName Optional quote name
     * @param mixed $customerInfo Optional customer info
     * @param array|null $defaultMarkups Optional default markups for comparison
     * @return int The new quote's ID
     */
    public function saveQuoteWithFlexiblePricing(
        array $quoteData, 
        array $markups, 
        ?string $quoteName = null, 
        $customerInfo = null,
        ?array $defaultMarkups = null
    ): int {
        // Get default markups if not provided
        if ($defaultMarkups === null) {
            $defaultMarkups = $this->getDefaultMarkups();
        }

        // Enhance quote data with base pricing information
        $enhancedQuoteData = $this->enhanceQuoteDataWithBasePricing($quoteData);

        // Determine which markups are overridden
        $markupsOverridden = [
            'doors' => abs($markups['doors'] - $defaultMarkups['doors']) > 0.01,
            'frames' => abs($markups['frames'] - $defaultMarkups['frames']) > 0.01,
            'hardware' => abs($markups['hardware'] - $defaultMarkups['hardware']) > 0.01,
        ];

        // Save quote with enhanced data
        return $this->saveQuoteWithMetadata(
            $enhancedQuoteData, 
            $markups, 
            $quoteName, 
            $customerInfo,
            $defaultMarkups,
            $markupsOverridden
        );
    }

    /**
     * Enhance quote data with base pricing information for price override tracking.
     * 
     * @param array $quoteData Original quote data
     * @return array Enhanced quote data with base pricing info
     */
    private function enhanceQuoteDataWithBasePricing(array $quoteData): array {
        $enhanced = [];
        
        foreach ($quoteData as $sectionKey => $items) {
            $enhanced[$sectionKey] = [];
            
            if (is_array($items)) {
                foreach ($items as $item) {
                    $enhancedItem = $item;
                    
                    // Look up base price if item name is provided
                    if (!empty($item['item'])) {
                        try {
                            $frameType = $item['frameType'] ?? null;
                            $basePrice = $this->lookupPrice($sectionKey, $item['item'], $frameType);
                            
                            $enhancedItem['basePrice'] = $basePrice;
                            $enhancedItem['priceOverridden'] = abs($item['price'] - $basePrice) > 0.01;
                        } catch (\Exception $e) {
                            // If lookup fails, mark as overridden if price is non-zero
                            $enhancedItem['basePrice'] = 0.0;
                            $enhancedItem['priceOverridden'] = $item['price'] > 0;
                        }
                    } else {
                        $enhancedItem['basePrice'] = 0.0;
                        $enhancedItem['priceOverridden'] = false;
                    }
                    
                    $enhanced[$sectionKey][] = $enhancedItem;
                }
            }
        }
        
        return $enhanced;
    }

    /**
     * Save quote with enhanced metadata for flexible pricing system.
     * 
     * @param array $quoteData Enhanced quote data with base pricing info
     * @param array $markups Current markups
     * @param string|null $quoteName Quote name
     * @param mixed $customerInfo Customer info
     * @param array $defaultMarkups Default markups for comparison
     * @param array $markupsOverridden Flags indicating overridden markups
     * @return int Quote ID
     */
    private function saveQuoteWithMetadata(
        array $quoteData,
        array $markups,
        ?string $quoteName,
        $customerInfo,
        array $defaultMarkups,
        array $markupsOverridden
    ): int {
        // Validate user session
        $user = $this->userSession->getUser();
        if (!$user) {
            throw new ValidationException('User not authenticated');
        }
        $userId = $user->getUID();
        
        // Validate and sanitize input
        $this->validateQuoteData($quoteData);
        $this->validateMarkups($markups);
        
        $sanitizedQuoteName = $this->sanitizeQuoteName($quoteName);
        $sanitizedCustomerInfo = $this->sanitizeCustomerInfo($customerInfo);
        
        try {
            $totalAmount = EstimatorUtils::calculateQuoteTotal($quoteData, $markups);
            
            $this->logger->info('Saving quote with flexible pricing', [
                'user_id' => $userId,
                'quote_name' => $sanitizedQuoteName,
                'total_amount' => $totalAmount,
                'markups_overridden' => $markupsOverridden
            ]);
            
            $this->db->beginTransaction();
            
            // Create enhanced quote metadata
            $quoteMetadata = [
                'quote_data' => $quoteData,
                'markups' => $markups,
                'defaultMarkups' => $defaultMarkups,
                'markupsOverridden' => $markupsOverridden
            ];
            
            $qb = $this->db->getQueryBuilder();
            $qb->insert('door_estimator_quotes')
                ->values([
                    'user_id' => $qb->createNamedParameter($userId),
                    'quote_name' => $qb->createNamedParameter($sanitizedQuoteName),
                    'customer_info' => $qb->createNamedParameter($sanitizedCustomerInfo ? json_encode($sanitizedCustomerInfo) : null),
                    'quote_data' => $qb->createNamedParameter(json_encode($quoteMetadata)),
                    'markups' => $qb->createNamedParameter(json_encode($markups)),
                    'total_amount' => $qb->createNamedParameter($totalAmount),
                    'created_at' => $qb->createNamedParameter(date('Y-m-d H:i:s')),
                    'updated_at' => $qb->createNamedParameter(date('Y-m-d H:i:s'))
                ]);
            
            $qb->executeStatement();
            $id = (int)$this->db->lastInsertId();
            
            $this->db->commit();
            
            $this->logger->info('Quote with flexible pricing saved successfully', [
                'quote_id' => $id, 
                'user_id' => $userId
            ]);
            
            return $id;
            
        } catch (\Throwable $e) {
            $this->db->rollBack();
            $this->logger->error('Failed to save quote with flexible pricing', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'quote_name' => $sanitizedQuoteName ?? 'unnamed'
            ]);
            throw $e;
        }
    }

    /**
     * Enhanced quote retrieval with flexible pricing metadata.
     * 
     * @param int $quoteId Quote ID to retrieve
     * @return array Quote data with flexible pricing information
     */
    public function getQuoteWithFlexiblePricing(int $quoteId): array {
        $quote = $this->getQuote($quoteId);
        
        // Parse quote data structure
        $quoteDataRaw = json_decode($quote['quote_data'], true);
        
        // Check if this is the new enhanced format
        if (is_array($quoteDataRaw) && isset($quoteDataRaw['quote_data'])) {
            // New format with flexible pricing metadata
            return [
                'id' => $quote['id'],
                'quote_name' => $quote['quote_name'],
                'customer_info' => $quote['customer_info'],
                'quote_data' => $quoteDataRaw['quote_data'],
                'markups' => $quoteDataRaw['markups'],
                'defaultMarkups' => $quoteDataRaw['defaultMarkups'] ?? $this->getDefaultMarkups(),
                'markupsOverridden' => $quoteDataRaw['markupsOverridden'] ?? [
                    'doors' => false,
                    'frames' => false,
                    'hardware' => false
                ],
                'total_amount' => $quote['total_amount'],
                'created_at' => $quote['created_at'],
                'updated_at' => $quote['updated_at']
            ];
        } else {
            // Legacy format - enhance with current defaults
            $defaultMarkups = $this->getDefaultMarkups();
            $markups = json_decode($quote['markups'], true);
            
            return [
                'id' => $quote['id'],
                'quote_name' => $quote['quote_name'],
                'customer_info' => $quote['customer_info'],
                'quote_data' => $quoteDataRaw,
                'markups' => $markups,
                'defaultMarkups' => $defaultMarkups,
                'markupsOverridden' => [
                    'doors' => abs($markups['doors'] - $defaultMarkups['doors']) > 0.01,
                    'frames' => abs($markups['frames'] - $defaultMarkups['frames']) > 0.01,
                    'hardware' => abs($markups['hardware'] - $defaultMarkups['hardware']) > 0.01,
                ],
                'total_amount' => $quote['total_amount'],
                'created_at' => $quote['created_at'],
                'updated_at' => $quote['updated_at']
            ];
        }
    }
    
    /**
     * Generate a professional PDF for a quote by ID with comprehensive error handling.
     * 
     * @param int $quoteId Quote ID to generate PDF for
     * @return array|null ['path' => string, 'downloadUrl' => string] or null if quote not found
     * @throws ValidationException if user not authenticated or quote ID invalid
     * @throws QuoteNotFoundException if quote not found or access denied
     */
    public function generateQuotePDF(int $quoteId): ?array {
        // Validate quote ID
        if ($quoteId <= 0) {
            throw new ValidationException('Invalid quote ID');
        }
        
        // Check for cached PDF first
        $cacheKey = 'pdf_' . $quoteId;
        $cached = $this->cache->get($cacheKey);
        
        if ($cached !== null) {
            $cachedData = json_decode($cached, true);
            $this->logger->debug('PDF cache hit', ['quote_id' => $quoteId]);
            return $cachedData;
        }
        
        try {
            $startTime = microtime(true);
            $this->logger->info('Starting PDF generation', ['quote_id' => $quoteId]);
            
            // Get quote data (this will validate user access and throw exceptions if needed)
            $quote = $this->getQuote($quoteId);
            
            // Generate professional HTML content with optimization
            $html = $this->generateOptimizedQuoteHTML($quote);
            
            // Create PDF with TCPDF and performance optimizations
            $pdf = $this->createOptimizedPDF($quote, $html);
            
            // Store PDF using Nextcloud's file system
            $fileInfo = $this->storePDFFile($pdf, $quoteId);
            
            $executionTime = (microtime(true) - $startTime) * 1000;
            
            // Cache the result for 1 hour (3600 seconds)
            $this->cache->set($cacheKey, json_encode($fileInfo), 3600);
            
            $this->logger->info('PDF generated successfully', [
                'quote_id' => $quoteId,
                'file_name' => $fileInfo['path'],
                'file_size' => strlen($pdf),
                'execution_time_ms' => round($executionTime, 2)
            ]);
            
            // Log performance warning if PDF generation takes too long
            if ($executionTime > 5000) {
                $this->logger->warning('Slow PDF generation detected', [
                    'quote_id' => $quoteId,
                    'execution_time_ms' => round($executionTime, 2)
                ]);
            }
            
            return $fileInfo;
            
        } catch (QuoteNotFoundException $e) {
            $this->logger->warning('PDF generation failed - quote not found', ['quote_id' => $quoteId]);
            throw $e;
        } catch (ValidationException $e) {
            $this->logger->warning('PDF generation failed - validation error', [
                'quote_id' => $quoteId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('PDF generation failed', [
                'quote_id' => $quoteId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new \RuntimeException('PDF generation failed: ' . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * Generate optimized HTML content for PDF generation.
     * 
     * @param array $quote Quote data
     * @return string Optimized HTML content
     */
    private function generateOptimizedQuoteHTML(array $quote): string {
        // Use a more efficient HTML generation approach
        $html = $this->buildQuoteHTMLTemplate($quote);
        
        // Optimize HTML for PDF rendering
        $html = $this->optimizeHTMLForPDF($html);
        
        return $html;
    }
    
    /**
     * Create an optimized PDF using TCPDF with performance improvements.
     * 
     * @param array $quote Quote data
     * @param string $html HTML content to convert
     * @return string PDF content as binary string
     */
    private function createOptimizedPDF(array $quote, string $html): string {
        try {
            // Initialize TCPDF with professional settings
            $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
            
            // Set document information
            $pdf->SetCreator('Door Estimator - Millwork Products LLC');
            $pdf->SetAuthor('Millwork Products LLC');
            $pdf->SetTitle('Door & Hardware Quote #' . $quote['id']);
            $pdf->SetSubject('Professional Door and Hardware Estimate');
            $pdf->SetKeywords('door, hardware, quote, estimate, millwork');
            
            // Set margins and auto page breaks
            $pdf->SetMargins(15, 20, 15);
            $pdf->SetHeaderMargin(10);
            $pdf->SetFooterMargin(15);
            $pdf->SetAutoPageBreak(true, 25);
            
            // Set header and footer
            $pdf->setHeaderFont(['helvetica', '', 10]);
            $pdf->setFooterFont(['helvetica', '', 8]);
            
            // Custom header
            $pdf->setHeaderData('', 0, 'Millwork Products LLC', 
                'Professional Door & Hardware Solutions' . "\n" . 
                'Quote #' . $quote['id'] . ' - ' . date('F j, Y', strtotime($quote['created_at'])));
            
            // Custom footer with page numbers
            $pdf->setFooterData([0,0,0], [0,0,0]);
            
            // Add first page
            $pdf->AddPage();
            
            // Set font for content
            $pdf->SetFont('helvetica', '', 10);
            
            // Convert HTML to PDF with proper styling
            $pdf->writeHTML($html, true, false, true, false, '');
            
            // Return PDF as string
            return $pdf->Output('', 'S');
            
        } catch (\Exception $e) {
            $this->logger->error('TCPDF generation failed', [
                'quote_id' => $quote['id'],
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('PDF creation failed: ' . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * Store PDF file using Nextcloud's IAppData interface with proper file naming.
     * 
     * @param string $pdfContent PDF binary content
     * @param int $quoteId Quote ID for file naming
     * @return array File information with path and download URL
     */
    private function storePDFFile(string $pdfContent, int $quoteId): array {
        try {
            // Ensure quotes folder exists
            try {
                $folder = $this->appData->getFolder('quotes');
            } catch (NotFoundException $e) {
                $folder = $this->appData->newFolder('quotes');
                $this->logger->info('Created quotes folder for PDF storage');
            }
            
            // Generate unique filename with timestamp
            $timestamp = date('Y-m-d_H-i-s');
            $fileName = sprintf('quote_%d_%s.pdf', $quoteId, $timestamp);
            
            // Create and write file
            $file = $folder->newFile($fileName);
            $file->putContent($pdfContent);
            
            $this->logger->debug('PDF file stored successfully', [
                'file_name' => $fileName,
                'file_size' => strlen($pdfContent)
            ]);
            
            return [
                'path' => $fileName,
                'downloadUrl' => '/apps/door_estimator/api/quotes/' . $quoteId . '/pdf/download/' . $fileName
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('PDF file storage failed', [
                'quote_id' => $quoteId,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('PDF storage failed: ' . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * Generate professional HTML content for quote PDF with enhanced styling and formatting.
     * 
     * @param array $quote Complete quote data including metadata and line items
     * @return string Professional HTML content ready for PDF conversion
     */
    private function generateProfessionalQuoteHTML(array $quote): string {
        $quoteData = $quote['quote_data'];
        $markups = $quote['markups'];
        $customerInfo = $quote['customer_info'] ?? null;
        
        // Start building professional HTML
        $html = $this->buildHTMLHeader($quote);
        $html .= $this->buildQuoteMetadata($quote, $customerInfo);
        
        $grandTotal = 0;
        $sectionsWithItems = [];
        
        // Process each section and build content
        foreach ($quoteData as $sectionKey => $items) {
            if (!empty($items) && is_array($items)) {
                $sectionResult = $this->buildSectionHTML($sectionKey, $items, $markups);
                if ($sectionResult['hasItems']) {
                    $sectionsWithItems[] = $sectionResult['html'];
                    $grandTotal += $sectionResult['sectionTotal'];
                }
            }
        }
        
        // Add all sections with items
        $html .= implode('', $sectionsWithItems);
        
        // Add totals and footer
        $html .= $this->buildTotalsSection($grandTotal);
        $html .= $this->buildQuoteFooter($markups);
        $html .= '</body></html>';
        
        return $html;
    }
    
    /**
     * Build professional HTML header with enhanced CSS styling.
     * 
     * @param array $quote Quote data for title generation
     * @return string HTML header content
     */
    private function buildHTMLHeader(array $quote): string {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Door & Hardware Quote #' . $quote['id'] . '</title>
    <style>
        body { 
            font-family: "Helvetica Neue", Arial, sans-serif; 
            margin: 0; 
            padding: 20px; 
            color: #2d3748; 
            line-height: 1.4;
        }
        .quote-header { 
            text-align: center; 
            margin-bottom: 30px; 
            border-bottom: 3px solid #2c5282; 
            padding-bottom: 20px; 
        }
        .company-name { 
            font-size: 28px; 
            font-weight: bold; 
            color: #2c5282; 
            margin-bottom: 5px;
        }
        .company-tagline { 
            font-size: 14px; 
            color: #4a5568; 
            font-style: italic;
        }
        .quote-metadata { 
            background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%); 
            padding: 20px; 
            border-radius: 8px; 
            margin-bottom: 25px; 
            border-left: 4px solid #2c5282;
        }
        .metadata-row { 
            display: table; 
            width: 100%; 
            margin-bottom: 8px; 
        }
        .metadata-label { 
            display: table-cell; 
            font-weight: bold; 
            width: 120px; 
            color: #2d3748;
        }
        .metadata-value { 
            display: table-cell; 
            color: #4a5568;
        }
        .section { 
            margin-bottom: 30px; 
            page-break-inside: avoid;
        }
        .section-title { 
            font-weight: bold; 
            font-size: 18px; 
            margin-bottom: 12px; 
            color: #2c5282; 
            border-bottom: 2px solid #e2e8f0; 
            padding-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .items-table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-bottom: 15px; 
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .items-table th { 
            background: linear-gradient(135deg, #2c5282 0%, #2a4365 100%); 
            color: white; 
            font-weight: bold; 
            padding: 12px 8px; 
            text-align: left; 
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .items-table td { 
            border: 1px solid #e2e8f0; 
            padding: 10px 8px; 
            font-size: 11px;
        }
        .items-table tr:nth-child(even) { 
            background-color: #f8f9fa; 
        }
        .items-table tr:hover { 
            background-color: #e6fffa; 
        }
        .price-cell { 
            text-align: right; 
            font-family: "Courier New", monospace; 
            font-weight: 500;
        }
        .section-total-row { 
            background: linear-gradient(135deg, #e6fffa 0%, #b2f5ea 100%); 
            font-weight: bold; 
            border-top: 2px solid #38b2ac;
        }
        .grand-total-section { 
            margin-top: 30px; 
            padding: 20px; 
            background: linear-gradient(135deg, #bee3f8 0%, #90cdf4 100%); 
            border-radius: 8px; 
            border: 2px solid #2c5282;
        }
        .grand-total-row { 
            font-weight: bold; 
            font-size: 20px; 
            color: #1a365d;
        }
        .quote-footer { 
            margin-top: 40px; 
            padding: 20px; 
            background: #f7fafc; 
            border-radius: 8px; 
            border-left: 4px solid #38b2ac;
        }
        .markup-info { 
            font-size: 11px; 
            color: #4a5568; 
            margin-bottom: 15px;
        }
        .terms { 
            font-size: 10px; 
            color: #718096; 
            font-style: italic; 
            line-height: 1.3;
        }
        .page-break { 
            page-break-before: always; 
        }
    </style>
</head>
<body>';
    }
    
    /**
     * Build quote metadata section with customer info and quote details.
     * 
     * @param array $quote Quote data
     * @param array|null $customerInfo Customer information if available
     * @return string HTML metadata section
     */
    private function buildQuoteMetadata(array $quote, ?array $customerInfo): string {
        $html = '<div class="quote-header">
            <div class="company-name">Millwork Products LLC</div>
            <div class="company-tagline">Professional Door & Hardware Solutions</div>
        </div>
        
        <div class="quote-metadata">
            <div class="metadata-row">
                <div class="metadata-label">Quote Number:</div>
                <div class="metadata-value">#' . $quote['id'] . '</div>
            </div>
            <div class="metadata-row">
                <div class="metadata-label">Quote Date:</div>
                <div class="metadata-value">' . date('F j, Y', strtotime($quote['created_at'])) . '</div>
            </div>
            <div class="metadata-row">
                <div class="metadata-label">Quote Name:</div>
                <div class="metadata-value">' . htmlspecialchars($quote['quote_name']) . '</div>
            </div>';
            
        if ($customerInfo && is_array($customerInfo)) {
            if (!empty($customerInfo['name'])) {
                $html .= '<div class="metadata-row">
                    <div class="metadata-label">Customer:</div>
                    <div class="metadata-value">' . htmlspecialchars($customerInfo['name']) . '</div>
                </div>';
            }
            if (!empty($customerInfo['company'])) {
                $html .= '<div class="metadata-row">
                    <div class="metadata-label">Company:</div>
                    <div class="metadata-value">' . htmlspecialchars($customerInfo['company']) . '</div>
                </div>';
            }
            if (!empty($customerInfo['email'])) {
                $html .= '<div class="metadata-row">
                    <div class="metadata-label">Email:</div>
                    <div class="metadata-value">' . htmlspecialchars($customerInfo['email']) . '</div>
                </div>';
            }
            if (!empty($customerInfo['phone'])) {
                $html .= '<div class="metadata-row">
                    <div class="metadata-label">Phone:</div>
                    <div class="metadata-value">' . htmlspecialchars($customerInfo['phone']) . '</div>
                </div>';
            }
        }
        
        $html .= '<div class="metadata-row">
                <div class="metadata-label">Total Amount:</div>
                <div class="metadata-value" style="font-weight: bold; color: #2c5282;">$' . number_format($quote['total_amount'], 2) . '</div>
            </div>
        </div>';
        
        return $html;
    }
    
    /**
     * Build HTML for a single section with items, calculations, and formatting.
     * 
     * @param string $sectionKey Section identifier
     * @param array $items Array of line items
     * @param array $markups Markup percentages
     * @return array Result with HTML content, section total, and hasItems flag
     */
    private function buildSectionHTML(string $sectionKey, array $items, array $markups): array {
        $sectionName = EstimatorUtils::formatSectionName($sectionKey);
        $sectionSubtotal = 0;
        $hasItems = false;
        $itemsHTML = '';
        
        // Process each item in the section
        foreach ($items as $item) {
            if (isset($item['qty']) && $item['qty'] > 0) {
                $hasItems = true;
                $itemTotal = ($item['qty'] ?? 0) * ($item['price'] ?? 0);
                $sectionSubtotal += $itemTotal;
                
                $itemsHTML .= '<tr>
                    <td>' . htmlspecialchars($item['item'] ?? '') . '</td>
                    <td class="price-cell">' . number_format($item['qty'] ?? 0) . '</td>
                    <td class="price-cell">$' . number_format($item['price'] ?? 0, 2) . '</td>
                    <td class="price-cell">$' . number_format($itemTotal, 2) . '</td>
                </tr>';
            }
        }
        
        if (!$hasItems) {
            return ['html' => '', 'sectionTotal' => 0, 'hasItems' => false];
        }
        
        // Calculate section total with markup
        $markup = EstimatorUtils::getSectionMarkup($sectionKey, $markups);
        $sectionTotal = $sectionSubtotal * (1 + $markup / 100);
        
        // Build complete section HTML
        $html = '<div class="section">
            <div class="section-title">' . $sectionName . '</div>
            <table class="items-table">
                <thead>
                    <tr>
                        <th style="width: 50%;">Item Description</th>
                        <th style="width: 15%;">Quantity</th>
                        <th style="width: 17.5%;">Unit Price</th>
                        <th style="width: 17.5%;">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    ' . $itemsHTML . '
                    <tr class="section-total-row">
                        <td colspan="3"><strong>Section Total (with ' . number_format($markup, 1) . '% markup)</strong></td>
                        <td class="price-cell"><strong>$' . number_format($sectionTotal, 2) . '</strong></td>
                    </tr>
                </tbody>
            </table>
        </div>';
        
        return ['html' => $html, 'sectionTotal' => $sectionTotal, 'hasItems' => true];
    }
    
    /**
     * Build the grand total section with professional formatting.
     * 
     * @param float $grandTotal Calculated grand total
     * @return string HTML for totals section
     */
    private function buildTotalsSection(float $grandTotal): string {
        return '<div class="grand-total-section">
            <table class="items-table">
                <tr class="grand-total-row">
                    <td colspan="3" style="text-align: center; padding: 15px;"><strong>GRAND TOTAL</strong></td>
                    <td class="price-cell" style="padding: 15px;"><strong>$' . number_format($grandTotal, 2) . '</strong></td>
                </tr>
            </table>
        </div>';
    }
    
    /**
     * Build quote footer with markup information and terms.
     * 
     * @param array $markups Applied markup percentages
     * @return string HTML footer content
     */
    private function buildQuoteFooter(array $markups): string {
        return '<div class="quote-footer">
            <div class="markup-info">
                <p><strong>Markup Schedule Applied:</strong></p>
                <ul style="margin: 5px 0; padding-left: 20px;">
                    <li>Doors & Glass Inserts: ' . number_format($markups['doors'] ?? 15, 1) . '%</li>
                    <li>Frames & Frame Options: ' . number_format($markups['frames'] ?? 12, 1) . '%</li>
                    <li>Hardware & Accessories: ' . number_format($markups['hardware'] ?? 18, 1) . '%</li>
                </ul>
            </div>
            <div class="terms">
                <p><strong>Terms and Conditions:</strong></p>
                <ul style="margin: 5px 0; padding-left: 20px; line-height: 1.4;">
                    <li>This quote is valid for 30 days from the date of issue</li>
                    <li>Prices are subject to change without notice</li>
                    <li>All materials are subject to availability</li>
                    <li>Installation and delivery charges are not included unless specified</li>
                    <li>Payment terms: Net 30 days from invoice date</li>
                </ul>
                <p style="margin-top: 15px; text-align: center; font-weight: bold;">
                    Thank you for choosing Millwork Products LLC for your door and hardware needs!
                </p>
            </div>
        </div>';
    }
    
    /**
     * Get PDF file content from app data storage.
     * 
     * @param string $fileName PDF file name
     * @return string|null PDF content as binary string or null if not found
     */
    public function getPDFFileContent(string $fileName): ?string {
        try {
            // Validate filename format for security
            if (!preg_match('/^quote_\d+_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.pdf$/', $fileName)) {
                $this->logger->warning('Invalid PDF filename format', ['file_name' => $fileName]);
                return null;
            }
            
            $folder = $this->appData->getFolder('quotes');
            $file = $folder->getFile($fileName);
            
            $content = $file->getContent();
            
            $this->logger->debug('PDF file content retrieved', [
                'file_name' => $fileName,
                'file_size' => strlen($content)
            ]);
            
            return $content;
            
        } catch (NotFoundException $e) {
            $this->logger->info('PDF file not found', ['file_name' => $fileName]);
            return null;
        } catch (\Exception $e) {
            $this->logger->error('Failed to retrieve PDF file content', [
                'file_name' => $fileName,
                'error' => $e->getMessage()
            ]);
            return null;
        }
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
            // Simple content sniff to reduce obvious mislabels
            $starts = substr(ltrim((string)$jsonContent), 0, 1);
            if ($starts !== '{' && $starts !== '[') {
                return ['imported' => 0, 'errors' => ['Invalid JSON file content']];
            }
            $data = json_decode($jsonContent, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return ['imported' => 0, 'errors' => ['Invalid JSON file']];
            }
            
            // Validate and process 'markups' key
            if (!isset($data['markups']) || !is_array($data['markups'])) {
                // Normalize to default empty array to keep import going, but report
                $rowErrors[] = "Missing or invalid 'markups' key; using defaults";
            } else {
                // Validate markup structure
                $validMarkups = true;
                foreach ($data['markups'] as $type => $value) {
                    if (!is_string($type) || !is_numeric($value)) {
                        $rowErrors[] = "Invalid markup entry: type must be string, value must be numeric";
                        $validMarkups = false;
                        break;
                    }
                }
                
                // If markups are valid, update them
                if ($validMarkups) {
                    $this->updateDefaultMarkups($data['markups']);
                }
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
            } elseif ($fileType === 'application/vnd.ms-excel') {
                $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xls();
                $spreadsheet = $reader->load($tmpName);
            } elseif ($fileType === 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet') {
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
     * Export all pricing data and markup settings to JSON format.
     * 
     * @return array JSON structure with pricingData and markups keys
     * @throws \Exception if export fails
     */
    public function exportPricingData(): array {
        try {
            $this->logger->info('Starting pricing data export');
            
            // Get all pricing data
            $pricingData = $this->getAllPricingData();
            
            // Transform pricing data to export format
            $exportPricingData = [];
            foreach ($pricingData as $item) {
                $exportPricingData[] = [
                    'id' => $item['id'],
                    'category' => $item['category'],
                    'subcategory' => $item['subcategory'] ?? null,
                    'item' => $item['item'],
                    'price' => (float)$item['price'],
                    'stock_status' => $item['stock_status'] ?? 'stock',
                    'description' => $item['description'] ?? ''
                ];
            }
            
            // Get current markup settings
            $markups = $this->getDefaultMarkups();
            
            $exportData = [
                'pricingData' => $exportPricingData,
                'markups' => $markups,
                'exportedAt' => date('Y-m-d H:i:s'),
                'version' => '1.0'
            ];
            
            $this->logger->info('Pricing data export completed', [
                'items_count' => count($exportPricingData),
                'markups' => $markups
            ]);
            
            return $exportData;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to export pricing data', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Check if any pricing data exists in the database.
     * @return bool True if at least one pricing item exists, false otherwise.
     */
    public function isPricingDataPresent(): bool {
        try {
            $qb = $this->db->getQueryBuilder();
            $qb->select('id')
                ->from('door_estimator_pricing')
                ->setMaxResults(1);
            $result = $qb->executeQuery();
            $row = $result->fetch();
            return $row !== false;
        } catch (\Exception $e) {
            $this->logger->error('Failed to check pricing data presence', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Validate input parameters for price lookup operations.
     * 
     * @param string $category Product category
     * @param string $item Item name
     * @param string|null $frameType Optional frame type
     * @throws ValidationException if parameters are invalid
     */
    private function validatePriceLookupInput(string $category, string $item, ?string $frameType = null): void {
        $errors = [];
        
        if (empty(trim($category))) {
            $errors['category'] = 'Category cannot be empty';
        } elseif (strlen($category) > 50) {
            $errors['category'] = 'Category cannot exceed 50 characters';
        }
        
        if (empty(trim($item))) {
            $errors['item'] = 'Item name cannot be empty';
        } elseif (strlen($item) > 255) {
            $errors['item'] = 'Item name cannot exceed 255 characters';
        }
        
        if ($frameType !== null && strlen($frameType) > 100) {
            $errors['frameType'] = 'Frame type cannot exceed 100 characters';
        }
        
        if (!empty($errors)) {
            throw new ValidationException('Invalid price lookup parameters', $errors);
        }
    }

    /**
     * Validate pricing item data for create/update operations.
     * 
     * @param array $data Pricing item data
     * @throws ValidationException if data is invalid
     */
    private function validatePricingItemData(array $data): void {
        $errors = [];
        
        // Required fields validation
        if (!isset($data['item']) || !is_string($data['item']) || empty(trim($data['item']))) {
            $errors['item'] = 'Item name is required and must be a non-empty string';
        } elseif (strlen($data['item']) > 255) {
            $errors['item'] = 'Item name cannot exceed 255 characters';
        }
        
        if (!isset($data['price']) || !is_numeric($data['price'])) {
            $errors['price'] = 'Price is required and must be numeric';
        } elseif ((float)$data['price'] < 0) {
            $errors['price'] = 'Price must be a positive number';
        }
        
        if (!isset($data['category']) || !is_string($data['category']) || empty(trim($data['category']))) {
            $errors['category'] = 'Category is required and must be a non-empty string';
        } elseif (strlen($data['category']) > 50) {
            $errors['category'] = 'Category cannot exceed 50 characters';
        }
        
        // Optional fields validation
        if (isset($data['subcategory']) && strlen($data['subcategory']) > 100) {
            $errors['subcategory'] = 'Subcategory cannot exceed 100 characters';
        }
        
        if (isset($data['description']) && strlen($data['description']) > 2000) {
            $errors['description'] = 'Description cannot exceed 2000 characters';
        }
        
        if (isset($data['stock_status']) && !in_array($data['stock_status'], ['stock', 'out_of_stock', 'discontinued'])) {
            $errors['stock_status'] = 'Stock status must be one of: stock, out_of_stock, discontinued';
        }
        
        if (isset($data['id']) && (!is_numeric($data['id']) || (int)$data['id'] <= 0)) {
            $errors['id'] = 'ID must be a positive integer';
        }
        
        if (!empty($errors)) {
            throw new ValidationException('Invalid pricing item data', $errors);
        }
    }

    /**
     * Sanitize pricing item data to prevent XSS and ensure data integrity.
     * 
     * @param array $data Raw pricing item data
     * @return array Sanitized data
     */
    private function sanitizePricingItemData(array $data): array {
        $sanitized = [];
        
        $sanitized['item'] = substr(trim(strip_tags((string)$data['item'])), 0, 255);
        $sanitized['price'] = (float)$data['price'];
        $sanitized['category'] = substr(trim(strip_tags((string)$data['category'])), 0, 50);
        
        if (isset($data['subcategory'])) {
            $sanitized['subcategory'] = substr(trim(strip_tags((string)$data['subcategory'])), 0, 100);
        }
        
        if (isset($data['description'])) {
            $sanitized['description'] = substr(strip_tags((string)$data['description']), 0, 2000);
        }
        
        if (isset($data['stock_status'])) {
            $sanitized['stock_status'] = trim(strip_tags((string)$data['stock_status']));
        }
        
        if (isset($data['id'])) {
            $sanitized['id'] = (int)$data['id'];
        }
        
        return $sanitized;
    }

    /**
     * Validate quote data structure and content.
     * 
     * @param array $quoteData Quote data organized by sections
     * @throws ValidationException if quote data is invalid
     */
    private function validateQuoteData(array $quoteData): void {
        $errors = [];
        $validSections = [
            'doors', 'doorOptions', 'inserts', 'frames', 'frameOptions',
            'hinges', 'weatherstrip', 'closers', 'locksets', 'exitDevices', 'hardware'
        ];
        
        foreach ($quoteData as $sectionKey => $items) {
            if (!in_array($sectionKey, $validSections)) {
                $errors[$sectionKey] = "Invalid section key: {$sectionKey}";
                continue;
            }
            
            if (!is_array($items)) {
                $errors[$sectionKey] = "Section {$sectionKey} must be an array";
                continue;
            }
            
            foreach ($items as $index => $item) {
                if (!is_array($item)) {
                    $errors["{$sectionKey}[{$index}]"] = "Item must be an array";
                    continue;
                }
                
                // Validate required item fields
                if (!isset($item['item']) || !is_string($item['item'])) {
                    $errors["{$sectionKey}[{$index}].item"] = "Item name is required and must be a string";
                }
                
                if (!isset($item['qty']) || !is_numeric($item['qty']) || (int)$item['qty'] < 0) {
                    $errors["{$sectionKey}[{$index}].qty"] = "Quantity must be a non-negative number";
                }
                
                if (!isset($item['price']) || !is_numeric($item['price']) || (float)$item['price'] < 0) {
                    $errors["{$sectionKey}[{$index}].price"] = "Price must be a non-negative number";
                }
            }
        }
        
        if (!empty($errors)) {
            throw new ValidationException('Invalid quote data', $errors);
        }
    }

    /**
     * Validate markup configuration.
     * 
     * @param array $markups Markup percentages by section
     * @throws ValidationException if markups are invalid
     */
    private function validateMarkups(array $markups): void {
        $errors = [];
        $requiredMarkups = ['doors', 'frames', 'hardware'];
        
        foreach ($requiredMarkups as $section) {
            if (!isset($markups[$section])) {
                $errors[$section] = "Markup for {$section} is required";
            } elseif (!is_numeric($markups[$section])) {
                $errors[$section] = "Markup for {$section} must be numeric";
            } elseif ((float)$markups[$section] < 0 || (float)$markups[$section] > 100) {
                $errors[$section] = "Markup for {$section} must be between 0 and 100";
            }
        }
        
        if (!empty($errors)) {
            throw new ValidationException('Invalid markup configuration', $errors);
        }
    }

    /**
     * Sanitize quote name to prevent XSS and ensure length limits.
     * 
     * @param string|null $quoteName Raw quote name
     * @return string Sanitized quote name
     */
    private function sanitizeQuoteName(?string $quoteName): string {
        if ($quoteName === null || trim($quoteName) === '') {
            return 'Quote ' . date('Y-m-d H:i:s');
        }
        
        return substr(trim(strip_tags((string)$quoteName)), 0, 255);
    }

    /**
     * Sanitize customer info to prevent XSS while preserving structure.
     * 
     * @param mixed $customerInfo Raw customer info
     * @return mixed Sanitized customer info
     */
    private function sanitizeCustomerInfo($customerInfo) {
        if ($customerInfo === null) {
            return null;
        }
        
        if (is_string($customerInfo)) {
            return substr(strip_tags($customerInfo), 0, 1000);
        }
        
        if (is_array($customerInfo)) {
            $sanitized = [];
            foreach ($customerInfo as $key => $value) {
                if (is_string($value)) {
                    $sanitized[strip_tags($key)] = substr(strip_tags($value), 0, 500);
                } elseif (is_numeric($value)) {
                    $sanitized[strip_tags($key)] = $value;
                }
            }
            return $sanitized;
        }
        
        return null;
    }

    /**
     * Check if a user owns a specific quote
     * 
     * @param int $quoteId Quote ID to check
     * @param string $userId User ID to check ownership for
     * @return bool True if user owns the quote, false otherwise
     */
    public function userOwnsQuote(int $quoteId, string $userId): bool
    {
        try {
            $quote = $this->repository->getQuote($quoteId);
            return $quote && $quote['user_id'] === $userId;
        } catch (QuoteNotFoundException $e) {
            return false;
        } catch (\Throwable $e) {
            $this->logger->error('Error checking quote ownership', [
                'quote_id' => $quoteId,
                'user_id' => $userId,
                'exception' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get current authenticated user ID
     * 
     * @return string|null User ID or null if not authenticated
     */
    public function getCurrentUserId(): ?string
    {
        $user = $this->userSession->getUser();
        return $user ? $user->getUID() : null;
    }

    /**
     * Validate user has access to perform operations
     * 
     * @throws AuthorizationException If user is not authenticated
     */
    public function validateUserAccess(): void
    {
        $user = $this->userSession->getUser();
        if (!$user) {
            throw AuthorizationException::forResourceAccess('application', 'access');
        }
    }

    /**
     * Check if current user is an administrator
     * 
     * @return bool True if user is admin, false otherwise
     */
    public function isCurrentUserAdmin(): bool
    {
        $user = $this->userSession->getUser();
        if (!$user) {
            return false;
        }

        // Check if user is in admin group
        $groupManager = \OC::$server->getGroupManager();
        return $groupManager->isAdmin($user->getUID());
    }
}