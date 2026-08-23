# Release v1.26.0 — Admin UX and Information Architecture

Release date: 2026-08-23

Status: **RELEASE**

## Highlights

v1.26.0 consolidates the Filament administration experience into seven operator-oriented domains and replaces debug-first operational screens with concise, actionable interfaces. The release preserves all Product/catalog governance, RBAC, queue safety, and Phase 2–9 production-readiness guarantees.

## Navigation and daily workflows

- Bán hàng: leads, quotes, reviews, questions, and BTU consultations.
- Sản phẩm: Products, categories, brands, and promotions.
- Nội dung: posts, taxonomies, FAQ, case studies, testimonials, landing, home, and policy content.
- SEO & Marketing: SEO audit, integrations, internal links, redirects, and campaigns.
- AI Content: governed content jobs and provider configuration.
- Hệ thống: import/export, Media & CDN, email, users, roles, and website settings.
- Vận hành: queue, worker, and scheduler health.

## Screen improvements

- Dashboard prioritizes business KPIs and action-required items; technical health is compact and secondary.
- Import/Export retains its useful job history while using the shared Filament theme.
- Media & CDN separates routine actions from dangerous URL migration and keeps explicit permissions and confirmation.
- AI Queue Health explains desired versus actual worker state without treating an intentionally disabled worker as critical.
- AI Content Jobs summarizes workload and hides low-value runtime columns by default.
- Marketing Integrations presents readiness, missing configuration, capabilities, and technical values in separate layers.

## Performance

- Main Dashboard widget: 85 to 21 measured queries.
- System Health widget: 65 to 25 measured queries.
- Request-level snapshots remove duplicate stats and queue-health reads.

These are local populated-database diagnostics, not production latency certification.

## Security and data safety

- Server-side RBAC and confirmation gates remain authoritative.
- PHPUnit removes generated compiled config before application bootstrap, so test database overrides remain authoritative even after a release cache build.
- Product/catalog technical writes: 0.
- AI provider calls: 0.
- AI worker: `DISABLED_BY_OPERATOR`.
- Database baseline: 81 Products, 212 catalog sources, 36,453 catalog models, 656,507 catalog fields, and 90 migrations.
- BTU hash: `3e981c60fcadd3461746fd8f3b94855dc5205bad6c446c55c17066d40c47e3ba`.

## Validation

- Full suite: 326 tests, 1,053 assertions, zero failures/errors, one existing skipped test.
- Composer validate/audit: PASS.
- npm audit: 0 vulnerabilities.
- Vite production build: PASS.
- Config, route, and view cache: PASS.
- PHP lint and `git diff --check`: PASS.
- No Playwright/Dusk or authenticated CDP transport was available; no browser PASS is claimed.

During release verification, a test run executed after `config:cache` loaded the cached local MySQL connection instead of PHPUnit's SQLite override and emptied the current Product/catalog tables. Release processing stopped immediately. The empty state was backed up forensically, the verified Phase 9 backup was restored through `SafeRestorePayloadBuilder`, and exact counts, migration state, BTU hash, queue state, and backup SHA were revalidated. The PHPUnit bootstrap guard above was then added and proven against a deliberately compiled config cache before the final green suite.

## Release lineage

The existing annotated `v1.25.0` tag was not moved or overwritten. It points to an earlier tree whose canonical `VERSION` is `1.24.0`. Operator authorization selected `v1.26.0` as the next semantic minor release for this substantial, backward-compatible Admin UX feature set.

## Deployment requirements

Use production environment values, authoritative HTTPS, secure session cookies, protected secrets, correct database/storage/mail/cache/queue configuration, and a verified pre-deployment backup. Keep the AI worker disabled unless separately authorized and verify scheduler heartbeat after deployment.
