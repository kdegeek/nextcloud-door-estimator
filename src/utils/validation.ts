/**
 * Comprehensive client-side validation utilities with accessibility support
 * Provides real-time validation for forms and user input with screen reader support
 */

export interface ValidationRule {
  required?: boolean
  minLength?: number
  maxLength?: number
  min?: number
  max?: number
  pattern?: RegExp
  custom?: (value: any) => string | null
  accessibility?: {
    label?: string
    description?: string
    errorPrefix?: string
  }
}

export interface ValidationResult {
  isValid: boolean
  errors: string[]
  warnings?: string[]
  fieldId?: string
  ariaDescribedBy?: string
}

export interface FieldValidationOptions {
  fieldId?: string
  label?: string
  liveValidation?: boolean
  announceErrors?: boolean
}

export class InputValidator {
  /**
   * Validate a single field against rules with accessibility support
   */
  static validateField(
    value: any, 
    rules: ValidationRule, 
    options: FieldValidationOptions = {}
  ): ValidationResult {
    const errors: string[] = []
    const warnings: string[] = []
    const { label, fieldId } = options
    const accessibilityLabel = rules.accessibility?.label || label

    // Required validation
    if (rules.required && this.isEmpty(value)) {
      const message = accessibilityLabel 
        ? `${accessibilityLabel} is required`
        : 'This field is required'
      errors.push(message)
      return { 
        isValid: false, 
        errors, 
        warnings,
        fieldId,
        ariaDescribedBy: fieldId ? `${fieldId}-error` : undefined
      }
    }

    // Skip other validations if field is empty and not required
    if (this.isEmpty(value) && !rules.required) {
      return { isValid: true, errors: [], warnings, fieldId }
    }

    // String validations
    if (typeof value === 'string') {
      if (rules.minLength && value.length < rules.minLength) {
        const message = accessibilityLabel
          ? `${accessibilityLabel} must be at least ${rules.minLength} characters`
          : `Must be at least ${rules.minLength} characters`
        errors.push(message)
      }
      if (rules.maxLength && value.length > rules.maxLength) {
        const message = accessibilityLabel
          ? `${accessibilityLabel} must not exceed ${rules.maxLength} characters`
          : `Must not exceed ${rules.maxLength} characters`
        errors.push(message)
      }
      if (rules.pattern && !rules.pattern.test(value)) {
        const message = accessibilityLabel
          ? `${accessibilityLabel} has an invalid format`
          : 'Invalid format'
        errors.push(message)
      }

      // Accessibility warnings for string length
      if (rules.maxLength && value.length > rules.maxLength * 0.8) {
        warnings.push(`Approaching character limit (${value.length}/${rules.maxLength})`)
      }
    }

    // Numeric validations
    if (typeof value === 'number' || (typeof value === 'string' && !isNaN(Number(value)))) {
      const numValue = Number(value)
      if (rules.min !== undefined && numValue < rules.min) {
        const message = accessibilityLabel
          ? `${accessibilityLabel} must be at least ${rules.min}`
          : `Must be at least ${rules.min}`
        errors.push(message)
      }
      if (rules.max !== undefined && numValue > rules.max) {
        const message = accessibilityLabel
          ? `${accessibilityLabel} must not exceed ${rules.max}`
          : `Must not exceed ${rules.max}`
        errors.push(message)
      }

      // Accessibility warnings for number ranges
      if (rules.max !== undefined && numValue > rules.max * 0.9) {
        warnings.push(`Approaching maximum value (${numValue}/${rules.max})`)
      }
    }

    // Custom validation
    if (rules.custom) {
      const customError = rules.custom(value)
      if (customError) {
        const prefix = rules.accessibility?.errorPrefix || ''
        errors.push(prefix + customError)
      }
    }

    return {
      isValid: errors.length === 0,
      errors,
      warnings,
      fieldId,
      ariaDescribedBy: fieldId && errors.length > 0 ? `${fieldId}-error` : undefined
    }
  }

  /**
   * Validate multiple fields
   */
  static validateFields(data: Record<string, any>, rules: Record<string, ValidationRule>): Record<string, ValidationResult> {
    const results: Record<string, ValidationResult> = {}
    
    for (const [field, fieldRules] of Object.entries(rules)) {
      results[field] = this.validateField(data[field], fieldRules)
    }

    return results
  }

