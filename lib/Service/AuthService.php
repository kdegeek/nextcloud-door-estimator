<?php

declare(strict_types=1);

namespace OCA\DoorEstimator\Service;

use OCP\IUserSession;
use OCP\IGroupManager;
use OCP\IConfig;
use OCP\IRequest;
use Psr\Log\LoggerInterface;
use OCA\DoorEstimator\Exception\AuthorizationException;

/**
 * Authentication and authorization service for Door Estimator
 * 
 * Provides centralized authentication, authorization, and session management
 * with comprehensive logging and security features.
 */
class AuthService
{
    private IUserSession $userSession;
    private IGroupManager $groupManager;
    private IConfig $config;
    private IRequest $request;
    private LoggerInterface $logger;

    public function __construct(
        IUserSession $userSession,
        IGroupManager $groupManager,
        IConfig $config,
        IRequest $request,
        LoggerInterface $logger
    ) {
        $this->userSession = $userSession;
        $this->groupManager = $groupManager;
        $this->config = $config;
        $this->request = $request;
        $this->logger = $logger;
    }

    /**
     * Get the current authenticated user
     * 
     * @return \OCP\IUser|null Current user or null if not authenticated
     */
    public function getCurrentUser(): ?\OCP\IUser
    {
        return $this->userSession->getUser();
    }

