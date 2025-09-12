/**
 * Loading states and progress indicators for the Door Estimator application
 * Provides accessible loading states, progress tracking, and user feedback
 */

import { ref, computed, reactive } from 'vue'
import { a11y } from './accessibility'

export interface LoadingState {
  isLoading: boolean
  progress?: number
  message?: string
  error?: string | null
  startTime?: number
  estimatedDuration?: number
}

export interface ProgressStep {
  id: string
  label: string
  description?: string
  weight?: number
  completed?: boolean
  error?: string | null
}

export interface LoadingOptions {
  message?: string
  estimatedDuration?: number
  announceStart?: boolean
  announceProgress?: boolean
  announceCompletion?: boolean
  progressSteps?: ProgressStep[]
}

/**
 * Loading state manager with accessibility support
 */
export class LoadingManager {
  private static instance: LoadingManager
  private loadingStates: Map<string, LoadingState> = new Map()
  private progressSteps: Map<string, ProgressStep[]> = new Map()

  static getInstance(): LoadingManager {
    if (!LoadingManager.instance) {
      LoadingManager.instance = new LoadingManager()
    }
    return LoadingManager.instance
  }

  /**
   * Start a loading operation
   */
  startLoading(
    id: string, 
    options: LoadingOptions = {}
  ): void {
    const state: LoadingState = {
      isLoading: true,
      progress: 0,
      message: options.message || 'Loading...',
      error: null,
      startTime: Date.now(),
      estimatedDuration: options.estimatedDuration
    }

    this.loadingStates.set(id, state)

    if (options.progressSteps) {
      this.progressSteps.set(id, options.progressSteps)
    }

    // Announce loading start to screen readers
    if (options.announceStart !== false) {
      a11y.announce(`Loading started: ${state.message}`, 'polite')
    }
  }

  /**
   * Update loading progress
   */
  updateProgress(
    id: string, 
    progress: number, 
    message?: string,
    announceProgress = false
  ): void {
    const state = this.loadingStates.get(id)
    if (!state) return

    state.progress = Math.max(0, Math.min(100, progress))
    if (message) {
      state.message = message
    }

    this.loadingStates.set(id, state)

    // Announce progress milestones
    if (announceProgress && progress % 25 === 0) {
      a11y.announce(`${progress}% complete: ${state.message}`, 'polite')
    }
  }

  /**
   * Complete a step in a multi-step process
   */
  completeStep(
    id: string, 
    stepId: string, 
    message?: string
  ): void {
    const steps = this.progressSteps.get(id)
    if (!steps) return

    const step = steps.find(s => s.id === stepId)
    if (!step) return

    step.completed = true
    if (message) {
      step.description = message
    }

    // Calculate overall progress based on completed steps
    const totalWeight = steps.reduce((sum, s) => sum + (s.weight || 1), 0)
    const completedWeight = steps
      .filter(s => s.completed)
      .reduce((sum, s) => sum + (s.weight || 1), 0)
    
    const progress = Math.round((completedWeight / totalWeight) * 100)
    this.updateProgress(id, progress, step.label)

    // Announce step completion
    a11y.announce(`Step completed: ${step.label}`, 'polite')
  }

  /**
   * Mark a step as failed
   */
  failStep(
    id: string, 
    stepId: string, 
    error: string
  ): void {
    const steps = this.progressSteps.get(id)
    if (!steps) return

    const step = steps.find(s => s.id === stepId)
    if (!step) return

    step.error = error
    
    // Also update the main loading state
    const state = this.loadingStates.get(id)
    if (state) {
      state.error = error
      this.loadingStates.set(id, state)
    }

    // Announce step failure
    a11y.announce(`Step failed: ${step.label} - ${error}`, 'assertive')
  }

  /**
   * Complete loading operation
   */
  completeLoading(
    id: string, 
    message?: string,
    announceCompletion = true
  ): void {
    const state = this.loadingStates.get(id)
    if (!state) return

    state.isLoading = false
    state.progress = 100
    if (message) {
      state.message = message
    }

    this.loadingStates.set(id, state)

    // Announce completion
    if (announceCompletion) {
      const duration = state.startTime ? Date.now() - state.startTime : 0
      const durationText = duration > 1000 
        ? `in ${Math.round(duration / 1000)} seconds`
        : ''
      
      a11y.announce(
        `Loading completed: ${state.message} ${durationText}`.trim(), 
        'polite'
      )
    }

    // Clean up after a delay
    setTimeout(() => {
      this.loadingStates.delete(id)
      this.progressSteps.delete(id)
    }, 5000)
  }

  /**
   * Fail loading operation
   */
  failLoading(
    id: string, 
    error: string,
    announceError = true
  ): void {
    const state = this.loadingStates.get(id)
    if (!state) return

    state.isLoading = false
    state.error = error

    this.loadingStates.set(id, state)

    // Announce error
    if (announceError) {
      a11y.announce(`Loading failed: ${error}`, 'assertive')
    }
  }

  /**
   * Get loading state
   */
  getLoadingState(id: string): LoadingState | undefined {
    return this.loadingStates.get(id)
  }

  /**
   * Get progress steps
   */
  getProgressSteps(id: string): ProgressStep[] | undefined {
    return this.progressSteps.get(id)
  }

  /**
   * Check if any loading operation is active
   */
  hasActiveLoading(): boolean {
    return Array.from(this.loadingStates.values()).some(state => state.isLoading)
  }

  /**
   * Get all active loading operations
   */
  getActiveLoadingOperations(): Array<{ id: string; state: LoadingState }> {
    const active: Array<{ id: string; state: LoadingState }> = []
    
    this.loadingStates.forEach((state, id) => {
      if (state.isLoading) {
        active.push({ id, state })
      }
    })

    return active
  }

