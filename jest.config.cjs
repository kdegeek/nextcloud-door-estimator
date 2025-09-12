/**
 * Note:
 * - PHP tests under /tests/Service/ and /tests/Controller/ are executed via PHPUnit, not Jest.
 * - Jest is used for frontend/unit tests only.
 */
module.exports = {
  testEnvironment: 'jsdom',
  extensionsToTreatAsEsm: ['.ts'],
  testMatch: [
    '**/?(*.)+(test|spec).ts'
  ],
  testPathIgnorePatterns: [
    '/node_modules/',
    '/tests/Service/',
    '/tests/Controller/',
    '/tests/e2e/',
    '/tests/scripts/'
  ],
  moduleNameMapper: {
    '^utils/(.*)$': '<rootDir>/utils/$1',
    '^@/(.*)$': '<rootDir>/$1',
    '^~/(.*)$': '<rootDir>/$1'
  },
  setupFiles: [
    '<rootDir>/tests/frontend/jest.setup.ts'
  ],
  setupFilesAfterEnv: [
    '<rootDir>/tests/frontend/jest.setup.ts'
  ],
  transform: {
    '^.+\\.vue$': '@vue/vue3-jest',
    '^.+\\.ts$': ['ts-jest', { tsconfig: 'tsconfig.json', useESM: true }]
  },
  moduleFileExtensions: ['ts', 'js', 'json', 'vue', 'node'],
  coverageThreshold: {
    global: {
      statements: 70,
      branches: 60,
      functions: 70,
      lines: 70
    }
  }
};