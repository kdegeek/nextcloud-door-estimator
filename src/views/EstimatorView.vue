<template>
  <div class="estimator-view">
    <!-- Quote Management Section -->
    <QuoteManager />
    
    <!-- Estimator Sections -->
    <div class="estimator-sections">
      <h2 class="estimator-title">Quote Builder</h2>
      <div v-for="section in sections" :key="section.key" class="section-container">
      <div :class="sectionClasses">
        <h2 class="section-title">
          {{ section.title }}
        </h2>
        
        <!-- Frame Type Selection -->
        <div v-if="section.hasFrameType" class="frame-type-selector">
          <label class="field-label" :for="`frame-type-${section.key}`">
            Frame Type:
          </label>
          <select
            v-model="(quoteData[section.key][0] as any).frameType"
            :id="`frame-type-${section.key}`"
            :class="fieldClasses"
            @change="updateQuoteItem(section.key, 0, 'frameType', (quoteData[section.key][0] as any).frameType)"
          >
            <option v-for="(items, type) in pricingData.frames" :key="type" :value="type">
              {{ type }}
            </option>
          </select>
        </div>
        
        <!-- Items Table -->
        <div class="table-container">
          <table class="items-table" :aria-label="`${section.title} items`">
            <caption class="table-caption">{{ section.title }} items</caption>
            <thead>
              <tr>
                <th scope="col" class="table-header">Item</th>
                <th scope="col" class="table-header">Qty</th>
                <th scope="col" class="table-header">Price</th>
                <th scope="col" class="table-header">Total</th>
                <th scope="col" class="table-header">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(item, idx) in quoteData[section.key]" :key="`${section.key}-${item.id}-${idx}`">
                <td class="table-cell">
                  <div class="item-input-container">
                    <input
                      v-model="item.item"
                      :class="[fieldClasses, 'w-full']"
                      :placeholder="section.hasFrameType && idx === 0 ? 'Frame Item' : 'Item'"
                      aria-label="Item"
                      :list="`${section.key}-items-${idx}`"
                      @input="updateQuoteItem(section.key, idx, 'item', item.item)"
                      @blur="handleItemBlur(section.key, idx)"
                    >
                    <datalist :id="`${section.key}-items-${idx}`">
                      <option 
                        v-for="pricingItem in getAvailableItems(section.key, section.hasFrameType ? (item as any).frameType : undefined)" 
                        :key="pricingItem.item" 
                        :value="pricingItem.item"
                      >
                        {{ pricingItem.item }} - ${{ formatCurrency(pricingItem.price || 0) }}
                      </option>
                    </datalist>
                  </div>
                </td>
                <td class="table-cell">
                  <input
                    v-model.number="item.qty"
                    type="number"
                    min="0"
                    max="10000"
                    step="1"
                    :class="[fieldClasses, 'w-20']"
                    aria-label="Quantity"
                    @input="handleQuantityInput(section.key, idx, item.qty)"
                  >
                </td>
                <td class="table-cell">
                  <div class="price-input-container">
                    <input
                      v-model.number="item.price"
                      type="number"
                      min="0"
                      max="1000000"
                      step="0.01"
                      :class="[fieldClasses, 'w-24', { 'price-modified': isPriceModified(section.key, idx) }]"
                      aria-label="Unit price"
                      @input="handlePriceInput(section.key, idx, item.price)"
                    >
                    <div v-if="isPriceModified(section.key, idx)" class="price-override-indicator">
                      <span class="price-indicator" title="Price manually modified">*</span>
                      <button
                        :class="[buttonClasses.secondary, 'btn-tiny']"
                        @click="resetPriceToBase(section.key, idx)"
                        title="Reset to base price"
                      >
                        ↺
                      </button>
                    </div>
                  </div>
                </td>
                <td class="table-cell total-cell">
                  ${{ formatCurrency(item.total) }}
                </td>
                <td class="table-cell actions-cell">
                  <button
                    v-if="quoteData[section.key].length > 1"
                    :class="[buttonClasses.secondary, 'btn-small']"
                    @click="removeLineItem(section.key, idx)"
                    title="Remove item"
                  >
                    ×
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
          
          <!-- Add Item Button -->
          <div class="add-item-container">
            <button
              :class="[buttonClasses.secondary, 'btn-small']"
              @click="addLineItem(section.key)"
            >
              + Add Item
            </button>
          </div>
        </div>
        
        <!-- Markup Controls -->
        <div class="markup-controls">
          <div class="markup-input-group">
            <label class="markup-label">
              Markup: 
              <input
                :value="getMarkupForSection(section.key)"
                type="number"
                min="0"
                max="100"
                step="0.1"
                :class="[fieldClasses, 'markup-input', { 'markup-modified': isMarkupOverridden(section.key) }]"
                @input="updateSectionMarkup(section.key, ($event.target as HTMLInputElement).value)"
              >%
            </label>
            <div v-if="isMarkupOverridden(section.key)" class="markup-override-indicator">
              <span class="markup-indicator" title="Markup modified from default">*</span>
              <button
                :class="[buttonClasses.secondary, 'btn-tiny']"
                @click="resetMarkupToDefault(section.key)"
                title="Reset to default markup"
              >
                ↺
              </button>
            </div>
          </div>
        </div>
        
        <!-- Section Total -->
        <div class="section-total">
          <div class="subtotal-row">
            <span class="subtotal-label">Subtotal:</span>
            <span class="subtotal-amount">${{ formatCurrency(getSectionSubtotal(section.key)) }}</span>
          </div>
          <div class="markup-row">
            <span class="markup-label">Markup ({{ getMarkupForSection(section.key) }}%):</span>
            <span class="markup-amount">${{ formatCurrency(getSectionMarkupAmount(section.key)) }}</span>
          </div>
          <div class="total-row">
            <span class="total-label">Section Total:</span>
            <span class="total-amount">${{ formatCurrency(getSectionTotal(section.key)) }}</span>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Grand Total -->
    <div class="grand-total">
      <span class="grand-total-label">Grand Total:</span>
      <span class="grand-total-amount">${{ formatCurrency(grandTotal) }}</span>
    </div>
    
      <!-- Quote Actions -->
      <div class="quote-actions">
        <button
          :class="buttonClasses.secondary"
          @click="resetQuote"
        >
          Reset Quote
        </button>
      </div>
      
      <!-- Loading Indicator -->
      <div v-if="loading" class="loading-indicator">
        Loading...
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useEstimatorStore, useThemeStore, useNotificationsStore } from '../stores'
import { InputValidator, ValidationRules } from '../utils/validation'
import { QuoteManager } from '../components'
import type { SectionKey } from '../types'

