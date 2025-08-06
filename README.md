# Door Estimator NextCloud App

A comprehensive door and hardware estimating application for NextCloud that modernizes Excel-based workflows with a professional web interface.

---

**Note:** The frontend was migrated from React to Vue 3. See [MIGRATION.md](MIGRATION.md) for a detailed summary of the migration process, key changes, and future maintenance recommendations.

---

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

> ⚠️ **npm 10 Compatibility Warning:**
> If you are using **npm 10.x**, you may encounter compatibility issues with some dependencies or build tools.
> - If you experience build errors or unexpected issues, it is recommended to downgrade to **npm 9** (`npm install -g npm@9`).
> - Known issues with npm 10 include stricter peer dependency resolution and changes to the lockfile format.
> - See the install scripts and documentation for details and workarounds.

- **Node.js**: Version 16 or higher is required in the container to build the Vue 3 frontend.
  - To install Node.js v16+ in Ubuntu/Debian containers:
    ```bash
    curl -fsSL https://deb.nodesource.com/setup_16.x | bash - && apt-get install -y nodejs
    ```
  - Verify installation with `node -v` (should be 16.x or higher).

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

---

## 🖥️ Frontend Build & Development

The Door Estimator frontend is built with **Vue 3** and **TypeScript**, using Nextcloud's official webpack config.

> **Node.js v16+ is required to build the frontend.**
> If Node.js is not present in your container, install it with:
> ```bash
> curl -fsSL https://deb.nodesource.com/setup_16.x | bash - && apt-get install -y nodejs
> ```

### Setup & Build

```bash
# 1. Install Node.js (v16+ required)
#    (see above for install instructions if missing)
# 2. Install dependencies
npm install

# 3. Build for production
npm run build

# 4. For development (with hot reload)
npm run build:dev

# 5. Start local dev server (if supported)
npm start
```

- **TypeScript**: All source code is in TypeScript (`.vue`, `.ts`). Compilation is handled by webpack and `ts-loader`.
- **Scripts**: See `package.json` for all available scripts.
- **Output**: Compiled JS is output to `js/door-estimator.js` for Nextcloud to serve.

### Development Workflow

- Edit Vue/TypeScript code in `src/` or `js/`.
- Use `npm run build:dev` for fast rebuilds and source maps.
- Use `npm test` to run Jest unit tests.
- For Nextcloud integration, ensure the app is enabled and the built JS is up to date.

See [`docs/INSTALLATION.md`](docs/INSTALLATION.md:1) for full environment setup.

---

## 🐘 Backend Setup & Database

The backend is a standard Nextcloud PHP app.

### PHP Requirements

- PHP 8.0+ with extensions: `pdo`, `json`, `curl`, `mbstring`, `xml`
- All required PHP dependencies are bundled in `vendor/` (no Composer needed for end-users).

### Database Migrations

- Database schema is managed via migration scripts in `lib/Migration/`.
- Migrations are applied automatically on app enable, or can be run manually:
  ```bash
  sudo -u www-data php /var/www/nextcloud/occ migrations:execute door_estimator 001000
  ```

### Pricing Data Import

- Place your extracted pricing data as `scripts/extracted_pricing_data.json`.
- Import with:
  ```bash
  sudo -u www-data php /var/www/nextcloud/occ door-estimator:import-pricing
  ```
- See [`docs/PRICING_DATA_SETUP.md`](docs/PRICING_DATA_SETUP.md:1) for details.

### Automated Setup

- Use [`scripts/setup.sh`](scripts/setup.sh:1) for a fully automated install, including permissions, dependencies, and data import.
- See [`docs/INSTALLATION.md`](docs/INSTALLATION.md:1) for advanced/manual instructions.

---

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

---

### 🐳 Manual Frontend Build in AIO Containers

If you need to manually build the frontend inside a NextCloud AIO container, use:

```bash
docker exec nextcloud-aio-nextcloud bash -c 'cd /var/www/html/apps/door_estimator && sh scripts/build.sh'
```

Ensure Node.js v16+ is installed in the container before running the build script.

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

**Frontend/Build Issues**

- **npm install fails**:
  - Ensure Node.js v16+ is installed (`node -v`).
  - **If you are using npm 10.x, see the warning above. Some dependencies may not install or build correctly. Downgrade to npm 9 if you encounter issues.**
  - Delete `node_modules/` and `package-lock.json`, then run `npm install` again.
  - If you see errors about missing `webpack` or `ts-loader`, run `npm install` to restore all dependencies.

