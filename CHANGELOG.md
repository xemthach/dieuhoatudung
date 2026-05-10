# Changelog

All notable changes to this project will be documented in this file.

## [1.8.1] - 2026-05-10

### Fixed
- **Search API 500 on MySQL 5.7/MariaDB** — `JSON_EXTRACT(specs_json, '$[*].value')` uses `$[*]` wildcard which requires MySQL 8.0+. Replaced with `CAST(specs_json AS CHAR)` + `LIKE` for universal MySQL/MariaDB compatibility. Also added `whereNotNull('specs_json')` guard to prevent NULL column crashes

### Files Changed (2 files)
- `app/Services/Search/ProductSearchService.php` — MySQL-compatible JSON search fallback
- `VERSION` — 1.8.0 → 1.8.1

## [1.8.0] - 2026-05-10

### Added
- **Homepage Search Section** — Extracted search box from Hero Slider into independent `<x-home.homepage-search />` component. Search renders immediately on page load, unaffected by slide animations. New `homepage` variant added to `search-box.blade.php` with opaque white input and proper z-index for autocomplete dropdown
- **Compare Bar AJAX State Sync** — Rewrote `compare-bar.blade.php` to use server-session as single source of truth. All add/remove/clear operations now return full `items[]` array from server. Replaced browser `alert()` with modern toast notifications. `localStorage` demoted to transient UX cache
- **Admin CTA Mobile Bar Controls** — Added 3 toggle switches in Admin > Site Settings > CTA tab: `mobile_bar_call_enabled`, `mobile_bar_zalo_enabled`, `mobile_bar_quote_enabled`. Each mobile sticky bar button can now be individually toggled on/off
- **Hotline Display Setting** — Added `contact.hotline_display` setting for formatted phone display (e.g., "0909.123.456") separate from raw `tel:` number

### Fixed
- **Empty `tel:` links** — Homepage CTA section and mobile sticky bar had `href="tel:"` with no phone number. Now uses `setting('contact.hotline')` with conditional rendering — link hidden entirely if no hotline configured
- **405 MethodNotAllowed on GET /so-sanh-san-pham/remove** — Added GET fallback routes for `/add`, `/remove`, `/clear` that redirect to compare index instead of showing raw Symfony exception page. POST-only mutation routes remain unchanged
- **Wrong Zalo setting key** — `case-studies/index.blade.php` used non-existent `setting('contact.zalo')` with hardcoded `https://zalo.me/` fallback. Corrected to `setting('contact.zalo_link')`
- **Hardcoded phone fallback `0900000000`** — Removed fake phone number fallback from case studies CTA. Now uses actual admin-configured hotline

### Changed
- **Contact/CTA Dynamic Settings** — Refactored `sticky-cta.blade.php` (full rewrite), `home.blade.php` CTA section, and `case-studies/index.blade.php` CTA to use `setting()` helper for all phone numbers, Zalo links, and CTA labels. Zero hardcoded contact info remains in frontend
- **CSRF Expiry Notification** — Replaced `alert('Phiên làm việc đã hết hạn')` with animated toast notification that auto-dismisses before page reload
- **Responsive CSS Overhaul** — Added `overflow-x: hidden` global fix, hero slider overflow containment via `:has([x-data*="heroSlider"])`, `.scrollbar-none` utility, compare bar z-index layering (z-9998), sticky CTA auto-hide when compare bar visible, iOS safe area padding
- **Hero Slider Mobile Typography** — Reduced h1 from `text-3xl` to `text-2xl` on mobile, `sm:text-4xl` to `sm:text-3xl` on tablet to prevent text overflow
- **Search Box Mobile Optimization** — Added responsive padding (`pr-24` mobile → `pr-36` desktop), split button text ("Tìm" mobile / "Tìm sản phẩm" desktop)
- **Admin CTA Tab Reorganization** — Restructured CTA settings into 3 sections: CTA Buttons (2-column layout), Mobile Bottom Bar (3 toggles), and Hotline Display

### Security
- **Compare routes enforce POST** — All mutation routes (`/add`, `/remove`, `/clear`) remain POST-only with CSRF protection. GET requests gracefully redirect instead of exposing stack traces

