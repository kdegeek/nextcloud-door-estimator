import {
  validateQuoteItem,
  validatePricingItem,
  validateMarkup,
  sanitizeInput,
  validateFileUpload,
  validateQuoteData,
  isValidPrice,
  isValidQuantity,
  isValidItemName
} from '../../../src/utils/validation'

describe('Validation Utilities', () => {
  describe('validateQuoteItem', () => {
    it('validates valid quote item', () => {
      const validItem = {
        id: '1',
        item: 'Test Door',
        qty: 2,
        price: 100.50,
        total: 201.00
      }

      const result = validateQuoteItem(validItem)
      expect(result.isValid).toBe(true)
      expect(result.errors).toHaveLength(0)
    })

    it('validates item with frame type', () => {
      const frameItem = {
        id: '1',
        item: 'Test Frame',
        qty: 1,
        price: 75.00,
        total: 75.00,
        frameType: 'HM EWA'
      }

      const result = validateQuoteItem(frameItem)
      expect(result.isValid).toBe(true)
      expect(result.errors).toHaveLength(0)
    })

    it('rejects item with missing required fields', () => {
      const invalidItem = {
        id: '1',
        item: '',
        qty: 0,
        price: 0
      }

      const result = validateQuoteItem(invalidItem)
      expect(result.isValid).toBe(false)
      expect(result.errors).toContain('Item name is required')
      expect(result.errors).toContain('Quantity must be greater than 0')
      expect(result.errors).toContain('Price must be greater than 0')
    })

    it('rejects item with invalid data types', () => {
      const invalidItem = {
        id: '1',
        item: 'Test Door',
        qty: 'invalid',
        price: 'not-a-number',
        total: null
      }

      const result = validateQuoteItem(invalidItem)
      expect(result.isValid).toBe(false)
      expect(result.errors).toContain('Quantity must be a number')
      expect(result.errors).toContain('Price must be a number')
    })

    it('rejects item with negative values', () => {
      const invalidItem = {
        id: '1',
        item: 'Test Door',
        qty: -1,
        price: -50,
        total: -50
      }

      const result = validateQuoteItem(invalidItem)
      expect(result.isValid).toBe(false)
      expect(result.errors).toContain('Quantity cannot be negative')
      expect(result.errors).toContain('Price cannot be negative')
    })

    it('validates total calculation', () => {
      const itemWithWrongTotal = {
        id: '1',
        item: 'Test Door',
        qty: 2,
        price: 100,
        total: 150 // Should be 200
      }

      const result = validateQuoteItem(itemWithWrongTotal)
      expect(result.isValid).toBe(false)
      expect(result.errors).toContain('Total does not match quantity × price')
    })

    it('handles decimal precision correctly', () => {
      const itemWithDecimals = {
        id: '1',
        item: 'Test Door',
        qty: 3,
        price: 33.33,
        total: 99.99
      }

      const result = validateQuoteItem(itemWithDecimals)
      expect(result.isValid).toBe(true)
    })
  })

  describe('validatePricingItem', () => {
    it('validates valid pricing item', () => {
      const validItem = {
        id: 1,
        category: 'doors',
        item: 'Test Door',
        price: 100.00,
        stock_status: 'stock'
      }

      const result = validatePricingItem(validItem)
      expect(result.isValid).toBe(true)
      expect(result.errors).toHaveLength(0)
    })

    it('validates pricing item with subcategory', () => {
      const itemWithSubcategory = {
        id: 1,
        category: 'frames',
        subcategory: 'HM EWA',
        item: 'Test Frame',
        price: 75.00,
        stock_status: 'stock'
      }

      const result = validatePricingItem(itemWithSubcategory)
      expect(result.isValid).toBe(true)
    })

    it('rejects pricing item with invalid category', () => {
      const invalidItem = {
        id: 1,
        category: 'invalid-category',
        item: 'Test Item',
        price: 100.00
      }

      const result = validatePricingItem(invalidItem)
      expect(result.isValid).toBe(false)
      expect(result.errors).toContain('Invalid category')
    })

    it('rejects pricing item with missing required fields', () => {
      const invalidItem = {
        id: 1,
        category: '',
        item: '',
        price: null
      }

      const result = validatePricingItem(invalidItem)
      expect(result.isValid).toBe(false)
      expect(result.errors).toContain('Category is required')
      expect(result.errors).toContain('Item name is required')
      expect(result.errors).toContain('Price is required')
    })

    it('validates stock status values', () => {
      const validStatuses = ['stock', 'special-order', 'discontinued']
      
      validStatuses.forEach(status => {
        const item = {
          id: 1,
          category: 'doors',
          item: 'Test Door',
          price: 100.00,
          stock_status: status
        }

        const result = validatePricingItem(item)
        expect(result.isValid).toBe(true)
      })

      const invalidItem = {
        id: 1,
        category: 'doors',
        item: 'Test Door',
        price: 100.00,
        stock_status: 'invalid-status'
      }

      const result = validatePricingItem(invalidItem)
      expect(result.isValid).toBe(false)
      expect(result.errors).toContain('Invalid stock status')
    })
  })

  describe('validateMarkup', () => {
    it('validates valid markup percentage', () => {
      const result = validateMarkup(15.5)
      expect(result.isValid).toBe(true)
      expect(result.errors).toHaveLength(0)
    })

    it('accepts zero markup', () => {
      const result = validateMarkup(0)
      expect(result.isValid).toBe(true)
    })

    it('rejects negative markup', () => {
      const result = validateMarkup(-5)
      expect(result.isValid).toBe(false)
      expect(result.errors).toContain('Markup cannot be negative')
    })

    it('rejects non-numeric markup', () => {
      const result = validateMarkup('invalid')
      expect(result.isValid).toBe(false)
      expect(result.errors).toContain('Markup must be a number')
    })

    it('rejects extremely high markup', () => {
      const result = validateMarkup(1000)
      expect(result.isValid).toBe(false)
      expect(result.errors).toContain('Markup cannot exceed 500%')
    })

    it('validates markup object', () => {
      const markups = {
        doors: 15,
        frames: 12,
        hardware: 18
      }

      const result = validateMarkup(markups)
      expect(result.isValid).toBe(true)
    })

    it('rejects markup object with invalid values', () => {
      const markups = {
        doors: 15,
        frames: -5,
        hardware: 'invalid'
      }

      const result = validateMarkup(markups)
      expect(result.isValid).toBe(false)
      expect(result.errors).toContain('frames: Markup cannot be negative')
      expect(result.errors).toContain('hardware: Markup must be a number')
    })
  })

  describe('sanitizeInput', () => {
    it('removes HTML tags', () => {
      const input = '<script>alert("xss")</script>Clean text'
      const result = sanitizeInput(input)
      expect(result).toBe('Clean text')
      expect(result).not.toContain('<script>')
    })

    it('removes dangerous attributes', () => {
      const input = '<div onclick="malicious()">Content</div>'
      const result = sanitizeInput(input)
      expect(result).toBe('Content')
      expect(result).not.toContain('onclick')
    })

    it('preserves safe content', () => {
      const input = 'Normal text with numbers 123 and symbols !@#'
      const result = sanitizeInput(input)
      expect(result).toBe(input)
    })

    it('trims whitespace', () => {
      const input = '  Trimmed text  '
      const result = sanitizeInput(input)
      expect(result).toBe('Trimmed text')
    })

    it('handles empty input', () => {
      expect(sanitizeInput('')).toBe('')
      expect(sanitizeInput(null)).toBe('')
      expect(sanitizeInput(undefined)).toBe('')
    })

    it('limits input length', () => {
      const longInput = 'a'.repeat(1000)
      const result = sanitizeInput(longInput, { maxLength: 100 })
      expect(result.length).toBe(100)
    })

    it('normalizes line endings', () => {
      const input = 'Line 1\r\nLine 2\rLine 3\nLine 4'
      const result = sanitizeInput(input)
      expect(result).toBe('Line 1\nLine 2\nLine 3\nLine 4')
    })
  })

  describe('validateFileUpload', () => {
    it('validates valid JSON file', () => {
      const file = new File(['{"test": "data"}'], 'test.json', {
        type: 'application/json'
      })

      const result = validateFileUpload(file)
      expect(result.isValid).toBe(true)
      expect(result.errors).toHaveLength(0)
    })

    it('validates valid CSV file', () => {
      const file = new File(['header1,header2\nvalue1,value2'], 'test.csv', {
        type: 'text/csv'
      })

      const result = validateFileUpload(file)
      expect(result.isValid).toBe(true)
    })

    it('validates valid Excel file', () => {
      const file = new File(['excel content'], 'test.xlsx', {
        type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
      })

      const result = validateFileUpload(file)
      expect(result.isValid).toBe(true)
    })

    it('rejects unsupported file types', () => {
      const file = new File(['content'], 'test.txt', {
        type: 'text/plain'
      })

      const result = validateFileUpload(file)
      expect(result.isValid).toBe(false)
      expect(result.errors).toContain('Unsupported file type')
    })

    it('rejects files that are too large', () => {
      const largeContent = 'x'.repeat(6 * 1024 * 1024) // 6MB
      const file = new File([largeContent], 'large.json', {
        type: 'application/json'
      })

      const result = validateFileUpload(file)
      expect(result.isValid).toBe(false)
      expect(result.errors).toContain('File size exceeds 5MB limit')
    })

    it('rejects empty files', () => {
      const file = new File([''], 'empty.json', {
        type: 'application/json'
      })

      const result = validateFileUpload(file)
      expect(result.isValid).toBe(false)
      expect(result.errors).toContain('File is empty')
    })

    it('validates file extension matches MIME type', () => {
      const file = new File(['content'], 'test.json', {
        type: 'text/plain'
      })

      const result = validateFileUpload(file)
      expect(result.isValid).toBe(false)
      expect(result.errors).toContain('File extension does not match content type')
    })
  })

  describe('validateQuoteData', () => {
    it('validates complete quote data structure', () => {
      const quoteData = {
        doors: [
          { id: '1', item: 'Door A', qty: 1, price: 100, total: 100 }
        ],
        frames: [
          { id: '1', item: 'Frame A', qty: 1, price: 50, total: 50, frameType: 'HM EWA' }
        ],
        hardware: []
      }

      const result = validateQuoteData(quoteData)
      expect(result.isValid).toBe(true)
      expect(result.errors).toHaveLength(0)
    })

    it('validates empty quote sections', () => {
      const quoteData = {
        doors: [],
        frames: [],
        hardware: []
      }

      const result = validateQuoteData(quoteData)
      expect(result.isValid).toBe(true)
    })

    it('rejects quote data with invalid items', () => {
      const quoteData = {
        doors: [
          { id: '1', item: '', qty: -1, price: 'invalid', total: 0 }
        ]
      }

      const result = validateQuoteData(quoteData)
      expect(result.isValid).toBe(false)
      expect(result.errors.length).toBeGreaterThan(0)
    })

    it('validates all required sections exist', () => {
      const incompleteQuoteData = {
        doors: []
        // Missing other sections
      }

      const result = validateQuoteData(incompleteQuoteData)
      expect(result.isValid).toBe(false)
      expect(result.errors).toContain('Missing required sections')
    })
  })

  describe('Helper Functions', () => {
    describe('isValidPrice', () => {
      it('validates positive numbers', () => {
        expect(isValidPrice(100)).toBe(true)
        expect(isValidPrice(0.01)).toBe(true)
        expect(isValidPrice(999999.99)).toBe(true)
      })

      it('rejects invalid prices', () => {
        expect(isValidPrice(-1)).toBe(false)
        expect(isValidPrice(0)).toBe(false)
        expect(isValidPrice('100')).toBe(false)
        expect(isValidPrice(NaN)).toBe(false)
        expect(isValidPrice(Infinity)).toBe(false)
      })
    })

    describe('isValidQuantity', () => {
      it('validates positive integers', () => {
        expect(isValidQuantity(1)).toBe(true)
        expect(isValidQuantity(100)).toBe(true)
      })

      it('rejects invalid quantities', () => {
        expect(isValidQuantity(0)).toBe(false)
        expect(isValidQuantity(-1)).toBe(false)
        expect(isValidQuantity(1.5)).toBe(false)
        expect(isValidQuantity('1')).toBe(false)
        expect(isValidQuantity(NaN)).toBe(false)
      })
    })

    describe('isValidItemName', () => {
      it('validates non-empty strings', () => {
        expect(isValidItemName('Door A')).toBe(true)
        expect(isValidItemName('2-0 x 6-8 Door')).toBe(true)
      })

      it('rejects invalid item names', () => {
        expect(isValidItemName('')).toBe(false)
        expect(isValidItemName('   ')).toBe(false)
        expect(isValidItemName(null)).toBe(false)
        expect(isValidItemName(undefined)).toBe(false)
      })

      it('rejects names that are too long', () => {
        const longName = 'a'.repeat(256)
        expect(isValidItemName(longName)).toBe(false)
      })

      it('rejects names with dangerous content', () => {
        expect(isValidItemName('<script>alert("xss")</script>')).toBe(false)
        expect(isValidItemName('javascript:alert(1)')).toBe(false)
      })
    })
  })

  describe('Edge Cases', () => {
    it('handles null and undefined inputs gracefully', () => {
      expect(validateQuoteItem(null).isValid).toBe(false)
      expect(validatePricingItem(undefined).isValid).toBe(false)
      expect(validateMarkup(null).isValid).toBe(false)
      expect(sanitizeInput(null)).toBe('')
    })

    it('handles circular references in objects', () => {
      const circular = { a: 1 }
      circular.self = circular

      expect(() => validateQuoteData(circular)).not.toThrow()
    })

    it('handles very large numbers', () => {
      const largeNumber = Number.MAX_SAFE_INTEGER
      expect(isValidPrice(largeNumber)).toBe(true)
      expect(isValidQuantity(largeNumber)).toBe(true)
    })

    it('handles floating point precision issues', () => {
      const item = {
        id: '1',
        item: 'Test',
        qty: 3,
        price: 0.1,
        total: 0.3 // This might be 0.30000000000000004 due to floating point
      }

      const result = validateQuoteItem(item)
      expect(result.isValid).toBe(true) // Should handle precision tolerance
    })
  })
})