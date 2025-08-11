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
  transform: {
    '^.+\\.vue$': '@vue/vue3-jest',
    '^.+\\.ts$': ['ts-jest', { tsconfig: 'tsconfig.json', useESM: true }]
  },
  moduleFileExtensions: ['ts', 'js', 'json', 'vue', 'node']
};