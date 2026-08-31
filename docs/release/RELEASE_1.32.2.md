# v1.32.2

## Executive summary

AI Product technical-grounding and warning-classification patch. Provider-visible facts now respect the canonical `use_for_ai` policy, while server-side validation retains complete source authority. Historical technical evidence is rechecked against current Product/catalog facts instead of remaining a permanent hard blocker.

## Fixed

- Separates the provider fact allowlist from the server-side validation registry.
- Validates ranges whose verified minimum and maximum are stored as separate facts.
- Reclassifies historical technical warnings against current Product authority.
- Removes or repairs unsupported rewritable claims before draft persistence, then sanitizes and validates again.
- Separates editorial warnings, optional-data notices, processed technical evidence, informational diagnostics and hard blockers.
- Prevents optional catalog gaps and successful diagnostics from blocking Approve or Apply.
- Keeps technical contradictions, authorization, stale-target, schema/parser and concurrency guards fail-closed.
- Fixes the missing `apply_mode` default-path error.
- Corrects affected Vietnamese Product AI labels and warning presentation.

## Runtime proof

- Controlled real-provider path: Product Edit -> `ai_governed` -> managed worker -> `custom / gemini-2.5-flash`.
- Job #1118 / Item #1289 / Draft #1101 reached `REVIEW_REQUIRED` with parser success and no critical validation errors.
- Provider-reported total usage: 6,564 tokens.
- Product content and catalog authority remained unchanged before explicit approval/Apply.
- Final local worker state: `ENABLED`, `ONLINE`, `UP_TO_DATE`; pending, processing and stuck counts all zero.

## Validation

- Focused suite: 222 tests, 221 passed, 1 skipped, 871 assertions, 0 failures/errors.
- Full PHPUnit: 528 tests, 527 passed, 1 skipped, 3,244 assertions, 0 failures/errors; exit code 0.
- Browser: 17 passed, 2 intentional skips, 0 failures; controlled real-provider browser test passed separately.
- Composer validation/audit, npm high audit, production build, Laravel cache builds, PHP lint and `git diff --check`: PASS.
- Migrations: 95 ran, 0 pending.

## Data and migration

- No new migration.
- No bulk retry and no historical AI evidence rewrite.
- Controlled local Products remain inactive audit fixtures.
- Legacy low-confidence mojibake rows remain preserved for explicit manual data review; no speculative database rewrite was performed.

## Deployment

Deploy tag `v1.32.2` using [LIVE_DEPLOYMENT_RUNBOOK_1.32.2.md](LIVE_DEPLOYMENT_RUNBOOK_1.32.2.md). Restart PHP/OPcache and all managed workers so web and worker load the same release. Preserve the operator's pre-deploy AI desired state and do not bulk retry historical jobs.

## Rollback

Checkout `v1.32.1`, reinstall matching dependencies, rebuild Laravel caches, restart PHP/OPcache and managed workers, then verify application/worker build parity and queue health. Preserve Product rows, drafts, jobs and request logs.

## Evidence

- Full audit: [AI_PRODUCT_LOCAL_LIVE_PARITY_FULL_AUDIT_REPORT.md](../reports/final/AI_PRODUCT_LOCAL_LIVE_PARITY_FULL_AUDIT_REPORT.md)
- Provider ledger: [ai_guard_real_provider_ledger.csv](../reports/final/artifacts/ai_guard_real_provider_ledger.csv)
- Runtime matrix: [ai_local_live_runtime_matrix.csv](../reports/final/artifacts/ai_local_live_runtime_matrix.csv)
- Guard matrix: [ai_local_live_guard_policy_matrix.csv](../reports/final/artifacts/ai_local_live_guard_policy_matrix.csv)
