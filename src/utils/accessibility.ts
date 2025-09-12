/**
 * Accessibility utilities for the Door Estimator application
 * Provides functions for screen reader support, keyboard navigation, and ARIA management
 */

export interface AriaLiveRegion {
  element: HTMLElement
  type: 'polite' | 'assertive'
}

export class AccessibilityManager {
  private static instance: AccessibilityManager
  private liveRegions: Map<string, AriaLiveRegion> = new Map()
  private focusHistory: HTMLElement[] = []

  static getInstance(): AccessibilityManager {
    if (!AccessibilityManager.instance) {
      AccessibilityManager.instance = new AccessibilityManager()
    }
    return AccessibilityManager.instance
  }

  /**
   * Create or get an ARIA live region for announcements
   */
  createLiveRegion(id: string, type: 'polite' | 'assertive' = 'polite'): AriaLiveRegion {
    if (this.liveRegions.has(id)) {
      return this.liveRegions.get(id)!
    }

    const element = document.createElement('div')
    element.id = `live-region-${id}`
    element.setAttribute('aria-live', type)
    element.setAttribute('aria-atomic', 'true')
    element.className = 'sr-only'
    element.style.cssText = `
      position: absolute !important;
      width: 1px !important;
      height: 1px !important;
      padding: 0 !important;
      margin: -1px !important;
      overflow: hidden !important;
      clip: rect(0, 0, 0, 0) !important;
      white-space: nowrap !important;
      border: 0 !important;
    `

    document.body.appendChild(element)

    const liveRegion: AriaLiveRegion = { element, type }
    this.liveRegions.set(id, liveRegion)
    return liveRegion
  }

  /**
   * Announce a message to screen readers
   */
  announce(message: string, priority: 'polite' | 'assertive' = 'polite'): void {
    const regionId = priority === 'assertive' ? 'alerts' : 'announcements'
    const liveRegion = this.createLiveRegion(regionId, priority)
    
    // Clear previous message
    liveRegion.element.textContent = ''
    
    // Add new message after a brief delay to ensure it's announced
    setTimeout(() => {
      liveRegion.element.textContent = message
    }, 100)

    // Clear the message after announcement
    setTimeout(() => {
      liveRegion.element.textContent = ''
    }, 1000)
  }

