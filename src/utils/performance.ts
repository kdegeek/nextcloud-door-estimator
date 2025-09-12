/**
 * Performance monitoring utilities for the Door Estimator application
 */

export interface PerformanceMetric {
  name: string
  startTime: number
  endTime?: number
  duration?: number
  metadata?: Record<string, any>
}

export class PerformanceMonitor {
  private metrics: Map<string, PerformanceMetric> = new Map()
  private static instance: PerformanceMonitor

  static getInstance(): PerformanceMonitor {
    if (!PerformanceMonitor.instance) {
      PerformanceMonitor.instance = new PerformanceMonitor()
    }
    return PerformanceMonitor.instance
  }

  /**
   * Start timing a performance metric
   */
  start(name: string, metadata?: Record<string, any>): void {
    this.metrics.set(name, {
      name,
      startTime: performance.now(),
      metadata
    })
  }

  /**
   * End timing a performance metric and return the duration
   */
  end(name: string): number | null {
    const metric = this.metrics.get(name)
    if (!metric) {
      console.warn(`Performance metric '${name}' not found`)
      return null
    }

    const endTime = performance.now()
    const duration = endTime - metric.startTime

    metric.endTime = endTime
    metric.duration = duration

    // Log slow operations
    if (duration > 1000) { // > 1 second
      console.warn(`Slow operation detected: ${name} took ${duration.toFixed(2)}ms`, metric.metadata)
    } else if (duration > 500) { // > 500ms
      console.info(`Performance warning: ${name} took ${duration.toFixed(2)}ms`, metric.metadata)
    }

    return duration
  }

  /**
   * Get all recorded metrics
   */
  getMetrics(): PerformanceMetric[] {
    return Array.from(this.metrics.values())
  }

  /**
   * Clear all metrics
   */
  clear(): void {
    this.metrics.clear()
  }

  /**
   * Measure a function execution time
   */
  async measure<T>(name: string, fn: () => Promise<T>, metadata?: Record<string, any>): Promise<T> {
    this.start(name, metadata)
    try {
      const result = await fn()
      this.end(name)
      return result
    } catch (error) {
      this.end(name)
      throw error
    }
  }

  /**
   * Measure a synchronous function execution time
   */
  measureSync<T>(name: string, fn: () => T, metadata?: Record<string, any>): T {
    this.start(name, metadata)
    try {
      const result = fn()
      this.end(name)
      return result
    } catch (error) {
      this.end(name)
      throw error
    }
  }
}

/**
 * Debounce function to limit the rate of function calls
 */
export function debounce<T extends (...args: any[]) => any>(
  func: T,
  wait: number,
  immediate = false
): (...args: Parameters<T>) => void {
  let timeout: NodeJS.Timeout | null = null

  return function executedFunction(...args: Parameters<T>) {
    const later = () => {
      timeout = null
      if (!immediate) func(...args)
    }

    const callNow = immediate && !timeout

    if (timeout) clearTimeout(timeout)
    timeout = setTimeout(later, wait)

    if (callNow) func(...args)
  }
}

/**
 * Throttle function to limit the rate of function calls
 */
export function throttle<T extends (...args: any[]) => any>(
  func: T,
  limit: number
): (...args: Parameters<T>) => void {
  let inThrottle: boolean = false

  return function executedFunction(...args: Parameters<T>) {
    if (!inThrottle) {
      func(...args)
      inThrottle = true
      setTimeout(() => (inThrottle = false), limit)
    }
  }
}

/**
 * Lazy loading utility for components
 */
export function createLazyComponent<T>(
  importFn: () => Promise<T>,
  fallback?: any
) {
  return {
    component: importFn,
    loading: fallback || { template: '<div class="loading-spinner">Loading...</div>' },
    error: { template: '<div class="error-message">Failed to load component</div>' },
    delay: 200,
    timeout: 10000
  }
}

/**
 * Memory usage monitoring
 */
interface MemoryInfo {
  usedJSHeapSize: number
  totalJSHeapSize: number
  jsHeapSizeLimit: number
}

export class MemoryMonitor {
  static getMemoryUsage(): MemoryInfo | null {
    if ('memory' in performance) {
      return (performance as any).memory
    }
    return null
  }

  static logMemoryUsage(context: string): void {
    const memory = this.getMemoryUsage()
    if (memory) {
      console.debug(`Memory usage (${context}):`, {
        used: `${(memory.usedJSHeapSize / 1024 / 1024).toFixed(2)} MB`,
        total: `${(memory.totalJSHeapSize / 1024 / 1024).toFixed(2)} MB`,
        limit: `${(memory.jsHeapSizeLimit / 1024 / 1024).toFixed(2)} MB`
      })
    }
  }
}

/**
 * Virtual scrolling utility for large lists
 */
export class VirtualScrollManager {
  private container: HTMLElement
  private itemHeight: number
  private visibleCount: number
  private scrollTop = 0
  private totalItems = 0

  constructor(container: HTMLElement, itemHeight: number, visibleCount: number) {
    this.container = container
    this.itemHeight = itemHeight
    this.visibleCount = visibleCount
  }

  getVisibleRange(scrollTop: number, totalItems: number): { start: number; end: number } {
    this.scrollTop = scrollTop
    this.totalItems = totalItems

    const start = Math.floor(scrollTop / this.itemHeight)
    const end = Math.min(start + this.visibleCount + 1, totalItems)

    return { start: Math.max(0, start), end }
  }

  getTotalHeight(): number {
    return this.totalItems * this.itemHeight
  }

  getOffsetY(index: number): number {
    return index * this.itemHeight
  }
}

// Export singleton instance
export const performanceMonitor = PerformanceMonitor.getInstance()