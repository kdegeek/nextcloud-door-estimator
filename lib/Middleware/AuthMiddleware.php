<?php
declare(strict_types=1);

namespace OCA\DoorEstimator\Middleware;

use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Middleware;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IUserSession;
use OCP\IRequest;
use OCP\ICacheFactory;
use Psr\Log\LoggerInterface;

/**
 * Enhanced authentication and security middleware for Door Estimator app
 * Provides authentication, rate limiting, input validation, and security headers
 */
class AuthMiddleware extends Middleware {
    private IUserSession $userSession;
    private LoggerInterface $logger;
    private IRequest $request;
    private ICacheFactory $cacheFactory;

    public function __construct(
        IUserSession $userSession,
        LoggerInterface $logger,
        IRequest $request,
        ICacheFactory $cacheFactory
    ) {
        $this->userSession = $userSession;
        $this->logger = $logger;
        $this->request = $request;
        $this->cacheFactory = $cacheFactory;
    }

    /**
     * Enhanced security checks before controller execution
     */
    public function beforeController(Controller $controller, string $methodName): void {
        // Skip security checks for public endpoints
        if ($this->isPublicEndpoint($controller, $methodName)) {
            return;
        }

        // Rate limiting check
        $this->checkRateLimit($controller, $methodName);

        // Input validation and sanitization
        $this->validateAndSanitizeInput();

        // Authentication check
        $user = $this->userSession->getUser();
        if ($user === null) {
            $this->logger->warning('Unauthenticated access attempt to Door Estimator API', [
                'controller' => get_class($controller),
                'method' => $methodName,
                'ip' => $this->request->getRemoteAddress(),
                'user_agent' => $this->request->getHeader('User-Agent')
            ]);
            
            throw new \OCP\AppFramework\Http\Exception\NotLoggedInException();
        }

        // CSRF protection for state-changing operations
        if ($this->isStateChangingOperation($methodName)) {
            $this->validateCSRFToken();
        }

        // Log successful authentication for audit purposes
        $this->logger->debug('Authenticated API access', [
            'user' => $user->getUID(),
            'controller' => get_class($controller),
            'method' => $methodName,
            'ip' => $this->request->getRemoteAddress()
        ]);
    }

    /**
     * Handle exceptions from security checks
     */
    public function afterException(Controller $controller, string $methodName, \Exception $exception): Response {
        if ($exception instanceof \OCP\AppFramework\Http\Exception\NotLoggedInException) {
            return new JSONResponse([
                'success' => false,
                'error' => [
                    'code' => 'AUTHENTICATION_REQUIRED',
                    'message' => 'Authentication required to access this resource'
                ]
            ], 401);
        }

        // Handle rate limiting
        if ($exception->getCode() === 429) {
            return new JSONResponse([
                'success' => false,
                'error' => [
                    'code' => 'RATE_LIMITED',
                    'message' => $exception->getMessage()
                ]
            ], 429);
        }

        // Handle CSRF errors
        if ($exception->getCode() === 403) {
            return new JSONResponse([
                'success' => false,
                'error' => [
                    'code' => 'CSRF_ERROR',
                    'message' => $exception->getMessage()
                ]
            ], 403);
        }

        // Handle validation errors
        if ($exception->getCode() === 400) {
            return new JSONResponse([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => $exception->getMessage()
                ]
            ], 400);
        }

        // Handle payload too large
        if ($exception->getCode() === 413) {
            return new JSONResponse([
                'success' => false,
                'error' => [
                    'code' => 'PAYLOAD_TOO_LARGE',
                    'message' => $exception->getMessage()
                ]
            ], 413);
        }

