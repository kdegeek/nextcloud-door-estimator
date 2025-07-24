#!/bin/bash

# Door Estimator AIO Test Script
# Quick verification script for NextCloud AIO installations

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
APP_NAME="door_estimator"
AIO_CONTAINER_NAME="nextcloud-aio-nextcloud"

# Function to print colored output
print_status() {
    echo -e "${BLUE}[TEST]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[PASS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

print_error() {
    echo -e "${RED}[FAIL]${NC} $1"
}

# Detect AIO container
detect_container() {
    local container_names=(
        "nextcloud-aio-nextcloud"
        "nextcloud_aio_nextcloud"
        "nextcloud-aio-nextcloud-1"
        "aio-nextcloud"
    )
    
    for name in "${container_names[@]}"; do
        if docker ps --format "table {{.Names}}" | grep -q "^${name}$"; then
            AIO_CONTAINER_NAME="$name"
            return 0
        fi
    done
    return 1
}

echo "=================================================="
echo "Door Estimator AIO Installation Test"
echo "=================================================="
echo ""

# Test 1: Docker availability
print_status "Checking Docker availability..."
if command -v docker >/dev/null 2>&1; then
    print_success "Docker is installed"
else
    print_error "Docker not found"
    exit 1
fi

# Test 2: AIO container detection
print_status "Detecting NextCloud AIO container..."
if detect_container; then
    print_success "Found AIO container: $AIO_CONTAINER_NAME"
else
    print_error "No AIO container found"
    print_status "Available containers:"
    docker ps --format "table {{.Names}}\t{{.Status}}"
    exit 1
fi

# Test 3: Container accessibility
print_status "Testing container access..."
if docker exec "$AIO_CONTAINER_NAME" ls /var/www/html >/dev/null 2>&1; then
    print_success "Container filesystem accessible"
else
    print_error "Cannot access container filesystem"
    exit 1
fi

# Test 4: NextCloud OCC command
print_status "Testing NextCloud OCC command..."
if docker exec -u www-data "$AIO_CONTAINER_NAME" php /var/www/html/occ status >/dev/null 2>&1; then
    print_success "NextCloud OCC command works"
else
    print_error "NextCloud OCC command failed"
    exit 1
fi

# Test 5: App installation check
print_status "Checking Door Estimator app installation..."
if docker exec "$AIO_CONTAINER_NAME" test -d "/var/www/html/apps/$APP_NAME"; then
    print_success "App directory exists"
    
    # Check if app is enabled
    if docker exec -u www-data "$AIO_CONTAINER_NAME" php /var/www/html/occ app:list | grep -q "$APP_NAME"; then
        print_success "App is enabled in NextCloud"
    else
        print_warning "App directory exists but not enabled"
    fi
else
    print_warning "App not installed yet"
fi

# Test 6: Database tables check
print_status "Checking database tables..."
if docker exec "$AIO_CONTAINER_NAME" test -d "/var/www/html/apps/$APP_NAME"; then
    TABLES=$(docker exec -u www-data "$AIO_CONTAINER_NAME" php /var/www/html/occ db:show-tables 2>/dev/null | grep "door_estimator" | wc -l || echo "0")
    if [ "$TABLES" -gt 0 ]; then
        print_success "Found $TABLES Door Estimator database tables"
    else
        print_warning "No Door Estimator database tables found"
    fi
fi

# Test 7: Pricing data check
print_status "Checking pricing data..."
if docker exec "$AIO_CONTAINER_NAME" test -d "/var/www/html/apps/$APP_NAME"; then
    PRICING_COUNT=$(docker exec -u www-data "$AIO_CONTAINER_NAME" php /var/www/html/occ db:query "SELECT COUNT(*) as count FROM oc_door_estimator_pricing" --output=json 2>/dev/null | grep -o '"count":"[0-9]*"' | cut -d'"' -f4 || echo "0")
    if [ "$PRICING_COUNT" -gt 0 ]; then
        print_success "Found $PRICING_COUNT pricing items in database"
    else
        print_warning "No pricing data found - import needed"
    fi
fi

# Test 8: File permissions check
print_status "Checking file permissions..."
if docker exec "$AIO_CONTAINER_NAME" test -d "/var/www/html/apps/$APP_NAME"; then
    if docker exec "$AIO_CONTAINER_NAME" test -r "/var/www/html/apps/$APP_NAME/appinfo/info.xml"; then
        print_success "App files are readable"
    else
        print_error "App files permission issues"
    fi
fi

# Test 9: Web accessibility test
print_status "Testing web accessibility..."
if docker exec "$AIO_CONTAINER_NAME" test -f "/var/www/html/apps/$APP_NAME/js/door-estimator.js"; then
    print_success "Frontend files present"
else
    print_warning "Frontend files missing or inaccessible"
fi

echo ""
echo "=================================================="
echo "Test Summary"
echo "=================================================="
echo ""
echo "🐳 Container: $AIO_CONTAINER_NAME"
echo "📊 Pricing Items: ${PRICING_COUNT:-0}"
echo "🗄️  Database Tables: ${TABLES:-0}"
echo ""

if [ "${PRICING_COUNT:-0}" -eq 0 ]; then
    echo "📝 Next Steps:"
    echo "1. Import your pricing data:"
    echo "   docker cp extracted_pricing_data.json $AIO_CONTAINER_NAME:/var/www/html/apps/$APP_NAME/scripts/"
    echo "   docker exec -u www-data $AIO_CONTAINER_NAME php /var/www/html/occ door-estimator:import-pricing"
    echo ""
fi

echo "🌐 Access your Door Estimator:"
echo "   Open your NextCloud AIO interface and look for 'Door Estimator' in the apps menu"
echo ""

print_success "AIO installation test completed!"