// Stores
const estimatorStore = useEstimatorStore()
const themeStore = useThemeStore()
const notificationsStore = useNotificationsStore()

// Destructure store state and actions
const { 
  quoteData, 
  markups,
  defaultMarkups,
  markupsOverridden,
  pricingData, 
  loading, 
  grandTotal,
  updateQuoteItem,
  getSectionTotal,
  addLineItem,
  removeLineItem,
  resetQuote,
  updateMarkupForSection,
  isMarkupOverridden,
  resetMarkupToDefault,
  isPriceOverridden,
  getBasePrice,
  resetPriceToBase,
  loadDefaultMarkups
} = estimatorStore

const isDarkMode = computed(() => themeStore.isDarkMode)

// Section definitions
interface Section {
  key: SectionKey
  title: string
  hasFrameType?: boolean
}

const sections: Section[] = [
  { key: 'doors', title: 'Doors' },
  { key: 'doorOptions', title: 'Door Options' },
  { key: 'inserts', title: 'Inserts' },
  { key: 'frames', title: 'Frames', hasFrameType: true },
  { key: 'frameOptions', title: 'Frame Options' },
  { key: 'hinges', title: 'Hinges' },
  { key: 'weatherstrip', title: 'Weatherstrip' },
  { key: 'closers', title: 'Closers' },
  { key: 'locksets', title: 'Locksets' },
  { key: 'exitDevices', title: 'Exit Devices' },
  { key: 'hardware', title: 'Hardware' },
]

// Computed classes
const sectionClasses = computed(() => [
  'quote-section',
  isDarkMode.value ? 'section--dark' : 'section--light'
])

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

// Get available items for autocomplete
const getAvailableItems = (sectionKey: SectionKey, frameType?: string) => {
  if (sectionKey === 'frames' && frameType && pricingData.frames && typeof pricingData.frames === 'object') {
    const frameItems = pricingData.frames[frameType]
    return Array.isArray(frameItems) ? frameItems : []
  }
  
  const categoryData = pricingData[sectionKey]
  return Array.isArray(categoryData) ? categoryData : []
}

