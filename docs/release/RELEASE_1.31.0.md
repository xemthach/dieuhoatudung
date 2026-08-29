# v1.31.0

## Executive Summary

This minor release adds governed, configurable public navigation and ships the audited catalog schema/import tooling for Daikin categories. It also closes the Product/filter/navigation and marketing/content browser certification gates. No SkyAir production import, AI provider call, or Product/catalog technical mutation was performed.

## Product Listing

- Preserves the existing Product listing and canonical media/category rendering contracts.
- Browser-certified Product listing, detail navigation and responsive navigation at 1366px and 390px.

## Product Filters

- Preserves category, brand, capacity, combined filter, sorting and pagination behavior.
- Sort URL handling is browser-certified after the `window.URL` compatibility fix.

## Product Categories

- Category navigation resolves from active/indexable Product category identity.
- Inactive, missing or non-indexable category targets fail closed and are not rendered as public dead links.

## Breadcrumbs

- Product breadcrumbs use the canonical Product category contract and do not inject an unrelated floor-standing category.

## Product Media

- Preserves the audited canonical Product media composition and safe fallback behavior.
- No media or catalog records were mutated by release certification.

## Public Navigation

The previous header used separate literal desktop/mobile links, including a legacy floor-standing category dependency. The release uses `PublicNavigationResolver` backed by existing `site_settings` JSON configuration.

Implemented capabilities:

- label and target configuration;
- allowlisted named routes;
- Product category targets stored by category identity;
- safe custom URL validation;
- deterministic `sort_order`;
- enable/disable without deleting configuration;
- shared logical source for desktop and mobile header rendering;
- fail-closed inactive-category behavior;
- existing settings cache invalidation on save.

## Navigation Administration

The existing Filament Website Settings page contains the public navigation repeater. Browser round-trip proves label edit, active category target, desktop/mobile reflection, reorder, disable and restore without manual cache clearing.

## Campaigns

Campaign production/preview, scheduling, image and video renderer behavior remains browser-certified using isolated fixtures. Preview does not create analytics events.

## Post Editor

RichEditor click, cursor, mouse selection, Vietnamese typing, delete, paste, toolbar operation, save and reload persistence are browser-certified. Unsafe synthetic HTML is sanitized.

## AI Post Workflow

The same Post record is retained through review/apply and repeated apply is idempotent. Certification used deterministic local persisted state only; provider calls were zero.

## Promotions

Banner, landing, popup, announcement and Promotion AI description/detailed-content flows remain browser-certified. Structured discount/date/scope facts remain unchanged.

## Technical Catalog Schemas

Daikin SkyAir and wall-mounted schema/import contracts, mapping services, validation scripts and regression tests are included. These are schema/tooling changes, not a production catalog import.

## Daikin Wall-Mounted Data Tooling

Wall-mounted technical schema and import-readiness artifacts are included for controlled review. No Product publication or pricing/stock invention is performed.

## Daikin SkyAir Tooling

SkyAir extraction, source/provenance QA, schema and operator-review tooling plus audit artifacts are included where classified for release. Operator/production workbooks and the production import manifest remain excluded local artifacts.

## SEO / Internal Links

- Internal-link suggestions and category links use validated active targets.
- No automatic Product reclassification was performed.

## Performance

- Navigation resolution uses bounded configuration and existing settings cache infrastructure.
- Browser and backend regression runs completed without introducing provider or Product writes.

## Security

- Named route destinations are allowlisted.
- Custom URLs reject executable schemes.
- Production/import workbooks, browser screenshots and temporary artifacts are excluded from Git staging.

## Database / Migrations

- Migration count remains 93; `migrate:status` reports all migrations ran and no pending migration.
- Release certification database baseline observed: Products 357 (182 active, 175 inactive), Categories 7, Brands 14, Catalog sources 212, Catalog models 36,453, Catalog model fields 656,507.
- No production import or Product/catalog technical writes were executed.

## Data Governance

- Active Products in inactive categories and active uncategorized Products remain known operator decisions; this release does not auto-fix them.
- SkyAir operator approval and production import remain separate controlled workflows.

## Browser Certification

- Combined Playwright release run: 9 passed, 0 failed, 0 skipped, one worker.
- Coverage: Product listing/filter/detail, desktop/mobile navigation, Admin navigation round-trip, Campaign, AI Post, RichEditor, Promotion and console/network checks.
- Local server: `http://127.0.0.1:8098`; Chrome 152; Playwright 1.62.1.
- Provider calls: 0.

## Automated Tests

- PHPUnit: 475 tests, 474 passed, 1 skipped, 2,948 assertions, 0 failures/errors.
- Composer validation/audit, npm audit, Vite build, Laravel cache compilation, PHP lint, migration status and `git diff --check`: PASS.

## AI Runtime

AI worker desired state was not changed and remains operator-controlled/disabled. This release does not enable a worker, call a provider, purge a queue or spawn a process from HTTP.

## Worker / Scheduler Deployment

Live deployment must capture the pre-deploy worker desired/actual state, safely drain/disable if required, deploy code, run required migrations/cache steps, restart the actual OS-managed worker, verify fresh heartbeat, `database` connection, `ai_governed` queue, release/build identity and scheduler health, then intentionally restore the prior desired state. A disabled worker must remain disabled after restart.

## Upgrade Instructions

1. Fetch the tagged release and verify the commit SHA.
2. Install locked PHP dependencies with `composer install --no-dev --prefer-dist --optimize-autoloader` as the application user.
3. Build frontend assets according to the repository deployment convention.
4. Run `php artisan migrate --force` only after backup and migration review; this release adds no migration.
5. Rebuild config/route/view caches.
6. Restart all long-running workers through the configured process manager.
7. Run the post-release health and non-provider worker checks.

## Known Limitations

- Browser evidence is local fixture evidence, not a live-server smoke test.
- Third-party YouTube availability is not certified.
- Existing taxonomy anomalies remain unchanged by design.
- SkyAir production import is not authorized or performed by this release.

## Rollback

Rollback must use the previous tagged application release, rebuild caches, restart the web/queue processes through the actual process manager, verify worker release identity and heartbeat, and preserve the operator's desired AI state. Do not delete Products by “last N rows”; catalog import rollback remains governed by its separate manifest/backup runbook.
