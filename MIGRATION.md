# Migration Summary: React to Vue 3

## Overview

This document summarizes the migration of the Door Estimator NextCloud App frontend from React (TypeScript/TSX) to Vue 3 (Single File Components, SFCs). The migration was performed to leverage Vue's improved SFC developer experience, better integration with Nextcloud's recommended tooling, and to simplify state management and component structure.

---

## Migration Process

- **Component Rewrite**: All React `.tsx` components were rewritten as Vue 3 SFCs (`.vue` files) using the Composition API.
- **State Management**: Local and shared state previously managed with React hooks was migrated to Vue's reactive system.
- **Event Handling**: React event handlers were replaced with Vue's event and prop system.
- **Templates**: JSX/TSX render logic was converted to Vue template syntax.
- **Testing**: All component tests were rewritten using [@vue/test-utils](https://test-utils.vuejs.org/) and Jest.

---

## Key Changes

### Codebase

- **Removed**: All `.tsx` and React-specific files.
- **Added**: Vue 3 SFCs (`.vue` files) for all UI components.
- **Updated**: Test files to use Vue Test Utils and mount Vue components.

### Dependencies

- **Removed**: `react`, `react-dom`, `@types/react`, and related packages.
- **Added**:
  - `vue` (v3.x)
  - `@vue/compiler-sfc`
  - `@vue/test-utils`
  - `@vue/vue3-jest`
  - `vue-loader`
- **Retained**: TypeScript, Jest, ts-jest, ts-loader, webpack.

### Tooling & Configuration

- **Webpack**: Updated to handle `.vue` files with `vue-loader` and TypeScript with `ts-loader`.
- **Jest**: Configured to use `@vue/vue3-jest` for `.vue` files and `ts-jest` for `.ts` files.
- **TypeScript**: Updated `tsconfig.json` to include `.vue` files and use appropriate shims.
- **Type Declarations**: Added `types/shims-vue.d.ts` for Vue SFC support.

---

## Issues Encountered & Solutions

- **Jest + TypeScript + Vue SFCs**: Required configuration of `@vue/vue3-jest` and `ts-jest` in `jest.config.js` to support SFC unit tests.
- **TypeScript SFC Support**: Needed `types/shims-vue.d.ts` to allow TypeScript to recognize `.vue` imports.
- **Webpack Loader Order**: Ensured `vue-loader` and `ts-loader` were correctly chained for `.vue` files.
- **Legacy Test Migration**: All React-based tests were rewritten for Vue 3 using Vue Test Utils.

---

## Recommendations for Future Maintenance

- **Testing**: Use [@vue/test-utils](https://test-utils.vuejs.org/) and Jest for all component/unit tests. Ensure new SFCs are covered by tests.
- **TypeScript**: Keep `tsconfig.json` and type shims up to date with Vue 3 best practices.
- **Dependencies**: Regularly update Vue and related tooling to maintain compatibility and security.
- **Documentation**: Update this document with any future framework or major dependency changes.

---

## Actions Taken

- Migrated all frontend code from React/TSX to Vue 3 SFCs.
- Updated all build, test, and type tooling for Vue 3 compatibility.
- Rewrote all component tests for Vue 3.
- Removed all React and related dependencies.
- Updated documentation to reflect the new stack.
