import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import AdminView from '../../../src/views/AdminView.vue'
import { useEstimatorStore } from '../../../src/stores/estimator'
import { useNotificationStore } from '../../../src/stores/notifications'

// Mock the API service
jest.mock('../../../src/services/EstimatorApi', () => ({
  EstimatorApi: {
    getAllPricingData: jest.fn(),
    updatePricingItem: jest.fn(),
    searchPricing: jest.fn(),
    importPricingData: jest.fn(),
    exportPricingData: jest.fn(),
    getMarkupDefaults: jest.fn(),
    updateMarkupDefaults: jest.fn()
  }
}))

describe('AdminView Component', () => {
  let wrapper: any
  let estimatorStore: any
  let notificationStore: any

  beforeEach(() => {
    setActivePinia(createPinia())
    estimatorStore = useEstimatorStore()
    notificationStore = useNotificationStore()
    
    wrapper = mount(AdminView, {
      global: {
        plugins: [createPinia()]
      }
    })
  })

  afterEach(() => {
    wrapper.unmount()
    jest.clearAllMocks()
  })

  describe('Component Rendering', () => {
    it('renders admin interface', () => {
      expect(wrapper.find('[data-testid="admin-view"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="pricing-data-grid"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="markup-settings"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="import-export-controls"]').exists()).toBe(true)
    })

    it('shows loading state during data fetch', async () => {
      wrapper.vm.isLoading = true
      await wrapper.vm.$nextTick()
      
      expect(wrapper.find('[data-testid="loading-spinner"]').exists()).toBe(true)
    })

    it('displays pricing data in grid format', async () => {
      const mockPricingData = [
        { id: 1, category: 'doors', item: 'Door A', price: 100, stock_status: 'stock' },
        { id: 2, category: 'frames', item: 'Frame B', price: 50, stock_status: 'stock' }
      ]
      
      wrapper.vm.pricingData = mockPricingData
      await wrapper.vm.$nextTick()
      
      const rows = wrapper.findAll('[data-testid="pricing-row"]')
      expect(rows).toHaveLength(2)
      expect(rows[0].text()).toContain('Door A')
      expect(rows[1].text()).toContain('Frame B')
    })
  })

  describe('Pricing Data Management', () => {
    it('loads pricing data on mount', async () => {
      const { EstimatorApi } = require('../../../src/services/EstimatorApi')
      const mockData = [
        { id: 1, category: 'doors', item: 'Test Door', price: 100 }
      ]
      EstimatorApi.getAllPricingData.mockResolvedValue(mockData)
      
      await wrapper.vm.loadPricingData()
      await flushPromises()
      
      expect(EstimatorApi.getAllPricingData).toHaveBeenCalled()
      expect(wrapper.vm.pricingData).toEqual(mockData)
    })

    it('enables inline editing for pricing items', async () => {
      wrapper.vm.pricingData = [
        { id: 1, category: 'doors', item: 'Door A', price: 100, stock_status: 'stock' }
      ]
      await wrapper.vm.$nextTick()
      
      const editButton = wrapper.find('[data-testid="edit-item-1"]')
      await editButton.trigger('click')
      
      expect(wrapper.find('[data-testid="edit-form-1"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="item-input-1"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="price-input-1"]').exists()).toBe(true)
    })

    it('saves edited pricing item', async () => {
      const { EstimatorApi } = require('../../../src/services/EstimatorApi')
      EstimatorApi.updatePricingItem.mockResolvedValue({ success: true })
      
      wrapper.vm.pricingData = [
        { id: 1, category: 'doors', item: 'Door A', price: 100, stock_status: 'stock' }
      ]
      wrapper.vm.editingItem = 1
      await wrapper.vm.$nextTick()
      
      const itemInput = wrapper.find('[data-testid="item-input-1"]')
      const priceInput = wrapper.find('[data-testid="price-input-1"]')
      
      await itemInput.setValue('Updated Door')
      await priceInput.setValue('150')
      
      const saveButton = wrapper.find('[data-testid="save-item-1"]')
      await saveButton.trigger('click')
      await flushPromises()
      
      expect(EstimatorApi.updatePricingItem).toHaveBeenCalledWith({
        id: 1,
        category: 'doors',
        item: 'Updated Door',
        price: 150,
        stock_status: 'stock'
      })
    })

    it('cancels editing without saving', async () => {
      wrapper.vm.pricingData = [
        { id: 1, category: 'doors', item: 'Door A', price: 100, stock_status: 'stock' }
      ]
      wrapper.vm.editingItem = 1
      await wrapper.vm.$nextTick()
      
      const cancelButton = wrapper.find('[data-testid="cancel-edit-1"]')
      await cancelButton.trigger('click')
      
      expect(wrapper.vm.editingItem).toBeNull()
      expect(wrapper.find('[data-testid="edit-form-1"]').exists()).toBe(false)
    })

    it('adds new pricing item', async () => {
      const { EstimatorApi } = require('../../../src/services/EstimatorApi')
      EstimatorApi.updatePricingItem.mockResolvedValue({ success: true })
      
      const addButton = wrapper.find('[data-testid="add-new-item"]')
      await addButton.trigger('click')
      
      expect(wrapper.find('[data-testid="new-item-form"]').exists()).toBe(true)
      
      const categorySelect = wrapper.find('[data-testid="new-category"]')
      const itemInput = wrapper.find('[data-testid="new-item"]')
      const priceInput = wrapper.find('[data-testid="new-price"]')
      
      await categorySelect.setValue('doors')
      await itemInput.setValue('New Door')
      await priceInput.setValue('200')
      
      const saveButton = wrapper.find('[data-testid="save-new-item"]')
      await saveButton.trigger('click')
      await flushPromises()
      
      expect(EstimatorApi.updatePricingItem).toHaveBeenCalledWith({
        category: 'doors',
        item: 'New Door',
        price: 200,
        stock_status: 'stock'
      })
    })
  })

  describe('Search and Filtering', () => {
    it('filters pricing data by search term', async () => {
      wrapper.vm.pricingData = [
        { id: 1, category: 'doors', item: 'Door A', price: 100 },
        { id: 2, category: 'doors', item: 'Door B', price: 150 },
        { id: 3, category: 'frames', item: 'Frame C', price: 50 }
      ]
      
      const searchInput = wrapper.find('[data-testid="search-input"]')
      await searchInput.setValue('Door')
      await searchInput.trigger('input')
      await wrapper.vm.$nextTick()
      
      const visibleRows = wrapper.findAll('[data-testid="pricing-row"]:not(.hidden)')
      expect(visibleRows).toHaveLength(2)
    })

    it('filters pricing data by category', async () => {
      wrapper.vm.pricingData = [
        { id: 1, category: 'doors', item: 'Door A', price: 100 },
        { id: 2, category: 'frames', item: 'Frame B', price: 50 },
        { id: 3, category: 'hardware', item: 'Hardware C', price: 25 }
      ]
      
      const categoryFilter = wrapper.find('[data-testid="category-filter"]')
      await categoryFilter.setValue('doors')
      await categoryFilter.trigger('change')
      await wrapper.vm.$nextTick()
      
      const visibleRows = wrapper.findAll('[data-testid="pricing-row"]:not(.hidden)')
      expect(visibleRows).toHaveLength(1)
      expect(visibleRows[0].text()).toContain('Door A')
    })

    it('combines search and category filters', async () => {
      wrapper.vm.pricingData = [
        { id: 1, category: 'doors', item: 'Wood Door', price: 100 },
        { id: 2, category: 'doors', item: 'Steel Door', price: 150 },
        { id: 3, category: 'frames', item: 'Wood Frame', price: 50 }
      ]
      
      const searchInput = wrapper.find('[data-testid="search-input"]')
      const categoryFilter = wrapper.find('[data-testid="category-filter"]')
      
      await searchInput.setValue('Wood')
      await categoryFilter.setValue('doors')
      await wrapper.vm.$nextTick()
      
      const visibleRows = wrapper.findAll('[data-testid="pricing-row"]:not(.hidden)')
      expect(visibleRows).toHaveLength(1)
      expect(visibleRows[0].text()).toContain('Wood Door')
    })

    it('shows no results message when no items match', async () => {
      wrapper.vm.pricingData = [
        { id: 1, category: 'doors', item: 'Door A', price: 100 }
      ]
      
      const searchInput = wrapper.find('[data-testid="search-input"]')
      await searchInput.setValue('Nonexistent')
      await wrapper.vm.$nextTick()
      
      expect(wrapper.find('[data-testid="no-results"]').exists()).toBe(true)
    })
  })

  describe('Markup Settings', () => {
    it('displays current markup settings', async () => {
      const { EstimatorApi } = require('../../../src/services/EstimatorApi')
      EstimatorApi.getMarkupDefaults.mockResolvedValue({
        doors: 15,
        frames: 12,
        hardware: 18
      })
      
      await wrapper.vm.loadMarkupDefaults()
      await flushPromises()
      
      expect(wrapper.find('[data-testid="doors-markup"]').element.value).toBe('15')
      expect(wrapper.find('[data-testid="frames-markup"]').element.value).toBe('12')
      expect(wrapper.find('[data-testid="hardware-markup"]').element.value).toBe('18')
    })

    it('updates markup settings', async () => {
      const { EstimatorApi } = require('../../../src/services/EstimatorApi')
      EstimatorApi.updateMarkupDefaults.mockResolvedValue({ success: true })
      
      const doorsMarkupInput = wrapper.find('[data-testid="doors-markup"]')
      await doorsMarkupInput.setValue('20')
      
      const saveButton = wrapper.find('[data-testid="save-markups"]')
      await saveButton.trigger('click')
      await flushPromises()
      
      expect(EstimatorApi.updateMarkupDefaults).toHaveBeenCalledWith({
        doors: 20,
        frames: expect.any(Number),
        hardware: expect.any(Number)
      })
    })

    it('validates markup input values', async () => {
      const markupInput = wrapper.find('[data-testid="doors-markup"]')
      
      // Test negative value
      await markupInput.setValue('-5')
      await markupInput.trigger('blur')
      
      expect(wrapper.find('[data-testid="markup-error"]').exists()).toBe(true)
      
      // Test non-numeric value
      await markupInput.setValue('abc')
      await markupInput.trigger('blur')
      
      expect(wrapper.find('[data-testid="markup-error"]').exists()).toBe(true)
    })
  })

  describe('Import/Export Functionality', () => {
    it('shows import dialog when import button clicked', async () => {
      const importButton = wrapper.find('[data-testid="import-button"]')
      await importButton.trigger('click')
      
      expect(wrapper.find('[data-testid="import-dialog"]').exists()).toBe(true)
    })

    it('handles file upload for import', async () => {
      const { EstimatorApi } = require('../../../src/services/EstimatorApi')
      EstimatorApi.importPricingData.mockResolvedValue({
        success: true,
        imported: 10,
        errors: []
      })
      
      const file = new File(['{"pricingData": [], "markups": {}}'], 'test.json', {
        type: 'application/json'
      })
      
      const fileInput = wrapper.find('[data-testid="file-input"]')
      Object.defineProperty(fileInput.element, 'files', {
        value: [file],
        writable: false
      })
      
      await fileInput.trigger('change')
      
      const uploadButton = wrapper.find('[data-testid="upload-button"]')
      await uploadButton.trigger('click')
      await flushPromises()
      
      expect(EstimatorApi.importPricingData).toHaveBeenCalledWith(file)
    })

    it('validates file type before upload', async () => {
      const file = new File(['invalid content'], 'test.txt', {
        type: 'text/plain'
      })
      
      const fileInput = wrapper.find('[data-testid="file-input"]')
      Object.defineProperty(fileInput.element, 'files', {
        value: [file],
        writable: false
      })
      
      await fileInput.trigger('change')
      
      expect(wrapper.find('[data-testid="file-error"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="upload-button"]').attributes('disabled')).toBeDefined()
    })

    it('exports pricing data', async () => {
      const { EstimatorApi } = require('../../../src/services/EstimatorApi')
      const mockExportData = {
        pricingData: [{ id: 1, item: 'Door A', price: 100 }],
        markups: { doors: 15 }
      }
      EstimatorApi.exportPricingData.mockResolvedValue(mockExportData)
      
      // Mock URL.createObjectURL
      const mockCreateObjectURL = jest.fn().mockReturnValue('blob:mock-url')
      Object.defineProperty(window.URL, 'createObjectURL', {
        value: mockCreateObjectURL
      })
      
      const exportButton = wrapper.find('[data-testid="export-button"]')
      await exportButton.trigger('click')
      await flushPromises()
      
      expect(EstimatorApi.exportPricingData).toHaveBeenCalled()
      expect(mockCreateObjectURL).toHaveBeenCalled()
    })

    it('shows import progress during upload', async () => {
      const { EstimatorApi } = require('../../../src/services/EstimatorApi')
      
      // Mock a slow import
      EstimatorApi.importPricingData.mockImplementation(() => 
        new Promise(resolve => setTimeout(() => resolve({ success: true }), 1000))
      )
      
      const file = new File(['{}'], 'test.json', { type: 'application/json' })
      const fileInput = wrapper.find('[data-testid="file-input"]')
      Object.defineProperty(fileInput.element, 'files', {
        value: [file],
        writable: false
      })
      
      await fileInput.trigger('change')
      
      const uploadButton = wrapper.find('[data-testid="upload-button"]')
      await uploadButton.trigger('click')
      
      // Should show progress indicator
      expect(wrapper.find('[data-testid="import-progress"]').exists()).toBe(true)
    })
  })

  describe('Sorting and Pagination', () => {
    it('sorts pricing data by column', async () => {
      wrapper.vm.pricingData = [
        { id: 1, category: 'doors', item: 'B Door', price: 150 },
        { id: 2, category: 'doors', item: 'A Door', price: 100 }
      ]
      
      const itemHeader = wrapper.find('[data-testid="sort-item"]')
      await itemHeader.trigger('click')
      await wrapper.vm.$nextTick()
      
      const rows = wrapper.findAll('[data-testid="pricing-row"]')
      expect(rows[0].text()).toContain('A Door')
      expect(rows[1].text()).toContain('B Door')
    })

    it('reverses sort order on second click', async () => {
      wrapper.vm.pricingData = [
        { id: 1, category: 'doors', item: 'A Door', price: 100 },
        { id: 2, category: 'doors', item: 'B Door', price: 150 }
      ]
      
      const priceHeader = wrapper.find('[data-testid="sort-price"]')
      
      // First click - ascending
      await priceHeader.trigger('click')
      await wrapper.vm.$nextTick()
      
      let rows = wrapper.findAll('[data-testid="pricing-row"]')
      expect(rows[0].text()).toContain('100')
      
      // Second click - descending
      await priceHeader.trigger('click')
      await wrapper.vm.$nextTick()
      
      rows = wrapper.findAll('[data-testid="pricing-row"]')
      expect(rows[0].text()).toContain('150')
    })

    it('paginates large datasets', async () => {
      const manyItems = Array.from({ length: 100 }, (_, i) => ({
        id: i,
        category: 'doors',
        item: `Door ${i}`,
        price: 100 + i
      }))
      
      wrapper.vm.pricingData = manyItems
      wrapper.vm.itemsPerPage = 25
      await wrapper.vm.$nextTick()
      
      const rows = wrapper.findAll('[data-testid="pricing-row"]')
      expect(rows).toHaveLength(25)
      
      const pagination = wrapper.find('[data-testid="pagination"]')
      expect(pagination.exists()).toBe(true)
    })

    it('navigates between pages', async () => {
      const manyItems = Array.from({ length: 50 }, (_, i) => ({
        id: i,
        category: 'doors',
        item: `Door ${i}`,
        price: 100 + i
      }))
      
      wrapper.vm.pricingData = manyItems
      wrapper.vm.itemsPerPage = 25
      wrapper.vm.currentPage = 1
      await wrapper.vm.$nextTick()
      
      const nextButton = wrapper.find('[data-testid="next-page"]')
      await nextButton.trigger('click')
      
      expect(wrapper.vm.currentPage).toBe(2)
      
      const rows = wrapper.findAll('[data-testid="pricing-row"]')
      expect(rows[0].text()).toContain('Door 25')
    })
  })

  describe('Input Validation', () => {
    it('validates pricing item fields', async () => {
      const addButton = wrapper.find('[data-testid="add-new-item"]')
      await addButton.trigger('click')
      
      const saveButton = wrapper.find('[data-testid="save-new-item"]')
      await saveButton.trigger('click')
      
      // Should show validation errors
      expect(wrapper.find('[data-testid="category-error"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="item-error"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="price-error"]').exists()).toBe(true)
    })

    it('sanitizes input data', async () => {
      const addButton = wrapper.find('[data-testid="add-new-item"]')
      await addButton.trigger('click')
      
      const itemInput = wrapper.find('[data-testid="new-item"]')
      await itemInput.setValue('<script>alert("xss")</script>Door')
      await itemInput.trigger('blur')
      
      expect(itemInput.element.value).not.toContain('<script>')
      expect(itemInput.element.value).toContain('Door')
    })

    it('validates price format', async () => {
      const addButton = wrapper.find('[data-testid="add-new-item"]')
      await addButton.trigger('click')
      
      const priceInput = wrapper.find('[data-testid="new-price"]')
      
      // Test invalid price formats
      await priceInput.setValue('abc')
      await priceInput.trigger('blur')
      expect(wrapper.find('[data-testid="price-format-error"]').exists()).toBe(true)
      
      await priceInput.setValue('-10')
      await priceInput.trigger('blur')
      expect(wrapper.find('[data-testid="price-negative-error"]').exists()).toBe(true)
    })
  })

  describe('Error Handling', () => {
    it('handles API errors gracefully', async () => {
      const { EstimatorApi } = require('../../../src/services/EstimatorApi')
      EstimatorApi.getAllPricingData.mockRejectedValue(new Error('API Error'))
      
      const consoleSpy = jest.spyOn(console, 'error').mockImplementation()
      
      await wrapper.vm.loadPricingData()
      await flushPromises()
      
      expect(consoleSpy).toHaveBeenCalledWith('Error loading pricing data:', expect.any(Error))
      expect(wrapper.find('[data-testid="error-message"]').exists()).toBe(true)
      
      consoleSpy.mockRestore()
    })

    it('handles network failures during import', async () => {
      const { EstimatorApi } = require('../../../src/services/EstimatorApi')
      EstimatorApi.importPricingData.mockRejectedValue(new Error('Network error'))
      
      const file = new File(['{}'], 'test.json', { type: 'application/json' })
      const fileInput = wrapper.find('[data-testid="file-input"]')
      Object.defineProperty(fileInput.element, 'files', {
        value: [file],
        writable: false
      })
      
      await fileInput.trigger('change')
      
      const uploadButton = wrapper.find('[data-testid="upload-button"]')
      await uploadButton.trigger('click')
      await flushPromises()
      
      expect(wrapper.find('[data-testid="import-error"]').exists()).toBe(true)
    })

    it('recovers from corrupted data', async () => {
      // Simulate corrupted pricing data
      wrapper.vm.pricingData = [
        { id: null, category: undefined, item: '', price: NaN },
        { id: 1, category: 'doors', item: 'Valid Door', price: 100 }
      ]
      
      await wrapper.vm.$nextTick()
      
      // Should filter out invalid items
      const rows = wrapper.findAll('[data-testid="pricing-row"]')
      expect(rows).toHaveLength(1)
      expect(rows[0].text()).toContain('Valid Door')
    })
  })

  describe('Accessibility', () => {
    it('has proper table headers and structure', () => {
      const table = wrapper.find('[data-testid="pricing-data-grid"]')
      expect(table.find('thead').exists()).toBe(true)
      expect(table.find('tbody').exists()).toBe(true)
      
      const headers = table.findAll('th')
      headers.forEach(header => {
        expect(header.attributes('scope')).toBe('col')
      })
    })

    it('supports keyboard navigation in grid', async () => {
      wrapper.vm.pricingData = [
        { id: 1, category: 'doors', item: 'Door A', price: 100 }
      ]
      await wrapper.vm.$nextTick()
      
      const firstCell = wrapper.find('[data-testid="cell-0-0"]')
      await firstCell.trigger('focus')
      
      // Arrow keys should navigate between cells
      await firstCell.trigger('keydown.right')
      expect(document.activeElement).not.toBe(firstCell.element)
    })

    it('announces changes to screen readers', async () => {
      const statusRegion = wrapper.find('[data-testid="status-region"]')
      expect(statusRegion.attributes('aria-live')).toBe('polite')
      expect(statusRegion.attributes('aria-atomic')).toBe('true')
    })
  })

  describe('Performance', () => {
    it('virtualizes large datasets', async () => {
      const manyItems = Array.from({ length: 10000 }, (_, i) => ({
        id: i,
        category: 'doors',
        item: `Door ${i}`,
        price: 100 + i
      }))
      
      wrapper.vm.pricingData = manyItems
      await wrapper.vm.$nextTick()
      
      // Should only render visible items
      const rows = wrapper.findAll('[data-testid="pricing-row"]')
      expect(rows.length).toBeLessThan(100) // Much less than 10000
    })

    it('debounces search input', async () => {
      jest.useFakeTimers()
      
      const searchInput = wrapper.find('[data-testid="search-input"]')
      
      // Rapid typing
      await searchInput.setValue('D')
      await searchInput.setValue('Do')
      await searchInput.setValue('Door')
      
      // Should not filter immediately
      expect(wrapper.vm.filteredData).toEqual(wrapper.vm.pricingData)
      
      // Fast forward debounce timer
      jest.advanceTimersByTime(300)
      await wrapper.vm.$nextTick()
      
      // Now should be filtered
      expect(wrapper.vm.searchTerm).toBe('Door')
      
      jest.useRealTimers()
    })
  })
})