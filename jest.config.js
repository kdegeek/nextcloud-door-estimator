module.exports = {
  preset: 'ts-jest',
  testEnvironment: 'jsdom',
  testMatch: [
    '**/?(*.)+(test|spec).ts',
    '**/?(*.)+(test|spec).tsx'
  ],
  testPathIgnorePatterns: [
    '/node_modules/',
    '/tests/Service/',
    '/tests/Controller/',
    '/tests/e2e/',
    '/tests/scripts/'
  ],
  moduleNameMapper: {
    '^utils/(.*)$': '<rootDir>/utils/$1'
  },
  moduleFileExtensions: ['ts', 'tsx', 'js', 'jsx', 'json', 'node']
};