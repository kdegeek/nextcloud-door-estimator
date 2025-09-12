/**
 * Frontend error handling utilities for consistent error management
 */

export interface ApiError {
  code: string;
  message: string;
  context?: Record<string, any>;
  validation_errors?: Record<string, string>;
  retryable?: boolean;
}

export interface ApiResponse<T = any> {
  success: boolean;
  data?: T;
  error?: ApiError;
}

export class ErrorHandler {
  private static instance: ErrorHandler;
  private retryAttempts = new Map<string, number>();
  private maxRetries = 3;
  private retryDelays = [1000, 2000, 4000]; // ms

  static getInstance(): ErrorHandler {
    if (!ErrorHandler.instance) {
      ErrorHandler.instance = new ErrorHandler();
    }
    return ErrorHandler.instance;
  }

  /**
   * Handle API response and extract error information
   */
  handleApiResponse<T>(response: ApiResponse<T>): T {
    if (response.success && response.data !== undefined) {
      return response.data;
    }

    if (response.error) {
      throw new ApiException(response.error);
    }

    throw new ApiException({
      code: 'UNKNOWN_ERROR',
      message: 'An unexpected error occurred'
    });
  }

  /**
   * Execute API call with retry logic for retryable errors
   */
  async executeWithRetry<T>(
    operation: () => Promise<ApiResponse<T>>,
    operationName: string
  ): Promise<T> {
    const attemptKey = operationName;
    let attempt = this.retryAttempts.get(attemptKey) || 0;

    try {
      const response = await operation();
      
      // Reset retry count on success
      this.retryAttempts.delete(attemptKey);
      
      return this.handleApiResponse(response);
    } catch (error) {
      if (error instanceof ApiException && error.isRetryable() && attempt < this.maxRetries) {
        attempt++;
        this.retryAttempts.set(attemptKey, attempt);

        const delay = this.retryDelays[attempt - 1] || 4000;
        console.warn(`Operation ${operationName} failed, retrying in ${delay}ms (attempt ${attempt}/${this.maxRetries})`);

        await this.delay(delay);
        return this.executeWithRetry(operation, operationName);
      }

      // Reset retry count on non-retryable error or max retries exceeded
      this.retryAttempts.delete(attemptKey);
      throw error;
    }
  }

  /**
   * Get user-friendly error message for display
   */
  getUserMessage(error: unknown): string {
    if (error instanceof ApiException) {
      return error.getUserMessage();
    }

    if (error instanceof Error) {
      return error.message;
    }

    return 'An unexpected error occurred. Please try again.';
  }

  /**
   * Get validation errors for form display
   */
  getValidationErrors(error: unknown): Record<string, string> {
    if (error instanceof ApiException) {
      return error.getValidationErrors();
    }
    return {};
  }

  /**
   * Check if error is retryable
   */
  isRetryable(error: unknown): boolean {
    if (error instanceof ApiException) {
      return error.isRetryable();
    }
    return false;
  }

  /**
   * Log error for monitoring (could be sent to external service)
   */
  logError(error: unknown, context: Record<string, any> = {}): void {
    const errorInfo = {
      timestamp: new Date().toISOString(),
      error: error instanceof Error ? error.message : String(error),
      stack: error instanceof Error ? error.stack : undefined,
      context,
      userAgent: navigator.userAgent,
      url: window.location.href
    };

    console.error('Application Error:', errorInfo);

    // In production, you might want to send this to an error tracking service
    // this.sendToErrorTracking(errorInfo);
  }

  private delay(ms: number): Promise<void> {
    return new Promise(resolve => setTimeout(resolve, ms));
  }
}

/**
 * Custom exception class for API errors
 */
export class ApiException extends Error {
  private apiError: ApiError;

  constructor(apiError: ApiError) {
    super(apiError.message);
    this.name = 'ApiException';
    this.apiError = apiError;
  }

  getCode(): string {
    return this.apiError.code;
  }

  getUserMessage(): string {
    return this.apiError.message;
  }

  getContext(): Record<string, any> {
    return this.apiError.context || {};
  }

  getValidationErrors(): Record<string, string> {
    return this.apiError.validation_errors || {};
  }

  isRetryable(): boolean {
    return this.apiError.retryable || false;
  }

  hasValidationErrors(): boolean {
    return Object.keys(this.getValidationErrors()).length > 0;
  }
}

/**
 * Error boundary for Vue components
 */
export function createErrorBoundary() {
  return {
    errorCaptured(error: Error, instance: any, info: string) {
      ErrorHandler.getInstance().logError(error, {
        component: instance?.$options.name || 'Unknown',
        info
      });

      // Return false to propagate error to global handler
      return false;
    }
  };
}

/**
 * Global error handler for unhandled promise rejections
 */
export function setupGlobalErrorHandling(): void {
  window.addEventListener('unhandledrejection', (event) => {
    ErrorHandler.getInstance().logError(event.reason, {
      type: 'unhandledrejection'
    });
  });

  window.addEventListener('error', (event) => {
    ErrorHandler.getInstance().logError(event.error, {
      type: 'error',
      filename: event.filename,
      lineno: event.lineno,
      colno: event.colno
    });
  });
}

/**
 * Utility functions for common error scenarios
 */
export const ErrorUtils = {
  /**
   * Handle network errors
   */
  handleNetworkError(error: unknown): ApiException {
    if (error instanceof TypeError && error.message.includes('fetch')) {
      return new ApiException({
        code: 'NETWORK_ERROR',
        message: 'Unable to connect to the server. Please check your internet connection.',
        retryable: true
      });
    }
    
    return new ApiException({
      code: 'UNKNOWN_ERROR',
      message: 'An unexpected error occurred'
    });
  },

  /**
   * Handle timeout errors
   */
  handleTimeoutError(): ApiException {
    return new ApiException({
      code: 'TIMEOUT_ERROR',
      message: 'The request took too long to complete. Please try again.',
      retryable: true
    });
  },

  /**
   * Handle validation errors for forms
   */
  handleValidationError(errors: Record<string, string>): ApiException {
    return new ApiException({
      code: 'VALIDATION_ERROR',
      message: 'Please correct the highlighted fields and try again.',
      validation_errors: errors
    });
  }
};