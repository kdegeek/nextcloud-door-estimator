# Security Documentation

## Input Validation

### Frontend Validation
- All user inputs are validated using Zod schemas before API calls
- Type checking ensures data integrity
- Length limits are enforced on text inputs
- Numeric values are validated for ranges

### Backend Validation
- Double validation layer: controller and service levels
- Strict type checking for all input parameters
- Length constraints on string inputs:
  - Item names: max 255 characters
  - Categories: max 50 characters
  - Subcategories: max 100 characters
  - Descriptions: max 2000 characters
- Numeric validation for prices (0 to 1,000,000)
- Required field validation

## File Upload Security

### File Type Restrictions
- Only allowed file types:
  - CSV (text/csv)
  - Excel XLS (application/vnd.ms-excel)
  - Excel XLSX (application/vnd.openxmlformats-officedocument.spreadsheetml.sheet)
  - JSON (application/json)
- File size limit: 5MB maximum
- MIME type validation

### File Processing Security
- Temporary file handling with proper cleanup
- PhpSpreadsheet library for safe Excel/CSV parsing
- JSON validation before processing
- Row limit enforcement (10,000 rows max)

## SQL Injection Prevention

### Query Builder Usage
- All database queries use Nextcloud's QueryBuilder
- Parameterized queries with createNamedParameter()
- No raw SQL concatenation
- Prepared statements for all user inputs

### Search Query Sanitization
- Special characters escaped in LIKE queries
- Wildcards (% and _) are properly escaped
- Input sanitization before query construction

## XSS/CSRF Mitigations

### XSS Prevention
- HTML stripping using strip_tags() on all text inputs
- Vue.js automatic escaping in templates
- Content Security Policy headers from Nextcloud

### CSRF Protection
- Nextcloud's built-in CSRF token validation
- All state-changing operations require valid tokens
- Automatic token rotation

## Authorization/Authentication

### Nextcloud Integration
- Leverages Nextcloud's authentication system
- User session management by Nextcloud core
- NoAdminRequired attribute for user-level endpoints
- User ID isolation for quotes (getUserId())

### Access Control
- Users can only access their own quotes
- Quote IDs are validated against user ownership
- Admin panel requires appropriate permissions

## Security Testing Checklist

### Input Validation Tests
- [ ] Test with oversized inputs (>255 chars for names)
- [ ] Test with special characters in all fields
- [ ] Test with negative numbers for prices
- [ ] Test with extremely large numbers
- [ ] Test with empty/null values

### File Upload Tests
- [ ] Test with files larger than 5MB
- [ ] Test with unsupported file types
- [ ] Test with malformed CSV/Excel files
- [ ] Test with files containing formulas
- [ ] Test with files containing macros

### SQL Injection Tests
- [ ] Test search with SQL keywords
- [ ] Test with single/double quotes in inputs
- [ ] Test with comment sequences (-- or /**/)
- [ ] Test with UNION statements
- [ ] Test with wildcard characters

### XSS Tests
- [ ] Test with script tags in all inputs
- [ ] Test with event handlers (onclick, onerror)
- [ ] Test with data URIs
- [ ] Test with encoded payloads

### Authentication Tests
- [ ] Verify quote access across different users
- [ ] Test accessing quotes without authentication
- [ ] Verify token validation on all endpoints
- [ ] Test session timeout handling

## Incident Response

In case of security issues:
1. Report to Nextcloud security team
2. Apply patches immediately
3. Review logs for exploitation attempts
4. Update dependencies if vulnerable
5. Document incident and remediation

## Dependencies

Regular security updates required for:
- Nextcloud core
- PhpSpreadsheet library
- Vue.js and frontend dependencies
- Zod validation library

## Security Headers

Application inherits Nextcloud's security headers:
- X-Content-Type-Options: nosniff
- X-Frame-Options: SAMEORIGIN
- X-XSS-Protection: 1; mode=block
- Content-Security-Policy (configured by Nextcloud)