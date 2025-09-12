<?php

declare(strict_types=1);

namespace OCA\DoorEstimator\Exception;

/**
 * Exception thrown when file operations fail
 */
class FileOperationException extends BaseException
{
    protected static function getDefaultErrorCode(): string
    {
        return 'FILE_OPERATION_ERROR';
    }

    public function getHttpStatusCode(): int
    {
        return 500; // Internal Server Error
    }

    public function isRetryable(): bool
    {
        return in_array($this->getErrorCode(), ['TEMPORARY_ERROR', 'DISK_FULL']);
    }

    public static function forUploadFailure(string $filename, string $reason): self
    {
        return new self(
            "File upload failed for '{$filename}': {$reason}",
            'UPLOAD_ERROR',
            ['filename' => $filename, 'reason' => $reason],
            "Failed to upload '{$filename}'. {$reason}"
        );
    }

    public static function forPdfGeneration(string $error): self
    {
        return new self(
            "PDF generation failed: {$error}",
            'PDF_GENERATION_ERROR',
            ['error' => $error],
            'Unable to generate PDF. Please try again or contact support if the problem persists.'
        );
    }

    public static function forStorageFailure(string $operation, string $path): self
    {
        return new self(
            "Storage operation '{$operation}' failed for path '{$path}'",
            'STORAGE_ERROR',
            ['operation' => $operation, 'path' => $path],
            'A file storage error occurred. Please try again.'
        );
    }
}