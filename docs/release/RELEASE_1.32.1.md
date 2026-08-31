# v1.32.1

## Executive summary

AI Product guard and readiness patch release. The provider prompt allowlist and validator fact authority are now separate, preventing valid source-backed technical facts from being discarded during fact-checking.

## Included

- Adds a central, versioned AI guard policy with configurable editorial `BLOCK`, `WARN`, and `IGNORE` modes.
- Keeps authorization, technical safety, parser/schema integrity, stale/concurrency, duplicate and Apply guards locked fail-closed.
- Adds shared single/bulk generation preflight for Product eligibility, active conflicts, provider readiness and worker health.
- Stops known blockers before job creation/provider use and records sanitized preflight/terminal evidence.
- Corrects rated-capacity and capacity-range validation while retaining strict blocking for contradicted or unsupported technical claims.
- Persists structured critical validation errors so blocked job details show the actual hard blockers.

## Runtime proof

- Final real-provider path: `custom / gemini-2.5-flash` through `ai_governed`.
- Final controlled job reached `REVIEW_REQUIRED`; editorial length/Merchant findings remained warnings.
- Product content was unchanged before approval/Apply.
- Worker finished `ENABLED`, `ONLINE`, `UP_TO_DATE`, with 0 pending, 0 processing and 0 stuck jobs.

## Validation

- Focused suite: 162 tests, 161 passed, 1 skipped, 572 assertions, 0 failures/errors.
- Full PHPUnit: 525 tests, 524 passed, 1 skipped, 3,231 assertions, exit code 0.
- Browser: 14 passed, 1 intentional policy-superseded skip, 0 failures.
- Composer validation/audit, npm high audit, production build, Laravel caches, PHP lint and `git diff --check`: PASS.
- Migrations: 95 ran, 0 pending.

## Data and migration

- No new migration.
- No Product/catalog bulk mutation or historical retry.
- Controlled local provider fixtures remain inactive audit evidence.

## Deployment

Deploy tag `v1.32.1` using [LIVE_DEPLOYMENT_RUNBOOK_1.32.1.md](LIVE_DEPLOYMENT_RUNBOOK_1.32.1.md). Preserve the operator's pre-deploy AI desired state. Restart PHP/OPcache and all managed queue workers so web and worker load the same guard code.

## Rollback

Checkout `v1.32.0`, rebuild caches, restart PHP and managed workers, and verify version/build/queue alignment. Do not delete AI jobs, drafts, request logs or Product rows.

## Evidence

- Full audit: [AI_PRODUCT_GUARD_POLICY_FULL_AUDIT_REPORT.md](../reports/final/AI_PRODUCT_GUARD_POLICY_FULL_AUDIT_REPORT.md)
- Provider ledger: [ai_guard_real_provider_ledger.csv](../reports/final/artifacts/ai_guard_real_provider_ledger.csv)
