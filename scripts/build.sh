#!/usr/bin/env bash
set -euo pipefail

# Nextcloud Door Estimator Build Script
# Usage: ./scripts/build.sh [prod|dev-build|watch|-h|--help]
# Defaults to production build if no argument is given.

# --- Helper Functions ---
error_exit() {
  echo "Error: $1" >&2
  exit 1
}

info() {
  echo "[INFO] $1"
}

# Usage/help
usage() {
  cat <<'EOF'
Nextcloud Door Estimator Build Script
Usage: ./scripts/build.sh [prod|dev-build|watch|-h|--help]
Modes:
  prod       Production build (default). Runs 'npm run build', then validates the Vite manifest and copies the bundle to js/door-estimator.js.
  dev-build  Single development build. Runs 'npm run dev-build', then runs the same validation/copy steps as prod.
  watch      Performs an initial 'npm run dev-build', then starts 'npm run watch' and continuously re-copies js/door-estimator.js after each rebuild by monitoring the manifest.

Manifest detection:
  Searches multiple candidates: js/manifest.json, dist/manifest.json, build/manifest.json, .vite/manifest.json, plus $outDir/manifest.json if configured in vite.config.js.
  Falls back to scanning within js/, dist/, build/, and $outDir for any manifest.json. Selects the first manifest that yields an existing entry file.
  outDir detection is best-effort (parses vite.config.js or uses NC_VITE_OUT_DIR/VITE_OUT_DIR env vars); recommend setting the environment variable if detection fails.
EOF
}

# --- Validate Prerequisites ---
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
readonly SCRIPT_DIR
readonly PROJECT_ROOT

cd "$PROJECT_ROOT"

if [ ! -f "$PROJECT_ROOT/package.json" ]; then
  error_exit "package.json not found in project root."
fi
if [ ! -f "$PROJECT_ROOT/scripts/setup.sh" ]; then
  error_exit "scripts/setup.sh not found."
fi
if [ ! -f "$PROJECT_ROOT/vite.config.js" ]; then
  error_exit "vite.config.js not found in project root."
fi

# --- Run Setup Script (guarded to prevent recursion) ---
if [ -z "${NC_SKIP_SETUP:-}" ]; then
  info "Running setup script..."
  if [ ! -x "$PROJECT_ROOT/scripts/setup.sh" ]; then chmod +x "$PROJECT_ROOT/scripts/setup.sh"; fi
  # Pass NC_SKIP_SETUP=1 to prevent recursion if setup.sh invokes build.sh
  NC_SKIP_SETUP=1 "$PROJECT_ROOT/scripts/setup.sh" || error_exit "Setup script failed."
fi

# --- Optional TypeScript Config (non-blocking) ---
if [ ! -f tsconfig.json ]; then
  echo "Warning: tsconfig.json not found; continuing because Vite can build JS-only projects." >&2
fi

# --- Install Dependencies ---
if ! command -v npm >/dev/null 2>&1; then
  error_exit "npm is required but not found. Please install Node.js/npm and try again."
fi

# Optional: enforce npm major version 10+
NPM_MAJOR=$(npm -v | cut -d. -f1)
if [ -z "$NPM_MAJOR" ] || [ "$NPM_MAJOR" -lt 10 ]; then
  error_exit "npm v10 or higher is required. Found npm $(npm -v)."
fi

info "Running npm install..."
npm install || error_exit "npm install failed."

# --- Mode handling: prod (default), dev-build, watch ---
MODE="${1:-prod}"
case "$MODE" in
  -h|--help)
    usage
    exit 0
    ;;
  prod|production)
    info "Production build selected."
    info "Running Vite production build (npm run build)..."
    npm run build || error_exit "Vite build failed."
    ;;
  dev|development|dev-build)
    info "Development single build selected."
    info "Running Vite development build (npm run dev-build)..."
    npm run dev-build || npm run build -- --mode development || error_exit "Vite development build failed."
    ;;
  watch)
    info "Watch mode selected."
    info "Running initial development build (npm run dev-build)..."
    npm run dev-build || error_exit "Initial development build failed."
    ;;
  *)
    usage
    error_exit "Unknown mode: $MODE"
    ;;
esac

