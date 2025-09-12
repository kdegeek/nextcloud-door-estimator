/**
 * Custom Jest matchers for Door Estimator testing
 */

declare global {
  namespace jest {
    interface Matchers<R> {
      toBeValidPrice(): R
      toBeValidQuantity(): R
      toBeValidQuoteItem(): R
      toHaveValidMarkup(): R
      toBeAccessible(): R
      toHavePerformantResponse(): R
      toBeSecureInput(): R
    }
  }
}

// Custom matcher for valid price values
expect.extend({
  toBeValidPrice(received: any) {
    const pass = typeof received === 'number' && 
                 received > 0 && 
                 received < Number.MAX_SAFE_INTEGER &&
                 !isNaN(received) &&
                 isFinite(received)
    
    if (pass) {
      return {
        message: () => `expected ${received} not to be a valid price`,
        pass: true,
      }
    } else {
      return {
        message: () => `expected ${received} to be a valid price (positive number)`,
        pass: false,
      }
    }
  },
})

// Custom matcher for valid quantity values
expect.extend({
  toBeValidQuantity(received: any) {
    const pass = typeof received === 'number' && 
                 received > 0 && 
                 Number.isInteger(received) &&
                 received <= 10000 // Reasonable upper limit
    
    if (pass) {
      return {
        message: () => `expected ${received} not to be a valid quantity`,
        pass: true,
      }
    } else {
      return {
        message: () => `expected ${received} to be a valid quantity (positive integer)`,
        pass: false,
      }
    }
  },
})

// Custom matcher for valid quote items
expect.extend({
  toBeValidQuoteItem(received: any) {
    const isValid = received &&
                   typeof received === 'object' &&
                   typeof received.id === 'string' &&
                   received.id.length > 0 &&
                   typeof received.item === 'string' &&
                   received.item.length > 0 &&
                   typeof received.qty === 'number' &&
                   received.qty > 0 &&
                   typeof received.price === 'number' &&
                   received.price > 0 &&
                   typeof received.total === 'number' &&
                   received.total > 0 &&
                   Math.abs(received.total - (received.qty * received.price)) < 0.01
    
    if (isValid) {
      return {
        message: () => `expected quote item not to be valid`,
        pass: true,
      }
    } else {
      return {
        message: () => `expected quote item to be valid (must have id, item, qty, price, total with correct calculation)`,
        pass: false,
      }
    }
  },
})

// Custom matcher for valid markup values
expect.extend({
  toHaveValidMarkup(received: any) {
    const isValid = typeof received === 'number' &&
                   received >= 0 &&
                   received <= 500 && // Max 500% markup
                   !isNaN(received) &&
                   isFinite(received)
    
    if (isValid) {
      return {
        message: () => `expected ${received} not to be a valid markup`,
        pass: true,
      }
    } else {
      return {
        message: () => `expected ${received} to be a valid markup (0-500%)`,
        pass: false,
      }
    }
  },
})

// Custom matcher for accessibility compliance
expect.extend({
  toBeAccessible(received: any) {
    if (!received || !received.element) {
      return {
        message: () => `expected element to exist for accessibility check`,
        pass: false,
      }
    }
    
    const element = received.element
    const issues = []
    
    // Check for ARIA labels
    const interactiveElements = element.querySelectorAll('button, input, select, textarea, a[href]')
    interactiveElements.forEach((el: Element) => {
      if (!el.getAttribute('aria-label') && 
          !el.getAttribute('aria-labelledby') && 
          !el.textContent?.trim()) {
        issues.push(`Interactive element missing accessible label: ${el.tagName}`)
      }
    })
    
    // Check for proper heading hierarchy
    const headings = element.querySelectorAll('h1, h2, h3, h4, h5, h6')
    let lastLevel = 0
    headings.forEach((heading: Element) => {
      const level = parseInt(heading.tagName.charAt(1))
      if (level > lastLevel + 1) {
        issues.push(`Heading hierarchy skip detected: ${heading.tagName} after h${lastLevel}`)
      }
      lastLevel = level
    })
    
    // Check for alt text on images
    const images = element.querySelectorAll('img')
    images.forEach((img: Element) => {
      if (!img.getAttribute('alt')) {
        issues.push(`Image missing alt text: ${img.getAttribute('src')}`)
      }
    })
    
    // Check for form labels
    const inputs = element.querySelectorAll('input, select, textarea')
    inputs.forEach((input: Element) => {
      const id = input.getAttribute('id')
      if (id) {
        const label = element.querySelector(`label[for="${id}"]`)
        if (!label && !input.getAttribute('aria-label')) {
          issues.push(`Form input missing label: ${input.getAttribute('type') || input.tagName}`)
        }
      }
    })
    
    const pass = issues.length === 0
    
    if (pass) {
      return {
        message: () => `expected element not to be accessible`,
        pass: true,
      }
    } else {
      return {
        message: () => `expected element to be accessible. Issues found:\n${issues.join('\n')}`,
        pass: false,
      }
    }
  },
})

// Custom matcher for performance requirements
expect.extend({
  toHavePerformantResponse(received: any) {
    const maxResponseTime = 1000 // 1 second
    const responseTime = received?.responseTime || received
    
    const pass = typeof responseTime === 'number' && 
                 responseTime < maxResponseTime
    
    if (pass) {
      return {
        message: () => `expected response time ${responseTime}ms not to be performant`,
        pass: true,
      }
    } else {
      return {
        message: () => `expected response time ${responseTime}ms to be under ${maxResponseTime}ms`,
        pass: false,
      }
    }
  },
})

// Custom matcher for secure input validation
expect.extend({
  toBeSecureInput(received: any) {
    if (typeof received !== 'string') {
      return {
        message: () => `expected input to be a string`,
        pass: false,
      }
    }
    
    const securityIssues = []
    
    // Check for XSS patterns
    const xssPatterns = [
      /<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi,
      /javascript:/gi,
      /on\w+\s*=/gi,
      /<iframe\b[^<]*(?:(?!<\/iframe>)<[^<]*)*<\/iframe>/gi,
      /<object\b[^<]*(?:(?!<\/object>)<[^<]*)*<\/object>/gi,
      /<embed\b[^>]*>/gi
    ]
    
    xssPatterns.forEach(pattern => {
      if (pattern.test(received)) {
        securityIssues.push('Potential XSS pattern detected')
      }
    })
    
    // Check for SQL injection patterns
    const sqlPatterns = [
      /('|(\\')|(;)|(\\;)|(--)|(\s)|(\||(\*)|(%))|(union)|(select)|(insert)|(delete)|(update)|(drop)|(create)|(alter)|(exec)|(execute)/gi
    ]
    
    sqlPatterns.forEach(pattern => {
      if (pattern.test(received)) {
        securityIssues.push('Potential SQL injection pattern detected')
      }
    })
    
    // Check for path traversal
    if (received.includes('../') || received.includes('..\\')) {
      securityIssues.push('Path traversal pattern detected')
    }
    
    // Check for command injection
    const commandPatterns = [
      /[;&|`$(){}[\]]/g
    ]
    
    commandPatterns.forEach(pattern => {
      if (pattern.test(received)) {
        securityIssues.push('Potential command injection pattern detected')
      }
    })
    
    const pass = securityIssues.length === 0
    
    if (pass) {
      return {
        message: () => `expected input not to be secure`,
        pass: true,
      }
    } else {
      return {
        message: () => `expected input to be secure. Issues found:\n${securityIssues.join('\n')}`,
        pass: false,
      }
    }
  },
})

export {}