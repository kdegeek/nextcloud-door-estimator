/**
 * Frontend logging service for monitoring and debugging
 */

export interface LogEntry {
  timestamp: string;
  level: 'debug' | 'info' | 'warn' | 'error';
  message: string;
  context?: Record<string, any>;
  userId?: string;
  sessionId?: string;
  url?: string;
  userAgent?: string;
}

export interface PerformanceEntry {
  operation: string;
  duration: number;
  timestamp: string;
  context?: Record<string, any>;
}

class LoggingService {
  private static instance: LoggingService;
  private logs: LogEntry[] = [];
  private performanceEntries: PerformanceEntry[] = [];
  private maxLogEntries = 1000;
  private maxPerformanceEntries = 500;
  private sessionId: string;
  private userId?: string;

  constructor() {
    this.sessionId = this.generateSessionId();
    this.setupPerformanceObserver();
  }

  static getInstance(): LoggingService {
    if (!LoggingService.instance) {
      LoggingService.instance = new LoggingService();
    }
    return LoggingService.instance;
  }

  /**
   * Set the current user ID for logging context
   */
  setUserId(userId: string): void {
    this.userId = userId;
  }

  /**
   * Log debug message
   */
  debug(message: string, context?: Record<string, any>): void {
    this.log('debug', message, context);
  }

  /**
   * Log info message
   */
  info(message: string, context?: Record<string, any>): void {
    this.log('info', message, context);
  }

  /**
   * Log warning message
   */
  warn(message: string, context?: Record<string, any>): void {
    this.log('warn', message, context);
  }

  /**
   * Log error message
   */
  error(message: string, context?: Record<string, any>): void {
    this.log('error', message, context);
  }

  /**
   * Log user action for analytics
   */
  logUserAction(action: string, context?: Record<string, any>): void {
    this.info(`User Action: ${action}`, {
      ...context,
      action_type: 'user_action'
    });
  }

  /**
   * Log API call performance
   */
  logApiCall(endpoint: string, method: string, duration: number, success: boolean, context?: Record<string, any>): void {
    this.logPerformance(`API ${method} ${endpoint}`, duration, {
      ...context,
      endpoint,
      method,
      success,
      type: 'api_call'
    });

    this.info(`API Call: ${method} ${endpoint}`, {
      duration,
      success,
      ...context
    });
  }

  /**
   * Log component render performance
   */
  logComponentRender(componentName: string, duration: number, context?: Record<string, any>): void {
    this.logPerformance(`Component Render: ${componentName}`, duration, {
      ...context,
      component: componentName,
      type: 'component_render'
    });
  }

  /**
   * Log business operation
   */
  logBusinessOperation(operation: string, success: boolean, duration?: number, context?: Record<string, any>): void {
    this.info(`Business Operation: ${operation}`, {
      ...context,
      operation,
      success,
      duration,
      type: 'business_operation'
    });

    if (duration !== undefined) {
      this.logPerformance(operation, duration, context);
    }
  }

  /**
   * Log security event
   */
  logSecurityEvent(event: string, context?: Record<string, any>): void {
    this.warn(`Security Event: ${event}`, {
      ...context,
      event_type: 'security',
      timestamp: new Date().toISOString()
    });
  }

  /**
   * Get recent logs for debugging
   */
  getRecentLogs(count = 100): LogEntry[] {
    return this.logs.slice(-count);
  }

  /**
   * Get performance metrics
   */
  getPerformanceMetrics(): PerformanceEntry[] {
    return this.performanceEntries.slice();
  }

  /**
   * Export logs for support/debugging
   */
  exportLogs(): string {
    const exportData = {
      sessionId: this.sessionId,
      userId: this.userId,
      timestamp: new Date().toISOString(),
      logs: this.logs,
      performance: this.performanceEntries,
      userAgent: navigator.userAgent,
      url: window.location.href
    };

    return JSON.stringify(exportData, null, 2);
  }

  /**
   * Clear all logs (useful for privacy)
   */
  clearLogs(): void {
    this.logs = [];
    this.performanceEntries = [];
  }

