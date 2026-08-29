# v1.31.4

## Executive Summary

AI Product short-content policy update. Non-empty provider content below the target length is now retained for explicit admin review instead of being discarded as a total failure.

## AI Product Pipeline

- Stores short content as a reviewable draft with `content_too_short:x/y` warning.
- Keeps the configured 800-word normal and 1,200-word commercial targets unchanged.
- Keeps empty payloads and critical safety/validation failures blocked or failed.
- Product content remains unchanged until explicit approval and Apply.

## Validation

- Focused AI Product tests: 42 passed, 145 assertions.
- Full PHPUnit: 481 tests, 480 passed, 1 skipped, 2,972 assertions, 0 failures/errors.

## Deployment

Deploy tag `v1.31.4`, rebuild Laravel caches and restart PHP/OPcache plus Supervisor workers. Do not retry historical jobs in bulk.

## Rollback

Checkout `v1.31.3`, rebuild caches, restart PHP and managed workers, then verify application version and queue health. Do not delete AI history or Product rows.