# --- Resolve built main entry via manifest candidates and copy to js/door-estimator.js ---

readonly NODE_CMD
if ! command -v node >/dev/null 2>&1; then
  if ! command -v nodejs >/dev/null 2>&1; then
    echo "Error: Node.js is not installed (tried 'node' and 'nodejs'). Please install Node.js and try again." >&2
    exit 1
  else
    NODE_CMD=nodejs
  fi
else
  NODE_CMD=node
fi
readonly NODE_CMD

NODE_VERSION="$($NODE_CMD --version 2>/dev/null | sed 's/^v//')"
NODE_MAJOR=$(echo "$NODE_VERSION" | cut -d. -f1)

# Validate NODE_MAJOR is numeric
case "$NODE_MAJOR" in
  '' | *[!0-9]*) echo "Error: Could not parse Node.js major version from '$NODE_VERSION'. Please ensure Node.js is properly installed." >&2; exit 1 ;;
esac
if [ "$NODE_MAJOR" -lt 20 ]; then
  echo "Error: Node.js v20 or higher is required (found v$NODE_MAJOR). Please install a compatible version and try again." >&2
  exit 1
fi

# Updated trap to include cleanup
cleanup() {
  if [ -n "${WATCH_PID:-}" ] && kill -0 "$WATCH_PID" 2>/dev/null; then
    kill "$WATCH_PID" 2>/dev/null
    wait "$WATCH_PID" 2>/dev/null
    info "Watch process terminated."
  fi
}
trap 'cleanup; status=$?; if [ "$status" -ne 0 ]; then echo "Build failed" >&2; fi; exit "$status"' EXIT INT TERM

# Derive outDir from vite.config.js when present (best-effort, non-fatal)
derive_out_dir() {
  local out_dir="${NC_VITE_OUT_DIR:-${VITE_OUT_DIR:-}}"
  if [ -n "$out_dir" ]; then
    echo "$out_dir"
    return
  fi
  # Fallback to parsing vite.config.js using Node.js dynamic import with dual ESM/CJS support
  node -e "
    try {
      const fs = require('fs');
      const path = require('path');
      const configPath = path.join(process.cwd(), 'vite.config.js');
      if (fs.existsSync(configPath)) {
        const configModule = require(configPath);
        const config = typeof configModule === 'function' ? configModule() : configModule;
        console.log(config.build?.outDir || '');
      } else {
        console.log('');
      }
    } catch (e) {
      try {
        // Try ESM
        import('fs').then(fs => import('path').then(path => {
          const configPath = path.default.join(process.cwd(), 'vite.config.js');
          if (fs.default.existsSync(configPath)) {
            import('./vite.config.js').then(configModule => {
              const config = typeof configModule.default === 'function' ? configModule.default() : configModule.default;
              console.log(config.build?.outDir || '');
            }).catch(() => console.log(''));
          } else {
            console.log('');
          }
        }));
      } catch (e2) {
        console.log('');
      }
    }
  " 2>/dev/null || echo ''
}

OUT_DIR=$(derive_out_dir)
readonly OUT_DIR

# Build candidate manifest list (ordered)
build_manifest_candidates() {
  shopt -s nullglob
  local candidates=()
  if [ -n "$OUT_DIR" ]; then
    candidates+=("$OUT_DIR/manifest.json" "$OUT_DIR/.vite/manifest.json" "$OUT_DIR/assets/manifest.json")
  fi
  # Common defaults
  candidates+=("js/manifest.json" "dist/manifest.json" "build/manifest.json" ".vite/manifest.json")

  # As a fallback, scan common dirs for manifest.json (shallow)
  local search_dirs=("js" "dist" "build")
  if [ -n "$OUT_DIR" ]; then
    search_dirs+=("$OUT_DIR")
  fi
  for dir in "${search_dirs[@]}"; do
    [ -n "$dir" ] || continue
    if [ -d "$dir" ]; then
      # Safe null-delimited read to handle spaces in filenames
      while IFS= read -r -d '' f; do
        candidates+=("$f")
      done <<EOF
$(find "$dir" -maxdepth 3 -type f -name manifest.json -print0 2>/dev/null)
EOF
    fi
  done
  shopt -u nullglob
  printf '%s\n' "${candidates[@]}"
}

