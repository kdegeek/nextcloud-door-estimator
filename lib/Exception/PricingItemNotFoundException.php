<?php

declare(strict_types=1);

namespace OCA\DoorEstimator\Exception;

/**
 * Exception thrown when a requested pricing item is not found
 */
class PricingItemNotFoundException extends BaseException
{
    public function __construct(
        string $category,
        string $item,
        ?string $frameType = null,
        int $code = 0,
        ?\Exception $previous = null
    ) {
        $frameTypeStr = $frameType ? " (frame type: {$frameType})" : "";
        $message = "Pricing item '{$item}' in category '{$category}'{$frameTypeStr} not found";
        
        $context = [
            'category' => $category,
            'item' => $item,
            'frame_type' => $frameType
        ];

        $userMessage = $frameType 
            ? "The requested {$category} item '{$item}' for {$frameType} frame type was not found in our pricing database."
            : "The requested {$category} item '{$item}' was not found in our pricing database.";

        parent::__construct($message, 'PRICING_ITEM_NOT_FOUND', $context, $userMessage, $code, $previous);
    }

    protected static function getDefaultErrorCode(): string
    {
        return 'PRICING_ITEM_NOT_FOUND';
    }

    public function getHttpStatusCode(): int
    {
        return 404; // Not Found
    }
}