# Implementation Plan

- [x] 1. Set up Nextcloud app foundation and core structure
  - Create proper Nextcloud app directory structure with appinfo/, lib/, src/, templates/ directories
  - Implement compliant info.xml with correct namespace, dependencies, and version constraints for Nextcloud 29-31
  - Set up app.php bootstrap file with proper service registration and dependency injection
  - Create routes.php with RESTful API endpoint definitions using proper HTTP attributes
  - Configure composer.json and package.json with required dependencies (TCPDF, PhpSpreadsheet, Vue 3, TypeScript)
  - _Requirements: 12.1, 12.2, 12.13, 11.7_

- [x] 2. Implement database schema and migration system
  - Create database.xml schema definition for pricing and quotes tables
  - Implement Version001000Date20250124000000 migration class extending SimpleMigrationStep
  - Design door_estimator_pricing table with proper indexes for category, item_name, and subcategory lookups
  - Design door_estimator_quotes table with JSON columns for quote_data and markups
  - Add proper foreign key constraints and performance indexes for user_id and updated_at columns
  - Test migration rollback capabilities and data preservation during schema changes
  - _Requirements: 12.9, 11.3, 9.3, 10.7_

- [x] 3. Create core service layer with business logic
  - Implement EstimatorService class with dependency injection for IDBConnection, IUserSession, IAppData, IConfig
  - Create pricing lookup methods with frame type support and subcategory filtering
  - Implement quote calculation logic with configurable markup percentages (doors: 15%, frames: 12%, hardware: 18%)
  - Build quote management methods (save, load, duplicate, delete) with user isolation
  - Add comprehensive input validation and sanitization for all service methods
  - Implement error handling with proper exception types and logging via LoggerInterface
  - _Requirements: 2.1, 2.6, 4.5, 5.1, 6.7, 12.5, 12.10_

- [x] 4. Build EstimatorController with RESTful API endpoints
  - Extend OCP\AppFramework\Controller with proper constructor dependency injection
  - Implement pricing management endpoints (GET /api/pricing, GET /api/pricing/{category}, POST /api/pricing)
  - Create quote management endpoints (POST /api/quotes, GET /api/quotes, GET /api/quotes/{id}, DELETE /api/quotes/{id})
  - Add search and lookup endpoints (POST /api/lookup-price, GET /api/pricing/search)
  - Implement proper HTTP status codes, error responses, and OpenAPI annotations
  - Add input validation, rate limiting for import operations, and CSRF protection
  - _Requirements: 12.3, 12.7, 4.1, 4.6, 6.1, 6.6, 13.2, 13.3_

- [x] 5. Implement data import and export functionality
  - Create file upload validation for JSON, CSV, and Excel formats with 5MB size limit
  - Implement JSON import with pricingData and markups structure validation
  - Build CSV/Excel import using PhpSpreadsheet with header row detection and error reporting
  - Add comprehensive error handling with row-level validation and detailed error messages
  - Implement export functionality generating JSON files with all pricing data and markup settings
  - Create rate limiting system using OCP\ICacheFactory to prevent import abuse
  - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 3.7_

- [x] 6. Create PDF generation system with professional formatting
  - Integrate TCPDF library for PDF generation with proper error handling
  - Design professional quote template with company branding and consistent formatting
  - Implement HTML-to-PDF conversion with proper styling for tables, headers, and totals
  - Add quote metadata (quote number, date, customer info) and line item details
  - Calculate and display section totals with markup percentages and grand total
  - Store generated PDFs using OCP\Files\IAppData interface with proper file naming
  - _Requirements: 7.1, 7.2, 7.3, 7.4, 7.5, 7.6, 7.7_

- [x] 7. Build Vue 3 frontend foundation with TypeScript
  - Set up Vue 3 project with Composition API, TypeScript, and Vite build system
  - Integrate Nextcloud Vue components (@nextcloud/vue) and design system
  - Create main App.vue component with tab navigation (Estimator/Admin) and theme toggle
  - Implement reactive state management for quote data, pricing data, and markups
  - Add toast notification system for user feedback and error display
  - Configure TypeScript interfaces for all data models (QuoteLineItem, PricingItem, QuoteData)
  - _Requirements: 8.1, 8.2, 8.3, 8.4, 12.12, 4.1_

- [x] 8. Implement estimator interface with dynamic quote building
  - Create quote builder with dynamic sections for all product categories (doors, frames, hardware, etc.)
  - Implement real-time price lookup integration with automatic price population
  - Build quantity and price input handling with immediate total calculations
  - Add frame type selection with conditional pricing for HM Drywall, HM EWA, HM USA subcategories
  - Implement section total calculations with configurable markup application
  - Create grand total calculation with proper markup aggregation across all sections
  - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 4.8, 5.1, 5.2_

