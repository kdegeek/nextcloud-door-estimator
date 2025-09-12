import { defineStore } from 'pinia'
import { ref, reactive, computed } from 'vue'
import type { 
  QuoteDataMap, 
  MarkupsMap, 
  PricingDataMap, 
  SectionKey, 
  QuoteLineItem,
  PricingItem
} from '../types'
import { createEstimatorApi } from '../services'

export const useEstimatorStore = defineStore('estimator', () => {
  // API client
  const api = createEstimatorApi()

  // Loading states
  const loading = ref(false)
  const pricingLoading = ref(false)
  const quotesLoading = ref(false)

  // Error states
  const error = ref<string>('')
  const pricingError = ref<string>('')

  // Core data
  const quoteData = reactive<QuoteDataMap>({
    doors: [{ id: 'A', item: '', qty: 0, price: 0, total: 0 }, { id: 'B', item: '', qty: 0, price: 0, total: 0 }],
    doorOptions: [{ id: 'A', item: '', qty: 0, price: 0, total: 0 }],
    inserts: [{ id: 'A', item: '', qty: 0, price: 0, total: 0 }],
    frames: [{ id: 'A', item: '', frameType: 'HM Drywall', qty: 0, price: 0, total: 0 }],
    frameOptions: [{ id: 'A', item: '', qty: 0, price: 0, total: 0 }],
    hinges: [{ id: 'A', item: '', qty: 0, price: 0, total: 0 }],
    weatherstrip: [{ id: 'A', item: '', qty: 0, price: 0, total: 0 }],
    closers: [{ id: 'A', item: '', qty: 0, price: 0, total: 0 }],
    locksets: [{ id: 'A', item: '', qty: 0, price: 0, total: 0 }],
    exitDevices: [{ id: 'A', item: '', qty: 0, price: 0, total: 0 }],
    hardware: [{ id: 'A', item: '', qty: 0, price: 0, total: 0 }],
  })

  const markups = reactive<MarkupsMap>({ 
    doors: 15, 
    frames: 12, 
    hardware: 18 
  })

  // Default markups for comparison and reset functionality
  const defaultMarkups = reactive<MarkupsMap>({ 
    doors: 15, 
    frames: 12, 
    hardware: 18 
  })

  // Track which markups have been overridden from defaults
  const markupsOverridden = reactive({
    doors: false,
    frames: false,
    hardware: false
  })

  const pricingData = reactive<PricingDataMap>({
    doors: [],
    doorOptions: [],
    inserts: [],
    frames: { 'HM Drywall': [], 'HM EWA': [], 'HM USA': [] },
    frameOptions: [],
    hinges: [],
    weatherstrip: [],
    closers: [],
    locksets: [],
    exitDevices: [],
    hardware: [],
  })

  // Quotes management
  const quotes = ref<Array<{
    id: number
    quote_name: string
    total_amount: number
    created_at: string
    updated_at: string
  }>>([])

  const currentQuote = ref<{
    id: number
    quote_name: string
    customer_info?: any
    quote_data: Record<string, QuoteLineItem[]>
    markups: MarkupsMap
    total_amount: number
    created_at: string
    updated_at: string
  } | null>(null)

  // Computed values
  const grandTotal = computed(() => {
    let total = 0
    
    // Calculate section totals with markups
    const doorTotal = quoteData.doors.reduce((sum, item) => sum + (item.total || 0), 0)
    const doorOptionsTotal = quoteData.doorOptions.reduce((sum, item) => sum + (item.total || 0), 0)
    const insertsTotal = quoteData.inserts.reduce((sum, item) => sum + (item.total || 0), 0)
    
    const frameTotal = quoteData.frames.reduce((sum, item) => sum + (item.total || 0), 0)
    const frameOptionsTotal = quoteData.frameOptions.reduce((sum, item) => sum + (item.total || 0), 0)
    
    const hardwareTotal = [
      ...quoteData.hinges,
      ...quoteData.weatherstrip,
      ...quoteData.closers,
      ...quoteData.locksets,
      ...quoteData.exitDevices,
      ...quoteData.hardware
    ].reduce((sum, item) => sum + (item.total || 0), 0)

    // Apply markups
    total += (doorTotal + doorOptionsTotal + insertsTotal) * (1 + markups.doors / 100)
    total += (frameTotal + frameOptionsTotal) * (1 + markups.frames / 100)
    total += hardwareTotal * (1 + markups.hardware / 100)

    return total
  })

  // Actions
  const setLoading = (value: boolean) => {
    loading.value = value
  }

  const setError = (message: string) => {
    error.value = message
  }

  const clearError = () => {
    error.value = ''
  }

  // Normalize pricing data from API response
  const normalizePricing = (data: any[]): PricingDataMap => {
    const normalized: PricingDataMap = {
      doors: [],
      doorOptions: [],
      inserts: [],
      frames: { 'HM Drywall': [], 'HM EWA': [], 'HM USA': [] },
      frameOptions: [],
      hinges: [],
      weatherstrip: [],
      closers: [],
      locksets: [],
      exitDevices: [],
      hardware: [],
    }
    
    if (Array.isArray(data)) {
      data.forEach(item => {
        const categoryKey = item?.category as keyof PricingDataMap
        if (item?.category && Object.prototype.hasOwnProperty.call(normalized, categoryKey)) {
          const category = normalized[categoryKey]
          const priceParsed = typeof item.price === 'number' ? item.price : parseFloat(String(item.price))
          const price = Number.isNaN(priceParsed) || priceParsed < 0 ? 0 : priceParsed
          const itemName = typeof item.item === 'string' ? item.item : String(item.item ?? '')
          
          if (Array.isArray(category)) {
            category.push({ item: itemName, price })
          } else if (!Array.isArray(category) && typeof category === 'object' && item.subcategory) {
            const catObj = category as Record<string, Array<{ item: string; price: number }>>
            const subArr = catObj[item.subcategory]
            if (Array.isArray(subArr)) {
              subArr.push({ item: itemName, price })
            }
          }
        }
      })
    }
    
    return normalized
  }

  // Load pricing data
  const loadPricingData = async () => {
    try {
      pricingLoading.value = true
      pricingError.value = ''
      
      const data = await api.listPricingByCategory()
      const normalized = normalizePricing(data)
      
      // Update reactive pricing data
      Object.assign(pricingData, normalized)
      
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Failed to load pricing data'
      pricingError.value = message
      throw err
    } finally {
      pricingLoading.value = false
    }
  }

  // Load quotes
  const loadQuotes = async () => {
    try {
      quotesLoading.value = true
      const data = await api.listQuotes()
      quotes.value = data
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Failed to load quotes'
      setError(message)
      throw err
    } finally {
      quotesLoading.value = false
    }
  }

  // Save current quote
  const saveQuote = async (quoteName?: string, customerInfo?: any) => {
    try {
      setLoading(true)
      clearError()
      
      const response = await api.createQuote({
        quoteData,
        markups,
        quoteName,
        customerInfo
      })
      
      // Reload quotes list
      await loadQuotes()
      
      return response
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Failed to save quote'
      setError(message)
      throw err
    } finally {
      setLoading(false)
    }
  }

  // Load a specific quote
  const loadQuote = async (quoteId: number) => {
    try {
      setLoading(true)
      clearError()
      
      const quote = await api.getQuote(quoteId)
      currentQuote.value = quote
      
      // Update current quote data
      Object.assign(quoteData, quote.quote_data)
      Object.assign(markups, quote.markups)
      
      // Update flexible pricing metadata if available
      if (quote.defaultMarkups) {
        Object.assign(defaultMarkups, quote.defaultMarkups)
      }
      if (quote.markupsOverridden) {
        Object.assign(markupsOverridden, quote.markupsOverridden)
      }
      
      return quote
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Failed to load quote'
      setError(message)
      throw err
    } finally {
      setLoading(false)
    }
  }

  // Update quote item with flexible pricing support
  const updateQuoteItem = (
    section: SectionKey, 
    index: number, 
    field: 'qty' | 'price' | 'item' | 'frameType', 
    value: unknown
  ) => {
    const item = quoteData[section][index] as QuoteLineItem

    if (field === 'qty') {
      const n = typeof value === 'number' ? value : parseFloat(String(value))
      item.qty = Number.isNaN(n) || n < 0 ? 0 : n
    } else if (field === 'price') {
      const n = typeof value === 'number' ? value : parseFloat(String(value))
      const newPrice = Number.isNaN(n) || n < 0 ? 0 : n
      
      // Track price override if different from base price
      if (item.basePrice !== undefined) {
        item.priceOverridden = Math.abs(newPrice - item.basePrice) > 0.01
      } else {
        item.priceOverridden = newPrice > 0
      }
      
      item.price = newPrice
    } else if (field === 'item') {
      item.item = String(value ?? '')
      // Reset price override flag when item changes
      item.priceOverridden = false
    } else if (field === 'frameType' && 'frameType' in item) {
      (item as QuoteLineItem & { frameType?: string }).frameType = String(value ?? '')
      // Reset price override flag when frame type changes
      item.priceOverridden = false
    }

    // Update total
    item.total = item.qty * item.price

    // Price lookup for item changes
    if (field === 'item' || field === 'frameType') {
      lookupItemPrice(section, index)
    }
  }

  // Lookup price for an item with real-time API integration
  const lookupItemPrice = async (section: SectionKey, index: number) => {
    const item = quoteData[section][index] as QuoteLineItem
    if (!item.item) return

    try {
      let price = 0
      
      // First try local pricing data for immediate response
      if (section === 'frames' && 'frameType' in item) {
        const frameType = (item as QuoteLineItem & { frameType?: string }).frameType
        if (frameType && pricingData.frames && typeof pricingData.frames === 'object') {
          const frameItems = pricingData.frames[frameType]
          if (Array.isArray(frameItems)) {
            const found = frameItems.find(p => p.item === item.item)
            price = found?.price ?? 0
          }
        }
      } else {
        const categoryData = pricingData[section]
        if (Array.isArray(categoryData)) {
          const found = categoryData.find(p => p.item === item.item)
          price = found?.price ?? 0
        }
      }

      // If not found locally, try API lookup for real-time pricing
      if (price === 0) {
        try {
          const frameType = section === 'frames' && 'frameType' in item 
            ? (item as QuoteLineItem & { frameType?: string }).frameType 
            : undefined
          
          const result = await api.lookupPrice({
            category: section,
            item: item.item,
            frameType
          })
          
          price = result.price
        } catch (apiErr) {
          // API lookup failed, continue with local price (0)
          console.warn('API price lookup failed:', apiErr)
        }
      }

      if (price > 0) {
        // Store base price for comparison
        item.basePrice = price
        
        // Only update actual price if not manually overridden
        if (!item.priceOverridden) {
          item.price = price
          item.total = item.qty * item.price
        }
      }
    } catch (err) {
      console.warn('Price lookup failed:', err)
    }
  }

  // Calculate section total
  const getSectionTotal = (sectionKey: SectionKey): number => {
    const items = quoteData[sectionKey]
    const subtotal = items.reduce((sum, item) => sum + (item.total || 0), 0)
    
    // Apply appropriate markup
    let markupPercent = 0
    if (['doors', 'doorOptions', 'inserts'].includes(sectionKey)) {
      markupPercent = markups.doors
    } else if (['frames', 'frameOptions'].includes(sectionKey)) {
      markupPercent = markups.frames
    } else {
      markupPercent = markups.hardware
    }
    
    return subtotal * (1 + markupPercent / 100)
  }

  // Add new line item to a section
  const addLineItem = (section: SectionKey) => {
    const newId = String.fromCharCode(65 + quoteData[section].length) // A, B, C, etc.
    const newItem: QuoteLineItem = {
      id: newId,
      item: '',
      qty: 0,
      price: 0,
      total: 0
    }
    
    // Add frameType for frames section
    if (section === 'frames') {
      (newItem as QuoteLineItem & { frameType?: string }).frameType = 'HM Drywall'
    }
    
    quoteData[section].push(newItem)
  }

  // Remove line item from a section
  const removeLineItem = (section: SectionKey, index: number) => {
    if (quoteData[section].length > 1) {
      quoteData[section].splice(index, 1)
    }
  }

  // Reset quote data
  const resetQuote = () => {
    // Reset all sections to initial state
    Object.keys(quoteData).forEach(key => {
      const sectionKey = key as SectionKey
      quoteData[sectionKey].forEach(item => {
        item.item = ''
        item.qty = 0
        item.price = 0
        item.total = 0
        if ('frameType' in item) {
          (item as QuoteLineItem & { frameType?: string }).frameType = 'HM Drywall'
        }
      })
    })
    
    // Reset markups to defaults
    markups.doors = defaultMarkups.doors
    markups.frames = defaultMarkups.frames
    markups.hardware = defaultMarkups.hardware
    markupsOverridden.doors = false
    markupsOverridden.frames = false
    markupsOverridden.hardware = false
    
    currentQuote.value = null
  }

  // Delete a quote
  const deleteQuote = async (quoteId: number) => {
    try {
      setLoading(true)
      clearError()
      
      await api.deleteQuote(quoteId)
      
      // Remove from local quotes list
      quotes.value = quotes.value.filter(q => q.id !== quoteId)
      
      // Clear current quote if it was the deleted one
      if (currentQuote.value?.id === quoteId) {
        currentQuote.value = null
      }
      
      return true
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Failed to delete quote'
      setError(message)
      throw err
    } finally {
      setLoading(false)
    }
  }

  // Duplicate a quote
  const duplicateQuote = async (quoteId: number) => {
    try {
      setLoading(true)
      clearError()
      
      const response = await api.duplicateQuote(quoteId)
      
      // Reload quotes list to include the new duplicate
      await loadQuotes()
      
      return response.quoteId
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Failed to duplicate quote'
      setError(message)
      throw err
    } finally {
      setLoading(false)
    }
  }

  // Check onboarding status
  const checkOnboardingStatus = async () => {
    try {
      const status = await api.getOnboardingStatus()
      return status
    } catch (err) {
      console.warn('Failed to check onboarding status:', err)
      return { hasData: false }
    }
  }

  // Check if current user is admin
  const checkAdminStatus = async () => {
    try {
      // Try to call an admin-only endpoint to check permissions
      await api.getMarkupDefaults()
      return true
    } catch (err) {
      // If we get a 403 error, user is not admin
      if (err instanceof Error && err.message.includes('403')) {
        return false
      }
      // For other errors, assume admin (fail open for now)
      return true
    }
  }

  // Load default markups for comparison and reset functionality
  const loadDefaultMarkups = async () => {
    try {
      const defaults = await api.getMarkupDefaults()
      Object.assign(defaultMarkups, defaults)
      
      // Update current markups if they haven't been customized
      if (!markupsOverridden.doors) markups.doors = defaults.doors
      if (!markupsOverridden.frames) markups.frames = defaults.frames
      if (!markupsOverridden.hardware) markups.hardware = defaults.hardware
      
    } catch (err) {
      console.warn('Failed to load default markups:', err)
    }
  }

  // Update markup for a section and track if it's overridden
  const updateMarkupForSection = (sectionKey: SectionKey, value: number) => {
    const markupCategory = getMarkupCategoryForSection(sectionKey)
    markups[markupCategory] = value
    
    // Check if this markup is different from default
    const defaultValue = defaultMarkups[markupCategory]
    markupsOverridden[markupCategory] = Math.abs(value - defaultValue) > 0.01
  }

  // Get markup category for a section
  const getMarkupCategoryForSection = (sectionKey: SectionKey): keyof MarkupsMap => {
    if (['doors', 'doorOptions', 'inserts'].includes(sectionKey)) {
      return 'doors'
    } else if (['frames', 'frameOptions'].includes(sectionKey)) {
      return 'frames'
    } else {
      return 'hardware'
    }
  }

  // Check if a markup is overridden from default
  const isMarkupOverridden = (sectionKey: SectionKey): boolean => {
    const markupCategory = getMarkupCategoryForSection(sectionKey)
    return markupsOverridden[markupCategory]
  }

  // Reset markup to default value
  const resetMarkupToDefault = (sectionKey: SectionKey) => {
    const markupCategory = getMarkupCategoryForSection(sectionKey)
    markups[markupCategory] = defaultMarkups[markupCategory]
    markupsOverridden[markupCategory] = false
  }

  // Reset all markups to defaults
  const resetAllMarkupsToDefaults = () => {
    markups.doors = defaultMarkups.doors
    markups.frames = defaultMarkups.frames
    markups.hardware = defaultMarkups.hardware
    markupsOverridden.doors = false
    markupsOverridden.frames = false
    markupsOverridden.hardware = false
  }

  // Check if a line item price is overridden from base price
  const isPriceOverridden = (sectionKey: SectionKey, index: number): boolean => {
    const item = quoteData[sectionKey][index] as QuoteLineItem
    return item.priceOverridden === true
  }

  // Get base price for a line item
  const getBasePrice = (sectionKey: SectionKey, index: number): number => {
    const item = quoteData[sectionKey][index] as QuoteLineItem
    return item.basePrice || 0
  }

  // Reset line item price to base price
  const resetPriceToBase = (sectionKey: SectionKey, index: number) => {
    const item = quoteData[sectionKey][index] as QuoteLineItem
    if (item.basePrice !== undefined) {
      item.price = item.basePrice
      item.priceOverridden = false
      item.total = item.qty * item.price
    }
  }

  // Import pricing from file
  const importPricingFromFile = async (file: File) => {
    try {
      setLoading(true)
      clearError()
      
      const result = await api.importPricing(file)
      
      if (result.success) {
        // Reload pricing data after successful import
        await loadPricingData()
      }
      
      return result
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Failed to import pricing data'
      setError(message)
      throw err
    } finally {
      setLoading(false)
    }
  }

  // Import pricing from JSON text
  const importPricingFromText = async (jsonText: string) => {
    try {
      setLoading(true)
      clearError()
      
      // Parse and validate JSON
      const data = JSON.parse(jsonText)
      
      // Create a temporary file from the JSON data
      const blob = new Blob([jsonText], { type: 'application/json' })
      const file = new File([blob], 'import.json', { type: 'application/json' })
      
      const result = await api.importPricing(file)
      
      if (result.success) {
        // Reload pricing data after successful import
        await loadPricingData()
      }
      
      return result
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Failed to import pricing data'
      setError(message)
      throw err
    } finally {
      setLoading(false)
    }
  }

  // Update pricing item
  const updatePricingItem = async (item: PricingItem & { id?: number; subcategory?: string; description?: string }) => {
    try {
      setLoading(true)
      clearError()
      
      // For now, we'll simulate the API call by updating the local data
      // In a real implementation, this would call the API endpoint
      
      const category = item.category as keyof PricingDataMap
      const categoryData = pricingData[category]
      
      if (Array.isArray(categoryData)) {
        // Simple category - update or add item
        const existingIndex = categoryData.findIndex(p => p.item === item.item)
        if (existingIndex >= 0) {
          categoryData[existingIndex] = { item: item.item, price: item.price }
        } else {
          categoryData.push({ item: item.item, price: item.price })
        }
      } else if (typeof categoryData === 'object' && item.subcategory) {
        // Complex category with subcategories (like frames)
        const subCategoryData = categoryData[item.subcategory]
        if (Array.isArray(subCategoryData)) {
          const existingIndex = subCategoryData.findIndex(p => p.item === item.item)
          if (existingIndex >= 0) {
            subCategoryData[existingIndex] = { item: item.item, price: item.price }
          } else {
            subCategoryData.push({ item: item.item, price: item.price })
          }
        }
      }
      
      return { success: true }
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Failed to update pricing item'
      setError(message)
      throw err
    } finally {
      setLoading(false)
    }
  }

  // Delete pricing item
  const deletePricingItem = async (itemId: number) => {
    try {
      setLoading(true)
      clearError()
      
      // For now, we'll simulate the API call by removing from local data
      // In a real implementation, this would call the API endpoint
      
      // Find and remove the item from pricing data
      let found = false
      Object.entries(pricingData).forEach(([category, data]) => {
        if (Array.isArray(data)) {
          const index = data.findIndex((_, idx) => idx === itemId - 1) // Simple ID mapping
          if (index >= 0) {
            data.splice(index, 1)
            found = true
          }
        } else if (typeof data === 'object' && data !== null) {
          Object.entries(data).forEach(([subcategory, subItems]) => {
            if (Array.isArray(subItems)) {
              const index = subItems.findIndex((_, idx) => idx === itemId - 1) // Simple ID mapping
              if (index >= 0) {
                subItems.splice(index, 1)
                found = true
              }
            }
          })
        }
      })
      
      if (!found) {
        throw new Error('Item not found')
      }
      
      return { success: true }
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Failed to delete pricing item'
      setError(message)
      throw err
    } finally {
      setLoading(false)
    }
  }

  return {
    // State
    loading,
    pricingLoading,
    quotesLoading,
    error,
    pricingError,
    quoteData,
    markups,
    defaultMarkups,
    markupsOverridden,
    pricingData,
    quotes,
    currentQuote,
    
    // Computed
    grandTotal,
    
    // Actions
    setLoading,
    setError,
    clearError,
    loadPricingData,
    loadQuotes,
    saveQuote,
    loadQuote,
    deleteQuote,
    duplicateQuote,
    updateQuoteItem,
    lookupItemPrice,
    getSectionTotal,
    addLineItem,
    removeLineItem,
    resetQuote,
    checkOnboardingStatus,
    checkAdminStatus,
    loadDefaultMarkups,
    updateMarkupForSection,
    getMarkupCategoryForSection,
    isMarkupOverridden,
    resetMarkupToDefault,
    resetAllMarkupsToDefaults,
    isPriceOverridden,
    getBasePrice,
    resetPriceToBase,
    importPricingFromFile,
    importPricingFromText,
    updatePricingItem,
    deletePricingItem,
  }
})