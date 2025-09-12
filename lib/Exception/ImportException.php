<?php

declare(strict_types=1);

namespace OCA\DoorEstimator\Exception;

/**
 * Exception thrown when data import operations fail
 */
class ImportException extends BaseException
{
    private array $importErrors;

    public function __construct(
        string $message,
        array $importErrors = [],
        string $errorCode = '',
        array $context = [],
        ?string $userMessage = null,
        int $code = 0,
        ?\Exception $previous = null
    ) {
        parent::__construct($message, $errorCode, $context, $userMessage, $code, $previous);
        $this->importErrors = $importErrors;
    }

    protected static function getDefaultErrorCode(): string
    {
        return 'IMPORT_ERROR';
    }

    public function getHttpStatusCode(): int
    {
        return 400; // Bad Request
    }

    public function getImportErrors(): array
    {
        return $this->importErrors;
    }

    public static function forFileFormat(string $filename, string $expectedFormat): self
    {
        return new self(
            "Invalid file format for '{$filename}', expected {$expectedFormat}",
            [],
            'INVALID_FILE_FORMAT',
            ['filename' => $filename, 'expected_format' => $expectedFormat],
            "The uploaded file must be in {$expectedFormat} format. Please check your file and try again."
        );
    }

    public static function forFileSize(string $filename, int $actualSize, int $maxSize): self
    {
        $maxSizeMB = round($maxSize / 1024 / 1024, 1);
        $actualSizeMB = round($actualSize / 1024 / 1024, 1);
        
        return new self(
            "File '{$filename}' is too large ({$actualSizeMB}MB), maximum allowed is {$maxSizeMB}MB",
            [],
            'FILE_TOO_LARGE',
            ['filename' => $filename, 'actual_size' => $actualSize, 'max_size' => $maxSize],
            "The uploaded file is too large ({$actualSizeMB}MB). Please use a file smaller than {$maxSizeMB}MB."
        );
    }

    public static function forDataValidation(array $errors): self
    {
        $errorCount = count($errors);
        return new self(
            "Import validation failed with {$errorCount} errors",
            $errors,
            'IMPORT_VALIDATION_ERROR',
            ['error_count' => $errorCount],
            "The import file contains {$errorCount} validation errors. Please review and correct the highlighted issues."
        );
    }

    public function isRetryable(): bool
    {
        return in_array($this->getErrorCode(), ['TEMPORARY_ERROR', 'NETWORK_ERROR']);
    }

    public function toArray(): array
    {
        $data = parent::toArray();
        $data['import_errors'] = $this->importErrors;
        return $data;
    }
}