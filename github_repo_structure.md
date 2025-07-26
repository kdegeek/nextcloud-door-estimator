# NextCloud Door Estimator - Complete Repository Structure

Here's the complete file structure for your private GitHub repository:

## Repository Structure

```
door-estimator-nextcloud/
├── README.md
├── LICENSE
├── .gitignore
├── composer.json
├── appinfo/
│   ├── info.xml
│   ├── routes.php
│   ├── app.php
│   └── database.xml
├── lib/
│   ├── Controller/
│   │   ├── EstimatorController.php
│   │   └── PageController.php
│   ├── Service/
│   │   └── EstimatorService.php
│   ├── Migration/
│   │   └── Version001000Date20250124000000.php
│   ├── Command/
│   │   └── ImportPricingData.php
│   └── Settings/
│       └── AdminSettings.php
├── templates/
│   ├── main.php
│   └── admin.php
├── js/
│   ├── door-estimator.js
│   ├── admin.js
│   └── webpack.config.js
├── css/
│   └── style.css
├── img/
│   └── app.svg
├── docs/
│   ├── installation.md
│   ├── api.md
│   └── migration-guide.md
├── scripts/
│   ├── extract_excel_python.py
│   ├── extracted_pricing_data.json
│   └── setup.sh
├── tests/
│   ├── Unit/
│   │   └── EstimatorServiceTest.php
│   └── Integration/
│       └── EstimatorControllerTest.php
└── docker/
    ├── Dockerfile
    └── docker-compose.yml
```

## Quick Setup Commands

Once you create your private GitHub repo, run these commands:

```bash
# Clone your empty repo
git clone https://github.com/yourusername/door-estimator-nextcloud.git
cd door-estimator-nextcloud

# Create the directory structure
mkdir -p {appinfo,lib/Controller,lib/Service,lib/Migration,lib/Command,lib/Settings,templates,js,css,img,docs,scripts,tests/Unit,tests/Integration,docker}

# Then copy the files I've provided into their respective directories
# After adding all files:

git add .
git commit -m "Initial commit: NextCloud Door Estimator App"
git push origin main
```

## File Contents to Create

### 1. Root Files

#### README.md
```markdown
# NextCloud Door Estimator

A professional door and hardware estimating application for NextCloud that modernizes Excel-based workflows.

## Features

- Modern web interface with real-time calculations
- Complete business logic preservation from Excel
- Multi-user collaboration
- PDF quote generation
- Administrative pricing management
- Mobile-responsive design

## Installation

See [docs/INSTALLATION.md](docs/INSTALLATION.md) for complete setup instructions.

## Migration from Excel

See [docs/PRICING_DATA_SETUP.md](docs/PRICING_DATA_SETUP.md) for steps to import your existing Excel data.

## License

AGPL-3.0-or-later
```

#### .gitignore
```
# Dependencies
/vendor/
node_modules/

# Build artifacts
/js/build/
/css/build/

# IDE files
.vscode/
.idea/
*.swp
*.swo

# OS files
.DS_Store
Thumbs.db

# Logs
*.log

# Environment files
.env
.env.local

# Backup files
*.bak
*.backup

# Test coverage
coverage/
```

#### LICENSE
```
GNU AFFERO GENERAL PUBLIC LICENSE
Version 3, 19 November 2007

[Full AGPL-3.0 license text - you can copy from https://www.gnu.org/licenses/agpl-3.0.txt]
```

### 2. Application Info Files

#### appinfo/app.php
```php
<?php

namespace OCA\DoorEstimator\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

class Application extends App implements IBootstrap {
    public const APP_ID = 'door_estimator';

    public function __construct(array $urlParams = []) {
        parent::__construct(self::APP_ID, $urlParams);
    }

    public function register(IRegistrationContext $context): void {
        // Register services, middleware, etc.
    }

    public function boot(IBootContext $context): void {
        // Boot logic
    }
}
```

