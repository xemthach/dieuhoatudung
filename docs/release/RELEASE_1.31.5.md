# v1.31.5

## Executive Summary

AI Product operations workflow release. Operators can now reject reviewable drafts with a reason and release queued, processing, or stuck requests directly from Product Edit.

## AI Product Operations

- Added `Từ chối draft AI` with mandatory review note.
- Added `Giải phóng yêu cầu AI đang treo` for the current Product.
- Cancellation preserves AI history and Product content, records a canonical operator reason, and closes the parent job when appropriate.
- Duplicate protection remains active; this release does not bypass `DUPLICATE_IN_PROGRESS`.

## Safety

- No direct status editing, bulk retry, provider policy change, or automatic Product content Apply.
- Existing historical jobs and drafts are preserved.

## Validation

- Full PHPUnit: 481 tests, 480 passed, 1 skipped, 2,972 assertions, 0 failures/errors.
- Focused AI/Product tests: 55 passed, 225 assertions.

## Deployment

Deploy tag `v1.31.5`, rebuild Laravel caches, and restart PHP/OPcache plus Supervisor workers. Verify queue health before creating a new AI request.

## Rollback

Checkout `v1.31.4`, rebuild caches, restart managed workers, and verify application version and queue health. Do not delete AI history or Product rows.
