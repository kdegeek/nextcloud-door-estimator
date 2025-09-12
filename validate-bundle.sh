#!/usr/bin/env bash

set -euo pipefail

# validate-bundle.sh
# Thoroughly inspects the generated js/door-estimator.js bundle for integrity and integration readiness.

ROOT_DIR="$(cd "$(dirname "$0")" && pwd)"

# Configurable variables for paths and filenames
BUNDLE_PATH="${BUNDLE_PATH:-${ROOT_DIR}/js/door-estimator.js}"
TMP_DIR="${TMP_DIR:-${ROOT_DIR}/tmp}"
# REPORT_TMP is created after TMP_DIR is validated and created
# REPORT_TMP=$(mktemp "${TMP_DIR}/bundle-validation.XXXXXX")

# Validate that BUNDLE_PATH and TMP_DIR reside within ROOT_DIR (defense-in-depth)
ROOT_REAL=$(python3 -c 'import os,sys; print(os.path.realpath(sys.argv[1]))' "${ROOT_DIR}")
BUNDLE_REAL=$(python3 -c 'import os,sys; print(os.path.realpath(sys.argv[1]))' "${BUNDLE_PATH}")
TMP_REAL=$(python3 -c 'import os,sys; print(os.path.realpath(sys.argv[1]))' "${TMP_DIR}")

