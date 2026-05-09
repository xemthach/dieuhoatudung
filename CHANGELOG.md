# Changelog

All notable changes to this project will be documented in this file.

## [1.4.0] - 2026-05-09

### Fixed — Critical: Boolean Toggle Display Bug
- **All toggles in Site Settings showed ON regardless of actual DB value** — Root cause: `mount()` loaded boolean-type settings as raw strings (`"0"`, `"1"`). Filament Toggle treats non-empty string `"0"` as truthy → every toggle appeared ON even when DB stored OFF
- Fix: `mount()` now uses `filter_var($value, FILTER_VALIDATE_BOOLEAN)` to cast boolean-type settings to actual PHP `bool` before passing to form components
- This affected **all 50+ toggles** across the entire Settings page (R2, Mail, SEO, Sitemap, etc.)
- Also fixed corrupted UTF-8 comments in the file that prevented clean editing

### Fixed — SyncR2MediaJob URL Generation
- `SyncR2MediaJob` used `Storage::disk('r2')->url()` to generate public URLs — this relies on the disk driver config which may not be correctly configured
- Now uses `R2SyncService::buildPublicUrl()` which reads the actual Public URL from database settings
- Added **post-upload R2 object verification** (`$disk->fileExists()`) — if the file doesn't exist on R2 after upload, the item is marked as failed instead of falsely recorded as synced
- Added mid-job cancellation support (checks `$job->fresh()->status` each iteration)
- New status `completed_with_errors` when some files fail but others succeed
- Added structured logging for each upload success/failure

### Fixed — ProductDocumentsRelationManager Download URL
- Download action used `Storage::disk(config('media.disk', 'public'))->url()` — but `config('media.disk')` doesn't exist, silently falling back to `'public'` which may not resolve CDN URLs
- Now uses `media_url()` helper which correctly resolves to CDN or local URL

### Added — `media:audit` CLI Command
- `php artisan media:audit` — scans all media fields across 10 models (Product, Brand, Post, CaseStudy, ProductDocument, Testimonial, ProductReview, User, SiteSetting branding)
- Reports: DB path, local file existence, R2 sync status, fake CDN URLs
- Detects fake CDN URLs (CDN domain + path but `is_synced_to_r2 = false`)
- Handles JSON array fields (gallery, images_json)
- Options: `--model=Product` (filter), `--fix` (show repair suggestions)

### Added — `media:repair-paths` CLI Command
- `php artisan media:repair-paths` — converts full CDN/local URLs in DB back to relative paths
- `--dry-run` preview mode (no DB writes)
- Per-model filtering with `--model=`
- Handles JSON arrays, simple string paths
- Skips HTML content fields (needs full URLs to render)
- Collects base URLs from: APP_URL, R2 settings, old base URLs config

### Added — R2 Sync Manager Safety Guards
- Upload, Dry Run, and Replace URLs actions now **block with notification** when R2 is OFF — previously they created a job that immediately failed
- Modal descriptions explain what each action does
- Warning banner when R2 is OFF with link to Site Settings configuration

### Changed — MediaUrlReplaceService Rewrite
- `replaceUrls()` now verifies R2 sync status before any URL replacement — only replaces paths that are confirmed in `media_files` table with `is_synced_to_r2 = true`
- Pre-loads all synced paths into cache to avoid N+1 queries during batch replace
- Tracks `skipped_files` count and reports `completed_with_errors` when files are skipped
- Per-file regex-based replacement with individual logging

### Changed — MediaDiskService Upgrade
- Added `exists()`, `delete()`, `putUploadedFile()` methods for centralized storage operations
- Enhanced `normalizePath()` to strip `/storage/` prefix
- Added `isFullUrl()` utility
- Deprecated `configureR2Disk()` (now a no-op — `AppServiceProvider` handles R2 config globally at boot)

### Changed — R2 Storage Settings Tab Redesign
- Reorganized into 4 clear sections: Trạng thái R2, Thông tin kết nối, Cấu hình đồng bộ, URL Replace Config
- Added proper descriptions, helper texts, and danger warnings for destructive toggles
- 2-column layout for connection credentials
- R2 enabled toggle now has `->live()` for reactive UI feedback

### Changed — R2 Sync Manager Blade View
- Added `completed_with_errors` status badge (warning color)
- Scan completed panel shows file count and next-step guidance
- Warning banner with link to Settings when R2 is OFF

### Files Changed (9 files)
- `app/Filament/Pages/ManageSiteSettings.php` — Boolean cast fix + R2 tab redesign
- `app/Filament/Pages/R2SyncManager.php` — R2 guards + status handling
- `app/Filament/Resources/Products/RelationManagers/ProductDocumentsRelationManager.php` — media_url() fix
- `app/Jobs/SyncR2MediaJob.php` — URL generation + verification + logging
- `app/Services/Media/MediaDiskService.php` — Full service upgrade
- `app/Services/Media/MediaUrlReplaceService.php` — R2 existence verification
- `resources/views/filament/pages/r2-sync-manager.blade.php` — Status panel improvements
- `app/Console/Commands/MediaAudit.php` — **NEW** CLI audit tool
- `app/Console/Commands/MediaRepairPaths.php` — **NEW** CLI repair tool


## [1.3.0] - 2026-05-09

