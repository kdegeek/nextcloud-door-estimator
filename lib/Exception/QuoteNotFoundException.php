<?php

declare(strict_types=1);

namespace OCA\DoorEstimator\Exception;

/**
 * Exception thrown when a requested quote is not found or user doesn't have access
 */
class QuoteNotFoundException extends BaseException
{
    public function __construct(int $quoteId, int $code = 0, ?\Exception $previous = null)
    {
        $message = "Quote with ID {$quoteId} not found or access denied";
        $context = ['quote_id' => $quoteId];
        $userMessage = "The requested quote could not be found or you don't have permission to access it.";

        parent::__construct($message, 'QUOTE_NOT_FOUND', $context, $userMessage, $code, $previous);
    }

    protected static function getDefaultErrorCode(): string
    {
        return 'QUOTE_NOT_FOUND';
    }

    public function getHttpStatusCode(): int
    {
        return 404; // Not Found
    }
}