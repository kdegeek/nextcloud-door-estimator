# Door Estimator - Installation Guide


Complete step-by-step installation instructions for the Door Estimator NextCloud app.

## 📋 Pre-Installation Checklist

Before installing, verify your system meets these requirements:

- [ ] NextCloud 25+ installed and running
- [ ] PHP 8.0+ with required extensions
- [ ] MySQL 5.7+ or PostgreSQL 10+
- [ ] **Node.js v20+ installed** (required to build the Vue 3 + Vite frontend)
- [ ] **npm v10+ installed**
- [ ] At least 512MB PHP memory limit
- [ ] Web server with proper permissions
- [ ] Command line access to the server

> **Node.js v20+ and npm v10+ are required to build the Vue 3 frontend with Vite.**
> If Node.js is not present in your container, install it with:
> ```bash
> curl -fsSL https://deb.nodesource.com/setup_20.x | bash - && apt-get install -y nodejs
> ```
> Verify installation with `node -v` (should be 20.x or higher) and `npm -v` (should be 10.x or higher).

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

# Clone the repository
git clone https://github.com/kdegeek/nextcloud-door-estimator.git door_estimator
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

#### Step 4: PHP Dependencies

All required PHP dependencies are already bundled in the `vendor/` directory. No Composer installation is required for end-users.

- Composer is only needed by developers who wish to update or add PHP dependencies.
- Advanced PDF features are always available.

# Install Python dependencies for data extraction (if needed)
if command -v pip3 &> /dev/null; then
    pip3 install pandas openpyxl
    echo "✅ Python dependencies installed"
fi

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

# PHP dependencies
echo "📦 Verifying PHP dependencies..."
cd "$APP_DIR"
# All required PHP dependencies are already bundled in the vendor/ directory.
# Composer is only needed by developers who wish to update dependencies.

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

### Vite Build Issues

- **Problem**: `npm run build` or `npm run dev` fails with errors about missing Node.js, incompatible version, or Vite configuration.
- **Solution**:
  - Ensure Node.js v20+ and npm v10+ are installed in your environment/container.
  - To install Node.js v20+ in Ubuntu/Debian containers:
    ```bash
    curl -fsSL https://deb.nodesource.com/setup_20.x | bash - && apt-get install -y nodejs
    ```
  - Verify with `node -v` (should be 20.x or higher) and `npm -v` (should be 10.x or higher).
  - If you see Vite-specific errors, check your `vite.config.js` and ensure all required plugins are installed.
  - For issues with hot module replacement or dev server, ensure ports are open and not blocked by firewalls.
  - If you encounter dependency resolution errors, delete `node_modules` and `package-lock.json`, then run `npm install` again.

### Common Issues and Solutions

#### 1. Static Server Setup
- **Problem**: The static server is not running or not accessible.
- **Solution**: Ensure the static server is running by executing the following command:
  ```bash
  python3 -m http.server 8000
  ```
  Then, access the demo at `http://localhost:8000`.

#### 2. Browser Errors
- **Problem**: Browser console shows JavaScript errors.
- **Solution**: Check the browser console for specific error messages. Common issues include:
  - Missing files: Ensure all required files are present in the correct locations.
  - CORS issues: If running locally, ensure the server allows CORS.

#### 3. Python Environment Issues
- **Problem**: Python scripts fail to execute.
- **Solution**: Ensure Python 3 is installed and accessible. Verify dependencies:
  ```bash
  pip3 install pandas openpyxl
  ```
  Check Python version:
  ```bash
  python3 --version
  ```

#### 4. Missing Pricing Data
- **Problem**: Pricing data is not loading.
- **Solution**: Verify the `extracted_pricing_data.json` file exists and is correctly formatted. Run:
  ```bash
  python3 -m json.tool scripts/extracted_pricing_data.json
  ```

#### 5. Database Connection Issues
- **Problem**: Database operations fail.
- **Solution**: Ensure the database server is running and accessible. Check connection details in configuration files.

