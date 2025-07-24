# Door Estimator - Installation Guide

Complete step-by-step installation instructions for the Door Estimator NextCloud app.

## 📋 Pre-Installation Checklist

Before installing, verify your system meets these requirements:

- [ ] NextCloud 25+ installed and running
- [ ] PHP 8.0+ with required extensions
- [ ] MySQL 5.7+ or PostgreSQL 10+
- [ ] At least 512MB PHP memory limit
- [ ] Web server with proper permissions
- [ ] Command line access to the server

### System Requirements Check

```bash
# Check NextCloud version
sudo -u www-data php /var/www/nextcloud/occ status

# Check PHP version and extensions
php -v
php -m | grep -E "(pdo|mysql|pgsql|json|curl)"

# Check memory limit
php -i | grep memory_limit

# Check database connectivity
sudo -u www-data php /var/www/nextcloud/occ db:show-tables | head -5
```

## 🚀 Installation Methods

### Method 1: Manual Installation (Recommended)

#### Step 1: Prepare the Installation

```bash
# Navigate to NextCloud apps directory
cd /var/www/nextcloud/apps/

# Create the door_estimator directory
sudo mkdir door_estimator
cd door_estimator
```

#### Step 2: Copy Application Files

Copy all the application files to the door_estimator directory:

```bash
# Copy all files from your development directory
sudo cp -r /path/to/your/door-estimator-files/* .

# Or if extracting from archive
sudo tar -xzf door-estimator.tar.gz -C .
```

#### Step 3: Set Proper Permissions

```bash
# Set ownership to web server user
sudo chown -R www-data:www-data /var/www/nextcloud/apps/door_estimator

# Set directory permissions
sudo find /var/www/nextcloud/apps/door_estimator -type d -exec chmod 755 {} \;

# Set file permissions
sudo find /var/www/nextcloud/apps/door_estimator -type f -exec chmod 644 {} \;

# Make scripts executable
sudo chmod +x /var/www/nextcloud/apps/door_estimator/scripts/*.py
sudo chmod +x /var/www/nextcloud/apps/door_estimator/scripts/*.sh
```

#### Step 4: Install Dependencies (Optional)

```bash
cd /var/www/nextcloud/apps/door_estimator

# Install PHP dependencies if composer is available
if command -v composer &> /dev/null; then
    sudo -u www-data composer install --no-dev --optimize-autoloader
    echo "✅ PHP dependencies installed"
else
    echo "⚠️  Composer not available - app will work with basic functionality"
fi

# Install Python dependencies for data extraction (if needed)
if command -v pip3 &> /dev/null; then
    pip3 install pandas openpyxl
    echo "✅ Python dependencies installed"
fi
```

#### Step 5: Enable the Application

```bash
# Enable the app via command line
sudo -u www-data php /var/www/nextcloud/occ app:enable door_estimator

# Verify the app is enabled
sudo -u www-data php /var/www/nextcloud/occ app:list | grep door_estimator
```

#### Step 6: Import Pricing Data

```bash
# Import the extracted pricing data
sudo -u www-data php /var/www/nextcloud/occ door-estimator:import-pricing

# Verify data import
sudo -u www-data php /var/www/nextcloud/occ db:show-tables | grep door_estimator
```

### Method 2: Automated Installation

Create an installation script:

```bash
#!/bin/bash
# install-door-estimator.sh

set -e

echo "🚀 Starting Door Estimator installation..."

# Configuration
NEXTCLOUD_ROOT="/var/www/nextcloud"
APP_DIR="$NEXTCLOUD_ROOT/apps/door_estimator"
WEB_USER="www-data"

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo "Please run as root (use sudo)"
    exit 1
fi

# Check NextCloud installation
if [ ! -f "$NEXTCLOUD_ROOT/occ" ]; then
    echo "❌ NextCloud not found at $NEXTCLOUD_ROOT"
    exit 1
fi

# Create app directory
echo "📁 Creating app directory..."
mkdir -p "$APP_DIR"

# Copy files (assumes source is in current directory)
echo "📋 Copying application files..."
cp -r ./* "$APP_DIR/"

# Set permissions
echo "🔒 Setting permissions..."
chown -R $WEB_USER:$WEB_USER "$APP_DIR"
find "$APP_DIR" -type d -exec chmod 755 {} \;
find "$APP_DIR" -type f -exec chmod 644 {} \;

# Install dependencies
echo "📦 Installing dependencies..."
cd "$APP_DIR"
if command -v composer &> /dev/null; then
    sudo -u $WEB_USER composer install --no-dev --optimize-autoloader
fi

# Enable app
echo "🔌 Enabling application..."
sudo -u $WEB_USER php "$NEXTCLOUD_ROOT/occ" app:enable door_estimator

# Import data
echo "📊 Importing pricing data..."
sudo -u $WEB_USER php "$NEXTCLOUD_ROOT/occ" door-estimator:import-pricing

echo "✅ Door Estimator installation completed successfully!"
echo "🌐 Access the app through your NextCloud interface"
```