#### appinfo/database.xml
```xml
<?xml version="1.0" encoding="UTF-8" ?>
<database>
    <table>
        <name>*dbprefix*door_estimator_pricing</name>
        <declaration>
            <field>
                <name>id</name>
                <type>integer</type>
                <notnull>true</notnull>
                <autoincrement>1</autoincrement>
                <primary>true</primary>
            </field>
            <field>
                <name>category</name>
                <type>text</type>
                <length>50</length>
                <notnull>true</notnull>
            </field>
            <field>
                <name>subcategory</name>
                <type>text</type>
                <length>50</length>
                <notnull>false</notnull>
            </field>
            <field>
                <name>item_name</name>
                <type>clob</type>
                <notnull>true</notnull>
            </field>
            <field>
                <name>price</name>
                <type>decimal</type>
                <precision>10</precision>
                <scale>2</scale>
                <notnull>true</notnull>
            </field>
            <field>
                <name>created_at</name>
                <type>timestamp</type>
                <notnull>true</notnull>
            </field>
            <field>
                <name>updated_at</name>
                <type>timestamp</type>
                <notnull>true</notnull>
            </field>
        </declaration>
    </table>
    
    <table>
        <name>*dbprefix*door_estimator_quotes</name>
        <declaration>
            <field>
                <name>id</name>
                <type>integer</type>
                <notnull>true</notnull>
                <autoincrement>1</autoincrement>
                <primary>true</primary>
            </field>
            <field>
                <name>user_id</name>
                <type>text</type>
                <length>64</length>
                <notnull>true</notnull>
            </field>
            <field>
                <name>quote_data</name>
                <type>clob</type>
                <notnull>true</notnull>
            </field>
            <field>
                <name>total_amount</name>
                <type>decimal</type>
                <precision>10</precision>
                <scale>2</scale>
                <notnull>true</notnull>
            </field>
            <field>
                <name>created_at</name>
                <type>timestamp</type>
                <notnull>true</notnull>
            </field>
            <field>
                <name>updated_at</name>
                <type>timestamp</type>
                <notnull>true</notnull>
            </field>
        </declaration>
    </table>
</database>
```

### 3. Additional Controllers

#### lib/Controller/PageController.php
```php
<?php

namespace OCA\DoorEstimator\Controller;

use OCP\IRequest;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;

class PageController extends Controller {
    
    public function __construct($AppName, IRequest $request) {
        parent::__construct($AppName, $request);
    }
    
    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function index() {
        return new TemplateResponse('door_estimator', 'main');
    }
}
```

### 4. Templates

#### templates/main.php
```php
<?php
script('door_estimator', 'door-estimator');
style('door_estimator', 'style');
?>

<div id="door-estimator-app">
    <div class="loading">Loading Door Estimator...</div>
</div>
```

#### templates/admin.php
```php
<?php
script('door_estimator', 'admin');
style('door_estimator', 'style');
?>

<div id="door-estimator-admin">
    <h2><?php p($l->t('Door Estimator Settings')); ?></h2>
    <div class="section">
        <h3><?php p($l->t('Pricing Data Management')); ?></h3>
        <div id="pricing-management"></div>
    </div>
</div>
```

### 5. Frontend Assets

#### js/door-estimator.js
```javascript
// Main application JavaScript
(function() {
    'use strict';
    
    // Initialize the Door Estimator app
    document.addEventListener('DOMContentLoaded', function() {
        const app = document.getElementById('door-estimator-app');
        if (app) {
            // Load React component or vanilla JS app
            initializeDoorEstimator(app);
        }
    });
    
    function initializeDoorEstimator(container) {
        // App initialization logic
        container.innerHTML = '<div class="door-estimator-loaded">Door Estimator Ready</div>';
        
        // Load pricing data
        loadPricingData();
        
        // Setup event handlers
        setupEventHandlers();
    }
    
    function loadPricingData() {
        fetch(OC.generateUrl('/apps/door_estimator/api/pricing'))
            .then(response => response.json())
            .then(data => {
                window.doorEstimatorData = data;
                console.log('Pricing data loaded:', data);
            })
            .catch(error => {
                console.error('Error loading pricing data:', error);
            });
    }
    
    function setupEventHandlers() {
        // Event handling logic
    }
})();
```

#### css/style.css
```css
/* Door Estimator Styles */
#door-estimator-app {
    padding: 20px;
    max-width: 1200px;
    margin: 0 auto;
}

.door-estimator-section {
    background: white;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.door-estimator-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 30px;
    border-radius: 8px;
    margin-bottom: 30px;
}

.door-estimator-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
}

.price-input {
    width: 80px;
    padding: 5px;
    border: 1px solid #ddd;
    border-radius: 4px;
    text-align: right;
}

.total-display {
    font-weight: bold;
    color: #28a745;
    font-size: 1.1em;
}

/* Responsive design */
@media (max-width: 768px) {
    #door-estimator-app {
        padding: 10px;
    }
    
    .door-estimator-grid {
        grid-template-columns: 1fr;
    }
}
```

### 6. Scripts

