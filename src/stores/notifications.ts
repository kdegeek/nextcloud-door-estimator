import { defineStore } from 'pinia'
import { ref } from 'vue'

export type ToastType = 'success' | 'error' | 'warning' | 'info'

export interface Toast {
  id: number
  type: ToastType
  message: string
  timeout?: number
}

export const useNotificationsStore = defineStore('notifications', () => {
  const toasts = ref<Toast[]>([])
  let toastId = 0
  const activeTimeouts = new Map<number, number>()

  const addToast = (message: string, type: ToastType = 'info', timeout = 4000) => {
    const id = ++toastId
    const toast: Toast = { id, type, message, timeout }
    
    toasts.value.push(toast)
    
    if (timeout > 0) {
      const timeoutId = window.setTimeout(() => {
        removeToast(id)
      }, timeout)
      activeTimeouts.set(id, timeoutId)
    }
    
    return id
  }

  const removeToast = (id: number) => {
    const index = toasts.value.findIndex(t => t.id === id)
    if (index >= 0) {
      toasts.value.splice(index, 1)
    }
    
    const timeoutId = activeTimeouts.get(id)
    if (timeoutId) {
      clearTimeout(timeoutId)
      activeTimeouts.delete(id)
    }
  }

  const clearAllToasts = () => {
    toasts.value = []
    activeTimeouts.forEach(timeoutId => clearTimeout(timeoutId))
    activeTimeouts.clear()
  }

  // Convenience methods
  const success = (message: string, timeout?: number) => addToast(message, 'success', timeout)
  const error = (message: string, timeout?: number) => addToast(message, 'error', timeout)
  const warning = (message: string, timeout?: number) => addToast(message, 'warning', timeout)
  const info = (message: string, timeout?: number) => addToast(message, 'info', timeout)

  return {
    toasts,
    addToast,
    removeToast,
    clearAllToasts,
    success,
    error,
    warning,
    info,
  }
})