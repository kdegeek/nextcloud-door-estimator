#!/bin/bash

# Door Estimator NextCloud App - AIO Container Installer
# This script installs the Door Estimator app in NextCloud All-in-One (AIO) environment

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration for AIO environment
AIO_CONTAINER_NAME="nextcloud-aio-nextcloud"
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

# Function to check if command exists
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# Function to detect AIO container
detect_aio_container() {
    print_status "Detecting NextCloud AIO container..."
    
    # Try common AIO container names
    local container_names=(
        "nextcloud-aio-nextcloud"
        "nextcloud_aio_nextcloud"
        "nextcloud-aio-nextcloud-1"
        "aio-nextcloud"
    )
    
    for name in "${container_names[@]}"; do
        if docker ps --format "table {{.Names}}" | grep -q "^${name}$"; then
            AIO_CONTAINER_NAME="$name"
            print_success "Found AIO container: $AIO_CONTAINER_NAME"
            return 0
        fi
    done
    
    print_error "NextCloud AIO container not found"
    print_status "Available containers:"
    docker ps --format "table {{.Names}}\t{{.Status}}"
    print_status ""
    print_status "Please ensure NextCloud AIO is running, or specify container name:"
    print_status "  $0 --container <container-name>"
    return 1
}

# Function to check AIO environment
check_aio_environment() {
    print_status "Checking NextCloud AIO environment..."
    
    # Check if Docker is available
    if ! command_exists docker; then
        print_error "Docker is required but not installed"
        print_status "Install Docker first: https://docs.docker.com/get-docker/"
        exit 1
    fi
    
    # Check if running as root or in docker group
    if [ "$EUID" -ne 0 ] && ! groups | grep -q docker; then
        print_error "This script must be run as root or by a user in the docker group"
        print_status "Add your user to docker group: sudo usermod -aG docker \$USER"
        exit 1
    fi
    
    # Detect AIO container
    if ! detect_aio_container; then
        exit 1
    fi
    
    # Test container access
    if ! docker exec "$AIO_CONTAINER_NAME" ls /var/www/html >/dev/null 2>&1; then
        print_error "Cannot access NextCloud container filesystem"
        exit 1
    fi
    
    # Check if occ command works
    if ! docker exec -u www-data "$AIO_CONTAINER_NAME" php /var/www/html/occ status >/dev/null 2>&1; then
        print_error "NextCloud occ command not accessible"
        print_status "Make sure NextCloud is fully initialized"
        exit 1
    fi
    
    print_success "NextCloud AIO environment verified"
}

# Function to install git in container if needed
ensure_git_in_container() {
    print_status "Ensuring git is available in container..."
    
    # Check if git exists in container
    if docker exec "$AIO_CONTAINER_NAME" which git >/dev/null 2>&1; then
        print_success "Git already available in container"
        return 0
    fi
    
    print_status "Installing git in NextCloud container..."
    
    # Update package list and install git
    docker exec "$AIO_CONTAINER_NAME" apt-get update >/dev/null 2>&1 || {
        print_error "Failed to update package list in container"
        exit 1
    }
    
    docker exec "$AIO_CONTAINER_NAME" apt-get install -y git >/dev/null 2>&1 || {
        print_error "Failed to install git in container"
        exit 1
    }
    
    print_success "Git installed in container"
}

# Function to backup existing installation
backup_existing() {
    print_status "Checking for existing installation..."
    
    if docker exec "$AIO_CONTAINER_NAME" test -d "/var/www/html/apps/$APP_NAME" 2>/dev/null; then
        print_status "Existing installation found - creating backup..."
        local backup_name="${APP_NAME}_backup_$(date +%Y%m%d_%H%M%S)"
        
        docker exec "$AIO_CONTAINER_NAME" cp -r "/var/www/html/apps/$APP_NAME" "/var/www/html/apps/$backup_name" || {
            print_error "Failed to create backup"
            exit 1
        }
        
        print_success "Backup created: /var/www/html/apps/$backup_name"
        print_status "This is an UPDATE - existing app will be replaced with latest version"
    else
        print_status "No existing installation found - this is a fresh INSTALL"
    fi
}