### Files Changed (17 files)
- `app/Http/Controllers/CompareController.php` — `remove()` returns full `items[]`, accepts `product_id` alias, `clear()` returns `items: []`
- `app/Filament/Pages/ManageSiteSettings.php` — CTA tab expanded with mobile bar toggles and hotline_display
- `resources/css/app.css` — +58 lines: responsive overflow fixes, z-index layering, iOS safe area, scrollbar utility
- `resources/js/app.js` — CSRF 419 toast notification replaces `alert()`
- `resources/views/components/compare-bar.blade.php` — Full rewrite: AJAX state sync, toast notifications, mobile-optimized
- `resources/views/components/home/hero-slider.blade.php` — Removed search box, reduced mobile font sizes
- `resources/views/components/home/homepage-search.blade.php` — **NEW** — Independent search section component
- `resources/views/components/search-box.blade.php` — Added `homepage` variant, mobile-responsive padding/button
- `resources/views/pages/home.blade.php` — Added homepage-search component, dynamic CTA settings
- `resources/views/pages/case-studies/index.blade.php` — Fixed Zalo key, removed hardcoded fallbacks
- `resources/views/partials/sticky-cta.blade.php` — Full rewrite: dynamic settings + per-button toggles
- `routes/web.php` — Added GET fallback redirects for compare mutation routes
- `.gitignore` — Added `/antigravity` and `/antigravity.pub`
- `VERSION` — 1.7.1 → 1.8.0
- `public/build/assets/app-*.css` — Rebuilt
- `public/build/assets/app-*.js` — Rebuilt
- `public/build/manifest.json` — Updated asset hashes

## [1.7.1] - 2026-05-10

### Fixed — BTU Calculator Audit (4 issues)
- **Showroom W/m² label mismatch** — Frontend select displayed "900 W/m²" while service used correct 300 W/m². Calculation was correct, but UI was misleading
- **`energy_rating` sort pushed NULLs first** — When user selected "Tiết kiệm điện" priority, products without energy rating appeared first. Now NULLs sort to end via `$p->energy_rating ?? 999`
- **Landing page Quick BTU widget used flat 600 BTU/m²** — Replaced with accurate W/m²-based calculation using the same 27 space types as the main calculator, with Alpine.js reactive dropdown, real BTU tier rounding, and HP/W/m² display

### Changed — BTU Calculator Architecture
- **Hardcoded `<option>` tags → dynamic rendering** — Replaced 44 hardcoded `<option>` lines with `BtuCalculatorService::spaceTypeGrouped()` loop. Adding/editing space types in the service now auto-updates all UIs
- **Added `group` field to cooling load table** — Each of the 27 space types now has a `group` key (Nhà ở, Văn phòng, Thương mại, F&B, etc.) for `<optgroup>` rendering
- **Added `getCoolingLoad()` method** — Public accessor for W/m² values, used by landing page widget for client-side JS calculation
- **Added 3 missing VN market BTU tiers** — 30,000 (3.3HP), 42,000 (4.7HP), 45,000 (5.0HP) with corresponding area ranges
- **Updated `btuToAreaRange` map** — Added area ranges for new tiers: 30K→38-52m², 42K→55-72m², 45K→58-78m²

### Files Changed (5 files)
- `app/Services/Calculator/BtuCalculatorService.php` — +group field, +getCoolingLoad(), +spaceTypeGrouped(), +3 tiers, energy_rating fix
- `resources/views/components/btu-calculator.blade.php` — Dynamic options from service, Showroom label fix
- `resources/views/landing/sections/advisory_content.blade.php` — Full widget rewrite with W/m² accuracy
- `public/build/manifest.json` — Updated asset hashes
- `VERSION` — 1.7.0 → 1.7.1

---

## [1.7.0] - 2026-05-10