  /**
   * Check if value is empty
   */
  private static isEmpty(value: any): boolean {
    if (value === null || value === undefined) return true
    if (typeof value === 'string') return value.trim() === ''
    if (Array.isArray(value)) return value.length === 0
    if (typeof value === 'object') return Object.keys(value).length === 0
    return false
  }

  /**
   * Sanitize string input to prevent XSS
   */
  static sanitizeString(input: string): string {
    if (typeof input !== 'string') return ''
    
    // Remove HTML tags
    const withoutTags = input.replace(/<[^>]*>/g, '')
    
    // Remove potentially dangerous characters
    const sanitized = withoutTags
      .replace(/[<>'"&]/g, (match) => {
        const entities: Record<string, string> = {
          '<': '&lt;',
          '>': '&gt;',
          '"': '&quot;',
          "'": '&#x27;',
          '&': '&amp;'
        }
        return entities[match] || match
      })
      .replace(/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/g, '') // Remove control characters
    
    return sanitized.trim()
  }

  /**
   * Validate pricing item data
   */
  static validatePricingItem(item: any): ValidationResult {
    const errors: string[] = []

    // Validate item name
    if (!item.item || typeof item.item !== 'string') {
      errors.push('Item name is required')
    } else {
      const sanitized = this.sanitizeString(item.item)
      if (sanitized.length < 2) {
        errors.push('Item name must be at least 2 characters')
      }
      if (sanitized.length > 255) {
        errors.push('Item name must not exceed 255 characters')
      }
      if (!/^[a-zA-Z0-9\s\-_\.\(\)\/]+$/.test(sanitized)) {
        errors.push('Item name contains invalid characters')
      }
    }

    // Validate price
    if (item.price === null || item.price === undefined || item.price === '') {
      errors.push('Price is required')
    } else {
      const price = Number(item.price)
      if (isNaN(price)) {
        errors.push('Price must be a valid number')
      } else if (price < 0) {
        errors.push('Price cannot be negative')
      } else if (price > 1000000) {
        errors.push('Price cannot exceed $1,000,000')
      } else if (price > 0 && price < 0.01) {
        errors.push('Price must be at least $0.01 if not zero')
      }
    }

    // Validate category
    if (!item.category || typeof item.category !== 'string') {
      errors.push('Category is required')
    } else {
      const validCategories = [
        'doors', 'doorOptions', 'inserts', 'frames', 'frameOptions',
        'hinges', 'weatherstrip', 'closers', 'locksets', 'exitDevices', 'hardware'
      ]
      if (!validCategories.includes(item.category)) {
        errors.push('Invalid category selected')
      }
    }

    return {
      isValid: errors.length === 0,
      errors
    }
  }

  /**
   * Validate quote line item
   */
  static validateQuoteLineItem(item: any): ValidationResult {
    const errors: string[] = []

    // Validate item name
    if (!item.item || typeof item.item !== 'string') {
      errors.push('Item name is required')
    } else {
      const sanitized = this.sanitizeString(item.item)
      if (sanitized.length > 255) {
        errors.push('Item name too long')
      }
    }

    // Validate quantity
    if (item.qty === null || item.qty === undefined || item.qty === '') {
      errors.push('Quantity is required')
    } else {
      const qty = Number(item.qty)
      if (isNaN(qty) || !Number.isInteger(qty)) {
        errors.push('Quantity must be a whole number')
      } else if (qty < 0) {
        errors.push('Quantity cannot be negative')
      } else if (qty > 10000) {
        errors.push('Quantity cannot exceed 10,000')
      }
    }

    // Validate price
    if (item.price === null || item.price === undefined || item.price === '') {
      errors.push('Price is required')
    } else {
      const price = Number(item.price)
      if (isNaN(price)) {
        errors.push('Price must be a valid number')
      } else if (price < 0) {
        errors.push('Price cannot be negative')
      } else if (price > 1000000) {
        errors.push('Price cannot exceed $1,000,000')
      }
    }

    return {
      isValid: errors.length === 0,
      errors
    }
  }

  /**
   * Validate markup percentage
   */
  static validateMarkup(markup: any): ValidationResult {
    const errors: string[] = []

    if (markup === null || markup === undefined || markup === '') {
      errors.push('Markup is required')
    } else {
      const markupValue = Number(markup)
      if (isNaN(markupValue)) {
        errors.push('Markup must be a valid number')
      } else if (markupValue < 0) {
        errors.push('Markup cannot be negative')
      } else if (markupValue > 100) {
        errors.push('Markup cannot exceed 100%')
      }
    }

    return {
      isValid: errors.length === 0,
      errors
    }
  }

