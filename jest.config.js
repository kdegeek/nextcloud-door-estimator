module.exports = {
  preset: 'ts-jest',
  testEnvironment: 'jsdom',
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
    '\\.vue$': '<rootDir>/types/shims-vue.d.ts'
  },
  transform: {
    '^.+\\.vue$': '@vue/vue3-jest',
    '^.+\\.ts$': 'ts-jest'
  },
  moduleFileExtensions: ['ts', 'js', 'json', 'vue', 'node']
};