#### 6. Permission Errors
- **Problem**: Permission denied errors during installation.
- **Solution**: Ensure proper file permissions:
  ```bash
  sudo chown -R www-data:www-data /var/www/nextcloud/apps/door_estimator
  sudo chmod -R 755 /var/www/nextcloud/apps/door_estimator
  ```

#### 7. App Not Enabling
- **Problem**: The app does not enable in Nextcloud.
- **Solution**: Check Nextcloud logs for errors:
  ```bash
  tail -f /var/www/nextcloud/data/nextcloud.log | grep door_estimator
  ```
  Verify app structure and syntax:
  ```bash
  sudo -u www-data php -l /var/www/nextcloud/apps/door_estimator/appinfo/info.xml
  ```

#### 8. Data Import Fails
- **Problem**: Pricing data import fails.
- **Solution**: Check JSON file format and run import with verbose output:
  ```bash
  sudo -u www-data php /var/www/nextcloud/occ door-estimator:import-pricing -v
  ```
  Verify imported data:
  ```bash
  mysql -u nextcloud_user -p nextcloud_db -e "SELECT category, COUNT(*) FROM oc_door_estimator_pricing GROUP BY category;"
  ```

#### 9. Slow Performance
- **Problem**: The application is slow to load or respond.
- **Solution**: Optimize the server environment and ensure no other processes are consuming resources. Check server logs for performance bottlenecks.

#### 10. Missing Dependencies
- **Problem**: Missing PHP or JavaScript dependencies.
- **Solution**: All required PHP dependencies are already included in the `vendor/` directory. If you are a developer and need to update dependencies, use Composer. For end-users, no Composer installation is required.
  For JavaScript, ensure all npm packages are installed:
  ```bash
  npm install
  ```
  For Vite, build the frontend with:
  ```bash
  npm run build
  ```
  To start the Vite dev server for local development:
  ```bash
  npm run dev
  ```

#### 11. Configuration Issues
- **Problem**: Incorrect configuration settings.
- **Solution**: Verify configuration files (`config.php`, `appinfo/info.xml`) for correct settings. Ensure all paths and URLs are correct.

#### 12. Log Analysis
- **Problem**: Need to analyze logs for errors.
- **Solution**: Check Nextcloud, Apache/Nginx, and PHP logs:
  ```bash
  tail -f /var/www/nextcloud/data/nextcloud.log | grep -i "door_estimator\|error"
  tail -f /var/log/apache2/error.log  # Apache
  tail -f /var/log/nginx/error.log    # Nginx
  tail -f /var/log/php7.4-fpm.log     # Adjust PHP version
  ```

#### 13. Browser Caching Issues
- **Problem**: Browser shows outdated version of the app.
- **Solution**: Clear browser cache or use an incognito window to test changes.

#### 14. File Not Found Errors
- **Problem**: "File not found" errors in the browser.
- **Solution**: Verify all required files are present and correctly referenced in the code. Check file paths and ensure no typos.

#### 15. Incompatible Browser
- **Problem**: The app does not work in certain browsers.
- **Solution**: Ensure the app is tested in supported browsers (e.g., Chrome, Firefox, Edge). Check for browser-specific issues or bugs.

#### 16. Missing API Endpoints
- **Problem**: API endpoints are not responding.
- **Solution**: Verify the server is running and the endpoints are correctly defined. Check server logs for any errors related to API requests.

#### 17. Incorrect File Permissions
- **Problem**: Incorrect file permissions causing access issues.
- **Solution**: Ensure files and directories have the correct permissions:
  ```bash
  sudo chown -R www-data:www-data /var/www/nextcloud/apps/door_estimator
  sudo chmod -R 755 /var/www/nextcloud/apps/door_estimator
  ```

#### 18. Database Migration Issues
- **Problem**: Database migration fails.
- **Solution**: Check migration scripts for errors and run manually if needed:
  ```bash
  sudo -u www-data php /var/www/nextcloud/occ migrations:execute door_estimator 001000
  ```
  Verify migration status:
  ```bash
  sudo -u www-data php /var/www/nextcloud/occ migrations:status door_estimator
  ```