- **TypeScript compilation errors**:
  - Run `npm run build:dev` to see detailed error output.
  - Check `tsconfig.json` for correct settings.

- **Webpack build fails**:
  - Ensure all devDependencies are installed (`npm install`).
  - Check for syntax errors in your TypeScript code.

- **Hot reload not working**:
  - Use `npm start` or `npm run build:dev` for development.
  - Ensure your browser is not caching old JS.

**Backend/PHP Issues**

- **App won't enable**:
  - Check Nextcloud logs: `tail -f /var/www/nextcloud/data/nextcloud.log`
  - Verify file permissions: `ls -la /var/www/nextcloud/apps/door_estimator/`
  - Ensure all required PHP extensions are installed.

- **Database migration fails**:
  - Check user permissions and run migrations manually if needed.
  - See [`docs/INSTALLATION.md`](docs/INSTALLATION.md:1) for migration troubleshooting.

- **Pricing data not loading**:
  - Ensure `scripts/extracted_pricing_data.json` exists and is valid JSON.
  - Re-import: `sudo -u www-data php /var/www/nextcloud/occ door-estimator:import-pricing`
  - Check database tables: `sudo -u www-data php /var/www/nextcloud/occ db:show-tables | grep door_estimator`

- **Permission errors**:
  - Set correct ownership: `sudo chown -R www-data:www-data /var/www/nextcloud/apps/door_estimator`
  - Set permissions: `sudo chmod -R 755 /var/www/nextcloud/apps/door_estimator`

**General**

- **JavaScript errors**:
  - Check browser console.
  - Verify Nextcloud version compatibility.
  - Clear browser cache.

- **PDF generation issues**:
  - All dependencies are bundled; check file permissions on the quotes directory.

- **Composer issues (for developers only)**:
  - Run `composer install` in the app root if you need to update PHP dependencies.

**See [`docs/INSTALLATION.md`](docs/INSTALLATION.md:1) for a full troubleshooting matrix.**

### Database Maintenance

```bash
# Check table status
sudo -u www-data php /var/www/nextcloud/occ db:show-tables | grep door_estimator

# Backup pricing data
mysqldump nextcloud_db door_estimator_pricing > pricing_backup.sql

# Optimize database
sudo -u www-data php /var/www/nextcloud/occ db:add-missing-indices
```
## 🤝 Contributor Setup

We welcome contributions! To set up a development environment for either the frontend or backend:

### Frontend (Vue 3/TypeScript)

1. **Install Node.js v16+**
   Download from [nodejs.org](https://nodejs.org/).

2. **Install dependencies**
   ```bash
   npm install
   ```

3. **Run tests**
   ```bash
   npm test
   ```

4. **Build and develop**
   - Production build: `npm run build`
   - Development build: `npm run build:dev`
   - Start dev server: `npm start` (if supported)

5. **Code style**
   - Use TypeScript and Vue 3 best practices.
   - Follow the structure in `src/` and `js/`.

> **Note:** This project uses [Vue 3](https://vuejs.org/) with the Composition API and [@vue/test-utils](https://test-utils.vuejs.org/) v2+ for all component testing. If you are migrating from an older version, ensure all dependencies and test utilities are updated for Vue 3 compatibility.

### Backend (PHP/Nextcloud)

1. **Install PHP 8.0+** with required extensions.
2. **Install Composer** (for development only):
   ```bash
   composer install
   ```
3. **Run/modify migrations** in `lib/Migration/`.
4. **Run backend tests** (if available) using PHPUnit.
5. **Import pricing data** as described above.

### General

- See [`docs/INSTALLATION.md`](docs/INSTALLATION.md:1) for full setup and environment details.
- Use [`scripts/setup.sh`](scripts/setup.sh:1) to automate most setup steps.
- For detailed app structure, see the "Project Structure" section above.

---

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

- See [`docs/INSTALLATION.md`](docs/INSTALLATION.md:1) for a comprehensive installation and troubleshooting guide.
- For setup automation, see [`scripts/setup.sh`](scripts/setup.sh:1).
- For advanced issues, check:
  1. **NextCloud Logs**: `/var/www/nextcloud/data/nextcloud.log`
  2. **Browser Console**: JavaScript errors
  3. **Database Logs**: MySQL/PostgreSQL logs
  4. **File Permissions**: All files must be accessible by the web server

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

---

## 📄 Documentation Update Summary

- **Node.js v16+ requirement** is now clearly stated for all frontend build steps.
- **One-liner install command** for Node.js v16+ in containers is provided.
- **Manual frontend build command** for AIO containers is documented.
- All relevant sections updated for clarity and reproducibility.