### Added — Product Search Module
- **`SearchController`** (`app/Http/Controllers/SearchController.php`) — Autocomplete API + full search results page
- **`ProductSearchService`** (`app/Services/Search/ProductSearchService.php`) — Full-text search across product name, model, SKU, brand, category, BTU capacity with relevance scoring
- **Search box component** (`resources/views/components/search-box.blade.php`) — Reusable component with `hero` and `inline` variants, Alpine.js autocomplete with debounce 300ms
- **Search results page** (`/tim-kiem`) — Full results page with product cards, pagination, query highlighting
- **Search suggest API** (`/api/search/suggest`) — JSON autocomplete endpoint with throttle (30/min)
- **Inline search** added to `/san-pham` and category listing pages
- **SEO schema** — Updated `SearchAction` URL template from `/san-pham?q=` to `/tim-kiem?q=`
- Search logs table (`search_logs`) for analytics

### Added — Hero Slider CMS Module
- **`HeroSlide` model** (`app/Models/HeroSlide.php`) — Supports gradient, color, image, video, embed background types with overlay control
- **`HeroSlideResource`** — Full Filament CRUD under "Landing & Pages → Hero Slider" with 5-tab form (Nội dung, Background, CTA, Hiệu ứng, Trạng thái)
- **Hero slider component** (`resources/views/components/home/hero-slider.blade.php`) — Alpine.js carousel with autoplay, pause-on-hover, dot navigation, arrow controls, text animations (fade, slide-up, slide-left, zoom-in)
- Drag-to-reorder slides, duplicate, per-slide toggle
- CTA buttons with configurable URL, text, style (accent/outline)
- Media upload via `MediaDiskService` (R2/local)
- Fallback to static hero when no active slides exist
- **`HeroSlideSeeder`** — Seeds default slide matching original static hero
- Search box renders on **all slides** (not just first)

### Added — Home Benefits CMS Module
- **`HomeBenefitItem` model** (`app/Models/HomeBenefitItem.php`) — Supports heroicon (whitelist), image upload, custom SVG with sanitization
- **`HomeBenefitItemResource`** — Full Filament CRUD under "Landing & Pages → Home Benefits" with drag reorder
- **Benefit bar component** (`resources/views/components/home/benefit-bar.blade.php`) — Dynamic rendering with icon type switching, color presets, fallback to original 4 hardcoded items
- 7 whitelisted icon names: shield-check, zap, clock, badge-dollar-sign, truck, wrench, check-circle
- SVG sanitization (strips script tags, event handlers, javascript: URLs)
- **`HomeBenefitItemSeeder`** — Seeds 4 default benefit items

### Added — Quote Commitments CMS Module
- **`QuoteCommitmentBlock` + `QuoteCommitmentItem` models** — Parent-child relationship with cascade delete
- **`QuoteCommitmentBlockResource`** — Full Filament CRUD under "Landing & Pages → Quote Commitments" with Repeater for items (reorderable, collapsible, cloneable)
- **Commitment block component** (`resources/views/components/quote/commitment-block.blade.php`) — Sidebar widget on `/bao-gia`, loads first active block with active items
- 9 whitelisted icons: settings, file-text, map-pin, wrench, shield-check, check-circle, badge-dollar-sign, clock, phone
- Block-level toggle: when OFF → fallback content displays
- Per-item toggle + sort order control
- **`QuoteCommitmentSeeder`** — Seeds 1 block with 5 professional HVAC commitment items

### Fixed — Double HTML Escape (`&amp;` Bug)
- **`{{ e($var) }}`** in Blade caused double-encoding — Blade `{{ }}` already calls `htmlspecialchars()`, wrapping with `e()` encodes `&` → `&amp;amp;`
- Removed redundant `e()` calls in `benefit-bar.blade.php` (3 locations) and `commitment-block.blade.php` (3 locations)

### Changed — Homepage Architecture
- Replaced 42-line hardcoded Trust Badges section with `<x-home.benefit-bar />` component
- Replaced 33-line hardcoded Hero section with `<x-home.hero-slider />` component
- Homepage `home.blade.php` reduced from 198 to 126 lines

### Changed — Quote Page
- Replaced 11-line hardcoded "Cam kết của chúng tôi" block with `<x-quote.commitment-block />` component

### Routes Added (2)
- `GET /api/search/suggest` → `search.suggest` (throttle: 30/min)
- `GET /tim-kiem` → `search.index` (throttle: 60/min)

