# NextCloud Door Estimator App

A modern door and hardware estimating application for NextCloud that replaces your Excel-based workflow with a web-based solution.

## Features

- **Modern Web Interface**: Clean, responsive interface that works on desktop and mobile
- **Complete Business Logic**: Preserves all your existing pricing formulas and calculations
- **Real-time Price Lookups**: SUMPRODUCT-style lookups with instant price calculations
- **Multi-category Support**: Doors, frames, hardware, inserts, and all other categories
- **Complex Pricing Logic**: Handles conditional pricing like frame types and wood door configurations
- **Quote Management**: Save, load, and manage multiple quotes
- **PDF Generation**: Professional PDF quotes for clients
- **Admin Interface**: Easy pricing data management with frequent update capability
- **User Permissions**: NextCloud integration for user management and access control

## Installation

### Prerequisites

- NextCloud 25+ 
- PHP 8.0+
- MySQL/PostgreSQL database
- Composer (for dependencies)

### App Structure

```
apps/door_estimator/
├── appinfo/
│   ├── info.xml
│   ├── routes.php
│   └── app.php
├── lib/
│   ├── Controller/
│   │   ├── EstimatorController.php
│   │   └── PageController.php
│   ├── Service/
│   │   └── EstimatorService.php
│   ├── Migration/
│   │   └── Version001000Date20250124000000.php
│   └── Command/
│       └── ImportPricingData.php
├── templates/
│   └── main.php
├── js/
│   ├── door-estimator.js
├── css/
│   └── style.css
├── composer.json
└── README.md
```

### Configuration Files

#### appinfo/info.xml
```xml
<?xml version="1.0"?>
<info xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
      xsi:noNamespaceSchemaLocation="https://apps.nextcloud.com/schema/apps/info.xsd">
    <id>door_estimator</id>
    <name>Door Estimator</name>
    <summary>Professional door and hardware estimating system</summary>
    <description><![CDATA[
    A comprehensive door and hardware estimating application that replaces Excel-based workflows.
    Features include real-time pricing lookups, quote management, PDF generation, and administrative 
    tools for pricing data management.
    ]]></description>
    <version>1.0.0</version>
    <licence>agpl</licence>
    <author>Your Company</author>
    <namespace>DoorEstimator</namespace>
    <category>office</category>
    <bugs>https://github.com/yourcompany/door-estimator/issues</bugs>
    <dependencies>
        <nextcloud min-version="25" max-version="29"/>
        <php min-version="8.0"/>
        <database>mysql</database>
        <database>pgsql</database>
    </dependencies>
    <navigations>
        <navigation>
            <name>Door Estimator</name>
            <route>door_estimator.page.index</route>
            <icon>app.svg</icon>
        </navigation>
    </navigations>
    <settings>
        <admin>OCA\DoorEstimator\Settings\AdminSettings</admin>
    </settings>
</info>
```

#### appinfo/routes.php
```php
<?php
return [
    'routes' => [
        // Main app page
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
        
        // API endpoints
        ['name' => 'estimator#getAllPricingData', 'url' => '/api/pricing', 'verb' => 'GET'],
        ['name' => 'estimator#getPricingByCategory', 'url' => '/api/pricing/{category}', 'verb' => 'GET'],
        ['name' => 'estimator#updatePricingItem', 'url' => '/api/pricing', 'verb' => 'POST'],
        ['name' => 'estimator#lookupPrice', 'url' => '/api/lookup-price', 'verb' => 'POST'],
        
        // Quote management
        ['name' => 'estimator#saveQuote', 'url' => '/api/quotes', 'verb' => 'POST'],
        ['name' => 'estimator#getQuote', 'url' => '/api/quotes/{quoteId}', 'verb' => 'GET'],
        ['name' => 'estimator#getUserQuotes', 'url' => '/api/quotes', 'verb' => 'GET'],
        ['name' => 'estimator#generateQuotePDF', 'url' => '/api/quotes/{quoteId}/pdf', 'verb' => 'GET'],
    ]
];
```

#### composer.json
```json
{
    "name": "yourcompany/door-estimator",
    "description": "NextCloud Door Estimator App",
    "type": "nextcloud-app",
    "license": "AGPL-3.0-or-later",
    "require": {
        "php": "^8.0",
        "tecnickcom/tcpdf": "^6.4"
    },
    "autoload": {
        "psr-4": {
            "OCA\\DoorEstimator\\": "lib/"
        }
    }
}
```

## Deployment Instructions

### 1. Install the App

```bash
# Clone or copy app to NextCloud apps directory
cd /var/www/nextcloud/apps/
git clone https://github.com/yourcompany/door-estimator.git

# Install PHP dependencies
cd door-estimator
composer install --no-dev --optimize-autoloader

# Set proper permissions
chown -R www-data:www-data .
chmod -R 755 .
```

### 2. Enable the App

```bash
# Enable via command line
sudo -u www-data php /var/www/nextcloud/occ app:enable door_estimator

# Or enable via NextCloud admin interface:
# Settings > Apps > Door Estimator > Enable
```

