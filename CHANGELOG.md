# Changelog

All notable changes to this project will be documented in this file.

## [1.1.0] - 2026-05-09

### Added — Import/Export Data System
- Full import/export system for 4 modules: Product, Lead, Quote Request, BTU Calculation
- Support for 4 file formats: XLSX, CSV (UTF-8 BOM), XML, JSON
- Export with selectable field groups (Basic, Pricing, Specs, SEO, Media, etc.)
- Import with 3 modes: Create only, Update existing, Upsert
- Import preview/validation before writing to DB (no data written without admin confirm)
- Per-row validation: phone format, email format, numeric fields, JSON validity, foreign key checks
- Import preview shows: total/valid/error counts, first 20 rows, per-row errors & actions
- Import result page with created/updated/failed statistics and error details
- Chunked processing for large files (configurable chunk sizes)
- DB transaction safety per chunk during import
- Export/Import buttons on all 4 module list pages (Products, Leads, Báo giá, BTU)
- Central Data Transfer admin page (System → Import / Export) with job history
- UTF-8 encoding detection & auto-conversion for imported files
- CSV export with UTF-8 BOM for proper Vietnamese display in Excel
- XML export with `<?xml version="1.0" encoding="UTF-8"?>` declaration
- JSON export with `JSON_UNESCAPED_UNICODE` for Vietnamese characters

### Added — Database
- `data_import_jobs` table — tracks every import with full audit trail
- `data_export_jobs` table — tracks every export with file path & expiration
- 8 new permissions: `{product,lead,quote_request,btu_calculation}.{import,export}`
- 6 new site settings for import/export configuration

### Added — Services
- `DataExportService` — core export logic (XLSX, CSV, XML, JSON writers)
- `DataImportService` — core import logic (file parsing, validation, preview, confirm)
- `ModuleRegistry` — central field group & module configuration registry
- `ImportHandlerInterface` — contract for module-specific import handlers
- `ProductImportHandler` — brand/category name resolution, JSON parsing, unique slug gen
- `LeadImportHandler` — phone/email validation
- `QuoteRequestImportHandler` — product existence validation, HVAC field parsing
- `BtuCalculationImportHandler` — numeric/JSON validation
- `HasDataTransferActions` trait — reusable export/import buttons for any list page

### Security
- Import/export buttons hidden when user lacks permission
- File upload: MIME type whitelist, size limit, private storage
- Export download route requires authentication + module permission check
- Export files auto-expire after configurable days (default: 30)
- No executable file uploads (MIME whitelist: xlsx, csv, xml, json only)
- Import files stored in `storage/app/private/` — never publicly accessible

### Dependencies
- Added `maatwebsite/excel` (^3.1) — PhpSpreadsheet wrapper for XLSX support

---

## [1.0.0] - 2026-05-08

### Added
- Lead system with 3 flows: General CTA, Product CTA (Quick Quote), BTU Consultation
- Multi-step quote form (5 steps) with HVAC-specific fields
- BTU Calculator with standard W/m² cooling load coefficients
- Product Quote Modal (AJAX, desktop centered / mobile bottom sheet)
- Mail notification system with template engine (admin + customer)
- Mail template management via admin panel
- Admin CRM dashboard (Leads, Quote Requests, BTU Calculations)
- Product management with brands, categories, FAQs, documents
- Blog/content management system
- SEO system (sitemap, robots.txt, meta tags, structured data)
- Google Merchant Feed
- Product comparison tool
- Landing page builder with configurable sections
- Case studies module
- Policy pages management
- Role-based access control (RBAC) with Filament Shield
- Source/UTM tracking on all lead forms
- GTM dataLayer integration for conversion tracking

### Fixed
- UTF-8 encoding across entire system (admin, frontend, email, DB)
- Email template variable binding consistency
- Product CTA UX (modal-only, no pre-rendered form)
- Role/permission system (removed non-existent permissions, fixed privilege escalation)
- N+1 query optimization on product detail page (8 queries → 2)
- MySQL `only_full_group_by` compatibility for rating statistics
- BTU calculator showroom W/m² coefficient (900 → 300)
- Landing form source tracking (hardcoded literal → actual URL)
- Phone validation regex on all forms (BTU, Full Quote)
- Admin email empty field display (removed "—" fallback, use array_filter)
- Quote admin email template double BTU suffix
- BTU admin detail missing W/m² display and wrong area unit

### Security
- Honeypot spam protection on all public forms
- Rate limiting on all form submissions (5-10/hour per IP)
- CSRF protection on all POST routes
- No secrets/API keys committed to repository
- Standardized .env.example with no real values
- Admin password reset restricted to super_admin role
- Granular RBAC: user.create, user.edit, user.delete permissions

### Changed
- Centralized BTU calculation via BtuCalculatorService (single source of truth)
- Standardized mail template variable descriptions (Vietnamese with diacritics)
- Optimized product review rating statistics query
- Consistent field naming across all lead forms (full_name, phone, email, province_city)
- Production-ready .env.example defaults (debug=false, env=production)
