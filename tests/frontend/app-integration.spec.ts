import { mount, flushPromises } from '@vue/test-utils'
import App from '../../src/App.vue'
import NcAppContent from '@nextcloud/vue/dist/Components/NcAppContent.js'
import * as priceUtils from '../../utils/priceUtils'
import * as domUtils from '../../utils/domUtils'

jest.mock('@nextcloud/vue/dist/Components/NcAppContent.js', () => ({
  __esModule: true,
  default: {
    name: 'NcAppContent',
    template: '<div><slot /></div>',
  },
}))

jest.mock('../../utils/priceUtils')
jest.mock('../../utils/domUtils')

describe('Door Estimator App Integration', () => {
  let wrapper: any

  beforeEach(() => {
    wrapper = mount(App, {
      global: {
        components: { NcAppContent },
      },
    })
    jest.clearAllMocks()
  })

  it('renders navigation tabs and toggles between estimator/admin views', async () => {
    expect(wrapper.find('nav').exists()).toBe(true)
    expect(wrapper.text()).toContain('Estimator')
    expect(wrapper.text()).toContain('Admin')
    // Default tab
    expect(wrapper.findComponent({ ref: 'estimator' })).toBeTruthy()
    // Switch to admin
    await wrapper.findAll('button').at(1)!.trigger('click')
    expect(wrapper.vm.activeTab).toBe('admin')
    expect(wrapper.text()).toContain('Admin Panel')
    // Switch back to estimator
    await wrapper.findAll('button').at(0)!.trigger('click')
    expect(wrapper.vm.activeTab).toBe('estimator')
    expect(wrapper.text()).toContain('Grand Total')
  })

  it('toggles dark mode and updates UI classes', async () => {
    const darkBtn = wrapper.findAll('button').at(2)
    expect(wrapper.vm.darkMode).toBe(false)
    await darkBtn.trigger('click')
    expect(wrapper.vm.darkMode).toBe(true)
    expect(wrapper.find('.bg-gray-900').exists()).toBe(true)
    await darkBtn.trigger('click')
    expect(wrapper.vm.darkMode).toBe(false)
    expect(wrapper.find('.bg-gray-100').exists()).toBe(true)
  })

  it('edits quote items and updates totals reactively', async () => {
    // Find first item input in Doors section
    const itemInput = wrapper.find('input[placeholder="Item"]')
    await itemInput.setValue('2-0 x 6-8 Flush HM 18ga. Cyl lock prep, 2-3/4 backset')
    await itemInput.trigger('change')
    // Set quantity
    const qtyInput = wrapper.find('input[type="number"][min="0"]')
    await qtyInput.setValue(2)
    await qtyInput.trigger('change')
    // Mock price lookup
    (priceUtils.lookupPrice as jest.Mock).mockReturnValue(493)
    wrapper.vm.updateQuoteItem('doors', 0, 'item', '2-0 x 6-8 Flush HM 18ga. Cyl lock prep, 2-3/4 backset')
    wrapper.vm.updateQuoteItem('doors', 0, 'qty', 2)
    // Check total calculation
    expect(wrapper.vm.quoteData.doors[0].total).toBe(986)
    // Section total uses markup
    (priceUtils.calculateSectionTotal as jest.Mock).mockReturnValue(1134.9)
    expect(wrapper.vm.sectionTotal('doors')).toBeCloseTo(1134.9)
    // Grand total computed
    (priceUtils.calculateGrandTotal as jest.Mock).mockReturnValue(1134.9)
    expect(wrapper.vm.grandTotal).toBeCloseTo(1134.9)
  })

  it('shows error message for invalid input', async () => {
    wrapper.vm.setInputError('Test error')
    await flushPromises()
    expect(wrapper.text()).toContain('Test error')
    // Error shown in both section and grand total area
    expect(wrapper.find('.text-red-400').exists()).toBe(true)
    expect(wrapper.find('.text-red-500').exists()).toBe(true)
  })

  it('imports data via admin panel and updates pricing/markups', async () => {
    // Open admin tab
    await wrapper.findAll('button').at(1)!.trigger('click')
    // Open import dialog
    await wrapper.find('button.bg-blue-600').trigger('click')
    expect(wrapper.vm.showImportDialog).toBe(true)
    // Set import text
    const importText = '{"pricingData":{"doors":[{"item":"Test Door","price":100}]}, "markups":{"doors":10}}'
    await wrapper.find('textarea').setValue(importText)
    // Mock domUtils.handleImport
    (domUtils.handleImport as jest.Mock).mockImplementation((text, setPricing, setMarkups, setShow) => {
      setPricing({ doors: [{ item: 'Test Door', price: 100 }] })
      setMarkups({ doors: 10 })
      setShow(false)
    })
    await wrapper.find('button.bg-blue-600').trigger('click')
    wrapper.vm.importData()
    expect(wrapper.vm.pricingData.doors[0].item).toBe('Test Door')
    expect(wrapper.vm.markups.doors).toBe(10)
    expect(wrapper.vm.showImportDialog).toBe(false)
  })

  it('exports data via admin panel', async () => {
    await wrapper.findAll('button').at(1)!.trigger('click')
    // Mock domUtils.handleExport
    (domUtils.handleExport as jest.Mock).mockImplementation((pricing, markups, setShow) => {
      setShow(false)
    })
    await wrapper.find('button.bg-green-600').trigger('click')
    wrapper.vm.exportData()
    expect(domUtils.handleExport).toHaveBeenCalledWith(
      wrapper.vm.pricingData,
      wrapper.vm.markups,
      expect.any(Function)
    )
  })

  it('updates computed grand total when quoteData changes', async () => {
    (priceUtils.calculateGrandTotal as jest.Mock).mockReturnValue(2000)
    wrapper.vm.quoteData.doors[0].total = 1000
    wrapper.vm.quoteData.frames[0].total = 1000
    await flushPromises()
    expect(wrapper.vm.grandTotal).toBe(2000)
  })

  it('renders Nextcloud Vue NcAppContent wrapper', () => {
    expect(wrapper.findComponent(NcAppContent).exists()).toBe(true)
  })

  it('handles frameType selection and price lookup', async () => {
    // Find frameType select
    const frameSelect = wrapper.find('select')
    await frameSelect.setValue('HM EWA')
    await frameSelect.trigger('change')
    (priceUtils.lookupPrice as jest.Mock).mockReturnValue(555)
    wrapper.vm.updateQuoteItem('frames', 0, 'frameType', 'HM EWA')
    expect(wrapper.vm.quoteData.frames[0].price).toBe(555)
  })

  it('shows error on invalid JSON import', async () => {
    await wrapper.findAll('button').at(1)!.trigger('click')
    await wrapper.find('button.bg-blue-600').trigger('click')
    await wrapper.find('textarea').setValue('invalid json')
    // Mock domUtils.handleImport to throw
    (domUtils.handleImport as jest.Mock).mockImplementation(() => {
      throw new Error('Invalid JSON format')
    })
    expect(() => wrapper.vm.importData()).toThrow('Invalid JSON format')
  })

  it('handles user interactions for adding/removing items', async () => {
    // Simulate adding a new item to doors
    wrapper.vm.quoteData.doors.push({ id: 'C', item: 'New Door', qty: 1, price: 100, total: 100 })
    await flushPromises()
    expect(wrapper.vm.quoteData.doors.length).toBeGreaterThan(2)
    // Simulate removing an item
    wrapper.vm.quoteData.doors.pop()
    await flushPromises()
    expect(wrapper.vm.quoteData.doors.length).toBe(2)
  })
})