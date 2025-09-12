# Refactoring Plan for Nextcloud Door Estimator

## Frontend (js/door-estimator.js)

### 1. Extract Pure Functions
- **js/door-estimator.js**
  - Extract functions for DOM manipulation.
  - Extract functions for handling user inputs.
  - Extract functions for calculating estimates.

### 2. Isolate Side Effects
- **js/door-estimator.js**
  - Move API calls to separate service functions.
  - Move DOM manipulation to separate utility functions.

### 3. Introduce Higher-Order Functions
- **js/door-estimator.js**
  - Use higher-order functions for handling events and DOM updates.

### 4. Refactor for Immutability