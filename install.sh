#!/bin/bash

# Door Estimator NextCloud App - One-Click Installer
# This script downloads and installs the Door Estimator app from GitHub

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
TEMP_DIR="/tmp/door-estimator-install"

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
echo "Door Estimator NextCloud App - One-Click Installer"
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

# Remove temp directory if it exists
rm -rf "$TEMP_DIR" 2>/dev/null || true

# Clone the repository
print_status "Downloading Door Estimator from GitHub..."
if git clone "$GITHUB_REPO" "$TEMP_DIR"; then
    print_success "Repository downloaded successfully"
else
    print_error "Failed to clone repository"
    exit 1
fi

# Run the setup script from the downloaded code
if [ -f "$TEMP_DIR/scripts/setup.sh" ]; then
    print_status "Running setup script..."
    cd "$TEMP_DIR"
    bash scripts/setup.sh
else
    print_error "Setup script not found in repository"
    exit 1
fi

# Clean up
rm -rf "$TEMP_DIR"

print_success "Installation completed!"
echo ""
print_status "Next steps:"
print_status "1. Import your pricing data (see docs/PRICING_DATA_SETUP.md)"  
print_status "2. Access the app through your NextCloud interface"
print_status "3. Start creating professional door and hardware quotes!"