### Migrations (4)
- `2026_05_10_110000_create_search_logs_table`
- `2026_05_10_114600_create_hero_slides_table`
- `2026_05_10_123300_create_home_benefit_items_table`
- `2026_05_10_125000_create_quote_commitment_tables` (2 tables)

### Files Changed (45 total: 37 new + 8 modified)

**New Files (37)**
- `app/Http/Controllers/SearchController.php`
- `app/Services/Search/ProductSearchService.php`
- `app/Models/HeroSlide.php`, `HomeBenefitItem.php`, `QuoteCommitmentBlock.php`, `QuoteCommitmentItem.php`
- `app/Filament/Resources/HeroSlides/` (6 files)
- `app/Filament/Resources/HomeBenefitItems/` (6 files)
- `app/Filament/Resources/QuoteCommitments/` (6 files)
- `database/migrations/` (4 files)
- `database/seeders/` (3 files)
- `resources/views/components/search-box.blade.php`
- `resources/views/components/home/hero-slider.blade.php`, `benefit-bar.blade.php`
- `resources/views/components/quote/commitment-block.blade.php`
- `resources/views/pages/search.blade.php`
- `public/build/assets/app-DdT-N7Am.css`

**Modified Files (8)**
- `routes/web.php` — +2 search routes
- `resources/views/pages/home.blade.php` — Hero + Benefit Bar → components
- `resources/views/pages/quote.blade.php` — Commitment block → component
- `resources/views/products/index.blade.php` — +inline search
- `resources/views/products/category.blade.php` — +inline search
- `resources/views/components/layouts/app.blade.php` — SearchAction URL fix
- `public/build/manifest.json` — Updated asset hashes
- `VERSION` — 1.6.1 → 1.7.0

---

## [1.6.1] - 2026-05-10

### Fixed — PDF Export Permission Denied on Production
- **`mkdir(): Permission denied`** — mPDF's `Cache` class attempted to create `storage/app/mpdf-tmp` at runtime, but on production servers the web server user (www-data/nginx) may lack permission to create directories under `storage/app/`
- Fix: `exportPdf()` now explicitly creates the temp directory with `mkdir($tempDir, 0775, true)` before mPDF instantiation
- Added **fallback to `sys_get_temp_dir()`** — if `storage/app/mpdf-tmp` is not writable (e.g., restrictive hosting), mPDF falls back to the system temp directory
- Removed unused `ConfigVariables` and `FontVariables` imports

### Files Changed (2 files)
- `app/Services/Product/ProductComparisonService.php` — Temp dir creation + fallback logic
- `VERSION` — 1.6.0 → 1.6.1

---

## [1.6.0] - 2026-05-10

### Added — Product Comparison Module Upgrade
- **`ProductComparisonService`** (`app/Services/Product/ProductComparisonService.php`) — New central service that builds the full comparison matrix from both standard DB columns AND all `specs_json` extra specs
- **9 HVAC-domain groups**: Thông tin chung, Công suất & Hiệu suất, Điện & Môi chất lạnh, Dàn lạnh, Mặt nạ (Panel), Dàn nóng, Lắp đặt, Nguồn dữ liệu, Thông số khác
- **60+ spec fields** compared (up from 12 hardcoded fields) — includes EER/COP, rated current, refrigerant charge, indoor/outdoor packaging, panel specs, pipe dimensions, installation limits
- **Auto-collects ungrouped specs** — any extra key in `specs_json` not in predefined groups appears under "Thông số khác" (no specs lost)
- **Diff highlighting** — values that differ across products are highlighted in amber for easy identification

### Added — Export Comparison Data
- **PDF export** (`/so-sanh-san-pham/export/pdf`) — A4 landscape, mPDF with DejaVu Sans font for full Vietnamese diacritics support, grouped sections, diff highlighting, footer with site domain, multi-page support
- **Excel export** (`/so-sanh-san-pham/export/excel`) — XLSX with frozen panes (B2), indigo group headers, amber diff cells, auto column width, sheet name "So sánh sản phẩm"
- **CSV export** (`/so-sanh-san-pham/export/csv`) — UTF-8 BOM for Excel compatibility, comma delimiter, full Vietnamese character support
- **`ProductComparisonExport`** (`app/Exports/ProductComparisonExport.php`) — Maatwebsite Excel export class with professional styling

