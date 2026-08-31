# v1.32.0

## Executive summary

Certified AI Product workflow release. Product Edit and Product bulk operations now share canonical draft, approval, warning, stale-target and Apply governance.

## Included

- Adds governed single-product and bulk Product AI operations with immutable selection snapshots and per-Product audit ledgers.
- Adds canonical action/readiness resolvers, warning classification, approval audit fields and bulk-operation ledger migrations.
- Keeps generated Product content in a reviewable draft until an authorized operator approves and explicitly confirms Apply.
- Enforces technical hard blocks, stale-target protection, allowlisted field application and idempotent Apply.
- Enforces `SINGLE_OPERATOR_CONTROLLED_ROLLOUT` server-side and in Product Edit action visibility; read-only preview remains available where authorized.
- Improves Product Edit and AI job UX: compact action hierarchy, readable warning/status presentation, preview-first content and secondary technical diagnostics.

## Validation

- Full PHPUnit: 514 tests, 513 passed, 1 skipped, 3,192 assertions, exit code 0.
- Single-product Playwright: 13 passed, 1 policy-superseded skipped, 0 failures.
- Real provider validation uses `custom / gemini-2.5-flash` through the governed `ai_governed` worker. No bulk retry was performed.
- Local worker before release: ENABLED, ONLINE, UP_TO_DATE, queue drained (0 pending, 0 processing, 0 stuck).

## Migration

Run `php artisan migrate --force`. This release adds draft review audit columns and Product bulk-operation ledger tables.

## Deployment

Deploy tag `v1.32.0` using [LIVE_DEPLOYMENT_RUNBOOK_1.32.0.md](LIVE_DEPLOYMENT_RUNBOOK_1.32.0.md). Preserve the operator's pre-deploy AI desired state. Do not bulk retry historical jobs or import SkyAir workbooks during code deployment.

## Rollback

Checkout `v1.31.5`, rebuild caches, restart managed workers, and verify the rollback worker uses the rollback code. Do not delete AI history, drafts, ledgers or Product rows.
