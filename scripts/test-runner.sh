#!/bin/bash

# Door Estimator Comprehensive Test Runner
# Runs: PHP unit/integration tests, Vue frontend tests, build verification, e2e tests
# Handles: DB setup/teardown, test data management, result reporting

set -e

# Colors for output
RED="\\033[0;31m"
GREEN="\\033[0;32m"
YELLOW="\\033[1;33m"
NC="\\033[0m"

# Result tracking
declare -A RESULTS

echo -e "${YELLOW}Starting Door Estimator Test Runner...${NC}"

# 1. Database Setup
echo -e "${YELLOW}Setting up test database...${NC}"
if php occ migrations:execute "lib\\Migration\\Version001000Date20250124000000" up; then
  echo -e "${GREEN}Test database setup complete.${NC}"
  RESULTS[db_setup]="PASS"
else
  echo -e "${RED}Test database setup failed.${NC}"
  RESULTS[db_setup]="FAIL"
fi

# 2. PHP Integration Tests
echo -e "${YELLOW}Running PHP integration tests...${NC}"
if php vendor/bin/phpunit --configuration reference/mwpcloud/tests/phpunit.xml tests/integration/api-test.php; then
  echo -e "${GREEN}api-test.php passed.${NC}"
  RESULTS[api_test]="PASS"
else
  echo -e "${RED}api-test.php failed.${NC}"
  RESULTS[api_test]="FAIL"
fi

if php vendor/bin/phpunit --configuration reference/mwpcloud/tests/phpunit.xml tests/integration/database-test.php; then
  echo -e "${GREEN}database-test.php passed.${NC}"
  RESULTS[db_test]="PASS"
else
  echo -e "${RED}database-test.php failed.${NC}"
  RESULTS[db_test]="FAIL"
fi

# 3. PHP Unit Tests (if phpunit is available)
if command -v phpunit &> /dev/null; then
  echo -e "${YELLOW}Running PHP unit tests...${NC}"
  if phpunit --configuration reference/mwpcloud/tests/phpunit.xml; then
    echo -e "${GREEN}PHP unit tests passed.${NC}"
    RESULTS[phpunit]="PASS"
  else
    echo -e "${RED}PHP unit tests failed.${NC}"
    RESULTS[phpunit]="FAIL"
  fi
else
  echo -e "${YELLOW}phpunit not found, skipping PHP unit tests.${NC}"
  RESULTS[phpunit]="SKIP"
fi

# 4. Build Verification
echo -e "${YELLOW}Running build verification...${NC}"
if node tests/build/build-verification.js; then
  echo -e "${GREEN}Build verification passed.${NC}"
  RESULTS[build_verification]="PASS"
else
  echo -e "${RED}Build verification failed.${NC}"
  RESULTS[build_verification]="FAIL"
fi

# 5. Vue Frontend Integration Tests
echo -e "${YELLOW}Running Vue frontend integration tests...${NC}"
if npx jest tests/frontend/app-integration.spec.ts; then
  echo -e "${GREEN}Vue frontend integration tests passed.${NC}"
  RESULTS[vue_integration]="PASS"
else
  echo -e "${RED}Vue frontend integration tests failed.${NC}"
  RESULTS[vue_integration]="FAIL"
fi

# 6. End-to-End Tests (if present)
if [ -d "tests/e2e" ]; then
  echo -e "${YELLOW}Running end-to-end tests...${NC}"
  if npx playwright test tests/e2e; then
    echo -e "${GREEN}End-to-end tests passed.${NC}"
    RESULTS[e2e]="PASS"
  else
    echo -e "${RED}End-to-end tests failed.${NC}"
    RESULTS[e2e]="FAIL"
  fi
else
  echo -e "${YELLOW}No e2e test directory found, skipping e2e tests.${NC}"
  RESULTS[e2e]="SKIP"
fi

# 7. Database Teardown
echo -e "${YELLOW}Tearing down test database...${NC}"
if php occ migrations:execute "lib\\Migration\\Version001000Date20250124000000" down; then
  echo -e "${GREEN}Test database teardown complete.${NC}"
  RESULTS[db_teardown]="PASS"
else
  echo -e "${RED}Test database teardown failed.${NC}"
  RESULTS[db_teardown]="FAIL"
fi

# 8. Reporting
echo -e "\\n${YELLOW}========== TEST SUMMARY ==========${NC}"
for key in "${!RESULTS[@]}"; do
  status="${RESULTS[$key]}"
  if [ "$status" == "PASS" ]; then
    echo -e "${GREEN}$key: $status${NC}"
  elif [ "$status" == "FAIL" ]; then
    echo -e "${RED}$key: $status${NC}"
  else
    echo -e "${YELLOW}$key: $status${NC}"
  fi
done

# Final exit code
if [[ " ${RESULTS[@]} " =~ "FAIL" ]]; then
  echo -e "${RED}Some tests failed.${NC}"
  exit 1
else
  echo -e "${GREEN}All tests passed.${NC}"
  exit 0
fi