        throw $exception;
    }

    /**
     * Check rate limiting for API endpoints
     */
    private function checkRateLimit(Controller $controller, string $methodName): void {
        $cache = $this->cacheFactory->createDistributed('door_estimator_rate_limit');
        $clientIp = $this->request->getRemoteAddress();
        $user = $this->userSession->getUser();
        $userId = $user ? $user->getUID() : 'anonymous';
        
        // Different rate limits for different operations
        $limits = $this->getRateLimits($methodName);
        if (!$limits) {
            return; // No rate limiting for this method
        }
        
        $key = "rate_limit_{$userId}_{$clientIp}_{$methodName}";
        $requests = $cache->get($key) ?? 0;
        
        if ($requests >= $limits['max_requests']) {
            $this->logger->warning('Rate limit exceeded', [
                'user' => $userId,
                'ip' => $clientIp,
                'method' => $methodName,
                'requests' => $requests
            ]);
            
            throw new \Exception('Rate limit exceeded. Please try again later.', 429);
        }
        
        $cache->set($key, $requests + 1, $limits['window_seconds']);
    }

    /**
     * Get rate limits for different operations
     */
    private function getRateLimits(string $methodName): ?array {
        $rateLimits = [
            'importPricingData' => ['max_requests' => 5, 'window_seconds' => 300], // 5 per 5 minutes
            'updatePricingItem' => ['max_requests' => 100, 'window_seconds' => 60], // 100 per minute
            'saveQuote' => ['max_requests' => 50, 'window_seconds' => 60], // 50 per minute
            'lookupPrice' => ['max_requests' => 200, 'window_seconds' => 60], // 200 per minute
        ];
        
        return $rateLimits[$methodName] ?? null;
    }

    /**
     * Validate and sanitize input data
     */
    private function validateAndSanitizeInput(): void {
        $method = $this->request->getMethod();
        
        if ($method === 'POST' || $method === 'PUT') {
            $contentType = $this->request->getHeader('Content-Type');
            
            // Validate content type
            if ($contentType && !str_starts_with($contentType, 'application/json') && 
                !str_starts_with($contentType, 'multipart/form-data')) {
                throw new \Exception('Invalid content type', 400);
            }
            
            // Check request size
            $contentLength = $this->request->getHeader('Content-Length');
            if ($contentLength && (int)$contentLength > 10 * 1024 * 1024) { // 10MB limit
                throw new \Exception('Request too large', 413);
            }
            
            // Validate JSON structure for JSON requests
            if (str_starts_with($contentType ?? '', 'application/json')) {
                $rawInput = file_get_contents('php://input');
                if ($rawInput && !json_decode($rawInput)) {
                    throw new \Exception('Invalid JSON format', 400);
                }
            }
        }
    }

    /**
     * Check if operation changes state (requires CSRF protection)
     */
    private function isStateChangingOperation(string $methodName): bool {
        $stateChangingMethods = [
            'updatePricingItem', 'saveQuote', 'deleteQuote', 'duplicateQuote',
            'importPricingData', 'updateMarkupDefaults'
        ];
        
        return in_array($methodName, $stateChangingMethods);
    }

    /**
     * Validate CSRF token for state-changing operations
     */
    private function validateCSRFToken(): void {
        $token = $this->request->getHeader('requesttoken') ?? 
                 $this->request->getParam('requesttoken');
        
        if (!$token) {
            $this->logger->warning('Missing CSRF token', [
                'ip' => $this->request->getRemoteAddress(),
                'user_agent' => $this->request->getHeader('User-Agent')
            ]);
            throw new \Exception('CSRF token required', 403);
        }
        
        // Nextcloud's CSRF token validation would be handled by the framework
        // This is a placeholder for additional custom validation if needed
    }

    /**
     * Determine if an endpoint should be publicly accessible
     */
    private function isPublicEndpoint(Controller $controller, string $methodName): bool {
        // Page controller index method is handled by Nextcloud's authentication
        if (get_class($controller) === 'OCA\DoorEstimator\Controller\PageController' && $methodName === 'index') {
            return true;
        }

        // System status endpoint can be public for health checks
        if ($methodName === 'getSystemStatus') {
            return true;
        }

        return false;
    }
}