Save as `install-door-estimator.sh` and run:

```bash
chmod +x install-door-estimator.sh
sudo ./install-door-estimator.sh
```

## 🔧 Post-Installation Configuration

### 1. Verify Installation

```bash
# Check app status
sudo -u www-data php /var/www/nextcloud/occ app:list | grep door_estimator

# Check database tables
sudo -u www-data php /var/www/nextcloud/occ db:show-tables | grep door_estimator

# Test pricing data
mysql -u nextcloud_user -p nextcloud_db -e "SELECT COUNT(*) FROM oc_door_estimator_pricing;"
```

### 2. Configure Default Settings

```bash
# Set default markups (optional)
sudo -u www-data php /var/www/nextcloud/occ config:app:set door_estimator markup_doors --value="15"
sudo -u www-data php /var/www/nextcloud/occ config:app:set door_estimator markup_frames --value="12"
sudo -u www-data php /var/www/nextcloud/occ config:app:set door_estimator markup_hardware --value="18"
```

### 3. Set Up User Groups (Optional)

```bash
# Create estimator group
sudo -u www-data php /var/www/nextcloud/occ group:add estimators

# Add users to group
sudo -u www-data php /var/www/nextcloud/occ group:adduser estimators username1
sudo -u www-data php /var/www/nextcloud/occ group:adduser estimators username2
```

### 4. Configure Caching (Recommended)

Add to your NextCloud config.php:

```php
// config/config.php
'memcache.local' => '\OC\Memcache\APCu',
'memcache.distributed' => '\OC\Memcache\Redis',
'redis' => [
    'host' => 'localhost',
    'port' => 6379,
],
```

## 🧪 Testing the Installation

### 1. Basic Functionality Test

```bash
# Test API endpoints
curl -X GET "https://your-nextcloud.com/apps/door_estimator/api/pricing" \
     -H "Authorization: Bearer YOUR_TOKEN"

# Test database connectivity
sudo -u www-data php /var/www/nextcloud/occ door-estimator:import-pricing --json-file scripts/extracted_pricing_data.json
```

### 2. Web Interface Test

1. **Login to NextCloud**: Access your NextCloud interface
2. **Find the App**: Look for "Door Estimator" in the app menu
3. **Test Loading**: Verify the app loads without errors
4. **Test Pricing**: Select items and verify prices populate
5. **Test Calculations**: Add quantities and verify totals
6. **Test Save**: Create and save a test quote
7. **Test PDF**: Generate a PDF from the saved quote

### 3. Performance Test

```bash
# Test pricing data loading time
time curl -s "https://your-nextcloud.com/apps/door_estimator/api/pricing" > /dev/null

# Test database performance
sudo -u www-data php /var/www/nextcloud/occ db:add-missing-indices
```

## 🚨 Troubleshooting Installation Issues

### Common Installation Problems

#### 1. Permission Errors

```bash
# Symptoms: "Permission denied" errors in logs
# Solution: Fix permissions
sudo chown -R www-data:www-data /var/www/nextcloud/apps/door_estimator
sudo chmod -R 755 /var/www/nextcloud/apps/door_estimator
```

#### 2. App Won't Enable

```bash
# Check for syntax errors
sudo -u www-data php -l /var/www/nextcloud/apps/door_estimator/appinfo/info.xml

# Check NextCloud logs
tail -f /var/www/nextcloud/data/nextcloud.log | grep door_estimator

# Verify app structure
ls -la /var/www/nextcloud/apps/door_estimator/appinfo/
```

#### 3. Database Migration Fails

```bash
# Check database user permissions
mysql -u nextcloud_user -p -e "SHOW GRANTS;"

# Manually run migration
sudo -u www-data php /var/www/nextcloud/occ migrations:execute door_estimator 001000

# Check migration status
sudo -u www-data php /var/www/nextcloud/occ migrations:status door_estimator
```