# Function to install the application
install_app() {
    print_status "Installing Door Estimator application..."

    # Ensure git is available in container
    ensure_git_in_container

    # Preserve pricing JSON data if present
    PRESERVED_JSON_PATH="/tmp/extracted_pricing_data_$(date +%Y%m%d_%H%M%S).json"
    if docker exec "$AIO_CONTAINER_NAME" test -f "/var/www/html/apps/$APP_NAME/scripts/extracted_pricing_data.json"; then
        print_status "Preserving pricing data from previous installation..."
        docker exec "$AIO_CONTAINER_NAME" cp "/var/www/html/apps/$APP_NAME/scripts/extracted_pricing_data.json" "$PRESERVED_JSON_PATH" && print_success "Pricing data preserved at $PRESERVED_JSON_PATH" || print_error "Failed to preserve pricing data"
    else
        print_status "No pricing data found to preserve."
        PRESERVED_JSON_PATH=""
    fi

    # Remove existing installation
    print_status "Removing old installation in container..."
    docker exec "$AIO_CONTAINER_NAME" rm -rf "/var/www/html/apps/$APP_NAME" 2>/dev/null && print_success "Old installation removed." || print_status "No previous installation to remove."

    # Clone the repository directly in container
    print_status "Cloning repository in container..."
    if docker exec "$AIO_CONTAINER_NAME" git clone "$GITHUB_REPO" "/tmp/$APP_NAME-temp"; then
        print_success "Repository cloned successfully"
    else
        print_error "Failed to clone repository"
        exit 1
    fi

    # Move to apps directory
    docker exec "$AIO_CONTAINER_NAME" mv "/tmp/$APP_NAME-temp" "/var/www/html/apps/$APP_NAME" || {
        print_error "Failed to move app to apps directory"
        exit 1
    }

    # Restore preserved pricing JSON data if it exists
    if [ -n "$PRESERVED_JSON_PATH" ] && docker exec "$AIO_CONTAINER_NAME" test -f "$PRESERVED_JSON_PATH"; then
        print_status "Restoring preserved pricing data to /var/www/html/apps/$APP_NAME/scripts/extracted_pricing_data.json"
        docker exec "$AIO_CONTAINER_NAME" mkdir -p "/var/www/html/apps/$APP_NAME/scripts"
        docker exec "$AIO_CONTAINER_NAME" cp "$PRESERVED_JSON_PATH" "/var/www/html/apps/$APP_NAME/scripts/extracted_pricing_data.json" && print_success "Pricing data restored." || print_error "Failed to restore pricing data."
        docker exec "$AIO_CONTAINER_NAME" rm -f "$PRESERVED_JSON_PATH"
    else
        print_status "No preserved pricing data to restore."
    fi
    
    # --- Build Vue 3 Frontend in Container ---
    print_status "Checking for Node.js and npm in container (required for frontend build)..."
    ensure_nodejs_in_container() {
        if docker exec "$AIO_CONTAINER_NAME" command -v node >/dev/null 2>&1; then
            return 0
        fi

        print_warning "Node.js is required but not found in the container. Attempting automatic installation..."

        # Check if running as root in host (required for package install in container)
        if [ "$EUID" -ne 0 ]; then
            print_error "Node.js is missing in the container and this script is not running as root."
            print_status "Please install Node.js v18 LTS and npm manually in the container: https://nodejs.org/en/download or via your package manager."
            exit 1
        fi

        # Detect OS in container
        if docker exec "$AIO_CONTAINER_NAME" test -f /etc/os-release; then
            OS_ID=$(docker exec "$AIO_CONTAINER_NAME" sh -c ". /etc/os-release && echo \$ID")
            OS_LIKE=$(docker exec "$AIO_CONTAINER_NAME" sh -c ". /etc/os-release && echo \$ID_LIKE")
            if [[ "$OS_ID" == "alpine" ]]; then
                print_status "Detected Alpine Linux in container. Installing Node.js and npm using apk..."
                if docker exec "$AIO_CONTAINER_NAME" apk add --no-cache nodejs npm; then
                    if docker exec "$AIO_CONTAINER_NAME" command -v node >/dev/null 2>&1 && docker exec "$AIO_CONTAINER_NAME" command -v npm >/dev/null 2>&1; then
                        print_success "Node.js and npm installed successfully in the Alpine container."
                        return 0
                    else
                        print_error "Automatic Node.js/npm installation failed in the Alpine container."
                        print_status "Please install Node.js and npm manually in the container: https://nodejs.org/en/download"
                        exit 1
                    fi
                else
                    print_error "apk failed to install Node.js/npm in the Alpine container."
                    print_status "Please install Node.js and npm manually in the container: https://nodejs.org/en/download"
                    exit 1
                fi
            elif [[ "$OS_ID" == "debian" || "$OS_ID" == "ubuntu" || "$OS_LIKE" == *"debian"* ]]; then
                print_status "Detected Debian/Ubuntu in container. Installing Node.js v18 LTS using apt-get..."
                docker exec "$AIO_CONTAINER_NAME" apt-get update && \
                docker exec "$AIO_CONTAINER_NAME" bash -c "curl -fsSL https://deb.nodesource.com/setup_18.x | bash -" && \
                docker exec "$AIO_CONTAINER_NAME" apt-get install -y nodejs
                if docker exec "$AIO_CONTAINER_NAME" command -v node >/dev/null 2>&1; then
                    print_success "Node.js v18 LTS installed successfully in the container."
                    return 0
                else
                    print_error "Automatic Node.js installation failed in the container."
                    print_status "Please install Node.js v18 LTS manually in the container: https://nodejs.org/en/download"
                    exit 1
                fi
            fi
        fi

        print_error "Node.js is missing in the container and automatic installation is only supported on Alpine, Debian, or Ubuntu as root."
        print_status "Please install Node.js v18 LTS and npm manually in the container. For example:"
        print_status "  Alpine: apk add --no-cache nodejs npm"
        print_status "  Debian/Ubuntu: curl -fsSL https://deb.nodesource.com/setup_18.x | bash - && apt-get install -y nodejs"
        print_status "Or see: https://nodejs.org/en/download"
        exit 1
    }
    
    ensure_nodejs_in_container
    
    if ! docker exec "$AIO_CONTAINER_NAME" command -v node >/dev/null 2>&1; then
        print_error "Node.js is required to build the frontend but was not found in the container."
        print_status "Please install Node.js v18 LTS in the container and rerun this script, or build manually with: docker exec $AIO_CONTAINER_NAME bash -c 'cd /var/www/html/apps/$APP_NAME && sh scripts/build.sh'"
        exit 1
    fi
    if ! docker exec "$AIO_CONTAINER_NAME" command -v npm >/dev/null 2>&1; then
        print_error "npm is required to build the frontend but was not found in the container."
        print_status "Please install npm in the container and rerun this script, or build manually with: docker exec $AIO_CONTAINER_NAME bash -c 'cd /var/www/html/apps/$APP_NAME && sh scripts/build.sh'"
        exit 1
    fi
    print_status "Building Vue 3 frontend in container (npm install + webpack build)..."
    if ! docker exec "$AIO_CONTAINER_NAME" bash -c "cd /var/www/html/apps/$APP_NAME && sh scripts/build.sh"; then
        print_error "Frontend build failed in container. See above for details."
        exit 1
    fi
    
    print_success "Application files installed"
}