# Resolve current entry from a given manifest (sets ENTRY_FILE and DIST_FILE)
resolve_entry_from_manifest() {
  local manifest="$1"
  local entry_file=""

  # Prefer jq if available
  if command -v jq >/dev/null 2>&1; then
    entry_file=$(jq -r '."src/main.ts".file // ."src/main.js".file // (to_entries[] | select(.value.isEntry == true) | .value.file) // empty' "$manifest" 2>/dev/null || true)
  fi

  # Node-based fallback with dual ESM/CJS
  if [ -z "$entry_file" ]; then
    entry_file=$(node -e "
      try {
        const fs = require('fs');
        const m = JSON.parse(fs.readFileSync(process.argv[1], 'utf8'));
        const e = (m['src/main.ts']?.file) || (m['src/main.js']?.file) || (Object.values(m).find(v => v?.isEntry)?.file) || '';
        process.stdout.write(e || '');
      } catch (e) {
        try {
          // Try ESM
          import('fs').then(fs => {
            fs.default.readFile(process.argv[1], 'utf8').then(data => {
              const m = JSON.parse(data);
              const e = (m['src/main.ts']?.file) || (m['src/main.js']?.file) || (Object.values(m).find(v => v?.isEntry)?.file) || '';
              process.stdout.write(e || '');
            }).catch(() => process.stdout.write(''));
          });
        } catch (e2) {}
      }
    " "$manifest" 2>/dev/null || true)
  fi

  if [ -z "$entry_file" ]; then
    echo ""
    return 1
  fi

  local manifest_dir=$(dirname "$manifest")
  local dist_file="$manifest_dir/$entry_file"
  if [ -f "$dist_file" ]; then
    echo "$dist_file"
    return 0
  else
    echo ""
    return 1
  fi
}

# Copy bundle (and map if present) to js/door-estimator.js
copy_bundle_and_map() {
  local dist_file="$1"
  mkdir -p js
  cp -f "$dist_file" js/door-estimator.js

  # Copy source map if present
  if [ -f "${dist_file}.map" ]; then
    cp -f "${dist_file}.map" js/door-estimator.js.map
  fi

  local bundle_path="js/door-estimator.js"
  if [ ! -f "$bundle_path" ]; then
    error_exit "Expected bundle $bundle_path not found after copy."
  fi
  info "Bundle updated at $bundle_path"
}

# Resolve a valid manifest and copy
resolve_dist_and_manifest() {
  local selected_manifest=""
  local dist_file=""

  local candidates=$(build_manifest_candidates)
  while IFS= read -r manifest; do
    [ -f "$manifest" ] || continue
    local cand_dist=$(resolve_entry_from_manifest "$manifest")
    if [ $? -eq 0 ] && [ -n "$cand_dist" ]; then
      selected_manifest="$manifest"
      dist_file="$cand_dist"
      printf '%s\n%s\n' "$selected_manifest" "$dist_file"
      return 0
    fi
  done <<< "$candidates"

  # Fallback when no manifest is usable:
  # Scan common asset directories for JS bundles, prefer main*.js then newest *.js
  local fallback_js=""
  local search_dirs=("js/assets" "dist/assets")
  if [ -n "$OUT_DIR" ]; then
    search_dirs+=("$OUT_DIR/assets")
  fi

  # Priority 1: main*.js if present
  for dir in "${search_dirs[@]}"; do
    [ -d "$dir" ] || continue
    local cand=$(ls -1t "$dir"/main*.js 2>/dev/null | head -n 1 || true)
    if [ -n "$cand" ]; then
      fallback_js="$cand"
      break
    fi
  done

  # Priority 2: newest *.js across all candidate dirs
  if [ -z "$fallback_js" ]; then
    fallback_js=$(node -e '
      try {
        const fs = require("fs");
        const path = require("path");
        const dirs = process.argv.slice(1);
        let best = null, bestTime = -1;
        for (const d of dirs) {
          for (const e of fs.readdirSync(d)) {
            if (!e.endsWith(".js")) continue;
            const p = path.join(d, e);
            const t = fs.statSync(p).mtimeMs;
            if (t > bestTime) { bestTime = t; best = p; }
          }
        }
        if (best) process.stdout.write(best);
      } catch {}
      try {
        // ESM fallback
        import("fs").then(fs => import("path").then(path => {
          const dirs = process.argv.slice(1);
          let best = null, bestTime = -1;
          // ... similar logic
          if (best) process.stdout.write(best);
        }));
      } catch (e) {}
    ' "${search_dirs[@]}" 2>/dev/null || true)
  fi

  if [ -n "$fallback_js" ] && [ -f "$fallback_js" ]; then
    dist_file="$fallback_js"
    info "No manifest found; using fallback asset: $dist_file"
    printf '%s\n%s\n' "" "$dist_file"
    return 0
  fi

  return 1
}

watch_mode() {
  local selected_manifest="$1"
  local dist_file="$2"
  local last_hash=""

  if [ -n "$selected_manifest" ] && [ -f "$selected_manifest" ]; then
    last_hash="$(cksum "$selected_manifest" 2>/dev/null | awk '{print $1}') || $(sha1sum "$selected_manifest" 2>/dev/null | awk '{print $1}')"
    info "Watching manifest: $selected_manifest"
  else
    last_hash=""
    info "No manifest available to watch; attempting to resolve..."
  fi

  local has_inotifywait=0
  if command -v inotifywait >/dev/null 2>&1; then
    has_inotifywait=1
  fi

  while kill -0 "$WATCH_PID" 2>/dev/null; do
    if [ "$has_inotifywait" -eq 1 ]; then
      inotifywait -q -e modify "$selected_manifest" 2>/dev/null || true
    else
      sleep 1
    fi

    # Re-resolve if the selected manifest disappears or is unset
    if [ -z "$selected_manifest" ] || [ ! -f "$selected_manifest" ]; then
      info "Manifest missing or unset; attempting to re-resolve..."
      mapfile -t res < <(resolve_dist_and_manifest 2>/dev/null)
      if [ ${#res[@]} -ge 2 ]; then
        selected_manifest="${res[0]}"
        dist_file="${res[1]}"
        if [ -n "$dist_file" ]; then
          copy_bundle_and_map "$dist_file"
        fi
      fi
      if [ -n "$selected_manifest" ] && [ -f "$selected_manifest" ]; then
        last_hash="$(cksum "$selected_manifest" 2>/dev/null | awk '{print $1}') || $(sha1sum "$selected_manifest" 2>/dev/null | awk '{print $1}')"
        info "Watching manifest: $selected_manifest"
      else
        last_hash=""
        info "Continuing in fallback mode (no manifest found)."
      fi
      continue
    fi

    local new_hash="$(cksum "$selected_manifest" 2>/dev/null | awk '{print $1}') || $(sha1sum "$selected_manifest" 2>/dev/null | awk '{print $1}')"
    if [ -n "$new_hash" ] && [ "$new_hash" != "$last_hash" ]; then
      last_hash="$new_hash"
      info "Detected rebuild via manifest change ($selected_manifest). Refreshing bundle..."
      # Re-resolve (in case entry path changed) and copy again
      mapfile -t res < <(resolve_dist_and_manifest 2>/dev/null)
      if [ ${#res[@]} -ge 2 ]; then
        selected_manifest="${res[0]}"
        dist_file="${res[1]}"
        if [ -n "$dist_file" ]; then
          copy_bundle_and_map "$dist_file"
        fi
      fi
    fi
  done

  # Propagate exit status from watch process
  wait "$WATCH_PID"
  exit $?
}

# Initial resolve after build
mapfile -t res < <(resolve_dist_and_manifest 2>/dev/null || { error_exit "Could not resolve a valid built file from any manifest (searched common locations and vite.config.js outDir)."; })
selected_manifest="${res[0]}"
dist_file="${res[1]}"
if [ -z "$dist_file" ]; then
  error_exit "Could not resolve a valid built file from any manifest (searched common locations and vite.config.js outDir)."
fi
copy_bundle_and_map "$dist_file"

# In watch mode, start long-running watch after initial validation/copy
if [ "$MODE" = "watch" ]; then
  info "Starting watch (npm run watch) and enabling auto-recopy on rebuilds..."
  npm run watch &
  WATCH_PID=$!
  watch_mode "$selected_manifest" "$dist_file"
fi