<?php
declare(strict_types=1);

namespace OCA\DoorEstimator\Controller;

use OCP\IRequest;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\DataResponse;
use OCA\DoorEstimator\Service\EstimatorService;
use Psr\Log\LoggerInterface;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\AdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\ICacheFactory;
use OCP\IUserSession;
use OCA\DoorEstimator\Exception\ValidationException;
use OCA\DoorEstimator\Exception\QuoteNotFoundException;
use OCA\DoorEstimator\Exception\PricingItemNotFoundException;
use OCA\DoorEstimator\Exception\AuthorizationException;
use OCA\DoorEstimator\Exception\BaseException;
use OCA\DoorEstimator\Service\ErrorHandlerService;
use OCA\DoorEstimator\Service\RetryService;
use OCA\DoorEstimator\Service\AuthService;
use OCP\AppFramework\Http\ContentSecurityPolicy;

/**
 * RESTful API Controller for Door Estimator Application
 * 
 * Provides comprehensive API endpoints for pricing management, quote operations,
 * and data import/export with proper authentication, validation, and error handling.
 * 
 * @OpenAPI\Tag(name="estimator", description="Door Estimator API")
 */
class EstimatorController extends Controller
{
    private EstimatorService $estimatorService;
    private LoggerInterface $logger;
    private ICacheFactory $cacheFactory;
    private IUserSession $userSession;
    private ErrorHandlerService $errorHandler;
    private RetryService $retryService;
    private AuthService $authService;

    public function __construct(
        string $AppName,
        IRequest $request,
        EstimatorService $estimatorService,
        LoggerInterface $logger,
        ICacheFactory $cacheFactory,
        IUserSession $userSession,
        ErrorHandlerService $errorHandler,
        RetryService $retryService,
        AuthService $authService
    ) {
        parent::__construct($AppName, $request);
        $this->estimatorService = $estimatorService;
        $this->logger = $logger;
        $this->errorHandler = $errorHandler;
        $this->retryService = $retryService;
        $this->cacheFactory = $cacheFactory;
        $this->userSession = $userSession;
        $this->authService = $authService;
        
        // Set Content Security Policy for all responses
        $this->setContentSecurityPolicy();
    }
    
    /**
     * Set Content Security Policy headers for XSS prevention
     */
    private function setContentSecurityPolicy(): void
    {
        $csp = new ContentSecurityPolicy();
        $csp->addAllowedScriptDomain("'self'");
        $csp->addAllowedScriptDomain("'unsafe-eval'"); // Required for Vue.js
        $csp->addAllowedStyleDomain("'self'");
        $csp->addAllowedStyleDomain("'unsafe-inline'"); // Required for dynamic styles
        $csp->addAllowedImageDomain("'self'");
        $csp->addAllowedImageDomain("data:");
        $csp->addAllowedConnectDomain("'self'");
        $csp->addAllowedFontDomain("'self'");
        $csp->addAllowedObjectDomain("'none'");
        $csp->addAllowedMediaDomain("'self'");
        $csp->addAllowedFrameDomain("'none'");
        
        // Apply CSP to all responses
        $this->response->setContentSecurityPolicy($csp);
    }

    /**
     * Check if the current user is an administrator
     * 
     * @return bool True if user is admin, false otherwise
     */
    private function isAdmin(): bool
    {
        return $this->authService->isAdmin();
    }

    /**
     * Validate user session and ensure user is authenticated
     * 
     * @throws AuthorizationException If user is not authenticated
     */
    private function validateUserSession(): void
    {
        $this->authService->validateUserSession();
    }

    /**
     * Ensure current user has admin privileges
     * 
     * @throws AuthorizationException If user is not admin
     */
    private function requireAdmin(): void
    {
        $this->authService->requireAdmin();
    }

    /**
     * Get current authenticated user ID
     * 
     * @return string User ID
     * @throws AuthorizationException If user is not authenticated
     */
    private function getCurrentUserId(): string
    {
        return $this->authService->getCurrentUserId();
    }

    /**
     * Check if current user owns the specified quote
     * 
     * @param int $quoteId Quote ID to check ownership
     * @return bool True if user owns the quote
     */
    private function userOwnsQuote(int $quoteId): bool
    {
        try {
            $userId = $this->getCurrentUserId();
            return $this->estimatorService->userOwnsQuote($quoteId, $userId);
        } catch (AuthorizationException $e) {
            return false;
        }
    }

    /**
     * Ensure current user owns the specified quote
     * 
     * @param int $quoteId Quote ID to check ownership
     * @throws AuthorizationException If user doesn't own the quote
     */
    private function requireQuoteOwnership(int $quoteId): void
    {
        // Use AuthService permission checking
        $this->authService->requirePermission('quote', 'access', [
            'quote_id' => $quoteId,
            'owner_id' => $this->userOwnsQuote($quoteId) ? $this->getCurrentUserId() : null
        ]);
    }

    /**
     * Log security event for audit purposes
     * 
     * @param string $event Event type
     * @param array $context Additional context
     */
    private function logSecurityEvent(string $event, array $context = []): void
    {
        $userContext = $this->authService->getUserContext();
        $fullContext = array_merge($userContext, $context, [
            'event' => $event,
            'timestamp' => time()
        ]);
        
        $this->logger->info("Security event: {$event}", $fullContext);
    }
    
    /**
     * Get all pricing data
     * 
     * @OpenAPI\Get(
     *     path="/api/pricing",
     *     summary="Retrieve all pricing items",
     *     tags={"estimator"},
     *     @OpenAPI\Response(
     *         response=200,
     *         description="List of all pricing items",
     *         @OpenAPI\JsonContent(type="array", @OpenAPI\Items(ref="#/components/schemas/PricingItem"))
     *     ),
     *     @OpenAPI\Response(response=401, description="Unauthorized"),
     *     @OpenAPI\Response(response=500, description="Internal server error")
     * )
     */
    #[NoAdminRequired]
    #[ApiRoute(verb: 'GET', url: '/api/pricing')]
    #[OpenAPI(tags: ['estimator'])]
    public function getAllPricingData(): JSONResponse
    {
        try {
            // Validate user session
            $userId = $this->getCurrentUserId();
            
            $this->errorHandler->logOperation('get_all_pricing_data', [
                'user_id' => $userId
            ]);

            $startTime = microtime(true);
            
            $data = $this->retryService->executeWithRetry(
                fn() => $this->estimatorService->getAllPricingData(),
                'get_all_pricing_data'
            );

            $this->errorHandler->logPerformance(
                'get_all_pricing_data',
                microtime(true) - $startTime,
                ['item_count' => count($data)]
            );

            return $this->errorHandler->createSuccessResponse($data);
        } catch (AuthorizationException $e) {
            return $this->errorHandler->handleException($e);
        } catch (\Throwable $e) {
            return $this->errorHandler->handleException($e);
        }
    }
    
