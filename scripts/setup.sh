#!/bin/sh

# Door Estimator NextCloud App Setup Script
# This script automates the installation process

set -e
set -u

# Debug function for tracing
debug_trace() {
    printf "%s[DEBUG]%s %s\n" "$YELLOW" "$NC" "$1"
}

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Nextcloud path detection logic (env, arg, auto-detect)
detect_nextcloud_path() {
    # 1. If NEXTCLOUD_PATH is set, use it
    if [ -n "${NEXTCLOUD_PATH:-}" ]; then
        echo "$NEXTCLOUD_PATH"
        return
    fi
    # 2. If NEXTCLOUD_ROOT is set (legacy), use it
    if [ -n "${NEXTCLOUD_ROOT:-}" ]; then
        echo "$NEXTCLOUD_ROOT"
        return
    fi
    # 3. Auto-detect common locations
    if [ -d "/var/www/nextcloud" ]; then
        echo "/var/www/nextcloud"
        return
    fi
    if [ -d "/var/www/html" ]; then
        echo "/var/www/html"
        return
    fi
    # 4. Not found
    echo ""
}

# Configuration (allow override via env, arg, or auto-detect)
NEXTCLOUD_PATH="$(detect_nextcloud_path)"
if [ -z "$NEXTCLOUD_PATH" ]; then
    echo "[ERROR] Could not detect Nextcloud installation path. Set NEXTCLOUD_PATH or use --nextcloud-path argument."
    exit 1
fi

APP_NAME="${APP_NAME:-door_estimator}"
APP_DIR="${APP_DIR:-$NEXTCLOUD_PATH/apps/$APP_NAME}"
WEB_USER="${WEB_USER:-www-data}"
GITHUB_REPO="${GITHUB_REPO:-https://github.com/kdegeek/nextcloud-door-estimator.git}"
TEMP_DIR="${TEMP_DIR:-/tmp/door-estimator-install}"
LOG_FILE="${LOG_FILE:-/var/log/door_estimator_setup.log}"

# For backward compatibility, set NEXTCLOUD_ROOT as well
NEXTCLOUD_ROOT="$NEXTCLOUD_PATH"

# Function to print colored output
print_status() {
    printf "%s[INFO]%s %s\n" "$BLUE" "$NC" "$1"
}

print_success() {
    printf "%s[SUCCESS]%s %s\n" "$GREEN" "$NC" "$1"
}

print_warning() {
    printf "%s[WARNING]%s %s\n" "$YELLOW" "$NC" "$1"
}

print_error() {
    printf "%s[ERROR]%s %s\n" "$RED" "$NC" "$1"
    echo "[ERROR] $(date '+%Y-%m-%d %H:%M:%S') $1" >> "$LOG_FILE"
}

# Function to check if command exists
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# Function to check if running as root
check_root() {
    if [ "$(id -u)" -ne 0 ]; then
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

# Function to detect OS type for package instructions
detect_os_type() {
    if [ -f /etc/os-release ]; then
        . /etc/os-release
        if [ "$ID" = "alpine" ]; then
            echo "alpine"
        elif [ "$ID" = "debian" ] || [ "$ID" = "ubuntu" ] || echo "$ID_LIKE" | grep -q "debian"; then
            echo "debian"
        elif [ "$ID" = "centos" ] || [ "$ID" = "rhel" ] || [ "$ID_LIKE" = "rhel fedora" ]; then
            echo "redhat"
        else
            echo "$ID"
        fi
    else
        unameOut="$(uname -s)"
        case "${unameOut}" in
            Linux*)     echo "linux";;
            Darwin*)    echo "macos";;
            *)          echo "unknown";;
        esac
    fi
}

