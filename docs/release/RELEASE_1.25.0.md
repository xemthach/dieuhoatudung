# Release v1.25.0 — Production Release

Release date: 2026-08-23

Status: **RELEASE**

## Summary

v1.25.0 consolidates the completed Phase 2–9 roadmap: AI Product Content, Product/Catalog UX, search/filter/calculator, SEO/Merchant, performance, operations, security, and final recovery/readiness validation.

## AI Product Content

- Governed flow: read-only Product context → draft → human review → field approval → content-only apply.
- Allowlisted output: content HTML, descriptions, SEO title, meta description, and FAQ.
- Missing facts are omitted; conflicted facts are flagged or omitted.
- Product identity, capacity, technical specs, catalog facts, and provenance cannot be written by AI.
- Idempotency, rollback readiness, sanitizer, hard budget, RBAC, and single-operator controls are retained.
- AI worker remains `DISABLED_BY_OPERATOR`; provider calls during release validation: `0`.

## Product and Catalog UX

- Improved Product cards, detail pages, breadcrumbs, related Products, admin Product list, and form organization.
- Empty technical values are hidden instead of rendered as misleading placeholders.
- RAC marketing capacity and VRF source-native technical capacity retain separate semantics.
- Missing media uses a safe fallback; no Product images are invented.

## Search, Filter, and Calculator

- Exact model and prefix model ranking is deterministic.
- Documented case, whitespace, and separator normalization is supported without fuzzy identity substitution.
- Vietnamese normalized search preserves exact model priority.
- Query, filters, sorting, and pagination state are preserved in URLs.
- RAC BTU filtering is not mixed with VRF kW.
- Calculator validates boundaries and recommends RAC Products only; no implicit kW-to-BTU conversion.

## SEO, Structured Data, and Merchant

- Deterministic SEO title/meta precedence and fallback behavior.
- Canonical, robots, sitemap, breadcrumb, JSON-LD, FAQ, Open Graph, and search/filter indexation controls.
- Structured data emits only real Product facts; no invented price, stock, image, GTIN, MPN, warranty, ratings, or reviews.
- Merchant eligibility is deterministic; Products missing required data are excluded honestly.

## Performance and Operations

Populated-data query baselines were rechecked: listing about 20 cold/15 warm, search about 15, Merchant about 24, and Product detail about 44 cold/42 warm queries. Queue reconciliation preserves history, removes stale executable delivery safely, and leaves the AI worker disabled.

## Admin UX and information architecture

- Consolidated the Filament sidebar into seven operator-oriented groups: Bán hàng, Sản phẩm, Nội dung, SEO & Marketing, AI Content, Hệ thống, and Vận hành.
- Reworked Dashboard, Import/Export, Media & CDN, AI Queue Health, AI Content Jobs, and Marketing Integrations into compact summary-first screens with secondary technical detail.
- Preserved server-side RBAC and confirmation gates for recovery, synchronization, URL replacement, import/export, and integration actions.
- Reduced measured Dashboard widget queries from 85 to 21 and System Health queries from 65 to 25 by reusing request-level snapshots.

## Database integrity

- Products: `81`
- Catalog sources: `212`
- Catalog models: `36,453`
- Catalog fields: `656,507`
- Migrations: `90`
- BTU hash: `3e981c60fcadd3461746fd8f3b94855dc5205bad6c446c55c17066d40c47e3ba`

Verified local backup:

`storage/backups/phase9_pre_release_verified_20260823_082500.sql`

SHA-256: `A6EF06B7E3046A15852FABED08063411D2117676D67AD128AD4B6D4E4D26A70D`

The SQL backup is Git-ignored and must not be uploaded.

## Validation

| Check | Result |
|---|---|
| Full test suite with isolated SQLite test lifecycle | PASS — 326 tests, 1,052 assertions, 0 failures/errors, 1 existing skipped |
| Composer validate/audit | PASS |
| npm audit | PASS — 0 vulnerabilities |
| Vite production build | PASS |
| Config/route/view cache | PASS |
| PHP lint and `git diff --check` | PASS |
| Database counts and BTU hash after test run | PASS |
| Browser certification | Not available; no Playwright/Dusk harness |

The initial 2-failure/13-error result was traced to PHPUnit not overriding the local `.env` database configuration. A controlled test clone was used for the final proof; the production-like database was never used by the final test run.

The subsequent Admin UX release-candidate validation initially found two same-root Filament errors because navigation groups and their child items both declared icons. The configuration was corrected at the group level, the affected tests passed in isolation, and the complete suite then passed without skipping or deleting tests.

## Deployment

Set `APP_ENV=production`, `APP_DEBUG=false`, authoritative HTTPS `APP_URL`, `SESSION_SECURE_COOKIE=true`, protected `.env`, valid database/storage/mail/cache/queue settings, and a verified pre-deployment backup. Run:

```powershell
composer install --no-dev --prefer-dist --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Verify scheduler heartbeat after deployment. Enable the AI worker only through explicit operator authorization.

## Known non-blocking limitations

Historical missing media, 26 mojibake records, technical category-schema mismatches, unavailable browser harness, and scheduler heartbeat verification remain documented operational/data backlogs.

## Release decision

The code and release validation are green for `v1.25.0`. Publication is not complete: the existing annotated `v1.25.0` tag points to an earlier commit whose canonical `VERSION` file contains `1.24.0`. The tag must not be moved or overwritten; release publication requires an explicit version/tag decision.
