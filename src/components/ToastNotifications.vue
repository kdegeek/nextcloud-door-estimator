<template>
  <Teleport to="body">
    <div 
      class="toast-container"
      :class="{ 'toast-container--mobile': isMobile }"
      role="region"
      :aria-label="$t('Notifications')"
    >
      <TransitionGroup name="toast" tag="div" class="toast-list">
        <div
          v-for="toast in toasts"
          :key="toast.id"
          :class="[
            'toast',
            `toast--${toast.type}`,
            isDarkMode ? 'toast--dark' : 'toast--light',
            { 'toast--mobile': isMobile }
          ]"
          role="alert"
          :aria-live="toast.type === 'error' ? 'assertive' : 'polite'"
          :aria-atomic="true"
          :aria-describedby="`toast-message-${toast.id}`"
        >
          <div class="toast__icon" :aria-label="getToastTypeLabel(toast.type)">
            <component :is="getToastIcon(toast.type)" />
          </div>
          <div 
            :id="`toast-message-${toast.id}`"
            class="toast__message"
          >
            {{ toast.message }}
          </div>
          <button
            class="toast__close"
            type="button"
            :aria-label="$t('Close notification: {message}', { message: toast.message })"
            @click="removeToast(toast.id)"
            @keydown="handleCloseKeydown($event, toast.id)"
          >
            <CloseIcon />
          </button>
          
          <!-- Progress bar for auto-dismiss -->
          <div 
            v-if="toast.duration && toast.duration > 0"
            class="toast__progress"
            :class="`toast__progress--${toast.type}`"
            role="progressbar"
            :aria-label="$t('Time remaining')"
            :aria-valuenow="getProgressValue(toast)"
            aria-valuemin="0"
            aria-valuemax="100"
          >
            <div 
              class="toast__progress-bar"
              :style="{ 
                animationDuration: `${toast.duration}ms`,
                animationPlayState: toast.paused ? 'paused' : 'running'
              }"
            ></div>
          </div>
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { computed, ref, onMounted, onUnmounted } from 'vue'
import { useNotificationsStore, useThemeStore } from '../stores'

// Icons (using simple text for now, can be replaced with proper icon components)
const CheckIcon = () => '✓'
const ErrorIcon = () => '✕'
const WarningIcon = () => '⚠'
const InfoIcon = () => 'ℹ'
const CloseIcon = () => '×'

const notificationsStore = useNotificationsStore()
const themeStore = useThemeStore()

const toasts = computed(() => notificationsStore.toasts)
const isDarkMode = computed(() => themeStore.isDarkMode)

// Responsive state
const isMobile = ref(false)

const updateIsMobile = () => {
  if (typeof window !== 'undefined') {
    isMobile.value = window.innerWidth < 768
  }
}

const removeToast = (id: number) => {
  notificationsStore.removeToast(id)
}

const handleCloseKeydown = (event: KeyboardEvent, id: number) => {
  if (event.key === 'Enter' || event.key === ' ') {
    event.preventDefault()
    removeToast(id)
  }
}

const getToastIcon = (type: string) => {
  switch (type) {
    case 'success':
      return CheckIcon
    case 'error':
      return ErrorIcon
    case 'warning':
      return WarningIcon
    case 'info':
    default:
      return InfoIcon
  }
}

const getToastTypeLabel = (type: string): string => {
  switch (type) {
    case 'success':
      return $t('Success')
    case 'error':
      return $t('Error')
    case 'warning':
      return $t('Warning')
    case 'info':
    default:
      return $t('Information')
  }
}

const getProgressValue = (toast: any): number => {
  // This would need to be implemented based on the toast's remaining time
  // For now, return a placeholder value
  return 50
}

// Placeholder translation function with interpolation support
const $t = (text: string, params?: Record<string, any>) => {
  if (params) {
    return text.replace(/\{(\w+)\}/g, (match, key) => params[key] || match)
  }
  return text
}

// Lifecycle
onMounted(() => {
  updateIsMobile()
  if (typeof window !== 'undefined') {
    window.addEventListener('resize', updateIsMobile)
  }
})

onUnmounted(() => {
  if (typeof window !== 'undefined') {
    window.removeEventListener('resize', updateIsMobile)
  }
})
</script>

<style scoped>
.toast-container {
  position: fixed;
  top: 80px;
  right: 16px;
  z-index: 1000;
  pointer-events: none;
  max-height: calc(100vh - 100px);
  overflow: hidden;
}

.toast-container--mobile {
  top: 70px;
  right: 8px;
  left: 8px;
  max-height: calc(100vh - 80px);
}

.toast-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
  max-width: 400px;
  overflow-y: auto;
  max-height: inherit;
}

