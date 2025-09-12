#!/usr/bin/env bash

# Check Bash version (requires 4.0+)
if (( BASH_VERSINFO[0] < 4 )); then
  echo "ERROR: Bash version 4.0 or higher is required. Current version: ${BASH_VERSION}" >&2
  exit 1
fi

set -euo pipefail

: <<"DOC"
run-build-tests.sh
Purpose:
- Orchestrates build tests and bundle validation for the Door Estimator app.
- Prepares environment, checks required project files, runs build tests and bundle validation, and writes a master log.

Requirements:
- bash 4+
- node and npm available on PATH

Exit codes:
- 0 (E_OK): Success, all steps completed
- 1 (E_PREREQ): Missing prerequisites (e.g., node/npm not available)
- 2 (E_MISSING_FILES): Required project files are missing
- 3 (E_VALIDATE_FAILED): Bundle validation failed
DOC

# Exit code constants
readonly E_OK=0
readonly E_PREREQ=1
readonly E_MISSING_FILES=2
readonly E_VALIDATE_FAILED=3

# Resolve script directory robustly using BASH_SOURCE and realpath/readlink
resolve_realpath() {
  local path="$1"
  if command -v readlink >/dev/null 2>&1 && readlink -f / >/dev/null 2>&1; then
    readlink -f "$path"
  elif command -v greadlink >/dev/null 2>&1; then
    greadlink -f "$path"
  elif command -v python3 >/dev/null 2>&1; then
    python3 - <<'PY' "$path"
import os, sys
print(os.path.realpath(sys.argv[1]))
PY
  else
    # Fallback: resolve without symlink expansion
    local dir
    dir="$(cd "$(dirname "$path")" && pwd)"
    echo "${dir}/$(basename "$path")"
  fi
}

SCRIPT_PATH="${BASH_SOURCE[0]}"
SCRIPT_PATH="$(resolve_realpath "$SCRIPT_PATH")"
ROOT_DIR="$(cd "$(dirname "$SCRIPT_PATH")" && pwd)"
readonly ROOT_DIR

# Paths and config
REPORT_DIR="${ROOT_DIR}/tmp"
MASTER_LOG="${REPORT_DIR}/run-build-tests-$(date +%Y%m%d-%H%M%S).log"
TEST_SCRIPT="${ROOT_DIR}/test-build-process.sh"
VALIDATE_SCRIPT="${ROOT_DIR}/validate-bundle.sh"
readonly REPORT_DIR MASTER_LOG TEST_SCRIPT VALIDATE_SCRIPT

# Required files list
REQUIRED_FILES=(
  "${ROOT_DIR}/package.json"
  "${ROOT_DIR}/vite.config.js"
  "${ROOT_DIR}/src/main.js"
  "${ROOT_DIR}/src/App.vue"
  "${ROOT_DIR}/templates/main.php"
)
readonly -a REQUIRED_FILES

# Logging helpers (centralized logging; no tee in individual functions)
log()    { echo "[INFO]  $*"; }
ok()     { echo "[PASS]  $*"; }
warn()   { echo "[WARN]  $*"; }
fail()   { echo "[FAIL]  $*"; }
section(){ printf "\n===== %s =====\n" "$*"; }

# Function: prepare_environment
# Purpose: Create necessary directories and set up logging
prepare_environment() {
  mkdir -p "${REPORT_DIR}"
  # Redirect all stdout and stderr to MASTER_LOG
  exec >>"${MASTER_LOG}" 2>&1
  section "Master Log"
  log "Log file: ${MASTER_LOG}"
}

# Function: check_prerequisites
# Purpose: Verify that required tools (node, npm) are available
check_prerequisites() {
  section "Environment Preparation"
  if ! command -v node >/dev/null 2>&1; then
    fail "node is not installed or not in PATH"
    exit "${E_PREREQ}"
  fi
  if ! command -v npm >/dev/null 2>&1; then
    fail "npm is not installed or not in PATH"
    exit "${E_PREREQ}"
  fi
  ok "Node.js: $(node -v)"
  ok "npm: $(npm -v)"
}

# Function: verify_required_files
# Purpose: Check that all required project files exist
verify_required_files() {
  section "Project File Checks"
  local missing_count=0
  for file in "${REQUIRED_FILES[@]}"; do
    if [[ ! -f "$file" ]]; then
      fail "Required file missing: $file"
      missing_count=1
    fi
  done
  if [[ ${missing_count} -eq 1 ]]; then
    exit "${E_MISSING_FILES}"
  fi
  ok "All required files are present"
}

# Function: run_and_log
# Purpose: Execute a script while capturing output and returning exit status
run_and_log() {
  # Usage: run_and_log "Label" "/absolute/path/to/script.sh"
  local label="$1"
  local script_path="$2"
  section "${label}"
  if [[ ! -f "${script_path}" ]]; then
    fail "Script not found: ${script_path}"
    return "${E_PREREQ}"
  fi
  
  # Capture both stdout and stderr, log to MASTER_LOG, and display to console
  if bash "${script_path}" 2>&1 | tee -a "${MASTER_LOG}"; then
    local code=0
  else
    local code=${PIPESTATUS[0]}
  fi
  
  if [[ ${code} -eq 0 ]]; then
    ok "${label} completed successfully (exit ${code})"
  else
    fail "${label} failed (exit ${code})"
  fi
  return ${code}
}

# Function: verify_integration
# Purpose: Check that the main template references the door-estimator bundle
verify_integration() {
  section "Integration Verification"
  local main_php="${ROOT_DIR}/templates/main.php"
  if [[ -f "${main_php}" ]] && grep -q "door-estimator" "${main_php}"; then
    ok "templates/main.php references door-estimator bundle"
  else
    warn "templates/main.php does not obviously reference bundle; verify template configuration"
  fi
}

# Function: generate_final_report
# Purpose: Output final report message
generate_final_report() {
  section "Final Report"
  echo "All steps executed. See logs: ${MASTER_LOG}"
}

# Function: main
# Purpose: Orchestrate the entire build test process
main() {
  prepare_environment
  check_prerequisites
  verify_required_files

  if run_and_log "Execute Build Tests" "${TEST_SCRIPT}"; then
    ok "Build tests completed"
  else
    warn "Build tests reported issues; proceeding to bundle validation for details"
  fi

  if run_and_log "Validate Bundle" "${VALIDATE_SCRIPT}"; then
    ok "Bundle validation completed successfully"
  else
    fail "Bundle validation found errors"
    echo "Master logs: ${MASTER_LOG}"
    exit "${E_VALIDATE_FAILED}"
  fi

  verify_integration
  generate_final_report
  exit "${E_OK}"
}

main "$@"
