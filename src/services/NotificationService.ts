/**
 * Service for managing user notifications and error display
 */

import { reactive } from 'vue';
import { ApiException, ErrorHandler } from '../utils/errorHandling';

export interface Notification {
  id: string;
  type: 'success' | 'error' | 'warning' | 'info';
  title: string;
  message: string;
  duration?: number;
  actions?: NotificationAction[];
  dismissible?: boolean;
}

export interface NotificationAction {
  label: string;
  action: () => void;
  style?: 'primary' | 'secondary';
}

class NotificationService {
  private static instance: NotificationService;
  private notifications = reactive<Notification[]>([]);
  private nextId = 1;

  static getInstance(): NotificationService {
    if (!NotificationService.instance) {
      NotificationService.instance = new NotificationService();
    }
    return NotificationService.instance;
  }

  /**
   * Get all active notifications
   */
  getNotifications(): Notification[] {
    return this.notifications;
  }

  /**
   * Show a success notification
   */
  showSuccess(title: string, message: string, duration = 5000): string {
    return this.addNotification({
      type: 'success',
      title,
      message,
      duration,
      dismissible: true
    });
  }

  /**
   * Show an error notification
   */
  showError(title: string, message: string, actions?: NotificationAction[]): string {
    return this.addNotification({
      type: 'error',
      title,
      message,
      duration: 0, // Errors don't auto-dismiss
      actions,
      dismissible: true
    });
  }

  /**
   * Show a warning notification
   */
  showWarning(title: string, message: string, duration = 8000): string {
    return this.addNotification({
      type: 'warning',
      title,
      message,
      duration,
      dismissible: true
    });
  }

  /**
   * Show an info notification
   */
  showInfo(title: string, message: string, duration = 5000): string {
    return this.addNotification({
      type: 'info',
      title,
      message,
      duration,
      dismissible: true
    });
  }

  /**
   * Handle and display API errors with appropriate user messaging
   */
  handleApiError(error: unknown, context?: string): void {
    const errorHandler = ErrorHandler.getInstance();
    
    if (error instanceof ApiException) {
      const title = context ? `${context} Failed` : 'Operation Failed';
      const message = error.getUserMessage();
      
      const actions: NotificationAction[] = [];
      
      // Add retry action for retryable errors
      if (error.isRetryable()) {
        actions.push({
          label: 'Retry',
          action: () => {
            // This would need to be implemented by the calling component
            console.log('Retry action triggered');
          },
          style: 'primary'
        });
      }

      // Add details action for validation errors
      if (error.hasValidationErrors()) {
        actions.push({
          label: 'Show Details',
          action: () => {
            this.showValidationErrors(error.getValidationErrors());
          },
          style: 'secondary'
        });
      }

      this.showError(title, message, actions.length > 0 ? actions : undefined);
    } else {
      // Handle unexpected errors
      const message = errorHandler.getUserMessage(error);
      this.showError('Unexpected Error', message);
    }

    // Log error for monitoring
    errorHandler.logError(error, { context });
  }

  /**
   * Show validation errors in a detailed format
   */
  showValidationErrors(errors: Record<string, string>): void {
    const errorList = Object.entries(errors)
      .map(([field, message]) => `• ${field}: ${message}`)
      .join('\n');

    this.showError(
      'Validation Errors',
      `Please correct the following issues:\n${errorList}`
    );
  }

  /**
   * Show loading notification that can be updated
   */
  showLoading(title: string, message: string): string {
    return this.addNotification({
      type: 'info',
      title,
      message,
      duration: 0,
      dismissible: false
    });
  }

  /**
   * Update an existing notification
   */
  updateNotification(id: string, updates: Partial<Notification>): void {
    const index = this.notifications.findIndex(n => n.id === id);
    if (index !== -1) {
      Object.assign(this.notifications[index], updates);
    }
  }

  /**
   * Dismiss a notification
   */
  dismiss(id: string): void {
    const index = this.notifications.findIndex(n => n.id === id);
    if (index !== -1) {
      this.notifications.splice(index, 1);
    }
  }

  /**
   * Dismiss all notifications
   */
  dismissAll(): void {
    this.notifications.splice(0);
  }

  /**
   * Dismiss all notifications of a specific type
   */
  dismissByType(type: Notification['type']): void {
    for (let i = this.notifications.length - 1; i >= 0; i--) {
      if (this.notifications[i].type === type) {
        this.notifications.splice(i, 1);
      }
    }
  }

  private addNotification(notification: Omit<Notification, 'id'>): string {
    const id = `notification-${this.nextId++}`;
    const fullNotification: Notification = {
      id,
      ...notification
    };

    this.notifications.push(fullNotification);

    // Auto-dismiss if duration is set
    if (notification.duration && notification.duration > 0) {
      setTimeout(() => {
        this.dismiss(id);
      }, notification.duration);
    }

    return id;
  }
}

// Export singleton instance
export const notificationService = NotificationService.getInstance();

// Vue composable for using notifications
export function useNotifications() {
  const service = NotificationService.getInstance();

  return {
    notifications: service.getNotifications(),
    showSuccess: service.showSuccess.bind(service),
    showError: service.showError.bind(service),
    showWarning: service.showWarning.bind(service),
    showInfo: service.showInfo.bind(service),
    handleApiError: service.handleApiError.bind(service),
    showLoading: service.showLoading.bind(service),
    updateNotification: service.updateNotification.bind(service),
    dismiss: service.dismiss.bind(service),
    dismissAll: service.dismissAll.bind(service),
    dismissByType: service.dismissByType.bind(service)
  };
}