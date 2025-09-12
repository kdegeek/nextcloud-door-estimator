<?php

declare(strict_types=1);

namespace OCA\DoorEstimator\Service;

use OCA\DoorEstimator\Exception\BaseException;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Service for implementing retry mechanisms for transient failures
 */
class RetryService
{
    private LoggerInterface $logger;
    private int $maxRetries;
    private array $retryDelays;

    public function __construct(LoggerInterface $logger, int $maxRetries = 3)
    {
        $this->logger = $logger;
        $this->maxRetries = $maxRetries;
        // Exponential backoff: 1s, 2s, 4s
        $this->retryDelays = [1, 2, 4];
    }

    /**
     * Execute a callable with retry logic for transient failures
     *
     * @param callable $operation The operation to execute
     * @param string $operationName Name for logging purposes
     * @param array $context Additional context for logging
     * @return mixed The result of the operation
     * @throws Throwable The last exception if all retries fail
     */
    public function executeWithRetry(callable $operation, string $operationName, array $context = [])
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt <= $this->maxRetries) {
            try {
                if ($attempt > 0) {
                    $this->logger->info("Retrying operation: {$operationName}", array_merge($context, [
                        'attempt' => $attempt,
                        'max_attempts' => $this->maxRetries + 1
                    ]));
                }

                return $operation();
            } catch (Throwable $exception) {
                $lastException = $exception;
                $attempt++;

                // Check if this is a retryable error
                if (!$this->isRetryableException($exception)) {
                    $this->logger->warning("Non-retryable error in operation: {$operationName}", array_merge($context, [
                        'exception' => get_class($exception),
                        'message' => $exception->getMessage(),
                        'attempt' => $attempt
                    ]));
                    throw $exception;
                }

                // If we've exhausted all retries, throw the last exception
                if ($attempt > $this->maxRetries) {
                    $this->logger->error("All retry attempts failed for operation: {$operationName}", array_merge($context, [
                        'exception' => get_class($exception),
                        'message' => $exception->getMessage(),
                        'total_attempts' => $attempt
                    ]));
                    throw $exception;
                }

                // Wait before retrying
                $delay = $this->retryDelays[$attempt - 1] ?? 4;
                $this->logger->info("Operation failed, waiting {$delay}s before retry: {$operationName}", array_merge($context, [
                    'exception' => get_class($exception),
                    'message' => $exception->getMessage(),
                    'attempt' => $attempt,
                    'delay_seconds' => $delay
                ]));

                sleep($delay);
            }
        }

        // This should never be reached, but just in case
        throw $lastException;
    }

    /**
     * Check if an exception is retryable
     */
    private function isRetryableException(Throwable $exception): bool
    {
        // BaseException instances can specify if they're retryable
        if ($exception instanceof BaseException) {
            return $exception->isRetryable();
        }

        // Check for common retryable exceptions
        $retryableExceptions = [
            'PDOException', // Database connection issues
            'RuntimeException', // General runtime issues that might be temporary
        ];

        $exceptionClass = get_class($exception);
        foreach ($retryableExceptions as $retryableClass) {
            if ($exception instanceof $retryableClass || $exceptionClass === $retryableClass) {
                // Additional checks for specific exception types
                if ($exception instanceof \PDOException) {
                    return $this->isPdoExceptionRetryable($exception);
                }
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a PDO exception is retryable based on error code
     */
    private function isPdoExceptionRetryable(\PDOException $exception): bool
    {
        $retryableCodes = [
            '08S01', // Communication link failure
            '40001', // Serialization failure
            '40P01', // Deadlock detected
            '53300', // Too many connections
            '08006', // Connection failure
            '08003', // Connection does not exist
            '08000', // Connection exception
        ];

        $sqlState = $exception->errorInfo[0] ?? null;
        return in_array($sqlState, $retryableCodes);
    }

    /**
     * Execute operation with circuit breaker pattern
     * Prevents cascading failures by temporarily stopping retries after too many failures
     */
    public function executeWithCircuitBreaker(
        callable $operation,
        string $operationName,
        array $context = [],
        int $failureThreshold = 5,
        int $timeoutSeconds = 60
    ) {
        $cacheKey = "circuit_breaker_{$operationName}";
        
        // Check if circuit breaker is open
        if ($this->isCircuitBreakerOpen($cacheKey, $failureThreshold, $timeoutSeconds)) {
            $this->logger->warning("Circuit breaker is open for operation: {$operationName}", $context);
            throw new \RuntimeException("Service temporarily unavailable due to repeated failures");
        }

        try {
            $result = $this->executeWithRetry($operation, $operationName, $context);
            $this->recordCircuitBreakerSuccess($cacheKey);
            return $result;
        } catch (Throwable $exception) {
            $this->recordCircuitBreakerFailure($cacheKey);
            throw $exception;
        }
    }

    /**
     * Check if circuit breaker is open (too many recent failures)
     */
    private function isCircuitBreakerOpen(string $cacheKey, int $threshold, int $timeout): bool
    {
        // Simple file-based circuit breaker state
        $stateFile = sys_get_temp_dir() . '/' . md5($cacheKey) . '.cb';
        
        if (!file_exists($stateFile)) {
            return false;
        }

        $state = json_decode(file_get_contents($stateFile), true);
        if (!$state) {
            return false;
        }

        // Check if timeout has passed
        if (time() - $state['last_failure'] > $timeout) {
            unlink($stateFile);
            return false;
        }

        return $state['failure_count'] >= $threshold;
    }

    /**
     * Record a circuit breaker failure
     */
    private function recordCircuitBreakerFailure(string $cacheKey): void
    {
        $stateFile = sys_get_temp_dir() . '/' . md5($cacheKey) . '.cb';
        
        $state = ['failure_count' => 0, 'last_failure' => 0];
        if (file_exists($stateFile)) {
            $existing = json_decode(file_get_contents($stateFile), true);
            if ($existing) {
                $state = $existing;
            }
        }

        $state['failure_count']++;
        $state['last_failure'] = time();

        file_put_contents($stateFile, json_encode($state));
    }

    /**
     * Record a circuit breaker success (reset failure count)
     */
    private function recordCircuitBreakerSuccess(string $cacheKey): void
    {
        $stateFile = sys_get_temp_dir() . '/' . md5($cacheKey) . '.cb';
        if (file_exists($stateFile)) {
            unlink($stateFile);
        }
    }
}