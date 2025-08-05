// Type definitions for Nextcloud global OC object and platform APIs

declare global {
  interface Window {
    OC: OCNamespace;
  }
}

// Main OC namespace
interface OCNamespace {
  generateUrl: (route: string, params?: Record<string, any>) => string;
  requestToken: string;
  dialogs?: {
    filepicker?: NextcloudFilePicker;
  };
  Notification?: NextcloudNotification;
  // Add other Nextcloud APIs as needed
}

// Nextcloud File Picker API
interface NextcloudFilePicker {
  open: (
    options: NextcloudFilePickerOptions,
    callback: (filePath: string) => void
  ) => void;
  // Add other methods if needed
}

interface NextcloudFilePickerOptions {
  // Directory to start in, e.g. "/"
  dir?: string;
  // File type filter, e.g. "json"
  mimetype?: string;
  // Allow selecting folders
  allowFolders?: boolean;
  // Allow selecting multiple files
  multiple?: boolean;
  // Any other options supported by Nextcloud file picker
  [key: string]: any;
}

// Nextcloud Notification API
interface NextcloudNotification {
  show: (message: string, options?: NextcloudNotificationOptions) => void;
  // Add other methods if needed
}

interface NextcloudNotificationOptions {
  type?: 'success' | 'error' | 'info' | 'warning';
  timeout?: number;
  // Any other options supported by Nextcloud notifications
  [key: string]: any;
}

export {};