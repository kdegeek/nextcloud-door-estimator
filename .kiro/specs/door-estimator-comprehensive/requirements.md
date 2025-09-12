# Door Estimator Application - Requirements Document

## Introduction

The Door Estimator Application is a comprehensive Nextcloud-based system that modernizes Excel-based door and hardware estimating workflows. The application provides a professional web interface for creating quotes, managing pricing data, and generating professional PDF quotes for clients. It preserves existing business logic while adding multi-user capabilities, real-time collaboration, and modern web features.

## Requirements

### Requirement 1: User Authentication and Authorization

**User Story:** As a business owner, I want role-based access control so that regular users can create quotes while only administrators can manage pricing data.

#### Acceptance Criteria

1. WHEN a user accesses the application THEN the system SHALL authenticate them using Nextcloud's built-in user management
2. WHEN an authenticated user logs in THEN the system SHALL determine their role (regular user or administrator)
3. WHEN a regular user accesses the application THEN the system SHALL provide access to estimator and quote management features only
4. WHEN an administrator accesses the application THEN the system SHALL provide access to all features including pricing data management
5. WHEN an unauthenticated user attempts to access the application THEN the system SHALL redirect them to Nextcloud's login page

### Requirement 2: Pricing Data Management

**User Story:** As an administrator, I want to manage comprehensive pricing data for doors, frames, hardware, and related components so that users can create accurate quotes.

#### Acceptance Criteria

1. WHEN an administrator views pricing data THEN the system SHALL display items organized by categories: doors, door options, inserts, frames, frame options, hinges, weatherstrip, closers, locksets, exit devices, and hardware
2. WHEN an administrator adds a new pricing item THEN the system SHALL require category, item name, and price as mandatory fields
3. WHEN an administrator updates pricing data THEN the system SHALL validate that prices are positive numeric values
4. WHEN pricing data is updated THEN the system SHALL immediately reflect changes in price lookups for new quotes
5. WHEN an administrator searches pricing items THEN the system SHALL provide real-time search across item names and categories
6. WHEN the system handles frame pricing THEN the system SHALL support subcategories for HM Drywall, HM EWA, and HM USA frame types

### Requirement 3: Data Import and Export

**User Story:** As an administrator, I want to import and export pricing data in multiple formats so that I can maintain data efficiently and integrate with existing workflows.

#### Acceptance Criteria

1. WHEN an administrator imports data THEN the system SHALL accept JSON, CSV, and Excel (XLS/XLSX) file formats
2. WHEN importing JSON data THEN the system SHALL validate the structure contains pricingData and markups keys
3. WHEN importing CSV or Excel data THEN the system SHALL use the first row as headers and map columns to database fields
4. WHEN import validation fails THEN the system SHALL provide detailed error messages indicating specific rows and issues
5. WHEN an administrator exports data THEN the system SHALL generate a JSON file containing all pricing data and markup settings
6. WHEN file uploads exceed 5MB THEN the system SHALL reject the upload with an appropriate error message
7. WHEN import operations are rate-limited THEN the system SHALL prevent more than one import per second per user

### Requirement 4: Quote Creation and Management

**User Story:** As a user, I want to create detailed quotes with multiple product categories and automatic price calculations so that I can provide accurate estimates to customers.

#### Acceptance Criteria

1. WHEN a user creates a new quote THEN the system SHALL provide input sections for all product categories
2. WHEN a user selects an item name THEN the system SHALL automatically lookup and populate the base price
3. WHEN a user enters quantities THEN the system SHALL calculate line totals as quantity × unit price
4. WHEN a user modifies frame types THEN the system SHALL update pricing based on the selected frame subcategory
5. WHEN calculating section totals THEN the system SHALL apply appropriate markups (doors: 15%, frames: 12%, hardware: 18%)
6. WHEN a user saves a quote THEN the system SHALL store all quote data, markups, and calculated totals
7. WHEN a user provides a quote name THEN the system SHALL use it, otherwise generate a default name with timestamp
8. WHEN calculating grand totals THEN the system SHALL sum all section totals with applied markups

### Requirement 5: Quote Pricing Flexibility

**User Story:** As a user, I want to adjust final quote pricing on a case-by-case basis so that I can accommodate special circumstances while preserving base pricing data.

#### Acceptance Criteria

