import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import QuoteManager from '../../src/components/QuoteManager.vue'
import { useEstimatorStore } from '../../src/stores/estimator'

// Mock the API
vi.mock('../../src/services', () => ({
  createEstimatorApi: () => ({
    listQuotes: vi.fn().mockResolvedValue([
      {
        id: 1,
        quote_name: 'Test Quote',
        total_amount: 1500.00,
        created_at: '2024-01-01T10:00:00Z',
        updated_at: '2024-01-01T10:00:00Z'
      }
    ]),
    createQuote: vi.fn().mockResolvedValue({ success: true, quoteId: 2 }),
    deleteQuote: vi.fn().mockResolvedValue({ success: true }),
    duplicateQuote: vi.fn().mockResolvedValue({ success: true, quoteId: 3 }),
    getQuote: vi.fn().mockResolvedValue({
      id: 1,
      quote_name: 'Test Quote',
      quote_data: {},
      markups: { doors: 15, frames: 12, hardware: 18 },
      total_amount: 1500.00,
      created_at: '2024-01-01T10:00:00Z',
      updated_at: '2024-01-01T10:00:00Z'
    })
  })
}))

// Mock notifications store
vi.mock('../../src/stores/notifications', () => ({
  useNotificationsStore: () => ({
    success: vi.fn(),
    error: vi.fn()
  })
}))

// Mock theme store
vi.mock('../../src/stores/theme', () => ({
  useThemeStore: () => ({
    isDarkMode: false
  })
}))

describe('QuoteManager', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('renders quote manager component', () => {
    const wrapper = mount(QuoteManager)
    
    expect(wrapper.find('.quote-manager').exists()).toBe(true)
    expect(wrapper.find('.section-title').text()).toBe('Quote Management')
    expect(wrapper.find('button').text()).toContain('Save Current Quote')
  })

  it('shows save dialog when save button is clicked', async () => {
    const wrapper = mount(QuoteManager)
    
    // Initially dialog should not be visible
    expect(wrapper.find('.modal-overlay').exists()).toBe(false)
    
    // Click save button
    await wrapper.find('button').trigger('click')
    
    // Dialog should now be visible
    expect(wrapper.find('.modal-overlay').exists()).toBe(true)
    expect(wrapper.find('.modal-title').text()).toBe('Save Quote')
  })

  it('displays quotes list when quotes are loaded', async () => {
    const wrapper = mount(QuoteManager)
    const store = useEstimatorStore()
    
    // Simulate loading quotes
    store.quotes = [
      {
        id: 1,
        quote_name: 'Test Quote',
        total_amount: 1500.00,
        created_at: '2024-01-01T10:00:00Z',
        updated_at: '2024-01-01T10:00:00Z'
      }
    ]
    
    await wrapper.vm.$nextTick()
    
    expect(wrapper.find('.quotes-table').exists()).toBe(true)
    expect(wrapper.find('.quote-name-button').text()).toBe('Test Quote')
    expect(wrapper.find('.total-cell').text()).toBe('$1500.00')
  })

  it('shows empty state when no quotes exist', async () => {
    const wrapper = mount(QuoteManager)
    const store = useEstimatorStore()
    
    // Ensure quotes array is empty
    store.quotes = []
    
    await wrapper.vm.$nextTick()
    
    expect(wrapper.find('.empty-state').exists()).toBe(true)
    expect(wrapper.find('.empty-state p').text()).toBe('No saved quotes found.')
  })

  it('shows delete confirmation dialog', async () => {
    const wrapper = mount(QuoteManager)
    const store = useEstimatorStore()
    
    // Add a quote
    store.quotes = [
      {
        id: 1,
        quote_name: 'Test Quote',
        total_amount: 1500.00,
        created_at: '2024-01-01T10:00:00Z',
        updated_at: '2024-01-01T10:00:00Z'
      }
    ]
    
    await wrapper.vm.$nextTick()
    
    // Click delete button
    const deleteButton = wrapper.find('.btn-danger')
    await deleteButton.trigger('click')
    
    // Delete confirmation dialog should be visible
    expect(wrapper.find('.modal-overlay').exists()).toBe(true)
    expect(wrapper.find('.modal-title').text()).toBe('Confirm Delete')
    expect(wrapper.find('.delete-message').text()).toContain('Test Quote')
  })
})