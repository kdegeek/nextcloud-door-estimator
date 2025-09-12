# Door Estimator - Maintenance Guide

This guide covers routine maintenance procedures, monitoring, and troubleshooting for the Door Estimator app.

## Table of Contents

1. [Maintenance Overview](#maintenance-overview)
2. [Routine Maintenance](#routine-maintenance)
3. [Health Monitoring](#health-monitoring)
4. [Performance Monitoring](#performance-monitoring)
5. [Database Maintenance](#database-maintenance)
6. [File System Maintenance](#file-system-maintenance)
7. [Log Management](#log-management)
8. [Backup Procedures](#backup-procedures)
9. [Troubleshooting](#troubleshooting)

## Maintenance Overview

Regular maintenance ensures optimal performance, security, and reliability of the Door Estimator app.

### Maintenance Schedule

| Task | Frequency | Estimated Time |
|------|-----------|----------------|
| Health Check | Daily | 2 minutes |
| Log Review | Daily | 5 minutes |
| Performance Review | Weekly | 15 minutes |
| Database Optimization | Weekly | 10 minutes |
| File Cleanup | Weekly | 5 minutes |
| Security Review | Monthly | 30 minutes |
| Full Backup | Monthly | 30 minutes |
| Configuration Review | Quarterly | 60 minutes |

## Routine Maintenance

### Daily Tasks

#### 1. System Health Check
```bash
# Quick health check
php occ door-estimator:import --health-check

# Check exit code
if [ $? -eq 0 ]; then
    echo "System healthy"
else
    echo "Issues detected - review logs"
fi
```

#### 2. Log Review
```bash
# Check for errors in the last 24 hours
grep -A 5 -B 5 "door_estimator.*ERROR" /path/to/nextcloud/data/nextcloud.log | tail -50

# Check for warnings
grep -A 3 -B 3 "door_estimator.*WARN" /path/to/nextcloud/data/nextcloud.log | tail -20
```

#### 3. Monitor Disk Usage
```bash
# Check app data directory size
du -sh /path/to/nextcloud/data/appdata_*/door_estimator/

# Check for large files
find /path/to/nextcloud/data/appdata_*/door_estimator/ -size +10M -ls
```

### Weekly Tasks

#### 1. Performance Review
```bash
# Check performance metrics from logs
grep "Performance metric" /path/to/nextcloud/data/nextcloud.log | tail -100

# Analyze slow operations
grep "door_estimator.*duration_ms" /path/to/nextcloud/data/nextcloud.log | \
awk '$NF > 1000 {print}' | tail -20
```

#### 2. Database Statistics
```sql
-- Check table sizes
SELECT 
    table_name,
    table_rows,
    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'Size (MB)'
FROM information_schema.tables 
WHERE table_name LIKE 'door_estimator_%';

-- Check recent activity
SELECT 
    DATE(created_at) as date,
    COUNT(*) as quotes_created
FROM door_estimator_quotes 
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY DATE(created_at);
```

#### 3. File System Cleanup
```bash
# Remove temporary files older than 7 days
find /path/to/nextcloud/data/appdata_*/door_estimator/imports -name "temp_*" -mtime +7 -delete

# Remove old PDF files (if not needed)
find /path/to/nextcloud/data/appdata_*/door_estimator/quotes -name "*.pdf" -mtime +30 -delete

# Clean up old backup files
find /path/to/nextcloud/data/appdata_*/door_estimator/backups -name "*.json" -mtime +90 -delete
```

### Monthly Tasks

#### 1. Security Review
```bash
# Check for failed login attempts
grep "door_estimator.*authentication.*failed" /path/to/nextcloud/data/nextcloud.log | \
tail -50

# Review admin actions
grep "door_estimator.*admin.*action" /path/to/nextcloud/data/nextcloud.log | \
tail -100

# Check file upload attempts
grep "door_estimator.*upload.*rejected" /path/to/nextcloud/data/nextcloud.log | \
tail -20
```

#### 2. Configuration Backup
```bash
# Export current configuration
php occ door-estimator:import --export-config > \
    "door_estimator_config_$(date +%Y%m%d).json"

# Store in secure location
mv door_estimator_config_*.json /path/to/secure/backup/location/
```

#### 3. Database Optimization
```sql
-- Optimize tables
OPTIMIZE TABLE door_estimator_pricing;
OPTIMIZE TABLE door_estimator_quotes;

-- Update table statistics
ANALYZE TABLE door_estimator_pricing;
ANALYZE TABLE door_estimator_quotes;

-- Check for fragmentation
SELECT 
    table_name,
    ROUND(data_free / 1024 / 1024, 2) AS 'Fragmentation (MB)'
FROM information_schema.tables 
WHERE table_name LIKE 'door_estimator_%' 
AND data_free > 0;
```

## Health Monitoring

### Automated Health Checks

#### Setup Cron Job
```bash
# Add to crontab for automated daily checks
0 6 * * * /usr/bin/php /path/to/nextcloud/occ door-estimator:import --health-check >> /var/log/door_estimator_health.log 2>&1
```

#### Health Check Script
```bash
#!/bin/bash
# health_check.sh

LOGFILE="/var/log/door_estimator_health.log"
DATE=$(date '+%Y-%m-%d %H:%M:%S')

echo "[$DATE] Starting health check..." >> $LOGFILE

# Run health check
php /path/to/nextcloud/occ door-estimator:import --health-check >> $LOGFILE 2>&1

if [ $? -eq 0 ]; then
    echo "[$DATE] Health check passed" >> $LOGFILE
else
    echo "[$DATE] Health check failed - sending alert" >> $LOGFILE
    # Send alert (email, Slack, etc.)
    echo "Door Estimator health check failed at $DATE" | \
        mail -s "Door Estimator Alert" admin@example.com
fi
```

### Health Metrics

#### Key Metrics to Monitor

1. **Database Response Time**: < 100ms for simple queries
2. **Memory Usage**: < 256MB per process
3. **Cache Hit Rate**: > 80%
4. **Error Rate**: < 1% of requests
5. **File System Access**: < 50ms for file operations

#### Custom Health Checks
```bash
# Check database connectivity
mysql -u username -p -e "SELECT COUNT(*) FROM door_estimator_pricing;" > /dev/null
if [ $? -ne 0 ]; then
    echo "Database connectivity issue"
fi

# Check file permissions
if [ ! -w "/path/to/nextcloud/data/appdata_*/door_estimator/" ]; then
    echo "File permission issue"
fi

# Check PHP extensions
php -m | grep -E "(pdo|json|curl|mbstring|xml)" | wc -l
if [ $? -ne 5 ]; then
    echo "Missing PHP extensions"
fi
```

## Performance Monitoring

### Performance Metrics Collection

#### Enable Performance Monitoring
```bash
php occ config:app:set door_estimator enable_performance_monitoring --value="true"
```

#### Analyze Performance Logs
```bash
# Extract performance metrics
grep "Performance metric" /path/to/nextcloud/data/nextcloud.log | \
    jq -r '[.timestamp, .operation, .duration_ms] | @csv' > performance_data.csv

# Find slowest operations
grep "Performance metric" /path/to/nextcloud/data/nextcloud.log | \
    jq -r 'select(.duration_ms > 1000) | [.timestamp, .operation, .duration_ms] | @csv'
```

#### Performance Thresholds

| Operation | Warning (ms) | Critical (ms) |
|-----------|--------------|---------------|
| Price Lookup | 500 | 1000 |
| Quote Save | 1000 | 2000 |
| PDF Generation | 5000 | 10000 |
| Data Import | 10000 | 30000 |
| Database Query | 100 | 500 |

### Performance Optimization

#### Database Query Optimization
```sql
-- Find slow queries
SELECT 
    query_time,
    lock_time,
    rows_sent,
    rows_examined,
    sql_text
FROM mysql.slow_log 
WHERE sql_text LIKE '%door_estimator%'
ORDER BY query_time DESC 
LIMIT 10;
```

#### Cache Performance
```bash
# Check Redis cache stats (if using Redis)
redis-cli info stats | grep -E "(keyspace_hits|keyspace_misses)"

# Calculate hit rate
redis-cli info stats | awk -F: '/keyspace_hits/{hits=$2} /keyspace_misses/{misses=$2} END{print "Hit rate:", hits/(hits+misses)*100"%"}'
```

## Database Maintenance

### Regular Database Tasks

#### Index Maintenance
```sql
-- Check index usage
SELECT 
    table_name,
    index_name,
    cardinality,
    sub_part,
    packed,
    nullable,
    index_type
FROM information_schema.statistics 
WHERE table_name LIKE 'door_estimator_%'
ORDER BY table_name, seq_in_index;

-- Rebuild indexes if needed
ALTER TABLE door_estimator_pricing ENGINE=InnoDB;
ALTER TABLE door_estimator_quotes ENGINE=InnoDB;
```

#### Data Cleanup
```sql
-- Remove old temporary data (if any)
DELETE FROM door_estimator_pricing 
WHERE item_name LIKE 'temp_%' 
AND created_at < DATE_SUB(NOW(), INTERVAL 1 DAY);

-- Archive old quotes (optional)
CREATE TABLE door_estimator_quotes_archive AS 
SELECT * FROM door_estimator_quotes 
WHERE created_at < DATE_SUB(NOW(), INTERVAL 2 YEAR);

-- Remove archived quotes from main table
DELETE FROM door_estimator_quotes 
WHERE created_at < DATE_SUB(NOW(), INTERVAL 2 YEAR);
```

#### Database Backup
```bash
# Full backup
mysqldump -u username -p --single-transaction --routines --triggers \
    nextcloud_db door_estimator_pricing door_estimator_quotes > \
    door_estimator_backup_$(date +%Y%m%d).sql

# Compressed backup
mysqldump -u username -p --single-transaction --routines --triggers \
    nextcloud_db door_estimator_pricing door_estimator_quotes | \
    gzip > door_estimator_backup_$(date +%Y%m%d).sql.gz
```

## File System Maintenance

### Directory Structure Monitoring
```bash
# Check directory structure
find /path/to/nextcloud/data/appdata_*/door_estimator/ -type d -ls

# Expected directories:
# - quotes/
# - imports/
# - exports/
# - backups/
```

### File Cleanup Procedures

#### Automated Cleanup Script
```bash
#!/bin/bash
# cleanup.sh

APP_DATA_DIR="/path/to/nextcloud/data/appdata_*/door_estimator"

# Remove temporary files older than 24 hours
find $APP_DATA_DIR/imports -name "temp_*" -mtime +1 -delete

# Remove old PDF files (older than 90 days)
find $APP_DATA_DIR/quotes -name "*.pdf" -mtime +90 -delete

# Remove old backup files (older than 180 days)
find $APP_DATA_DIR/backups -name "*.json" -mtime +180 -delete

# Remove empty directories
find $APP_DATA_DIR -type d -empty -delete

echo "Cleanup completed at $(date)"
```

#### Manual Cleanup
```bash
# Check large files
find /path/to/nextcloud/data/appdata_*/door_estimator/ -size +50M -ls

# Check old files
find /path/to/nextcloud/data/appdata_*/door_estimator/ -mtime +365 -ls

# Check file permissions
find /path/to/nextcloud/data/appdata_*/door_estimator/ ! -user www-data -ls
```

## Log Management

### Log Rotation
```bash
# Setup logrotate for Door Estimator logs
cat > /etc/logrotate.d/door_estimator << EOF
/var/log/door_estimator*.log {
    daily
    rotate 30
    compress
    delaycompress
    missingok
    notifempty
    create 644 www-data www-data
}
EOF
```

### Log Analysis

#### Error Analysis
```bash
# Count errors by type
grep "door_estimator.*ERROR" /path/to/nextcloud/data/nextcloud.log | \
    awk '{print $5}' | sort | uniq -c | sort -nr

# Recent errors
grep "door_estimator.*ERROR" /path/to/nextcloud/data/nextcloud.log | tail -20
```

#### Performance Analysis
```bash
# Average response times
grep "Performance metric" /path/to/nextcloud/data/nextcloud.log | \
    jq -r '.duration_ms' | awk '{sum+=$1; count++} END {print "Average:", sum/count "ms"}'

# Operations by frequency
grep "Performance metric" /path/to/nextcloud/data/nextcloud.log | \
    jq -r '.operation' | sort | uniq -c | sort -nr
```

## Backup Procedures

### Automated Backup Script
```bash
#!/bin/bash
# backup.sh

BACKUP_DIR="/path/to/backups/door_estimator"
DATE=$(date +%Y%m%d_%H%M%S)

# Create backup directory
mkdir -p $BACKUP_DIR/$DATE

# Export configuration
php /path/to/nextcloud/occ door-estimator:import --export-config > \
    $BACKUP_DIR/$DATE/config.json

# Backup database
mysqldump -u username -p --single-transaction \
    nextcloud_db door_estimator_pricing door_estimator_quotes | \
    gzip > $BACKUP_DIR/$DATE/database.sql.gz

# Backup app data
tar -czf $BACKUP_DIR/$DATE/appdata.tar.gz \
    /path/to/nextcloud/data/appdata_*/door_estimator/

# Remove old backups (keep 30 days)
find $BACKUP_DIR -type d -mtime +30 -exec rm -rf {} \;

echo "Backup completed: $BACKUP_DIR/$DATE"
```

### Backup Verification
```bash
# Verify database backup
gunzip -c database.sql.gz | mysql -u username -p test_db

# Verify config backup
php -l config.json

# Verify app data backup
tar -tzf appdata.tar.gz | head -10
```

## Troubleshooting

### Common Issues and Solutions

#### 1. High Memory Usage
```bash
# Check PHP memory usage
ps aux | grep php | awk '{sum+=$6} END {print "Total memory:", sum/1024 "MB"}'

# Solution: Increase PHP memory limit
echo "memory_limit = 512M" >> /etc/php/8.1/apache2/php.ini
```

#### 2. Slow Database Queries
```sql
-- Enable slow query log
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 1;

-- Check for missing indexes
SELECT * FROM sys.schema_unused_indexes WHERE object_schema = 'nextcloud_db';
```

#### 3. File Permission Issues
```bash
# Fix permissions
chown -R www-data:www-data /path/to/nextcloud/data/appdata_*/door_estimator/
chmod -R 750 /path/to/nextcloud/data/appdata_*/door_estimator/
```

#### 4. Cache Issues
```bash
# Clear app cache
php occ config:app:delete door_estimator cache_data

# Restart Redis (if using)
systemctl restart redis
```

### Emergency Procedures

#### App Disable/Enable
```bash
# Disable app in emergency
php occ app:disable door_estimator

# Enable app after fix
php occ app:enable door_estimator
```

#### Maintenance Mode
```bash
# Enable maintenance mode
php occ config:app:set door_estimator maintenance_mode --value="true"

# Disable maintenance mode
php occ config:app:set door_estimator maintenance_mode --value="false"
```

#### Database Recovery
```bash
# Restore from backup
gunzip -c door_estimator_backup_YYYYMMDD.sql.gz | mysql -u username -p nextcloud_db

# Verify restoration
mysql -u username -p -e "SELECT COUNT(*) FROM door_estimator_pricing;"
```

---

For additional information, see:
- [Deployment Guide](DEPLOYMENT.md)
- [Configuration Guide](CONFIGURATION.md)
- [Security Guide](SECURITY.md)