### Added — Compare Page UX Improvements
- **Export dropdown button** — "Xuất dữ liệu" dropdown with PDF/Excel/CSV options with animated transition
- **Sticky first column** — Label column stays visible when scrolling horizontally on wide tables
- **Color-coded group headers** — Each HVAC domain has a unique pastel color (slate, blue, amber, cyan, violet, orange, emerald, gray)
- **Text truncation with tooltip** — Long values truncate with hover-to-expand behavior
- **Mobile scroll hint** — Animated arrow indicator for horizontal scroll on mobile/tablet
- **Transition animations** — Smooth hover effects on spec rows

### Changed — CompareController Refactored
- Injected `ProductComparisonService` via constructor DI (replaced inline DB queries and `ProductCompareSpecService` usage)
- View variable renamed: `$compareRows` → `$groupedSpecs` (grouped by HVAC domain instead of flat basic/technical/physical)
- Added 3 export endpoints: `exportPdf()`, `exportExcel()`, `exportCsv()` with `resolveExportProducts()` helper
- Products fetched via service with `brand` + `category` eager loading (was only loading `brand`)

### Changed — Compare Blade View Rewritten
- Replaced hardcoded `$row()` PHP closure with dynamic `@foreach` loop over grouped specs
- Group headers rendered from service data instead of static HTML blocks
- Product values escaped with `{{ }}` instead of mixed `{!! !!}` / `htmlspecialchars()` — eliminated raw HTML injection risk from `stock_status` and `inverter` fields
- Added responsive scrollbar styling via `<style>` block

### Changed — ProductSpecLabel
- Added `source_table` → `'Bảng catalogue'` label mapping

### Changed — .gitignore
- Added `/storage/app/mpdf-tmp` to prevent mPDF runtime cache from being committed

### Dependencies
- Added `mpdf/mpdf` (^8.3) — PDF generation with full Unicode/Vietnamese support via DejaVu Sans font

### Routes Added (3)
- `GET /so-sanh-san-pham/export/pdf` → `compare.export.pdf`
- `GET /so-sanh-san-pham/export/excel` → `compare.export.excel`
- `GET /so-sanh-san-pham/export/csv` → `compare.export.csv`

### Files Changed (10 files)
- `app/Services/Product/ProductComparisonService.php` — **NEW** (377 lines)
- `app/Exports/ProductComparisonExport.php` — **NEW** (157 lines)
- `app/Http/Controllers/CompareController.php` — Refactored with DI + export methods
- `resources/views/pages/compare.blade.php` — Full rewrite with grouped specs + UX
- `routes/web.php` — +3 export routes
- `app/Support/ProductSpecLabel.php` — +1 label
- `.gitignore` — +1 mpdf-tmp exclusion
- `composer.json` — +mpdf/mpdf dependency
- `composer.lock` — Updated lock file
- `public/build/` — Rebuilt production assets (new CSS hash)

---

## [1.5.1] - 2026-05-10

### Added — ProductSpecLabel Mapping System
- **`ProductSpecLabel`** (`app/Support/ProductSpecLabel.php`) — Central mapping of 89 HVAC spec keys to Vietnamese display labels
- **100% coverage** of all spec keys in DB — no raw snake_case keys visible to users
- 10 spec groups for organized frontend display: Hiệu suất năng lượng, Công suất & Điện năng, Dàn lạnh, Mặt nạ, Dàn nóng, Đường ống lắp đặt, Gas lạnh, Kích thước & Đóng gói, Vận hành, Solar/Inverter
- Auto-formatting: adds units (mm, kg, dB, m), cleans pipe inch notation, normalizes dimension separators
- Fallback `humanize()` for any future unmapped keys — always produces readable labels
- Hidden metadata keys (`source_catalogue`, `source_page`, `indoor_model`, `outdoor_model`) excluded from frontend display

