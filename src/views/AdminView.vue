<template>
  <div class="admin-view">
    <!-- Permission Check Loading -->
    <div v-if="checkingPermissions" class="permission-check">
      <div class="loading-spinner"></div>
      <p>Checking permissions...</p>
    </div>
    
    <!-- Access Denied -->
    <div v-else-if="!isAdmin" class="access-denied">
      <div class="denied-icon">🚫</div>
      <h3>Access Denied</h3>
      <p>You need administrator privileges to access this section.</p>
      <p>Please contact your system administrator for access.</p>
    </div>
    
    <!-- Admin Interface (only shown to admins) -->
    <template v-else>
      <!-- Admin Header -->
      <div class="admin-header">
        <h2 class="admin-title">Admin Panel</h2>
        <p class="admin-subtitle">Manage pricing data, import/export, and system configuration</p>
      </div>

      <!-- Admin Actions -->
      <div class="admin-actions">
        <button
          :class="buttonClasses.primary"
          @click="showImportDialog = true"
          :disabled="loading"
        >
          <span class="btn-icon">📁</span>
          Import Data
        </button>
        <button
          :class="buttonClasses.secondary"
          @click="exportData"
          :disabled="loading"
        >
          <span class="btn-icon">💾</span>
          Export Data
        </button>
        <button
          :class="buttonClasses.secondary"
          @click="addNewItem"
          :disabled="loading"
        >
          <span class="btn-icon">➕</span>
          Add Item
        </button>
      </div>

      <!-- Search and Filter Controls -->
      <div class="search-controls">
        <div class="search-input-container">
          <input
            v-model="searchQuery"
            :class="[fieldClasses, 'search-input']"
            placeholder="Search items by name, category, or price..."
            @input="handleSearch"
          >
          <span class="search-icon">🔍</span>
        </div>
        
        <div class="filter-controls">
          <select
            v-model="selectedCategory"
            :class="[fieldClasses, 'category-filter']"
            @change="handleCategoryFilter"
          >
            <option value="">All Categories</option>
            <option v-for="category in availableCategories" :key="category" :value="category">
              {{ formatCategoryName(category) }}
            </option>
          </select>
          
          <select
            v-model="sortBy"
            :class="[fieldClasses, 'sort-select']"
            @change="handleSort"
          >
            <option value="item">Sort by Name</option>
            <option value="category">Sort by Category</option>
            <option value="price">Sort by Price</option>
            <option value="updated_at">Sort by Date</option>
          </select>
          
          <button
            :class="[buttonClasses.secondary, 'sort-direction-btn']"
            @click="toggleSortDirection"
            :title="sortDirection === 'asc' ? 'Sort Ascending' : 'Sort Descending'"
          >
            {{ sortDirection === 'asc' ? '↑' : '↓' }}
          </button>
        </div>
      </div>

      <!-- Pricing Data Grid -->
      <div class="pricing-grid-container">
        <div v-if="loading && !pricingItems.length" class="loading-state">
          <div class="loading-spinner"></div>
          <p>Loading pricing data...</p>
        </div>
        
        <div v-else-if="pricingItems.length === 0" class="empty-state">
          <div class="empty-icon">📋</div>
          <h3>No Pricing Data</h3>
          <p>Import data or add items manually to get started.</p>
          <button :class="buttonClasses.primary" @click="showImportDialog = true">
            Import Data
          </button>
        </div>
        
        <div v-else class="pricing-grid">
          <!-- Grid Header -->
          <div class="grid-header">
            <div class="grid-stats">
              <span class="stats-text">
                Showing {{ filteredItems.length }} of {{ pricingItems.length }} items
              </span>
              <span v-if="searchQuery" class="search-results">
                for "{{ searchQuery }}"
              </span>
            </div>
          </div>
          
          <!-- Data Table -->
          <div class="table-container">
            <table class="pricing-table">
              <thead>
                <tr>
                  <th class="table-header sortable" @click="setSortBy('category')">
                    Category
                    <span v-if="sortBy === 'category'" class="sort-indicator">
                      {{ sortDirection === 'asc' ? '↑' : '↓' }}
                    </span>
                  </th>
                  <th class="table-header">Subcategory</th>
                  <th class="table-header sortable" @click="setSortBy('item')">
                    Item Name
                    <span v-if="sortBy === 'item'" class="sort-indicator">
                      {{ sortDirection === 'asc' ? '↑' : '↓' }}
                    </span>
                  </th>
                  <th class="table-header sortable" @click="setSortBy('price')">
                    Price
                    <span v-if="sortBy === 'price'" class="sort-indicator">
                      {{ sortDirection === 'asc' ? '↑' : '↓' }}
                    </span>
                  </th>
                  <th class="table-header">Description</th>
                  <th class="table-header">Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="item in paginatedItems"
                  :key="`${item.category}-${item.item}-${item.id || 'new'}`"
                  class="table-row"
                  :class="{ 'row-editing': editingInlineId === item.id }"
                >
                  <td class="table-cell">
                    <span class="category-badge" :class="`category-${item.category}`">
                      {{ formatCategoryName(item.category) }}
                    </span>
                  </td>
                  <td class="table-cell">
                    <span v-if="item.subcategory" class="subcategory-text">
                      {{ item.subcategory }}
                    </span>
                    <span v-else class="text-muted">—</span>
                  </td>
                  <td class="table-cell">
                    <div v-if="editingInlineId === item.id" class="inline-edit">
                      <input
                        v-model="inlineEditData.item"
                        :class="[fieldClasses, 'inline-input']"
                        @keyup.enter="saveInlineEdit"
                        @keyup.escape="cancelInlineEdit"
                      >
                    </div>
                    <div v-else class="item-name" @dblclick="startInlineEdit(item)">
                      {{ item.item }}
                    </div>
                  </td>
                  <td class="table-cell">
                    <div v-if="editingInlineId === item.id" class="inline-edit">
                      <input
                        v-model.number="inlineEditData.price"
                        type="number"
                        min="0"
                        step="0.01"
                        :class="[fieldClasses, 'inline-input price-input']"
                        @keyup.enter="saveInlineEdit"
                        @keyup.escape="cancelInlineEdit"
                      >
                    </div>
                    <div v-else class="price-display" @dblclick="startInlineEdit(item)">
                      ${{ formatCurrency(item.price) }}
                    </div>
                  </td>
                  <td class="table-cell">
                    <div v-if="editingInlineId === item.id" class="inline-edit">
                      <input
                        v-model="inlineEditData.description"
                        :class="[fieldClasses, 'inline-input']"
                        @keyup.enter="saveInlineEdit"
                        @keyup.escape="cancelInlineEdit"
                      >
                    </div>
                    <div v-else class="description-text" @dblclick="startInlineEdit(item)">
                      {{ item.description || '—' }}
                    </div>
                  </td>
                  <td class="table-cell actions-cell">
                    <div v-if="editingInlineId === item.id" class="inline-actions">
                      <button
                        :class="[buttonClasses.primary, 'btn-small']"
                        @click="saveInlineEdit"
                        :disabled="loading"
                      >
                        ✓
                      </button>
                      <button
                        :class="[buttonClasses.secondary, 'btn-small']"
                        @click="cancelInlineEdit"
                        :disabled="loading"
                      >
                        ✕
                      </button>
                    </div>
                    <div v-else class="row-actions">
                      <button
                        :class="[buttonClasses.secondary, 'btn-small']"
                        @click="editItem(item)"
                        title="Edit item"
                      >
                        ✏️
                      </button>
                      <button
                        :class="[buttonClasses.secondary, 'btn-small', 'btn-danger']"
                        @click="deleteItem(item)"
                        title="Delete item"
                      >
                        🗑️
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          
          <!-- Pagination -->
          <div v-if="totalPages > 1" class="pagination">
            <button
              :class="[buttonClasses.secondary, 'pagination-btn']"
              @click="goToPage(currentPage - 1)"
              :disabled="currentPage <= 1"
            >
              ← Previous
            </button>
            
            <div class="page-numbers">
              <button
                v-for="page in visiblePages"
                :key="page"
                :class="[
                  buttonClasses.secondary,
                  'pagination-btn',
                  'page-btn',
                  { 'page-active': page === currentPage }
                ]"
                @click="goToPage(page)"
              >
                {{ page }}
              </button>
            </div>
            
            <button
              :class="[buttonClasses.secondary, 'pagination-btn']"
              @click="goToPage(currentPage + 1)"
              :disabled="currentPage >= totalPages"
            >
              Next →
            </button>
          </div>
        </div>
      </div>
    </template>

    <!-- Import Dialog -->
    <div v-if="showImportDialog" class="modal-overlay" @click="closeImportDialog">
      <div class="import-dialog" @click.stop>
        <div class="dialog-header">
          <h3>Import Pricing Data</h3>
          <button class="close-btn" @click="closeImportDialog">×</button>
        </div>
        
        <div class="dialog-content">
          <div class="import-options">
            <div class="import-method">
              <h4>Upload File</h4>
              <p>Support formats: JSON, CSV, Excel (.xls, .xlsx)</p>
              <input
                ref="fileInput"
                type="file"
                accept=".json,.csv,.xls,.xlsx"
                @change="handleFileSelect"
                :class="fieldClasses"
              >
              <div v-if="selectedFile" class="file-info">
                <span class="file-name">{{ selectedFile.name }}</span>
                <span class="file-size">({{ formatFileSize(selectedFile.size) }})</span>
              </div>
            </div>
            
            <div class="import-method">
              <h4>Paste JSON Data</h4>
              <textarea
                v-model="importText"
                :class="[fieldClasses, 'import-textarea']"
                placeholder="Paste JSON data here..."
                rows="8"
              />
            </div>
          </div>
          
          <div v-if="importProgress.show" class="import-progress">
            <div class="progress-bar">
              <div 
                class="progress-fill" 
                :style="{ width: `${importProgress.percent}%` }"
              />
            </div>
            <p class="progress-text">{{ importProgress.message }}</p>
          </div>
          
          <div v-if="importErrors.length > 0" class="import-errors">
            <h4>Import Errors:</h4>
            <ul>
              <li v-for="(error, idx) in importErrors" :key="idx" class="error-item">
                {{ error }}
              </li>
            </ul>
          </div>
        </div>
        
        <div class="dialog-actions">
          <button
            :class="buttonClasses.primary"
            @click="performImport"
            :disabled="loading || (!selectedFile && !importText.trim())"
          >
            {{ loading ? 'Importing...' : 'Import' }}
          </button>
          <button
            :class="buttonClasses.secondary"
            @click="closeImportDialog"
            :disabled="loading"
          >
            Cancel
          </button>
        </div>
      </div>
    </div>

    <!-- Add/Edit Item Dialog -->
    <div v-if="showEditDialog" class="modal-overlay" @click="closeEditDialog">
      <div class="edit-dialog" @click.stop>
        <div class="dialog-header">
          <h3>{{ editingItem.id ? 'Edit Item' : 'Add New Item' }}</h3>
          <button class="close-btn" @click="closeEditDialog">×</button>
        </div>
        
        <div class="dialog-content">
          <div class="form-grid">
            <div class="form-group">
              <label class="form-label">Category *</label>
              <select
                v-model="editingItem.category"
                :class="[fieldClasses, { 'field-error': editErrors.category }]"
                required
              >
                <option value="">Select Category</option>
                <option v-for="category in availableCategories" :key="category" :value="category">
                  {{ formatCategoryName(category) }}
                </option>
              </select>
              <span v-if="editErrors.category" class="error-text">{{ editErrors.category }}</span>
            </div>
            
            <div v-if="editingItem.category === 'frames'" class="form-group">
              <label class="form-label">Frame Type</label>
              <select
                v-model="editingItem.subcategory"
                :class="fieldClasses"
              >
                <option value="">Select Frame Type</option>
                <option value="HM Drywall">HM Drywall</option>
                <option value="HM EWA">HM EWA</option>
                <option value="HM USA">HM USA</option>
              </select>
            </div>
            
            <div class="form-group">
              <label class="form-label">Item Name *</label>
              <input
                v-model="editingItem.item"
                :class="[fieldClasses, { 'field-error': editErrors.item }]"
                placeholder="Enter item name"
                required
              >
              <span v-if="editErrors.item" class="error-text">{{ editErrors.item }}</span>
            </div>
            
            <div class="form-group">
              <label class="form-label">Price *</label>
              <input
                v-model.number="editingItem.price"
                type="number"
                min="0"
                step="0.01"
                :class="[fieldClasses, { 'field-error': editErrors.price }]"
                placeholder="0.00"
                required
              >
              <span v-if="editErrors.price" class="error-text">{{ editErrors.price }}</span>
            </div>
            
            <div class="form-group full-width">
              <label class="form-label">Description</label>
              <textarea
                v-model="editingItem.description"
                :class="fieldClasses"
                placeholder="Optional description"
                rows="3"
              />
            </div>
          </div>
        </div>
        
        <div class="dialog-actions">
          <button
            :class="buttonClasses.primary"
            @click="saveItem"
            :disabled="loading || !isEditFormValid"
          >
            {{ loading ? 'Saving...' : 'Save' }}
          </button>
          <button
            :class="buttonClasses.secondary"
            @click="closeEditDialog"
            :disabled="loading"
          >
            Cancel
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch, nextTick, defineAsyncComponent } from 'vue'
import { useEstimatorStore, useThemeStore, useNotificationsStore } from '../stores'
import { InputValidator, ValidationRules } from '../utils/validation'
import { performanceMonitor, debounce, throttle, MemoryMonitor } from '../utils/performance'
import type { PricingItem } from '../types'

