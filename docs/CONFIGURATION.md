# Door Estimator - Configuration Guide

This guide covers all configuration options and settings for the Door Estimator app.

## Table of Contents

1. [Configuration Overview](#configuration-overview)
2. [Admin Settings](#admin-settings)
3. [User Preferences](#user-preferences)
4. [Environment Configuration](#environment-configuration)
5. [Performance Tuning](#performance-tuning)
6. [Security Settings](#security-settings)
7. [Feature Flags](#feature-flags)
8. [Configuration Management](#configuration-management)

## Configuration Overview

The Door Estimator app uses a hierarchical configuration system:

1. **System Defaults**: Built-in default values
2. **App Configuration**: Admin-configurable app-wide settings
3. **User Preferences**: Individual user settings
4. **Environment Overrides**: Config.php overrides

## Admin Settings

Access via **Settings** → **Administration** → **Door Estimator**

### Markup Configuration

Default markup percentages applied to new quotes:

| Category | Default | Range | Description |
|----------|---------|-------|-------------|
| Doors | 15% | 0-100% | Standard door markup |
| Door Options | 15% | 0-100% | Door accessories and modifications |
| Inserts | 15% | 0-100% | Door inserts and panels |
| Frames | 12% | 0-100% | Door frames and jambs |
| Frame Options | 12% | 0-100% | Frame accessories and modifications |
| Hinges | 18% | 0-100% | All hinge types |
| Weatherstrip | 18% | 0-100% | Weatherstripping materials |
| Closers | 18% | 0-100% | Door closers and operators |
| Locksets | 18% | 0-100% | Locks and locking hardware |
| Exit Devices | 18% | 0-100% | Panic bars and exit hardware |
| Hardware | 18% | 0-100% | General hardware items |

#### Configuration via Admin Interface

1. Navigate to admin settings
2. Modify values in the "Default Markup Percentages" section
3. Click "Save Markup Settings"

#### Configuration via CLI

```bash
# Export current markups
php occ door-estimator:import --export-config > current_config.json

# Edit the file and import
php occ door-estimator:import --import-config modified_config.json
```

### File Upload Settings

| Setting | Default | Range | Description |
|---------|---------|-------|-------------|
| Max Import File Size | 5MB | 1MB-50MB | Maximum size for data import files |
| Allowed Formats | json,csv,xlsx,xls | - | Supported import file formats |
| Import Rate Limit | 1/second | 1-10/second | Maximum imports per second per user |

### Performance Settings

| Setting | Default | Range | Description |
|---------|---------|-------|-------------|
| Cache Timeout | 3600s | 60-86400s | How long to cache pricing data |
| Search Result Limit | 1000 | 100-5000 | Maximum search results returned |
| Quote List Page Size | 50 | 10-200 | Quotes per page in lists |
| PDF Generation Timeout | 30s | 5-120s | Maximum time for PDF generation |

### System Monitoring

| Setting | Default | Description |
|---------|---------|-------------|
| Performance Monitoring | Enabled | Log performance metrics |
| Debug Logging | Disabled | Enable detailed debug logs |
| Maintenance Mode | Disabled | Temporarily disable app |

## User Preferences

Individual user settings accessible via user menu:

### Interface Preferences

| Setting | Default | Options | Description |
|---------|---------|---------|-------------|
| Theme | Auto | Auto, Light, Dark | Interface color scheme |
| Default Quote Name | "Quote {date}" | Custom pattern | Template for new quote names |
| Auto Save Quotes | Enabled | On/Off | Automatically save quote changes |
| Show Advanced Options | Disabled | On/Off | Show advanced interface options |

### Localization

| Setting | Default | Options | Description |
|---------|---------|---------|-------------|
| Currency | USD | USD, CAD, EUR, etc. | Display currency |
| Decimal Places | 2 | 0-4 | Price decimal precision |
| Date Format | Y-m-d | Various | Date display format |
| Time Format | H:i:s | 12/24 hour | Time display format |

### Setting User Preferences

#### Via Interface
1. Click user avatar → Settings
2. Navigate to "Door Estimator" section
3. Modify preferences
4. Changes save automatically

#### Via API
```javascript
// Set user preference
fetch('/apps/door_estimator/api/user/preferences', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        theme: 'dark',
        auto_save_quotes: true
    })
});
```

## Environment Configuration

Override settings in Nextcloud's `config/config.php`:

```php
<?php
$CONFIG = [
    // ... other Nextcloud config ...
    
    'door_estimator' => [
        // Markup defaults (percentages)
        'default_door_markup' => 15,
        'default_frame_markup' => 12,
        'default_hardware_markup' => 18,
        
        // File upload settings
        'max_import_file_size' => 10485760, // 10MB
        'allowed_import_formats' => 'json,csv,xlsx,xls',
        'import_rate_limit' => 2, // per second
        
        // Performance settings
        'cache_timeout' => 7200, // 2 hours
        'search_result_limit' => 2000,
        'pdf_generation_timeout' => 60, // 1 minute
        
        // System settings
        'enable_debug_logging' => false,
        'enable_performance_monitoring' => true,
        'maintenance_mode' => false,
        
        // Security settings
        'enable_csrf_protection' => true,
        'session_timeout' => 3600,
        'max_login_attempts' => 5,
        
        // Feature flags
        'enable_pdf_generation' => true,
        'enable_data_import' => true,
        'enable_data_export' => true,
        'enable_quote_sharing' => false,
    ],
];
```

## Performance Tuning

### Database Optimization

#### Connection Settings
```php
// In config.php
'dbdriveroptions' => [
    PDO::MYSQL_ATTR_INIT_COMMAND => 'SET sql_mode="STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION"',
    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
],
```

#### Query Optimization
- Ensure all recommended indexes are present
- Monitor slow query log
- Use EXPLAIN for complex queries

#### Index Verification
```sql
-- Check pricing table indexes
SHOW INDEX FROM door_estimator_pricing;

-- Expected indexes:
-- PRIMARY (id)
-- idx_de_pricing_category (category)
-- idx_de_pricing_item_name (item_name)
-- idx_de_pricing_cat_subcat (category, subcategory)
-- idx_de_pricing_cat_item (category, item_name)
-- idx_de_pricing_updated (updated_at)
-- uniq_de_pricing_item (category, subcategory, item_name)
```

### Caching Configuration

#### Redis Cache (Recommended)
```php
// In config.php
'memcache.distributed' => '\OC\Memcache\Redis',
'memcache.locking' => '\OC\Memcache\Redis',
'redis' => [
    'host' => 'localhost',
    'port' => 6379,
    'timeout' => 0.0,
    'password' => '', // Optional
    'dbindex' => 0,
],
```

#### APCu Cache (Alternative)
```php
// In config.php
'memcache.local' => '\OC\Memcache\APCu',
```

### PHP Configuration

#### Memory Settings
```ini
; php.ini
memory_limit = 512M
max_execution_time = 300
max_input_time = 300
```

#### Upload Settings
```ini
; php.ini
upload_max_filesize = 50M
post_max_size = 50M
max_file_uploads = 20
```

#### OPcache Settings
```ini
; php.ini
opcache.enable = 1
opcache.memory_consumption = 256
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 10000
opcache.revalidate_freq = 2
opcache.save_comments = 1
```

## Security Settings

### Authentication & Authorization

#### Session Configuration
```php
// In config.php
'door_estimator' => [
    'session_timeout' => 3600, // 1 hour
    'max_login_attempts' => 5,
    'enable_csrf_protection' => true,
],
```

#### Role-Based Access
- **Admin Users**: Full access to all features
- **Regular Users**: Quote creation and management only
- **Guest Users**: No access (app requires authentication)

### File Upload Security

#### Validation Settings
```php
'door_estimator' => [
    'max_import_file_size' => 5242880, // 5MB
    'allowed_import_formats' => 'json,csv,xlsx,xls',
    'validate_file_content' => true,
    'scan_uploaded_files' => true,
],
```

#### File Type Validation
- MIME type checking
- File extension validation
- Content structure validation
- Virus scanning (if available)

### Data Protection

#### Encryption
- All data encrypted at rest (via Nextcloud)
- HTTPS required for all communications
- Database connections use SSL when available

#### Privacy
- User data isolation (users see only their quotes)
- No data sharing between users
- Audit logging for admin actions

## Feature Flags

Control feature availability:

```php
'door_estimator' => [
    'enable_pdf_generation' => true,    // PDF quote generation
    'enable_data_import' => true,       // Pricing data import
    'enable_data_export' => true,       // Pricing data export
    'enable_quote_sharing' => false,    // Share quotes between users (future)
    'enable_api_access' => true,        // REST API access
    'enable_bulk_operations' => true,   // Bulk quote operations
],
```

### Feature Flag Management

#### Via Admin Interface
1. Go to admin settings
2. Navigate to "Feature Settings"
3. Toggle features on/off
4. Click "Save Feature Settings"

#### Via CLI
```bash
# Disable PDF generation
php occ config:app:set door_estimator enable_pdf_generation --value="false"

# Enable quote sharing
php occ config:app:set door_estimator enable_quote_sharing --value="true"
```

## Configuration Management

### Backup Configuration

#### Export All Settings
```bash
php occ door-estimator:import --export-config > door_estimator_config.json
```

#### Export Specific Settings
```bash
# Export only markup settings
php occ config:app:get door_estimator | grep markup
```

### Import Configuration

#### From Backup File
```bash
php occ door-estimator:import --import-config door_estimator_config.json
```

#### Individual Settings
```bash
# Set individual config values
php occ config:app:set door_estimator default_door_markup --value="20"
php occ config:app:set door_estimator cache_timeout --value="7200"
```

### Configuration Validation

#### Validate Current Config
```bash
php occ door-estimator:import --health-check
```

#### Check Specific Values
```bash
# List all app configuration
php occ config:app:get door_estimator

# Get specific value
php occ config:app:get door_estimator default_door_markup
```

### Migration Between Environments

#### Development to Staging
```bash
# Export from development
php occ door-estimator:import --export-config > dev_config.json

# Import to staging
scp dev_config.json staging:/tmp/
ssh staging "php occ door-estimator:import --import-config /tmp/dev_config.json"
```

#### Configuration Templates

Create environment-specific templates:

**production.json**
```json
{
    "app_config": {
        "enable_debug_logging": "false",
        "enable_performance_monitoring": "true",
        "cache_timeout": "7200",
        "max_import_file_size": "10485760"
    }
}
```

**development.json**
```json
{
    "app_config": {
        "enable_debug_logging": "true",
        "enable_performance_monitoring": "false",
        "cache_timeout": "300",
        "max_import_file_size": "52428800"
    }
}
```

## Troubleshooting Configuration

### Common Issues

#### 1. Settings Not Saving
- Check file permissions on data directory
- Verify database connectivity
- Check for JavaScript errors in browser console

#### 2. Performance Issues
- Increase cache timeout
- Verify database indexes
- Check PHP memory limits

#### 3. Import Failures
- Verify file size limits
- Check allowed file formats
- Review import rate limits

### Debug Configuration

#### Enable Debug Mode
```bash
php occ config:app:set door_estimator enable_debug_logging --value="true"
```

#### View Debug Logs
```bash
tail -f /path/to/nextcloud/data/nextcloud.log | grep door_estimator
```

#### Disable Debug Mode
```bash
php occ config:app:set door_estimator enable_debug_logging --value="false"
```

---

For more information, see:
- [Deployment Guide](DEPLOYMENT.md)
- [Security Guide](SECURITY.md)
- [Performance Guide](PERFORMANCE.md)