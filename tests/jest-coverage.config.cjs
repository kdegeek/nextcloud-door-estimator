/**
 * Jest configuration for comprehensive frontend testing with coverage
 */
module.exports = {
  testEnvironment: 'jsdom',
  extensionsToTreatAsEsm: ['.ts'],
  
  // Test file patterns
  testMatch: [
    '**/tests/frontend/**/?(*.)+(test|spec).ts',
    '**/tests/frontend/**/?(*.)+(test|spec).js'
  ],
  
  // Ignore patterns
  testPathIgnorePatterns: [
    '/node_modules/',
    '/tests/Service/',
    '/tests/Controller/',
    '/tests/e2e/',
    '/tests/scripts/',
    '/tests/integration/',
    '/tests/performance/',
    '/tests/security/'
  ],
  
  // Module name mapping
  moduleNameMapper: {
    '^utils/(.*)$': '<rootDir>/utils/$1',
    '^@/(.*)$': '<rootDir>/$1',
    '^~/(.*)$': '<rootDir>/$1',
    '^src/(.*)$': '<rootDir>/src/$1',
    '\\.(css|less|scss|sass)$': 'identity-obj-proxy'
  },
  
  // Setup files
  setupFiles: [
    '<rootDir>/tests/frontend/jest.setup.ts'
  ],
  
  setupFilesAfterEnv: [
    '<rootDir>/tests/frontend/jest.setup.ts'
  ],
  
  // Transform configuration
  transform: {
    '^.+\\.vue$': '@vue/vue3-jest',
    '^.+\\.ts$': ['ts-jest', { 
      tsconfig: 'tsconfig.json', 
      useESM: true 
    }],
    '^.+\\.js$': 'babel-jest'
  },
  
  // File extensions
  moduleFileExtensions: ['ts', 'js', 'json', 'vue', 'node'],
  
  // Coverage configuration
  collectCoverage: true,
  collectCoverageFrom: [
    'src/**/*.{ts,js,vue}',
    '!src/**/*.d.ts',
    '!src/main.ts',
    '!src/main.js',
    '!**/node_modules/**',
    '!**/vendor/**',
    '!**/tests/**'
  ],
  
  coverageDirectory: 'tests/coverage/frontend',
  
  coverageReporters: [
    'text',
    'text-summary',
    'html',
    'lcov',
    'clover',
    'json'
  ],
  
  // Coverage thresholds
  coverageThreshold: {
    global: {
      statements: 85,
      branches: 80,
      functions: 85,
      lines: 85
    },
    // Specific thresholds for critical components
    'src/services/': {
      statements: 90,
      branches: 85,
      functions: 90,
      lines: 90
    },
    'src/stores/': {
      statements: 90,
      branches: 85,
      functions: 90,
      lines: 90
    },
    'src/utils/': {
      statements: 95,
      branches: 90,
      functions: 95,
      lines: 95
    }
  },
  
  // Test environment options
  testEnvironmentOptions: {
    url: 'http://localhost:3000'
  },
  
  // Global test timeout
  testTimeout: 10000,
  
  // Verbose output
  verbose: true,
  
  // Clear mocks between tests
  clearMocks: true,
  
  // Restore mocks after each test
  restoreMocks: true,
  
  // Error handling
  errorOnDeprecated: true,
  
  // Performance monitoring
  detectOpenHandles: true,
  detectLeaks: true,
  
  // Test result processors
  reporters: [
    'default',
    ['jest-html-reporters', {
      publicPath: 'tests/coverage/frontend',
      filename: 'jest-report.html',
      expand: true,
      hideIcon: false,
      pageTitle: 'Door Estimator Frontend Test Report'
    }],
    ['jest-junit', {
      outputDirectory: 'tests/coverage/frontend',
      outputName: 'junit.xml',
      ancestorSeparator: ' › ',
      uniqueOutputName: 'false',
      suiteNameTemplate: '{filepath}',
      classNameTemplate: '{classname}',
      titleTemplate: '{title}'
    }]
  ],
  
  // Global setup and teardown
  globalSetup: '<rootDir>/tests/frontend/global-setup.ts',
  globalTeardown: '<rootDir>/tests/frontend/global-teardown.ts',
  
  // Watch mode configuration
  watchman: true,
  watchPathIgnorePatterns: [
    '/node_modules/',
    '/tests/coverage/',
    '/tmp/'
  ],
  
  // Snapshot configuration
  snapshotSerializers: [
    '<rootDir>/node_modules/@vue/test-utils/dist/serializer.js'
  ],
  
  // Custom matchers
  setupFilesAfterEnv: [
    '<rootDir>/tests/frontend/jest.setup.ts',
    '<rootDir>/tests/frontend/custom-matchers.ts'
  ],
  
  // Test groups
  runner: 'groups',
  
  // Cache configuration
  cacheDirectory: '<rootDir>/tests/coverage/frontend/.jest-cache',
  
  // Bail configuration
  bail: false,
  
  // Notify configuration for watch mode
  notify: true,
  notifyMode: 'failure-change',
  
  // Max workers for parallel execution
  maxWorkers: '50%',
  
  // Test retry configuration
  retry: 1,
  
  // Silent mode
  silent: false
};