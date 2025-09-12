import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import QuoteManager from '../../../src/components/QuoteManager.vue'
import { useEstimatorStore } from '../../../src/stores/estimator'
import { useNotificationStore } from '../../../src/stores/notifications'

// Mock the API service
jest.mock('../../../src/services/EstimatorApi', () => ({
  EstimatorApi: {
    saveQuote: jest.fn(),
    getUserQuotes: jest.fn(),
    getQuote: jest.fn(),
    deleteQuote: jest.fn(),
    duplicateQuote: jest.fn(),
    generateQuotePDF: jest.fn()
  }
}))

describe('QuoteManager Component', () => {
  let wrapper: any
  let estimatorStore: any
  let notificationStore: any

  beforeEach(() => {
    setActivePinia(createPinia())
    estimatorStore = useEstimatorStore()
    notificationStore = useNotificationStore()
    
    wrapper = mount(QuoteManager, {
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
    it('renders quote manager interface', () => {
      expect(wrapper.find('[data-testid="quote-manager"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="save-quote-btn"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="load-quote-btn"]').exists()).toBe(true)
    })

    it('shows quote list when expanded', async () => {
      await wrapper.find('[data-testid="load-quote-btn"]').trigger('click')
      expect(wrapper.find('[data-testid="quote-list"]').exists()).toBe(true)
    })

    it('displays loading state during operations', async () => {
      wrapper.vm.isLoading = true
      await wrapper.vm.$nextTick()
      expect(wrapper.find('[data-testid="loading-indicator"]').exists()).toBe(true)
    })
  })

  describe('Quote Operations', () => {
    it('saves quote with valid data', async () => {
      const { EstimatorApi } = require('../../../src/services/EstimatorApi')
      EstimatorApi.saveQuote.mockResolvedValue({ success: true, quoteId: 123 })

      estimatorStore.quoteData = {
        doors: [{ id: '1', item: 'Test Door', qty: 1, price: 100, total: 100 }]
      }
      estimatorStore.markups = { doors: 15 }

      await wrapper.find('[data-testid="save-quote-btn"]').trigger('click')
      await flushPromises()

      expect(EstimatorApi.saveQuote).toHaveBeenCalledWith(
        estimatorStore.quoteData,
        estimatorStore.markups,
        expect.any(String),
        expect.any(Object)
      )
    })

    it('handles save quote errors gracefully', async () => {
      const { EstimatorApi } = require('../../../src/services/EstimatorApi')
      EstimatorApi.saveQuote.mockRejectedValue(new Error('Save failed'))

      const consoleSpy = jest.spyOn(console, 'error').mockImplementation()

      await wrapper.find('[data-testid="save-quote-btn"]').trigger('click')
      await flushPromises()

      expect(consoleSpy).toHaveBeenCalledWith('Error saving quote:', expect.any(Error))
      consoleSpy.mockRestore()
    })

    it('loads user quotes on component mount', async () => {
      const { EstimatorApi } = require('../../../src/services/EstimatorApi')
      const mockQuotes = [
        { id: 1, quote_name: 'Test Quote 1', total_amount: 100, created_at: '2024-01-01' },
        { id: 2, quote_name: 'Test Quote 2', total_amount: 200, created_at: '2024-01-02' }
      ]
      EstimatorApi.getUserQuotes.mockResolvedValue(mockQuotes)

      await wrapper.vm.loadUserQuotes()
      await flushPromises()

      expect(wrapper.vm.userQuotes).toEqual(mockQuotes)
      expect(EstimatorApi.getUserQuotes).toHaveBeenCalled()
    })

    it('loads specific quote by ID', async () => {
      const { EstimatorApi } = require('../../../src/services/EstimatorApi')
      const mockQuote = {
        id: 1,
        quote_name: 'Test Quote',
        quote_data: { doors: [{ item: 'Door A', qty: 1, price: 100 }] },
        markups: { doors: 15 },
        customer_info: { name: 'John Doe' }
      }
      EstimatorApi.getQuote.mockResolvedValue(mockQuote)

      await wrapper.vm.loadQuote(1)
      await flushPromises()

      expect(estimatorStore.quoteData).toEqual(mockQuote.quote_data)
      expect(estimatorStore.markups).toEqual(mockQuote.markups)
    })

    it('deletes quote with confirmation', async () => {
      const { EstimatorApi } = require('../../../src/services/EstimatorApi')
      EstimatorApi.deleteQuote.mockResolvedValue({ success: true })
      
      // Mock window.confirm
      const confirmSpy = jest.spyOn(window, 'confirm').mockReturnValue(true)

      await wrapper.vm.deleteQuote(1)
      await flushPromises()

      expect(confirmSpy).toHaveBeenCalledWith('Are you sure you want to delete this quote?')
      expect(EstimatorApi.deleteQuote).toHaveBeenCalledWith(1)
      
      confirmSpy.mockRestore()
    })

    it('cancels delete when user declines confirmation', async () => {
      const { EstimatorApi } = require('../../../src/services/EstimatorApi')
      const confirmSpy = jest.spyOn(window, 'confirm').mockReturnValue(false)

      await wrapper.vm.deleteQuote(1)

      expect(EstimatorApi.deleteQuote).not.toHaveBeenCalled()
      confirmSpy.mockRestore()
    })

    it('duplicates quote successfully', async () => {
      const { EstimatorApi } = require('../../../src/services/EstimatorApi')
      EstimatorApi.duplicateQuote.mockResolvedValue({ success: true, newQuoteId: 456 })

      await wrapper.vm.duplicateQuote(123)
      await flushPromises()

      expect(EstimatorApi.duplicateQuote).toHaveBeenCalledWith(123)
    })
  })

  describe('PDF Generation', () => {
    it('generates PDF for quote', async () => {
      const { EstimatorApi } = require('../../../src/services/EstimatorApi')
      EstimatorApi.generateQuotePDF.mockResolvedValue({
        success: true,
        pdfPath: '/tmp/quote.pdf',
        downloadUrl: '/download/quote.pdf'
      })

      await wrapper.vm.generatePDF(1)
      await flushPromises()

      expect(EstimatorApi.generateQuotePDF).toHaveBeenCalledWith(1)
    })

    it('handles PDF generation errors', async () => {
      const { EstimatorApi } = require('../../../src/services/EstimatorApi')
      EstimatorApi.generateQuotePDF.mockRejectedValue(new Error('PDF generation failed'))

      const consoleSpy = jest.spyOn(console, 'error').mockImplementation()

      await wrapper.vm.generatePDF(1)
      await flushPromises()

      expect(consoleSpy).toHaveBeenCalledWith('Error generating PDF:', expect.any(Error))
      consoleSpy.mockRestore()
    })
  })

  describe('Input Validation', () => {
    it('validates quote name input', async () => {
      const input = wrapper.find('[data-testid="quote-name-input"]')
      
      // Test empty name
      await input.setValue('')
      expect(wrapper.vm.isValidQuoteName('')).toBe(false)
      
      // Test valid name
      await input.setValue('Valid Quote Name')
      expect(wrapper.vm.isValidQuoteName('Valid Quote Name')).toBe(true)
      
      // Test name too long
      const longName = 'a'.repeat(256)
      await input.setValue(longName)
      expect(wrapper.vm.isValidQuoteName(longName)).toBe(false)
    })

    it('sanitizes customer info input', () => {
      const maliciousInput = '<script>alert("xss")</script>John Doe'
      const sanitized = wrapper.vm.sanitizeCustomerInfo(maliciousInput)
      expect(sanitized).not.toContain('<script>')
      expect(sanitized).toContain('John Doe')
    })
  })

  describe('Accessibility', () => {
    it('has proper ARIA labels', () => {
      expect(wrapper.find('[data-testid="save-quote-btn"]').attributes('aria-label')).toBeDefined()
      expect(wrapper.find('[data-testid="load-quote-btn"]').attributes('aria-label')).toBeDefined()
    })

    it('supports keyboard navigation', async () => {
      const saveBtn = wrapper.find('[data-testid="save-quote-btn"]')
      const loadBtn = wrapper.find('[data-testid="load-quote-btn"]')
      
      expect(saveBtn.attributes('tabindex')).toBe('0')
      expect(loadBtn.attributes('tabindex')).toBe('0')
      
      // Test Enter key activation
      await saveBtn.trigger('keydown.enter')
      // Should trigger save functionality
    })
  })

  describe('Performance', () => {
    it('debounces search input', async () => {
      jest.useFakeTimers()
      
      const searchInput = wrapper.find('[data-testid="quote-search"]')
      await searchInput.setValue('test')
      
      // Should not search immediately
      expect(wrapper.vm.searchResults).toEqual([])
      
      // Fast forward timers
      jest.advanceTimersByTime(300)
      await flushPromises()
      
      // Now should have searched
      expect(wrapper.vm.searchTerm).toBe('test')
      
      jest.useRealTimers()
    })

    it('limits displayed quotes for performance', async () => {
      const manyQuotes = Array.from({ length: 1000 }, (_, i) => ({
        id: i,
        quote_name: `Quote ${i}`,
        total_amount: 100,
        created_at: '2024-01-01'
      }))
      
      wrapper.vm.userQuotes = manyQuotes
      await wrapper.vm.$nextTick()
      
      const displayedQuotes = wrapper.findAll('[data-testid="quote-item"]')
      expect(displayedQuotes.length).toBeLessThanOrEqual(50) // Pagination limit
    })
  })
})