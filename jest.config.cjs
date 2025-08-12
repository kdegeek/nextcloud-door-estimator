module.exports = {
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
    'uuid': require.resolve('uuid'),
    '\\.(css|less|scss|sass)$': 'identity-obj-proxy',
    '^@nextcloud/vue/dist/Components/(.*)\\.js$': '<rootDir>/node_modules/@nextcloud/vue/dist/Components/$1.js',
    '^@nextcloud/router$': '<rootDir>/node_modules/@nextcloud/router/dist/index.js',
    '^@/(.*)$': '<rootDir>/src/$1',
  },
  transform: {
    '^.+\\.vue$': '@vue/vue3-jest',
    '^.+\\.(ts|tsx)$': 'ts-jest',
  },
  transformIgnorePatterns: [
    '/node_modules/(?!(@nextcloud/router|@nextcloud/vue)/)',
  ],
  moduleFileExtensions: ['ts', 'js', 'json', 'vue', 'node'],
};