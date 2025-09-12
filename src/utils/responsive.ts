/**
 * Responsive design utilities for the Door Estimator application
 * Provides breakpoint detection, responsive helpers, and adaptive UI functions
 */

import { ref, computed, onMounted, onUnmounted } from 'vue'

// Breakpoint definitions (matching Tailwind CSS defaults)
export const BREAKPOINTS = {
  sm: 640,   // Small devices (landscape phones)
  md: 768,   // Medium devices (tablets)
  lg: 1024,  // Large devices (desktops)
  xl: 1280,  // Extra large devices (large desktops)
  '2xl': 1536 // 2X Large devices (larger desktops)
} as const

export type Breakpoint = keyof typeof BREAKPOINTS
export type BreakpointValue = typeof BREAKPOINTS[Breakpoint]

/**
 * Responsive breakpoint manager
 */
export class ResponsiveManager {
  private static instance: ResponsiveManager
  private listeners: Set<() => void> = new Set()
  private _currentWidth = ref(0)
  private _currentHeight = ref(0)

  static getInstance(): ResponsiveManager {
    if (!ResponsiveManager.instance) {
      ResponsiveManager.instance = new ResponsiveManager()
    }
    return ResponsiveManager.instance
  }

  constructor() {
    if (typeof window !== 'undefined') {
      this.updateDimensions()
      window.addEventListener('resize', this.handleResize)
      window.addEventListener('orientationchange', this.handleOrientationChange)
    }
  }

  private handleResize = () => {
    this.updateDimensions()
    this.notifyListeners()
  }

  private handleOrientationChange = () => {
    // Delay to allow for orientation change to complete
    setTimeout(() => {
      this.updateDimensions()
      this.notifyListeners()
    }, 100)
  }

  private updateDimensions() {
    if (typeof window !== 'undefined') {
      this._currentWidth.value = window.innerWidth
      this._currentHeight.value = window.innerHeight
    }
  }

  private notifyListeners() {
    this.listeners.forEach(listener => listener())
  }

  get currentWidth() {
    return this._currentWidth.value
  }

  get currentHeight() {
    return this._currentHeight.value
  }

  /**
   * Check if current width is at or above a breakpoint
   */
  isAtLeast(breakpoint: Breakpoint): boolean {
    return this.currentWidth >= BREAKPOINTS[breakpoint]
  }

  /**
   * Check if current width is below a breakpoint
   */
  isBelow(breakpoint: Breakpoint): boolean {
    return this.currentWidth < BREAKPOINTS[breakpoint]
  }

  /**
   * Check if current width is between two breakpoints
   */
  isBetween(min: Breakpoint, max: Breakpoint): boolean {
    return this.currentWidth >= BREAKPOINTS[min] && this.currentWidth < BREAKPOINTS[max]
  }

  /**
   * Get the current breakpoint
   */
  getCurrentBreakpoint(): Breakpoint {
    const width = this.currentWidth
    
    if (width >= BREAKPOINTS['2xl']) return '2xl'
    if (width >= BREAKPOINTS.xl) return 'xl'
    if (width >= BREAKPOINTS.lg) return 'lg'
    if (width >= BREAKPOINTS.md) return 'md'
    if (width >= BREAKPOINTS.sm) return 'sm'
    
    return 'sm' // Default to smallest breakpoint
  }

  /**
   * Check if device is in portrait orientation
   */
  isPortrait(): boolean {
    return this.currentHeight > this.currentWidth
  }

  /**
   * Check if device is in landscape orientation
   */
  isLandscape(): boolean {
    return this.currentWidth > this.currentHeight
  }

  /**
   * Check if device is likely a mobile device
   */
  isMobile(): boolean {
    return this.isBelow('md')
  }

  /**
   * Check if device is likely a tablet
   */
  isTablet(): boolean {
    return this.isBetween('md', 'lg')
  }

  /**
   * Check if device is likely a desktop
   */
  isDesktop(): boolean {
    return this.isAtLeast('lg')
  }

  /**
   * Check if device has a small screen (mobile-like)
   */
  isSmallScreen(): boolean {
    return this.isBelow('md')
  }

  /**
   * Check if device has a large screen (desktop-like)
   */
  isLargeScreen(): boolean {
    return this.isAtLeast('lg')
  }

  /**
   * Get responsive value based on current breakpoint
   */
  getResponsiveValue<T>(values: Partial<Record<Breakpoint | 'default', T>>): T | undefined {
    const currentBreakpoint = this.getCurrentBreakpoint()
    
    // Try current breakpoint first
    if (values[currentBreakpoint] !== undefined) {
      return values[currentBreakpoint]
    }
    
    // Fall back to smaller breakpoints
    const breakpointOrder: Breakpoint[] = ['2xl', 'xl', 'lg', 'md', 'sm']
    const currentIndex = breakpointOrder.indexOf(currentBreakpoint)
    
    for (let i = currentIndex + 1; i < breakpointOrder.length; i++) {
      const breakpoint = breakpointOrder[i]
      if (values[breakpoint] !== undefined) {
        return values[breakpoint]
      }
    }
    
    // Fall back to default
    return values.default
  }