### Changed — Frontend Product Spec Table
- Specs now grouped with colored section headers (`bg-primary-50`) instead of flat list
- All spec labels display in Vietnamese with proper HVAC terminology
- Values auto-formatted with correct units (kg, mm, dB(A), m)

### Changed — Admin Repeater UX
- Key input now has `datalist` autocomplete with all mapped key suggestions
- Live `hint()` shows Vietnamese label preview while typing
- Collapsed view with `itemLabel` showing "Label: Value" for each spec entry
- Improved helper text guidance

### Files Changed (5 files)
- `app/Support/ProductSpecLabel.php` — **NEW** (351 lines)
- `resources/views/products/show.blade.php` — Grouped spec display
- `app/Filament/Resources/Products/Schemas/ProductForm.php` — Repeater UX improvements
- `public/build/manifest.json` — Updated asset hashes
- `public/build/assets/app-Ct6c5dpS.css` — Rebuilt production CSS

---

## [1.5.0] - 2026-05-10

### Added — Product Import Mapper Service
- **`ProductImportMapper`** (`app/Services/Product/ProductImportMapper.php`) — Central mapping layer that routes import keys to dedicated DB columns and isolates unknown fields into `specs_json`
- 30+ import key aliases mapped to 15 standard DB columns (e.g. `capacity_btu` → `btu`, `power_input_kw` → `power_consumption`, `phase` → `voltage`)
- Metadata exclusion list prevents product identity fields (name, slug, brand_id) from leaking into JSON specs
- `castValue()` — Type-safe casting per column (int for BTU, float for kW/HP, boolean for inverter, enum normalization for cooling_type)
- `flattenSpecs()` / `toRepeaterFormat()` — Bidirectional conversion between Filament Repeater format and flat key-value

### Added — `product:clean-specs` CLI Command
- `php artisan product:clean-specs` — Migrates standard fields from `specs_json` back to dedicated DB columns, removes metadata keys, deduplicates
- `--dry-run` mode for safe preview
- Normalizes `cooling_type` values: `"1 chiều"` → `"1_chieu"`, `"2 chiều"` → `"2_chieu"`

### Added — `product:audit-catalogue-specs` CLI Command
- `php artisan product:audit-catalogue-specs` — Audits all products for spec coverage, detects misplaced standard fields in JSON, duplicate spec keys, and critical missing data
- `--fix` flag to auto-repair: moves standard fields from JSON to columns, deduplicates, removes metadata
- Reports: total standard fields filled, extra specs count, low coverage products, critical missing

### Added — Database Schema: `capacity_kw` and `hp` Columns
- New columns `capacity_kw` (decimal 8,2) and `hp` (decimal 5,1) on `products` table
- Migration includes data normalization: `cooling_type` enum values standardized to `1_chieu` / `2_chieu`
- Reversible migration with proper `down()` method

### Changed — ProductForm Technical Specs Tab
- Added `capacity_kw` (Công suất kW) and `hp` (Mã lực HP) input fields to the technical specifications section
- Renamed specs_json Repeater label from `"Thông số kỹ thuật mở rộng (JSON)"` to `"Thông số kỹ thuật mở rộng"` with helper text explaining usage
- Repeater now shows guidance: "Chỉ thêm thông số KHÔNG có field chuẩn ở trên"

### Changed — Product Model Casts
- Added explicit casts: `btu` → `integer`, `capacity_kw` → `decimal:2`, `hp` → `decimal:1`
- Ensures consistent type handling across Filament forms, API responses, and import pipeline

### DevOps — Build Assets
- Rebuilt production CSS (`public/build/assets/app-B21MSLaX.css`)
- Updated `public/build/manifest.json` with new asset hashes

### Files Changed (8 files)
- `app/Services/Product/ProductImportMapper.php` — **NEW** (287 lines)
- `app/Console/Commands/CleanProductSpecs.php` — **NEW** (105 lines)
- `app/Console/Commands/AuditCatalogueSpecs.php` — **NEW** (148 lines)
- `database/migrations/2026_05_09_185109_add_capacity_kw_and_hp_to_products_table.php` — **NEW** (38 lines)
- `app/Filament/Resources/Products/Schemas/ProductForm.php` — Added kW/HP fields + repeater label
- `app/Models/Product.php` — Added casts for new columns
- `public/build/manifest.json` — Updated asset hashes
- `public/build/assets/app-B21MSLaX.css` — Rebuilt production CSS