// Stores
const estimatorStore = useEstimatorStore()
const themeStore = useThemeStore()
const notificationsStore = useNotificationsStore()

// State
const loading = ref(false)
const isAdmin = ref(false)
const checkingPermissions = ref(true)
const searchQuery = ref('')
const selectedCategory = ref('')
const sortBy = ref<'item' | 'category' | 'price' | 'updated_at'>('item')
const sortDirection = ref<'asc' | 'desc'>('asc')

// Import dialog state
const showImportDialog = ref(false)
const selectedFile = ref<File | null>(null)
const importText = ref('')
const importProgress = ref({
  show: false,
  percent: 0,
  message: ''
})
const importErrors = ref<string[]>([])

// Edit dialog state
const showEditDialog = ref(false)
const editingItem = ref<Partial<PricingItem & { id?: number; subcategory?: string; description?: string }>>({})
const editErrors = ref<Record<string, string>>({})

// Inline editing state
const editingInlineId = ref<number | null>(null)
const inlineEditData = ref<Partial<PricingItem & { description?: string }>>({})

// Pagination with performance optimization
const currentPage = ref(1)
const itemsPerPage = 25
const maxItemsToRender = 1000 // Limit rendering for performance

// File input ref
const fileInput = ref<HTMLInputElement>()

// Computed
const isDarkMode = computed(() => themeStore.isDarkMode)

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

