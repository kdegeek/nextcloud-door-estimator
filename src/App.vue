<template>
	<NcAppContent>
		<div :class="appClasses" role="application" :aria-label="$t('Door Estimator Application')">
			<!-- Skip to main content link for accessibility -->
			<a 
				href="#main-content" 
				class="skip-link"
				@click="skipToMainContent"
			>
				{{ $t('Skip to main content') }}
			</a>

			<!-- Navigation -->
			<TabNavigation 
				:active-tab="activeTab" 
				:tabs="navigationTabs"
				@tab-change="handleTabChange" 
			/>

			<!-- Loading overlay for app initialization -->
			<div v-if="appLoading" class="app-loading-overlay" role="status" :aria-label="$t('Loading application')">
				<div class="loading-spinner" aria-hidden="true"></div>
				<p class="loading-text">{{ $t('Loading Door Estimator...') }}</p>
			</div>

			<main 
				id="main-content" 
				class="main-content"
				:class="{ 'content-loading': appLoading }"
				role="main"
				:aria-busy="appLoading"
			>
				<!-- Toast Notifications -->
				<ToastNotifications />
				
				<!-- Session Warning Modal -->
				<SessionWarning />
				
				<!-- Error Boundary -->
				<div v-if="appError" class="app-error" role="alert">
					<div class="error-icon" aria-hidden="true">⚠️</div>
					<div class="error-content">
						<h2 class="error-title">{{ $t('Application Error') }}</h2>
						<p class="error-message">{{ appError }}</p>
						<button 
							:class="buttonClasses.primary" 
							@click="retryAppInitialization"
							:aria-label="$t('Retry loading application')"
						>
							{{ $t('Retry') }}
						</button>
					</div>
				</div>

				<!-- Main Views -->
				<template v-else>
					<!-- Estimator View -->
					<EstimatorView 
						v-if="activeTab === 'estimator'" 
						:key="'estimator'"
						@loading-change="handleViewLoading"
					/>

					<!-- Admin View -->
					<AdminView 
						v-if="activeTab === 'admin'" 
						:key="'admin'"
						@loading-change="handleViewLoading"
					/>
				</template>
			</main>

			<!-- Global progress indicator -->
			<div 
				v-if="globalLoading" 
				class="global-progress" 
				role="progressbar" 
				:aria-label="$t('Operation in progress')"
				aria-live="polite"
			>
				<div class="progress-bar">
					<div class="progress-fill" :style="{ width: `${loadingProgress}%` }"></div>
				</div>
			</div>
		</div>
	</NcAppContent>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onErrorCaptured, nextTick } from 'vue'
import NcAppContent from '@nextcloud/vue/dist/Components/NcAppContent.js'
import TabNavigation from './components/TabNavigation.vue'
import ToastNotifications from './components/ToastNotifications.vue'
import SessionWarning from './components/SessionWarning.vue'
import EstimatorView from './views/EstimatorView.vue'
import AdminView from './views/AdminView.vue'
import { useEstimatorStore, useThemeStore, useNotificationsStore } from './stores'
import { setupGlobalErrorHandling, createErrorBoundary } from './utils/errorHandling'
import { useNotifications } from './services/NotificationService'
import { useLogging, measurePerformance } from './services/LoggingService'
import { useAuth } from './composables/useAuth'
import type { SectionKey } from './types'

// Stores
const estimatorStore = useEstimatorStore()
const themeStore = useThemeStore()
const notificationsStore = useNotificationsStore()

// Authentication
const auth = useAuth()

// Error handling and logging
const { handleApiError, showError, showSuccess } = useNotifications()
const { info, error: logError, logUserAction, logBusinessOperation } = useLogging()

// Setup global error handling
setupGlobalErrorHandling()

// Error boundary mixin
const errorBoundary = createErrorBoundary()

// State
const activeTab = ref<'estimator' | 'admin'>('estimator')
const appLoading = ref(true)
const appError = ref<string | null>(null)
const globalLoading = ref(false)
const loadingProgress = ref(0)
const viewLoading = ref(false)