### Fixed — Critical: Fake CDN URL Bug
- **`media_url()` generated fake CDN URLs**: When R2 was enabled and a file didn't exist locally, the helper assumed it was on R2 and returned a CDN URL — but the file was never uploaded to R2. This caused all media (logos, product images, etc.) to show broken on frontend
- **Root cause**: `!file_exists($localFilePath)` was used as a proxy for "file is on R2" — this is fundamentally wrong. Replaced with strict check: only return CDN URL when `MediaFile.is_synced_to_r2 = true`
- Also removed auto-rewriting of `/storage/` URLs to CDN URLs for full URL inputs — this was another vector for generating invalid CDN links

### Fixed — Branding Uploads Ignored R2
- All 5 branding FileUpload components (`logo_image`, `logo_dark_image`, `logo_mobile_image`, `favicon`, `apple_touch_icon`) were hardcoded to `disk('public')`, bypassing R2 entirely even when enabled
- Now use `MediaDiskService::getUploadDisk()` for dynamic disk selection

### Fixed — All Resource Uploads Missing Disk
- FileUpload components across **all Resources** (Product, Brand, Post, CaseStudy, Category, Testimonial, User, ProductDocument) had no explicit disk set
- Filament falls back to `config('filesystems.default')` = `local` (private storage!) — files were uploaded but inaccessible via URL
- All now use `MediaDiskService::getUploadDisk()` for dynamic R2/local switching

### Changed — Import FK Validation
- `ProductImportHandler::validateRow()` now checks `brand_id` and `product_category_id` exist in DB before import
- `prepareData()` adds defensive null-set for missing FK IDs to prevent SQL constraint violations on production

### Files Changed (10 files)
- `app/Support/helpers.php` — Rewrote `media_url()` core logic
- `app/Filament/Pages/ManageSiteSettings.php` — 5 branding uploads
- `app/Filament/Resources/Products/Schemas/ProductForm.php` — 4 uploads
- `app/Filament/Resources/Brands/Schemas/BrandForm.php` — 1 upload
- `app/Filament/Resources/Posts/Schemas/PostForm.php` — 2 uploads
- `app/Filament/Resources/CaseStudies/Schemas/CaseStudyForm.php` — 3 uploads
- `app/Filament/Resources/ProductCategories/Schemas/ProductCategoryForm.php` — 1 upload
- `app/Filament/Resources/Testimonials/Schemas/TestimonialForm.php` — 2 uploads
- `app/Filament/Resources/Users/UserResource.php` — 1 upload
- `app/Filament/Resources/Products/RelationManagers/ProductDocumentsRelationManager.php` — 1 upload

## [1.2.0] - 2026-05-09

### Fixed — Critical Import Bugs
- **SoftDeletes slug collision**: `Product::where('slug')->exists()` skipped soft-deleted rows, but MySQL unique index still enforced them → all imports crashed with duplicate entry errors. Fixed by adding `withTrashed()` to all uniqueness checks (`ensureUniqueSlug`, `findExisting`, `validateRow`)
- **Cascade transaction failure**: Chunked `DB::beginTransaction()` meant one row's constraint violation invalidated the entire chunk (all 108 rows failed even though only 1 had an error). Replaced with per-row transaction isolation
- **CREATE mode missing guard**: CREATE mode didn't check if record already existed → blind `Product::create()` → crash. Now detects existing records and skips with clear error message suggesting UPSERT mode

### Improved — Import Preview Page (Production UI)
- Rebuilt with card-based architecture: Summary Stats → File Info → Error Table → Preview Table
- File info displayed as proper HTML table (Module / Tên file / Định dạng / Matching Key)
- Summary stat cards with colored accent bars (green/red/blue/yellow/purple) and `text-3xl` numbers
- Preview table: sticky header, zebra rows, hover highlight, monospace for model/sku codes
- Smart data resolution: `brand_id → "Gree"`, `category_id → "Điều hòa âm trần Cassette"`, booleans → badges, prices → formatted
- Footer info box with stat counters: **108** TỔNG DÒNG · **20** ĐANG PREVIEW · **88** CÒN LẠI
- Tooltip on truncated text (product name, slug)
- Error table with dot-list formatting and row badges

### Improved — Import Confirmation UX
- Confirm modal: warning icon (⚠), descriptive heading, bullet-point summary, "Hành động này không thể hoàn tác" notice
- Cancel button now requires confirmation: "Dữ liệu preview sẽ bị xóa. Bạn sẽ cần upload lại file."

### Improved — Data Transfer Dashboard
- Refactored from debug-style text list to professional admin dashboard
- 4 separate cards: Summary Stats (4-col grid) → Export Jobs table → Import Jobs table → Module Reference (collapsible)
- Export table: ID, Module badge, Format badge, Row count, Status badge, Creator, Timestamp, Download icon
- Import table: ID, Module badge, Filename (truncated + tooltip), Mode badge (CREATE/UPDATE/UPSERT), Total/OK/Error counts, Status badge, Timestamp, Action icon buttons
- Status badges: Hoàn thành (green), Lỗi (red), Preview (yellow), Đang import (blue), Chờ xử lý (gray)
- Action buttons: eye icon → preview, document icon → result, warning icon → error log
- Empty states with icon + descriptive message
- Responsive: mobile card stack, horizontal table scroll

### Changed
- `DataImportService::confirmImport()` — per-row transaction instead of chunked transaction
- `ProductImportHandler::ensureUniqueSlug()` — truncates slug to 200 chars, uses `withTrashed()`
- `ProductImportHandler::findExisting()` — includes soft-deleted records in all lookups
- `ProductImportHandler::validateRow()` — detects existing records in CREATE mode, warns about duplicate slugs

---

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
