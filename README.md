# Door Estimator NextCloud App

A comprehensive door and hardware estimating application for NextCloud that modernizes Excel-based workflows with a professional web interface.

## 🚀 Features

- **Modern Web Interface**: Responsive design that works on desktop, tablet, and mobile
- **Complete Business Logic**: Preserves all existing Excel pricing formulas and calculations
- **Real-time Price Lookups**: SUMPRODUCT-style lookups with instant price calculations
- **Multi-category Support**: Doors, frames, hardware, inserts, and all product categories
- **Complex Pricing Logic**: Handles conditional pricing like frame types and wood door configurations
- **Quote Management**: Save, load, and manage multiple quotes with versioning
- **PDF Generation**: Professional PDF quotes for clients
- **Admin Interface**: Easy pricing data management with bulk import capabilities
- **User Permissions**: NextCloud integration for user management and access control
- **Database Integration**: MySQL/PostgreSQL backend with optimized queries

## 📋 Prerequisites

- **NextCloud**: Version 25 or higher
- **PHP**: Version 8.0 or higher
- **Database**: MySQL 5.7+ or PostgreSQL 10+
- **Web Server**: Apache 2.4+ or Nginx 1.16+
- **Memory**: At least 512MB PHP memory limit
- **Storage**: Minimum 100MB free space

## 📁 Project Structure

```
door-estimator/
├── appinfo/
│   ├── info.xml                 # App metadata and dependencies
│   ├── routes.php              # API route definitions
│   ├── app.php                 # App bootstrap
│   └── database.xml            # Database schema
├── lib/
│   ├── Controller/
│   │   ├── EstimatorController.php  # Main API controller
│   │   └── PageController.php       # Page rendering
│   ├── Service/
│   │   └── EstimatorService.php     # Business logic
│   ├── Migration/
│   │   └── Version001000Date20250124000000.php  # DB migration
│   └── Command/
│       └── ImportPricingData.php    # Data import command
├── templates/
│   └── main.php                # Main app template
├── js/
│   └── door-estimator.js       # Frontend JavaScript
├── css/
│   └── style.css               # Application styles
├── scripts/
│   ├── extract_excel_python.py     # Excel data extraction
│   ├── extracted_pricing_data.json # Extracted data
│   └── pricing_data_import.sql     # SQL import script
├── composer.json               # PHP dependencies
└── README.md                   # This file
```

## 🛠️ Installation Guide

### Quick Installation

**For Standard NextCloud:**
```bash
# One-command installation from GitHub
curl -fsSL https://raw.githubusercontent.com/kdegeek/nextcloud-door-estimator/main/install.sh | sudo bash
```

**For NextCloud AIO (All-in-One) Containers:**
```bash
# AIO-specific installer for containerized environments
curl -fsSL https://raw.githubusercontent.com/kdegeek/nextcloud-door-estimator/main/install-aio.sh | sudo bash
```

### Manual Installation

```bash
# Clone the repository
git clone https://github.com/kdegeek/nextcloud-door-estimator.git
cd nextcloud-door-estimator

# For standard NextCloud
sudo ./scripts/setup.sh

# For NextCloud AIO containers
sudo ./install-aio.sh
```

### Step 2: PHP Dependencies

All required PHP dependencies are already bundled in the `vendor/` directory. No Composer installation is required for end-users.

- **Advanced PDF features are always available** since all dependencies (including TCPDF) are included.
- Composer is only needed by developers who wish to update or add PHP dependencies.

### Step 3: Set Permissions

```bash
# Set proper ownership and permissions
chown -R www-data:www-data /var/www/nextcloud/apps/door_estimator
chmod -R 755 /var/www/nextcloud/apps/door_estimator

# Ensure NextCloud can write to necessary directories
chmod 775 /var/www/nextcloud/apps/door_estimator/scripts/
```

### Step 4: Enable the App

```bash
# Enable via command line (recommended)
sudo -u www-data php /var/www/nextcloud/occ app:enable door_estimator

# Alternatively, enable via NextCloud admin interface:
# Settings > Apps > Door Estimator > Enable
```