// Destructure store state and actions
const { 
  loadPricingData,
  checkOnboardingStatus
} = estimatorStore

const isDarkMode = computed(() => themeStore.isDarkMode)

// Navigation tabs with accessibility labels and permission checking
const navigationTabs = computed(() => {
  const tabs = [
    { 
      key: 'estimator', 
      label: 'Estimator', 
      icon: () => '📊',
      ariaLabel: 'Switch to Estimator view'
    }
  ]
  
  // Only show admin tab if user has admin privileges
  if (auth.isAdmin.value) {
    tabs.push({
      key: 'admin', 
      label: 'Admin', 
      icon: () => '⚙️',
      ariaLabel: 'Switch to Admin view'
    })
  }
  
  return tabs
})

// Computed classes with responsive and accessibility considerations
const appClasses = computed(() => [
  'app-container',
  isDarkMode.value ? 'app--dark' : 'app--light',
  {
    'app--loading': appLoading.value,
    'app--error': appError.value,
    'app--mobile': isMobile.value,
    'app--tablet': isTablet.value,
    'app--desktop': isDesktop.value
  }
])

const buttonClasses = computed(() => ({
  primary: [
    'btn btn--primary',
    isDarkMode.value ? 'btn--primary-dark' : 'btn--primary-light'
  ],
  secondary: [
    'btn btn--secondary',
    isDarkMode.value ? 'btn--secondary-dark' : 'btn--secondary-light'
  ]
}))

// Responsive breakpoint detection
const isMobile = computed(() => {
  if (typeof window === 'undefined') return false
  return window.innerWidth < 768
})

const isTablet = computed(() => {
  if (typeof window === 'undefined') return false
  return window.innerWidth >= 768 && window.innerWidth < 1024
})

const isDesktop = computed(() => {
  if (typeof window === 'undefined') return false
  return window.innerWidth >= 1024
})

// Methods
const handleTabChange = (tab: string) => {
  // Check permissions before switching tabs
  if (tab === 'admin' && !auth.isAdmin.value) {
    showError('Access denied', 'Administrator privileges required')
    return
  }
  
  activeTab.value = tab as 'estimator' | 'admin'
  
  // Announce tab change to screen readers
  const tabLabel = navigationTabs.value.find(t => t.key === tab)?.label || tab
  notificationsStore.info(`Switched to ${tabLabel} view`, { 
    duration: 2000,
    screenReaderOnly: true 
  })
  
  // Log user action
  logUserAction('tab_change', { tab, user_id: auth.userId.value })
}

const handleViewLoading = (loading: boolean) => {
  viewLoading.value = loading
  globalLoading.value = loading
}

const skipToMainContent = (event: Event) => {
  event.preventDefault()
  const mainContent = document.getElementById('main-content')
  if (mainContent) {
    mainContent.focus()
    mainContent.scrollIntoView({ behavior: 'smooth' })
  }
}

const retryAppInitialization = async () => {
  appError.value = null
  appLoading.value = true
  await initializeApp()
}

// Placeholder translation function (would be replaced with actual Nextcloud translation)
const $t = (text: string) => text