#### 4. Pricing Data Import Issues

```bash
# Check JSON file format
python3 -m json.tool scripts/extracted_pricing_data.json > /dev/null

# Run import with verbose output
sudo -u www-data php /var/www/nextcloud/occ door-estimator:import-pricing -v

# Check imported data
mysql -u nextcloud_user -p nextcloud_db -e "SELECT category, COUNT(*) FROM oc_door_estimator_pricing GROUP BY category;"
```

### Log Analysis

```bash
# NextCloud application log
tail -f /var/www/nextcloud/data/nextcloud.log | grep -i "door_estimator\|error"

# Apache/Nginx error logs
tail -f /var/log/apache2/error.log  # Apache
tail -f /var/log/nginx/error.log    # Nginx

# PHP error logs
tail -f /var/log/php7.4-fpm.log     # Adjust PHP version
```

## 🔄 Updating the Application

### Update Process

```bash
# 1. Backup current installation
sudo cp -r /var/www/nextcloud/apps/door_estimator /var/backups/door_estimator_$(date +%Y%m%d)

# 2. Backup database
mysqldump nextcloud_db oc_door_estimator_pricing oc_door_estimator_quotes > /var/backups/door_estimator_db_$(date +%Y%m%d).sql

# 3. Disable app
sudo -u www-data php /var/www/nextcloud/occ app:disable door_estimator

# 4. Update files
sudo cp -r /path/to/new/version/* /var/www/nextcloud/apps/door_estimator/

# 5. Set permissions
sudo chown -R www-data:www-data /var/www/nextcloud/apps/door_estimator

# 6. Run migrations
sudo -u www-data php /var/www/nextcloud/occ app:enable door_estimator

# 7. Update pricing data if needed
sudo -u www-data php /var/www/nextcloud/occ door-estimator:import-pricing
```

## 🌐 Production Deployment

### Performance Optimization

```bash
# Enable OPcache
echo "opcache.enable=1" >> /etc/php/8.0/fpm/php.ini
echo "opcache.memory_consumption=256" >> /etc/php/8.0/fpm/php.ini

# Configure PHP-FPM
echo "pm.max_children = 50" >> /etc/php/8.0/fpm/pool.d/www.conf
echo "pm.start_servers = 5" >> /etc/php/8.0/fpm/pool.d/www.conf

# Restart services
sudo systemctl restart php8.0-fpm
sudo systemctl restart apache2  # or nginx
```

### Security Hardening

```bash
# Set strict file permissions
sudo find /var/www/nextcloud/apps/door_estimator -type f -exec chmod 644 {} \;
sudo find /var/www/nextcloud/apps/door_estimator -type d -exec chmod 755 {} \;

# Remove unnecessary files
sudo rm -f /var/www/nextcloud/apps/door_estimator/scripts/extract_excel_python.py
sudo rm -f /var/www/nextcloud/apps/door_estimator/scripts/extracted_pricing_data.json
```

### Monitoring Setup

```bash
# Set up log rotation
sudo cat > /etc/logrotate.d/door-estimator << 'EOF'
/var/www/nextcloud/data/nextcloud.log {
    daily
    missingok
    rotate 52
    compress
    notifempty
    create 644 www-data www-data
    postrotate
        systemctl reload apache2
    endscript
}
EOF
```

## ✅ Installation Verification Checklist

After installation, verify these items:

- [ ] App appears in NextCloud apps menu
- [ ] Main interface loads without errors
- [ ] Pricing data is populated (366 items across 11 categories)
- [ ] Items can be selected from dropdowns
- [ ] Prices populate automatically when items are selected
- [ ] Quantities can be entered and totals calculate
- [ ] Markups are applied correctly to section totals
- [ ] Grand total calculates properly
- [ ] Quotes can be saved with user attribution
- [ ] Saved quotes can be loaded and edited
- [ ] PDF generation works (creates HTML-based quotes)
- [ ] Admin interface shows pricing categories
- [ ] Database tables exist and contain data
- [ ] No errors in NextCloud logs

## 📞 Getting Help

If you encounter issues during installation:

1. **Check Logs**: Review NextCloud, PHP, and web server logs
2. **Verify Requirements**: Ensure all system requirements are met
3. **Test Database**: Verify database connectivity and permissions
4. **File Permissions**: Double-check file and directory permissions
5. **Browser Console**: Check for JavaScript errors in browser developer tools

Remember to include relevant log excerpts and system information when seeking support.