// Convert pricing data to flat array for table display
const pricingItems = computed(() => {
  const items: Array<PricingItem & { id?: number; subcategory?: string; description?: string }> = []
  let id = 1
  
  Object.entries(estimatorStore.pricingData).forEach(([category, data]) => {
    if (Array.isArray(data)) {
      data.forEach(item => {
        items.push({
          id: id++,
          category,
          item: item.item,
          price: item.price,
          description: item.description || ''
        })
      })
    } else if (typeof data === 'object' && data !== null) {
      // Handle frames with subcategories
      Object.entries(data).forEach(([subcategory, subItems]) => {
        if (Array.isArray(subItems)) {
          subItems.forEach(item => {
            items.push({
              id: id++,
              category,
              subcategory,
              item: item.item,
              price: item.price,
              description: item.description || ''
            })
          })
        }
      })
    }
  })
  
  return items
})

// Available categories for filters
const availableCategories = computed(() => {
  const categories = new Set<string>()
  pricingItems.value.forEach(item => categories.add(item.category))
  return Array.from(categories).sort()
})

// Filtered and sorted items
const filteredItems = computed(() => {
  let items = pricingItems.value

  // Apply search filter
  if (searchQuery.value.trim()) {
    const query = searchQuery.value.toLowerCase().trim()
    items = items.filter(item =>
      item.item.toLowerCase().includes(query) ||
      item.category.toLowerCase().includes(query) ||
      (item.subcategory && item.subcategory.toLowerCase().includes(query)) ||
      item.price.toString().includes(query) ||
      (item.description && item.description.toLowerCase().includes(query))
    )
  }

  // Apply category filter
  if (selectedCategory.value) {
    items = items.filter(item => item.category === selectedCategory.value)
  }

  // Apply sorting
  items.sort((a, b) => {
    let aVal: string | number
    let bVal: string | number

    switch (sortBy.value) {
      case 'category':
        aVal = a.category
        bVal = b.category
        break
      case 'price':
        aVal = a.price
        bVal = b.price
        break
      case 'updated_at':
        // For now, sort by item name as we don't have updated_at
        aVal = a.item
        bVal = b.item
        break
      case 'item':
      default:
        aVal = a.item
        bVal = b.item
        break
    }

    if (typeof aVal === 'string' && typeof bVal === 'string') {
      const comparison = aVal.localeCompare(bVal)
      return sortDirection.value === 'asc' ? comparison : -comparison
    } else if (typeof aVal === 'number' && typeof bVal === 'number') {
      const comparison = aVal - bVal
      return sortDirection.value === 'asc' ? comparison : -comparison
    }

    return 0
  })

  return items
})

