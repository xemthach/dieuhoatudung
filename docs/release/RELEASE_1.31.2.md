# v1.31.2

## Executive Summary

AI Product generation evidence hotfix. Provider output rejected by content validation is now retained as a sanitized failed draft with field-level and validation evidence.

## AI Product Pipeline

- Persists sanitized output, provider/model metadata, token usage, warnings and structured validation errors when `CONTENT_TOO_SHORT` occurs.
- Keeps Product content protected by draft-only/review controls.
- Aligns critical fact-check failures with canonical `BLOCKED` state.
- Adds generated-field coverage to the Filament job table and readable Vietnamese warning labels, while retaining raw warning codes in technical tooltips.

## Validation

- Focused AI Product tests: 39 passed, 134 assertions.
- Full PHPUnit: 478 tests, 477 passed, 1 skipped, 2,961 assertions, 0 failures/errors.
- Controlled real-provider probes used temporary local Products only. The provider returned short content naturally; draft/evidence persistence and worker cross-process behavior were verified.

## Data and Operations

- No migration, Product/catalog write, bulk retry, or production AI operation.
- Real-provider ledger: `docs/reports/final/artifacts/ai_product_real_provider_test_ledger.csv`.
- Full audit: `docs/reports/final/AI_PRODUCT_GENERATION_PIPELINE_FULL_AUDIT_REPORT.md`.

## Deployment

Deploy tag `v1.31.2`, rebuild Laravel caches, restart PHP/OPcache and Supervisor workers according to [LIVE_DEPLOYMENT_RUNBOOK_1.31.2.md](LIVE_DEPLOYMENT_RUNBOOK_1.31.2.md). Preserve the operator's pre-deploy AI desired state. Do not bulk retry historical jobs.

## Rollback

Checkout `v1.31.1`, rebuild caches, restart PHP and managed workers, then verify version/build and queue health. Do not delete AI history or Product rows.