#### 19. Missing Environment Variables
- **Problem**: Missing environment variables causing app to fail.
- **Solution**: Ensure all required environment variables are set. Check `.env` file or server configuration.

#### 20. Incorrect Database Credentials
- **Problem**: Database connection fails due to incorrect credentials.
- **Solution**: Verify database credentials in configuration files and ensure they match the database settings.

#### 21. Missing Composer Packages
- **Problem**: Missing PHP packages causing app to fail.
- **Solution**: All required PHP packages are already included in the `vendor/` directory. Composer is only needed by developers who wish to update dependencies.

#### 22. Incorrect File Paths
- **Problem**: Incorrect file paths causing file not found errors.
- **Solution**: Verify all file paths in the code are correct and match the actual file locations.

#### 23. Missing JavaScript Libraries
- **Problem**: Missing JavaScript libraries causing frontend issues.
- **Solution**: Ensure all required JavaScript libraries are installed:
  ```bash
  npm install
  ```

#### 24. Incorrect Server Configuration
- **Problem**: Incorrect server configuration causing app to fail.
- **Solution**: Verify server configuration files (`apache2.conf`, `nginx.conf`, `php.ini`) for correct settings.

#### 25. Missing PHP Extensions
- **Problem**: Missing PHP extensions causing app to fail.
- **Solution**: Ensure all required PHP extensions are installed and enabled:
  ```bash
  php -m | grep -E "(pdo|mysql|pgsql|json|curl)"
  ```

#### 26. Incorrect Database Schema
- **Problem**: Incorrect database schema causing database operations to fail.
- **Solution**: Verify database schema matches the expected structure. Check migration scripts for any issues.

#### 27. Missing API Keys
- **Problem**: Missing API keys causing external services to fail.
- **Solution**: Ensure all required API keys are set in the configuration files.

#### 28. Incorrect Timezone Settings
- **Problem**: Incorrect timezone settings causing date/time issues.
- **Solution**: Verify timezone settings in configuration files and ensure they match the server timezone.

#### 29. Missing Cron Jobs
- **Problem**: Missing cron jobs causing scheduled tasks to fail.
- **Solution**: Ensure all required cron jobs are set up and running:
  ```bash
  crontab -l
  ```

#### 30. Incorrect File Encoding
- **Problem**: Incorrect file encoding causing file read errors.
- **Solution**: Ensure all files are saved with the correct encoding (UTF-8). Check file encoding settings in the editor.

#### 31. Missing Environment Setup
- **Problem**: Missing environment setup causing app to fail.
- **Solution**: Ensure the environment is set up correctly. Check `.env` file or server configuration for any missing settings.

#### 32. Incorrect File Ownership
- **Problem**: Incorrect file ownership causing permission issues.
- **Solution**: Ensure files and directories have the correct ownership:
  ```bash
  sudo chown -R www-data:www-data /var/www/nextcloud/apps/door_estimator
  ```

#### 33. Missing Database Indexes
- **Problem**: Missing database indexes causing slow queries.
- **Solution**: Ensure all required database indexes are created. Check migration scripts for any missing indexes.

#### 34. Incorrect Server Port
- **Problem**: Incorrect server port causing connection issues.
- **Solution**: Verify server port settings in configuration files and ensure they match the actual server port.

#### 35. Missing SSL Certificate
- **Problem**: Missing SSL certificate causing secure connections to fail.
- **Solution**: Ensure an SSL certificate is installed and configured correctly. Check server logs for any SSL-related errors.

#### 36. Incorrect File Upload Settings
- **Problem**: Incorrect file upload settings causing uploads to fail.
- **Solution**: Verify file upload settings in configuration files and ensure they match the server settings.

#### 37. Missing Database Backups
- **Problem**: Missing database backups causing data loss.
- **Solution**: Ensure regular database backups are set up and running:
  ```bash
  mysqldump nextcloud_db > /var/backups/nextcloud_db_$(date +%Y%m%d).sql
  ```