// Paginated items with performance optimization
const paginatedItems = computed(() => {
  performanceMonitor.start('admin_pagination')
  
  // Limit total items for performance
  const itemsToProcess = filteredItems.value.slice(0, maxItemsToRender)
  
  const start = (currentPage.value - 1) * itemsPerPage
  const end = start + itemsPerPage
  const result = itemsToProcess.slice(start, end)
  
  performanceMonitor.end('admin_pagination')
  return result
})

// Pagination computed
const totalPages = computed(() => Math.ceil(filteredItems.value.length / itemsPerPage))

const visiblePages = computed(() => {
  const pages: number[] = []
  const total = totalPages.value
  const current = currentPage.value
  
  // Always show first page
  if (total > 0) pages.push(1)
  
  // Show pages around current page
  for (let i = Math.max(2, current - 1); i <= Math.min(total - 1, current + 1); i++) {
    if (!pages.includes(i)) pages.push(i)
  }
  
  // Always show last page
  if (total > 1 && !pages.includes(total)) pages.push(total)
  
  return pages.sort((a, b) => a - b)
})

// Form validation
const isEditFormValid = computed(() => {
  return editingItem.value.category &&
         editingItem.value.item &&
         editingItem.value.item.trim() !== '' &&
         typeof editingItem.value.price === 'number' &&
         editingItem.value.price >= 0
})

// Methods
const formatCurrency = (amount: number): string => {
  return (amount || 0).toFixed(2)
}

const formatCategoryName = (category: string): string => {
  return category.replace(/([A-Z])/g, ' $1').replace(/^./, str => str.toUpperCase())
}

const formatFileSize = (bytes: number): string => {
  if (bytes === 0) return '0 Bytes'
  const k = 1024
  const sizes = ['Bytes', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
}

// Search and filter handlers with performance optimization
const handleSearch = debounce(() => {
  performanceMonitor.start('admin_search', { query: searchQuery.value })
  currentPage.value = 1 // Reset to first page when searching
  performanceMonitor.end('admin_search')
}, 300)

const handleCategoryFilter = () => {
  performanceMonitor.start('admin_filter', { category: selectedCategory.value })
  currentPage.value = 1 // Reset to first page when filtering
  performanceMonitor.end('admin_filter')
}

const handleSort = () => {
  performanceMonitor.start('admin_sort', { sortBy: sortBy.value, direction: sortDirection.value })
  currentPage.value = 1 // Reset to first page when sorting
  performanceMonitor.end('admin_sort')
}

const setSortBy = (field: typeof sortBy.value) => {
  if (sortBy.value === field) {
    toggleSortDirection()
  } else {
    sortBy.value = field
    sortDirection.value = 'asc'
  }
  currentPage.value = 1
}

const toggleSortDirection = () => {
  sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'
}

// Pagination
const goToPage = (page: number) => {
  if (page >= 1 && page <= totalPages.value) {
    currentPage.value = page
  }
}

// Import functionality
const handleFileSelect = (event: Event) => {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0] || null
  
  if (file) {
    // Comprehensive file validation
    const fileValidation = InputValidator.validateFileUpload(file)
    
    if (!fileValidation.isValid) {
      // Show validation errors
      fileValidation.errors.forEach(error => {
        notificationsStore.error(error)
      })
      
      // Reset file input
      target.value = ''
      selectedFile.value = null
      return
    }
    
    // Additional security checks
    if (file.name.includes('<script') || file.name.includes('javascript:')) {
      notificationsStore.error('File name contains potentially dangerous content')
      target.value = ''
      selectedFile.value = null
      return
    }
    
    // File passed validation
    selectedFile.value = file
    importText.value = '' // Clear text input when file is selected
    notificationsStore.info(`File "${file.name}" selected for import`)
  } else {
    selectedFile.value = null
  }
}

