#!/bin/sh

# Node.js version validation (must be v16 or higher)
if ! command -v node >/dev/null 2>&1; then
  echo "Error: Node.js v16 or higher is required. Please install a compatible version and try again." >&2
  exit 1
fi

NODE_VERSION=$(node --version 2>/dev/null | sed 's/^v//')
NODE_MAJOR=$(echo "$NODE_VERSION" | cut -d. -f1)

if [ -z "$NODE_MAJOR" ] || [ "$NODE_MAJOR" -lt 16 ]; then
  echo "Error: Node.js v16 or higher is required. Please install a compatible version and try again." >&2
  exit 1
fi

# Nextcloud Door Estimator Build Script
# Usage: ./scripts/build.sh [dev|prod]
# Defaults to production build if no argument is given.

set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

cd "$PROJECT_ROOT"

# --- Helper Functions ---
error_exit() {
  echo "Error: $1" >&2
  exit 1
}

info() {
  echo "[INFO] $1"
}

# --- Validate Prerequisites ---
[ -f package.json ] || error_exit "package.json not found in project root."
[ -f tsconfig.json ] || error_exit "tsconfig.json not found in project root."
[ -f scripts/setup.sh ] || error_exit "scripts/setup.sh not found."
if [ ! -f webpack.config.js ]; then
  info "webpack.config.js not found. Skipping build. Please add webpack config for JS bundling."
  exit 0
fi

# --- Run Setup Script ---
info "Running setup script..."
sh scripts/setup.sh || error_exit "Setup script failed."

# --- Install Dependencies ---
info "Running npm install..."
npm install || error_exit "npm install failed."

# --- Determine Build Mode ---
BUILD_MODE="production"
if [ "$1" = "dev" ] || [ "$1" = "build:dev" ]; then
  BUILD_MODE="development"
  info "Development build selected."
else
  info "Production build selected."
fi

# --- Run Webpack Build ---
info "Compiling TypeScript and bundling with webpack..."
npx webpack --mode "$BUILD_MODE" || error_exit "Webpack build failed."

# --- Validate Output ---
BUNDLE_PATH="js/door-estimator.js"
if [ ! -f "$BUNDLE_PATH" ]; then
  error_exit "Expected bundle $BUNDLE_PATH not found. Build may have failed."
fi

info "Build completed successfully. Bundle generated at $BUNDLE_PATH."