// App initialization with comprehensive error handling
const initializeApp = async () => {
  return measurePerformance('app_initialization', async () => {
    try {
      appLoading.value = true
      appError.value = null
      loadingProgress.value = 0
      
      info('Starting app initialization')
      
      // Initialize authentication
      loadingProgress.value = 10
      try {
        await auth.initialize()
        if (!auth.isAuthenticated.value) {
          throw new Error('User not authenticated')
        }
        logUserAction('auth_initialized', { user_id: auth.userId.value })
      } catch (error) {
        logError('Authentication failed', { error: error instanceof Error ? error.message : String(error) })
        appError.value = 'Authentication required. Please log in to continue.'
        return
      }
      
      // Initialize theme
      loadingProgress.value = 30
      themeStore.initializeTheme()
      logUserAction('theme_initialized')
      
      // Check onboarding status
      loadingProgress.value = 50
      try {
        const status = await checkOnboardingStatus()
        if (!status.hasData) {
          // Only switch to admin tab if user has admin privileges
          if (auth.isAdmin.value) {
            activeTab.value = 'admin'
            showSuccess('Welcome!', 'Please import your pricing data to get started.')
          } else {
            showError('Setup Required', 'Please contact an administrator to set up pricing data.')
          }
          logUserAction('onboarding_required')
        } else {
          logUserAction('onboarding_complete')
        }
      } catch (error) {
        logError('Failed to check onboarding status', { error: error instanceof Error ? error.message : String(error) })
        // Non-critical error, continue initialization
      }
      
      // Load pricing data
      loadingProgress.value = 80
      try {
        await loadPricingData()
        showSuccess('Success', 'Pricing data loaded successfully!')
        logBusinessOperation('load_pricing_data', true)
      } catch (error) {
        logError('Failed to load pricing data', { error: error instanceof Error ? error.message : String(error) })
        handleApiError(error, 'Loading pricing data')
        logBusinessOperation('load_pricing_data', false)
        // This is not a critical error for app functionality
      }
      
      loadingProgress.value = 100
      
      // Small delay to show completion
      await new Promise(resolve => setTimeout(resolve, 300))
      
      info('App initialization completed successfully')
      logBusinessOperation('app_initialization', true)
      
    } catch (error) {
      logError('App initialization failed', { error: error instanceof Error ? error.message : String(error) })
      appError.value = error instanceof Error ? error.message : 'Failed to initialize application'
      logBusinessOperation('app_initialization', false)
      handleApiError(error, 'Application initialization')
    } finally {
      appLoading.value = false
      loadingProgress.value = 0
    }
  })
}

// Error boundary with comprehensive logging
onErrorCaptured((error, instance, info) => {
  logError('Vue error captured', {
    error: error.message,
    component: instance?.$options.name || 'Unknown',
    info,
    stack: error.stack
  })
  
  appError.value = 'An unexpected error occurred. Please refresh the page.'
  handleApiError(error, 'Component error')
  
  return false // Propagate error to global handler
})

// Initialize app
onMounted(async () => {
  await initializeApp()
  
  // Set up responsive breakpoint listeners
  if (typeof window !== 'undefined') {
    const handleResize = () => {
      // Force reactivity update for responsive computed properties
      nextTick()
    }
    
    window.addEventListener('resize', handleResize)
    
    // Cleanup on unmount would go here in a real component
  }
})
</script>

<style>
/* Import responsive and accessibility styles */
@import './styles/responsive.css';
</style>

<style scoped>
/* App Container */
.app-container {
  min-height: 100vh;
  transition: background-color 0.2s, color 0.2s;
  position: relative;
}

.app--light {
  background-color: #f9fafb;
  color: #111827;
}

.app--dark {
  background-color: #111827;
  color: #f9fafb;
}

/* Skip Link for Accessibility */
.skip-link {
  position: absolute;
  top: -40px;
  left: 6px;
  background: #3b82f6;
  color: white;
  padding: 8px 16px;
  text-decoration: none;
  border-radius: 4px;
  z-index: 10000;
  font-weight: 500;
  transition: top 0.2s;
}

.skip-link:focus {
  top: 6px;
}

/* App Loading Overlay */
.app-loading-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.8);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  z-index: 9999;
  color: white;
}

.loading-spinner {
  width: 48px;
  height: 48px;
  border: 4px solid rgba(255, 255, 255, 0.3);
  border-top: 4px solid #3b82f6;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin-bottom: 16px;
}

