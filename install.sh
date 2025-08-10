#!/bin/bash

# Door Estimator NextCloud App - Installer (Updated July 2025)
# Installs the Door Estimator app directly into your NextCloud instance.

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
NEXTCLOUD_ROOT="/var/www/nextcloud"
APP_NAME="door_estimator"
GITHUB_REPO="https://github.com/kdegeek/nextcloud-door-estimator.git"
APP_DIR="$NEXTCLOUD_ROOT/apps/$APP_NAME"
WEB_USER="www-data"

# Function to print colored output
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    print_error "This script must be run as root (use sudo)"
    exit 1
fi

# Check if git is available
if ! command -v git >/dev/null 2>&1; then
    print_error "Git is required but not installed"
    print_status "Install git first: apt-get install git"
    exit 1
fi

# Check NextCloud installation
if [ ! -f "$NEXTCLOUD_ROOT/occ" ]; then
    print_error "NextCloud not found at $NEXTCLOUD_ROOT"
    print_status "Please set NEXTCLOUD_ROOT variable or install NextCloud first"
    exit 1
fi

echo "=================================================="
echo "Door Estimator NextCloud App - Installer"
echo "=================================================="
echo ""
print_status "This will install the Door Estimator app from GitHub"
print_status "Repository: $GITHUB_REPO"
echo ""

# Confirm installation
read -p "Continue with installation? (y/N): " -r
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "Installation cancelled."
    exit 0
fi

# Remove any previous installation, preserving pricing JSON data if present
PRESERVED_JSON=""
if [ -d "$APP_DIR" ]; then
    print_warning "Existing installation found at $APP_DIR."
    if [ -f "$APP_DIR/scripts/extracted_pricing_data.json" ]; then
        PRESERVED_JSON="/tmp/extracted_pricing_data_$(date +%Y%m%d_%H%M%S).json"
        print_status "Preserving pricing data: $APP_DIR/scripts/extracted_pricing_data.json"
        cp "$APP_DIR/scripts/extracted_pricing_data.json" "$PRESERVED_JSON" && print_success "Pricing data preserved at $PRESERVED_JSON" || print_error "Failed to preserve pricing data"
    else
        print_status "No pricing data found to preserve."
    fi
    print_status "Removing old installation at $APP_DIR..."
    rm -rf "$APP_DIR" && print_success "Old installation removed." || print_error "Failed to remove old installation."
fi

# Clone the repository directly into the NextCloud apps directory
print_status "Cloning repository into $APP_DIR..."
if git clone "$GITHUB_REPO" "$APP_DIR"; then
    print_success "Repository cloned successfully"
else
    print_error "Failed to clone repository"
    exit 1
fi

# Restore preserved pricing JSON data if it exists
if [ -n "$PRESERVED_JSON" ] && [ -f "$PRESERVED_JSON" ]; then
    print_status "Restoring preserved pricing data to $APP_DIR/scripts/extracted_pricing_data.json"
    mkdir -p "$APP_DIR/scripts"
    cp "$PRESERVED_JSON" "$APP_DIR/scripts/extracted_pricing_data.json" && print_success "Pricing data restored." || print_error "Failed to restore pricing data."
    rm -f "$PRESERVED_JSON"
else
    print_status "No preserved pricing data to restore."
fi

# --- Node.js v20+ and npm v10+ Auto-Install Logic ---
ensure_nodejs() {
    if command -v node >/dev/null 2>&1; then
        NODE_VERSION=$(node --version | sed 's/v//')
        NODE_MAJOR=$(echo "$NODE_VERSION" | cut -d. -f1)
        if [ "$NODE_MAJOR" -ge 20 ]; then
            return 0
        else
            print_error "Node.js version $NODE_VERSION detected. Node.js v20+ is required."
            print_status "Please upgrade Node.js to v20 or newer: https://nodejs.org/en/download"
            exit 1
        fi
    fi

    print_warning "Node.js v20+ is required but not found. Attempting automatic installation..."

    # Check if running as root
    if [ "$EUID" -ne 0 ]; then
        print_error "Node.js is missing and this script is not running as root."
        print_status "Please install Node.js v20+ manually: https://nodejs.org/en/download or via your package manager."
        exit 1
    fi

    # Detect Debian/Ubuntu
    if [ -f /etc/os-release ]; then
        . /etc/os-release
        if [[ "$ID" == "debian" || "$ID" == "ubuntu" || "$ID_LIKE" == *"debian"* ]]; then
            print_status "Detected Debian/Ubuntu. Installing Node.js v20 LTS using apt-get..."
            apt-get update && \
            curl -fsSL https://deb.nodesource.com/setup_20.x | bash - && \
            apt-get install -y nodejs
            if command -v node >/dev/null 2>&1; then
                NODE_VERSION=$(node --version | sed 's/v//')
                NODE_MAJOR=$(echo "$NODE_VERSION" | cut -d. -f1)
                if [ "$NODE_MAJOR" -ge 20 ]; then
                    print_success "Node.js v20+ installed successfully."
                    return 0
                else
                    print_error "Automatic Node.js installation failed or installed version is too old."
                    print_status "Please install Node.js v20+ manually: https://nodejs.org/en/download"
                    exit 1
                fi
            else
                print_error "Automatic Node.js installation failed."
                print_status "Please install Node.js v20+ manually: https://nodejs.org/en/download"
                exit 1
            fi
        fi
    fi

    print_error "Node.js is missing and automatic installation is only supported on Debian/Ubuntu as root."
    print_status "Please install Node.js v20+ manually: https://nodejs.org/en/download"
    exit 1
}

