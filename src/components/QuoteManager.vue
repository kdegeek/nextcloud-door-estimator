<template>
  <div class="quote-manager">
    <!-- Header with Save Quote Button -->
    <div class="quote-manager-header">
      <h2 class="section-title">Quote Management</h2>
      <div class="header-actions">
        <button
          :class="buttonClasses.primary"
          @click="showSaveDialog = true"
          :disabled="loading"
        >
          {{ loading ? 'Saving...' : 'Save Current Quote' }}
        </button>
      </div>
    </div>

    <!-- Save Quote Dialog -->
    <div v-if="showSaveDialog" class="modal-overlay" @click="closeSaveDialog">
      <div class="modal-content" @click.stop>
        <h3 class="modal-title">Save Quote</h3>
        <form @submit.prevent="handleSaveQuote">
          <div class="form-group">
            <label for="quote-name" class="field-label">Quote Name:</label>
            <input
              id="quote-name"
              v-model="saveForm.quoteName"
              :class="fieldClasses"
              placeholder="Enter quote name (optional)"
              maxlength="255"
            >
          </div>
          <div class="form-group">
            <label for="customer-name" class="field-label">Customer Name:</label>
            <input
              id="customer-name"
              v-model="saveForm.customerName"
              :class="fieldClasses"
              placeholder="Enter customer name (optional)"
              maxlength="255"
            >
          </div>
          <div class="form-group">
            <label for="customer-email" class="field-label">Customer Email:</label>
            <input
              id="customer-email"
              v-model="saveForm.customerEmail"
              type="email"
              :class="fieldClasses"
              placeholder="Enter customer email (optional)"
              maxlength="255"
            >
          </div>
          <div class="modal-actions">
            <button
              type="button"
              :class="buttonClasses.secondary"
              @click="closeSaveDialog"
            >
              Cancel
            </button>
            <button
              type="submit"
              :class="buttonClasses.primary"
              :disabled="loading"
            >
              {{ loading ? 'Saving...' : 'Save Quote' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Quotes List -->
    <div class="quotes-section">
      <div class="quotes-header">
        <h3 class="quotes-title">Saved Quotes</h3>
        <button
          :class="buttonClasses.secondary"
          @click="loadQuotes"
          :disabled="quotesLoading"
        >
          {{ quotesLoading ? 'Loading...' : 'Refresh' }}
        </button>
      </div>

      <!-- Loading State -->
      <div v-if="quotesLoading" class="loading-state">
        <div class="loading-spinner"></div>
        <p>Loading quotes...</p>
      </div>

      <!-- Empty State -->
      <div v-else-if="quotes.length === 0" class="empty-state">
        <p>No saved quotes found.</p>
        <p class="empty-subtitle">Create your first quote by filling out the estimator and clicking "Save Current Quote".</p>
      </div>

      <!-- Quotes Table -->
      <div v-else class="quotes-table-container">
        <table class="quotes-table" aria-label="Saved quotes">
          <caption class="table-caption">Your saved quotes</caption>
          <thead>
            <tr>
              <th scope="col" class="table-header">Quote Name</th>
              <th scope="col" class="table-header">Total Amount</th>
              <th scope="col" class="table-header">Created</th>
              <th scope="col" class="table-header">Updated</th>
              <th scope="col" class="table-header">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="quote in sortedQuotes" :key="quote.id" class="quote-row">
              <td class="table-cell quote-name-cell">
                <button
                  class="quote-name-button"
                  @click="handleLoadQuote(quote.id)"
                  :title="`Load quote: ${quote.quote_name}`"
                >
                  {{ quote.quote_name }}
                </button>
              </td>
              <td class="table-cell total-cell">
                ${{ formatCurrency(quote.total_amount) }}
              </td>
              <td class="table-cell date-cell">
                {{ formatDate(quote.created_at) }}
              </td>
              <td class="table-cell date-cell">
                {{ formatDate(quote.updated_at) }}
              </td>
              <td class="table-cell actions-cell">
                <div class="action-buttons">
                  <button
                    :class="[buttonClasses.secondary, 'btn-small']"
                    @click="handleDuplicateQuote(quote.id)"
                    :disabled="loading"
                    title="Duplicate quote"
                  >
                    Copy
                  </button>
                  <button
                    :class="[buttonClasses.secondary, 'btn-small', 'btn-danger']"
                    @click="showDeleteConfirm(quote)"
                    :disabled="loading"
                    title="Delete quote"
                  >
                    Delete
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Delete Confirmation Dialog -->
    <div v-if="deleteConfirm.show" class="modal-overlay" @click="closeDeleteConfirm">
      <div class="modal-content" @click.stop>
        <h3 class="modal-title">Confirm Delete</h3>
        <p class="delete-message">
          Are you sure you want to delete the quote "{{ deleteConfirm.quote?.quote_name }}"?
        </p>
        <p class="delete-warning">This action cannot be undone.</p>
        <div class="modal-actions">
          <button
            type="button"
            :class="buttonClasses.secondary"
            @click="closeDeleteConfirm"
          >
            Cancel
          </button>
          <button
            type="button"
            :class="[buttonClasses.primary, 'btn-danger']"
            @click="handleDeleteQuote"
            :disabled="loading"
          >
            {{ loading ? 'Deleting...' : 'Delete Quote' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useEstimatorStore, useThemeStore, useNotificationsStore } from '../stores'

// Stores
const estimatorStore = useEstimatorStore()
const themeStore = useThemeStore()
const notificationsStore = useNotificationsStore()

// Destructure store state and actions
const { 
  quotes,
  loading,
  quotesLoading,
  loadQuotes,
  saveQuote,
  loadQuote,
  deleteQuote,
  duplicateQuote
} = estimatorStore

const isDarkMode = computed(() => themeStore.isDarkMode)

// Component state
const showSaveDialog = ref(false)
const saveForm = ref({
  quoteName: '',
  customerName: '',
  customerEmail: ''
})

const deleteConfirm = ref<{
  show: boolean
  quote: any | null
}>({
  show: false,
  quote: null
})

// Computed
const sortedQuotes = computed(() => {
  return [...quotes.value].sort((a, b) => {
    return new Date(b.updated_at).getTime() - new Date(a.updated_at).getTime()
  })
})

const fieldClasses = computed(() => [
  'form-field',
  isDarkMode.value ? 'field--dark' : 'field--light'
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

// Methods
const formatCurrency = (amount: number | null | undefined): string => {
  return (amount || 0).toFixed(2)
}

const formatDate = (dateString: string): string => {
  try {
    const date = new Date(dateString)
    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], { 
      hour: '2-digit', 
      minute: '2-digit' 
    })
  } catch {
    return dateString
  }
}

const closeSaveDialog = () => {
  showSaveDialog.value = false
  saveForm.value = {
    quoteName: '',
    customerName: '',
    customerEmail: ''
  }
}

const handleSaveQuote = async () => {
  try {
    const quoteName = saveForm.value.quoteName.trim() || 
      `Quote ${new Date().toLocaleDateString()} ${new Date().toLocaleTimeString()}`
    
    const customerInfo = {
      name: saveForm.value.customerName.trim() || null,
      email: saveForm.value.customerEmail.trim() || null
    }

    await saveQuote(quoteName, customerInfo)
    
    notificationsStore.success('Quote saved successfully!')
    closeSaveDialog()
    
    // Reload quotes to show the new one
    await loadQuotes()
  } catch (error) {
    notificationsStore.error('Failed to save quote. Please try again.')
  }
}

const handleLoadQuote = async (quoteId: number) => {
  try {
    await loadQuote(quoteId)
    notificationsStore.success('Quote loaded successfully!')
  } catch (error) {
    notificationsStore.error('Failed to load quote. Please try again.')
  }
}

const handleDuplicateQuote = async (quoteId: number) => {
  try {
    const newQuoteId = await duplicateQuote(quoteId)
    notificationsStore.success('Quote duplicated successfully!')
    
    // Optionally load the duplicated quote
    await loadQuote(newQuoteId)
  } catch (error) {
    notificationsStore.error('Failed to duplicate quote. Please try again.')
  }
}

const showDeleteConfirm = (quote: any) => {
  deleteConfirm.value = {
    show: true,
    quote
  }
}

const closeDeleteConfirm = () => {
  deleteConfirm.value = {
    show: false,
    quote: null
  }
}

const handleDeleteQuote = async () => {
  if (!deleteConfirm.value.quote) return

  try {
    await deleteQuote(deleteConfirm.value.quote.id)
    notificationsStore.success('Quote deleted successfully!')
    closeDeleteConfirm()
  } catch (error) {
    notificationsStore.error('Failed to delete quote. Please try again.')
  }
}

// Load quotes on component mount
onMounted(() => {
  loadQuotes()
})
</script>

<style scoped>
/* Quote Manager */
.quote-manager {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.quote-manager-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 16px;
}

.section-title {
  font-size: 24px;
  font-weight: 600;
  margin: 0;
}

.header-actions {
  display: flex;
  gap: 12px;
}

/* Quotes Section */
.quotes-section {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.quotes-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
}

.quotes-title {
  font-size: 18px;
  font-weight: 600;
  margin: 0;
}

/* Loading State */
.loading-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16px;
  padding: 48px 24px;
  text-align: center;
}

.loading-spinner {
  width: 32px;
  height: 32px;
  border: 3px solid #e5e7eb;
  border-top: 3px solid #3b82f6;
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

/* Empty State */
.empty-state {
  text-align: center;
  padding: 48px 24px;
  color: #6b7280;
}

.empty-subtitle {
  font-size: 14px;
  margin-top: 8px;
}

/* Quotes Table */
.quotes-table-container {
  overflow-x: auto;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
}

.quotes-table {
  width: 100%;
  border-collapse: collapse;
  background: white;
}

.table-caption {
  font-size: 14px;
  color: #6b7280;
  margin-bottom: 8px;
  text-align: left;
  caption-side: top;
}

.table-header {
  text-align: left;
  padding: 12px 16px;
  font-weight: 600;
  background: #f9fafb;
  border-bottom: 1px solid #e5e7eb;
  white-space: nowrap;
}

.table-cell {
  padding: 12px 16px;
  vertical-align: middle;
  border-bottom: 1px solid #f3f4f6;
}

.quote-row:hover {
  background: #f9fafb;
}

.quote-name-cell {
  max-width: 200px;
}

.quote-name-button {
  background: none;
  border: none;
  color: #3b82f6;
  cursor: pointer;
  font-weight: 500;
  text-align: left;
  padding: 0;
  text-decoration: underline;
  word-break: break-word;
}

.quote-name-button:hover {
  color: #2563eb;
}

.total-cell {
  font-family: 'SF Mono', 'Monaco', 'Inconsolata', 'Roboto Mono', monospace;
  font-weight: 600;
  color: #059669;
}

.date-cell {
  font-size: 14px;
  color: #6b7280;
  white-space: nowrap;
}

.actions-cell {
  width: 140px;
}

.action-buttons {
  display: flex;
  gap: 8px;
}

/* Modal */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 16px;
}

.modal-content {
  background: white;
  border-radius: 8px;
  padding: 24px;
  max-width: 500px;
  width: 100%;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}

.modal-title {
  font-size: 20px;
  font-weight: 600;
  margin: 0 0 20px 0;
}

.form-group {
  margin-bottom: 16px;
}

.field-label {
  display: block;
  margin-bottom: 4px;
  font-weight: 500;
  color: #374151;
}

.modal-actions {
  display: flex;
  gap: 12px;
  justify-content: flex-end;
  margin-top: 24px;
}

/* Delete Confirmation */
.delete-message {
  margin-bottom: 8px;
  color: #374151;
}

.delete-warning {
  font-size: 14px;
  color: #dc2626;
  font-weight: 500;
}

/* Form Fields */
.form-field {
  width: 100%;
  border: 1px solid;
  border-radius: 6px;
  padding: 8px 12px;
  transition: all 0.2s;
  font-size: 14px;
}

.field--light {
  background: white;
  border-color: #d1d5db;
  color: #111827;
}

.field--light:focus {
  border-color: #3b82f6;
  outline: none;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.field--dark {
  background: #374151;
  border-color: #4b5563;
  color: #f9fafb;
}

.field--dark:focus {
  border-color: #60a5fa;
  outline: none;
  box-shadow: 0 0 0 3px rgba(96, 165, 250, 0.1);
}

/* Buttons */
.btn {
  padding: 8px 16px;
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
  white-space: nowrap;
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-small {
  padding: 4px 8px;
  font-size: 12px;
  min-width: auto;
}

.btn--primary-light {
  background: #3b82f6;
  color: white;
}

.btn--primary-light:hover:not(:disabled) {
  background: #2563eb;
}

.btn--primary-dark {
  background: #2563eb;
  color: white;
}

.btn--primary-dark:hover:not(:disabled) {
  background: #1d4ed8;
}

.btn--secondary-light {
  background: #f3f4f6;
  color: #374151;
  border: 1px solid #d1d5db;
}

.btn--secondary-light:hover:not(:disabled) {
  background: #e5e7eb;
}

.btn--secondary-dark {
  background: #374151;
  color: #d1d5db;
  border: 1px solid #4b5563;
}

.btn--secondary-dark:hover:not(:disabled) {
  background: #4b5563;
}

.btn-danger {
  background: #dc2626 !important;
  color: white !important;
  border-color: #dc2626 !important;
}

.btn-danger:hover:not(:disabled) {
  background: #b91c1c !important;
}

/* Dark mode for modal */
@media (prefers-color-scheme: dark) {
  .modal-content {
    background: #1f2937;
    color: #f9fafb;
  }
  
  .field-label {
    color: #f9fafb;
  }
  
  .delete-message {
    color: #f9fafb;
  }
  
  .quotes-table {
    background: #1f2937;
    color: #f9fafb;
  }
  
  .table-header {
    background: #374151;
    border-color: #4b5563;
  }
  
  .table-cell {
    border-color: #374151;
  }
  
  .quote-row:hover {
    background: #374151;
  }
  
  .quotes-table-container {
    border-color: #4b5563;
  }
}

/* Responsive Design */
@media (max-width: 768px) {
  .quote-manager-header {
    flex-direction: column;
    align-items: stretch;
  }
  
  .quotes-header {
    flex-direction: column;
    align-items: stretch;
  }
  
  .quotes-table-container {
    font-size: 14px;
  }
  
  .table-header,
  .table-cell {
    padding: 8px 12px;
  }
  
  .action-buttons {
    flex-direction: column;
    gap: 4px;
  }
  
  .modal-content {
    margin: 16px;
    padding: 20px;
  }
  
  .modal-actions {
    flex-direction: column;
  }
}

@media (max-width: 480px) {
  .section-title {
    font-size: 20px;
  }
  
  .quotes-title {
    font-size: 16px;
  }
  
  .table-header,
  .table-cell {
    padding: 6px 8px;
  }
  
  .btn {
    width: 100%;
  }
}
</style>