# Function to set permissions
set_permissions() {
    print_status "Setting file permissions..."
    
    # Set ownership to www-data
    docker exec "$AIO_CONTAINER_NAME" chown -R www-data:www-data "/var/www/html/apps/$APP_NAME" || {
        print_error "Failed to set ownership"
        exit 1
    }
    
    # Set directory permissions
    docker exec "$AIO_CONTAINER_NAME" find "/var/www/html/apps/$APP_NAME" -type d -exec chmod 755 {} \; || {
        print_error "Failed to set directory permissions"
        exit 1
    }
    
    # Set file permissions
    docker exec "$AIO_CONTAINER_NAME" find "/var/www/html/apps/$APP_NAME" -type f -exec chmod 644 {} \; || {
        print_error "Failed to set file permissions"
        exit 1
    }
    
    # Make scripts executable
    docker exec "$AIO_CONTAINER_NAME" chmod +x "/var/www/html/apps/$APP_NAME/scripts"/*.sh 2>/dev/null || true
    docker exec "$AIO_CONTAINER_NAME" chmod +x "/var/www/html/apps/$APP_NAME/scripts"/*.py 2>/dev/null || true

    print_success "Permissions set successfully"
}

# Function to install dependencies
install_dependencies() {
    print_status "All PHP dependencies are already bundled in the vendor/ directory."
    print_status "Advanced PDF features are always available—no additional setup required."
    
    # Install Python dependencies if available
    if docker exec "$AIO_CONTAINER_NAME" which python3 >/dev/null 2>&1 && docker exec "$AIO_CONTAINER_NAME" which pip3 >/dev/null 2>&1; then
        if docker exec "$AIO_CONTAINER_NAME" test -f "/var/www/html/apps/$APP_NAME/requirements.txt"; then
            print_status "Installing Python dependencies..."
            docker exec "$AIO_CONTAINER_NAME" pip3 install pandas openpyxl >/dev/null 2>&1 || {
                print_warning "Some Python dependencies may not have installed"
            }
        fi
    fi
}

# Function to enable the application
enable_app() {
    print_status "Enabling Door Estimator application..."
    
    # Disable app first if it exists
    docker exec -u www-data "$AIO_CONTAINER_NAME" php /var/www/html/occ app:disable "$APP_NAME" >/dev/null 2>&1 || true
    
    # Enable the app
    if docker exec -u www-data "$AIO_CONTAINER_NAME" php /var/www/html/occ app:enable "$APP_NAME"; then
        print_success "Application enabled successfully"
    else
        print_error "Failed to enable application"
        print_status "Check NextCloud logs for details:"
        print_status "  docker exec $AIO_CONTAINER_NAME tail -f /var/www/html/data/nextcloud.log"
        exit 1
    fi
}

# Function to import pricing data
import_data() {
    print_status "Checking for pricing data..."
    
    if docker exec "$AIO_CONTAINER_NAME" test -f "/var/www/html/apps/$APP_NAME/scripts/extracted_pricing_data.json"; then
        print_status "Found pricing data file, importing..."
        if docker exec -u www-data "$AIO_CONTAINER_NAME" php /var/www/html/occ door-estimator:import-pricing; then
            print_success "Pricing data imported successfully"
        else
            print_error "Failed to import pricing data"
            exit 1
        fi
    else
        print_warning "No pricing data file found"
        print_status "To import your pricing data:"
        print_status "1. Copy your pricing file to the container:"
        print_status "   docker cp extracted_pricing_data.json $AIO_CONTAINER_NAME:/var/www/html/apps/$APP_NAME/scripts/"
        print_status "2. Set permissions:"
        print_status "   docker exec $AIO_CONTAINER_NAME chown www-data:www-data /var/www/html/apps/$APP_NAME/scripts/extracted_pricing_data.json"
        print_status "3. Import the data:"
        print_status "   docker exec -u www-data $AIO_CONTAINER_NAME php /var/www/html/occ door-estimator:import-pricing"
    fi
}

# Function to verify installation
verify_installation() {
    print_status "Verifying installation..."
    
    # Check if app is enabled
    if docker exec -u www-data "$AIO_CONTAINER_NAME" php /var/www/html/occ app:list | grep -q "$APP_NAME"; then
        print_success "App is enabled in NextCloud"
    else
        print_error "App is not enabled"
        return 1
    fi
    
    # Check database tables
    local pricing_count
    pricing_count=$(docker exec -u www-data "$AIO_CONTAINER_NAME" php /var/www/html/occ db:query "SELECT COUNT(*) as count FROM oc_door_estimator_pricing" --output=json 2>/dev/null | grep -o '"count":"[0-9]*"' | cut -d'"' -f4 || echo "0")
    
    if [ "$pricing_count" -gt 0 ]; then
        print_success "Database contains $pricing_count pricing items"
    else
        print_warning "No pricing data found in database"
    fi
    
    # Check file accessibility
    if docker exec "$AIO_CONTAINER_NAME" test -r "/var/www/html/apps/$APP_NAME/appinfo/info.xml" && docker exec "$AIO_CONTAINER_NAME" test -r "/var/www/html/apps/$APP_NAME/js/door-estimator.js"; then
        print_success "Application files are accessible"
    else
        print_error "Some application files are not accessible"
        return 1
    fi
    
    return 0
}

# Function to show post-installation instructions
show_instructions() {
    echo ""
    echo "=================================================="
    echo -e "${GREEN}Door Estimator AIO Installation Complete!${NC}"
    echo "=================================================="
    echo ""
    echo "🌐 Access the app:"
    echo "   1. Open your NextCloud AIO interface"
    echo "   2. Log in with your admin credentials"
    echo "   3. Look for 'Door Estimator' in the app menu"
    echo "   4. Click to start creating quotes"
    echo ""
    echo "🔧 NextCloud AIO Container: $AIO_CONTAINER_NAME"
    echo ""
    echo "📊 Pricing Data:"
    local pricing_count
    pricing_count=$(docker exec -u www-data "$AIO_CONTAINER_NAME" php /var/www/html/occ db:query "SELECT COUNT(*) as count FROM oc_door_estimator_pricing" --output=json 2>/dev/null | grep -o '"count":"[0-9]*"' | cut -d'"' -f4 || echo "0")
    echo "   - $pricing_count items currently in database"
    if [ "$pricing_count" -eq 0 ]; then
        echo -e "   ${YELLOW}- Import your pricing data using the instructions above${NC}"
    fi
    echo ""
    echo "🛠️  Container Management:"
    echo "   - View logs: docker exec $AIO_CONTAINER_NAME tail -f /var/www/html/data/nextcloud.log"
    echo "   - Access container: docker exec -it $AIO_CONTAINER_NAME bash"
    echo "   - Run occ commands: docker exec -u www-data $AIO_CONTAINER_NAME php /var/www/html/occ <command>"
    echo ""
    echo "📖 Documentation:"
    echo "   - Complete guide: https://github.com/kdegeek/nextcloud-door-estimator"
    echo "   - Pricing data setup: Check docs/PRICING_DATA_SETUP.md in the repository"
    echo ""
    echo "🔍 Troubleshooting:"
    echo "   - Check container status: docker ps | grep nextcloud"
    echo "   - Restart AIO: docker restart \$(docker ps -q --filter name=nextcloud-aio)"
    echo "   - View app files: docker exec $AIO_CONTAINER_NAME ls -la /var/www/html/apps/door_estimator"
    echo ""
}

# Main execution
main() {
    echo "=================================================="
    echo "Door Estimator NextCloud AIO Installer"
    echo "=================================================="
    echo ""
    echo "This installer is specifically designed for NextCloud All-in-One (AIO) containers."
    echo ""
    
    check_aio_environment
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
        echo "Door Estimator AIO Installer"
        echo ""
        echo "Usage: $0 [options]"
        echo ""
        echo "Options:"
        echo "  --help, -h           Show this help message"
        echo "  --container <name>   Specify AIO container name"
        echo "  --check              Check AIO environment only"
        echo ""
        echo "This script will:"
        echo "  1. Detect your NextCloud AIO container"
        echo "  2. Install the Door Estimator app from GitHub"
        echo "  3. Set proper permissions within the container"
        echo "  4. Enable the application"
        echo "  5. Guide you through pricing data import"
        echo ""
        echo "Requirements:"
        echo "  - NextCloud AIO container running"
        echo "  - Docker access (root or docker group)"
        echo "  - Internet connection for GitHub access"
        echo ""
        exit 0
        ;;
    --container)
        if [ -z "${2:-}" ]; then
            print_error "Container name required"
            exit 1
        fi
        AIO_CONTAINER_NAME="$2"
        main
        ;;
    --check)
        print_status "Checking AIO environment only..."
        check_aio_environment
        print_success "AIO environment check completed!"
        exit 0
        ;;
    *)
        main
        ;;
esac