<?php

declare(strict_types=1);

namespace OCA\DoorEstimator\Exception;

/**
 * Exception thrown when user lacks required permissions
 */
class AuthorizationException extends BaseException
{
    protected static function getDefaultErrorCode(): string
    {
        return 'AUTHORIZATION_ERROR';
    }

    public function getHttpStatusCode(): int
    {
        return 403; // Forbidden
    }

    public static function forAdminRequired(): self
    {
        return new self(
            'Administrator privileges required for this operation',
            'ADMIN_REQUIRED',
            [],
            'You need administrator privileges to perform this action.'
        );
    }

    public static function forResourceAccess(string $resource, string $action): self
    {
        return new self(
            "Access denied for {$action} on {$resource}",
            'ACCESS_DENIED',
            ['resource' => $resource, 'action' => $action],
            "You don't have permission to {$action} this {$resource}."
        );
    }
}