- [x] 9. Build quote management interface with CRUD operations
  - Implement quote save functionality with optional quote naming and customer information
  - Create quote list display with sorting by date and user-specific filtering
  - Build quote loading functionality that restores all line items, quantities, prices, and markups
  - Add quote duplication feature with automatic name modification ("Copy" suffix)
  - Implement quote deletion with user confirmation and proper error handling
  - Ensure data isolation so users can only access their own quotes
  - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5, 6.6, 6.7_

- [x] 10. Create admin interface for pricing data management
  - Build pricing data grid with search, sort, and filter capabilities across categories
  - Implement inline editing for pricing items with real-time validation
  - Create category-based organization with support for subcategories (frame types)
  - Add bulk import interface with file upload, validation, and progress feedback
  - Implement export functionality with download generation for JSON format
  - Build admin-only access controls with proper permission checking
  - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5, 3.1, 3.5, 1.4_

- [x] 11. Implement flexible pricing and markup system
  - Create per-quote markup adjustment interface allowing case-by-case modifications
  - Build price override functionality for individual line items while preserving base pricing
  - Implement markup configuration management with default values and per-quote customization
  - Add visual indicators showing when prices or markups have been modified from defaults
  - Ensure base pricing data remains unchanged when quotes use custom pricing
  - Create markup persistence in quote data structure for accurate quote reproduction
  - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5, 5.6_

- [x] 12. Add comprehensive input validation and security measures
  - Implement client-side validation for all form inputs with real-time feedback
  - Add server-side validation using prepared statements and parameterized queries exclusively
  - Create input sanitization for all user data including HTML tag stripping and length limits
  - Implement XSS prevention with proper output encoding and Content Security Policy
  - Add file upload security with type validation, size limits, and content verification
  - Ensure CSRF protection is enabled for all state-changing operations
  - _Requirements: 10.1, 10.2, 10.3, 10.4, 10.5, 10.6, 12.14_

- [x] 13. Build responsive UI with accessibility and theme support
  - Implement responsive design that works on desktop, tablet, and mobile devices
  - Add dark/light theme toggle with user preference persistence
  - Create accessible forms with proper ARIA labels, keyboard navigation, and screen reader support
  - Implement loading states and progress indicators for all async operations
  - Add proper error message display with actionable guidance for users
  - Ensure consistent styling following Nextcloud design system guidelines
  - _Requirements: 8.1, 8.2, 8.5, 8.6, 8.7, 12.12_

- [x] 14. Implement performance optimizations and caching
  - Add database query optimization with proper indexing for frequent lookups
  - Implement caching strategy using OCP\ICacheFactory for pricing data and configuration
  - Create efficient search functionality with sub-500ms response times for pricing items
  - Add pagination for large datasets to prevent memory issues
  - Implement lazy loading for admin interface components to reduce initial bundle size
  - Optimize PDF generation to complete within 5 seconds for standard quotes
  - _Requirements: 9.1, 9.2, 9.3, 9.4, 9.5, 9.6, 9.7_

- [x] 15. Create comprehensive error handling and logging system
  - Implement structured error responses with consistent format and proper HTTP status codes
  - Add user-friendly error messages with actionable guidance for common issues
  - Create comprehensive logging using Psr\Log\LoggerInterface for all business operations
  - Implement retry mechanisms for transient failures in non-critical operations
  - Add graceful error handling that prevents application crashes and data loss
  - Create error monitoring and alerting for critical system failures
  - _Requirements: 13.1, 13.2, 13.3, 13.4, 13.5, 13.6, 13.7_

- [x] 16. Build authentication and authorization system
  - Integrate with Nextcloud's user authentication using OCP\IUserSession interface
  - Implement role-based access control distinguishing between regular users and administrators
  - Create admin-only route protection for pricing management and configuration endpoints
  - Add user session validation and proper session timeout handling
  - Implement user context isolation ensuring users can only access their own data
  - Create proper permission checking for all sensitive operations
  - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 12.6_

- [x] 17. Implement comprehensive testing suite
  - Create unit tests for all Vue components using Jest and Vue Test Utils
  - Build unit tests for all PHP services and controllers using PHPUnit
  - Implement integration tests for complete user workflows from quote creation to PDF generation
  - Add performance tests validating 25 concurrent users and sub-500ms response times
  - Create security tests for authentication, authorization, and input validation
  - Build automated test pipeline with coverage reporting and quality gates
  - _Requirements: 9.1, 9.3, 9.4, 10.5, 10.6, 12.14_

- [x] 18. Set up deployment and configuration management
  - Create proper Nextcloud app packaging with all required metadata and dependencies
  - Implement configuration management using OCP\IConfig for app settings and user preferences
  - Set up database migration execution and rollback procedures
  - Create installation and upgrade procedures following Nextcloud app standards
  - Implement health monitoring and system status checking
  - Add documentation for deployment, configuration, and maintenance procedures
  - _Requirements: 11.1, 11.7, 11.9, 11.10, 12.1, 12.13