  /**
   * Send logs to server for monitoring (if configured)
   */
  async sendLogsToServer(): Promise<void> {
    // Only send error and warning logs to reduce noise
    const criticalLogs = this.logs.filter(log => ['error', 'warn'].includes(log.level));
    
    if (criticalLogs.length === 0) {
      return;
    }

    try {
      await fetch('/api/logs', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          sessionId: this.sessionId,
          userId: this.userId,
          logs: criticalLogs,
          performance: this.performanceEntries.filter(p => p.duration > 1000), // Only slow operations
          timestamp: new Date().toISOString()
        })
      });
    } catch (error) {
      // Don't log this error to avoid infinite loops
      console.warn('Failed to send logs to server:', error);
    }
  }

  private log(level: LogEntry['level'], message: string, context?: Record<string, any>): void {
    const entry: LogEntry = {
      timestamp: new Date().toISOString(),
      level,
      message,
      context,
      userId: this.userId,
      sessionId: this.sessionId,
      url: window.location.href,
      userAgent: navigator.userAgent
    };

    this.logs.push(entry);

    // Trim logs if we exceed max entries
    if (this.logs.length > this.maxLogEntries) {
      this.logs = this.logs.slice(-this.maxLogEntries);
    }

    // Also log to console in development
    if (process.env.NODE_ENV === 'development') {
      const consoleMethod = level === 'debug' ? 'log' : level;
      console[consoleMethod](`[${level.toUpperCase()}] ${message}`, context || '');
    }
  }

  private logPerformance(operation: string, duration: number, context?: Record<string, any>): void {
    const entry: PerformanceEntry = {
      operation,
      duration,
      timestamp: new Date().toISOString(),
      context
    };

    this.performanceEntries.push(entry);

    // Trim performance entries if we exceed max
    if (this.performanceEntries.length > this.maxPerformanceEntries) {
      this.performanceEntries = this.performanceEntries.slice(-this.maxPerformanceEntries);
    }
  }

  private generateSessionId(): string {
    return `session_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
  }

  private setupPerformanceObserver(): void {
    // Monitor long tasks that might affect user experience
    if ('PerformanceObserver' in window) {
      try {
        const observer = new PerformanceObserver((list) => {
          for (const entry of list.getEntries()) {
            if (entry.duration > 50) { // Log tasks longer than 50ms
              this.logPerformance('Long Task', entry.duration, {
                type: 'long_task',
                name: entry.name,
                startTime: entry.startTime
              });
            }
          }
        });

        observer.observe({ entryTypes: ['longtask'] });
      } catch (error) {
        // PerformanceObserver might not be supported
        console.warn('PerformanceObserver not supported:', error);
      }
    }
  }
}

// Export singleton instance
export const loggingService = LoggingService.getInstance();

// Vue composable for using logging
export function useLogging() {
  const service = LoggingService.getInstance();

  return {
    debug: service.debug.bind(service),
    info: service.info.bind(service),
    warn: service.warn.bind(service),
    error: service.error.bind(service),
    logUserAction: service.logUserAction.bind(service),
    logApiCall: service.logApiCall.bind(service),
    logComponentRender: service.logComponentRender.bind(service),
    logBusinessOperation: service.logBusinessOperation.bind(service),
    logSecurityEvent: service.logSecurityEvent.bind(service),
    exportLogs: service.exportLogs.bind(service),
    clearLogs: service.clearLogs.bind(service)
  };
}

// Performance measurement utility
export function measurePerformance<T>(
  operation: string,
  fn: () => T | Promise<T>,
  context?: Record<string, any>
): T | Promise<T> {
  const start = performance.now();
  const logger = LoggingService.getInstance();

  try {
    const result = fn();

    if (result instanceof Promise) {
      return result.then(
        (value) => {
          logger.logPerformance(operation, performance.now() - start, context);
          return value;
        },
        (error) => {
          logger.logPerformance(operation, performance.now() - start, {
            ...context,
            error: true
          });
          throw error;
        }
      );
    } else {
      logger.logPerformance(operation, performance.now() - start, context);
      return result;
    }
  } catch (error) {
    logger.logPerformance(operation, performance.now() - start, {
      ...context,
      error: true
    });
    throw error;
  }
}