# Function to print install instructions for missing PHP extensions
print_php_extension_instructions() {
    OS_TYPE=$(detect_os_type)
    PHP_MAJOR=$(php -r "echo PHP_MAJOR_VERSION;" 2>/dev/null || echo "8")
    PHP_MINOR=$(php -r "echo PHP_MINOR_VERSION;" 2>/dev/null || echo "0")
    PHP_VER="${PHP_MAJOR}.${PHP_MINOR}"

    for ext in "$@"; do
        case "$OS_TYPE" in
            alpine)
                # Alpine uses php8-<ext>
                echo "  apk add php${PHP_MAJOR}-${ext}"
                ;;
            debian)
                # Debian/Ubuntu uses php<ver>-<ext>
                echo "  apt-get install php${PHP_VER}-${ext}"
                ;;
            redhat)
                # RHEL/CentOS uses php-<ext>
                echo "  yum install php-${ext}"
                ;;
            macos)
                echo "  brew install php"
                ;;
            *)
                echo "  # Please install PHP extension: $ext (unknown OS)"
                ;;
        esac
    done
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
    REQUIRED_EXTENSIONS="pdo json curl mbstring xml"
    debug_trace "Checking required PHP extensions: $REQUIRED_EXTENSIONS"
    MISSING_EXTENSIONS=""
    for ext in $REQUIRED_EXTENSIONS; do
        debug_trace "Checking PHP extension: $ext"
        if ! php -m | grep -q "^$ext$"; then
            MISSING_EXTENSIONS="$MISSING_EXTENSIONS $ext"
        fi
    done
    if [ -n "$MISSING_EXTENSIONS" ]; then
        print_error "The following required PHP extensions are missing:$MISSING_EXTENSIONS"
        echo "To install, run the following commands as root (or with sudo):"
        print_php_extension_instructions $MISSING_EXTENSIONS
        exit 1
    fi
    print_success "Required PHP extensions available"

    # Check for at least one common PHP PDO database driver
    if ! php -m | grep -q "^pdo_mysql$" && ! php -m | grep -q "^pdo_pgsql$" && ! php -m | grep -q "^pdo_sqlite$"; then
        print_error "No PHP PDO database driver (pdo_mysql, pdo_pgsql, pdo_sqlite) is enabled."
        echo "ERROR: A PHP PDO database driver is required for Nextcloud to connect to its database."
        echo ""
        echo "IMPORTANT: Installing only the core 'pdo' extension is NOT sufficient."
        echo "You must install a specific PDO driver for your database backend."
        echo ""
        echo "For Alpine Linux users:"
        echo "  - You need to install the unversioned PDO driver packages: 'php-pdo', 'php-pdo_mysql', 'php-pdo_pgsql', or 'php-pdo_sqlite'."
        echo "  - Example for MySQL/MariaDB:    apk add php-pdo php-pdo_mysql"
        echo "  - Example for PostgreSQL:       apk add php-pdo php-pdo_pgsql"
        echo "  - Example for SQLite:           apk add php-pdo php-pdo_sqlite"
        echo "  - Note: If you are using a custom or non-default PHP build (e.g., php8X-*), adjust the package names accordingly."
        echo ""
        echo "For other OSes, run the following command for your database backend:"
        echo ""
        echo "For MySQL/MariaDB:"
        print_php_extension_instructions pdo_mysql
        echo ""
        echo "For PostgreSQL:"
        print_php_extension_instructions pdo_pgsql
        echo ""
        echo "For SQLite:"
        print_php_extension_instructions pdo_sqlite
        echo ""
        echo "After installing the appropriate PDO driver, re-run this setup script."
        exit 1
    fi

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
    
    # --- Node.js Auto-Install Logic ---
    
    # --- Build Vue 3 Frontend ---
    print_status "Checking for Node.js and npm (required for frontend build)..."
    if ! command -v node >/dev/null 2>&1; then
        print_error "Node.js is required to build the frontend but was not found."
        print_status "Please install Node.js v18 LTS and rerun this script, or build manually with: cd $APP_DIR && sh scripts/build.sh"
        exit 1
    fi
    if ! command -v npm >/dev/null 2>&1; then
        print_error "npm is required to build the frontend but was not found."
        print_status "Please install npm and rerun this script, or build manually with: cd $APP_DIR && sh scripts/build.sh"
        exit 1
    fi
    
    # --- NPM Version Check and Guidance ---
    NPM_VERSION=$(npm --version 2>/dev/null || echo "unknown")
    debug_trace "Detected npm version: $NPM_VERSION"
    NPM_MAJOR=$(echo "$NPM_VERSION" | cut -d. -f1)
    if [ "$NPM_MAJOR" = "10" ]; then
        print_warning "npm version 10 detected (version: $NPM_VERSION)."
        print_warning "Some dependencies or build tools may not be fully compatible with npm 10."
        print_status "If you encounter build errors or unexpected issues, consider downgrading to npm 9 (npm install -g npm@9)."
        print_status "Known issues with npm 10 include stricter peer dependency resolution and changes to the lockfile format."
        print_status "See project documentation for details and workarounds."
    fi
    
    print_status "Building Vue 3 frontend (npm install + webpack build)..."
    cd "$APP_DIR"
    sh scripts/build.sh || { print_error "Frontend build failed. See above for details."; exit 1; }
    cd - >/dev/null
    
    print_success "Application files installed from GitHub"
}