    /**
     * Get the current authenticated user ID
     * 
     * @return string User ID
     * @throws AuthorizationException If user is not authenticated
     */
    public function getCurrentUserId(): string
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            $this->logSecurityEvent('unauthenticated_access_attempt');
            throw AuthorizationException::forResourceAccess('application', 'access');
        }
        return $user->getUID();
    }

    /**
     * Check if the current user is authenticated
     * 
     * @return bool True if user is authenticated, false otherwise
     */
    public function isAuthenticated(): bool
    {
        return $this->getCurrentUser() !== null && $this->userSession->isLoggedIn();
    }

    /**
     * Validate user session and ensure user is authenticated
     * 
     * @throws AuthorizationException If user is not authenticated
     */
    public function validateUserSession(): void
    {
        if (!$this->isAuthenticated()) {
            $this->logSecurityEvent('invalid_session_access_attempt');
            throw AuthorizationException::forResourceAccess('application', 'access');
        }

        // Additional session validation
        $user = $this->getCurrentUser();
        if (!$user || !$this->userSession->isLoggedIn()) {
            $this->logSecurityEvent('session_validation_failed', [
                'user_id' => $user ? $user->getUID() : 'unknown'
            ]);
            throw AuthorizationException::forResourceAccess('application', 'access');
        }
    }

    /**
     * Check if the current user is an administrator
     * 
     * @return bool True if user is admin, false otherwise
     */
    public function isAdmin(): bool
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            return false;
        }

        return $this->groupManager->isAdmin($user->getUID());
    }

    /**
     * Check if a specific user is an administrator
     * 
     * @param string $userId User ID to check
     * @return bool True if user is admin, false otherwise
     */
    public function isUserAdmin(string $userId): bool
    {
        return $this->groupManager->isAdmin($userId);
    }

    /**
     * Ensure current user has admin privileges
     * 
     * @throws AuthorizationException If user is not admin
     */
    public function requireAdmin(): void
    {
        $this->validateUserSession();
        
        if (!$this->isAdmin()) {
            $user = $this->getCurrentUser();
            $this->logSecurityEvent('admin_access_denied', [
                'user_id' => $user ? $user->getUID() : 'unknown'
            ]);
            throw AuthorizationException::forAdminRequired();
        }
    }

    /**
     * Check if current user is in a specific group
     * 
     * @param string $groupId Group ID to check
     * @return bool True if user is in group, false otherwise
     */
    public function isInGroup(string $groupId): bool
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            return false;
        }

        return $this->groupManager->isInGroup($user->getUID(), $groupId);
    }

    /**
     * Get user's groups
     * 
     * @return array List of group IDs the user belongs to
     */
    public function getUserGroups(): array
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            return [];
        }

        $groups = $this->groupManager->getUserGroups($user);
        return array_map(fn($group) => $group->getGID(), $groups);
    }

    /**
     * Check if user has permission for a specific resource and action
     * 
     * @param string $resource Resource type (e.g., 'quote', 'pricing')
     * @param string $action Action type (e.g., 'read', 'write', 'delete')
     * @param array $context Additional context for permission checking
     * @return bool True if user has permission, false otherwise
     */
    public function hasPermission(string $resource, string $action, array $context = []): bool
    {
        // Ensure user is authenticated
        if (!$this->isAuthenticated()) {
            return false;
        }

        // Admin users have all permissions
        if ($this->isAdmin()) {
            return true;
        }

        // Resource-specific permission logic
        switch ($resource) {
            case 'pricing':
                // Only admins can modify pricing data
                return $action === 'read';
                
            case 'quote':
                // Users can manage their own quotes
                if (isset($context['owner_id'])) {
                    return $context['owner_id'] === $this->getCurrentUserId();
                }
                return true; // Allow creating new quotes
                
            case 'admin':
                // Only admins can access admin features
                return false;
                
            default:
                // Default to deny for unknown resources
                return false;
        }
    }

    /**
     * Require specific permission for a resource and action
     * 
     * @param string $resource Resource type
     * @param string $action Action type
     * @param array $context Additional context
     * @throws AuthorizationException If user lacks permission
     */
    public function requirePermission(string $resource, string $action, array $context = []): void
    {
        if (!$this->hasPermission($resource, $action, $context)) {
            $this->logSecurityEvent('permission_denied', [
                'resource' => $resource,
                'action' => $action,
                'context' => $context
            ]);
            throw AuthorizationException::forResourceAccess($resource, $action);
        }
    }

    /**
     * Get session timeout configuration
     * 
     * @return int Session timeout in seconds
     */
    public function getSessionTimeout(): int
    {
        return (int)$this->config->getSystemValue('session_lifetime', 60 * 60 * 24); // Default 24 hours
    }

    /**
     * Check if session is about to expire
     * 
     * @param int $warningThreshold Warning threshold in seconds (default 5 minutes)
     * @return bool True if session is about to expire
     */
    public function isSessionExpiring(int $warningThreshold = 300): bool
    {
        if (!$this->isAuthenticated()) {
            return true;
        }

        $sessionTimeout = $this->getSessionTimeout();
        $lastActivity = $this->userSession->getUser()->getLastLogin();
        $timeRemaining = $sessionTimeout - (time() - $lastActivity);
        
        return $timeRemaining <= $warningThreshold;
    }

    /**
     * Refresh user session to extend timeout
     */
    public function refreshSession(): void
    {
        if ($this->isAuthenticated()) {
            // Touch the session to update last activity
            $this->userSession->getUser()->updateLastLoginTimestamp();
            
            $this->logSecurityEvent('session_refreshed');
        }
    }

    /**
     * Log security events for audit purposes
     * 
     * @param string $event Event type
     * @param array $context Additional context
     */
    private function logSecurityEvent(string $event, array $context = []): void
    {
        $user = $this->getCurrentUser();
        $baseContext = [
            'user_id' => $user ? $user->getUID() : 'anonymous',
            'ip' => $this->request->getRemoteAddress(),
            'user_agent' => $this->request->getHeader('User-Agent'),
            'endpoint' => $this->request->getRequestUri(),
            'timestamp' => time()
        ];
        
        $this->logger->info("Security event: {$event}", array_merge($baseContext, $context));
    }

    /**
     * Get user context for logging and auditing
     * 
     * @return array User context information
     */
    public function getUserContext(): array
    {
        $user = $this->getCurrentUser();
        return [
            'user_id' => $user ? $user->getUID() : null,
            'display_name' => $user ? $user->getDisplayName() : null,
            'is_admin' => $this->isAdmin(),
            'groups' => $this->getUserGroups(),
            'ip' => $this->request->getRemoteAddress(),
            'user_agent' => $this->request->getHeader('User-Agent')
        ];
    }
}