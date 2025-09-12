/// <reference types="jest" />

// Basic shims for JSDOM
if (!(window as any).scrollTo) {
  Object.defineProperty(window, 'scrollTo', { value: () => {}, writable: true })
}

// URL.createObjectURL / revokeObjectURL for Blob usage
if (!window.URL.createObjectURL) {
  Object.defineProperty(window.URL, 'createObjectURL', {
    value: () => 'blob:mock-url',
    writable: true,
  })
}
if (!window.URL.revokeObjectURL) {
  Object.defineProperty(window.URL, 'revokeObjectURL', {
    value: () => {},
    writable: true,
  })
}

// Mock @nextcloud/vue NcAppContent component if not already mocked by test files
jest.mock('@nextcloud/vue/dist/Components/NcAppContent.js', () => ({
  __esModule: true,
  default: {
    name: 'NcAppContent',
    template: '<div><slot /></div>',
  },
}))

// Quiet console noise from tests (optional)
// const originalError = console.error
// console.error = (...args: unknown[]) => {
//   if (String(args[0] || '').includes('Vue warn')) return
//   originalError(...args)
// }