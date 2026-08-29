# v1.31.3

## Executive Summary

AI Product generation quality follow-up. A provider response below the configured content threshold receives one controlled content-only recovery attempt. Review scoring and warnings now use generated draft evidence rather than stale Product fields.

## Fixed

- The 800-word normal / 1200-word commercial validation thresholds remain unchanged.
- Successful recovery proceeds to review with combined token usage.
- If recovery also fails, the system keeps FAILED status with sanitized payload, draft and validation evidence.
- Draft `score_after` and warning checks evaluate generated payload evidence, avoiding false `missing_content`, `missing_seo`, `missing_faq` warnings from an unchanged Product row.

## Safety and Operations

- Recovery runs only for `CONTENT_TOO_SHORT`, at most once per generation.
- No historical jobs were retried and no bulk generation was executed.
- Product content remains draft-only until explicit review/apply.

## Validation

- Focused AI Product tests: 41 passed, 142 assertions.
- Real worker probe reached `needs_review` with a persisted draft; temporary fixture was removed afterward.
- Previous full-suite baseline: 478 tests, 477 passed, 1 skipped, 2,961 assertions, 0 failures/errors.

## Deployment

Deploy `v1.31.3` and restart PHP/OPcache plus Supervisor workers using [LIVE_DEPLOYMENT_RUNBOOK_1.31.3.md](LIVE_DEPLOYMENT_RUNBOOK_1.31.3.md). Do not bulk retry historical AI jobs.

## Rollback

Checkout `v1.31.2`, rebuild caches, restart PHP and managed workers, then verify version/build and queue health. Do not delete AI history or Product rows.