const closeImportDialog = () => {
  if (loading.value) return
  showImportDialog.value = false
  selectedFile.value = null
  importText.value = ''
  importProgress.value = { show: false, percent: 0, message: '' }
  importErrors.value = []
  if (fileInput.value) {
    fileInput.value.value = ''
  }
}

const performImport = async () => {
  try {
    loading.value = true
    importProgress.value = { show: true, percent: 0, message: 'Starting import...' }
    importErrors.value = []

    let result: { success: boolean; imported: number; errors: string[] }

    if (selectedFile.value) {
      // File import
      importProgress.value = { show: true, percent: 25, message: 'Uploading file...' }
      result = await estimatorStore.importPricingFromFile(selectedFile.value)
    } else if (importText.value.trim()) {
      // JSON text import
      importProgress.value = { show: true, percent: 25, message: 'Processing JSON data...' }
      result = await estimatorStore.importPricingFromText(importText.value)
    } else {
      throw new Error('No file or text data provided')
    }

    importProgress.value = { show: true, percent: 75, message: 'Processing data...' }

    if (result.success) {
      importProgress.value = { show: true, percent: 100, message: 'Import completed!' }
      
      // Reload pricing data
      await estimatorStore.loadPricingData()
      
      notificationsStore.success(`Successfully imported ${result.imported} items`)
      
      if (result.errors.length > 0) {
        importErrors.value = result.errors
        notificationsStore.warning(`Import completed with ${result.errors.length} warnings`)
      }
      
      // Close dialog after short delay
      setTimeout(() => {
        closeImportDialog()
      }, 1500)
    } else {
      throw new Error('Import failed')
    }
  } catch (error) {
    const message = error instanceof Error ? error.message : 'Import failed'
    notificationsStore.error(message)
    importProgress.value = { show: false, percent: 0, message: '' }
  } finally {
    loading.value = false
  }
}

// Export functionality
const exportData = async () => {
  try {
    loading.value = true
    
    const exportData = {
      pricingData: estimatorStore.pricingData,
      markups: estimatorStore.markups,
      exportedAt: new Date().toISOString(),
      version: '1.0'
    }
    
    const blob = new Blob([JSON.stringify(exportData, null, 2)], {
      type: 'application/json'
    })
    
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `door-estimator-data-${new Date().toISOString().split('T')[0]}.json`
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    URL.revokeObjectURL(url)
    
    notificationsStore.success('Data exported successfully!')
  } catch (error) {
    const message = error instanceof Error ? error.message : 'Export failed'
    notificationsStore.error(message)
  } finally {
    loading.value = false
  }
}

// Item management
const addNewItem = () => {
  editingItem.value = {
    category: '',
    item: '',
    price: 0,
    description: ''
  }
  editErrors.value = {}
  showEditDialog.value = true
}

const editItem = (item: typeof pricingItems.value[0]) => {
  editingItem.value = { ...item }
  editErrors.value = {}
  showEditDialog.value = true
}

const closeEditDialog = () => {
  if (loading.value) return
  showEditDialog.value = false
  editingItem.value = {}
  editErrors.value = {}
}

const validateEditForm = (): boolean => {
  editErrors.value = {}
  
  // Use comprehensive validation
  const validation = InputValidator.validatePricingItem(editingItem.value)
  
  if (!validation.isValid) {
    // Map validation errors to form fields
    validation.errors.forEach(error => {
      if (error.includes('Item name')) {
        editErrors.value.item = error
      } else if (error.includes('Price')) {
        editErrors.value.price = error
      } else if (error.includes('Category')) {
        editErrors.value.category = error
      } else {
        // Generic error
        editErrors.value.general = error
      }
    })
  }
  
  // Additional real-time sanitization
  if (editingItem.value.item) {
    const sanitized = InputValidator.sanitizeString(editingItem.value.item)
    if (sanitized !== editingItem.value.item) {
      editingItem.value.item = sanitized
      notificationsStore.warning('Item name was sanitized for security')
    }
  }
  
  return Object.keys(editErrors.value).length === 0
}

const saveItem = async () => {
  if (!validateEditForm()) return
  
  try {
    loading.value = true
    
    await estimatorStore.updatePricingItem(editingItem.value as PricingItem)
    
    notificationsStore.success(editingItem.value.id ? 'Item updated successfully' : 'Item added successfully')
    closeEditDialog()
    
    // Reload pricing data
    await estimatorStore.loadPricingData()
  } catch (error) {
    const message = error instanceof Error ? error.message : 'Failed to save item'
    notificationsStore.error(message)
  } finally {
    loading.value = false
  }
}

const deleteItem = async (item: typeof pricingItems.value[0]) => {
  if (!confirm(`Are you sure you want to delete "${item.item}"?`)) return
  
  try {
    loading.value = true
    
    await estimatorStore.deletePricingItem(item.id!)
    
    notificationsStore.success('Item deleted successfully')
    
    // Reload pricing data
    await estimatorStore.loadPricingData()
  } catch (error) {
    const message = error instanceof Error ? error.message : 'Failed to delete item'
    notificationsStore.error(message)
  } finally {
    loading.value = false
  }
}