---

## [1.4.1] - 2026-05-09

### Fixed — Critical: R2 Upload Silent Failures
- **R2 disk `throw => false`** — Upload failures were silently swallowed, returning `false` instead of throwing exceptions. Changed to `throw => true` on both `r2` and `public` disks in `config/filesystems.php`
- **R2 disk `use_path_style_endpoint => false`** — Cloudflare R2 requires path-style endpoints. Without this, S3 requests fail silently. Changed to `true`
- **`AppServiceProvider` missing R2 runtime config** — Runtime R2 config override (from DB settings) was missing `use_path_style_endpoint` and `throw` keys → requests defaulted to virtual-hosted style which R2 doesn't support

### Fixed — PostForm Crash: Non-existent Config Key
- `PostForm.php` used `config('media.directories.images')` — this key **did not exist** in `config/media.php`, returning `null` → FileUpload directory was empty, files uploaded to root
- Cover image and RichEditor attachments both affected
- Fixed: changed to `config('media.folders.blog')` which is the correct existing key
- Added `directories.images` backward-compat alias in `config/media.php` for safety

### Fixed — RichEditor Disk Bypass
- `PostForm.php` and `ProductForm.php` RichEditor components used `config('media.disk')` (static `.env` value) instead of `MediaDiskService::getUploadDisk()` (dynamic R2-aware)
- When R2 enabled via admin, RichEditor inline images still uploaded to local `public` disk
- Now uses dynamic closure: `fn () => app(MediaDiskService::class)->getUploadDisk()`

### Fixed — ProductReviewForm Deprecated Disk Pattern
- Used `configureR2Disk()` (deprecated no-op) and captured disk into static `$disk` variable at form construction time
- If R2 state changed after form was built, the old disk name persisted until next request
- Replaced with dynamic closure matching all other upload fields in the system

### Changed — MediaDiskService Config Validation
- `getUploadDisk()` now validates R2 credentials (key, secret, bucket, endpoint) before returning `'r2'`
- If R2 enabled but config incomplete → falls back to `'public'` with warning log instead of returning `'r2'` that will crash on first upload
- New method: `r2ConfigValid()` — checks all 4 required R2 credentials exist
- `putUploadedFile()` now **throws `RuntimeException`** on failure instead of returning `false` — prevents saving empty/fake paths to DB

### Changed — `media_url()` Direct R2 Upload Support
- Previously only returned CDN URL if file existed in `media_files` table with `is_synced_to_r2 = true`
- Files uploaded directly to R2 via Filament FileUpload (bypassing sync flow) had no `media_files` record → always fell back to local URL
- Now also checks `Storage::disk('r2')->exists()` as fallback when sync record doesn't exist
- Added `media_disk()` helper function — shorthand for `MediaDiskService::getUploadDisk()`

### DevOps — Build Assets
- Updated `public/build/` CSS assets — previous commit had stale CSS causing MIME type errors on production

### Files Changed (10 files)
- `config/filesystems.php` — `throw => true`, `use_path_style_endpoint => true`
- `config/media.php` — Added `directories.images` backward compat key
- `app/Services/Media/MediaDiskService.php` — Config validation, throw on failure, new methods
- `app/Support/helpers.php` — `media_url()` R2 direct check, `media_disk()` helper
- `app/Providers/AppServiceProvider.php` — R2 `use_path_style_endpoint` + `throw`
- `app/Filament/Resources/ProductReviews/Schemas/ProductReviewForm.php` — Dynamic disk closure
- `app/Filament/Resources/Posts/Schemas/PostForm.php` — Dynamic disk + correct config key
- `app/Filament/Resources/Products/Schemas/ProductForm.php` — Dynamic RichEditor disk
- `public/build/manifest.json` — Updated asset hashes
- `public/build/assets/app-*.css` — Rebuilt production CSS

---

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
