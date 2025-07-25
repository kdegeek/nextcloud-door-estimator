#!/bin/bash

# Door Estimator NextCloud App Setup Script
# This script automates the installation process

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
APP_DIR="$NEXTCLOUD_ROOT/apps/$APP_NAME"
WEB_USER="www-data"
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

# Function to check if command exists
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# Function to check if running as root
check_root() {
    if [ "$EUID" -ne 0 ]; then
        print_error "This script must be run as root (use sudo)"
        exit 1
    fi
}

# Function to check NextCloud installation
check_nextcloud() {
    print_status "Checking NextCloud installation..."
    
    if [ ! -d "$NEXTCLOUD_ROOT" ]; then
        print_error "NextCloud directory not found at $NEXTCLOUD_ROOT"
        exit 1
    fi
    
    if [ ! -f "$NEXTCLOUD_ROOT/occ" ]; then
        print_error "NextCloud occ command not found at $NEXTCLOUD_ROOT/occ"
        exit 1
    fi
    
    # Check if NextCloud is accessible
    if ! sudo -u $WEB_USER php "$NEXTCLOUD_ROOT/occ" status >/dev/null 2>&1; then
        print_error "Cannot access NextCloud via occ command"
        exit 1
    fi
    
    print_success "NextCloud installation verified"
}

# Function to check system requirements
check_requirements() {
    print_status "Checking system requirements..."
    
    # Check PHP version
    PHP_VERSION=$(php -r "echo PHP_VERSION;" 2>/dev/null || echo "0")
    if [ "$(echo "$PHP_VERSION" | cut -d. -f1)" -lt 8 ]; then
        print_error "PHP 8.0+ is required. Current version: $PHP_VERSION"
        exit 1
    fi
    print_success "PHP version: $PHP_VERSION"
    
    # Check required PHP extensions
    REQUIRED_EXTENSIONS=("pdo" "json" "curl" "mbstring" "xml")
    for ext in "${REQUIRED_EXTENSIONS[@]}"; do
        if ! php -m | grep -q "^$ext$"; then
            print_error "Required PHP extension missing: $ext"
            exit 1
        fi
    done
    print_success "Required PHP extensions available"
    
    # Check database connectivity
    if ! sudo -u $WEB_USER php "$NEXTCLOUD_ROOT/occ" db:show-tables >/dev/null 2>&1; then
        print_error "Cannot connect to NextCloud database"
        exit 1
    fi
    print_success "Database connectivity verified"
}

# Function to backup existing installation
backup_existing() {
    if [ -d "$APP_DIR" ]; then
        print_status "Existing installation found - creating backup..."
        BACKUP_DIR="/var/backups/${APP_NAME}_$(date +%Y%m%d_%H%M%S)"
        mkdir -p "/var/backups"
        cp -r "$APP_DIR" "$BACKUP_DIR"
        print_success "Backup created at $BACKUP_DIR"
        print_status "This is an UPDATE - existing app will be replaced with latest version"
    else
        print_status "No existing installation found - this is a fresh INSTALL"
    fi
}

