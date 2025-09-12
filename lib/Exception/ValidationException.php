<?php

declare(strict_types=1);

namespace OCA\DoorEstimator\Exception;

/**
 * Exception thrown when input validation fails
 */
class ValidationException extends BaseException
{
    private array $validationErrors;

    public function __construct(
        string $message,
        array $validationErrors = [],
        string $errorCode = '',
        array $context = [],
        ?string $userMessage = null,
        int $code = 0,
        ?\Exception $previous = null
    ) {
        parent::__construct($message, $errorCode, $context, $userMessage, $code, $previous);
        $this->validationErrors = $validationErrors;
    }

    protected static function getDefaultErrorCode(): string
    {
        return 'VALIDATION_ERROR';
    }

    public function getHttpStatusCode(): int
    {
        return 400; // Bad Request
    }

    public function getValidationErrors(): array
    {
        return $this->validationErrors;
    }

    public static function forField(string $field, string $reason, $value = null): self
    {
        $context = ['field' => $field, 'reason' => $reason];
        if ($value !== null) {
            $context['value'] = $value;
        }

        return new self(
            "Validation failed for field '{$field}': {$reason}",
            [$field => $reason],
            'VALIDATION_ERROR',
            $context,
            "Invalid {$field}: {$reason}"
        );
    }

    public static function forMultipleFields(array $errors): self
    {
        $fieldNames = array_keys($errors);
        $message = 'Validation failed for fields: ' . implode(', ', $fieldNames);
        
        return new self(
            $message,
            $errors,
            'VALIDATION_ERROR',
            ['errors' => $errors],
            'Please correct the highlighted fields and try again.'
        );
    }

    public function toArray(): array
    {
        $data = parent::toArray();
        $data['validation_errors'] = $this->validationErrors;
        return $data;
    }
}