# NextCloud AIO Setup Guide

Complete installation guide for the Door Estimator app on NextCloud All-in-One (AIO) containers.

## 🐳 About NextCloud AIO

NextCloud All-in-One (AIO) is a popular containerized deployment method that packages NextCloud with all dependencies in Docker containers. This setup guide is specifically designed for AIO environments.

## 🚀 Quick Installation

### One-Command Installation

```bash
# Download and run the AIO installer
curl -fsSL https://raw.githubusercontent.com/kdegeek/nextcloud-door-estimator/main/install-aio.sh | sudo bash
```

### Manual Installation

```bash
# Download the AIO installer
wget https://raw.githubusercontent.com/kdegeek/nextcloud-door-estimator/main/install-aio.sh
chmod +x install-aio.sh

# Run the installer
sudo ./install-aio.sh
```

## 🔍 Prerequisites

### System Requirements

- **Docker**: Installed and running
- **NextCloud AIO**: Container running and accessible
- **Node.js v16+**: Required in the container to build the Vue 3 frontend
- **User Permissions**: Root access or user in docker group
- **Internet Access**: For downloading from GitHub

> **Node.js v16+ is required to build the Vue 3 frontend inside the container.**
> If Node.js is not present in your container, install it with:
> ```bash
> curl -fsSL https://deb.nodesource.com/setup_16.x | bash - && apt-get install -y nodejs
> ```
> Verify installation with `node -v` (should be 16.x or higher).

### Verify Your AIO Setup

```bash
# Check if AIO containers are running
docker ps | grep nextcloud

# Should show containers like:
# nextcloud-aio-nextcloud
# nextcloud-aio-database  
# nextcloud-aio-redis
# etc.
```

## 📋 Installation Process

The AIO installer automatically:

1. **🔍 Detects AIO Container**: Finds your NextCloud AIO container
2. **📦 Installs Dependencies**: Adds git to container if needed (all PHP dependencies are already bundled in `vendor/`)
3. **📥 Downloads App**: Clones from GitHub directly into container
4. **🔒 Sets Permissions**: Configures proper file permissions
5. **🔌 Enables App**: Activates the Door Estimator in NextCloud
6. **📊 Imports Data**: Guides through pricing data setup

> **Note:** All required PHP dependencies are included. Composer is only needed by developers who wish to update dependencies. Advanced PDF features are always available.

## 🛠️ Container-Specific Features

### Automatic Container Detection

The installer tries common AIO container names:
- `nextcloud-aio-nextcloud`
- `nextcloud_aio_nextcloud` 
- `nextcloud-aio-nextcloud-1`
- `aio-nextcloud`

### Manual Container Specification

If auto-detection fails:

```bash
# Specify your container name
sudo ./install-aio.sh --container your-container-name

# Check available containers
docker ps --format "table {{.Names}}\t{{.Status}}"
```

### Environment Check

```bash
# Test your AIO environment before installation
sudo ./install-aio.sh --check
```

## 📊 Pricing Data Import for AIO

### Method 1: Copy File to Container

```bash
# Copy your pricing data file to the container
docker cp extracted_pricing_data.json nextcloud-aio-nextcloud:/var/www/html/apps/door_estimator/scripts/

# Set proper permissions
docker exec nextcloud-aio-nextcloud chown www-data:www-data /var/www/html/apps/door_estimator/scripts/extracted_pricing_data.json

# Import the data
docker exec -u www-data nextcloud-aio-nextcloud php /var/www/html/occ door-estimator:import-pricing
```

### Method 2: Extract from Excel in Container

```bash
# Copy Excel file to container
docker cp "Estimator 050825.xlsx" nextcloud-aio-nextcloud:/var/www/html/apps/door_estimator/

# Install Python dependencies in container
docker exec nextcloud-aio-nextcloud apt-get update
docker exec nextcloud-aio-nextcloud apt-get install -y python3 python3-pip
docker exec nextcloud-aio-nextcloud pip3 install pandas openpyxl

# Run extraction script in container
docker exec nextcloud-aio-nextcloud bash -c "cd /var/www/html/apps/door_estimator && python3 scripts/extract_excel_python.py"

# Import extracted data
docker exec -u www-data nextcloud-aio-nextcloud php /var/www/html/occ door-estimator:import-pricing
```

### Method 3: Volume Mount (Advanced)

```bash
# If you have persistent volumes, you can mount data directly
# Check your AIO compose file for volume mappings
docker exec nextcloud-aio-nextcloud ls -la /var/www/html/apps/door_estimator/
```

## 🔧 Manual Frontend Build in AIO Containers

If you need to manually build the frontend inside a NextCloud AIO container (for example, after updating source code), run:

```bash
docker exec nextcloud-aio-nextcloud bash -c 'cd /var/www/html/apps/door_estimator && sh scripts/build.sh'
```

Ensure Node.js v16+ is installed in the container before running the build script.

## 🔧 AIO-Specific Commands

### Common Container Operations

```bash
# Access the NextCloud container
docker exec -it nextcloud-aio-nextcloud bash

# Run NextCloud occ commands
docker exec -u www-data nextcloud-aio-nextcloud php /var/www/html/occ app:list

# Check app status
docker exec -u www-data nextcloud-aio-nextcloud php /var/www/html/occ app:list | grep door_estimator

# View NextCloud logs
docker exec nextcloud-aio-nextcloud tail -f /var/www/html/data/nextcloud.log

# Check database
docker exec -u www-data nextcloud-aio-nextcloud php /var/www/html/occ db:show-tables | grep door_estimator
```

