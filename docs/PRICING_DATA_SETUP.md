# Pricing Data Setup Guide

This guide explains how to import your proprietary pricing data into the Door Estimator application.

## 🔒 Security Note

The pricing data is **not included** in the public GitHub repository to protect your proprietary business information. You must import it separately using one of the methods below.

## 📊 Data Import Methods

### Method 1: Using Pre-extracted JSON File

If you have the `extracted_pricing_data.json` file:

```bash
# 1. Copy the pricing data file to the app directory
sudo cp extracted_pricing_data.json /var/www/nextcloud/apps/door_estimator/scripts/

# 2. Import the data
sudo -u www-data php /var/www/nextcloud/occ door-estimator:import-pricing

# 3. Verify the import
sudo -u www-data php /var/www/nextcloud/occ db:query "SELECT category, COUNT(*) as count FROM oc_door_estimator_pricing GROUP BY category"
```

### Method 2: Extract from Excel File

If you have the original Excel file (`Estimator 050825.xlsx`):

```bash
# 1. Install Python dependencies
pip3 install pandas openpyxl

# 2. Copy the Excel file to the scripts directory
sudo cp "Estimator 050825.xlsx" /var/www/nextcloud/apps/door_estimator/

# 3. Run the extraction script
cd /var/www/nextcloud/apps/door_estimator/
python3 scripts/extract_excel_python.py

# 4. Import the extracted data
sudo -u www-data php /var/www/nextcloud/occ door-estimator:import-pricing
```

### Method 3: Manual Database Import

If you have the SQL file:

```bash
# 1. Import directly to database
mysql -u nextcloud_user -p nextcloud_db < pricing_data_import.sql

# 2. Verify the import
mysql -u nextcloud_user -p nextcloud_db -e "SELECT COUNT(*) FROM oc_door_estimator_pricing;"
```

## 📋 Expected Data Structure

Your pricing database should contain approximately:

| Category | Expected Items | Description |
|----------|---------------|-------------|
| doors | ~75 | Hollow metal doors |
| inserts | ~24 | Glass inserts |
| frames | ~176 | Door frames (HM Drywall, EWA, USA) |
| hinges | ~14 | Door hinges |
| weatherstrip | ~10 | Weatherstrip products |
| locksets | ~13 | Lock sets |
| exitDevices | ~26 | Exit devices |
| closers | ~10 | Door closers |
| hardware | ~17 | Miscellaneous hardware |

**Total: ~366 pricing items**

## 🔧 Troubleshooting

### No Pricing Data Appears

```bash
# Check if data was imported
sudo -u www-data php /var/www/nextcloud/occ db:query "SELECT COUNT(*) FROM oc_door_estimator_pricing"

# Check for errors in NextCloud log  
tail -f /var/www/nextcloud/data/nextcloud.log | grep door_estimator
```

### Import Command Fails

```bash
# Check file permissions
ls -la /var/www/nextcloud/apps/door_estimator/scripts/extracted_pricing_data.json

# Check file format
head -20 /var/www/nextcloud/apps/door_estimator/scripts/extracted_pricing_data.json
```

### Prices Don't Show in Interface

1. **Verify Import**: Check database contains data
2. **Clear Cache**: Restart NextCloud or clear cache
3. **Check Categories**: Ensure category names match exactly
4. **Browser Console**: Check for JavaScript errors

## 🔄 Updating Pricing Data

To update pricing data:

```bash
# 1. Extract new data from updated Excel file
python3 scripts/extract_excel_python.py

# 2. Re-import (this clears existing data first)
sudo -u www-data php /var/www/nextcloud/occ door-estimator:import-pricing

# 3. Verify new data
sudo -u www-data php /var/www/nextcloud/occ db:query "SELECT category, COUNT(*) FROM oc_door_estimator_pricing GROUP BY category"
```

## 🛡️ Security Best Practices

1. **File Permissions**: Ensure pricing files are readable only by web server
   ```bash
   sudo chmod 640 /var/www/nextcloud/apps/door_estimator/scripts/extracted_pricing_data.json
   sudo chown www-data:www-data /var/www/nextcloud/apps/door_estimator/scripts/extracted_pricing_data.json
   ```

2. **Remove After Import**: Consider removing the JSON file after successful import
   ```bash
   sudo rm /var/www/nextcloud/apps/door_estimator/scripts/extracted_pricing_data.json
   ```

3. **Database Backups**: Regularly backup your pricing data
   ```bash
   mysqldump nextcloud_db oc_door_estimator_pricing > pricing_backup_$(date +%Y%m%d).sql
   ```

## 📞 Support

If you encounter issues with pricing data import:

1. Check NextCloud logs for error messages
2. Verify file formats and permissions  
3. Ensure database connectivity
4. Test with a small subset of data first

The application will work without pricing data, but items will show $0.00 prices until data is imported.