  /**
   * Cancel loading operation
   */
  cancelLoading(id: string, reason?: string): void {
    const state = this.loadingStates.get(id)
    if (!state) return

    state.isLoading = false
    state.error = reason || 'Operation cancelled'

    this.loadingStates.set(id, state)

    // Announce cancellation
    a11y.announce(`Loading cancelled: ${reason || 'Operation cancelled'}`, 'polite')

    // Clean up
    setTimeout(() => {
      this.loadingStates.delete(id)
      this.progressSteps.delete(id)
    }, 2000)
  }

  /**
   * Get estimated time remaining
   */
  getEstimatedTimeRemaining(id: string): number | null {
    const state = this.loadingStates.get(id)
    if (!state || !state.startTime || !state.estimatedDuration || !state.progress) {
      return null
    }

    const elapsed = Date.now() - state.startTime
    const progressRatio = state.progress / 100
    
    if (progressRatio <= 0) return null

    const estimatedTotal = elapsed / progressRatio
    const remaining = estimatedTotal - elapsed

    return Math.max(0, remaining)
  }

  /**
   * Clean up all loading states
   */
  cleanup(): void {
    this.loadingStates.clear()
    this.progressSteps.clear()
  }
}

/**
 * Vue composable for loading states
 */
export function useLoading(id?: string) {
  const manager = LoadingManager.getInstance()
  const loadingId = id || `loading-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`

  // Reactive state
  const state = reactive<LoadingState>({
    isLoading: false,
    progress: 0,
    message: '',
    error: null
  })

  // Update reactive state when manager state changes
  const updateState = () => {
    const managerState = manager.getLoadingState(loadingId)
    if (managerState) {
      Object.assign(state, managerState)
    }
  }

  // Computed properties
  const isLoading = computed(() => state.isLoading)
  const progress = computed(() => state.progress || 0)
  const message = computed(() => state.message || '')
  const error = computed(() => state.error)
  const hasError = computed(() => !!state.error)

  const progressSteps = computed(() => manager.getProgressSteps(loadingId) || [])
  const completedSteps = computed(() => progressSteps.value.filter(step => step.completed))
  const failedSteps = computed(() => progressSteps.value.filter(step => step.error))

  const estimatedTimeRemaining = computed(() => {
    const remaining = manager.getEstimatedTimeRemaining(loadingId)
    return remaining ? Math.ceil(remaining / 1000) : null
  })

  // Methods
  const startLoading = (options: LoadingOptions = {}) => {
    manager.startLoading(loadingId, options)
    updateState()
  }

  const updateProgress = (progress: number, message?: string, announce = false) => {
    manager.updateProgress(loadingId, progress, message, announce)
    updateState()
  }

  const completeStep = (stepId: string, message?: string) => {
    manager.completeStep(loadingId, stepId, message)
    updateState()
  }

  const failStep = (stepId: string, error: string) => {
    manager.failStep(loadingId, stepId, error)
    updateState()
  }

  const completeLoading = (message?: string, announce = true) => {
    manager.completeLoading(loadingId, message, announce)
    updateState()
  }

  const failLoading = (error: string, announce = true) => {
    manager.failLoading(loadingId, error, announce)
    updateState()
  }

  const cancelLoading = (reason?: string) => {
    manager.cancelLoading(loadingId, reason)
    updateState()
  }

  return {
    // State
    isLoading,
    progress,
    message,
    error,
    hasError,
    progressSteps,
    completedSteps,
    failedSteps,
    estimatedTimeRemaining,

    // Methods
    startLoading,
    updateProgress,
    completeStep,
    failStep,
    completeLoading,
    failLoading,
    cancelLoading,

    // Utilities
    loadingId
  }
}

/**
 * Global loading utilities
 */
export const loading = {
  /**
   * Start a global loading operation
   */
  start: (id: string, options: LoadingOptions = {}) => {
    LoadingManager.getInstance().startLoading(id, options)
  },

  /**
   * Update global loading progress
   */
  updateProgress: (id: string, progress: number, message?: string) => {
    LoadingManager.getInstance().updateProgress(id, progress, message)
  },

  /**
   * Complete global loading operation
   */
  complete: (id: string, message?: string) => {
    LoadingManager.getInstance().completeLoading(id, message)
  },

  /**
   * Fail global loading operation
   */
  fail: (id: string, error: string) => {
    LoadingManager.getInstance().failLoading(id, error)
  },

  /**
   * Check if any loading is active
   */
  hasActive: () => {
    return LoadingManager.getInstance().hasActiveLoading()
  },

  /**
   * Get all active loading operations
   */
  getActive: () => {
    return LoadingManager.getInstance().getActiveLoadingOperations()
  }
}

/**
 * Loading component props interface
 */
export interface LoadingComponentProps {
  loading?: boolean
  progress?: number
  message?: string
  error?: string | null
  size?: 'small' | 'medium' | 'large'
  variant?: 'spinner' | 'progress' | 'skeleton'
  showProgress?: boolean
  showMessage?: boolean
  overlay?: boolean
  accessible?: boolean
}

/**
 * Generate loading component classes
 */
export function getLoadingClasses(props: LoadingComponentProps): string[] {
  const classes = ['loading-component']

  if (props.size) {
    classes.push(`loading-component--${props.size}`)
  }

  if (props.variant) {
    classes.push(`loading-component--${props.variant}`)
  }

  if (props.overlay) {
    classes.push('loading-component--overlay')
  }

  if (props.error) {
    classes.push('loading-component--error')
  }

  return classes
}