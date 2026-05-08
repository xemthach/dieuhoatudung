# Changelog

All notable changes to this project will be documented in this file.

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
