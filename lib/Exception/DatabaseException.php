<?php

declare(strict_types=1);

namespace OCA\DoorEstimator\Exception;

/**
 * Exception thrown when database operations fail
 */
class DatabaseException extends BaseException
{
    protected static function getDefaultErrorCode(): string
    {
        return 'DATABASE_ERROR';
    }

    public function getHttpStatusCode(): int
    {
        return 500; // Internal Server Error
    }

    public function isRetryable(): bool
    {
        // Database connection issues might be retryable
        return in_array($this->getErrorCode(), ['CONNECTION_ERROR', 'TIMEOUT_ERROR']);
    }

    public static function forConnectionFailure(string $details = ''): self
    {
        return new self(
            "Database connection failed: {$details}",
            'CONNECTION_ERROR',
            ['details' => $details],
            'Unable to connect to the database. Please try again in a few moments.'
        );
    }

    public static function forQueryFailure(string $query, string $error): self
    {
        return new self(
            "Database query failed: {$error}",
            'QUERY_ERROR',
            ['query' => $query, 'error' => $error],
            'A database error occurred. Please try again or contact support if the problem persists.'
        );
    }

    public static function forTimeout(): self
    {
        return new self(
            'Database operation timed out',
            'TIMEOUT_ERROR',
            [],
            'The operation took too long to complete. Please try again.'
        );
    }
}