.toast {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 12px 16px;
  border-radius: 8px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  pointer-events: auto;
  min-width: 300px;
  max-width: 400px;
  word-wrap: break-word;
  position: relative;
  overflow: hidden;
}

.toast--mobile {
  min-width: auto;
  max-width: none;
  padding: 16px;
  gap: 16px;
}

.toast--light {
  background: white;
  border: 1px solid #e5e7eb;
  color: #374151;
}

.toast--dark {
  background: #374151;
  border: 1px solid #4b5563;
  color: #f9fafb;
}

.toast--success {
  border-left: 4px solid #10b981;
}

.toast--error {
  border-left: 4px solid #ef4444;
}

.toast--warning {
  border-left: 4px solid #f59e0b;
}

.toast--info {
  border-left: 4px solid #3b82f6;
}

.toast__icon {
  flex-shrink: 0;
  width: 20px;
  height: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: bold;
  margin-top: 2px;
}

.toast--success .toast__icon {
  color: #10b981;
}

.toast--error .toast__icon {
  color: #ef4444;
}

.toast--warning .toast__icon {
  color: #f59e0b;
}

.toast--info .toast__icon {
  color: #3b82f6;
}

.toast__message {
  flex: 1;
  font-size: 14px;
  line-height: 1.5;
  word-break: break-word;
  padding-right: 8px;
}

.toast__close {
  flex-shrink: 0;
  width: 32px;
  height: 32px;
  border: none;
  background: none;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 4px;
  font-size: 18px;
  line-height: 1;
  opacity: 0.6;
  transition: all 0.2s;
  margin-top: -2px;
}

.toast__close:hover,
.toast__close:focus {
  opacity: 1;
  transform: scale(1.1);
}

.toast__close:focus-visible {
  outline: 2px solid #3b82f6;
  outline-offset: 2px;
}

.toast--light .toast__close {
  color: #6b7280;
}

.toast--light .toast__close:hover {
  background: #f3f4f6;
}

.toast--dark .toast__close {
  color: #9ca3af;
}

.toast--dark .toast__close:hover {
  background: #4b5563;
}

/* Progress bar for auto-dismiss */
.toast__progress {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  height: 3px;
  background: rgba(0, 0, 0, 0.1);
  overflow: hidden;
}

.toast__progress-bar {
  height: 100%;
  width: 100%;
  transform-origin: left;
  animation: toast-progress linear forwards;
}

.toast__progress--success .toast__progress-bar {
  background: #10b981;
}

.toast__progress--error .toast__progress-bar {
  background: #ef4444;
}

.toast__progress--warning .toast__progress-bar {
  background: #f59e0b;
}

.toast__progress--info .toast__progress-bar {
  background: #3b82f6;
}

@keyframes toast-progress {
  from {
    transform: scaleX(1);
  }
  to {
    transform: scaleX(0);
  }
}

/* Transitions */
.toast-enter-active,
.toast-leave-active {
  transition: all 0.3s ease;
}

.toast-enter-from {
  opacity: 0;
  transform: translateX(100%) scale(0.95);
}

.toast-leave-to {
  opacity: 0;
  transform: translateX(100%) scale(0.95);
}

.toast-move {
  transition: transform 0.3s ease;
}

/* Mobile responsive design */
@media (max-width: 767px) {
  .toast-container {
    top: 70px;
    right: 8px;
    left: 8px;
    max-height: calc(100vh - 80px);
  }
  
  .toast-list {
    max-width: none;
  }
  
  .toast {
    min-width: auto;
    max-width: none;
    padding: 16px;
    gap: 16px;
  }
  
  .toast__message {
    font-size: 15px;
    line-height: 1.4;
  }
  
  .toast__close {
    width: 36px;
    height: 36px;
    font-size: 20px;
  }
  
  .toast__icon {
    width: 24px;
    height: 24px;
    font-size: 18px;
  }
}

/* Tablet responsive design */
@media (min-width: 768px) and (max-width: 1023px) {
  .toast-container {
    right: 12px;
    max-width: 380px;
  }
  
  .toast {
    max-width: 380px;
  }
}

/* High contrast mode support */
@media (prefers-contrast: high) {
  .toast {
    border-width: 2px;
  }
  
  .toast__close {
    border: 2px solid currentColor;
  }
}

/* Reduced motion support */
@media (prefers-reduced-motion: reduce) {
  .toast-enter-active,
  .toast-leave-active,
  .toast-move,
  .toast__close,
  .toast__progress-bar {
    transition: none;
    animation: none;
  }
  
  .toast-enter-from,
  .toast-leave-to {
    transform: none;
  }
}

/* Print styles */
@media print {
  .toast-container {
    display: none;
  }
}
</style>