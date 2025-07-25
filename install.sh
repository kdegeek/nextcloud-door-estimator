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

# Remove any previous installation
if [ -d "$APP_DIR" ]; then
    print_warning "Existing installation found at $APP_DIR. Removing..."
    rm -rf "$APP_DIR"
fi

# Clone the repository directly into the NextCloud apps directory
print_status "Cloning repository into $APP_DIR..."
if git clone "$GITHUB_REPO" "$APP_DIR"; then
    print_success "Repository cloned successfully"
else
    print_error "Failed to clone repository"
    exit 1
fi

# PHP dependencies are already bundled in vendor/
print_status "All PHP dependencies are already bundled in the vendor/ directory."
print_status "Advanced PDF features are always available—no additional setup required."

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
print_status "1. Import your pricing data (see docs/PRICING_DATA_SETUP.md)"
print_status "2. Access the app through your NextCloud interface"
print_status "3. Start creating professional door and hardware quotes!"