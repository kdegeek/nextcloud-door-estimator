<?php

declare(strict_types=1);

namespace OCA\DoorEstimator\Exception;

use Exception;

/**
 * Base exception class for all Door Estimator exceptions
 */
abstract class BaseException extends Exception
{
    protected string $errorCode;
    protected array $context = [];
    protected ?string $userMessage = null;

    public function __construct(
        string $message = '',
        string $errorCode = '',
        array $context = [],
        ?string $userMessage = null,
        int $code = 0,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->errorCode = $errorCode ?: static::getDefaultErrorCode();
        $this->context = $context;
        $this->userMessage = $userMessage;
    }

    abstract protected static function getDefaultErrorCode(): string;

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function getUserMessage(): ?string
    {
        return $this->userMessage;
    }

    public function setUserMessage(string $userMessage): void
    {
        $this->userMessage = $userMessage;
    }

    /**
     * Get HTTP status code for this exception
     */
    abstract public function getHttpStatusCode(): int;

    /**
     * Check if this is a retryable error
     */
    public function isRetryable(): bool
    {
        return false;
    }

    /**
     * Get structured error data for API responses
     */
    public function toArray(): array
    {
        return [
            'code' => $this->getErrorCode(),
            'message' => $this->getUserMessage() ?: $this->getMessage(),
            'context' => $this->getContext(),
            'retryable' => $this->isRetryable()
        ];
    }
}