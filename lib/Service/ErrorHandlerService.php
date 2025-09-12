<?php

declare(strict_types=1);

namespace OCA\DoorEstimator\Service;

use OCA\DoorEstimator\Exception\BaseException;
use OCP\AppFramework\Http\JSONResponse;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Service for handling errors and creating consistent API responses
 */
class ErrorHandlerService
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Handle an exception and return appropriate JSON response
     */
    public function handleException(Throwable $exception): JSONResponse
    {
        // Log the exception
        $this->logException($exception);

        // Create response based on exception type
        if ($exception instanceof BaseException) {
            return $this->handleBaseException($exception);
        }

        // Handle unexpected exceptions
        return $this->handleUnexpectedException($exception);
    }

    /**
     * Handle BaseException instances with structured error responses
     */
    private function handleBaseException(BaseException $exception): JSONResponse
    {
        $response = new JSONResponse([
            'success' => false,
            'error' => $exception->toArray()
        ], $exception->getHttpStatusCode());

        // Add retry-after header for retryable errors
        if ($exception->isRetryable()) {
            $response->addHeader('Retry-After', '30'); // 30 seconds
        }

        return $response;
    }

    /**
     * Handle unexpected exceptions with generic error response
     */
    private function handleUnexpectedException(Throwable $exception): JSONResponse
    {
        return new JSONResponse([
            'success' => false,
            'error' => [
                'code' => 'INTERNAL_ERROR',
                'message' => 'An unexpected error occurred. Please try again or contact support.',
                'context' => [],
                'retryable' => false
            ]
        ], 500);
    }

    /**
     * Log exception with appropriate level and context
     */
    private function logException(Throwable $exception): void
    {
        $context = [
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ];

        if ($exception instanceof BaseException) {
            $context['error_code'] = $exception->getErrorCode();
            $context['user_message'] = $exception->getUserMessage();
            $context['exception_context'] = $exception->getContext();
        }

        // Determine log level based on exception type
        $level = $this->getLogLevel($exception);

        $this->logger->log($level, $exception->getMessage(), $context);
    }

    /**
     * Determine appropriate log level for exception
     */
    private function getLogLevel(Throwable $exception): string
    {
        if ($exception instanceof BaseException) {
            $httpCode = $exception->getHttpStatusCode();
            
            if ($httpCode >= 500) {
                return 'error';
            } elseif ($httpCode >= 400) {
                return 'warning';
            }
            
            return 'info';
        }

        // Unexpected exceptions are always errors
        return 'error';
    }

    /**
     * Create a success response with consistent format
     */
    public function createSuccessResponse(array $data = [], int $statusCode = 200): JSONResponse
    {
        return new JSONResponse([
            'success' => true,
            'data' => $data
        ], $statusCode);
    }

    /**
     * Create a validation error response
     */
    public function createValidationErrorResponse(array $errors): JSONResponse
    {
        return new JSONResponse([
            'success' => false,
            'error' => [
                'code' => 'VALIDATION_ERROR',
                'message' => 'Please correct the highlighted fields and try again.',
                'validation_errors' => $errors,
                'retryable' => false
            ]
        ], 400);
    }

    /**
     * Log business operation for monitoring
     */
    public function logOperation(string $operation, array $context = [], string $level = 'info'): void
    {
        $this->logger->log($level, "Operation: {$operation}", array_merge([
            'operation' => $operation,
            'timestamp' => time()
        ], $context));
    }

    /**
     * Log performance metrics
     */
    public function logPerformance(string $operation, float $duration, array $context = []): void
    {
        $this->logger->info("Performance: {$operation}", array_merge([
            'operation' => $operation,
            'duration_ms' => round($duration * 1000, 2),
            'timestamp' => time()
        ], $context));
    }

    /**
     * Log security events
     */
    public function logSecurityEvent(string $event, array $context = []): void
    {
        $this->logger->warning("Security: {$event}", array_merge([
            'security_event' => $event,
            'timestamp' => time(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ], $context));
    }
}