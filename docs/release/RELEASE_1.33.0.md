# v1.33.0

## Executive summary

AI Product lifecycle integrity and Product detail runtime reliability release. This release centralizes lineage, lifecycle transitions, cancellation, retry/recovery, and parent reconciliation while preserving historical evidence and existing safety controls.

## AI Product lifecycle

- Resolves active operations, actionable/approved drafts, history, blockers, next actions, and invariant violations through one canonical lineage resolver.
- Routes single Product, bulk, worker, monitoring, cancellation, retry, recovery, and regeneration through shared domain services.
- Prevents terminal history from blocking new generation and prevents queue retries from reopening terminal failed items.
- Checks cancellation before provider execution and at worker checkpoints, retains request evidence, releases runtime ownership, and reconciles parent state.
- Adds read-only integrity audit output for ghost active rows, orphan relationships, parent-child mismatches, duplicate actionable lineages, and dispatch correlation.

## Database

The additive migration `2026_08_31_000001_add_ai_product_lifecycle_integrity_columns` adds:

- cancellation audit fields to AI Product jobs and items;
- a nullable unique item `dispatch_uuid`;
- canonical Product/status and actionable-draft indexes.

It does not rewrite historical AI statuses or Product/catalog content.

## PRODUCT-DETAIL-001

Production logs on v1.32.2 showed repeated `number_format(): Argument #1 ($num) must be of type int|float, string given` failures on Product detail pages. The proven input was a legacy BTU range string (`24225.2 / 28660.8`) reaching Blade numeric formatting through the technical-fact resolver.

The fix validates and normalizes scalar/range BTU values at the resolver boundary, gives non-numeric values explicit fallback behavior, and applies the same contract to Product detail, cards, and quote modal. Monetary formatting now accepts only numeric primitives or plain decimal strings; formatted strings and business labels are not silently coerced.

Regression coverage includes integer, float, decimal DB strings, null, empty, formatted strings, business labels, range BTU, and the actual problematic Product shape. Browser proof covers the range Product, decimal price Product, and no-price fallback without HTTP 500 or JavaScript errors.

## Safety

- No bulk retry, catalog rewrite, historical evidence deletion, or direct status rewrite.
- Apply allowlists, stale-target checks, technical hard blocks, RBAC, and controlled rollout remain fail-closed.
- Provider-generated content remains draft-only until authorized approval and confirmed Apply.

## Validation

- Focused release suite: 93 tests, 93 passed, 412 assertions.
- Full PHPUnit: 546 tests, 545 passed, 1 skipped, 3,333 assertions, 0 failures/errors; exit code 0.
- Final deterministic Playwright matrix: 23 passed, 4 intentional skips for explicit Live/real-provider gates, and 0 outstanding failures. The prior controlled provider browser paths were not repeated solely for release packaging.
- Composer validation/audit: PASS, no advisories.
- npm high audit: PASS, 0 vulnerabilities; production Vite build: PASS.
- Laravel config/route/view caches, changed-PHP lint, secret signature scan, and `git diff --check`: PASS.
- Migrations: 96 ran, 0 pending locally.
- Read-only AI Product integrity audit: exit 0, 0 unknown violations; 21 legacy anomalies remain classified `KNOWN` without mutation.

## Deployment

Deploy tag `v1.33.0` with [LIVE_DEPLOYMENT_RUNBOOK_1.33.0.md](LIVE_DEPLOYMENT_RUNBOOK_1.33.0.md). Take a verified backup, ensure the governed queue is drained, run the additive migration, rebuild Laravel caches, and restart PHP/OPcache plus all managed workers. Do not bulk retry historical AI jobs.

## Rollback

Return to `v1.32.2`, reinstall matching dependencies, rebuild caches, and restart PHP/workers. The additive columns and indexes may safely remain during application rollback; do not run a destructive migration rollback or delete Product/AI evidence.

## Evidence

- [AI Product forensic audit](../reports/final/AI_PRODUCT_FULL_FORENSIC_AUDIT_REPORT.md)
- [Target architecture](../ai/AI_PRODUCT_TARGET_ARCHITECTURE.md)
- [Remediation plan](../ai/AI_PRODUCT_REMEDIATION_PLAN.md)
- [Product detail runtime gate](../reports/final/PRODUCT_DETAIL_NUMERIC_FORMATTING_PRE_RELEASE_GATE.md)
- [Master issue ledger](../reports/final/artifacts/AI_PRODUCT_MASTER_ISSUE_LEDGER.csv)