// Handle item input blur for price lookup and validation
const handleItemBlur = (sectionKey: SectionKey, index: number) => {
  const item = quoteData[sectionKey][index]
  
  // Validate and sanitize item name
  if (item && item.item) {
    const sanitized = InputValidator.sanitizeString(item.item)
    if (sanitized !== item.item) {
      updateQuoteItem(sectionKey, index, 'item', sanitized)
      notificationsStore.warning('Item name was sanitized for security')
    }
    
    // Validate item
    const validation = InputValidator.validateQuoteLineItem(item)
    if (!validation.isValid) {
      validation.errors.forEach(error => {
        if (error.includes('Item name')) {
          notificationsStore.error(`${sectionKey}[${index}]: ${error}`)
        }
      })
    }
  }
  
  // Trigger price lookup when user finishes typing
  setTimeout(() => {
    estimatorStore.lookupItemPrice(sectionKey, index)
  }, 100)
}

// Validate quantity input
const handleQuantityInput = (sectionKey: SectionKey, index: number, value: number) => {
  const validation = InputValidator.validateField(value, ValidationRules.quantity)
  
  if (!validation.isValid) {
    validation.errors.forEach(error => {
      notificationsStore.error(`${sectionKey}[${index}] quantity: ${error}`)
    })
  }
  
  updateQuoteItem(sectionKey, index, 'qty', value)
}

// Validate price input
const handlePriceInput = (sectionKey: SectionKey, index: number, value: number) => {
  const validation = InputValidator.validateField(value, ValidationRules.price)
  
  if (!validation.isValid) {
    validation.errors.forEach(error => {
      notificationsStore.error(`${sectionKey}[${index}] price: ${error}`)
    })
  }
  
  updateQuoteItem(sectionKey, index, 'price', value)
}

// Check if price has been manually modified (using store method)
const isPriceModified = (sectionKey: SectionKey, index: number) => {
  return isPriceOverridden(sectionKey, index)
}

// Get markup percentage for a section
const getMarkupForSection = (sectionKey: SectionKey): number => {
  if (['doors', 'doorOptions', 'inserts'].includes(sectionKey)) {
    return markups.doors
  } else if (['frames', 'frameOptions'].includes(sectionKey)) {
    return markups.frames
  } else {
    return markups.hardware
  }
}

// Update markup for a section with validation
const updateSectionMarkup = (sectionKey: SectionKey, value: string | number) => {
  const numValue = typeof value === 'number' ? value : parseFloat(String(value))
  
  // Validate markup
  const validation = InputValidator.validateMarkup(numValue)
  
  if (!validation.isValid) {
    validation.errors.forEach(error => {
      notificationsStore.error(`${sectionKey} markup: ${error}`)
    })
    return
  }
  
  const markup = Number.isNaN(numValue) || numValue < 0 ? 0 : Math.min(100, numValue)
  updateMarkupForSection(sectionKey, markup)
}

// Get section subtotal (before markup)
const getSectionSubtotal = (sectionKey: SectionKey): number => {
  return quoteData[sectionKey].reduce((sum, item) => sum + (item.total || 0), 0)
}

// Get section markup amount
const getSectionMarkupAmount = (sectionKey: SectionKey): number => {
  const subtotal = getSectionSubtotal(sectionKey)
  const markupPercent = getMarkupForSection(sectionKey)
  return subtotal * (markupPercent / 100)
}

// Initialize component
onMounted(async () => {
  // Load default markups for comparison
  await loadDefaultMarkups()
})


</script>

<style scoped>
/* Estimator View */
.estimator-view {
  display: flex;
  flex-direction: column;
  gap: 32px;
}

.estimator-sections {
  display: flex;
  flex-direction: column;
  gap: 32px;
}

.estimator-title {
  font-size: 24px;
  font-weight: 600;
  margin: 0 0 16px 0;
  padding-bottom: 16px;
  border-bottom: 2px solid #e5e7eb;
}

.section-container {
  margin-bottom: 32px;
}

.quote-section {
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  padding: 24px;
  border: 1px solid;
  transition: all 0.2s;
}

.section--light {
  background: white;
  border-color: #e5e7eb;
}

.section--dark {
  background: #1f2937;
  border-color: #374151;
}

.section-title {
  font-size: 20px;
  font-weight: 600;
  margin-bottom: 16px;
}

.frame-type-selector {
  margin-bottom: 16px;
}

.field-label {
  display: block;
  margin-bottom: 4px;
  font-weight: 500;
}

