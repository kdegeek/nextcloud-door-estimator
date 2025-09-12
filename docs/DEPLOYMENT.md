# Door Estimator - Deployment Guide

This guide covers the deployment, configuration, and maintenance procedures for the Door Estimator Nextcloud app.

## Table of Contents

1. [System Requirements](#system-requirements)
2. [Installation](#installation)
3. [Configuration](#configuration)
4. [Health Monitoring](#health-monitoring)
5. [Maintenance](#maintenance)
6. [Troubleshooting](#troubleshooting)
7. [Backup and Recovery](#backup-and-recovery)

## System Requirements

### Nextcloud Requirements
- Nextcloud 29.0 or higher (tested up to 31.x)
- PHP 8.0 or higher (recommended: PHP 8.1+)
- MySQL 5.7+ / MariaDB 10.2+ / PostgreSQL 11+ / SQLite 3.8+

### PHP Extensions
- `pdo` - Database connectivity
- `json` - JSON data processing
- `curl` - HTTP client functionality
- `mbstring` - Multi-byte string handling
- `xml` - XML processing

### PHP Libraries
- `TCPDF` - PDF generation (installed via Composer)
- `PhpSpreadsheet` - Excel/CSV import (installed via Composer)

### System Resources
- **Memory**: Minimum 256MB PHP memory limit (512MB recommended)
- **Storage**: 50MB for app files + data storage for quotes and imports
- **Performance**: Supports up to 25 concurrent users

## Installation

### Method 1: Nextcloud App Store (Recommended)

1. Log in to your Nextcloud instance as an administrator
2. Navigate to **Apps** → **App Store**
3. Search for "Door Estimator"
4. Click **Download and enable**

### Method 2: Manual Installation

1. Download the latest release from GitHub:
   ```bash
   wget https://github.com/millworkproducts/door-estimator/releases/latest/download/door_estimator.tar.gz
   ```

2. Extract to your Nextcloud apps directory:
   ```bash
   cd /path/to/nextcloud/apps
   tar -xzf door_estimator.tar.gz
   ```

3. Set proper permissions:
   ```bash
   chown -R www-data:www-data door_estimator
   chmod -R 755 door_estimator
   ```

4. Install PHP dependencies:
   ```bash
   cd door_estimator
   composer install --no-dev --optimize-autoloader
   ```

5. Build frontend assets:
   ```bash
   npm install
   npm run build
   ```

6. Enable the app via Nextcloud admin interface or CLI:
   ```bash
   php occ app:enable door_estimator
   ```

### Post-Installation Verification

Run the installation health check:
```bash
php occ door-estimator:import --health-check
```

Expected output should show all systems as "HEALTHY".

## Configuration

### Admin Settings

Access admin settings via **Settings** → **Administration** → **Door Estimator**.

#### Default Markup Percentages
Configure default markup percentages for each product category:
- **Doors**: 15% (default)
- **Frames**: 12% (default)
- **Hardware**: 18% (default)
- **Door Options**: 15% (default)
- **Frame Options**: 12% (default)
- **Hinges**: 18% (default)
- **Weatherstrip**: 18% (default)
- **Closers**: 18% (default)
- **Locksets**: 18% (default)
- **Exit Devices**: 18% (default)

#### Performance Settings
- **Cache Timeout**: 3600 seconds (1 hour)
- **PDF Generation Timeout**: 30 seconds
- **Max Import File Size**: 5MB

#### Feature Flags
- **PDF Generation**: Enabled
- **Data Import**: Enabled
- **Data Export**: Enabled
- **Quote Sharing**: Disabled (future feature)

#### System Monitoring
- **Performance Monitoring**: Enabled
- **Debug Logging**: Disabled (enable only for troubleshooting)
- **Maintenance Mode**: Disabled

### Configuration via CLI

Export current configuration:
```bash
php occ door-estimator:import --export-config
```

Import configuration from backup:
```bash
php occ door-estimator:import --import-config config_backup.json
```

### Environment Variables

Set these in your Nextcloud config.php if needed:

```php
'door_estimator' => [
    'max_import_file_size' => 10485760, // 10MB
    'cache_timeout' => 7200, // 2 hours
    'enable_debug_logging' => false,
    'maintenance_mode' => false,
],
```

## Health Monitoring

### Automated Health Checks

The app includes comprehensive health monitoring:

1. **Database Connectivity**: Verifies database connection and table integrity
2. **File System Access**: Checks app data directories and permissions
3. **Cache System**: Tests distributed cache functionality
4. **Configuration Validation**: Validates all configuration values
5. **Dependencies**: Verifies required PHP extensions and libraries
6. **Performance Metrics**: Monitors query times and memory usage

### Running Health Checks

#### Via Admin Interface
1. Go to **Settings** → **Administration** → **Door Estimator**
2. Click **Run Health Check**
3. Review results in the Health Check Results section

#### Via CLI
```bash
php occ door-estimator:import --health-check
```

### Health Check Statuses

- **HEALTHY**: All systems operating normally
- **WARNING**: Non-critical issues detected (app still functional)
- **ERROR**: Critical issues requiring immediate attention

### Monitoring Integration

Health check results can be integrated with external monitoring systems:

```bash
# Check exit code (0 = healthy, 1 = issues)
php occ door-estimator:import --health-check
echo $?
```

## Maintenance

### Regular Maintenance Tasks

#### Daily
- Monitor system health via admin interface
- Check error logs for any issues
- Verify backup completion

#### Weekly
- Review performance metrics
- Clean up temporary import files
- Update pricing data if needed

#### Monthly
- Review and update markup percentages
- Analyze usage patterns
- Plan capacity upgrades if needed

### Database Maintenance

#### Optimize Database Tables
```sql
OPTIMIZE TABLE door_estimator_pricing;
OPTIMIZE TABLE door_estimator_quotes;
```

#### Clean Up Old Data
```sql
-- Remove quotes older than 2 years (adjust as needed)
DELETE FROM door_estimator_quotes 
WHERE created_at < DATE_SUB(NOW(), INTERVAL 2 YEAR);
```

### File System Maintenance

#### Clean Temporary Files
```bash
# Remove temporary import files older than 24 hours
find /path/to/nextcloud/data/appdata_*/door_estimator/imports -name "temp_*" -mtime +1 -delete
```

#### Monitor Disk Usage
```bash
du -sh /path/to/nextcloud/data/appdata_*/door_estimator/
```

### Performance Optimization

#### Enable OPcache
Add to php.ini:
```ini
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=10000
opcache.revalidate_freq=2
```

#### Database Indexing
Ensure all recommended indexes are present:
```sql
SHOW INDEX FROM door_estimator_pricing;
SHOW INDEX FROM door_estimator_quotes;
```

## Troubleshooting

### Common Issues

#### 1. Import Files Too Large
**Symptoms**: "File too large" error during import
**Solution**: 
- Increase `max_import_file_size` in admin settings
- Check PHP `upload_max_filesize` and `post_max_size`

#### 2. PDF Generation Fails
**Symptoms**: "PDF generation failed" error
**Solution**:
- Verify TCPDF library is installed
- Check PHP memory limit (increase to 512MB if needed)
- Increase `pdf_generation_timeout` setting

#### 3. Slow Performance
**Symptoms**: Long response times, timeouts
**Solution**:
- Enable performance monitoring
- Check database indexes
- Increase cache timeout
- Monitor memory usage

#### 4. Database Connection Errors
**Symptoms**: "Database connection failed" in health check
**Solution**:
- Verify Nextcloud database configuration
- Check database server status
- Review database user permissions

### Debug Mode

Enable debug logging for detailed troubleshooting:

1. Go to admin settings
2. Enable "Debug Logging"
3. Reproduce the issue
4. Check Nextcloud logs: `data/nextcloud.log`
5. Disable debug logging when done

### Log Analysis

Important log entries to monitor:
```bash
# Filter Door Estimator logs
grep "door_estimator" /path/to/nextcloud/data/nextcloud.log

# Monitor performance issues
grep "Performance metric" /path/to/nextcloud/data/nextcloud.log
```

## Backup and Recovery

### Configuration Backup

Export configuration before major changes:
```bash
php occ door-estimator:import --export-config
```

### Database Backup

#### Full Database Backup
```bash
mysqldump -u username -p nextcloud_db > nextcloud_backup.sql
```

#### App-Specific Tables Only
```bash
mysqldump -u username -p nextcloud_db \
  door_estimator_pricing \
  door_estimator_quotes > door_estimator_backup.sql
```

### File System Backup

#### App Data Directory
```bash
tar -czf door_estimator_data_backup.tar.gz \
  /path/to/nextcloud/data/appdata_*/door_estimator/
```

#### App Files
```bash
tar -czf door_estimator_app_backup.tar.gz \
  /path/to/nextcloud/apps/door_estimator/
```

### Recovery Procedures

#### Restore Configuration
```bash
php occ door-estimator:import --import-config backup_config.json
```

#### Restore Database
```bash
mysql -u username -p nextcloud_db < door_estimator_backup.sql
```

#### Restore App Data
```bash
cd /path/to/nextcloud/data/
tar -xzf door_estimator_data_backup.tar.gz
chown -R www-data:www-data appdata_*/door_estimator/
```

### Disaster Recovery

1. **Restore Nextcloud instance** from backup
2. **Reinstall Door Estimator app**
3. **Restore database tables** from backup
4. **Restore app data directory** from backup
5. **Import configuration** from backup
6. **Run health check** to verify recovery
7. **Test core functionality** (create quote, generate PDF)

## Security Considerations

### File Permissions
```bash
# App directory
chmod -R 755 /path/to/nextcloud/apps/door_estimator
chown -R www-data:www-data /path/to/nextcloud/apps/door_estimator

# App data directory
chmod -R 750 /path/to/nextcloud/data/appdata_*/door_estimator
chown -R www-data:www-data /path/to/nextcloud/data/appdata_*/door_estimator
```

### Database Security
- Use dedicated database user with minimal privileges
- Enable SSL for database connections
- Regular security updates for database server

### Web Server Security
- Enable HTTPS with valid SSL certificate
- Configure proper security headers
- Regular security updates for web server

## Support and Updates

### Getting Support
- **Documentation**: Check this guide and other docs in `/docs/`
- **Issues**: Report bugs on GitHub Issues
- **Community**: Nextcloud community forums

### Updates
- **Automatic**: Enable automatic updates in Nextcloud app settings
- **Manual**: Download from GitHub releases and follow installation steps
- **CLI**: Use `php occ app:update door_estimator`

### Version Compatibility
- Always check compatibility before updating Nextcloud
- Test updates in staging environment first
- Keep backups before major updates

---

For additional help, consult the other documentation files:
- [Installation Guide](INSTALLATION.md)
- [Architecture Overview](ARCHITECTURE.md)
- [Security Guide](SECURITY.md)
- [Testing Guide](TESTING.md)