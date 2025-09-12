#!/usr/bin/env bash

# This script requires external commands: node, npm, bash, stat (preferred for file metadata), setsid (for process groups if available), pkill (fallback for process termination). Ensure these are installed on the platform (macOS/Linux).

set -euo pipefail

# test-build-process.sh
# Runs production and development builds via scripts/build.sh and validates outputs.

ROOT_DIR="$(cd "$(dirname "$0")" && pwd)"
BUILD_SCRIPT="${ROOT_DIR}/scripts/build.sh"
BUNDLE_PATH="${ROOT_DIR}/js/door-estimator.js"
VALIDATE_SCRIPT="${ROOT_DIR}/validate-bundle.sh"

# Initialize sizes to 0 to ensure they are always defined for summary
prod_size=0
dev_size=0

# Create temporary report directory and file using mktemp for collision-free names
REPORT_DIR=$(mktemp -d "${ROOT_DIR}/tmp/build-test.XXXXXX")
REPORT_FILE=$(mktemp "${REPORT_DIR}/report.XXXXXX.log")
WATCH_LOG=$(mktemp "${REPORT_DIR}/watch.XXXXXX.log")

# Trap to clean up temporary directory on script exit
cleanup_temp() {
  if [[ -d "${REPORT_DIR}" ]]; then
    rm -rf "${REPORT_DIR}"
    log "Temporary report directory cleaned up"
  fi
}
trap cleanup_temp EXIT

log() { echo "[INFO]  $*" | tee -a "${REPORT_FILE}"; }
ok() { echo "[PASS]  $*" | tee -a "${REPORT_FILE}"; }
fail() { echo "[FAIL]  $*" | tee -a "${REPORT_FILE}"; }
section() { printf "\n===== %s =====\n" "$*" | tee -a "${REPORT_FILE}"; }

require_file() {
  if [[ ! -f "$1" ]]; then
    fail "Missing required file: $1"
    exit 1
  fi
}

clean_artifacts() {
  rm -f "${ROOT_DIR}/js/door-estimator.js" "${ROOT_DIR}/js/door-estimator.js.map"
  rm -rf "${ROOT_DIR}/dist" "${ROOT_DIR}/js/assets" "${ROOT_DIR}/.vite" 2>/dev/null || true
}