// Inline editing
const startInlineEdit = (item: typeof pricingItems.value[0]) => {
  editingInlineId.value = item.id!
  inlineEditData.value = { ...item }
  
  // Focus the first input after DOM update
  nextTick(() => {
    const firstInput = document.querySelector('.inline-edit input') as HTMLInputElement
    firstInput?.focus()
  })
}

const saveInlineEdit = async () => {
  if (!editingInlineId.value) return
  
  try {
    loading.value = true
    
    // Validate inline edit data
    if (!inlineEditData.value.item || inlineEditData.value.item.trim() === '') {
      notificationsStore.error('Item name is required')
      return
    }
    
    if (typeof inlineEditData.value.price !== 'number' || inlineEditData.value.price < 0) {
      notificationsStore.error('Price must be a positive number')
      return
    }
    
    // Update the item
    await estimatorStore.updatePricingItem({
      id: editingInlineId.value,
      ...inlineEditData.value
    } as PricingItem)
    
    notificationsStore.success('Item updated successfully')
    cancelInlineEdit()
    
    // Reload pricing data
    await estimatorStore.loadPricingData()
  } catch (error) {
    const message = error instanceof Error ? error.message : 'Failed to update item'
    notificationsStore.error(message)
  } finally {
    loading.value = false
  }
}

const cancelInlineEdit = () => {
  editingInlineId.value = null
  inlineEditData.value = {}
}

// Watch for search query changes to reset pagination
watch(searchQuery, () => {
  currentPage.value = 1
})

watch(selectedCategory, () => {
  currentPage.value = 1
})

// Initialize with performance monitoring
onMounted(async () => {
  performanceMonitor.start('admin_init')
  MemoryMonitor.logMemoryUsage('admin_init_start')
  
  try {
    // Check admin permissions first
    checkingPermissions.value = true
    
    performanceMonitor.start('admin_permission_check')
    isAdmin.value = await estimatorStore.checkAdminStatus()
    performanceMonitor.end('admin_permission_check')
    
    if (!isAdmin.value) {
      notificationsStore.error('Access denied. Admin privileges required.')
      return
    }
    
    // Load pricing data if user is admin
    performanceMonitor.start('admin_data_load')
    await estimatorStore.loadPricingData()
    performanceMonitor.end('admin_data_load')
    
    MemoryMonitor.logMemoryUsage('admin_init_complete')
  } catch (error) {
    notificationsStore.error('Failed to load admin interface')
    console.error('Admin initialization error:', error)
  } finally {
    checkingPermissions.value = false
    performanceMonitor.end('admin_init')
  }
})
</script>

<style scoped>
/* Admin View Layout */
.admin-view {
  display: flex;
  flex-direction: column;
  gap: 24px;
  max-width: 1400px;
  margin: 0 auto;
  padding: 24px;
}

/* Permission Check */
.permission-check {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 20px;
  text-align: center;
}

.permission-check p {
  margin: 16px 0 0 0;
  color: #6b7280;
  font-size: 16px;
}

/* Access Denied */
.access-denied {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 20px;
  text-align: center;
  background: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: 12px;
  margin: 40px auto;
  max-width: 500px;
}

.denied-icon {
  font-size: 64px;
  margin-bottom: 20px;
  opacity: 0.8;
}

.access-denied h3 {
  margin: 0 0 12px 0;
  font-size: 24px;
  color: #dc2626;
  font-weight: 600;
}

.access-denied p {
  margin: 0 0 8px 0;
  color: #7f1d1d;
  font-size: 16px;
  line-height: 1.5;
}

.access-denied p:last-child {
  margin-bottom: 0;
  font-size: 14px;
  color: #991b1b;
}

.admin-header {
  text-align: center;
  margin-bottom: 16px;
}

.admin-title {
  font-size: 28px;
  font-weight: 700;
  margin: 0 0 8px 0;
  color: inherit;
}

.admin-subtitle {
  font-size: 16px;
  color: #6b7280;
  margin: 0;
}

/* Admin Actions */
.admin-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  justify-content: center;
  margin-bottom: 24px;
}

