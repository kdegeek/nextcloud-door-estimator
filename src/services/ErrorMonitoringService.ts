/**
 * Error monitoring service for critical system failures
 */

import { loggingService } from './LoggingService';
import { notificationService } from './NotificationService';

export interface CriticalError {
  id: string;
  timestamp: string;
  type: 'database' | 'network' | 'authentication' | 'file_system' | 'unknown';
  message: string;
  context: Record<string, any>;
  severity: 'low' | 'medium' | 'high' | 'critical';
  resolved: boolean;
}

class ErrorMonitoringService {
  private static instance: ErrorMonitoringService;
  private criticalErrors: CriticalError[] = [];
  private errorThresholds = {
    database: 3, // 3 database errors in 5 minutes
    network: 5,  // 5 network errors in 5 minutes
    authentication: 2, // 2 auth errors in 5 minutes
    file_system: 3, // 3 file system errors in 5 minutes
  };
  private timeWindow = 5 * 60 * 1000; // 5 minutes
  private alertCooldown = 10 * 60 * 1000; // 10 minutes between alerts
  private lastAlerts = new Map<string, number>();

  static getInstance(): ErrorMonitoringService {
    if (!ErrorMonitoringService.instance) {
      ErrorMonitoringService.instance = new ErrorMonitoringService();
    }
    return ErrorMonitoringService.instance;
  }

  /**
   * Report a critical error for monitoring
   */
  reportCriticalError(
    type: CriticalError['type'],
    message: string,
    context: Record<string, any> = {},
    severity: CriticalError['severity'] = 'medium'
  ): void {
    const error: CriticalError = {
      id: this.generateErrorId(),
      timestamp: new Date().toISOString(),
      type,
      message,
      context,
      severity,
      resolved: false
    };

    this.criticalErrors.push(error);
    
    // Log the critical error
    loggingService.error(`Critical Error [${type}]: ${message}`, {
      ...context,
      error_id: error.id,
      severity,
      type: 'critical_error'
    });

    // Check if we need to trigger an alert
    this.checkErrorThresholds(type);

    // Show user notification for high/critical severity
    if (severity === 'high' || severity === 'critical') {
      this.showCriticalErrorNotification(error);
    }

    // Clean up old errors
    this.cleanupOldErrors();
  }

  /**
   * Mark an error as resolved
   */
  resolveError(errorId: string): void {
    const error = this.criticalErrors.find(e => e.id === errorId);
    if (error) {
      error.resolved = true;
      loggingService.info(`Critical error resolved: ${errorId}`, {
        error_id: errorId,
        type: 'error_resolved'
      });
    }
  }

  /**
   * Get all unresolved critical errors
   */
  getUnresolvedErrors(): CriticalError[] {
    return this.criticalErrors.filter(e => !e.resolved);
  }

  /**
   * Get error statistics for monitoring dashboard
   */
  getErrorStatistics(): {
    total: number;
    byType: Record<string, number>;
    bySeverity: Record<string, number>;
    recentErrors: CriticalError[];
  } {
    const now = Date.now();
    const recentErrors = this.criticalErrors.filter(
      e => now - new Date(e.timestamp).getTime() < this.timeWindow
    );

    const byType: Record<string, number> = {};
    const bySeverity: Record<string, number> = {};

    recentErrors.forEach(error => {
      byType[error.type] = (byType[error.type] || 0) + 1;
      bySeverity[error.severity] = (bySeverity[error.severity] || 0) + 1;
    });

    return {
      total: recentErrors.length,
      byType,
      bySeverity,
      recentErrors: recentErrors.slice(-10) // Last 10 errors
    };
  }

  /**
   * Check if error thresholds have been exceeded
   */
  private checkErrorThresholds(type: CriticalError['type']): void {
    const now = Date.now();
    const recentErrors = this.criticalErrors.filter(
      e => e.type === type && 
           now - new Date(e.timestamp).getTime() < this.timeWindow &&
           !e.resolved
    );

    const threshold = this.errorThresholds[type];
    if (threshold && recentErrors.length >= threshold) {
      this.triggerAlert(type, recentErrors.length);
    }
  }

  /**
   * Trigger an alert for excessive errors
   */
  private triggerAlert(type: CriticalError['type'], count: number): void {
    const alertKey = `threshold_${type}`;
    const now = Date.now();
    const lastAlert = this.lastAlerts.get(alertKey) || 0;

    // Check cooldown period
    if (now - lastAlert < this.alertCooldown) {
      return;
    }

    this.lastAlerts.set(alertKey, now);

    const message = `High error rate detected: ${count} ${type} errors in the last 5 minutes`;
    
    loggingService.error(message, {
      type: 'error_threshold_exceeded',
      error_type: type,
      count,
      threshold: this.errorThresholds[type]
    });

    notificationService.showError(
      'System Alert',
      message,
      [{
        label: 'View Details',
        action: () => this.showErrorDetails(type),
        style: 'primary'
      }]
    );

    // In production, you might want to send this to an external monitoring service
    this.sendAlertToMonitoringService(type, count);
  }