case "${BUNDLE_REAL}" in
  "${ROOT_REAL}"|${ROOT_REAL}/*) ;;
  *)
    echo "[FAIL]  BUNDLE_PATH is outside the allowed directory: ${BUNDLE_PATH}" >&2
    exit 1
    ;;
esac

case "${TMP_REAL}" in
  "${ROOT_REAL}"|${ROOT_REAL}/*) ;;
  *)
    echo "[FAIL]  TMP_DIR is outside the allowed directory: ${TMP_DIR}" >&2
    exit 1
    ;;
esac

mkdir -p "${TMP_DIR}"
REPORT_TMP=$(mktemp "${TMP_DIR}/bundle-validation.XXXXXX")

passes=0
fails=0
warnings=0

note() {
  echo "[INFO]  $*" | tee -a "${REPORT_TMP}"
}

ok() {
  echo "[PASS]  $*" | tee -a "${REPORT_TMP}"
  passes=$((passes+1))
}

warn() {
  echo "[WARN]  $*" | tee -a "${REPORT_TMP}"
  warnings=$((warnings+1))
}

fail() {
  echo "[FAIL]  $*" | tee -a "${REPORT_TMP}"
  fails=$((fails+1))
}

header() {
  echo "" | tee -a "${REPORT_TMP}"
  echo "===== $* =====" | tee -a "${REPORT_TMP}"
}

header "Bundle Validation"
note "Bundle path: ${BUNDLE_PATH}"

# 1) File existence and basic checks
header "Existence & Basics"
if [[ -f "${BUNDLE_PATH}" ]]; then
  ok "Bundle file exists"
else
  fail "Bundle file does not exist at ${BUNDLE_PATH}"
  echo "" | tee -a "${REPORT_TMP}"
  echo "Summary: ${passes} passed, ${warnings} warnings, ${fails} failed" | tee -a "${REPORT_TMP}"
  exit 1
fi

# Size checks: use Node if available, otherwise fall back to wc -c
if command -v node >/dev/null 2>&1; then
  raw_size=$(node -e "const fs=require('fs');try{console.log(fs.statSync(process.argv[1]).size)}catch(e){console.log(0)}" "${BUNDLE_PATH}")
  size_bytes=$(echo "${raw_size}" | tr -d '[:space:]')
  if [[ ! "${size_bytes}" =~ ^[0-9]+$ ]]; then
    size_bytes=$(wc -c < "${BUNDLE_PATH}" | tr -d '[:space:]')
  fi
else
  size_bytes=$(wc -c < "${BUNDLE_PATH}" | tr -d '[:space:]')
fi
note "Bundle size: ${size_bytes} bytes"
if [[ "${size_bytes}" -gt 1024 ]]; then
  ok "Bundle size is greater than 1KB"
else
  fail "Bundle size seems too small; expected a real build artifact (>1KB)"
fi

# 2) Content validation
header "Content Validation"

# Single awk pass to check all patterns efficiently
pattern_output=$(awk '
/createApp/ { createApp=1 }
($0 ~ /mount[ \t]*\(/ && $0 ~ /#door-estimator-app/) { mount=1 }
(/price|Price|estimate|Estimate/) { pricing=1 }
(/NcApp|NcAppContent|nextcloud/) { nc=1 }
(/(^|[^A-Za-z_])t\(/) { t=1 }
(/(^|[^A-Za-z_])n\(/) { n=1 }
(/__vite/) { vite=1 }
(/# sourceMappingURL=/) { sourcemap=1 }
# Simplified static ESM import/export heuristic:
# - Flags classic static import/export declarations commonly left by mis-bundled files
# - Ignores dynamic import(...) calls
# - This is a heuristic; ESM-targeted bundles may legitimately contain these
((/^[ \t]*import[ \t]/ && $0 !~ /import\(/) || /^[ \t]*export[ \t]/) { import_export=1 }
END {
  if (createApp) print "createApp:1"
  if (mount) print "mount:1"
  if (pricing) print "pricing:1"
  if (nc) print "nc:1"
  if (t) print "t:1"
  if (n) print "n:1"
  if (vite) print "vite:1"
  if (sourcemap) print "sourcemap:1"
  if (import_export) print "import_export:1"
}
' "${BUNDLE_PATH}")

# Check results from awk output
if echo "${pattern_output}" | grep -q "createApp:1"; then
  ok "Contains Vue createApp"
else
  fail "Missing Vue createApp"
fi

if echo "${pattern_output}" | grep -q "mount:1"; then
  ok "Contains mount('#door-estimator-app')"
else
  fail "Missing mount('#door-estimator-app')"
fi

if echo "${pattern_output}" | grep -q "pricing:1"; then
  ok "Contains pricing-related tokens (heuristic for utils integration)"
else
  warn "Could not confidently detect pricing utils by heuristics"
fi

if echo "${pattern_output}" | grep -q "nc:1"; then
  ok "Hints of Nextcloud Vue components present"
else
  warn "Could not detect Nc* component tokens; may be minified"
fi

if echo "${pattern_output}" | grep -q "t:1"; then
  ok "Found translation call t(…)"
else
  warn "No obvious t(…) calls detected"
fi

if echo "${pattern_output}" | grep -q "n:1"; then
  ok "Found pluralization call n(…)"
else
  warn "No obvious n(…) calls detected"
fi

# 3) Bundle structure analysis
header "Structure Analysis"

# Vite runtime markers frequently appear
if echo "${pattern_output}" | grep -q "vite:1"; then
  ok "Vite runtime markers present"
else
  warn "No __vite markers detected; bundle may still be valid"
fi

# Static import/export check:
# Purpose: detect common static ESM import/export statements that may indicate the bundle
# was not fully bundled/transpiled. If the final artifact intentionally targets ESM,
# the presence of these may be acceptable and will only trigger a warning.
if echo "${pattern_output}" | grep -q "import_export:1"; then
  warn "Detected static import/export statements. If the bundle targets ESM this may be acceptable."
else
  ok "No static import/export statements detected"
fi

# Check for source map reference (optional)
if echo "${pattern_output}" | grep -q "sourcemap:1"; then
  note "Source map reference found"
else
  note "No source map reference; acceptable depending on build mode"
fi

# 4) Integration readiness
header "Integration Readiness"

# Basic syntax check using node (best-effort parse)
if command -v node >/dev/null 2>&1; then
  if node -e "const fs=require('fs'); const code=fs.readFileSync(process.argv[1],'utf8'); try{ new Function(code); process.exit(0);}catch(e){ process.exit(1);} " "${BUNDLE_PATH}" >/dev/null 2>&1; then
    ok "Bundle parsed by Node's Function constructor (best-effort)"
  else
    warn "Bundle did not parse with Function constructor (may be ESM or syntax outside Function)."
  fi
else
  warn "Node not available to perform parse check"
fi

# 5) Performance checks
header "Performance Checks"

if [[ "${size_bytes}" -gt 800000 ]]; then
  warn "Bundle size is relatively large (>800KB). Consider checking dependencies/minification."
else
  ok "Bundle size within reasonable range"
fi

echo "" | tee -a "${REPORT_TMP}"
echo "Summary: ${passes} passed, ${warnings} warnings, ${fails} failed" | tee -a "${REPORT_TMP}"

[[ ${fails} -eq 0 ]] || exit 2
exit 0