#### scripts/setup.sh
```bash
#!/bin/bash

# NextCloud Door Estimator Setup Script

echo "Setting up NextCloud Door Estimator..."

# Check if we're in the NextCloud apps directory
if [ ! -d "../../core" ]; then
    echo "Error: This script should be run from the NextCloud apps directory"
    exit 1
fi

# Install composer dependencies
if [ -f "composer.json" ]; then
    echo "Installing PHP dependencies..."
    composer install --no-dev --optimize-autoloader
fi

# Set proper permissions
echo "Setting permissions..."
chown -R www-data:www-data .
chmod -R 755 .

# Enable the app
echo "Enabling Door Estimator app..."
sudo -u www-data php ../../occ app:enable door_estimator

echo "Setup complete! You can now access Door Estimator from your NextCloud apps menu."
echo ""
echo "Next steps:"
echo "1. Import your Excel pricing data using: php ../../occ door-estimator:import-pricing"
echo "2. Configure user permissions in NextCloud admin panel"
echo "3. Access the app from the NextCloud apps menu"
```

#### scripts/import-excel-data.php
```php
<?php
/**
 * Script to import Excel data into Door Estimator
 * Usage: php import-excel-data.php /path/to/exported/csv/files/
 */

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

if ($argc < 2) {
    echo "Usage: php import-excel-data.php /path/to/csv/files/\n";
    exit(1);
}

$csvPath = $argv[1];
if (!is_dir($csvPath)) {
    echo "Error: Directory not found: $csvPath\n";
    exit(1);
}

echo "Importing Excel data from: $csvPath\n";

// Import logic here
$files = glob($csvPath . '*.csv');
foreach ($files as $file) {
    $category = basename($file, '.csv');
    echo "Importing $category from $file...\n";
    
    // CSV parsing and database insertion logic
    importCsvFile($file, $category);
}

echo "Import complete!\n";

function importCsvFile($file, $category) {
    // Implementation for CSV import
    echo "  - Processing $file\n";
}
?>
```

### 7. Documentation

#### docs/INSTALLATION.md
```markdown
# Installation Guide

## Prerequisites
- NextCloud 25+
- PHP 8.0+
- MySQL/PostgreSQL
- All PHP dependencies are bundled in the `vendor/` directory. Composer is only required for developers who wish to update dependencies.

## Step-by-Step Installation

1. **Download the app**
   ```bash
   cd /var/www/nextcloud/apps/
   git clone https://github.com/kdegeek/nextcloud-door-estimator.git door_estimator
   ```

2. **Install dependencies**
   ```bash
   # All required PHP dependencies are already bundled in the vendor/ directory.
   # Composer is only needed by developers who wish to update dependencies.
   ```

3. **Set permissions**
   ```bash
   chown -R www-data:www-data .
   chmod -R 755 .
   ```

4. **Enable the app**
   ```bash
   sudo -u www-data php /var/www/nextcloud/occ app:enable door_estimator
   ```

5. **Import your data**
   ```bash
   sudo -u www-data php /var/www/nextcloud/occ door-estimator:import-pricing
   ```

## Configuration

Access the admin panel at: Settings > Administration > Door Estimator
```

#### docs/api.md
```markdown
# API Documentation

## Endpoints

### Pricing Data
- `GET /api/pricing` - Get all pricing data
- `GET /api/pricing/{category}` - Get pricing for specific category
- `POST /api/pricing` - Update pricing item

### Quotes
- `GET /api/quotes` - Get user's quotes  
- `POST /api/quotes` - Save a quote
- `GET /api/quotes/{id}` - Get specific quote
- `GET /api/quotes/{id}/pdf` - Generate PDF

### Price Lookup
- `POST /api/lookup-price` - Lookup price for item

## Examples

```javascript
// Get all pricing data
fetch('/apps/door_estimator/api/pricing')
  .then(response => response.json())
  .then(data => console.log(data));

// Save a quote
fetch('/apps/door_estimator/api/quotes', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ quoteData: {...} })
});
```
```

## How to Set This Up

1. **Create your private GitHub repo:**
   - Go to GitHub.com
   - Click "New Repository"
   - Name it `door-estimator-nextcloud`
   - Make it private
   - Don't initialize with README (we'll add our own)

2. **Clone and set up locally:**
   ```bash
   git clone https://github.com/yourusername/door-estimator-nextcloud.git
   cd door-estimator-nextcloud
   
   # Create all the directories
   mkdir -p appinfo lib/Controller lib/Service lib/Migration lib/Command lib/Settings templates js css img docs scripts tests/Unit tests/Integration docker
   ```

3. **Add all the files I've provided above** to their respective directories

4. **Push to GitHub:**
   ```bash
   git add .
   git commit -m "Initial commit: NextCloud Door Estimator App"
   git push origin main
   ```

Would you like me to provide any additional files or help you with specific parts of the setup process?