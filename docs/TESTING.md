# Door Estimator App: Comprehensive Testing Documentation

## Overview

This document describes how to run, interpret, and extend the test suites for the Door Estimator app. It covers PHP integration/unit tests, Vue frontend tests, build verification, and end-to-end (e2e) tests. The goal is to ensure reliability and correctness across all application layers.

---

## 1. Test Database Setup and Teardown

- The test runner script automatically sets up and tears down the test database using migration scripts.
- **Manual setup:**  
  ```bash
  php lib/Migration/Version001000Date20250124000000.php --setup-test-db
  ```
- **Manual teardown:**  
  ```bash
  php lib/Migration/Version001000Date20250124000000.php --teardown-test-db
  ```

---

## 2. Running All Test Suites

- Use the comprehensive runner script:
  ```bash
  ./scripts/test-runner.sh
  ```
- This script will:
  - Set up the test database
  - Run PHP integration and unit tests
  - Run Vue frontend integration tests
  - Run build verification tests
  - Run end-to-end (e2e) tests (if present)
  - Tear down the test database
  - Print a color-coded summary of results

---

## 3. Running Individual Test Suites

- **PHP Integration Tests:**  
  ```bash
  php tests/integration/api-test.php
  php tests/integration/database-test.php
  ```
- **PHP Unit Tests (if available):**  
  ```bash
  phpunit --configuration reference/mwpcloud/tests/phpunit.xml
  ```
- **Vue Frontend Integration Tests:**  
  ```bash
  npx jest tests/frontend/app-integration.spec.ts
  ```
- **Build Verification:**  
  ```bash
  node tests/build/build-verification.js
  ```
- **End-to-End Tests (if present):**  
  ```bash
  npx playwright test tests/e2e
  ```

---

## 4. Interpreting Test Results

- The runner script outputs colored status for each suite (PASS/FAIL/SKIP).
- A summary table is printed at the end.
- For individual tests, check the console output for errors and stack traces.
- Build verification will print details about build steps and asset checks.

---

## 5. Troubleshooting Test Failures

- **Database setup issues:** Ensure migration scripts are up to date and the database is accessible.
- **Dependency errors:** Run `npm install` or `composer install` as needed.
- **Build failures:** Check Vite/TypeScript output and verify config files.
- **Frontend test errors:** Ensure Jest and Playwright are installed and configured.
- **Common fixes:** Clean up test data, reset the database, check for missing environment variables.

---

## 6. Adding New Tests

- **PHP:**  
  - Add new test files in `tests/integration/` or `tests/Controller/`, following PHPUnit conventions.
  - Use descriptive test method names and assertions.
- **Frontend:**  
  - Add `.spec.ts` files in `tests/frontend/`, using Jest and Vue Test Utils.
  - Mock dependencies as needed for integration.
- **Build:**  
  - Extend `tests/build/build-verification.js` for new build checks.
- **E2E:**  
  - Add Playwright test files in `tests/e2e/`.

---

## 7. Maintaining Test Coverage

- Ensure new features and bug fixes include corresponding tests.
- Use code coverage tools (e.g., Jest coverage, phpunit coverage) to monitor gaps.
- Refactor tests for clarity and maintainability.
- Periodically review test suites for completeness.

---

## 8. Reference: Test Files and Runner Script

- **Test files:**  
  - [`tests/integration/api-test.php`](../tests/integration/api-test.php)
  - [`tests/integration/database-test.php`](../tests/integration/database-test.php)
  - [`tests/frontend/app-integration.spec.ts`](../tests/frontend/app-integration.spec.ts)
  - [`tests/build/build-verification.js`](../tests/build/build-verification.js)
  - [`tests/e2e/`](../tests/e2e/) (if present)
- **Test runner script:**  
  - [`scripts/test-runner.sh`](../scripts/test-runner.sh)

---

## 9. Additional Notes

- Always run tests in a clean environment to avoid data contamination.
- For CI/CD integration, invoke `./scripts/test-runner.sh` and parse its exit code.
- For more details on installation and environment setup, see [`docs/INSTALLATION.md`](INSTALLATION.md).
