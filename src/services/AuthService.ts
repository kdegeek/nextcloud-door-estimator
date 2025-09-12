/**
 * Frontend authentication service for Door Estimator
 * 
 * Handles user authentication, session management, and authorization
 * on the client side with integration to Nextcloud's authentication system.
 */

import { getCurrentUser } from '@nextcloud/auth'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'

export interface User {
  uid: string
  displayName: string
  isAdmin: boolean
  groups: string[]
}

export interface SessionInfo {
  isAuthenticated: boolean
  user: User | null
  sessionExpiring: boolean
  timeRemaining: number
}

class AuthService {
  private user: User | null = null
  private sessionCheckInterval: number | null = null
  private readonly SESSION_CHECK_INTERVAL = 60000 // 1 minute
  private readonly SESSION_WARNING_THRESHOLD = 300 // 5 minutes

  /**
   * Initialize the authentication service
   */
  async initialize(): Promise<void> {
    await this.loadCurrentUser()
    this.startSessionMonitoring()
  }

  /**
   * Load current user information from Nextcloud
   */
  private async loadCurrentUser(): Promise<void> {
    try {
      const nextcloudUser = getCurrentUser()
      if (!nextcloudUser) {
        this.user = null
        return
      }

      // Get additional user information from our API
      const response = await axios.get(generateUrl('/apps/door_estimator/api/user/info'))
      
      this.user = {
        uid: nextcloudUser.uid,
        displayName: nextcloudUser.displayName,
        isAdmin: response.data.isAdmin || false,
        groups: response.data.groups || []
      }
    } catch (error) {
      console.error('Failed to load user information:', error)
      this.user = null
    }
  }

  /**
   * Get current user information
   */
  getCurrentUser(): User | null {
    return this.user
  }

  /**
   * Check if user is authenticated
   */
  isAuthenticated(): boolean {
    return this.user !== null
  }

  /**
   * Check if current user is an administrator
   */
  isAdmin(): boolean {
    return this.user?.isAdmin || false
  }

  /**
   * Check if user is in a specific group
   */
  isInGroup(groupId: string): boolean {
    return this.user?.groups.includes(groupId) || false
  }

  /**
   * Check if user has permission for a specific resource and action
   */
  hasPermission(resource: string, action: string, context: Record<string, any> = {}): boolean {
    if (!this.isAuthenticated()) {
      return false
    }

    // Admin users have all permissions
    if (this.isAdmin()) {
      return true
    }

    // Resource-specific permission logic
    switch (resource) {
      case 'pricing':
        // Only admins can modify pricing data
        return action === 'read'
        
      case 'quote':
        // Users can manage their own quotes
        if (context.ownerId) {
          return context.ownerId === this.user?.uid
        }
        return true // Allow creating new quotes
        
      case 'admin':
        // Only admins can access admin features
        return false
        
      default:
        // Default to deny for unknown resources
        return false
    }
  }

  /**
   * Require specific permission, throw error if not authorized
   */
  requirePermission(resource: string, action: string, context: Record<string, any> = {}): void {
    if (!this.hasPermission(resource, action, context)) {
      throw new Error(`Access denied: ${action} on ${resource}`)
    }
  }

  /**
   * Require admin privileges
   */
  requireAdmin(): void {
    if (!this.isAdmin()) {
      throw new Error('Administrator privileges required')
    }
  }

  /**
   * Get session information
   */
  async getSessionInfo(): Promise<SessionInfo> {
    try {
      const response = await axios.get(generateUrl('/apps/door_estimator/api/session/info'))
      
      return {
        isAuthenticated: this.isAuthenticated(),
        user: this.user,
        sessionExpiring: response.data.sessionExpiring || false,
        timeRemaining: response.data.timeRemaining || 0
      }
    } catch (error) {
      console.error('Failed to get session info:', error)
      return {
        isAuthenticated: this.isAuthenticated(),
        user: this.user,
        sessionExpiring: false,
        timeRemaining: 0
      }
    }
  }

  /**
   * Refresh user session to extend timeout
   */
  async refreshSession(): Promise<void> {
    try {
      await axios.post(generateUrl('/apps/door_estimator/api/session/refresh'))
    } catch (error) {
      console.error('Failed to refresh session:', error)
      throw error
    }
  }

  /**
   * Start monitoring session status
   */
  private startSessionMonitoring(): void {
    if (this.sessionCheckInterval) {
      clearInterval(this.sessionCheckInterval)
    }

    this.sessionCheckInterval = window.setInterval(async () => {
      try {
        const sessionInfo = await this.getSessionInfo()
        
        // Emit session events for components to listen to
        if (sessionInfo.sessionExpiring) {
          this.emitSessionWarning(sessionInfo.timeRemaining)
        }
        
        if (!sessionInfo.isAuthenticated) {
          this.emitSessionExpired()
        }
      } catch (error) {
        console.error('Session monitoring error:', error)
      }
    }, this.SESSION_CHECK_INTERVAL)
  }

  /**
   * Stop session monitoring
   */
  stopSessionMonitoring(): void {
    if (this.sessionCheckInterval) {
      clearInterval(this.sessionCheckInterval)
      this.sessionCheckInterval = null
    }
  }

  /**
   * Emit session warning event
   */
  private emitSessionWarning(timeRemaining: number): void {
    const event = new CustomEvent('session-warning', {
      detail: { timeRemaining }
    })
    window.dispatchEvent(event)
  }

  /**
   * Emit session expired event
   */
  private emitSessionExpired(): void {
    const event = new CustomEvent('session-expired')
    window.dispatchEvent(event)
  }

  /**
   * Handle authentication errors from API responses
   */
  handleAuthError(error: any): void {
    if (error.response?.status === 401) {
      this.user = null
      this.emitSessionExpired()
    } else if (error.response?.status === 403) {
      const event = new CustomEvent('access-denied', {
        detail: { error: error.response.data }
      })
      window.dispatchEvent(event)
    }
  }

  /**
   * Get user context for logging and debugging
   */
  getUserContext(): Record<string, any> {
    return {
      userId: this.user?.uid || null,
      displayName: this.user?.displayName || null,
      isAdmin: this.isAdmin(),
      groups: this.user?.groups || [],
      isAuthenticated: this.isAuthenticated()
    }
  }

  /**
   * Cleanup resources
   */
  destroy(): void {
    this.stopSessionMonitoring()
    this.user = null
  }
}

// Create singleton instance
export const authService = new AuthService()

// Auto-initialize when imported
authService.initialize().catch(error => {
  console.error('Failed to initialize auth service:', error)
})

export default authService