  /**
   * Validate file upload
   */
  static validateFileUpload(file: File): ValidationResult {
    const errors: string[] = []

    // Check file size (5MB limit)
    const maxSize = 5 * 1024 * 1024
    if (file.size > maxSize) {
      errors.push('File size cannot exceed 5MB')
    }

    if (file.size === 0) {
      errors.push('File is empty')
    }

    // Check file type
    const allowedTypes = [
      'application/json',
      'text/csv',
      'application/vnd.ms-excel',
      'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    ]
    
    const allowedExtensions = ['json', 'csv', 'xls', 'xlsx']
    const extension = file.name.split('.').pop()?.toLowerCase()

    if (!allowedTypes.includes(file.type) && !allowedExtensions.includes(extension || '')) {
      errors.push('Only JSON, CSV, and Excel files are allowed')
    }

    // Check filename security
    if (file.name.includes('..') || file.name.includes('/') || file.name.includes('\\')) {
      errors.push('Invalid filename')
    }

    if (file.name.length > 255) {
      errors.push('Filename too long')
    }

    // Check for dangerous extensions
    const dangerousExtensions = [
      'php', 'exe', 'bat', 'cmd', 'scr', 'vbs', 'js', 'jar', 'sh', 'py'
    ]
    if (dangerousExtensions.includes(extension || '')) {
      errors.push('File type not allowed for security reasons')
    }

    return {
      isValid: errors.length === 0,
      errors
    }
  }

  /**
   * Validate quote name
   */
  static validateQuoteName(name: string): ValidationResult {
    const errors: string[] = []

    if (name && typeof name === 'string') {
      const sanitized = this.sanitizeString(name)
      if (sanitized.length > 255) {
        errors.push('Quote name must not exceed 255 characters')
      }
    }

    return {
      isValid: errors.length === 0,
      errors
    }
  }

  /**
   * Validate customer info
   */
  static validateCustomerInfo(info: any): ValidationResult {
    const errors: string[] = []

    if (info && typeof info === 'object') {
      // Validate name
      if (info.name && typeof info.name === 'string') {
        const sanitized = this.sanitizeString(info.name)
        if (sanitized.length > 100) {
          errors.push('Customer name must not exceed 100 characters')
        }
      }

      // Validate email
      if (info.email && typeof info.email === 'string') {
        const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
        if (!emailPattern.test(info.email)) {
          errors.push('Invalid email format')
        }
        if (info.email.length > 255) {
          errors.push('Email must not exceed 255 characters')
        }
      }

      // Validate phone
      if (info.phone && typeof info.phone === 'string') {
        const phonePattern = /^[\d\s\-\(\)\+\.]+$/
        if (!phonePattern.test(info.phone)) {
          errors.push('Invalid phone number format')
        }
        if (info.phone.length > 20) {
          errors.push('Phone number must not exceed 20 characters')
        }
      }

      // Validate company
      if (info.company && typeof info.company === 'string') {
        const sanitized = this.sanitizeString(info.company)
        if (sanitized.length > 255) {
          errors.push('Company name must not exceed 255 characters')
        }
      }
    }

    return {
      isValid: errors.length === 0,
      errors
    }
  }
}

// Export validation rules for common use cases
export const ValidationRules = {
  itemName: {
    required: true,
    minLength: 2,
    maxLength: 255,
    pattern: /^[a-zA-Z0-9\s\-_\.\(\)\/]+$/
  },
  price: {
    required: true,
    min: 0,
    max: 1000000,
    custom: (value: any) => {
      const num = Number(value)
      if (num > 0 && num < 0.01) {
        return 'Price must be at least $0.01 if not zero'
      }
      return null
    }
  },
  quantity: {
    required: true,
    min: 0,
    max: 10000,
    custom: (value: any) => {
      const num = Number(value)
      if (!Number.isInteger(num)) {
        return 'Quantity must be a whole number'
      }
      return null
    }
  },
  markup: {
    required: true,
    min: 0,
    max: 100
  },
  quoteName: {
    maxLength: 255
  },
  customerName: {
    maxLength: 100
  },
  customerEmail: {
    maxLength: 255,
    pattern: /^[^\s@]+@[^\s@]+\.[^\s@]+$/
  },
  customerPhone: {
    maxLength: 20,
    pattern: /^[\d\s\-\(\)\+\.]+$/
  },
  customerCompany: {
    maxLength: 255
  }
} as const