  /**
   * Add a listener for breakpoint changes
   */
  addListener(listener: () => void): () => void {
    this.listeners.add(listener)
    
    // Return cleanup function
    return () => {
      this.listeners.delete(listener)
    }
  }

  /**
   * Clean up event listeners
   */
  cleanup() {
    if (typeof window !== 'undefined') {
      window.removeEventListener('resize', this.handleResize)
      window.removeEventListener('orientationchange', this.handleOrientationChange)
    }
    this.listeners.clear()
  }
}

/**
 * Vue composable for responsive design
 */
export function useResponsive() {
  const manager = ResponsiveManager.getInstance()
  
  // Reactive refs for breakpoint states
  const currentWidth = computed(() => manager.currentWidth)
  const currentHeight = computed(() => manager.currentHeight)
  const currentBreakpoint = computed(() => manager.getCurrentBreakpoint())
  
  // Device type checks
  const isMobile = computed(() => manager.isMobile())
  const isTablet = computed(() => manager.isTablet())
  const isDesktop = computed(() => manager.isDesktop())
  const isSmallScreen = computed(() => manager.isSmallScreen())
  const isLargeScreen = computed(() => manager.isLargeScreen())
  
  // Orientation checks
  const isPortrait = computed(() => manager.isPortrait())
  const isLandscape = computed(() => manager.isLandscape())
  
  // Breakpoint checks
  const isAtLeast = (breakpoint: Breakpoint) => computed(() => manager.isAtLeast(breakpoint))
  const isBelow = (breakpoint: Breakpoint) => computed(() => manager.isBelow(breakpoint))
  const isBetween = (min: Breakpoint, max: Breakpoint) => computed(() => manager.isBetween(min, max))
  
  return {
    // Dimensions
    currentWidth,
    currentHeight,
    currentBreakpoint,
    
    // Device types
    isMobile,
    isTablet,
    isDesktop,
    isSmallScreen,
    isLargeScreen,
    
    // Orientation
    isPortrait,
    isLandscape,
    
    // Breakpoint utilities
    isAtLeast,
    isBelow,
    isBetween,
    
    // Utility functions
    getResponsiveValue: manager.getResponsiveValue.bind(manager),
    addListener: manager.addListener.bind(manager)
  }
}

/**
 * Utility functions for responsive design
 */
export const responsive = {
  /**
   * Get current breakpoint
   */
  getCurrentBreakpoint: () => ResponsiveManager.getInstance().getCurrentBreakpoint(),
  
  /**
   * Check if at least a certain breakpoint
   */
  isAtLeast: (breakpoint: Breakpoint) => ResponsiveManager.getInstance().isAtLeast(breakpoint),
  
  /**
   * Check if below a certain breakpoint
   */
  isBelow: (breakpoint: Breakpoint) => ResponsiveManager.getInstance().isBelow(breakpoint),
  
  /**
   * Check device type
   */
  device: {
    isMobile: () => ResponsiveManager.getInstance().isMobile(),
    isTablet: () => ResponsiveManager.getInstance().isTablet(),
    isDesktop: () => ResponsiveManager.getInstance().isDesktop()
  },
  
  /**
   * Get responsive value
   */
  getValue: <T>(values: Partial<Record<Breakpoint | 'default', T>>) => 
    ResponsiveManager.getInstance().getResponsiveValue(values)
}

/**
 * CSS class utilities for responsive design
 */
export function getResponsiveClasses(
  baseClass: string,
  modifiers: Partial<Record<Breakpoint | 'default', string>> = {}
): string[] {
  const manager = ResponsiveManager.getInstance()
  const classes = [baseClass]
  
  // Add responsive modifier classes
  Object.entries(modifiers).forEach(([breakpoint, modifier]) => {
    if (breakpoint === 'default') {
      classes.push(`${baseClass}--${modifier}`)
    } else {
      const bp = breakpoint as Breakpoint
      if (manager.isAtLeast(bp)) {
        classes.push(`${baseClass}--${bp}-${modifier}`)
      }
    }
  })
  
  return classes
}

/**
 * Generate responsive CSS custom properties
 */
export function getResponsiveStyles(
  properties: Partial<Record<Breakpoint | 'default', Record<string, string>>>
): Record<string, string> {
  const manager = ResponsiveManager.getInstance()
  const currentBreakpoint = manager.getCurrentBreakpoint()
  
  // Get the appropriate properties for current breakpoint
  const props = manager.getResponsiveValue(properties) || properties.default || {}
  
  return props
}

/**
 * Touch and pointer utilities
 */
export const touch = {
  /**
   * Check if device supports touch
   */
  isSupported: () => 'ontouchstart' in window || navigator.maxTouchPoints > 0,
  
  /**
   * Check if primary input is touch
   */
  isPrimary: () => window.matchMedia('(pointer: coarse)').matches,
  
  /**
   * Check if device supports hover
   */
  canHover: () => window.matchMedia('(hover: hover)').matches,
  
  /**
   * Get minimum touch target size
   */
  getMinTargetSize: () => touch.isPrimary() ? 44 : 32
}