  /**
   * Manage focus for modal dialogs and overlays
   */
  trapFocus(container: HTMLElement): () => void {
    const focusableElements = this.getFocusableElements(container)
    if (focusableElements.length === 0) return () => {}

    const firstElement = focusableElements[0]
    const lastElement = focusableElements[focusableElements.length - 1]

    // Store the currently focused element
    const previouslyFocused = document.activeElement as HTMLElement
    this.focusHistory.push(previouslyFocused)

    // Focus the first element
    firstElement.focus()

    const handleTabKey = (event: KeyboardEvent) => {
      if (event.key !== 'Tab') return

      if (event.shiftKey) {
        // Shift + Tab
        if (document.activeElement === firstElement) {
          event.preventDefault()
          lastElement.focus()
        }
      } else {
        // Tab
        if (document.activeElement === lastElement) {
          event.preventDefault()
          firstElement.focus()
        }
      }
    }

    const handleEscapeKey = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        this.releaseFocus()
      }
    }

    container.addEventListener('keydown', handleTabKey)
    container.addEventListener('keydown', handleEscapeKey)

    // Return cleanup function
    return () => {
      container.removeEventListener('keydown', handleTabKey)
      container.removeEventListener('keydown', handleEscapeKey)
      this.releaseFocus()
    }
  }

  /**
   * Release focus trap and return focus to previous element
   */
  releaseFocus(): void {
    const previousElement = this.focusHistory.pop()
    if (previousElement && document.contains(previousElement)) {
      previousElement.focus()
    }
  }

  /**
   * Get all focusable elements within a container
   */
  getFocusableElements(container: HTMLElement): HTMLElement[] {
    const focusableSelectors = [
      'button:not([disabled])',
      'input:not([disabled])',
      'select:not([disabled])',
      'textarea:not([disabled])',
      'a[href]',
      '[tabindex]:not([tabindex="-1"])',
      '[contenteditable="true"]'
    ].join(', ')

    const elements = Array.from(container.querySelectorAll(focusableSelectors)) as HTMLElement[]
    return elements.filter(element => {
      return element.offsetWidth > 0 && 
             element.offsetHeight > 0 && 
             !element.hidden &&
             window.getComputedStyle(element).visibility !== 'hidden'
    })
  }

  /**
   * Set up keyboard navigation for a list of items
   */
  setupArrowKeyNavigation(
    container: HTMLElement, 
    itemSelector: string,
    options: {
      wrap?: boolean
      orientation?: 'horizontal' | 'vertical' | 'both'
      onActivate?: (element: HTMLElement) => void
    } = {}
  ): () => void {
    const { wrap = true, orientation = 'vertical', onActivate } = options

    const handleKeyDown = (event: KeyboardEvent) => {
      const items = Array.from(container.querySelectorAll(itemSelector)) as HTMLElement[]
      const currentIndex = items.indexOf(event.target as HTMLElement)
      
      if (currentIndex === -1) return

      let newIndex = currentIndex
      let handled = false

      switch (event.key) {
        case 'ArrowDown':
          if (orientation === 'vertical' || orientation === 'both') {
            newIndex = currentIndex + 1
            handled = true
          }
          break
        case 'ArrowUp':
          if (orientation === 'vertical' || orientation === 'both') {
            newIndex = currentIndex - 1
            handled = true
          }
          break
        case 'ArrowRight':
          if (orientation === 'horizontal' || orientation === 'both') {
            newIndex = currentIndex + 1
            handled = true
          }
          break
        case 'ArrowLeft':
          if (orientation === 'horizontal' || orientation === 'both') {
            newIndex = currentIndex - 1
            handled = true
          }
          break
        case 'Home':
          newIndex = 0
          handled = true
          break
        case 'End':
          newIndex = items.length - 1
          handled = true
          break
        case 'Enter':
        case ' ':
          if (onActivate) {
            event.preventDefault()
            onActivate(event.target as HTMLElement)
            handled = true
          }
          break
      }

      if (handled) {
        event.preventDefault()
        
        // Handle wrapping
        if (wrap) {
          if (newIndex < 0) newIndex = items.length - 1
          if (newIndex >= items.length) newIndex = 0
        } else {
          newIndex = Math.max(0, Math.min(items.length - 1, newIndex))
        }

        if (items[newIndex] && newIndex !== currentIndex) {
          items[newIndex].focus()
        }
      }
    }

    container.addEventListener('keydown', handleKeyDown)

    return () => {
      container.removeEventListener('keydown', handleKeyDown)
    }
  }

  /**
   * Generate a unique ID for ARIA relationships
   */
  generateId(prefix: string = 'aria'): string {
    return `${prefix}-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`
  }

  /**
   * Set up ARIA describedby relationship
   */
  setDescribedBy(element: HTMLElement, descriptionId: string): void {
    const existingIds = element.getAttribute('aria-describedby')?.split(' ') || []
    if (!existingIds.includes(descriptionId)) {
      existingIds.push(descriptionId)
      element.setAttribute('aria-describedby', existingIds.join(' '))
    }
  }

  /**
   * Remove ARIA describedby relationship
   */
  removeDescribedBy(element: HTMLElement, descriptionId: string): void {
    const existingIds = element.getAttribute('aria-describedby')?.split(' ') || []
    const filteredIds = existingIds.filter(id => id !== descriptionId)
    
    if (filteredIds.length > 0) {
      element.setAttribute('aria-describedby', filteredIds.join(' '))
    } else {
      element.removeAttribute('aria-describedby')
    }
  }

  /**
   * Check if user prefers reduced motion
   */
  prefersReducedMotion(): boolean {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches
  }

  /**
   * Check if user prefers high contrast
   */
  prefersHighContrast(): boolean {
    return window.matchMedia('(prefers-contrast: high)').matches
  }

  /**
   * Get the user's preferred color scheme
   */
  getPreferredColorScheme(): 'light' | 'dark' {
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
  }

  /**
   * Clean up all live regions
   */
  cleanup(): void {
    this.liveRegions.forEach(({ element }) => {
      if (element.parentNode) {
        element.parentNode.removeChild(element)
      }
    })
    this.liveRegions.clear()
    this.focusHistory = []
  }
}

/**
 * Utility functions for common accessibility tasks
 */
export const a11y = {
  /**
   * Announce a message to screen readers
   */
  announce: (message: string, priority: 'polite' | 'assertive' = 'polite') => {
    AccessibilityManager.getInstance().announce(message, priority)
  },

  /**
   * Trap focus within a container
   */
  trapFocus: (container: HTMLElement) => {
    return AccessibilityManager.getInstance().trapFocus(container)
  },

  /**
   * Set up arrow key navigation
   */
  setupArrowKeys: (
    container: HTMLElement, 
    itemSelector: string, 
    options?: Parameters<AccessibilityManager['setupArrowKeyNavigation']>[2]
  ) => {
    return AccessibilityManager.getInstance().setupArrowKeyNavigation(container, itemSelector, options)
  },

  /**
   * Generate unique ID
   */
  generateId: (prefix?: string) => {
    return AccessibilityManager.getInstance().generateId(prefix)
  },

  /**
   * Check user preferences
   */
  preferences: {
    reducedMotion: () => AccessibilityManager.getInstance().prefersReducedMotion(),
    highContrast: () => AccessibilityManager.getInstance().prefersHighContrast(),
    colorScheme: () => AccessibilityManager.getInstance().getPreferredColorScheme()
  }
}

/**
 * Vue composable for accessibility features
 */
export function useAccessibility() {
  const manager = AccessibilityManager.getInstance()

  return {
    announce: manager.announce.bind(manager),
    trapFocus: manager.trapFocus.bind(manager),
    releaseFocus: manager.releaseFocus.bind(manager),
    setupArrowKeys: manager.setupArrowKeyNavigation.bind(manager),
    generateId: manager.generateId.bind(manager),
    setDescribedBy: manager.setDescribedBy.bind(manager),
    removeDescribedBy: manager.removeDescribedBy.bind(manager),
    prefersReducedMotion: manager.prefersReducedMotion.bind(manager),
    prefersHighContrast: manager.prefersHighContrast.bind(manager),
    getPreferredColorScheme: manager.getPreferredColorScheme.bind(manager)
  }
}