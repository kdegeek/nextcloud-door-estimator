import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import EstimatorView from '../../../src/views/EstimatorView.vue'
import { useEstimatorStore } from '../../../src/stores/estimator'
import { useNotificationStore } from '../../../src/stores/notifications'

// Mock the API service
jest.mock('../../../src/services/EstimatorApi', () => ({
  EstimatorApi: {
    lookupPrice: jest.fn(),
    getMarkupDefaults: jest.fn(),
    getAllPricingData: jest.fn()
  }
}))

describe('EstimatorView Component', () => {
  let wrapper: any
  let estimatorStore: any
  let notificationStore: any

  beforeEach(() => {
    setActivePinia(createPinia())
    estimatorStore = useEstimatorStore()
    notificationStore = useNotificationStore()
    
    wrapper = mount(EstimatorView, {
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
    it('renders estimator interface', () => {
      expect(wrapper.find('[data-testid="estimator-view"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="quote-sections"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="grand-total"]').exists()).toBe(true)
    })

    it('renders all quote sections', () => {
      const sections = [
        'doors', 'door-options', 'inserts', 'frames', 'frame-options',
        'hinges', 'weatherstrip', 'closers', 'locksets', 'exit-devices', 'hardware'
      ]
      
      sections.forEach(section => {
        expect(wrapper.find(`[data-testid="${section}-section"]`).exists()).toBe(true)
      })
    })

    it('displays section totals', () => {
      const sectionTotals = wrapper.findAll('[data-testid="section-total"]')
      expect(sectionTotals.length).toBeGreaterThan(0)
    })

    it('shows loading state during initialization', async () => {
      wrapper.vm.isLoading = true
      await wrapper.vm.$nextTick()
      
      expect(wrapper.find('[data-testid="loading-spinner"]').exists()).toBe(true)
    })
  })

  describe('Quote Item Management', () => {
    it('adds new quote item to section', async () => {
      const addButton = wrapper.find('[data-testid="add-doors-item"]')
      await addButton.trigger('click')
      
      const doorItems = wrapper.findAll('[data-testid="doors-item"]')
      expect(doorItems.length).toBeGreaterThan(1)
    })

    it('removes quote item from section', async () => {
      // Ensure there's at least one item
      estimatorStore.quoteData.doors = [
        { id: '1', item: 'Test Door', qty: 1, price: 100, total: 100 }
      ]
      await wrapper.vm.$nextTick()
      
      const removeButton = wrapper.find('[data-testid="remove-doors-item-0"]')
      await removeButton.trigger('click')
      
      expect(estimatorStore.quoteData.doors).toHaveLength(0)
    })

    it('updates item name and triggers price lookup', async () => {
      const { EstimatorApi } = require('../../../src/services/EstimatorApi')
      EstimatorApi.lookupPrice.mockResolvedValue({ price: 150 })
      
      const itemInput = wrapper.find('[data-testid="doors-item-0-name"]')
      await itemInput.setValue('New Door Item')
      await itemInput.trigger('blur')
      await flushPromises()
      
      expect(EstimatorApi.lookupPrice).toHaveBeenCalledWith('doors', 'New Door Item', null)
      expect(estimatorStore.quoteData.doors[0].price).toBe(150)
    })

    it('updates quantity and recalculates total', async () => {
      estimatorStore.quoteData.doors[0] = { id: '1', item: 'Door', qty: 1, price: 100, total: 100 }
      
      const qtyInput = wrapper.find('[data-testid="doors-item-0-qty"]')
      await qtyInput.setValue('3')
      await qtyInput.trigger('input')
      
      expect(estimatorStore.quoteData.doors[0].qty).toBe(3)
      expect(estimatorStore.quoteData.doors[0].total).toBe(300)
    })

    it('updates price and recalculates total', async () => {
      estimatorStore.quoteData.doors[0] = { id: '1', item: 'Door', qty: 2, price: 100, total: 200 }
      
      const priceInput = wrapper.find('[data-testid="doors-item-0-price"]')
      await priceInput.setValue('150')
      await priceInput.trigger('input')
      
      expect(estimatorStore.quoteData.doors[0].price).toBe(150)
      expect(estimatorStore.quoteData.doors[0].total).toBe(300)
    })
  })

  describe('Frame Type Handling', () => {
    it('shows frame type selector for frames section', () => {
      const frameTypeSelect = wrapper.find('[data-testid="frames-item-0-type"]')
      expect(frameTypeSelect.exists()).toBe(true)
    })

    it('updates price when frame type changes', async () => {
      const { EstimatorApi } = require('../../../src/services/EstimatorApi')
      EstimatorApi.lookupPrice.mockResolvedValue({ price: 200 })
      
      const frameTypeSelect = wrapper.find('[data-testid="frames-item-0-type"]')
      await frameTypeSelect.setValue('HM EWA')
      await frameTypeSelect.trigger('change')
      await flushPromises()
      
      expect(EstimatorApi.lookupPrice).toHaveBeenCalledWith('frames', expect.any(String), 'HM EWA')
    })

    it('shows frame type options', () => {
      const frameTypeSelect = wrapper.find('[data-testid="frames-item-0-type"]')
      const options = frameTypeSelect.findAll('option')
      
      expect(options.length).toBeGreaterThan(1)
      expect(options.some(option => option.text().includes('HM Drywall'))).toBe(true)
      expect(options.some(option => option.text().includes('HM EWA'))).toBe(true)
      expect(options.some(option => option.text().includes('HM USA'))).toBe(true)
    })
  })

  describe('Markup Management', () => {
    it('displays markup controls', () => {
      const markupControls = wrapper.find('[data-testid="markup-controls"]')
      expect(markupControls.exists()).toBe(true)
    })

    it('updates section markup', async () => {
      const doorsMarkupInput = wrapper.find('[data-testid="doors-markup"]')
      await doorsMarkupInput.setValue('20')
      await doorsMarkupInput.trigger('input')
      
      expect(estimatorStore.markups.doors).toBe(20)
    })

    it('recalculates totals when markup changes', async () => {
      estimatorStore.quoteData.doors[0] = { id: '1', item: 'Door', qty: 1, price: 100, total: 100 }
      estimatorStore.markups.doors = 15
      
      const doorsMarkupInput = wrapper.find('[data-testid="doors-markup"]')
      await doorsMarkupInput.setValue('25')
      await doorsMarkupInput.trigger('input')
      
      // Section total should reflect new markup
      const sectionTotal = wrapper.find('[data-testid="doors-section-total"]')
      expect(sectionTotal.text()).toContain('125') // 100 * 1.25
    })

    it('loads default markups on mount', async () => {
      const { EstimatorApi } = require('../../../src/services/EstimatorApi')
      EstimatorApi.getMarkupDefaults.mockResolvedValue({
        doors: 15,
        frames: 12,
        hardware: 18
      })
      
      await wrapper.vm.loadDefaultMarkups()
      await flushPromises()
      
      expect(estimatorStore.markups.doors).toBe(15)
      expect(estimatorStore.markups.frames).toBe(12)
      expect(estimatorStore.markups.hardware).toBe(18)
    })
  })

  describe('Total Calculations', () => {
    it('calculates section totals with markup', () => {
      estimatorStore.quoteData.doors = [
        { id: '1', item: 'Door 1', qty: 2, price: 100, total: 200 },
        { id: '2', item: 'Door 2', qty: 1, price: 150, total: 150 }
      ]
      estimatorStore.markups.doors = 15
      
      const sectionTotal = wrapper.vm.calculateSectionTotal('doors')
      expect(sectionTotal).toBe(402.5) // (200 + 150) * 1.15
    })

    it('calculates grand total across all sections', () => {
      estimatorStore.quoteData = {
        doors: [{ id: '1', item: 'Door', qty: 1, price: 100, total: 100 }],
        frames: [{ id: '1', item: 'Frame', qty: 1, price: 50, total: 50 }],
        hardware: [{ id: '1', item: 'Hardware', qty: 1, price: 25, total: 25 }]
      }
      estimatorStore.markups = { doors: 15, frames: 12, hardware: 18 }
      
      const grandTotal = wrapper.vm.calculateGrandTotal()
      const expected = (100 * 1.15) + (50 * 1.12) + (25 * 1.18)
      expect(grandTotal).toBe(expected)
    })

    it('updates grand total reactively', async () => {
      estimatorStore.quoteData.doors[0] = { id: '1', item: 'Door', qty: 1, price: 100, total: 100 }
      await wrapper.vm.$nextTick()
      
      const initialTotal = wrapper.find('[data-testid="grand-total-amount"]').text()
      
      // Update quantity
      const qtyInput = wrapper.find('[data-testid="doors-item-0-qty"]')
      await qtyInput.setValue('2')
      await qtyInput.trigger('input')
      await wrapper.vm.$nextTick()
      
      const updatedTotal = wrapper.find('[data-testid="grand-total-amount"]').text()
      expect(updatedTotal).not.toBe(initialTotal)
    })
  })

  describe('Input Validation', () => {
    it('validates quantity input', async () => {
      const qtyInput = wrapper.find('[data-testid="doors-item-0-qty"]')
      
      // Test negative quantity
      await qtyInput.setValue('-1')
      await qtyInput.trigger('blur')
      
      expect(wrapper.find('[data-testid="qty-error"]').exists()).toBe(true)
    })

    it('validates price input', async () => {
      const priceInput = wrapper.find('[data-testid="doors-item-0-price"]')
      
      // Test negative price
      await priceInput.setValue('-10')
      await priceInput.trigger('blur')
      
      expect(wrapper.find('[data-testid="price-error"]').exists()).toBe(true)
    })

    it('sanitizes item name input', async () => {
      const itemInput = wrapper.find('[data-testid="doors-item-0-name"]')
      
      await itemInput.setValue('<script>alert("xss")</script>Door')
      await itemInput.trigger('blur')
      
      expect(estimatorStore.quoteData.doors[0].item).not.toContain('<script>')
      expect(estimatorStore.quoteData.doors[0].item).toContain('Door')
    })

    it('validates markup percentages', async () => {
      const markupInput = wrapper.find('[data-testid="doors-markup"]')
      
      // Test negative markup
      await markupInput.setValue('-5')
      await markupInput.trigger('blur')
      
      expect(wrapper.find('[data-testid="markup-error"]').exists()).toBe(true)
    })
  })

  describe('Accessibility', () => {
    it('has proper form labels', () => {
      const inputs = wrapper.findAll('input')
      inputs.forEach(input => {
        const label = wrapper.find(`label[for="${input.attributes('id')}"]`)
        expect(label.exists() || input.attributes('aria-label')).toBeTruthy()
      })
    })

    it('has proper ARIA attributes for sections', () => {
      const sections = wrapper.findAll('[data-testid$="-section"]')
      sections.forEach(section => {
        expect(section.attributes('role')).toBe('region')
        expect(section.attributes('aria-labelledby')).toBeDefined()
      })
    })

    it('supports keyboard navigation', async () => {
      const firstInput = wrapper.find('input')
      await firstInput.trigger('focus')
      
      // Tab should move to next input
      await firstInput.trigger('keydown.tab')
      
      // Should focus next input (implementation depends on actual DOM structure)
      expect(document.activeElement).not.toBe(firstInput.element)
    })

    it('announces total changes to screen readers', async () => {
      const grandTotalElement = wrapper.find('[data-testid="grand-total"]')
      expect(grandTotalElement.attributes('aria-live')).toBe('polite')
    })
  })

  describe('Performance', () => {
    it('debounces price lookups', async () => {
      jest.useFakeTimers()
      const { EstimatorApi } = require('../../../src/services/EstimatorApi')
      
      const itemInput = wrapper.find('[data-testid="doors-item-0-name"]')
      
      // Rapid typing
      await itemInput.setValue('D')
      await itemInput.setValue('Do')
      await itemInput.setValue('Door')
      
      // Should not have called API yet
      expect(EstimatorApi.lookupPrice).not.toHaveBeenCalled()
      
      // Fast forward debounce timer
      jest.advanceTimersByTime(500)
      await flushPromises()
      
      // Now should have called API once
      expect(EstimatorApi.lookupPrice).toHaveBeenCalledTimes(1)
      
      jest.useRealTimers()
    })

    it('efficiently handles large numbers of items', async () => {
      // Add many items to doors section
      const manyItems = Array.from({ length: 100 }, (_, i) => ({
        id: `${i}`,
        item: `Door ${i}`,
        qty: 1,
        price: 100,
        total: 100
      }))
      
      estimatorStore.quoteData.doors = manyItems
      await wrapper.vm.$nextTick()
      
      // Should render without performance issues
      const doorItems = wrapper.findAll('[data-testid="doors-item"]')
      expect(doorItems.length).toBe(100)
      
      // Grand total calculation should be fast
      const startTime = performance.now()
      wrapper.vm.calculateGrandTotal()
      const endTime = performance.now()
      
      expect(endTime - startTime).toBeLessThan(100) // Should complete in <100ms
    })
  })

  describe('Error Handling', () => {
    it('handles price lookup failures gracefully', async () => {
      const { EstimatorApi } = require('../../../src/services/EstimatorApi')
      EstimatorApi.lookupPrice.mockRejectedValue(new Error('Network error'))
      
      const consoleSpy = jest.spyOn(console, 'error').mockImplementation()
      
      const itemInput = wrapper.find('[data-testid="doors-item-0-name"]')
      await itemInput.setValue('Test Door')
      await itemInput.trigger('blur')
      await flushPromises()
      
      expect(consoleSpy).toHaveBeenCalledWith('Error looking up price:', expect.any(Error))
      
      // Should show error notification
      expect(notificationStore.notifications.some(n => n.type === 'error')).toBe(true)
      
      consoleSpy.mockRestore()
    })

    it('handles invalid calculation inputs', () => {
      estimatorStore.quoteData.doors[0] = { 
        id: '1', 
        item: 'Door', 
        qty: NaN, 
        price: 'invalid', 
        total: null 
      }
      
      // Should not crash
      expect(() => wrapper.vm.calculateSectionTotal('doors')).not.toThrow()
      expect(() => wrapper.vm.calculateGrandTotal()).not.toThrow()
    })

    it('recovers from store errors', async () => {
      const consoleSpy = jest.spyOn(console, 'error').mockImplementation()
      
      // Simulate store error
      estimatorStore.updateQuoteItem = jest.fn().mockImplementation(() => {
        throw new Error('Store error')
      })
      
      const itemInput = wrapper.find('[data-testid="doors-item-0-name"]')
      await itemInput.setValue('Test')
      await itemInput.trigger('input')
      
      // Should handle error gracefully
      expect(wrapper.exists()).toBe(true)
      
      consoleSpy.mockRestore()
    })
  })

  describe('Responsive Design', () => {
    it('adapts layout for mobile devices', async () => {
      // Mock mobile viewport
      Object.defineProperty(window, 'innerWidth', {
        writable: true,
        configurable: true,
        value: 375
      })
      
      window.dispatchEvent(new Event('resize'))
      await wrapper.vm.$nextTick()
      
      const container = wrapper.find('[data-testid="estimator-view"]')
      expect(container.classes()).toContain('mobile')
    })

    it('stacks sections vertically on small screens', async () => {
      await wrapper.setData({ isMobile: true })
      
      const sections = wrapper.find('[data-testid="quote-sections"]')
      expect(sections.classes()).toContain('vertical-layout')
    })
  })
})