ensure_nodejs

# --- Build Vue 3 Frontend ---
print_status "Checking for Node.js v20+ and npm v10+ (required for frontend build)..."
if ! command -v node >/dev/null 2>&1; then
    print_error "Node.js is required to build the frontend but was not found."
    print_status "Please install Node.js v20+ and rerun this script, or build manually with: cd $APP_DIR && sh scripts/build.sh"
    exit 1
fi
NODE_VERSION=$(node --version | sed 's/v//')
NODE_MAJOR=$(echo "$NODE_VERSION" | cut -d. -f1)
if [ "$NODE_MAJOR" -lt 20 ]; then
    print_error "Node.js version $NODE_VERSION detected. Node.js v20+ is required."
    print_status "Please upgrade Node.js to v20 or newer: https://nodejs.org/en/download"
    exit 1
fi
if ! command -v npm >/dev/null 2>&1; then
    print_error "npm is required to build the frontend but was not found."
    print_status "Please install npm and rerun this script, or build manually with: cd $APP_DIR && sh scripts/build.sh"
    exit 1
fi
NPM_VERSION=$(npm --version 2>/dev/null || echo "unknown")
NPM_MAJOR=$(echo "$NPM_VERSION" | cut -d. -f1)
if [ "$NPM_MAJOR" -lt 10 ]; then
    print_error "npm version $NPM_VERSION detected. npm v10+ is required."
    print_status "Please upgrade npm to v10 or newer: https://www.npmjs.com/get-npm"
    exit 1
fi

print_status "Building Vue 3 frontend (npm install + webpack build)..."
cd "$APP_DIR"
sh scripts/build.sh || { print_error "Frontend build failed. See above for details."; exit 1; }
cd - >/dev/null

# PHP dependencies are already bundled in vendor/
print_status "All PHP dependencies are already bundled in the vendor/ directory."
print_status "You do NOT need to run composer install. Advanced PDF features are always available—no additional setup required."

# Set permissions
print_status "Setting file permissions..."
chown -R $WEB_USER:$WEB_USER "$APP_DIR"
find "$APP_DIR" -type d -exec chmod 755 {} \;
find "$APP_DIR" -type f -exec chmod 644 {} \;
if [ -d "$APP_DIR/scripts" ]; then
    chmod +x "$APP_DIR/scripts"/*.sh 2>/dev/null || true
    chmod +x "$APP_DIR/scripts"/*.py 2>/dev/null || true
fi
print_success "Permissions set successfully"

# Enable the app
print_status "Enabling Door Estimator application in NextCloud..."
sudo -u $WEB_USER php "$NEXTCLOUD_ROOT/occ" app:enable $APP_NAME && print_success "Application enabled" || print_warning "App may already be enabled"

echo ""
print_success "Installation completed!"
echo ""
print_status "Next steps:"
if [ ! -f "$APP_DIR/scripts/extracted_pricing_data.json" ]; then
    print_warning "No pricing data file found in scripts/. You can:"
    print_status "1. Download a sample pricing data file from the project repository:"
    print_status "   wget https://raw.githubusercontent.com/kdegeek/nextcloud-door-estimator/main/scripts/extracted_pricing_data.json -O $APP_DIR/scripts/extracted_pricing_data.json"
    print_status "2. Or follow docs/PRICING_DATA_SETUP.md to create your own."
else
    print_status "1. Pricing data file detected. You may import it using the app interface or occ command."
fi
print_status "2. Access the app through your NextCloud interface"
print_status "3. Start creating professional door and hardware quotes!"