### App Management

```bash
# Disable the app
docker exec -u www-data nextcloud-aio-nextcloud php /var/www/html/occ app:disable door_estimator

# Enable the app
docker exec -u www-data nextcloud-aio-nextcloud php /var/www/html/occ app:enable door_estimator

# Check app files
docker exec nextcloud-aio-nextcloud ls -la /var/www/html/apps/door_estimator/
```

## 🚨 Troubleshooting AIO Issues

### Container Not Found

```bash
# List all containers
docker ps -a

# Look for NextCloud containers
docker ps | grep -i nextcloud

# Check if AIO is running
docker-compose ps  # if using compose
# or
docker ps | grep aio
```

### Permission Issues

```bash
# Fix file ownership in container
docker exec nextcloud-aio-nextcloud chown -R www-data:www-data /var/www/html/apps/door_estimator

# Fix file permissions
docker exec nextcloud-aio-nextcloud find /var/www/html/apps/door_estimator -type f -exec chmod 644 {} \;
docker exec nextcloud-aio-nextcloud find /var/www/html/apps/door_estimator -type d -exec chmod 755 {} \;
```

### App Won't Enable

```bash
# Check NextCloud logs for errors
docker exec nextcloud-aio-nextcloud tail -20 /var/www/html/data/nextcloud.log

# Verify app files are present
docker exec nextcloud-aio-nextcloud ls -la /var/www/html/apps/door_estimator/appinfo/

# Check PHP syntax
docker exec nextcloud-aio-nextcloud php -l /var/www/html/apps/door_estimator/appinfo/info.xml
```

### Database Issues

```bash
# Check database connection
docker exec -u www-data nextcloud-aio-nextcloud php /var/www/html/occ db:show-tables

# Run database migrations manually
docker exec -u www-data nextcloud-aio-nextcloud php /var/www/html/occ migrations:execute door_estimator 001000

# Check migration status
docker exec -u www-data nextcloud-aio-nextcloud php /var/www/html/occ migrations:status door_estimator
```

## 🔄 Updating the App in AIO

### Update from GitHub

```bash
# Backup current installation
docker exec nextcloud-aio-nextcloud cp -r /var/www/html/apps/door_estimator /var/www/html/apps/door_estimator_backup

# Remove old version
docker exec nextcloud-aio-nextcloud rm -rf /var/www/html/apps/door_estimator

# Install new version
sudo ./install-aio.sh
```

### Manual Update

```bash
# Enter the container
docker exec -it nextcloud-aio-nextcloud bash

# Navigate to apps directory
cd /var/www/html/apps/

# Backup current installation
cp -r door_estimator door_estimator_backup

# Remove old version
rm -rf door_estimator

# Clone latest version
git clone https://github.com/kdegeek/nextcloud-door-estimator.git door_estimator

# Set permissions
chown -R www-data:www-data door_estimator

# Exit container and enable app
exit
docker exec -u www-data nextcloud-aio-nextcloud php /var/www/html/occ app:enable door_estimator
```

## 🔒 Security Considerations for AIO

### Container Isolation

- App runs within the NextCloud container
- Database access controlled by NextCloud
- File permissions managed by container user (www-data)

### Data Protection

```bash
# Backup your pricing data outside the container
docker cp nextcloud-aio-nextcloud:/var/www/html/apps/door_estimator/scripts/extracted_pricing_data.json ./backup_pricing_data.json

# Regular database backups
docker exec -u www-data nextcloud-aio-nextcloud php /var/www/html/occ db:export-schema > schema_backup.sql
```

### Network Security

- AIO containers typically run on isolated Docker networks
- Only expose necessary ports
- Use reverse proxy for HTTPS termination

## 📊 Performance Optimization for AIO

### Container Resources

```bash
# Check container resource usage
docker stats nextcloud-aio-nextcloud

# Monitor memory usage
docker exec nextcloud-aio-nextcloud free -h

# Check disk usage
docker exec nextcloud-aio-nextcloud df -h
```

### Database Optimization

```bash
# Add missing database indices
docker exec -u www-data nextcloud-aio-nextcloud php /var/www/html/occ db:add-missing-indices

# Optimize database
docker exec -u www-data nextcloud-aio-nextcloud php /var/www/html/occ db:convert-filecache-bigint
```

## 🤝 AIO Community Support

For AIO-specific issues:

1. **NextCloud AIO Documentation**: https://github.com/nextcloud/all-in-one
2. **NextCloud Community**: https://help.nextcloud.com/
3. **Docker Documentation**: https://docs.docker.com/

For Door Estimator app issues:

1. **GitHub Issues**: https://github.com/kdegeek/nextcloud-door-estimator/issues
2. **Documentation**: Check the main README.md

## ✅ AIO Installation Checklist

After installation, verify:

- [ ] AIO containers are running (`docker ps`)
- [ ] Door Estimator appears in NextCloud apps menu
- [ ] App loads without JavaScript errors
- [ ] Pricing data is imported (if applicable)
- [ ] Can create and save quotes
- [ ] PDF generation works
- [ ] Admin interface accessible

The AIO installation provides the same functionality as standard NextCloud but with the convenience and security of containerized deployment!

---

## 📄 Documentation Update Summary

- Node.js v16+ requirement is now clearly stated for AIO container builds.
- One-liner install command for Node.js v16+ in containers is provided.
- Manual frontend build command for AIO containers is documented.
- All relevant sections updated for clarity and reproducibility.