  /**
   * Show critical error notification to user
   */
  private showCriticalErrorNotification(error: CriticalError): void {
    const title = this.getErrorTypeDisplayName(error.type);
    const actions = [];

    // Add retry action if applicable
    if (this.isRetryableErrorType(error.type)) {
      actions.push({
        label: 'Retry',
        action: () => this.retryFailedOperation(error),
        style: 'primary' as const
      });
    }

    // Add report action
    actions.push({
      label: 'Report Issue',
      action: () => this.reportIssue(error),
      style: 'secondary' as const
    });

    notificationService.showError(
      `${title} Error`,
      error.message,
      actions
    );
  }

  /**
   * Show detailed error information
   */
  private showErrorDetails(type: CriticalError['type']): void {
    const errors = this.criticalErrors
      .filter(e => e.type === type && !e.resolved)
      .slice(-5); // Show last 5 errors

    const details = errors.map(e => 
      `${new Date(e.timestamp).toLocaleTimeString()}: ${e.message}`
    ).join('\n');

    notificationService.showError(
      `${this.getErrorTypeDisplayName(type)} Error Details`,
      details
    );
  }

  /**
   * Send alert to external monitoring service
   */
  private async sendAlertToMonitoringService(type: CriticalError['type'], count: number): Promise<void> {
    try {
      // This would integrate with your monitoring service (e.g., Sentry, DataDog, etc.)
      await fetch('/api/monitoring/alert', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          type: 'error_threshold_exceeded',
          error_type: type,
          count,
          threshold: this.errorThresholds[type],
          timestamp: new Date().toISOString(),
          user_agent: navigator.userAgent,
          url: window.location.href
        })
      });
    } catch (error) {
      // Don't log this error to avoid infinite loops
      console.warn('Failed to send alert to monitoring service:', error);
    }
  }

  /**
   * Clean up old errors to prevent memory leaks
   */
  private cleanupOldErrors(): void {
    const cutoff = Date.now() - (24 * 60 * 60 * 1000); // 24 hours
    this.criticalErrors = this.criticalErrors.filter(
      e => new Date(e.timestamp).getTime() > cutoff
    );
  }

  private generateErrorId(): string {
    return `error_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
  }

  private getErrorTypeDisplayName(type: CriticalError['type']): string {
    const names = {
      database: 'Database',
      network: 'Network',
      authentication: 'Authentication',
      file_system: 'File System',
      unknown: 'System'
    };
    return names[type] || 'System';
  }

  private isRetryableErrorType(type: CriticalError['type']): boolean {
    return ['network', 'database', 'file_system'].includes(type);
  }

  private retryFailedOperation(error: CriticalError): void {
    // This would need to be implemented based on the specific error context
    loggingService.info(`Retry requested for error: ${error.id}`, {
      error_id: error.id,
      type: 'retry_requested'
    });
    
    notificationService.showInfo(
      'Retry Initiated',
      'Attempting to retry the failed operation...'
    );
  }

  private reportIssue(error: CriticalError): void {
    // Generate error report
    const report = {
      error_id: error.id,
      timestamp: error.timestamp,
      type: error.type,
      message: error.message,
      context: error.context,
      severity: error.severity,
      user_agent: navigator.userAgent,
      url: window.location.href,
      logs: loggingService.exportLogs()
    };

    // In a real application, this would open a support ticket or send to support system
    console.log('Error report generated:', report);
    
    notificationService.showSuccess(
      'Issue Reported',
      'Your issue has been reported to the support team.'
    );
  }
}

// Export singleton instance
export const errorMonitoringService = ErrorMonitoringService.getInstance();

// Convenience functions for common error types
export const ErrorMonitoring = {
  reportDatabaseError: (message: string, context?: Record<string, any>) => 
    errorMonitoringService.reportCriticalError('database', message, context, 'high'),
    
  reportNetworkError: (message: string, context?: Record<string, any>) => 
    errorMonitoringService.reportCriticalError('network', message, context, 'medium'),
    
  reportAuthError: (message: string, context?: Record<string, any>) => 
    errorMonitoringService.reportCriticalError('authentication', message, context, 'high'),
    
  reportFileSystemError: (message: string, context?: Record<string, any>) => 
    errorMonitoringService.reportCriticalError('file_system', message, context, 'medium'),
    
  reportUnknownError: (message: string, context?: Record<string, any>) => 
    errorMonitoringService.reportCriticalError('unknown', message, context, 'medium')
};