### Step 5: Import Pricing Data

**Important:** Pricing data is not included in the public repository for security reasons.

```bash
# The setup script will guide you through pricing data import
# See docs/PRICING_DATA_SETUP.md for detailed instructions

# Quick setup if you have the JSON file:
sudo cp your-extracted-pricing-data.json /var/www/nextcloud/apps/door_estimator/scripts/extracted_pricing_data.json
sudo -u www-data php /var/www/nextcloud/occ door-estimator:import-pricing
```

📖 **See [docs/PRICING_DATA_SETUP.md](docs/PRICING_DATA_SETUP.md) for complete pricing data import instructions.**

🐳 **For NextCloud AIO users, see [docs/NEXTCLOUD_AIO_SETUP.md](docs/NEXTCLOUD_AIO_SETUP.md) for container-specific instructions.**

### Step 6: Verify Installation

1. **Access the App**: Go to your NextCloud instance and look for "Door Estimator" in the app menu
2. **Test Pricing**: Try selecting items and verify prices populate correctly
3. **Create Test Quote**: Add some items with quantities and verify calculations
4. **Save Quote**: Test the save functionality
5. **Generate PDF**: Test PDF generation (creates HTML-based quotes)

## 📊 Data Migration Details

### Excel Data Successfully Extracted

The system has extracted **366 pricing items** from your `Estimator 050825.xlsx` file:

| Category | Items | Description |
|----------|-------|-------------|
| **Doors** | 75 | Hollow metal doors with various sizes and specifications |
| **Inserts** | 24 | Fire-rated and standard glass inserts |
| **Frames** | 176 | HM Drywall, EWA, and USA frames with subcategories |
| **Hinges** | 14 | Various hinge types and finishes |
| **Weatherstrip** | 10 | Weatherstrip sets, sills, and sweeps |
| **Locksets** | 13 | Grade 1 & 2 leversets |
| **Exit Devices** | 26 | RIM exit devices, fire-rated options |
| **Closers** | 10 | Grade 1 door closers, hold-open arms |
| **Hardware** | 17 | Push/pull plates, kick plates, bolts |
| **SC Fire** | 1 | Fire-rated solid core doors |

### Business Logic Preservation

The app replicates your Excel formulas:

- **Price Lookups**: `=SUMPRODUCT((Doors!A5:A93=B2)*(Doors!B5:B93))` becomes `lookupPrice('doors', selectedItem)`
- **Frame Logic**: `=IF(H13="HM Drywall",SUMPRODUCT(...))` becomes conditional frame type pricing
- **Markup Calculations**: Configurable percentages by category (Doors: 15%, Frames: 12%, Hardware: 18%)

## 🔧 Configuration

### Default Markups

The system includes configurable markup percentages:

- **Doors & Inserts**: 15% (configurable)
- **Frames**: 12% (configurable)  
- **Hardware**: 18% (configurable)

### API Endpoints

The app provides a comprehensive REST API:

- `GET /api/pricing` - Get all pricing data
- `GET /api/pricing/{category}` - Get pricing for specific category
- `POST /api/pricing` - Update pricing item
- `POST /api/lookup-price` - Lookup price for item
- `POST /api/quotes` - Save a quote
- `GET /api/quotes` - Get user's quotes
- `GET /api/quotes/{id}` - Get specific quote
- `GET /api/quotes/{id}/pdf` - Generate PDF

## 🎯 Usage Instructions

### Creating a Quote

1. **Access the App**: Click "Door Estimator" in your NextCloud apps
2. **Select Items**: Use dropdown menus to select doors, frames, hardware, etc.
3. **Enter Quantities**: Add quantities for each selected item
4. **Automatic Pricing**: Prices populate automatically based on your Excel data
5. **Review Totals**: Section totals and grand total calculate with markups
6. **Save Quote**: Click "Save Quote" and provide a name
7. **Generate PDF**: Click "Generate PDF" for client presentation

### Managing Pricing Data