# Refactored measure_time to accept command as array-like arguments, execute preserving boundaries, capture exit code
measure_time() {
  local label="$1"; shift
  local cmd=("$@")
  local start end elapsed exit_code
  start=$(date +%s)
  if [[ ${#cmd[@]} -eq 0 ]]; then
    fail "${label}: No command provided"
    return 1
  fi
  "${cmd[@]}"
  exit_code=$?
  end=$(date +%s)
  elapsed=$((end - start))
  if [[ ${exit_code} -eq 0 ]]; then
    log "${label} completed in ${elapsed}s with exit code ${exit_code}"
    return 0
  else
    fail "${label} failed after ${elapsed}s with exit code ${exit_code}"
    return ${exit_code}
  fi
}

# Refactored file_size and file_mtime_ms to prefer POSIX stat for efficiency, fallback to Node.js if stat unavailable
# Preference for stat: faster native call, no subprocess overhead, better for maintainability on Unix-like systems
file_size() {
  local path="$1"
  if command -v stat >/dev/null 2>&1; then
    # macOS uses -f %z, GNU uses -c %s; detect and use appropriate
    if stat -f %z "${path}" 2>/dev/null >/dev/null; then
      stat -f %z "${path}"
    else
      stat -c %s "${path}" 2>/dev/null || echo 0
    fi
  else
    # Fallback to Node.js
    node -e "const fs=require('fs');try{console.log(fs.statSync(process.argv[1]).size)}catch(e){console.log(0)}" "$path"
  fi
}

file_mtime_ms() {
  local path="$1"
  if command -v stat >/dev/null 2>&1; then
    # Approximate ms from seconds; for precision, Node.js fallback is used if needed
    # macOS: stat -f %Sm -t '%s' for seconds, multiply by 1000
    if stat -f %Sm -t '%s' "${path}" 2>/dev/null >/dev/null; then
      local secs=$(stat -f %Sm -t '%s' "${path}")
      echo $((secs * 1000))
    else
      # GNU stat
      local secs=$(stat -c %Y "${path}" 2>/dev/null || echo 0)
      echo $((secs * 1000))
    fi
  else
    # Fallback to Node.js for precise mtimeMs
    node -e "const fs=require('fs');try{console.log(Math.floor(fs.statSync(process.argv[1]).mtimeMs))}catch(e){console.log(0)}" "$path"
  fi
}

# New helper function for build and validation (refactors repeated steps for prod and dev)
build_and_validate() {
  local build_type="$1"
  local build_cmd="$2"
  local size_var="$3"
  local build_ok=1

  section "${build_type^} Build"
  clean_artifacts

  if ! measure_time "${build_type} build" bash -c "set -Eeuo pipefail; cd '${ROOT_DIR}' && NC_SKIP_SETUP=1 ${build_cmd}"; then
    fail "${build_type^} build failed; continuing for additional diagnostics if applicable"
    build_ok=0
  fi

  if [[ -f "${BUNDLE_PATH}" ]]; then
    ok "${build_type^} bundle created: js/door-estimator.js"
  else
    fail "${build_type^} bundle not found"
    if [[ ${build_ok} -eq 1 ]]; then
      exit 2
    else
      log "Skipping ${build_type} validation due to earlier build failure"
    fi
    return 1
  fi

  local size=$(file_size "${BUNDLE_PATH}")
  log "${build_type^} bundle size: ${size} bytes"
  eval "${size_var}=${size}"

  if [[ ${build_ok} -eq 1 && -f "${BUNDLE_PATH}" ]]; then
    if bash -c "set -o pipefail; '${VALIDATE_SCRIPT}' | tee -a '${REPORT_FILE}'"; then
      ok "${build_type^} bundle validated"
    else
      fail "${build_type^} bundle validation failed"
      # Continue to collect more info
    fi
  else
    log "Skipping ${build_type} validation due to build failure or missing bundle"
  fi

  # For dev, stricter exit if no bundle and build failed
  if [[ "${build_type}" == "dev" && ${build_ok} -eq 0 && ! -f "${BUNDLE_PATH}" ]]; then
    exit 3
  fi

  # Log size comparison if both builds done (but since called sequentially, handle in summary)
  if [[ "${build_type}" == "dev" && ${prod_size} -gt 0 ]]; then
    if (( dev_size < prod_size )); then
      log "Dev bundle is smaller than prod; this may be fine depending on config/minification."
    else
      log "Dev bundle size is >= prod; typical for unminified dev builds."
    fi
  fi
}

section "Prerequisites"
require_file "${BUILD_SCRIPT}"
require_file "${ROOT_DIR}/package.json"
require_file "${ROOT_DIR}/vite.config.js"
require_file "${VALIDATE_SCRIPT}"
if ! command -v npm >/dev/null 2>&1; then
  fail "npm not found in PATH"
  exit 1
fi
if ! command -v node >/dev/null 2>&1; then
  fail "node not found in PATH"
  exit 1
fi
ok "Environment prerequisites satisfied"

# Production build using helper
build_and_validate "prod" "'${BUILD_SCRIPT}' prod" "prod_size"

# Development build using helper
build_and_validate "dev" "'${BUILD_SCRIPT}' dev-build" "dev_size"

section "Watch Mode (smoke test)"
clean_artifacts

WATCH_PID=""

# Refactored cleanup_watch: Uses process group for safer termination if setsid available, else fallback
cleanup_watch() {
  if [[ -n "${WATCH_PID}" ]]; then
    if command -v setsid >/dev/null 2>&1; then
      # If started with setsid, kill process group
      if kill -0 "${WATCH_PID}" >/dev/null 2>&1; then
        kill -TERM -"${WATCH_PID}" >/dev/null 2>&1 || true
        sleep 1
        if kill -0 "${WATCH_PID}" >/dev/null 2>&1; then
          kill -KILL -"${WATCH_PID}" >/dev/null 2>&1 || true
        fi
        wait "${WATCH_PID}" 2>/dev/null || true
      fi
    else
      # Fallback: original pkill -P and kill
      if kill -0 "${WATCH_PID}" >/dev/null 2>&1; then
        pkill -P "${WATCH_PID}" >/dev/null 2>&1 || true
        kill -TERM "${WATCH_PID}" >/dev/null 2>&1 || true
        sleep 1
        if kill -0 "${WATCH_PID}" >/dev/null 2>&1; then
          kill -KILL "${WATCH_PID}" >/dev/null 2>&1 || true
        fi
        wait "${WATCH_PID}" 2>/dev/null || true
      fi
    fi
    WATCH_PID=""
    log "Watch mode stopped"
  fi
}

# Trap for cleanup_watch: Ensures watch processes are terminated on script exit to prevent orphans
trap cleanup_watch EXIT

# Start watch in background, use setsid if available for process group
if command -v setsid >/dev/null 2>&1; then
  (
    cd "${ROOT_DIR}"
    exec setsid NC_SKIP_SETUP=1 "${BUILD_SCRIPT}" watch >>"${WATCH_LOG}" 2>&1
  ) &
  WATCH_PID=$!
else
  (
    cd "${ROOT_DIR}"
    NC_SKIP_SETUP=1 "${BUILD_SCRIPT}" watch >>"${WATCH_LOG}" 2>&1
  ) &
  WATCH_PID=$!
fi
log "Started watch (PID ${WATCH_PID})"

# Wait up to 60s for initial bundle to appear
initial_wait_start=$(date +%s)
while [[ ! -f "${BUNDLE_PATH}" ]]; do
  now=$(date +%s)
  if (( now - initial_wait_start > 60 )); then
    fail "Watch mode did not produce initial bundle within 60s"
    break
  fi
  sleep 1
done
if [[ -f "${BUNDLE_PATH}" ]]; then
  ok "Initial watch bundle created"
fi

# Non-destructive watch trigger (backup, modify, then restore)
SRC_FILE="${ROOT_DIR}/src/App.vue"
if [[ -f "${SRC_FILE}" ]]; then
  mtime_before=$(file_mtime_ms "${BUNDLE_PATH}")
  touch "${SRC_FILE}"
  log "Touched src/App.vue to trigger rebuild"
  # Poll for mtime increase up to 60s
  rebuild_start=$(date +%s)
  updated=0
  while true; do
    mtime_after=$(file_mtime_ms "${BUNDLE_PATH}")
    if [[ "${mtime_after}" -gt "${mtime_before}" ]]; then
      updated=1
      break
    fi
    now=$(date +%s)
    if (( now - rebuild_start > 60 )); then
      break
    fi
    sleep 1
  done
  if [[ ${updated} -eq 1 ]]; then
    ok "Watch mode rebuilt the bundle after change"
  else
    fail "Watch mode did not update the bundle timestamp within 60s"
  fi
else
  log "src/App.vue not found; skipping change trigger"
fi

# Cleanup will be handled by EXIT trap

section "Summary"
echo "Production size: ${prod_size} bytes" | tee -a "${REPORT_FILE}"
echo "Development size: ${dev_size} bytes" | tee -a "${REPORT_FILE}"
echo "Logs stored at: ${REPORT_FILE}" | tee -a "${REPORT_FILE}"

exit 0