1. WHEN a user views a quote line item THEN the system SHALL display both the base price and allow manual price override
2. WHEN a user modifies a line item price THEN the system SHALL recalculate totals using the modified price
3. WHEN a user changes markup percentages for a quote THEN the system SHALL apply the custom markups to that quote only
4. WHEN base pricing data is updated THEN the system SHALL NOT automatically update existing saved quotes
5. WHEN a user creates a new quote THEN the system SHALL use current base pricing and default markup settings
6. WHEN a user saves a quote with custom pricing THEN the system SHALL preserve both base and modified prices

### Requirement 6: Quote Management Operations

**User Story:** As a user, I want to save, load, duplicate, and delete quotes so that I can efficiently manage multiple customer estimates.

#### Acceptance Criteria

1. WHEN a user saves a quote THEN the system SHALL associate it with their user account
2. WHEN a user views their quotes THEN the system SHALL display a list with quote names, totals, and dates
3. WHEN a user loads a saved quote THEN the system SHALL restore all line items, quantities, prices, and markups
4. WHEN a user duplicates a quote THEN the system SHALL create a copy with "(Copy)" appended to the name
5. WHEN a user deletes a quote THEN the system SHALL remove it permanently after confirmation
6. WHEN displaying quote lists THEN the system SHALL order them by most recently updated first
7. WHEN a user accesses quotes THEN the system SHALL only show quotes they created (data isolation)

### Requirement 7: PDF Quote Generation

**User Story:** As a user, I want to generate professional PDF quotes so that I can provide branded, consistent documentation to customers.

#### Acceptance Criteria

1. WHEN a user generates a PDF THEN the system SHALL create a professional document with company branding
2. WHEN generating PDFs THEN the system SHALL include quote number, date, customer info, and all line items
3. WHEN displaying line items THEN the system SHALL show item descriptions, quantities, unit prices, and totals
4. WHEN calculating section totals THEN the system SHALL display markup percentages and final amounts
5. WHEN showing grand totals THEN the system SHALL prominently display the final quote amount
6. WHEN PDFs are generated THEN the system SHALL include standard terms (30-day validity, price change notice)
7. WHEN PDF generation completes THEN the system SHALL provide a download link to the user

### Requirement 8: User Interface and Experience

**User Story:** As a user, I want a modern, responsive interface with accessibility features so that I can work efficiently on desktop, tablet, and mobile devices.

#### Acceptance Criteria

1. WHEN users access the application THEN the system SHALL provide a responsive design that works on desktop, tablet, and mobile
2. WHEN users interact with the interface THEN the system SHALL support both light and dark mode themes
3. WHEN users perform actions THEN the system SHALL provide immediate feedback through toast notifications
4. WHEN users navigate the application THEN the system SHALL provide clear tab-based navigation between Estimator and Admin sections
5. WHEN users input data THEN the system SHALL provide appropriate form validation and error messages
6. WHEN users work with tables THEN the system SHALL provide proper ARIA labels and accessibility features
7. WHEN the system loads data THEN the system SHALL display loading indicators to inform users of progress

### Requirement 9: Performance and Scalability

**User Story:** As a business owner, I want the system to perform efficiently with up to 25 concurrent users and handle thousands of pricing items so that it scales with business growth.

#### Acceptance Criteria

1. WHEN up to 25 users access the system concurrently THEN the system SHALL maintain responsive performance
2. WHEN the system handles 30-50 quotes per day THEN the system SHALL process them without performance degradation
3. WHEN the database contains thousands of pricing items THEN the system SHALL provide fast price lookups (sub-second response)
4. WHEN users search pricing data THEN the system SHALL return results within 500 milliseconds
5. WHEN generating PDFs THEN the system SHALL complete generation within 5 seconds
6. WHEN importing large data files THEN the system SHALL process them efficiently without blocking other operations
7. WHEN the system scales THEN the system SHALL leverage Nextcloud's underlying infrastructure capabilities

### Requirement 10: Data Integrity and Validation

**User Story:** As an administrator, I want robust data validation and integrity checks so that the system maintains accurate pricing and quote information.

#### Acceptance Criteria