### 3. Import Your Excel Data

Create a CSV export of each sheet from your Excel file, then use the import command:

```bash
# Import pricing data
sudo -u www-data php /var/www/nextcloud/occ door-estimator:import-pricing

# Or use the admin interface to bulk import CSV files
```

### 4. Configure Permissions

Set up user groups and permissions:

```bash
# Create estimator group
sudo -u www-data php /var/www/nextcloud/occ group:add estimators

# Add users to group
sudo -u www-data php /var/www/nextcloud/occ group:adduser estimators username
```

## Data Migration from Excel

### Step 1: Export Excel Data

1. Open your `Estimator 050825.xlsx` file
2. For each sheet, export as CSV:
   - Doors → doors.csv
   - Frames → frames.csv
   - Inserts → inserts.csv
   - etc.

### Step 2: Import Script

Use the provided Python extraction script or admin interface:

```bash
# Extract data from Excel (if needed)
python3 scripts/extract_excel_python.py

# Import extracted data
sudo -u www-data php /var/www/nextcloud/occ door-estimator:import-pricing
```

### Step 3: Verify Data

1. Access the admin panel in the app
2. Review all imported pricing data
3. Test price lookups in the estimator interface
4. Verify calculations match your Excel formulas

## Business Logic Implementation

### Price Lookups
The app replicates your Excel SUMPRODUCT formulas:

```javascript
// Original Excel: =SUMPRODUCT((Doors!A5:A93=B2)*(Doors!B5:B93))
// Becomes: lookupPrice('doors', selectedItem)

// Original Excel: =IF(H13="HM Drywall",SUMPRODUCT((Frames!A3:A52=B12)*(Frames!B3:B52))...)
// Becomes: lookupPrice('frames', selectedItem, frameType)
```

### Markup Calculations
Percentage markups are applied by category:
- Doors & Inserts: Configurable % (default 15%)
- Frames: Configurable % (default 12%)  
- Hardware: Configurable % (default 18%)

### Complex Pricing Logic
Wood door configurations (SCwood/SCfire sheets) are handled with conditional logic that matches your Excel IF statements.

## Customization

### Adding New Categories

1. Add database entries:
```sql
INSERT INTO door_estimator_pricing (category, item_name, price, created_at, updated_at) 
VALUES ('new_category', 'Item Name', 99.99, NOW(), NOW());
```

2. Update frontend interface:
```javascript
// Add to quoteData structure in React component
newCategory: [
  { id: 'Z', item: '', qty: 0, price: 0, total: 0 }
]
```

### Modifying Formulas

Update the `EstimatorService::lookupPrice()` method to handle custom pricing logic.

### PDF Customization

Modify `EstimatorService::generateQuoteHTML()` to customize PDF layout and branding.

## Maintenance

### Updating Prices

Use the admin interface or API to update pricing:

```bash
# Bulk price update
curl -X POST http://nextcloud.local/apps/door_estimator/api/pricing \
  -H "Content-Type: application/json" \
  -d '{"category":"doors","item":"2-0 x 6-8 Flush HM","price":525.00}'
```

### Database Maintenance

```bash
# Backup pricing data
sudo -u www-data php /var/www/nextcloud/occ door-estimator:export-pricing > pricing-backup.sql

# Optimize database
sudo -u www-data php /var/www/nextcloud/occ db:add-missing-indices
```

### Performance Optimization

1. Enable NextCloud caching (Redis/APCu)
2. Optimize database queries with proper indices
3. Use CDN for static assets if needed

## Support & Development

### Adding Features

The modular architecture makes it easy to add:
- Additional pricing categories
- Custom calculation formulas  
- New export formats
- Integration with external systems

### API Documentation

All endpoints are documented and can be tested:
- `GET /api/pricing` - Get all pricing data
- `POST /api/quotes` - Save a quote
- `GET /api/quotes/{id}/pdf` - Generate PDF

### Troubleshooting

Common issues and solutions:

1. **Pricing lookups return 0**: Check category names match exactly
2. **PDF generation fails**: Verify TCPDF installation and file permissions
3. **Import errors**: Validate CSV format and encoding (UTF-8)

## Migration Benefits

Moving from Excel to this NextCloud app provides:

✅ **Multi-user access** - No more file locking issues  
✅ **Real-time collaboration** - Multiple users can work simultaneously  
✅ **Version control** - Built-in quote history and audit trails  
✅ **Mobile access** - Works on tablets and phones  
✅ **Automated backups** - NextCloud handles data protection  
✅ **API integration** - Connect to other business systems  
✅ **Professional PDFs** - Branded, consistent quote documents  
✅ **Easy price updates** - No Excel expertise required for staff  
✅ **Search & reporting** - Query historical quotes and data  
✅ **Scalability** - Handle larger datasets without performance issues  

The app preserves 100% of your existing business logic while providing a modern, maintainable platform for growth.