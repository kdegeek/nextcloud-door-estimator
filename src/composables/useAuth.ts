/**
 * Vue composable for authentication and authorization
 * 
 * Provides reactive authentication state and methods for Vue components
 */

import { ref, computed, onMounted, onUnmounted } from 'vue'
import { authService, type User, type SessionInfo } from '../services/AuthService'

export function useAuth() {
  const user = ref<User | null>(null)
  const sessionInfo = ref<SessionInfo | null>(null)
  const loading = ref(true)
  const error = ref<string | null>(null)

  // Computed properties
  const isAuthenticated = computed(() => user.value !== null)
  const isAdmin = computed(() => user.value?.isAdmin || false)
  const userId = computed(() => user.value?.uid || null)
  const displayName = computed(() => user.value?.displayName || '')
  const groups = computed(() => user.value?.groups || [])

  // Session warning state
  const sessionExpiring = ref(false)
  const timeRemaining = ref(0)

  /**
   * Initialize authentication state
   */
  const initialize = async () => {
    try {
      loading.value = true
      error.value = null
      
      await authService.initialize()
      user.value = authService.getCurrentUser()
      sessionInfo.value = await authService.getSessionInfo()
      
      // Set up event listeners
      setupEventListeners()
    } catch (err) {
      error.value = err instanceof Error ? err.message : 'Authentication failed'
      console.error('Auth initialization failed:', err)
    } finally {
      loading.value = false
    }
  }

  /**
   * Set up event listeners for session events
   */
  const setupEventListeners = () => {
    window.addEventListener('session-warning', handleSessionWarning)
    window.addEventListener('session-expired', handleSessionExpired)
    window.addEventListener('access-denied', handleAccessDenied)
  }

  /**
   * Clean up event listeners
   */
  const cleanupEventListeners = () => {
    window.removeEventListener('session-warning', handleSessionWarning)
    window.removeEventListener('session-expired', handleSessionExpired)
    window.removeEventListener('access-denied', handleAccessDenied)
  }

  /**
   * Handle session warning event
   */
  const handleSessionWarning = (event: CustomEvent) => {
    sessionExpiring.value = true
    timeRemaining.value = event.detail.timeRemaining
  }

  /**
   * Handle session expired event
   */
  const handleSessionExpired = () => {
    user.value = null
    sessionInfo.value = null
    sessionExpiring.value = false
    timeRemaining.value = 0
    
    // Redirect to login or show login modal
    window.location.reload()
  }

  /**
   * Handle access denied event
   */
  const handleAccessDenied = (event: CustomEvent) => {
    error.value = event.detail.error?.message || 'Access denied'
  }

  /**
   * Check if user has permission for a resource and action
   */
  const hasPermission = (resource: string, action: string, context: Record<string, any> = {}): boolean => {
    return authService.hasPermission(resource, action, context)
  }

  /**
   * Require specific permission, throw error if not authorized
   */
  const requirePermission = (resource: string, action: string, context: Record<string, any> = {}): void => {
    authService.requirePermission(resource, action, context)
  }

  /**
   * Require admin privileges
   */
  const requireAdmin = (): void => {
    authService.requireAdmin()
  }

  /**
   * Check if user is in a specific group
   */
  const isInGroup = (groupId: string): boolean => {
    return authService.isInGroup(groupId)
  }

  /**
   * Refresh user session
   */
  const refreshSession = async (): Promise<void> => {
    try {
      await authService.refreshSession()
      sessionExpiring.value = false
      timeRemaining.value = 0
    } catch (err) {
      error.value = err instanceof Error ? err.message : 'Failed to refresh session'
      throw err
    }
  }

  /**
   * Get user context for logging
   */
  const getUserContext = (): Record<string, any> => {
    return authService.getUserContext()
  }

  /**
   * Handle authentication errors from API calls
   */
  const handleAuthError = (err: any): void => {
    authService.handleAuthError(err)
  }

  /**
   * Clear error state
   */
  const clearError = (): void => {
    error.value = null
  }

  /**
   * Format time remaining for display
   */
  const formatTimeRemaining = (seconds: number): string => {
    const minutes = Math.floor(seconds / 60)
    const remainingSeconds = seconds % 60
    
    if (minutes > 0) {
      return `${minutes}m ${remainingSeconds}s`
    }
    return `${remainingSeconds}s`
  }

  // Lifecycle hooks
  onMounted(() => {
    initialize()
  })

  onUnmounted(() => {
    cleanupEventListeners()
  })

  return {
    // State
    user: readonly(user),
    sessionInfo: readonly(sessionInfo),
    loading: readonly(loading),
    error: readonly(error),
    sessionExpiring: readonly(sessionExpiring),
    timeRemaining: readonly(timeRemaining),

    // Computed
    isAuthenticated,
    isAdmin,
    userId,
    displayName,
    groups,

    // Methods
    initialize,
    hasPermission,
    requirePermission,
    requireAdmin,
    isInGroup,
    refreshSession,
    getUserContext,
    handleAuthError,
    clearError,
    formatTimeRemaining
  }
}

// Global auth state for use across the application
let globalAuthState: ReturnType<typeof useAuth> | null = null

/**
 * Get global authentication state
 */
export function useGlobalAuth() {
  if (!globalAuthState) {
    globalAuthState = useAuth()
  }
  return globalAuthState
}