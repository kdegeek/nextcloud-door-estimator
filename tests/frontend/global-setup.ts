/**
 * Global setup for Jest tests
 * Runs once before all tests
 */

export default async function globalSetup(): Promise<void> {
  // Set up test environment variables
  process.env.NODE_ENV = 'test'
  process.env.VUE_APP_API_BASE_URL = 'http://localhost:3000'
  
  // Mock global objects that might not be available in test environment
  global.ResizeObserver = class ResizeObserver {
    observe() {}
    unobserve() {}
    disconnect() {}
  }
  
  // Mock IntersectionObserver
  global.IntersectionObserver = class IntersectionObserver {
    constructor() {}
    observe() {}
    unobserve() {}
    disconnect() {}
  }
  
  // Mock matchMedia
  Object.defineProperty(window, 'matchMedia', {
    writable: true,
    value: jest.fn().mockImplementation(query => ({
      matches: false,
      media: query,
      onchange: null,
      addListener: jest.fn(), // deprecated
      removeListener: jest.fn(), // deprecated
      addEventListener: jest.fn(),
      removeEventListener: jest.fn(),
      dispatchEvent: jest.fn(),
    })),
  })
  
  // Mock scrollTo
  Object.defineProperty(window, 'scrollTo', {
    value: jest.fn(),
    writable: true
  })
  
  // Mock requestAnimationFrame
  global.requestAnimationFrame = jest.fn(cb => setTimeout(cb, 0))
  global.cancelAnimationFrame = jest.fn(id => clearTimeout(id))
  
  // Mock performance API
  Object.defineProperty(window, 'performance', {
    value: {
      now: jest.fn(() => Date.now()),
      mark: jest.fn(),
      measure: jest.fn(),
      getEntriesByName: jest.fn(() => []),
      getEntriesByType: jest.fn(() => [])
    },
    writable: true
  })
  
  // Mock localStorage
  const localStorageMock = {
    getItem: jest.fn(),
    setItem: jest.fn(),
    removeItem: jest.fn(),
    clear: jest.fn(),
    length: 0,
    key: jest.fn()
  }
  Object.defineProperty(window, 'localStorage', {
    value: localStorageMock,
    writable: true
  })
  
  // Mock sessionStorage
  Object.defineProperty(window, 'sessionStorage', {
    value: localStorageMock,
    writable: true
  })
  
  // Mock URL.createObjectURL and revokeObjectURL
  Object.defineProperty(window.URL, 'createObjectURL', {
    value: jest.fn(() => 'blob:mock-url'),
    writable: true
  })
  Object.defineProperty(window.URL, 'revokeObjectURL', {
    value: jest.fn(),
    writable: true
  })
  
  // Mock fetch if not available
  if (!global.fetch) {
    global.fetch = jest.fn(() =>
      Promise.resolve({
        ok: true,
        status: 200,
        json: () => Promise.resolve({}),
        text: () => Promise.resolve(''),
        blob: () => Promise.resolve(new Blob()),
        headers: new Map()
      })
    ) as jest.Mock
  }
  
  // Mock console methods for cleaner test output
  const originalConsoleError = console.error
  console.error = (...args: any[]) => {
    // Filter out Vue warnings and other noise
    const message = args[0]
    if (
      typeof message === 'string' &&
      (message.includes('Vue warn') ||
       message.includes('[Vue warn]') ||
       message.includes('Download the Vue Devtools'))
    ) {
      return
    }
    originalConsoleError(...args)
  }
  
  // Set up test database if needed
  await setupTestDatabase()
  
  // Initialize test data
  await initializeTestData()
  
  console.log('Global test setup completed')
}

async function setupTestDatabase(): Promise<void> {
  // Set up in-memory database for testing
  // This would typically initialize a test database connection
  console.log('Setting up test database...')
}

async function initializeTestData(): Promise<void> {
  // Initialize any required test data
  console.log('Initializing test data...')
}