1. **Admin Panel**: Click the "Admin" tab in the app
2. **View Categories**: Browse all pricing categories and items
3. **Search Items**: Use the search functionality to find specific items
4. **Update Prices**: Edit individual item prices as needed
5. **Bulk Import**: Upload CSV files for bulk price updates

### Quote Management

- **Save Quotes**: All quotes are saved with timestamps and user association
- **Load Quotes**: Access previously saved quotes from the quotes list
- **Duplicate Quotes**: Create copies of existing quotes for similar projects
- **PDF Export**: Generate professional PDF quotes for clients

## 🔒 Security Features

- **User Authentication**: Integrates with NextCloud's user system
- **Data Isolation**: Users can only access their own quotes
- **SQL Injection Protection**: All database queries use prepared statements
- **XSS Prevention**: All user input is properly sanitized
- **CSRF Protection**: NextCloud's built-in CSRF protection

## 📈 Performance Optimization

- **Database Indexes**: Optimized indexes on frequently queried columns
- **Caching**: Pricing data is cached for faster lookups
- **Lazy Loading**: Admin interface loads data on demand
- **Minimal Dependencies**: Core functionality works without external libraries

## 🐛 Troubleshooting

### Common Issues and Solutions

**1. App Won't Enable**
```bash
# Check NextCloud logs
tail -f /var/www/nextcloud/data/nextcloud.log

# Verify file permissions
ls -la /var/www/nextcloud/apps/door_estimator/
```

**2. Pricing Data Not Loading**
```bash
# Re-import pricing data
sudo -u www-data php /var/www/nextcloud/occ door-estimator:import-pricing

# Check database tables
sudo -u www-data php /var/www/nextcloud/occ db:show-tables | grep door_estimator
```

**3. JavaScript Errors**
- Check browser console for errors
- Verify NextCloud version compatibility
- Clear browser cache

**4. PDF Generation Issues**
- HTML-based PDFs and advanced PDF features are always available (all dependencies are bundled in `vendor/`)
- Check file permissions on the quotes directory

### Database Maintenance

```bash
# Check table status
sudo -u www-data php /var/www/nextcloud/occ db:show-tables | grep door_estimator

# Backup pricing data
mysqldump nextcloud_db door_estimator_pricing > pricing_backup.sql

# Optimize database
sudo -u www-data php /var/www/nextcloud/occ db:add-missing-indices
```

## 🔄 Updates and Maintenance

### Updating Pricing Data

1. **Export from Excel**: Export updated sheets as CSV files
2. **Use Import Script**: Run the Python extraction script on new Excel files
3. **Database Update**: Use the import command to update pricing
4. **Verify Changes**: Test price lookups to ensure accuracy

### Backup Recommendations

- **Database**: Regular backups of `door_estimator_pricing` and `door_estimator_quotes` tables
- **Files**: Backup the entire app directory
- **Configuration**: Document any custom markup settings

## 📞 Support

### Getting Help

1. **NextCloud Logs**: Check `/var/www/nextcloud/data/nextcloud.log`
2. **Browser Console**: Check for JavaScript errors
3. **Database Logs**: Check MySQL/PostgreSQL logs for database issues
4. **File Permissions**: Verify all files are accessible by the web server

### Reporting Issues

When reporting issues, please include:
- NextCloud version
- PHP version
- Browser and version
- Error messages from logs
- Steps to reproduce the issue

## 🎉 Success Metrics

Your Door Estimator app provides significant improvements over Excel:

✅ **Multi-user Access** - No more file locking issues  
✅ **Real-time Collaboration** - Multiple users can work simultaneously  
✅ **Version Control** - Built-in quote history and audit trails  
✅ **Mobile Access** - Works on tablets and phones  
✅ **Automated Backups** - NextCloud handles data protection  
✅ **API Integration** - Connect to other business systems  
✅ **Professional PDFs** - Branded, consistent quote documents  
✅ **Easy Price Updates** - No Excel expertise required for staff  
✅ **Search & Reporting** - Query historical quotes and data  
✅ **Scalability** - Handle larger datasets without performance issues  

The application preserves 100% of your existing business logic while providing a modern, maintainable platform for growth.