/* Form Fields */
.form-field {
  border: 1px solid;
  border-radius: 6px;
  padding: 8px 12px;
  transition: all 0.2s;
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

/* Tables */
.table-container {
  margin-bottom: 16px;
  overflow-x: auto;
}

.items-table {
  width: 100%;
  border-collapse: collapse;
}

.table-caption {
  font-size: 14px;
  color: #6b7280;
  margin-bottom: 8px;
  text-align: left;
}

.table-header {
  text-align: left;
  padding: 8px;
  font-weight: 500;
  border-bottom: 1px solid #e5e7eb;
}

.table-cell {
  padding: 8px;
  vertical-align: middle;
}

.total-cell {
  font-family: 'SF Mono', 'Monaco', 'Inconsolata', 'Roboto Mono', monospace;
  font-weight: 500;
}

.actions-cell {
  width: 60px;
  text-align: center;
}

/* Item Input */
.item-input-container {
  position: relative;
  width: 100%;
}

/* Price Input */
.price-input-container {
  position: relative;
  display: flex;
  align-items: center;
  gap: 4px;
}

.price-modified {
  background-color: #fef3c7 !important;
  border-color: #f59e0b !important;
}

.price-override-indicator {
  display: flex;
  align-items: center;
  gap: 2px;
}

.price-indicator {
  color: #f59e0b;
  font-weight: bold;
  font-size: 16px;
}

.btn-tiny {
  padding: 2px 4px;
  font-size: 10px;
  min-width: auto;
  line-height: 1;
}

/* Add Item */
.add-item-container {
  margin-top: 8px;
  text-align: right;
}

.btn-small {
  padding: 4px 8px;
  font-size: 12px;
  min-width: auto;
}

/* Markup Controls */
.markup-controls {
  margin-bottom: 16px;
  padding: 12px;
  background: rgba(0, 0, 0, 0.02);
  border-radius: 6px;
}

.markup-input-group {
  display: flex;
  align-items: center;
  gap: 8px;
}

.markup-label {
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: 500;
}

.markup-input {
  width: 60px;
  text-align: center;
}

.markup-modified {
  background-color: #fef3c7 !important;
  border-color: #f59e0b !important;
}

.markup-override-indicator {
  display: flex;
  align-items: center;
  gap: 2px;
}

.markup-indicator {
  color: #f59e0b;
  font-weight: bold;
  font-size: 14px;
}

/* Section Total */
.section-total {
  padding-top: 16px;
  border-top: 1px solid;
  border-color: inherit;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.subtotal-row,
.markup-row,
.total-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.subtotal-label,
.markup-label {
  font-weight: 400;
  color: #6b7280;
}

.subtotal-amount,
.markup-amount {
  font-family: 'SF Mono', 'Monaco', 'Inconsolata', 'Roboto Mono', monospace;
  color: #6b7280;
}

.total-label {
  font-weight: 600;
}

.total-amount {
  font-weight: 700;
  color: #059669;
  font-family: 'SF Mono', 'Monaco', 'Inconsolata', 'Roboto Mono', monospace;
}

/* Grand Total */
.grand-total {
  display: flex;
  justify-content: flex-end;
  align-items: center;
  gap: 16px;
  font-size: 24px;
  font-weight: 700;
  padding: 24px;
  border-radius: 8px;
  background: rgba(16, 185, 129, 0.1);
  border: 1px solid rgba(16, 185, 129, 0.2);
}

.grand-total-label {
  color: inherit;
}

.grand-total-amount {
  color: #059669;
}

/* Quote Actions */
.quote-actions {
  display: flex;
  gap: 12px;
  justify-content: flex-end;
  padding: 16px 0;
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
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
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

/* Loading Indicator */
.loading-indicator {
  text-align: center;
  padding: 16px;
  color: #3b82f6;
  font-weight: 500;
}

/* Responsive Design */
@media (max-width: 768px) {
  .quote-section {
    padding: 16px;
  }
  
  .grand-total {
    font-size: 20px;
    padding: 16px;
  }
  
  .quote-actions {
    flex-direction: column;
  }
  
  .btn {
    width: 100%;
  }
  
  .table-container {
    font-size: 14px;
  }
  
  .form-field {
    padding: 6px 8px;
  }
}

@media (max-width: 480px) {
  .section-title {
    font-size: 18px;
  }
  
  .grand-total {
    font-size: 18px;
    flex-direction: column;
    gap: 8px;
  }
}
</style>