# Function to install the application
install_app() {
    print_status "Installing Door Estimator application..."
    
    # Check if git is available
    if ! command_exists git; then
        print_error "Git is required but not installed"
        exit 1
    fi
    
    # Remove temp directory if it exists
    rm -rf "$TEMP_DIR" 2>/dev/null || true
    
    # Clone the repository
    print_status "Cloning from GitHub repository..."
    if git clone "$GITHUB_REPO" "$TEMP_DIR"; then
        print_success "Repository cloned successfully"
    else
        print_error "Failed to clone repository from $GITHUB_REPO"
        exit 1
    fi
    
    # Create app directory
    mkdir -p "$APP_DIR"
    
    # Copy application files
    print_status "Copying application files..."
    cp -r "$TEMP_DIR"/* "$APP_DIR/"
    
    # Clean up temp directory
    rm -rf "$TEMP_DIR"
    
    print_success "Application files installed from GitHub"
}

# Function to set permissions
set_permissions() {
    print_status "Setting file permissions..."
    
    # Set ownership
    chown -R $WEB_USER:$WEB_USER "$APP_DIR"
    
    # Set directory permissions
    find "$APP_DIR" -type d -exec chmod 755 {} \;
    
    # Set file permissions
    find "$APP_DIR" -type f -exec chmod 644 {} \;
    
    # Make scripts executable
    if [ -d "$APP_DIR/scripts" ]; then
        chmod +x "$APP_DIR/scripts"/*.sh 2>/dev/null || true
        chmod +x "$APP_DIR/scripts"/*.py 2>/dev/null || true
    fi
    
    print_success "Permissions set successfully"
}

# Function to install dependencies
install_dependencies() {
    print_status "Installing dependencies..."
    
    cd "$APP_DIR"
    
    # Install PHP dependencies if composer is available
    if command_exists composer; then
        print_status "Installing PHP dependencies with Composer..."
        sudo -u $WEB_USER composer install --no-dev --optimize-autoloader --no-interaction
        print_success "PHP dependencies installed"
    else
        print_warning "Composer not available - app will work with basic functionality"
    fi
    
    # Check for Python dependencies (optional)
    if command_exists python3 && command_exists pip3; then
        if [ -f "requirements.txt" ]; then
            print_status "Installing Python dependencies..."
            pip3 install -r requirements.txt >/dev/null 2>&1 || print_warning "Some Python dependencies may not have installed"
        fi
    fi
}

# Function to enable the application
enable_app() {
    print_status "Enabling Door Estimator application..."
    
    # Disable app first if it exists
    sudo -u $WEB_USER php "$NEXTCLOUD_ROOT/occ" app:disable $APP_NAME >/dev/null 2>&1 || true
    
    # Enable the app
    if sudo -u $WEB_USER php "$NEXTCLOUD_ROOT/occ" app:enable $APP_NAME; then
        print_success "Application enabled successfully"
    else
        print_error "Failed to enable application"
        exit 1
    fi
}

# Function to import pricing data
import_data() {
    print_status "Checking for pricing data..."
    
    if [ -f "$APP_DIR/scripts/extracted_pricing_data.json" ]; then
        print_status "Found pricing data file, importing..."
        if sudo -u $WEB_USER php "$NEXTCLOUD_ROOT/occ" door-estimator:import-pricing; then
            print_success "Pricing data imported successfully"
        else
            print_error "Failed to import pricing data"
            exit 1
        fi
    else
        print_warning "No pricing data file found in scripts/ directory"
        print_status "To import your pricing data:"
        print_status "1. Place your extracted_pricing_data.json file in $APP_DIR/scripts/"
        print_status "2. Run: sudo -u $WEB_USER php $NEXTCLOUD_ROOT/occ door-estimator:import-pricing"
        print_status "3. Or use the Python extraction script on your Excel file"
    fi
}

# Function to verify installation
verify_installation() {
    print_status "Verifying installation..."
    
    # Check if app is enabled
    if sudo -u $WEB_USER php "$NEXTCLOUD_ROOT/occ" app:list | grep -q "$APP_NAME"; then
        print_success "App is enabled in NextCloud"
    else
        print_error "App is not enabled"
        return 1
    fi
    
    # Check database tables
    PRICING_COUNT=$(sudo -u $WEB_USER php "$NEXTCLOUD_ROOT/occ" db:query "SELECT COUNT(*) as count FROM oc_door_estimator_pricing" --output=json 2>/dev/null | grep -o '"count":"[0-9]*"' | cut -d'"' -f4 || echo "0")
    
    if [ "$PRICING_COUNT" -gt 0 ]; then
        print_success "Database contains $PRICING_COUNT pricing items"
    else
        print_warning "No pricing data found in database"
    fi
    
    # Check file permissions
    if [ -r "$APP_DIR/appinfo/info.xml" ] && [ -r "$APP_DIR/js/door-estimator.js" ]; then
        print_success "Application files are accessible"
    else
        print_error "Some application files are not accessible"
        return 1
    fi
}

# Function to show post-installation instructions
show_instructions() {
    echo ""
    echo "=================================================="
    echo -e "${GREEN}Door Estimator Installation Complete!${NC}"
    echo "=================================================="
    echo ""
    echo "🌐 Access the app:"
    echo "   1. Log in to your NextCloud instance"
    echo "   2. Look for 'Door Estimator' in the app menu"
    echo "   3. Click to start creating quotes"
    echo ""
    echo "🔧 Default Configuration:"
    echo "   - Doors & Inserts markup: 15%"
    echo "   - Frames markup: 12%"
    echo "   - Hardware markup: 18%"
    echo ""
    echo "📊 Pricing Data:"
    echo "   - $PRICING_COUNT items imported across 11 categories"
    echo "   - Covers doors, frames, hardware, inserts, etc."
    echo ""
    echo "🛠️  Administration:"
    echo "   - Use the 'Admin' tab in the app to manage pricing"
    echo "   - Update markups in the estimator interface"
    echo "   - All quotes are saved per user"
    echo ""
    echo "📖 Documentation:"
    echo "   - README.md: Complete user guide"
    echo "   - docs/INSTALLATION.md: Detailed installation guide"
    echo ""
    echo "🔍 Troubleshooting:"
    echo "   - Check NextCloud logs: tail -f $NEXTCLOUD_ROOT/data/nextcloud.log"
    echo "   - Verify permissions: ls -la $APP_DIR"
    echo "   - Test database: sudo -u $WEB_USER php $NEXTCLOUD_ROOT/occ db:show-tables | grep door_estimator"
    echo ""
    if [ "$PRICING_COUNT" -eq 0 ]; then
        echo -e "${YELLOW}⚠️  Note: No pricing data was imported. You may need to run:${NC}"
        echo "   sudo -u $WEB_USER php $NEXTCLOUD_ROOT/occ door-estimator:import-pricing"
        echo ""
    fi
}

# Main execution
main() {
    echo "=================================================="
    echo "Door Estimator NextCloud App Setup"
    echo "=================================================="
    echo ""
    
    check_root
    check_nextcloud
    check_requirements
    backup_existing
    install_app
    set_permissions
    install_dependencies
    enable_app
    import_data
    
    if verify_installation; then
        show_instructions
    else
        print_error "Installation verification failed"
        exit 1
    fi
}

# Handle command line arguments
case "${1:-}" in
    --help|-h)
        echo "Door Estimator Setup Script"
        echo ""
        echo "Usage: $0 [options]"
        echo ""
        echo "Options:"
        echo "  --help, -h     Show this help message"
        echo "  --check        Check system requirements only"
        echo ""
        echo "This script will:"
        echo "  1. Verify system requirements"
        echo "  2. Install the Door Estimator NextCloud app"
        echo "  3. Set proper permissions"
        echo "  4. Import pricing data"
        echo "  5. Enable the application"
        echo ""
        exit 0
        ;;
    --check)
        print_status "Checking system requirements only..."
        check_root
        check_nextcloud
        check_requirements
        print_success "All requirements met!"
        exit 0
        ;;
    *)
        main
        ;;
esac