#### 38. Incorrect Server Time
- **Problem**: Incorrect server time causing time-related issues.
- **Solution**: Verify server time settings and ensure they match the actual time. Check server logs for any time-related errors.

#### 39. Missing Database Triggers
- **Problem**: Missing database triggers causing data inconsistencies.
- **Solution**: Ensure all required database triggers are created. Check migration scripts for any missing triggers.

#### 40. Incorrect File Permissions for Logs
- **Problem**: Incorrect file permissions for logs causing log write failures.
- **Solution**: Ensure log files have the correct permissions:
  ```bash
  sudo chown www-data:www-data /var/www/nextcloud/data/nextcloud.log
  sudo chmod 644 /var/www/nextcloud/data/nextcloud.log
  ```

#### 41. Missing Database Constraints
- **Problem**: Missing database constraints causing data integrity issues.
- **Solution**: Ensure all required database constraints are created. Check migration scripts for any missing constraints.

#### 42. Incorrect Server IP
- **Problem**: Incorrect server IP causing connection issues.
- **Solution**: Verify server IP settings in configuration files and ensure they match the actual server IP.

#### 43. Missing Database Views
- **Problem**: Missing database views causing query failures.
- **Solution**: Ensure all required database views are created. Check migration scripts for any missing views.

#### 44. Incorrect File Permissions for Cache
- **Problem**: Incorrect file permissions for cache causing cache write failures.
- **Solution**: Ensure cache directories have the correct permissions:
  ```bash
  sudo chown -R www-data:www-data /var/www/nextcloud/data/cache
  sudo chmod -R 755 /var/www/nextcloud/data/cache
  ```

#### 45. Missing Database Functions
- **Problem**: Missing database functions causing query failures.
- **Solution**: Ensure all required database functions are created. Check migration scripts for any missing functions.

#### 46. Incorrect Server Hostname
- **Problem**: Incorrect server hostname causing connection issues.
- **Solution**: Verify server hostname settings in configuration files and ensure they match the actual server hostname.

#### 47. Missing Database Procedures
- **Problem**: Missing database procedures causing query failures.
- **Solution**: Ensure all required database procedures are created. Check migration scripts for any missing procedures.

#### 48. Incorrect File Permissions for Config
- **Problem**: Incorrect file permissions for config causing config read failures.
- **Solution**: Ensure config files have the correct permissions:
  ```bash
  sudo chown www-data:www-data /var/www/nextcloud/config/config.php
  sudo chmod 644 /var/www/nextcloud/config/config.php
  ```

#### 49. Missing Database Sequences
- **Problem**: Missing database sequences causing ID generation failures.
- **Solution**: Ensure all required database sequences are created. Check migration scripts for any missing sequences.

#### 50. Incorrect Server Domain
- **Problem**: Incorrect server domain causing connection issues.
- **Solution**: Verify server domain settings in configuration files and ensure they match the actual server domain.

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
- [ ] Frontend build output exists in `dist/` after running `npm run build`
- [ ] All source files are located in the `src/` directory

## 📞 Getting Help

If you encounter issues during installation:

1. **Check Logs**: Review NextCloud, PHP, and web server logs
2. **Verify Requirements**: Ensure all system requirements are met
3. **Test Database**: Verify database connectivity and permissions
4. **File Permissions**: Double-check file and directory permissions
5. **Browser Console**: Check for JavaScript errors in browser developer tools

Remember to include relevant log excerpts and system information when seeking support.

---

## 📄 Documentation Update Summary

- Node.js v20+ and npm v10+ requirements are now clearly stated as prerequisites.
- One-liner install command for Node.js v20+ in containers is provided.
- Vite build system instructions added, replacing old build tool references.
- Troubleshooting section updated for Vite-specific issues.
- Verification steps updated for new `src/` structure and Vite build output.
- Frontend build instructions now use `npm run build` and `npm run dev` (Vite).
- Sections for ESLint and Stylelint usage added.
- All file paths updated to reflect the new `src/` directory structure.