.loading-text {
  font-size: 16px;
  font-weight: 500;
  margin: 0;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

/* Main Content */
.main-content {
  max-width: 1280px;
  margin: 0 auto;
  padding: 24px 16px;
  transition: opacity 0.3s, filter 0.3s;
  outline: none; /* Remove focus outline since this is programmatically focused */
}

.content-loading {
  opacity: 0.7;
  filter: blur(1px);
  pointer-events: none;
}

/* App Error State */
.app-error {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 48px 24px;
  text-align: center;
  min-height: 400px;
}

.error-icon {
  font-size: 48px;
  margin-bottom: 16px;
}

.error-content {
  max-width: 500px;
}

.error-title {
  font-size: 24px;
  font-weight: 600;
  margin: 0 0 12px 0;
  color: #dc2626;
}

.error-message {
  font-size: 16px;
  margin: 0 0 24px 0;
  color: #6b7280;
  line-height: 1.5;
}

/* Global Progress Indicator */
.global-progress {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  z-index: 9998;
  height: 3px;
  background: rgba(0, 0, 0, 0.1);
}

.progress-bar {
  height: 100%;
  background: #3b82f6;
  transition: width 0.3s ease;
  border-radius: 0 3px 3px 0;
}

.progress-fill {
  height: 100%;
  background: linear-gradient(90deg, #3b82f6, #1d4ed8);
  transition: width 0.3s ease;
}

/* Buttons */
.btn {
  padding: 10px 16px;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 500;
  border: none;
  cursor: pointer;
  transition: all 0.2s;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 44px; /* Minimum touch target size */
  min-width: 44px;
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn:focus-visible {
  outline: 2px solid #3b82f6;
  outline-offset: 2px;
}

.btn--primary-light {
  background: #3b82f6;
  color: white;
}

.btn--primary-light:hover:not(:disabled) {
  background: #2563eb;
  transform: translateY(-1px);
}

.btn--primary-dark {
  background: #2563eb;
  color: white;
}

.btn--primary-dark:hover:not(:disabled) {
  background: #1d4ed8;
  transform: translateY(-1px);
}

.btn--secondary-light {
  background: #f3f4f6;
  color: #374151;
  border: 1px solid #d1d5db;
}

.btn--secondary-light:hover:not(:disabled) {
  background: #e5e7eb;
  transform: translateY(-1px);
}

.btn--secondary-dark {
  background: #374151;
  color: #d1d5db;
  border: 1px solid #4b5563;
}

.btn--secondary-dark:hover:not(:disabled) {
  background: #4b5563;
  transform: translateY(-1px);
}

/* Responsive Design */
.app--mobile .main-content {
  padding: 16px 12px;
}

.app--tablet .main-content {
  padding: 20px 16px;
}

.app--desktop .main-content {
  padding: 24px 16px;
}

/* Mobile-first responsive breakpoints */
@media (max-width: 767px) {
  .main-content {
    padding: 16px 12px;
  }
  
  .app-error {
    padding: 32px 16px;
    min-height: 300px;
  }
  
  .error-icon {
    font-size: 36px;
  }
  
  .error-title {
    font-size: 20px;
  }
  
  .error-message {
    font-size: 14px;
  }
  
  .loading-spinner {
    width: 36px;
    height: 36px;
    border-width: 3px;
  }
  
  .loading-text {
    font-size: 14px;
  }
}

@media (min-width: 768px) and (max-width: 1023px) {
  .main-content {
    padding: 20px 16px;
  }
}

@media (min-width: 1024px) {
  .main-content {
    padding: 24px 16px;
  }
}

/* High contrast mode support */
@media (prefers-contrast: high) {
  .app-container {
    border: 2px solid currentColor;
  }
  
  .btn {
    border: 2px solid currentColor;
  }
  
  .skip-link {
    border: 2px solid white;
  }
}

/* Reduced motion support */
@media (prefers-reduced-motion: reduce) {
  .app-container,
  .main-content,
  .btn,
  .loading-spinner,
  .progress-fill {
    transition: none;
    animation: none;
  }
  
  .skip-link {
    transition: none;
  }
  
  .loading-spinner {
    animation: none;
    border: 4px solid #3b82f6;
  }
}

/* Print styles */
@media print {
  .skip-link,
  .app-loading-overlay,
  .global-progress {
    display: none;
  }
  
  .main-content {
    max-width: none;
    padding: 0;
  }
  
  .app-container {
    background: white;
    color: black;
  }
}
</style>
