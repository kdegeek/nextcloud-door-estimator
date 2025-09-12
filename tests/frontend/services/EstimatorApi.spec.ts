import { EstimatorApi } from '../../../src/services/EstimatorApi'

// Mock axios
jest.mock('@nextcloud/axios', () => ({
  get: jest.fn(),
  post: jest.fn(),
  put: jest.fn(),
  delete: jest.fn()
}))

describe('EstimatorApi Service', () => {
  const mockAxios = require('@nextcloud/axios')

  beforeEach(() => {
    jest.clearAllMocks()
  })

  describe('Pricing Data Operations', () => {
    it('fetches all pricing data', async () => {
      const mockData = [
        { id: 1, category: 'doors', item: 'Door A', price: 100 },
        { id: 2, category: 'frames', item: 'Frame B', price: 50 }
      ]
      mockAxios.get.mockResolvedValue({ data: mockData })

      const result = await EstimatorApi.getAllPricingData()

      expect(mockAxios.get).toHaveBeenCalledWith('/apps/door_estimator/api/pricing')
      expect(result).toEqual(mockData)
    })

    it('fetches pricing data by category', async () => {
      const mockData = [
        { id: 1, category: 'doors', item: 'Door A', price: 100 }
      ]
      mockAxios.get.mockResolvedValue({ data: mockData })

      const result = await EstimatorApi.getPricingByCategory('doors')

      expect(mockAxios.get).toHaveBeenCalledWith('/apps/door_estimator/api/pricing/doors')
      expect(result).toEqual(mockData)
    })

    it('updates pricing item', async () => {
      const itemData = {
        id: 1,
        category: 'doors',
        item: 'Updated Door',
        price: 150
      }
      mockAxios.post.mockResolvedValue({ data: { success: true } })

      const result = await EstimatorApi.updatePricingItem(itemData)

      expect(mockAxios.post).toHaveBeenCalledWith('/apps/door_estimator/api/pricing', itemData)
      expect(result).toEqual({ success: true })
    })

    it('searches pricing items', async () => {
      const mockResults = [
        { id: 1, category: 'doors', item: 'Door A', price: 100 }
      ]
      mockAxios.post.mockResolvedValue({ data: mockResults })

      const result = await EstimatorApi.searchPricing('Door', 'doors', 10)

      expect(mockAxios.post).toHaveBeenCalledWith('/apps/door_estimator/api/pricing/search', {
        query: 'Door',
        category: 'doors',
        limit: 10
      })
      expect(result).toEqual(mockResults)
    })

    it('looks up price for item', async () => {
      mockAxios.post.mockResolvedValue({ data: { price: 123.45 } })

      const result = await EstimatorApi.lookupPrice('doors', 'Door A', 'HM EWA')

      expect(mockAxios.post).toHaveBeenCalledWith('/apps/door_estimator/api/lookup-price', {
        category: 'doors',
        item: 'Door A',
        frameType: 'HM EWA'
      })
      expect(result).toEqual({ price: 123.45 })
    })

    it('looks up price without frame type', async () => {
      mockAxios.post.mockResolvedValue({ data: { price: 100 } })

      const result = await EstimatorApi.lookupPrice('doors', 'Door A')

      expect(mockAxios.post).toHaveBeenCalledWith('/apps/door_estimator/api/lookup-price', {
        category: 'doors',
        item: 'Door A',
        frameType: null
      })
      expect(result).toEqual({ price: 100 })
    })
  })

  describe('Quote Operations', () => {
    it('saves quote', async () => {
      const quoteData = {
        doors: [{ item: 'Door A', qty: 1, price: 100, total: 100 }]
      }
      const markups = { doors: 15 }
      const quoteName = 'Test Quote'
      const customerInfo = { name: 'John Doe' }

      mockAxios.post.mockResolvedValue({ data: { success: true, quoteId: 123 } })

      const result = await EstimatorApi.saveQuote(quoteData, markups, quoteName, customerInfo)

      expect(mockAxios.post).toHaveBeenCalledWith('/apps/door_estimator/api/quotes', {
        quoteData,
        markups,
        quoteName,
        customerInfo
      })
      expect(result).toEqual({ success: true, quoteId: 123 })
    })

    it('gets user quotes', async () => {
      const mockQuotes = [
        { id: 1, quote_name: 'Quote 1', total_amount: 100 },
        { id: 2, quote_name: 'Quote 2', total_amount: 200 }
      ]
      mockAxios.get.mockResolvedValue({ data: mockQuotes })

      const result = await EstimatorApi.getUserQuotes()

      expect(mockAxios.get).toHaveBeenCalledWith('/apps/door_estimator/api/quotes')
      expect(result).toEqual(mockQuotes)
    })

    it('gets specific quote', async () => {
      const mockQuote = {
        id: 1,
        quote_name: 'Test Quote',
        quote_data: { doors: [] },
        markups: { doors: 15 }
      }
      mockAxios.get.mockResolvedValue({ data: mockQuote })

      const result = await EstimatorApi.getQuote(1)

      expect(mockAxios.get).toHaveBeenCalledWith('/apps/door_estimator/api/quotes/1')
      expect(result).toEqual(mockQuote)
    })

    it('deletes quote', async () => {
      mockAxios.delete.mockResolvedValue({ data: { success: true } })

      const result = await EstimatorApi.deleteQuote(1)

      expect(mockAxios.delete).toHaveBeenCalledWith('/apps/door_estimator/api/quotes/1')
      expect(result).toEqual({ success: true })
    })

    it('duplicates quote', async () => {
      mockAxios.post.mockResolvedValue({ data: { success: true, newQuoteId: 456 } })

      const result = await EstimatorApi.duplicateQuote(123)

      expect(mockAxios.post).toHaveBeenCalledWith('/apps/door_estimator/api/quotes/123/duplicate')
      expect(result).toEqual({ success: true, newQuoteId: 456 })
    })

    it('generates quote PDF', async () => {
      const mockPdfResult = {
        success: true,
        pdfPath: '/tmp/quote.pdf',
        downloadUrl: '/download/quote.pdf'
      }
      mockAxios.post.mockResolvedValue({ data: mockPdfResult })

      const result = await EstimatorApi.generateQuotePDF(1)

      expect(mockAxios.post).toHaveBeenCalledWith('/apps/door_estimator/api/quotes/1/pdf')
      expect(result).toEqual(mockPdfResult)
    })
  })

  describe('Markup Operations', () => {
    it('gets markup defaults', async () => {
      const mockMarkups = {
        doors: 15,
        frames: 12,
        hardware: 18
      }
      mockAxios.get.mockResolvedValue({ data: mockMarkups })

      const result = await EstimatorApi.getMarkupDefaults()

      expect(mockAxios.get).toHaveBeenCalledWith('/apps/door_estimator/api/markups')
      expect(result).toEqual(mockMarkups)
    })

    it('updates markup defaults', async () => {
      const markups = {
        doors: 20,
        frames: 15,
        hardware: 22
      }
      mockAxios.post.mockResolvedValue({ data: { success: true } })

      const result = await EstimatorApi.updateMarkupDefaults(markups)

      expect(mockAxios.post).toHaveBeenCalledWith('/apps/door_estimator/api/markups', { markups })
      expect(result).toEqual({ success: true })
    })
  })

  describe('Import/Export Operations', () => {
    it('imports pricing data from file', async () => {
      const file = new File(['{"pricingData": [], "markups": {}}'], 'test.json', {
        type: 'application/json'
      })
      const mockResult = {
        success: true,
        imported: 10,
        errors: []
      }
      mockAxios.post.mockResolvedValue({ data: mockResult })

      const result = await EstimatorApi.importPricingData(file)

      expect(mockAxios.post).toHaveBeenCalledWith(
        '/apps/door_estimator/api/import',
        expect.any(FormData),
        expect.objectContaining({
          headers: expect.objectContaining({
            'Content-Type': 'multipart/form-data'
          })
        })
      )
      expect(result).toEqual(mockResult)
    })

    it('exports pricing data', async () => {
      const mockExportData = {
        pricingData: [{ id: 1, item: 'Door A', price: 100 }],
        markups: { doors: 15 },
        exportedAt: '2024-01-01T12:00:00Z'
      }
      mockAxios.get.mockResolvedValue({ data: mockExportData })

      const result = await EstimatorApi.exportPricingData()

      expect(mockAxios.get).toHaveBeenCalledWith('/apps/door_estimator/api/export')
      expect(result).toEqual(mockExportData)
    })
  })

  describe('Error Handling', () => {
    it('handles network errors', async () => {
      const networkError = new Error('Network Error')
      mockAxios.get.mockRejectedValue(networkError)

      await expect(EstimatorApi.getAllPricingData()).rejects.toThrow('Network Error')
    })

    it('handles HTTP error responses', async () => {
      const httpError = {
        response: {
          status: 404,
          data: { error: 'Not found' }
        }
      }
      mockAxios.get.mockRejectedValue(httpError)

      await expect(EstimatorApi.getQuote(999)).rejects.toEqual(httpError)
    })

    it('handles server errors gracefully', async () => {
      const serverError = {
        response: {
          status: 500,
          data: { error: 'Internal server error' }
        }
      }
      mockAxios.post.mockRejectedValue(serverError)

      await expect(EstimatorApi.saveQuote({}, {})).rejects.toEqual(serverError)
    })

    it('handles timeout errors', async () => {
      const timeoutError = new Error('timeout of 5000ms exceeded')
      mockAxios.post.mockRejectedValue(timeoutError)

      await expect(EstimatorApi.importPricingData(new File([], 'test.json'))).rejects.toThrow('timeout')
    })
  })

  describe('Request Configuration', () => {
    it('sets correct headers for JSON requests', async () => {
      mockAxios.post.mockResolvedValue({ data: { success: true } })

      await EstimatorApi.updatePricingItem({ id: 1, item: 'Test', price: 100 })

      expect(mockAxios.post).toHaveBeenCalledWith(
        expect.any(String),
        expect.any(Object),
        expect.objectContaining({
          headers: expect.objectContaining({
            'Content-Type': 'application/json'
          })
        })
      )
    })

    it('sets correct headers for file uploads', async () => {
      const file = new File(['test'], 'test.json')
      mockAxios.post.mockResolvedValue({ data: { success: true } })

      await EstimatorApi.importPricingData(file)

      expect(mockAxios.post).toHaveBeenCalledWith(
        expect.any(String),
        expect.any(FormData),
        expect.objectContaining({
          headers: expect.objectContaining({
            'Content-Type': 'multipart/form-data'
          })
        })
      )
    })

    it('includes CSRF token in requests', async () => {
      // Mock CSRF token
      const mockToken = 'mock-csrf-token'
      Object.defineProperty(document, 'querySelector', {
        value: jest.fn().mockReturnValue({ content: mockToken }),
        writable: true
      })

      mockAxios.post.mockResolvedValue({ data: { success: true } })

      await EstimatorApi.updatePricingItem({ id: 1, item: 'Test', price: 100 })

      expect(mockAxios.post).toHaveBeenCalledWith(
        expect.any(String),
        expect.any(Object),
        expect.objectContaining({
          headers: expect.objectContaining({
            'X-CSRF-Token': mockToken
          })
        })
      )
    })
  })

  describe('Response Processing', () => {
    it('extracts data from successful responses', async () => {
      const responseData = { items: [{ id: 1, name: 'Test' }] }
      mockAxios.get.mockResolvedValue({ 
        data: responseData,
        status: 200,
        statusText: 'OK'
      })

      const result = await EstimatorApi.getAllPricingData()

      expect(result).toEqual(responseData)
    })

    it('handles empty responses', async () => {
      mockAxios.get.mockResolvedValue({ data: null })

      const result = await EstimatorApi.getAllPricingData()

      expect(result).toBeNull()
    })

    it('preserves response metadata when needed', async () => {
      const mockResponse = {
        data: { items: [] },
        status: 200,
        headers: { 'x-total-count': '0' }
      }
      mockAxios.get.mockResolvedValue(mockResponse)

      // For operations that need full response
      const result = await EstimatorApi.searchPricing('test', null, 10)

      expect(result).toEqual({ items: [] })
    })
  })

  describe('Request Validation', () => {
    it('validates required parameters', async () => {
      await expect(EstimatorApi.lookupPrice('', '')).rejects.toThrow('Category and item are required')
    })

    it('validates file types for upload', async () => {
      const invalidFile = new File(['test'], 'test.txt', { type: 'text/plain' })

      await expect(EstimatorApi.importPricingData(invalidFile)).rejects.toThrow('Invalid file type')
    })

    it('validates quote data structure', async () => {
      const invalidQuoteData = null

      await expect(EstimatorApi.saveQuote(invalidQuoteData, {})).rejects.toThrow('Quote data is required')
    })

    it('validates markup values', async () => {
      const invalidMarkups = { doors: -5 }

      await expect(EstimatorApi.updateMarkupDefaults(invalidMarkups)).rejects.toThrow('Markup values must be non-negative')
    })
  })
})