    /**
     * Get pricing data by category
     * 
     * @OpenAPI\Get(
     *     path="/api/pricing/{category}",
     *     summary="Retrieve pricing items for a specific category",
     *     tags={"estimator"},
     *     @OpenAPI\Parameter(
     *         name="category",
     *         in="path",
     *         required=true,
     *         description="Product category",
     *         @OpenAPI\Schema(type="string")
     *     ),
     *     @OpenAPI\Response(
     *         response=200,
     *         description="List of pricing items for the category",
     *         @OpenAPI\JsonContent(type="array", @OpenAPI\Items(ref="#/components/schemas/PricingItem"))
     *     ),
     *     @OpenAPI\Response(response=400, description="Invalid category"),
     *     @OpenAPI\Response(response=401, description="Unauthorized"),
     *     @OpenAPI\Response(response=500, description="Internal server error")
     * )
     */
    #[NoAdminRequired]
    #[ApiRoute(verb: 'GET', url: '/api/pricing/{category}')]
    #[OpenAPI(tags: ['estimator'])]
    public function getPricingByCategory(string $category): JSONResponse
    {
        try {
            // Validate user session
            $this->validateUserSession();
            
            // Input validation
            if (empty(trim($category)) || strlen($category) > 50) {
                return $this->errorResponse('VALIDATION_ERROR', 'Invalid category parameter', 400);
            }

            $data = $this->estimatorService->getPricingByCategory($category);
            return new JSONResponse([
                'success' => true,
                'data' => $data,
                'category' => $category
            ]);
        } catch (AuthorizationException $e) {
            return $this->errorHandler->handleException($e);
        } catch (\Throwable $e) {
            $this->logger->error('getPricingByCategory failed', [
                'category' => $category,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->errorResponse('SERVER_ERROR', 'Failed to retrieve pricing data for category', 500);
        }
    }
    
    /**
     * Create or update a pricing item
     * 
     * @OpenAPI\Post(
     *     path="/api/pricing",
     *     summary="Create or update a pricing item",
     *     tags={"estimator"},
     *     @OpenAPI\RequestBody(
     *         required=true,
     *         @OpenAPI\JsonContent(
     *             required={"category", "item", "price"},
     *             @OpenAPI\Property(property="id", type="integer", description="Item ID for updates (optional for new items)"),
     *             @OpenAPI\Property(property="category", type="string", maxLength=50, description="Product category"),
     *             @OpenAPI\Property(property="subcategory", type="string", maxLength=100, description="Product subcategory"),
     *             @OpenAPI\Property(property="item", type="string", maxLength=255, description="Item name"),
     *             @OpenAPI\Property(property="price", type="number", minimum=0, maximum=1000000, description="Item price"),
     *             @OpenAPI\Property(property="description", type="string", description="Item description")
     *         )
     *     ),
     *     @OpenAPI\Response(
     *         response=200,
     *         description="Pricing item updated successfully",
     *         @OpenAPI\JsonContent(
     *             @OpenAPI\Property(property="success", type="boolean"),
     *             @OpenAPI\Property(property="id", type="integer", description="Item ID")
     *         )
     *     ),
     *     @OpenAPI\Response(response=400, description="Invalid input data"),
     *     @OpenAPI\Response(response=401, description="Unauthorized"),
     *     @OpenAPI\Response(response=403, description="Admin access required"),
     *     @OpenAPI\Response(response=500, description="Internal server error")
     * )
     */
    #[AdminRequired]
    #[ApiRoute(verb: 'POST', url: '/api/pricing')]
    #[OpenAPI(tags: ['estimator'])]
    public function updatePricingItem(): JSONResponse
    {
        try {
            // Require admin privileges
            $this->requireAdmin();
            
            $data = $this->request->getParams();
            
            // Comprehensive input validation
            $validationErrors = $this->validatePricingItemInput($data);
            if (!empty($validationErrors)) {
                return $this->errorResponse('VALIDATION_ERROR', 'Invalid input data', 400, [
                    'details' => $validationErrors
                ]);
            }

            $result = $this->estimatorService->updatePricingItem($data);
            
            $this->logSecurityEvent('pricing_item_updated', [
                'item_id' => $data['id'] ?? null,
                'category' => $data['category'] ?? null,
                'item_name' => $data['item'] ?? null
            ]);
            
            return new JSONResponse([
                'success' => true,
                'id' => $data['id'] ?? null,
                'message' => 'Pricing item updated successfully'
            ]);
        } catch (AuthorizationException $e) {
            return $this->errorHandler->handleException($e);
        } catch (ValidationException $e) {
            return $this->errorResponse('VALIDATION_ERROR', $e->getMessage(), 400);
        } catch (\Throwable $e) {
            $this->logger->error('updatePricingItem failed', [
                'data' => $data ?? [],
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->errorResponse('SERVER_ERROR', 'Failed to update pricing item', 500);
        }
    }
    
    /**
     * Lookup price for a specific item
     * 
     * @OpenAPI\Post(
     *     path="/api/lookup-price",
     *     summary="Lookup price for a specific item",
     *     tags={"estimator"},
     *     @OpenAPI\RequestBody(
     *         required=true,
     *         @OpenAPI\JsonContent(
     *             required={"category", "item"},
     *             @OpenAPI\Property(property="category", type="string", description="Product category"),
     *             @OpenAPI\Property(property="item", type="string", description="Item name"),
     *             @OpenAPI\Property(property="frameType", type="string", description="Frame type (for frame items)")
     *         )
     *     ),
     *     @OpenAPI\Response(
     *         response=200,
     *         description="Price lookup successful",
     *         @OpenAPI\JsonContent(
     *             @OpenAPI\Property(property="success", type="boolean"),
     *             @OpenAPI\Property(property="price", type="number", description="Item price"),
     *             @OpenAPI\Property(property="category", type="string"),
     *             @OpenAPI\Property(property="item", type="string"),
     *             @OpenAPI\Property(property="frameType", type="string", nullable=true)
     *         )
     *     ),
     *     @OpenAPI\Response(response=400, description="Invalid input data"),
     *     @OpenAPI\Response(response=404, description="Item not found"),
     *     @OpenAPI\Response(response=401, description="Unauthorized"),
     *     @OpenAPI\Response(response=500, description="Internal server error")
     * )
     */
    #[NoAdminRequired]
    #[ApiRoute(verb: 'POST', url: '/api/lookup-price')]
    #[OpenAPI(tags: ['estimator'])]
    public function lookupPrice(): JSONResponse
    {
        try {
            // Validate user session
            $this->validateUserSession();
            
            $category = $this->request->getParam('category');
            $item = $this->request->getParam('item');
            $frameType = $this->request->getParam('frameType');
            
            // Input validation
            if (
                !is_string($category) || trim($category) === '' ||
                !is_string($item) || trim($item) === ''
            ) {
                return $this->errorResponse('VALIDATION_ERROR', 'Category and item are required', 400);
            }

            $price = $this->estimatorService->lookupPrice($category, $item, $frameType);
            
            return new JSONResponse([
                'success' => true,
                'price' => $price,
                'category' => $category,
                'item' => $item,
                'frameType' => $frameType
            ]);
        } catch (AuthorizationException $e) {
            return $this->errorHandler->handleException($e);
        } catch (PricingItemNotFoundException $e) {
            return $this->errorResponse('NOT_FOUND', 'Pricing item not found', 404);
        } catch (ValidationException $e) {
            return $this->errorResponse('VALIDATION_ERROR', $e->getMessage(), 400);
        } catch (\Throwable $e) {
            $this->logger->error('lookupPrice failed', [
                'category' => $category ?? null,
                'item' => $item ?? null,
                'frameType' => $frameType ?? null,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->errorResponse('SERVER_ERROR', 'Failed to lookup price', 500);
        }
    }
    
    /**
     * Create or update a quote
     * 
     * @OpenAPI\Post(
     *     path="/api/quotes",
     *     summary="Create or update a quote",
     *     tags={"estimator"},
     *     @OpenAPI\RequestBody(
     *         required=true,
     *         @OpenAPI\JsonContent(
     *             required={"quoteData", "markups"},
     *             @OpenAPI\Property(property="quoteData", type="object", description="Quote line items organized by category"),
     *             @OpenAPI\Property(property="markups", type="object", description="Markup percentages by category"),
     *             @OpenAPI\Property(property="quoteName", type="string", description="Optional quote name"),
     *             @OpenAPI\Property(property="customerInfo", type="object", description="Customer information")
     *         )
     *     ),
     *     @OpenAPI\Response(
     *         response=201,
     *         description="Quote created successfully",
     *         @OpenAPI\JsonContent(
     *             @OpenAPI\Property(property="success", type="boolean"),
     *             @OpenAPI\Property(property="quoteId", type="integer"),
     *             @OpenAPI\Property(property="message", type="string")
     *         )
     *     ),
     *     @OpenAPI\Response(response=400, description="Invalid input data"),
     *     @OpenAPI\Response(response=401, description="Unauthorized"),
     *     @OpenAPI\Response(response=500, description="Internal server error")
     * )
     */
    #[NoAdminRequired]
    #[ApiRoute(verb: 'POST', url: '/api/quotes')]
    #[OpenAPI(tags: ['estimator'])]
    public function saveQuote(): JSONResponse
    {
        try {
            // Validate user session
            $userId = $this->getCurrentUserId();
            
            $quoteData = $this->request->getParam('quoteData');
            $markups = $this->request->getParam('markups');
            $quoteName = $this->request->getParam('quoteName');
            $customerInfo = $this->request->getParam('customerInfo');
            
            $this->errorHandler->logOperation('save_quote', [
                'user_id' => $userId,
                'quote_name' => $quoteName,
                'has_customer_info' => !empty($customerInfo)
            ]);

            // Input validation
            $validationErrors = $this->validateQuoteInput($quoteData, $markups);
            if (!empty($validationErrors)) {
                throw ValidationException::forMultipleFields($validationErrors);
            }

            $startTime = microtime(true);

            // Use flexible pricing system for new quotes
            $quoteId = $this->retryService->executeWithRetry(
                fn() => $this->estimatorService->saveQuoteWithFlexiblePricing($quoteData, $markups, $quoteName, $customerInfo),
                'save_quote',
                ['quote_name' => $quoteName]
            );

            $this->errorHandler->logPerformance(
                'save_quote',
                microtime(true) - $startTime,
                ['quote_id' => $quoteId]
            );
            
            $this->logSecurityEvent('quote_saved', [
                'quote_id' => $quoteId,
                'quote_name' => $quoteName
            ]);
            
            return $this->errorHandler->createSuccessResponse([
                'quoteId' => $quoteId,
                'message' => 'Quote saved successfully'
            ], 201);
        } catch (AuthorizationException $e) {
            return $this->errorHandler->handleException($e);
        } catch (\Throwable $e) {
            return $this->errorHandler->handleException($e);
        }
    }
    
    /**
     * Get a specific quote by ID
     * 
     * @OpenAPI\Get(
     *     path="/api/quotes/{quoteId}",
     *     summary="Retrieve a specific quote",
     *     tags={"estimator"},
     *     @OpenAPI\Parameter(
     *         name="quoteId",
     *         in="path",
     *         required=true,
     *         description="Quote ID",
     *         @OpenAPI\Schema(type="integer")
     *     ),
     *     @OpenAPI\Response(
     *         response=200,
     *         description="Quote retrieved successfully",
     *         @OpenAPI\JsonContent(ref="#/components/schemas/Quote")
     *     ),
     *     @OpenAPI\Response(response=401, description="Unauthorized"),
     *     @OpenAPI\Response(response=403, description="Access denied - not your quote"),
     *     @OpenAPI\Response(response=404, description="Quote not found"),
     *     @OpenAPI\Response(response=500, description="Internal server error")
     * )
     */
    #[NoAdminRequired]
    #[ApiRoute(verb: 'GET', url: '/api/quotes/{quoteId}')]
    #[OpenAPI(tags: ['estimator'])]
    public function getQuote(int $quoteId): JSONResponse
    {
        try {
            // Validate user session
            $this->validateUserSession();
            
            // Validate quote ID
            if ($quoteId <= 0) {
                return $this->errorResponse('VALIDATION_ERROR', 'Invalid quote ID', 400);
            }

            // Ensure user owns the quote
            $this->requireQuoteOwnership($quoteId);

            // Use flexible pricing system for quote retrieval
            $quote = $this->estimatorService->getQuoteWithFlexiblePricing($quoteId);
            
            $this->logSecurityEvent('quote_accessed', ['quote_id' => $quoteId]);
            
            return new JSONResponse([
                'success' => true,
                'data' => $quote
            ]);
        } catch (AuthorizationException $e) {
            return $this->errorHandler->handleException($e);
        } catch (QuoteNotFoundException $e) {
            return $this->errorResponse('NOT_FOUND', 'Quote not found', 404);
        } catch (ValidationException $e) {
            return $this->errorResponse('VALIDATION_ERROR', $e->getMessage(), 400);
        } catch (\Throwable $e) {
            $this->logger->error('getQuote failed', [
                'quoteId' => $quoteId,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->errorResponse('SERVER_ERROR', 'Failed to retrieve quote', 500);
        }
    }
    
    /**
     * Get all quotes for the current user with optimized pagination
     * 
     * @OpenAPI\Get(
     *     path="/api/quotes",
     *     summary="Retrieve all quotes for the current user",
     *     tags={"estimator"},
     *     @OpenAPI\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Maximum number of quotes to return",
     *         @OpenAPI\Schema(type="integer", minimum=1, maximum=100, default=50)
     *     ),
     *     @OpenAPI\Parameter(
     *         name="offset",
     *         in="query",
     *         description="Number of quotes to skip",
     *         @OpenAPI\Schema(type="integer", minimum=0, default=0)
     *     ),
     *     @OpenAPI\Response(
     *         response=200,
     *         description="List of user quotes",
     *         @OpenAPI\JsonContent(
     *             @OpenAPI\Property(property="success", type="boolean"),
     *             @OpenAPI\Property(property="data", type="array", @OpenAPI\Items(ref="#/components/schemas/QuoteSummary")),
     *             @OpenAPI\Property(property="total", type="integer", description="Total number of quotes"),
     *             @OpenAPI\Property(property="limit", type="integer"),
     *             @OpenAPI\Property(property="offset", type="integer"),
     *             @OpenAPI\Property(property="hasMore", type="boolean", description="Whether more quotes are available")
     *         )
     *     ),
     *     @OpenAPI\Response(response=401, description="Unauthorized"),
     *     @OpenAPI\Response(response=500, description="Internal server error")
     * )
     */
    #[NoAdminRequired]
    #[ApiRoute(verb: 'GET', url: '/api/quotes')]
    #[OpenAPI(tags: ['estimator'])]
    public function getUserQuotes(): JSONResponse
    {
        try {
            // Validate user session and get user ID
            $userId = $this->getCurrentUserId();
            
            $limit = min(100, max(1, (int)($this->request->getParam('limit') ?? 50)));
            $offset = max(0, (int)($this->request->getParam('offset') ?? 0));

            // Use optimized service method with built-in pagination and user isolation
            $result = $this->estimatorService->getUserQuotes($limit, $offset);
            
            $this->logSecurityEvent('quotes_listed', [
                'count' => count($result['quotes']),
                'total' => $result['total']
            ]);
            
            return new JSONResponse([
                'success' => true,
                'data' => $result['quotes'],
                'total' => $result['total'],
                'limit' => $result['limit'],
                'offset' => $result['offset'],
                'hasMore' => $result['hasMore']
            ]);
        } catch (AuthorizationException $e) {
            return $this->errorHandler->handleException($e);
        } catch (\Throwable $e) {
            $this->logger->error('getUserQuotes failed', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->errorResponse('SERVER_ERROR', 'Failed to retrieve quotes', 500);
        }
    }
    
    /**
     * Generate PDF for a quote
     * 
     * @OpenAPI\Get(
     *     path="/api/quotes/{quoteId}/pdf",
     *     summary="Generate PDF document for a quote",
     *     tags={"estimator"},
     *     @OpenAPI\Parameter(
     *         name="quoteId",
     *         in="path",
     *         required=true,
     *         description="Quote ID",
     *         @OpenAPI\Schema(type="integer")
     *     ),
     *     @OpenAPI\Response(
     *         response=200,
     *         description="PDF generated successfully",
     *         @OpenAPI\JsonContent(
     *             @OpenAPI\Property(property="success", type="boolean"),
     *             @OpenAPI\Property(property="pdfPath", type="string", description="Path to generated PDF"),
     *             @OpenAPI\Property(property="downloadUrl", type="string", description="Download URL for PDF"),
     *             @OpenAPI\Property(property="message", type="string")
     *         )
     *     ),
     *     @OpenAPI\Response(response=401, description="Unauthorized"),
     *     @OpenAPI\Response(response=403, description="Access denied - not your quote"),
     *     @OpenAPI\Response(response=404, description="Quote not found"),
     *     @OpenAPI\Response(response=500, description="PDF generation failed"),
     *     @OpenAPI\Response(response=503, description="PDF service unavailable")
     * )
     */
    #[NoAdminRequired]
    #[ApiRoute(verb: 'GET', url: '/api/quotes/{quoteId}/pdf')]
    #[OpenAPI(tags: ['estimator'])]
    public function generateQuotePDF(int $quoteId): JSONResponse
    {
        try {
            // Validate user session
            $this->validateUserSession();
            
            // Validate quote ID
            if ($quoteId <= 0) {
                return $this->errorResponse('VALIDATION_ERROR', 'Invalid quote ID', 400);
            }

            // Ensure user owns the quote
            $this->requireQuoteOwnership($quoteId);

            $pdfResult = $this->estimatorService->generateQuotePDF($quoteId);
            
            if ($pdfResult) {
                $this->logSecurityEvent('pdf_generated', [
                    'quote_id' => $quoteId,
                    'pdf_path' => $pdfResult['path']
                ]);
                
                return new JSONResponse([
                    'success' => true,
                    'pdfPath' => $pdfResult['path'],
                    'downloadUrl' => $pdfResult['downloadUrl'],
                    'message' => 'PDF generated successfully'
                ]);
            } else {
                return $this->errorResponse('PDF_ERROR', 'Failed to generate PDF', 500);
            }
        } catch (AuthorizationException $e) {
            return $this->errorHandler->handleException($e);
        } catch (QuoteNotFoundException $e) {
            return $this->errorResponse('NOT_FOUND', 'Quote not found', 404);
        } catch (ValidationException $e) {
            return $this->errorResponse('VALIDATION_ERROR', $e->getMessage(), 400);
        } catch (\Throwable $e) {
            $this->logger->error('generateQuotePDF failed', [
                'quoteId' => $quoteId,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->errorResponse('SERVER_ERROR', 'PDF generation failed', 500);
        }
    }
    
    /**
     * Download a generated PDF file
     * 
     * @OpenAPI\Get(
     *     path="/api/quotes/{quoteId}/pdf/download/{fileName}",
     *     summary="Download a generated PDF file",
     *     tags={"estimator"},
     *     @OpenAPI\Parameter(
     *         name="quoteId",
     *         in="path",
     *         required=true,
     *         @OpenAPI\Schema(type="integer"),
     *         description="Quote ID"
     *     ),
     *     @OpenAPI\Parameter(
     *         name="fileName",
     *         in="path",
     *         required=true,
     *         @OpenAPI\Schema(type="string"),
     *         description="PDF file name"
     *     ),
     *     @OpenAPI\Response(
     *         response=200,
     *         description="PDF file download",
     *         @OpenAPI\MediaType(
     *             mediaType="application/pdf",
     *             @OpenAPI\Schema(type="string", format="binary")
     *         )
     *     ),
     *     @OpenAPI\Response(response=401, description="Unauthorized"),
     *     @OpenAPI\Response(response=403, description="Access denied - not your quote"),
     *     @OpenAPI\Response(response=404, description="File not found"),
     *     @OpenAPI\Response(response=500, description="Internal server error")
     * )
     */
    #[NoAdminRequired]
    #[ApiRoute(verb: 'GET', url: '/api/quotes/{quoteId}/pdf/download/{fileName}')]
    #[OpenAPI(tags: ['estimator'])]
    public function downloadQuotePDF(int $quoteId, string $fileName): Response
    {
        try {
            // Validate user session
            $this->validateUserSession();
            
            // Ensure user owns the quote
            $this->requireQuoteOwnership($quoteId);
            
            // Validate user has access to this quote
            $quote = $this->estimatorService->getQuote($quoteId);
            if (!$quote) {
                return $this->errorResponse('NOT_FOUND', 'Quote not found', 404);
            }
            
            // Get PDF file from app data storage
            $pdfContent = $this->estimatorService->getPDFFileContent($fileName);
            if (!$pdfContent) {
                return $this->errorResponse('NOT_FOUND', 'PDF file not found', 404);
            }
            
            // Create download response
            $response = new Response($pdfContent);
            $response->addHeader('Content-Type', 'application/pdf');
            $response->addHeader('Content-Disposition', 'attachment; filename="' . $fileName . '"');
            $response->addHeader('Content-Length', (string)strlen($pdfContent));
            $response->addHeader('Cache-Control', 'no-cache, must-revalidate');
            
            $this->logSecurityEvent('pdf_downloaded', [
                'quote_id' => $quoteId,
                'file_name' => $fileName,
                'file_size' => strlen($pdfContent)
            ]);
            
            return $response;
            
        } catch (AuthorizationException $e) {
            return $this->errorHandler->handleException($e);
        } catch (QuoteNotFoundException $e) {
            return $this->errorResponse('NOT_FOUND', 'Quote not found', 404);
        } catch (ValidationException $e) {
            return $this->errorResponse('VALIDATION_ERROR', $e->getMessage(), 400);
        } catch (\Throwable $e) {
            $this->logger->error('PDF download failed', [
                'quote_id' => $quoteId,
                'file_name' => $fileName,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->errorResponse('SERVER_ERROR', 'PDF download failed', 500);
        }
    }
    
    /**
     * Import pricing data from uploaded file
     * 
     * @OpenAPI\Post(
     *     path="/api/import",
     *     summary="Import pricing data from JSON, CSV, or Excel file",
     *     tags={"estimator"},
     *     @OpenAPI\RequestBody(
     *         required=true,
     *         @OpenAPI\MediaType(
     *             mediaType="multipart/form-data",
     *             @OpenAPI\Schema(
     *                 @OpenAPI\Property(
     *                     property="file",
     *                     type="string",
     *                     format="binary",
     *                     description="File to import (JSON, CSV, XLS, XLSX)"
     *                 )
     *             )
     *         )
     *     ),
     *     @OpenAPI\Response(
     *         response=200,
     *         description="Import completed",
     *         @OpenAPI\JsonContent(
     *             @OpenAPI\Property(property="success", type="boolean"),
     *             @OpenAPI\Property(property="imported", type="integer", description="Number of items imported"),
     *             @OpenAPI\Property(property="errors", type="array", @OpenAPI\Items(type="string"), description="Import errors")
     *         )
     *     ),
     *     @OpenAPI\Response(response=400, description="Invalid file or format"),
     *     @OpenAPI\Response(response=401, description="Unauthorized"),
     *     @OpenAPI\Response(response=403, description="Admin access required"),
     *     @OpenAPI\Response(response=413, description="File too large"),
     *     @OpenAPI\Response(response=429, description="Rate limited - too many import attempts"),
     *     @OpenAPI\Response(response=500, description="Internal server error")
     * )
     */
    #[AdminRequired]
    #[ApiRoute(verb: 'POST', url: '/api/import')]
    #[OpenAPI(tags: ['estimator'])]
    public function importPricingData(): JSONResponse
    {
        try {
            // Require admin privileges
            $this->requireAdmin();

            $this->errorHandler->logOperation('import_pricing_data', [
                'user_id' => $this->userSession->getUser()?->getUID()
            ]);

            // Rate limiting implementation
            $rateLimitResult = $this->validateImportRateLimit();
            if ($rateLimitResult !== null) {
                return $rateLimitResult;
            }

            $uploadedFile = $this->request->getUploadedFile('file');
            
            // Input validation
            if (!$uploadedFile || (!is_array($uploadedFile) && !is_object($uploadedFile))) {
                throw ValidationException::forField('file', 'No file uploaded');
            }

            // Enhanced security validation
            $this->validateUploadSecurity($uploadedFile);

            // Additional JSON structure validation
            $this->validateJsonStructure($uploadedFile);

            $startTime = microtime(true);

            $result = $this->retryService->executeWithCircuitBreaker(
                fn() => $this->estimatorService->importPricingFromUpload($uploadedFile),
                'import_pricing_data',
                ['filename' => $uploadedFile['name'] ?? 'unknown']
            );

            $this->errorHandler->logPerformance(
                'import_pricing_data',
                microtime(true) - $startTime,
                [
                    'imported_count' => $result['imported'],
                    'error_count' => count($result['errors'])
                ]
            );

            $this->errorHandler->logOperation('import_pricing_data_completed', [
                'user_id' => $this->userSession->getUser()?->getUID(),
                'imported' => $result['imported'],
                'errors' => count($result['errors'])
            ]);
            
            return $this->errorHandler->createSuccessResponse([
                'imported' => $result['imported'],
                'errors' => $result['errors'],
                'message' => sprintf('Successfully imported %d items', $result['imported'])
            ]);
        } catch (\Throwable $e) {
            return $this->errorHandler->handleException($e);
        }
    }

    /**
     * Export all pricing data and markup settings to JSON format
     * 
     * @OpenAPI\Get(
     *     path="/api/export",
     *     summary="Export all pricing data and markup settings as JSON",
     *     tags={"estimator"},
     *     @OpenAPI\Response(
     *         response=200,
     *         description="Export completed successfully",
     *         @OpenAPI\JsonContent(
     *             @OpenAPI\Property(property="success", type="boolean"),
     *             @OpenAPI\Property(property="data", type="object", description="Export data with pricingData and markups"),
     *             @OpenAPI\Property(property="filename", type="string", description="Suggested filename for download"),
     *             @OpenAPI\Property(property="message", type="string")
     *         )
     *     ),
     *     @OpenAPI\Response(response=401, description="Unauthorized"),
     *     @OpenAPI\Response(response=403, description="Admin access required"),
     *     @OpenAPI\Response(response=500, description="Export failed")
     * )
     */
    #[AdminRequired]
    #[ApiRoute(verb: 'GET', url: '/api/export')]
    #[OpenAPI(tags: ['estimator'])]
    public function exportPricingData(): JSONResponse
    {
        try {
            $this->logger->info('Starting pricing data export request');
            
            $exportData = $this->estimatorService->exportPricingData();
            
            // Generate suggested filename
            $timestamp = date('Y-m-d_H-i-s');
            $filename = "door_estimator_export_{$timestamp}.json";
            
            return new JSONResponse([
                'success' => true,
                'data' => $exportData,
                'filename' => $filename,
                'message' => sprintf('Successfully exported %d pricing items', count($exportData['pricingData']))
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('exportPricingData failed', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->errorResponse('SERVER_ERROR', 'Export failed', 500);
        }
    }



    /**
     * Validate JSON file structure for import
     * 
     * @param mixed $uploadedFile Uploaded file
     * @return JSONResponse|null Validation error response or null if valid
     */
    private function validateJsonStructure($uploadedFile): ?JSONResponse
    {
        $tmpName = is_array($uploadedFile) ? ($uploadedFile['tmp_name'] ?? '') : ($uploadedFile->getTmpName() ?? '');
        
        // Check if it's a JSON file
        $isJson = false;
        try {
            $fi = \finfo_open(\FILEINFO_MIME_TYPE);
            if ($fi !== false) {
                $mime = \finfo_file($fi, (string)$tmpName) ?: '';
                \finfo_close($fi);
                if ($mime === 'application/json' || $mime === 'text/plain') {
                    $isJson = true;
                }
            }
        } catch (\Throwable $t) {
            $isJson = true; // Allow attempt; validate via json_decode below
        }

        if ($isJson) {
            $jsonContent = @file_get_contents($tmpName);
            if ($jsonContent === false) {
                return $this->errorResponse('UPLOAD_READ_ERROR', 'Unable to read uploaded file content', 400);
            }

            // Check file size limit for JSON content
            if (strlen($jsonContent) > 10 * 1024 * 1024) { // 10MB limit for JSON content
                return $this->errorResponse('JSON_TOO_LARGE', 'JSON file content exceeds 10MB limit', 413);
            }

            $jsonContent = $this->removeBOM($jsonContent);
            $data = json_decode($jsonContent, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->errorResponse('INVALID_JSON', 'Invalid JSON file: ' . json_last_error_msg(), 400);
            }

            // Validate JSON structure
            if (!is_array($data)) {
                return $this->errorResponse('INVALID_JSON_STRUCTURE', 'JSON must be an object/array', 400);
            }

            if (!isset($data['pricingData']) || !isset($data['markups'])) {
                return $this->errorResponse('INVALID_JSON_STRUCTURE', 'JSON must contain pricingData and markups keys', 400);
            }

            // Validate pricingData structure
            if (!is_array($data['pricingData'])) {
                return $this->errorResponse('INVALID_JSON_STRUCTURE', 'pricingData must be an array', 400);
            }

            // Validate markups structure
            if (!is_array($data['markups'])) {
                return $this->errorResponse('INVALID_JSON_STRUCTURE', 'markups must be an array', 400);
            }

            // Check for reasonable data size limits
            if (count($data['pricingData']) > 50000) {
                return $this->errorResponse('DATA_TOO_LARGE', 'Cannot import more than 50,000 pricing items', 413);
            }
        }

        return null;
    }
    
    /**
     * Delete a specific quote
     * 
     * @OpenAPI\Delete(
     *     path="/api/quotes/{quoteId}",
     *     summary="Delete a specific quote",
     *     tags={"estimator"},
     *     @OpenAPI\Parameter(
     *         name="quoteId",
     *         in="path",
     *         required=true,
     *         description="Quote ID",
     *         @OpenAPI\Schema(type="integer")
     *     ),
     *     @OpenAPI\Response(
     *         response=200,
     *         description="Quote deleted successfully",
     *         @OpenAPI\JsonContent(
     *             @OpenAPI\Property(property="success", type="boolean"),
     *             @OpenAPI\Property(property="message", type="string")
     *         )
     *     ),
     *     @OpenAPI\Response(response=401, description="Unauthorized"),
     *     @OpenAPI\Response(response=403, description="Access denied - not your quote"),
     *     @OpenAPI\Response(response=404, description="Quote not found"),
     *     @OpenAPI\Response(response=500, description="Internal server error")
     * )
     */
    #[NoAdminRequired]
    #[ApiRoute(url: '/api/quotes/{quoteId}', verb: 'DELETE')]
    #[OpenAPI(tags: ['estimator'])]
    public function deleteQuote(int $quoteId): JSONResponse
    {
        try {
            // Validate user session
            $this->validateUserSession();
            
            // Validate quote ID
            if ($quoteId <= 0) {
                return $this->errorResponse('VALIDATION_ERROR', 'Invalid quote ID', 400);
            }

            // Ensure user owns the quote
            $this->requireQuoteOwnership($quoteId);

            $result = $this->estimatorService->deleteQuote($quoteId);
            
            $this->logSecurityEvent('quote_deleted', ['quote_id' => $quoteId]);
            
            return new JSONResponse([
                'success' => true,
                'message' => 'Quote deleted successfully'
            ]);
        } catch (AuthorizationException $e) {
            return $this->errorHandler->handleException($e);
        } catch (QuoteNotFoundException $e) {
            return $this->errorResponse('NOT_FOUND', 'Quote not found', 404);
        } catch (ValidationException $e) {
            return $this->errorResponse('VALIDATION_ERROR', $e->getMessage(), 400);
        } catch (\Throwable $e) {
            $this->logger->error('deleteQuote failed', [
                'quoteId' => $quoteId,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->errorResponse('SERVER_ERROR', 'Failed to delete quote', 500);
        }
    }
    
    /**
     * Duplicate an existing quote
     * 
     * @OpenAPI\Post(
     *     path="/api/quotes/{quoteId}/duplicate",
     *     summary="Create a copy of an existing quote",
     *     tags={"estimator"},
     *     @OpenAPI\Parameter(
     *         name="quoteId",
     *         in="path",
     *         required=true,
     *         description="Quote ID to duplicate",
     *         @OpenAPI\Schema(type="integer")
     *     ),
     *     @OpenAPI\Response(
     *         response=201,
     *         description="Quote duplicated successfully",
     *         @OpenAPI\JsonContent(
     *             @OpenAPI\Property(property="success", type="boolean"),
     *             @OpenAPI\Property(property="newQuoteId", type="integer"),
     *             @OpenAPI\Property(property="message", type="string")
     *         )
     *     ),
     *     @OpenAPI\Response(response=401, description="Unauthorized"),
     *     @OpenAPI\Response(response=403, description="Access denied - not your quote"),
     *     @OpenAPI\Response(response=404, description="Quote not found"),
     *     @OpenAPI\Response(response=500, description="Internal server error")
     * )
     */
    #[NoAdminRequired]
    #[ApiRoute(url: '/api/quotes/{quoteId}/duplicate', verb: 'POST')]
    #[OpenAPI(tags: ['estimator'])]
    public function duplicateQuote(int $quoteId): JSONResponse
    {
        try {
            // Validate user session
            $this->validateUserSession();
            
            // Validate quote ID
            if ($quoteId <= 0) {
                return $this->errorResponse('VALIDATION_ERROR', 'Invalid quote ID', 400);
            }

            // Ensure user owns the original quote
            $this->requireQuoteOwnership($quoteId);

            $newQuoteId = $this->estimatorService->duplicateQuote($quoteId);
            
            $this->logSecurityEvent('quote_duplicated', [
                'original_quote_id' => $quoteId,
                'new_quote_id' => $newQuoteId
            ]);
            
            return new JSONResponse([
                'success' => true,
                'newQuoteId' => $newQuoteId,
                'message' => 'Quote duplicated successfully'
            ], 201);
        } catch (AuthorizationException $e) {
            return $this->errorHandler->handleException($e);
        } catch (QuoteNotFoundException $e) {
            return $this->errorResponse('NOT_FOUND', 'Quote not found', 404);
        } catch (ValidationException $e) {
            return $this->errorResponse('VALIDATION_ERROR', $e->getMessage(), 400);
        } catch (\Throwable $e) {
            $this->logger->error('duplicateQuote failed', [
                'quoteId' => $quoteId,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->errorResponse('SERVER_ERROR', 'Failed to duplicate quote', 500);
        }
    }
    
    /**
     * Search pricing items
     * 
     * @OpenAPI\Get(
     *     path="/api/pricing/search",
     *     summary="Search pricing items by name or category",
     *     tags={"estimator"},
     *     @OpenAPI\Parameter(
     *         name="query",
     *         in="query",
     *         required=true,
     *         description="Search query string",
     *         @OpenAPI\Schema(type="string", minLength=1)
     *     ),
     *     @OpenAPI\Parameter(
     *         name="category",
     *         in="query",
     *         description="Filter by category",
     *         @OpenAPI\Schema(type="string")
     *     ),
     *     @OpenAPI\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Maximum number of results",
     *         @OpenAPI\Schema(type="integer", minimum=1, maximum=100, default=50)
     *     ),
     *     @OpenAPI\Response(
     *         response=200,
     *         description="Search results",
     *         @OpenAPI\JsonContent(
     *             @OpenAPI\Property(property="success", type="boolean"),
     *             @OpenAPI\Property(property="data", type="array", @OpenAPI\Items(ref="#/components/schemas/PricingItem")),
     *             @OpenAPI\Property(property="query", type="string"),
     *             @OpenAPI\Property(property="category", type="string", nullable=true),
     *             @OpenAPI\Property(property="total", type="integer")
     *         )
     *     ),
     *     @OpenAPI\Response(response=400, description="Invalid search parameters"),
     *     @OpenAPI\Response(response=401, description="Unauthorized"),
     *     @OpenAPI\Response(response=500, description="Internal server error")
     * )
     */
    #[NoAdminRequired]
    #[ApiRoute(url: '/api/pricing/search', verb: 'GET')]
    #[OpenAPI(tags: ['estimator'])]
    public function searchPricing(): JSONResponse
    {
        try {
            $query = $this->request->getParam('query') ?? $this->request->getParam('q');
            $category = $this->request->getParam('category');
            $limit = min(100, max(1, (int)($this->request->getParam('limit') ?? 25)));
            $offset = max(0, (int)($this->request->getParam('offset') ?? 0));

            // Input validation
            if (!is_string($query) || strlen(trim($query)) < 2) {
                return $this->errorResponse('VALIDATION_ERROR', 'Search query must be at least 2 characters', 400);
            }

            $startTime = microtime(true);
            
            $result = $this->estimatorService->searchPricing($query, $category, $limit, $offset);
            
            $executionTime = (microtime(true) - $startTime) * 1000;
            
            return new JSONResponse([
                'success' => true,
                'data' => $result['items'],
                'total' => $result['total'],
                'hasMore' => $result['hasMore'],
                'query' => $query,
                'category' => $category,
                'limit' => $result['limit'],
                'offset' => $result['offset'],
                'executionTime' => round($executionTime, 2)
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('searchPricing failed', [
                'query' => $query ?? null,
                'category' => $category ?? null,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->errorResponse('SERVER_ERROR', 'Search failed', 500);
        }
    }
    
    /**
     * Get default markup percentages
     * 
     * @OpenAPI\Get(
     *     path="/api/markup-defaults",
     *     summary="Retrieve default markup percentages for all categories",
     *     tags={"estimator"},
     *     @OpenAPI\Response(
     *         response=200,
     *         description="Default markup percentages",
     *         @OpenAPI\JsonContent(
     *             @OpenAPI\Property(property="success", type="boolean"),
     *             @OpenAPI\Property(property="data", type="object", description="Markup percentages by category"),
     *             @OpenAPI\Property(property="message", type="string")
     *         )
     *     ),
     *     @OpenAPI\Response(response=401, description="Unauthorized"),
     *     @OpenAPI\Response(response=500, description="Internal server error")
     * )
     */
    #[NoAdminRequired]
    #[ApiRoute(url: '/api/markup-defaults', verb: 'GET')]
    #[OpenAPI(tags: ['estimator'])]
    public function getMarkupDefaults(): JSONResponse
    {
        try {
            $markups = $this->estimatorService->getDefaultMarkups();
            
            return new JSONResponse([
                'success' => true,
                'data' => $markups,
                'message' => 'Default markups retrieved successfully'
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('getMarkupDefaults failed', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->errorResponse('SERVER_ERROR', 'Failed to retrieve markup defaults', 500);
        }
    }
    
    /**
     * Update default markup percentages
     * 
     * @OpenAPI\Post(
     *     path="/api/markup-defaults",
     *     summary="Update default markup percentages",
     *     tags={"estimator"},
     *     @OpenAPI\RequestBody(
     *         required=true,
     *         @OpenAPI\JsonContent(
     *             required={"markups"},
     *             @OpenAPI\Property(
     *                 property="markups",
     *                 type="object",
     *                 description="Markup percentages by category",
     *                 example={"doors": 15, "frames": 12, "hardware": 18}
     *             )
     *         )
     *     ),
     *     @OpenAPI\Response(
     *         response=200,
     *         description="Markup defaults updated successfully",
     *         @OpenAPI\JsonContent(
     *             @OpenAPI\Property(property="success", type="boolean"),
     *             @OpenAPI\Property(property="message", type="string")
     *         )
     *     ),
     *     @OpenAPI\Response(response=400, description="Invalid markup data"),
     *     @OpenAPI\Response(response=401, description="Unauthorized"),
     *     @OpenAPI\Response(response=403, description="Admin access required"),
     *     @OpenAPI\Response(response=500, description="Internal server error")
     * )
     */
    #[AdminRequired]
    #[ApiRoute(url: '/api/markup-defaults', verb: 'POST')]
    #[OpenAPI(tags: ['estimator'])]
    public function updateMarkupDefaults(): JSONResponse
    {
        try {
            $markups = $this->request->getParam('markups');
            
            // Input validation
            if (!is_array($markups) || empty($markups)) {
                return $this->errorResponse('VALIDATION_ERROR', 'Markups data is required', 400);
            }

            // Validate markup values
            $validationErrors = [];
            foreach ($markups as $category => $markup) {
                if (!is_numeric($markup) || $markup < 0 || $markup > 100) {
                    $validationErrors[$category] = 'Markup must be a number between 0 and 100';
                }
            }

            if (!empty($validationErrors)) {
                return $this->errorResponse('VALIDATION_ERROR', 'Invalid markup values', 400, [
                    'details' => $validationErrors
                ]);
            }

            $result = $this->estimatorService->updateDefaultMarkups($markups);
            
            return new JSONResponse([
                'success' => true,
                'message' => 'Default markups updated successfully'
            ]);
        } catch (ValidationException $e) {
            return $this->errorResponse('VALIDATION_ERROR', $e->getMessage(), 400);
        } catch (\Throwable $e) {
            $this->logger->error('updateMarkupDefaults failed', [
                'markups' => $markups ?? null,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->errorResponse('SERVER_ERROR', 'Failed to update markup defaults', 500);
        }
    }
    /**
     * Get onboarding status
     * 
     * @OpenAPI\Get(
     *     path="/api/onboarding-status",
     *     summary="Check if initial setup/onboarding is required",
     *     tags={"estimator"},
     *     @OpenAPI\Response(
     *         response=200,
     *         description="Onboarding status",
     *         @OpenAPI\JsonContent(
     *             @OpenAPI\Property(property="success", type="boolean"),
     *             @OpenAPI\Property(property="onboardingRequired", type="boolean", description="Whether onboarding is needed"),
     *             @OpenAPI\Property(property="hasPricingData", type="boolean", description="Whether pricing data exists"),
     *             @OpenAPI\Property(property="message", type="string")
     *         )
     *     ),
     *     @OpenAPI\Response(response=401, description="Unauthorized"),
     *     @OpenAPI\Response(response=500, description="Internal server error")
     * )
     */
    #[NoAdminRequired]
    #[ApiRoute(verb: 'GET', url: '/api/onboarding-status')]
    #[OpenAPI(tags: ['estimator'])]
    public function getOnboardingStatus(): JSONResponse
    {
        try {
            $hasPricing = $this->estimatorService->isPricingDataPresent();
            $onboardingRequired = !$hasPricing;
            
            return new JSONResponse([
                'success' => true,
                'onboardingRequired' => $onboardingRequired,
                'hasPricingData' => $hasPricing,
                'message' => $onboardingRequired ? 'Onboarding required - no pricing data found' : 'System ready'
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('getOnboardingStatus failed', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->errorResponse('SERVER_ERROR', 'Failed to check onboarding status', 500);
        }
    }
    
    /**
     * Standardized error response format
     * 
     * @param string $code Error code
     * @param string $message Error message
     * @param int $status HTTP status code
     * @param array $extra Additional error data
     * @return JSONResponse
     */
    private function errorResponse(string $code, string $message, int $status = 400, array $extra = []): JSONResponse
    {
        $response = [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ]
        ];

        if (!empty($extra)) {
            $response['error'] = array_merge($response['error'], $extra);
        }

        return new JSONResponse($response, $status);
    }

    /**
     * Comprehensive input validation for pricing item data
     * Includes sanitization, length limits, and security checks
     * 
     * @param array $data Input data
     * @return array Validation errors
     */
    private function validatePricingItemInput(array $data): array
    {
        $errors = [];

        // Validate and sanitize item name
        $item = $data['item'] ?? null;
        if (!is_string($item)) {
            $errors['item'] = 'Item name must be a string';
        } else {
            $item = $this->sanitizeString($item);
            if ($item === '') {
                $errors['item'] = 'Item name is required';
            } elseif (mb_strlen($item, 'UTF-8') > 255) {
                $errors['item'] = 'Item name must not exceed 255 characters';
            } elseif (mb_strlen($item, 'UTF-8') < 2) {
                $errors['item'] = 'Item name must be at least 2 characters';
            } elseif (!preg_match('/^[a-zA-Z0-9\s\-_\.\(\)\/]+$/u', $item)) {
                $errors['item'] = 'Item name contains invalid characters';
            }
        }

        // Validate price with enhanced checks
        $price = $data['price'] ?? null;
        if ($price === null || $price === '') {
            $errors['price'] = 'Price is required';
        } elseif (!is_numeric($price)) {
            $errors['price'] = 'Price must be a valid number';
        } else {
            $price = (float)$price;
            if ($price < 0) {
                $errors['price'] = 'Price must be non-negative';
            } elseif ($price > 1000000) {
                $errors['price'] = 'Price must not exceed $1,000,000';
            } elseif ($price > 0 && $price < 0.01) {
                $errors['price'] = 'Price must be at least $0.01 if not zero';
            }
        }

        // Validate and sanitize category
        $category = $data['category'] ?? null;
        if (!is_string($category)) {
            $errors['category'] = 'Category must be a string';
        } else {
            $category = $this->sanitizeString($category);
            if ($category === '') {
                $errors['category'] = 'Category is required';
            } elseif (mb_strlen($category, 'UTF-8') > 50) {
                $errors['category'] = 'Category must not exceed 50 characters';
            } elseif (!in_array($category, $this->getAllowedCategories())) {
                $errors['category'] = 'Invalid category selected';
            }
        }

        // Validate and sanitize subcategory (optional)
        $subcategory = $data['subcategory'] ?? null;
        if ($subcategory !== null) {
            if (!is_string($subcategory)) {
                $errors['subcategory'] = 'Subcategory must be a string';
            } else {
                $subcategory = $this->sanitizeString($subcategory);
                if (mb_strlen($subcategory, 'UTF-8') > 100) {
                    $errors['subcategory'] = 'Subcategory must not exceed 100 characters';
                }
            }
        }

        // Validate description (optional)
        $description = $data['description'] ?? null;
        if ($description !== null) {
            if (!is_string($description)) {
                $errors['description'] = 'Description must be a string';
            } else {
                $description = $this->sanitizeString($description);
                if (mb_strlen($description, 'UTF-8') > 2000) {
                    $errors['description'] = 'Description must not exceed 2000 characters';
                }
            }
        }

        // Validate ID for updates
        $id = $data['id'] ?? null;
        if ($id !== null) {
            if (!is_numeric($id) || (int)$id <= 0) {
                $errors['id'] = 'Invalid ID provided';
            }
        }

        return $errors;
    }

    /**
     * Comprehensive validation for quote input data
     * Includes deep validation of quote structure and content
     * 
     * @param mixed $quoteData Quote data
     * @param mixed $markups Markup data
     * @return array Validation errors
     */
    private function validateQuoteInput($quoteData, $markups): array
    {
        $errors = [];

        // Validate quote data structure
        if (!is_array($quoteData)) {
            $errors['quoteData'] = 'Quote data must be an array';
            return $errors; // Cannot continue validation without proper structure
        }

        if (empty($quoteData)) {
            $errors['quoteData'] = 'Quote data cannot be empty';
            return $errors;
        }

        // Validate each section in quote data
        $validSections = $this->getAllowedQuoteSections();
        $totalItems = 0;
        
        foreach ($quoteData as $sectionKey => $items) {
            if (!is_string($sectionKey) || !in_array($sectionKey, $validSections)) {
                $errors["quoteData.{$sectionKey}"] = 'Invalid section key';
                continue;
            }

            if (!is_array($items)) {
                $errors["quoteData.{$sectionKey}"] = 'Section must contain an array of items';
                continue;
            }

            // Validate each item in the section
            foreach ($items as $index => $item) {
                $itemPath = "quoteData.{$sectionKey}[{$index}]";
                
                if (!is_array($item)) {
                    $errors["{$itemPath}"] = 'Item must be an array';
                    continue;
                }

                // Validate required item fields
                if (!isset($item['item']) || !is_string($item['item'])) {
                    $errors["{$itemPath}.item"] = 'Item name is required and must be a string';
                } else {
                    $itemName = $this->sanitizeString($item['item']);
                    if (mb_strlen($itemName, 'UTF-8') > 255) {
                        $errors["{$itemPath}.item"] = 'Item name too long';
                    }
                }

                if (!isset($item['qty']) || !is_numeric($item['qty'])) {
                    $errors["{$itemPath}.qty"] = 'Quantity is required and must be numeric';
                } else {
                    $qty = (int)$item['qty'];
                    if ($qty < 0) {
                        $errors["{$itemPath}.qty"] = 'Quantity cannot be negative';
                    } elseif ($qty > 10000) {
                        $errors["{$itemPath}.qty"] = 'Quantity cannot exceed 10,000';
                    }
                }

                if (!isset($item['price']) || !is_numeric($item['price'])) {
                    $errors["{$itemPath}.price"] = 'Price is required and must be numeric';
                } else {
                    $price = (float)$item['price'];
                    if ($price < 0) {
                        $errors["{$itemPath}.price"] = 'Price cannot be negative';
                    } elseif ($price > 1000000) {
                        $errors["{$itemPath}.price"] = 'Price cannot exceed $1,000,000';
                    }
                }

                $totalItems++;
            }
        }

        // Validate total items limit
        if ($totalItems > 1000) {
            $errors['quoteData'] = 'Quote cannot contain more than 1,000 items';
        }

        // Validate markups structure and values
        if (!is_array($markups)) {
            $errors['markups'] = 'Markups must be an array';
        } else {
            $requiredMarkups = ['doors', 'frames', 'hardware'];
            
            foreach ($requiredMarkups as $category) {
                if (!isset($markups[$category])) {
                    $errors["markups.{$category}"] = "Markup for {$category} is required";
                } elseif (!is_numeric($markups[$category])) {
                    $errors["markups.{$category}"] = "Markup for {$category} must be numeric";
                } else {
                    $markup = (float)$markups[$category];
                    if ($markup < 0 || $markup > 100) {
                        $errors["markups.{$category}"] = "Markup for {$category} must be between 0 and 100";
                    }
                }
            }

            // Check for unexpected markup categories
            foreach ($markups as $category => $markup) {
                if (!in_array($category, $requiredMarkups)) {
                    $errors["markups.{$category}"] = "Unknown markup category: {$category}";
                }
            }
        }

        return $errors;
    }

    private function removeBOM(string $input): string
    {
        if (strncmp($input, "\xEF\xBB\xBF", 3) === 0) {
            return substr($input, 3);
        }
        return $input;
    }

    private function sniffJson(string $tmpName): bool
    {
        $content = @file_get_contents($tmpName);
        if ($content === false) {
            return false;
        }
        $content = $this->removeBOM($content);
        $decoded = json_decode($content, true);
        return json_last_error() === JSON_ERROR_NONE;
    }

    private function sniffCsv(string $tmpName): bool
    {
        $fh = @fopen($tmpName, 'rb');
        if ($fh === false) {
            return false;
        }
        $bom = fread($fh, 3);
        if (strncmp($bom, "\xEF\xBB\xBF", 3) !== 0) {
            rewind($fh);
        }
        $row = fgetcsv($fh);
        fclose($fh);
        return is_array($row) && count($row) >= 1;
    }

    private function sniffXlsx(string $tmpName): bool
    {
        $fh = @fopen($tmpName, 'rb');
        if ($fh === false) {
            return false;
        }
        $head = fread($fh, 8);
        fclose($fh);
        return strncmp($head, "PK", 2) === 0;
    }

    private function sniffXls(string $tmpName): bool
    {
        $fh = @fopen($tmpName, 'rb');
        if ($fh === false) {
            return false;
        }
        $head = fread($fh, 8);
        fclose($fh);
        return ($head !== '') && (substr(bin2hex($head), 0, 16) === 'd0cf11e0a1b11ae1');
    }

    /**
     * Centralized basic validation for uploaded files
     * 
     * @param mixed $uploadedFile Uploaded file
     * @return JSONResponse|null Validation error response or null if valid
     */
    private function validateUploadBasics($uploadedFile): ?JSONResponse
    {
        $maxSize = 5 * 1024 * 1024; // 5MB

        $fileSize = is_array($uploadedFile) ? ($uploadedFile['size'] ?? 0) : ($uploadedFile->getSize() ?? 0);
        $tmpName = is_array($uploadedFile) ? ($uploadedFile['tmp_name'] ?? '') : ($uploadedFile->getTmpName() ?? '');

        // Validate file size
        if ($fileSize <= 0) {
            return $this->errorResponse('FILE_SIZE_INVALID', 'Uploaded file is empty', 400);
        }
        if ($fileSize > $maxSize) {
            return $this->errorResponse('FILE_SIZE_INVALID', 'File size exceeds 5MB limit', 413);
        }

        // Validate file readability
        if (!is_readable($tmpName)) {
            return $this->errorResponse('UPLOAD_READ_ERROR', 'Uploaded file is not readable', 400);
        }

        // Determine and validate file type
        $realMime = null;
        try {
            $fi = \finfo_open(\FILEINFO_MIME_TYPE);
            if ($fi !== false) {
                $realMime = \finfo_file($fi, (string)$tmpName) ?: null;
                \finfo_close($fi);
            }
        } catch (\Throwable $t) {
            $this->logger->warning('File type detection failed', [
                'exception' => $t->getMessage()
            ]);
        }

        // Validate supported file types
        $allowed = false;
        $typeDetected = 'unknown';
        
        if (($realMime === 'application/json' || $realMime === 'text/plain') && $this->sniffJson($tmpName)) {
            $allowed = true;
            $typeDetected = 'json';
        } elseif (($realMime === 'text/csv' || $realMime === 'text/plain') && $this->sniffCsv($tmpName)) {
            $allowed = true;
            $typeDetected = 'csv';
        } elseif ($realMime === 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' || $this->sniffXlsx($tmpName)) {
            $allowed = true;
            $typeDetected = 'xlsx';
        } elseif ($realMime === 'application/vnd.ms-excel' || $this->sniffXls($tmpName)) {
            $allowed = true;
            $typeDetected = 'xls';
        }

        if (!$allowed) {
            return $this->errorResponse(
                'UNSUPPORTED_FILE_TYPE', 
                'Unsupported file type. Only JSON, CSV, and Excel files are allowed.', 
                400,
                ['detectedType' => $typeDetected, 'mimeType' => $realMime]
            );
        }

        return null;
    }

    /**
     * Sanitize string input to prevent XSS and ensure data integrity
     * 
     * @param string $input Raw string input
     * @return string Sanitized string
     */
    private function sanitizeString(string $input): string
    {
        // Remove HTML tags and PHP tags
        $sanitized = strip_tags($input);
        
        // Remove null bytes and control characters
        $sanitized = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $sanitized);
        
        // Trim whitespace
        $sanitized = trim($sanitized);
        
        // Convert HTML entities
        $sanitized = htmlspecialchars($sanitized, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        return $sanitized;
    }

    /**
     * Get list of allowed categories for validation
     * 
     * @return array List of valid categories
     */
    private function getAllowedCategories(): array
    {
        return [
            'doors', 'doorOptions', 'inserts', 'frames', 'frameOptions',
            'hinges', 'weatherstrip', 'closers', 'locksets', 'exitDevices', 'hardware'
        ];
    }

    /**
     * Get list of allowed quote sections for validation
     * 
     * @return array List of valid quote sections
     */
    private function getAllowedQuoteSections(): array
    {
        return [
            'doors', 'doorOptions', 'inserts', 'frames', 'frameOptions',
            'hinges', 'weatherstrip', 'closers', 'locksets', 'exitDevices', 'hardware'
        ];
    }

    /**
     * Enhanced file upload validation with security checks
     * 
     * @param mixed $uploadedFile Uploaded file
     * @return JSONResponse|null Validation error response or null if valid
     */
    private function validateUploadSecurity($uploadedFile): ?JSONResponse
    {
        // Basic validation first
        $basicValidation = $this->validateUploadBasics($uploadedFile);
        if ($basicValidation !== null) {
            return $basicValidation;
        }

        $tmpName = is_array($uploadedFile) ? ($uploadedFile['tmp_name'] ?? '') : ($uploadedFile->getTmpName() ?? '');
        $fileName = is_array($uploadedFile) ? ($uploadedFile['name'] ?? '') : ($uploadedFile->getName() ?? '');

        // Validate file name for security
        if (!$this->isSecureFileName($fileName)) {
            return $this->errorResponse(
                'INVALID_FILENAME',
                'File name contains invalid or potentially dangerous characters',
                400
            );
        }

        // Check for embedded executables or suspicious content
        if ($this->containsSuspiciousContent($tmpName)) {
            return $this->errorResponse(
                'SUSPICIOUS_CONTENT',
                'File contains potentially malicious content',
                400
            );
        }

        // Additional MIME type verification
        if (!$this->verifyMimeTypeSecurity($tmpName)) {
            return $this->errorResponse(
                'MIME_TYPE_MISMATCH',
                'File content does not match expected format',
                400
            );
        }

        return null;
    }

    /**
     * Check if filename is secure (no path traversal, executable extensions, etc.)
     * 
     * @param string $fileName File name to check
     * @return bool True if secure, false otherwise
     */
    private function isSecureFileName(string $fileName): bool
    {
        // Check for path traversal attempts
        if (strpos($fileName, '..') !== false || strpos($fileName, '/') !== false || strpos($fileName, '\\') !== false) {
            return false;
        }

        // Check for null bytes
        if (strpos($fileName, "\0") !== false) {
            return false;
        }

        // Check for dangerous extensions
        $dangerousExtensions = [
            'php', 'phtml', 'php3', 'php4', 'php5', 'phar',
            'exe', 'bat', 'cmd', 'com', 'scr', 'vbs', 'js',
            'jar', 'sh', 'py', 'pl', 'rb', 'asp', 'aspx'
        ];

        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if (in_array($extension, $dangerousExtensions)) {
            return false;
        }

        // Check filename length
        if (strlen($fileName) > 255) {
            return false;
        }

        return true;
    }

    /**
     * Check file content for suspicious patterns
     * 
     * @param string $filePath Path to file to check
     * @return bool True if suspicious content found, false otherwise
     */
    private function containsSuspiciousContent(string $filePath): bool
    {
        $handle = fopen($filePath, 'rb');
        if (!$handle) {
            return true; // Assume suspicious if can't read
        }

        // Read first 8KB for analysis
        $content = fread($handle, 8192);
        fclose($handle);

        // Check for executable signatures
        $executableSignatures = [
            "\x4D\x5A", // PE executable
            "\x7F\x45\x4C\x46", // ELF executable
            "\xFE\xED\xFA", // Mach-O executable
            "<?php", // PHP code
            "<script", // JavaScript
            "javascript:", // JavaScript protocol
        ];

        foreach ($executableSignatures as $signature) {
            if (strpos($content, $signature) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Verify MIME type matches file content for security
     * 
     * @param string $filePath Path to file to verify
     * @return bool True if MIME type is secure, false otherwise
     */
    private function verifyMimeTypeSecurity(string $filePath): bool
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if (!$finfo) {
            return false;
        }

        $mimeType = finfo_file($finfo, $filePath);
        finfo_close($finfo);

        // Allow only specific safe MIME types
        $allowedMimeTypes = [
            'application/json',
            'text/plain',
            'text/csv',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ];

        return in_array($mimeType, $allowedMimeTypes);
    }

    /**
     * Validate request rate limiting for import operations
     * 
     * @return JSONResponse|null Rate limit error or null if allowed
     */
    private function validateImportRateLimit(): ?JSONResponse
    {
        $cache = $this->cacheFactory->createDistributed('door_estimator_import_rate_limit');
        $userId = $this->userSession->getUser()?->getUID() ?? 'anonymous';
        $key = "import_rate_limit_{$userId}";
        
        $lastImport = $cache->get($key);
        $now = time();
        
        // Allow one import per 5 seconds per user
        if ($lastImport !== null && ($now - $lastImport) < 5) {
            return $this->errorResponse(
                'RATE_LIMITED',
                'Import rate limit exceeded. Please wait before importing again.',
                429
            );
        }
        
        // Set rate limit
        $cache->set($key, $now, 10); // Cache for 10 seconds
        
        return null;
    }

    /**
     * Get current user information including admin status and groups
     * 
     * @OpenAPI\Get(
     *     path="/api/user/info",
     *     summary="Get current user information",
     *     tags={"estimator"},
     *     @OpenAPI\Response(
     *         response=200,
     *         description="User information",
     *         @OpenAPI\JsonContent(
     *             @OpenAPI\Property(property="success", type="boolean"),
     *             @OpenAPI\Property(property="user", type="object",
     *                 @OpenAPI\Property(property="uid", type="string"),
     *                 @OpenAPI\Property(property="displayName", type="string"),
     *                 @OpenAPI\Property(property="isAdmin", type="boolean"),
     *                 @OpenAPI\Property(property="groups", type="array", @OpenAPI\Items(type="string"))
     *             )
     *         )
     *     ),
     *     @OpenAPI\Response(response=401, description="Unauthorized")
     * )
     */
    #[NoAdminRequired]
    #[ApiRoute(verb: 'GET', url: '/api/user/info')]
    #[OpenAPI(tags: ['estimator'])]
    public function getUserInfo(): JSONResponse
    {
        try {
            $this->validateUserSession();
            $userContext = $this->authService->getUserContext();
            
            return new JSONResponse([
                'success' => true,
                'user' => [
                    'uid' => $userContext['user_id'],
                    'displayName' => $userContext['display_name'],
                    'isAdmin' => $userContext['is_admin'],
                    'groups' => $userContext['groups']
                ]
            ]);
        } catch (AuthorizationException $e) {
            return $this->errorHandler->handleException($e);
        } catch (\Throwable $e) {
            $this->logger->error('getUserInfo failed', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->errorResponse('SERVER_ERROR', 'Failed to get user information', 500);
        }
    }

    /**
     * Get session information including expiration status
     * 
     * @OpenAPI\Get(
     *     path="/api/session/info",
     *     summary="Get session information",
     *     tags={"estimator"},
     *     @OpenAPI\Response(
     *         response=200,
     *         description="Session information",
     *         @OpenAPI\JsonContent(
     *             @OpenAPI\Property(property="success", type="boolean"),
     *             @OpenAPI\Property(property="sessionExpiring", type="boolean"),
     *             @OpenAPI\Property(property="timeRemaining", type="integer")
     *         )
     *     ),
     *     @OpenAPI\Response(response=401, description="Unauthorized")
     * )
     */
    #[NoAdminRequired]
    #[ApiRoute(verb: 'GET', url: '/api/session/info')]
    #[OpenAPI(tags: ['estimator'])]
    public function getSessionInfo(): JSONResponse
    {
        try {
            $this->validateUserSession();
            
            $sessionExpiring = $this->authService->isSessionExpiring();
            $timeRemaining = $this->authService->getSessionTimeout();
            
            return new JSONResponse([
                'success' => true,
                'sessionExpiring' => $sessionExpiring,
                'timeRemaining' => $timeRemaining
            ]);
        } catch (AuthorizationException $e) {
            return $this->errorHandler->handleException($e);
        } catch (\Throwable $e) {
            $this->logger->error('getSessionInfo failed', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->errorResponse('SERVER_ERROR', 'Failed to get session information', 500);
        }
    }

    /**
     * Refresh user session to extend timeout
     * 
     * @OpenAPI\Post(
     *     path="/api/session/refresh",
     *     summary="Refresh user session",
     *     tags={"estimator"},
     *     @OpenAPI\Response(
     *         response=200,
     *         description="Session refreshed successfully",
     *         @OpenAPI\JsonContent(
     *             @OpenAPI\Property(property="success", type="boolean"),
     *             @OpenAPI\Property(property="message", type="string")
     *         )
     *     ),
     *     @OpenAPI\Response(response=401, description="Unauthorized")
     * )
     */
    #[NoAdminRequired]
    #[ApiRoute(verb: 'POST', url: '/api/session/refresh')]
    #[OpenAPI(tags: ['estimator'])]
    public function refreshSession(): JSONResponse
    {
        try {
            $this->validateUserSession();
            $this->authService->refreshSession();
            
            $this->logSecurityEvent('session_refreshed');
            
            return new JSONResponse([
                'success' => true,
                'message' => 'Session refreshed successfully'
            ]);
        } catch (AuthorizationException $e) {
            return $this->errorHandler->handleException($e);
        } catch (\Throwable $e) {
            $this->logger->error('refreshSession failed', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->errorResponse('SERVER_ERROR', 'Failed to refresh session', 500);
        }
    }
}