.btn {
  padding: 12px 20px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  border: none;
  cursor: pointer;
  transition: all 0.2s;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  text-decoration: none;
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-icon {
  font-size: 16px;
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

.btn-small {
  padding: 6px 10px;
  font-size: 12px;
  min-width: auto;
}

.btn-danger {
  color: #dc2626 !important;
}

.btn-danger:hover:not(:disabled) {
  background: #fee2e2 !important;
}

/* Search Controls */
.search-controls {
  display: flex;
  flex-direction: column;
  gap: 16px;
  margin-bottom: 24px;
}

.search-input-container {
  position: relative;
  max-width: 500px;
  margin: 0 auto;
}

.search-input {
  width: 100%;
  padding-right: 40px;
}

.search-icon {
  position: absolute;
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: #6b7280;
  pointer-events: none;
}

.filter-controls {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  justify-content: center;
  align-items: center;
}

.category-filter,
.sort-select {
  min-width: 150px;
}

.sort-direction-btn {
  min-width: 40px;
  padding: 8px;
  font-size: 16px;
  font-weight: bold;
}

/* Form Fields */
.form-field {
  border: 1px solid;
  border-radius: 6px;
  padding: 10px 12px;
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

.field-error {
  border-color: #dc2626 !important;
}

.error-text {
  color: #dc2626;
  font-size: 12px;
  margin-top: 4px;
  display: block;
}

/* Modal Overlay */
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
  padding: 20px;
}

/* Import Dialog */
.import-dialog,
.edit-dialog {
  background: white;
  border-radius: 12px;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
  max-width: 600px;
  width: 100%;
  max-height: 90vh;
  overflow-y: auto;
}

.dialog-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 24px;
  border-bottom: 1px solid #e5e7eb;
}

.dialog-header h3 {
  margin: 0;
  font-size: 18px;
  font-weight: 600;
}

.close-btn {
  background: none;
  border: none;
  font-size: 24px;
  cursor: pointer;
  color: #6b7280;
  padding: 0;
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 4px;
}

.close-btn:hover {
  background: #f3f4f6;
  color: #374151;
}

.dialog-content {
  padding: 24px;
}

.dialog-actions {
  display: flex;
  gap: 12px;
  justify-content: flex-end;
  padding: 20px 24px;
  border-top: 1px solid #e5e7eb;
}

/* Import Options */
.import-options {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.import-method h4 {
  margin: 0 0 8px 0;
  font-size: 16px;
  font-weight: 600;
}

.import-method p {
  margin: 0 0 12px 0;
  color: #6b7280;
  font-size: 14px;
}

.import-textarea {
  width: 100%;
  resize: vertical;
  font-family: 'SF Mono', 'Monaco', 'Inconsolata', 'Roboto Mono', monospace;
  font-size: 13px;
}

.file-info {
  margin-top: 8px;
  padding: 8px 12px;
  background: #f3f4f6;
  border-radius: 6px;
  font-size: 14px;
}

.file-name {
  font-weight: 500;
}

.file-size {
  color: #6b7280;
  margin-left: 8px;
}

/* Import Progress */
.import-progress {
  margin-top: 16px;
}

.progress-bar {
  width: 100%;
  height: 8px;
  background: #e5e7eb;
  border-radius: 4px;
  overflow: hidden;
}

.progress-fill {
  height: 100%;
  background: #3b82f6;
  transition: width 0.3s ease;
}

.progress-text {
  margin: 8px 0 0 0;
  font-size: 14px;
  color: #6b7280;
  text-align: center;
}

/* Import Errors */
.import-errors {
  margin-top: 16px;
  padding: 12px;
  background: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: 6px;
}

.import-errors h4 {
  margin: 0 0 8px 0;
  color: #dc2626;
  font-size: 14px;
}

.import-errors ul {
  margin: 0;
  padding-left: 16px;
}

.error-item {
  color: #dc2626;
  font-size: 13px;
  margin-bottom: 4px;
}

/* Edit Form */
.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

.form-group {
  display: flex;
  flex-direction: column;
}

.form-group.full-width {
  grid-column: 1 / -1;
}

.form-label {
  font-weight: 500;
  margin-bottom: 4px;
  font-size: 14px;
}

/* Pricing Grid */
.pricing-grid-container {
  background: white;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  overflow: hidden;
}

.loading-state,
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 20px;
  text-align: center;
}

.loading-spinner {
  width: 40px;
  height: 40px;
  border: 4px solid #e5e7eb;
  border-top: 4px solid #3b82f6;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin-bottom: 16px;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

.empty-icon {
  font-size: 48px;
  margin-bottom: 16px;
  opacity: 0.5;
}

.empty-state h3 {
  margin: 0 0 8px 0;
  font-size: 18px;
  color: #374151;
}

.empty-state p {
  margin: 0 0 20px 0;
  color: #6b7280;
}

/* Grid Header */
.grid-header {
  padding: 16px 24px;
  border-bottom: 1px solid #e5e7eb;
  background: #f9fafb;
}

.grid-stats {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  color: #6b7280;
}

.search-results {
  font-weight: 500;
  color: #3b82f6;
}

/* Data Table */
.table-container {
  overflow-x: auto;
}

.pricing-table {
  width: 100%;
  border-collapse: collapse;
}

.table-header {
  text-align: left;
  padding: 12px 16px;
  font-weight: 600;
  font-size: 13px;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #374151;
  background: #f9fafb;
  border-bottom: 1px solid #e5e7eb;
  position: sticky;
  top: 0;
  z-index: 10;
}

.table-header.sortable {
  cursor: pointer;
  user-select: none;
  position: relative;
}

.table-header.sortable:hover {
  background: #f3f4f6;
}

.sort-indicator {
  margin-left: 4px;
  font-size: 12px;
}

.table-row {
  border-bottom: 1px solid #f3f4f6;
  transition: background-color 0.2s;
}

.table-row:hover {
  background: #f9fafb;
}

.table-row.row-editing {
  background: #fef3c7;
}

.table-cell {
  padding: 12px 16px;
  vertical-align: middle;
  font-size: 14px;
}

.category-badge {
  display: inline-block;
  padding: 4px 8px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 500;
  text-transform: capitalize;
}

.category-doors { background: #dbeafe; color: #1e40af; }
.category-doorOptions { background: #e0e7ff; color: #3730a3; }
.category-inserts { background: #f3e8ff; color: #7c3aed; }
.category-frames { background: #ecfdf5; color: #059669; }
.category-frameOptions { background: #f0fdf4; color: #16a34a; }
.category-hinges { background: #fef3c7; color: #d97706; }
.category-weatherstrip { background: #fecaca; color: #dc2626; }
.category-closers { background: #fed7d7; color: #c53030; }
.category-locksets { background: #e2e8f0; color: #4a5568; }
.category-exitDevices { background: #fbb6ce; color: #be185d; }
.category-hardware { background: #d1fae5; color: #065f46; }

.subcategory-text {
  font-size: 12px;
  color: #6b7280;
  font-style: italic;
}

.text-muted {
  color: #9ca3af;
}

.item-name {
  font-weight: 500;
  cursor: pointer;
}

.item-name:hover {
  color: #3b82f6;
}

.price-display {
  font-family: 'SF Mono', 'Monaco', 'Inconsolata', 'Roboto Mono', monospace;
  font-weight: 600;
  color: #059669;
  cursor: pointer;
}

.price-display:hover {
  color: #047857;
}

.description-text {
  max-width: 200px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  cursor: pointer;
}

.description-text:hover {
  color: #3b82f6;
}

.actions-cell {
  width: 120px;
}

.row-actions,
.inline-actions {
  display: flex;
  gap: 4px;
  justify-content: center;
}

/* Inline Editing */
.inline-edit {
  display: flex;
  align-items: center;
}

.inline-input {
  width: 100%;
  min-width: 100px;
  padding: 4px 8px;
  border: 1px solid #3b82f6;
  border-radius: 4px;
  font-size: 13px;
}

.inline-input.price-input {
  width: 80px;
  text-align: right;
  font-family: 'SF Mono', 'Monaco', 'Inconsolata', 'Roboto Mono', monospace;
}

/* Pagination */
.pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 20px;
  border-top: 1px solid #e5e7eb;
}

.pagination-btn {
  min-width: 40px;
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
}

.page-numbers {
  display: flex;
  gap: 4px;
}

.page-btn.page-active {
  background: #3b82f6 !important;
  color: white !important;
  border-color: #3b82f6 !important;
}

/* Dark Mode Overrides */
@media (prefers-color-scheme: dark) {
  .import-dialog,
  .edit-dialog {
    background: #1f2937;
    color: #f9fafb;
  }
  
  .dialog-header {
    border-bottom-color: #374151;
  }
  
  .dialog-actions {
    border-top-color: #374151;
  }
  
  .close-btn:hover {
    background: #374151;
    color: #d1d5db;
  }
  
  .pricing-grid-container {
    background: #1f2937;
  }
  
  .grid-header {
    background: #111827;
    border-bottom-color: #374151;
  }
  
  .table-header {
    background: #111827;
    color: #d1d5db;
    border-bottom-color: #374151;
  }
  
  .table-header.sortable:hover {
    background: #1f2937;
  }
  
  .table-row {
    border-bottom-color: #374151;
  }
  
  .table-row:hover {
    background: #111827;
  }
  
  .pagination {
    border-top-color: #374151;
  }
  
  .file-info {
    background: #374151;
  }
  
  .import-errors {
    background: #1f2937;
    border-color: #374151;
  }
}

/* Responsive Design */
@media (max-width: 1024px) {
  .admin-view {
    padding: 16px;
  }
  
  .search-controls {
    align-items: stretch;
  }
  
  .filter-controls {
    flex-direction: column;
  }
  
  .category-filter,
  .sort-select {
    min-width: auto;
  }
  
  .form-grid {
    grid-template-columns: 1fr;
  }
  
  .table-container {
    font-size: 13px;
  }
  
  .table-cell {
    padding: 8px 12px;
  }
  
  .description-text {
    max-width: 150px;
  }
}

@media (max-width: 768px) {
  .admin-actions {
    flex-direction: column;
  }
  
  .btn {
    width: 100%;
    justify-content: center;
  }
  
  .search-input-container {
    max-width: none;
  }
  
  .import-dialog,
  .edit-dialog {
    margin: 10px;
    max-width: none;
    width: calc(100% - 20px);
  }
  
  .dialog-content {
    padding: 16px;
  }
  
  .import-options {
    gap: 16px;
  }
  
  .table-container {
    font-size: 12px;
  }
  
  .table-cell {
    padding: 6px 8px;
  }
  
  .category-badge {
    font-size: 10px;
    padding: 2px 6px;
  }
  
  .description-text {
    max-width: 100px;
  }
  
  .pagination {
    flex-wrap: wrap;
    gap: 4px;
  }
  
  .pagination-btn {
    min-width: 36px;
    height: 36px;
    font-size: 12px;
  }
}

@media (max-width: 480px) {
  .admin-title {
    font-size: 24px;
  }
  
  .admin-subtitle {
    font-size: 14px;
  }
  
  .table-header {
    font-size: 11px;
    padding: 8px 6px;
  }
  
  .table-cell {
    padding: 4px 6px;
  }
  
  .btn-small {
    padding: 4px 6px;
    font-size: 10px;
  }
  
  .actions-cell {
    width: 80px;
  }
  
  .row-actions,
  .inline-actions {
    flex-direction: column;
    gap: 2px;
  }
}
</style>