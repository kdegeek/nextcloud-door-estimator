import { mount, flushPromises } from '@vue/test-utils'
import App from '../src/App.vue'
import NcAppContent from '@nextcloud/vue/dist/Components/NcAppContent.js'
import * as priceUtils from '../utils/priceUtils'
import * as domUtils from '../utils/domUtils'

jest.mock('../utils/priceUtils')
jest.mock('../utils/domUtils')

describe('App.vue', () => {
  let wrapper

  beforeEach(() => {
    wrapper = mount(App)
    jest.clearAllMocks()
  })

  it('renders without crashing', () => {
    expect(wrapper.exists()).toBe(true)
    expect(wrapper.findComponent(NcAppContent).exists()).toBe(true)
  })

  it('shows estimator tab by default and can switch tabs', async () => {
    expect(wrapper.vm.activeTab).toBe('estimator')
    expect(wrapper.text()).toContain('Grand Total')
    // Switch to admin
    await wrapper.findAll('button').at(1).trigger('click')
    expect(wrapper.vm.activeTab).toBe('admin')
    expect(wrapper.text()).toContain('Admin Panel')
    // Switch back to estimator
    await wrapper.findAll('button').at(0).trigger('click')
    expect(wrapper.vm.activeTab).toBe('estimator')
  })

  it('toggles dark mode', async () => {
    expect(wrapper.vm.darkMode).toBe(false)
    await wrapper.findAll('button').at(2).trigger('click')
    expect(wrapper.vm.darkMode).toBe(true)
    await wrapper.findAll('button').at(2).trigger('click')
    expect(wrapper.vm.darkMode).toBe(false)
  })

  it('renders all quote sections and allows input', async () => {
    for (const section of wrapper.vm.sections) {
      expect(wrapper.text()).toContain(section.title)
      // Find first item input in section
      const sectionKey = section.key
      const itemInput = wrapper.find(`input[placeholder="${section.hasFrameType ? 'Frame Item' : 'Item'}"]`)
      await itemInput.setValue('Test Item')
      await itemInput.trigger('change')
      expect(wrapper.vm.quoteData[sectionKey][0].item).toBe('Test Item')
    }
  })

  it('updates frame type and triggers price lookup', async () => {
    // Only frames section has frameType
    await wrapper.setData({ activeTab: 'estimator' })
    const frameTypeSelect = wrapper.find('select')
    await frameTypeSelect.setValue('HM EWA')
    await frameTypeSelect.trigger('change')
    expect(wrapper.vm.quoteData.frames[0].frameType).toBe('HM EWA')
    expect(priceUtils.lookupPrice).toHaveBeenCalledWith(
      wrapper.vm.pricingData,
      'frames',
      wrapper.vm.quoteData.frames[0].item,
      'HM EWA'
    )
  })

  it('updates item, qty, price and calculates total', async () => {
    const sectionKey = 'doors'
    const idx = 0
    wrapper.vm.quoteData[sectionKey][idx].item = 'Test Door'
    wrapper.vm.quoteData[sectionKey][idx].qty = 2
    wrapper.vm.quoteData[sectionKey][idx].price = 100
    wrapper.vm.updateQuoteItem(sectionKey, idx, 'item', 'Test Door')
    wrapper.vm.updateQuoteItem(sectionKey, idx, 'qty', 2)
    wrapper.vm.updateQuoteItem(sectionKey, idx, 'price', 100)
    expect(wrapper.vm.quoteData[sectionKey][idx].total).toBe(200)
  })

  it('calculates section total and grand total using priceUtils', () => {
    jest.spyOn(priceUtils, 'calculateSectionTotal').mockReturnValue(330)
    jest.spyOn(priceUtils, 'calculateGrandTotal').mockReturnValue(382.5)
    expect(wrapper.vm.sectionTotal('doors')).toBe(330)
    expect(wrapper.vm.grandTotal).toBe(382.5)
  })

  it('shows error message for invalid input', async () => {
    wrapper.vm.setInputError('Test error')
    await flushPromises()
    expect(wrapper.text()).toContain('Test error')
  })

  it('imports data via admin panel and updates pricing/markups', async () => {
    await wrapper.findAll('button').at(1).trigger('click') // Switch to admin
    await wrapper.find('button.bg-blue-600').trigger('click') // Open import dialog
    expect(wrapper.vm.showImportDialog).toBe(true)
    const importText = '{"pricingData":{"doors":[{"item":"Test Door","price":100}]}, "markups":{"doors":10}}'
    await wrapper.find('textarea').setValue(importText)
    jest.spyOn(domUtils, 'handleImport').mockImplementation((text, setPricing, setMarkups, setShow) => {
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

  it('handles import error (invalid JSON)', async () => {
    await wrapper.findAll('button').at(1).trigger('click') // Switch to admin
    await wrapper.find('button.bg-blue-600').trigger('click') // Open import dialog
    jest.spyOn(domUtils, 'handleImport').mockImplementation(() => {
      throw new Error('Invalid JSON format')
    })
    await wrapper.find('textarea').setValue('not a json')
    try {
      wrapper.vm.importData()
    } catch (e) {
      expect(e.message).toContain('Invalid JSON format')
    }
  })

  it('exports data via admin panel', async () => {
    await wrapper.findAll('button').at(1).trigger('click') // Switch to admin
    jest.spyOn(domUtils, 'handleExport').mockImplementation((pricingData, markups, setShowExportDialog) => {
      setShowExportDialog(false)
    })
    wrapper.vm.exportData()
    expect(domUtils.handleExport).toHaveBeenCalledWith(
      wrapper.vm.pricingData,
      wrapper.vm.markups,
      expect.any(Function)
    )
  })

  it('reactively updates computed grandTotal when quoteData changes', async () => {
    jest.spyOn(priceUtils, 'calculateGrandTotal').mockReturnValue(999)
    wrapper.vm.quoteData.doors[0].total = 500
    await flushPromises()
    expect(wrapper.vm.grandTotal).toBe(999)
  })

  it('shows all pricing categories in admin view', async () => {
    await wrapper.findAll('button').at(1).trigger('click') // Switch to admin
    for (const category of Object.keys(wrapper.vm.pricingData)) {
      expect(wrapper.text()).toContain(category.charAt(0).toUpperCase() + category.slice(1))
    }
  })
})