# POSIX-compliant ensure_nodejs (moved to top-level)
ensure_nodejs() {
    if command -v node >/dev/null 2>&1; then
        return 0
    fi

    print_warning "Node.js is required but not found. Attempting automatic installation..."

    # Check if running as root
    if [ "$(id -u)" -ne 0 ]; then
        print_error "Node.js is missing and this script is not running as root."
        print_status "Please install Node.js v18 LTS manually: https://nodejs.org/en/download or via your package manager."
        exit 1
    fi

    # Detect Debian/Ubuntu
    if [ -f /etc/os-release ]; then
        . /etc/os-release
        if [ "$ID" = "debian" ] || [ "$ID" = "ubuntu" ] || echo "$ID_LIKE" | grep -q "debian"; then
            print_status "Detected Debian/Ubuntu. Installing Node.js v18 LTS using apt-get..."
            apt-get update && \
            curl -fsSL https://deb.nodesource.com/setup_18.x | sh - && \
            apt-get install -y nodejs
            if command -v node >/dev/null 2>&1; then
                print_success "Node.js v18 LTS installed successfully."
                return 0
            else
                print_error "Automatic Node.js installation failed."
                print_status "Please install Node.js v18 LTS manually: https://nodejs.org/en/download"
                exit 1
            fi
        fi
    fi

    print_error "Node.js is missing and automatic installation is only supported on Debian/Ubuntu as root."
    print_status "Please install Node.js v18 LTS manually: https://nodejs.org/en/download"
    exit 1
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
    debug_trace "PRICING_COUNT result: $PRICING_COUNT"
    if [ "$PRICING_COUNT" -gt 0 ] 2>/dev/null; then
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
    ensure_nodejs
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
case "${1-}" in
    --help|-h)
        echo "Door Estimator Setup Script"
        echo ""
        echo "Usage: $0 [options] [NEXTCLOUD_PATH]"
        echo ""
        echo "Options:"
        echo "  --help, -h               Show this help message"
        echo "  --check                  Check system requirements only"
        echo "  --root PATH              Override Nextcloud path (legacy, same as --nextcloud-path)"
        echo "  --nextcloud-path PATH    Set Nextcloud installation path"
        echo "  --app-name NAME          Override APP_NAME"
        echo "  --app-dir DIR            Override APP_DIR"
        echo ""
        echo "Environment variables can also be used to override:"
        echo "  NEXTCLOUD_PATH, NEXTCLOUD_ROOT, APP_NAME, APP_DIR, WEB_USER, GITHUB_REPO, TEMP_DIR, LOG_FILE"
        echo ""
        echo "You may also provide the Nextcloud path as a positional argument:"
        echo "  $0 /path/to/nextcloud"
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
    --root|--nextcloud-path)
        shift
        export NEXTCLOUD_PATH="${1-}"
        export NEXTCLOUD_ROOT="$NEXTCLOUD_PATH"
        shift
        main
        ;;
    --app-name)
        shift
        export APP_NAME="${1-}"
        shift
        main
        ;;
    --app-dir)
        shift
        export APP_DIR="${1-}"
        shift
        main
        ;;
    "")
        main
        ;;
    *)
        # If a positional argument is given, treat as Nextcloud path
        export NEXTCLOUD_PATH="$1"
        export NEXTCLOUD_ROOT="$NEXTCLOUD_PATH"
        shift
        main
        ;;
esac