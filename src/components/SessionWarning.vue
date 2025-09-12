<template>
  <NcModal
    v-if="showWarning"
    :can-close="false"
    :show.sync="showWarning"
    size="small"
  >
    <div class="session-warning">
      <div class="session-warning__icon">
        <ClockIcon :size="48" />
      </div>
      
      <h2 class="session-warning__title">
        Session Expiring Soon
      </h2>
      
      <p class="session-warning__message">
        Your session will expire in {{ formatTimeRemaining(timeRemaining) }}.
        Would you like to extend your session?
      </p>
      
      <div class="session-warning__actions">
        <NcButton
          type="primary"
          :disabled="refreshing"
          @click="extendSession"
        >
          <template #icon>
            <LoadingIcon v-if="refreshing" :size="16" />
            <RefreshIcon v-else :size="16" />
          </template>
          {{ refreshing ? 'Extending...' : 'Extend Session' }}
        </NcButton>
        
        <NcButton
          type="secondary"
          @click="logout"
        >
          <template #icon>
            <LogoutIcon :size="16" />
          </template>
          Logout
        </NcButton>
      </div>
      
      <div v-if="error" class="session-warning__error">
        <NcNoteCard type="error">
          {{ error }}
        </NcNoteCard>
      </div>
    </div>
  </NcModal>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useAuth } from '../composables/useAuth'
import { showError, showSuccess } from '@nextcloud/dialogs'

// Nextcloud Vue components
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcNoteCard from '@nextcloud/vue/dist/Components/NcNoteCard.js'

// Icons
import ClockIcon from 'vue-material-design-icons/Clock.vue'
import RefreshIcon from 'vue-material-design-icons/Refresh.vue'
import LogoutIcon from 'vue-material-design-icons/Logout.vue'
import LoadingIcon from 'vue-material-design-icons/Loading.vue'

const auth = useAuth()

// Local state
const showWarning = ref(false)
const refreshing = ref(false)
const error = ref<string | null>(null)

// Computed
const timeRemaining = computed(() => auth.timeRemaining.value)

/**
 * Format time remaining for display
 */
const formatTimeRemaining = (seconds: number): string => {
  const minutes = Math.floor(seconds / 60)
  const remainingSeconds = seconds % 60
  
  if (minutes > 0) {
    return `${minutes} minute${minutes !== 1 ? 's' : ''} and ${remainingSeconds} second${remainingSeconds !== 1 ? 's' : ''}`
  }
  return `${remainingSeconds} second${remainingSeconds !== 1 ? 's' : ''}`
}

/**
 * Handle session warning event
 */
const handleSessionWarning = (event: CustomEvent) => {
  showWarning.value = true
  error.value = null
}

/**
 * Handle session expired event
 */
const handleSessionExpired = () => {
  showWarning.value = false
  showError('Your session has expired. Please log in again.')
  // Redirect will be handled by the auth service
}

/**
 * Extend the user session
 */
const extendSession = async () => {
  try {
    refreshing.value = true
    error.value = null
    
    await auth.refreshSession()
    
    showWarning.value = false
    showSuccess('Session extended successfully')
  } catch (err) {
    error.value = err instanceof Error ? err.message : 'Failed to extend session'
    showError('Failed to extend session. Please try again.')
  } finally {
    refreshing.value = false
  }
}

/**
 * Logout user
 */
const logout = () => {
  showWarning.value = false
  window.location.href = '/logout'
}

/**
 * Set up event listeners
 */
const setupEventListeners = () => {
  window.addEventListener('session-warning', handleSessionWarning)
  window.addEventListener('session-expired', handleSessionExpired)
}

/**
 * Clean up event listeners
 */
const cleanupEventListeners = () => {
  window.removeEventListener('session-warning', handleSessionWarning)
  window.removeEventListener('session-expired', handleSessionExpired)
}

// Lifecycle
onMounted(() => {
  setupEventListeners()
})

onUnmounted(() => {
  cleanupEventListeners()
})
</script>

<style scoped>
.session-warning {
  padding: 24px;
  text-align: center;
  max-width: 400px;
}

.session-warning__icon {
  margin-bottom: 16px;
  color: var(--color-warning);
}

.session-warning__title {
  margin: 0 0 16px 0;
  font-size: 1.25rem;
  font-weight: 600;
  color: var(--color-main-text);
}

.session-warning__message {
  margin: 0 0 24px 0;
  color: var(--color-text-lighter);
  line-height: 1.5;
}

.session-warning__actions {
  display: flex;
  gap: 12px;
  justify-content: center;
  margin-bottom: 16px;
}

.session-warning__error {
  margin-top: 16px;
}

@media (max-width: 768px) {
  .session-warning__actions {
    flex-direction: column;
  }
}
</style>