1. WHEN users input pricing data THEN the system SHALL validate that prices are positive numeric values
2. WHEN users input quantities THEN the system SHALL validate that quantities are non-negative integers
3. WHEN users input item names THEN the system SHALL enforce maximum length limits and sanitize input
4. WHEN importing data THEN the system SHALL validate file formats and reject malformed data
5. WHEN database operations occur THEN the system SHALL use prepared statements to prevent SQL injection
6. WHEN users input data THEN the system SHALL sanitize all input to prevent XSS attacks
7. WHEN calculations are performed THEN the system SHALL handle edge cases like zero quantities and null values gracefully

### Requirement 11: System Integration and Compatibility

**User Story:** As a system administrator, I want the application to integrate seamlessly with Nextcloud infrastructure so that it leverages existing security, user management, and deployment capabilities.

#### Acceptance Criteria

1. WHEN the application is deployed THEN the system SHALL integrate with Nextcloud versions 29-31 as specified in info.xml dependencies
2. WHEN the application runs THEN the system SHALL require PHP 8.0 or higher with extensions: pdo, json, curl, mbstring, xml
3. WHEN the application stores data THEN the system SHALL use OCP\IDBConnection and Nextcloud's query builder exclusively
4. WHEN the application handles files THEN the system SHALL use OCP\Files\IAppData for app-specific storage
5. WHEN users authenticate THEN the system SHALL use OCP\IUserSession for session management and user context
6. WHEN the application logs events THEN the system SHALL use Psr\Log\LoggerInterface injected through dependency injection
7. WHEN the application is installed THEN the system SHALL provide proper app.php bootstrap, routes.php definitions, and database.xml schema
8. WHEN the application handles caching THEN the system SHALL use OCP\ICacheFactory for distributed caching needs
9. WHEN the application manages configuration THEN the system SHALL use OCP\IConfig for both app-level and user-level settings
10. WHEN the application is enabled/disabled THEN the system SHALL properly handle Nextcloud's app lifecycle events

### Requirement 12: Nextcloud App Framework Compliance

**User Story:** As a system administrator, I want the application to fully comply with Nextcloud app development standards so that it integrates seamlessly, remains maintainable, and follows security best practices.

#### Acceptance Criteria

1. WHEN the application is structured THEN the system SHALL follow the official Nextcloud app directory structure with appinfo/, lib/, templates/, and src/ directories
2. WHEN defining app metadata THEN the system SHALL use a compliant info.xml file with proper namespace, dependencies, and version constraints
3. WHEN implementing controllers THEN the system SHALL extend OCP\AppFramework\Controller and use proper HTTP attributes for routing
4. WHEN handling database operations THEN the system SHALL use Nextcloud's IDBConnection interface and query builder for all database interactions
5. WHEN implementing services THEN the system SHALL use dependency injection and follow Nextcloud's service container patterns
6. WHEN handling user sessions THEN the system SHALL use OCP\IUserSession interface for authentication and user management
7. WHEN implementing API endpoints THEN the system SHALL use proper OpenAPI annotations and follow RESTful conventions
8. WHEN handling file operations THEN the system SHALL use OCP\Files\IAppData interface for app-specific file storage
9. WHEN implementing migrations THEN the system SHALL use Nextcloud's migration system for database schema changes
10. WHEN logging events THEN the system SHALL use Psr\Log\LoggerInterface for consistent logging
11. WHEN handling configuration THEN the system SHALL use OCP\IConfig interface for app settings and user preferences
12. WHEN implementing frontend THEN the system SHALL use Nextcloud's Vue.js components and follow the design system guidelines
13. WHEN packaging the app THEN the system SHALL include proper composer.json, package.json, and build scripts
14. WHEN handling security THEN the system SHALL implement CSRF protection, input sanitization, and follow Nextcloud security guidelines

### Requirement 13: Error Handling and Recovery

**User Story:** As a user, I want clear error messages and graceful error handling so that I can understand and resolve issues quickly.

#### Acceptance Criteria

1. WHEN errors occur THEN the system SHALL display user-friendly error messages with actionable guidance
2. WHEN API calls fail THEN the system SHALL provide specific error codes and descriptions
3. WHEN file uploads fail THEN the system SHALL indicate the specific reason (size, format, corruption)
4. WHEN database operations fail THEN the system SHALL log detailed errors while showing generic messages to users
5. WHEN network issues occur THEN the system SHALL provide retry mechanisms for non-critical operations
6. WHEN validation fails THEN the system SHALL highlight specific fields and provide correction guidance
7. WHEN the system encounters unexpected errors THEN the system SHALL fail gracefully without exposing sensitive information