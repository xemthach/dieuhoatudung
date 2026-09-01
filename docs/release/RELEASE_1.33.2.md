# Release v1.33.2

## Tổng quan

Patch release sửa production regression Bulk AI: Bulk preflight chấp nhận Product sẵn sàng nhưng worker block toàn bộ child trước provider bằng `DUPLICATE_IN_PROGRESS`.

## Bulk AI root cause

Job #31 có `config_json` không chứa `operation_generation`. `AIProductIdempotencyService` khi đó dùng fallback `legacy-generation`; historical non-DONE item có cùng Product/config/key bị hiểu nhầm là yêu cầu đang chạy.

Chuỗi đã được xác nhận:

`READY preflight` → thiếu operation identity → `legacy-generation` → historical collision → `DUPLICATE_IN_PROGRESS` → `BLOCKED` trước provider.

## Production Job #31 evidence

Job #31 giữ nguyên historical evidence: 276 tổng, 276 processed, 0 success, 276 blocked; toàn bộ child là `BLOCKED / DUPLICATE_IN_PROGRESS`, không provider/model/token/draft/attempt và không request log trong incident window. Release không retry, delete hoặc rewrite Job #31 hay 276 child.

## operation_generation contract

`AiProductLifecycleService::prepareGenerationConfig()` là identity boundary dùng chung:

- mỗi authorized operation nhận UUID `operation_generation` duy nhất;
- mọi child trong cùng Bulk Job chia sẻ UUID đó;
- identity đã frozen không được xoay;
- Batch/Single worker bổ sung identity duy nhất cho queued legacy Job thiếu field trước idempotency gate.

## Idempotency và Single/Bulk parity

Historical terminal item thuộc operation cũ không còn chặn operation mới. Duplicate thực sự — Product đã có operation active với cùng identity — vẫn fail-closed. Product List header, table Bulk action, Bulk Regenerate, Batch worker và Single worker dùng cùng lifecycle boundary.

## Current/history và numeric formatting

Không có thay đổi mã nguồn current/history resolver hoặc Product numeric formatting trong v1.33.2. Các fix đó đã nằm ở v1.33.1/v1.33.0 và vẫn được regression suite bao phủ; không được gộp nhầm vào patch này.

## Tests và provider certification

- Focused: 80 passed, 439 assertions.
- Full PHPUnit: 551 tests, 550 passed, 1 skipped, 3,459 assertions, 0 failures/errors.
- Playwright Bulk: 2 passed, 0 failed.
- Provider `custom / gemini-2.5-flash`, governed queue `ai_governed`:
  - 1 Product: Job 1434, request 339, 5,592 total tokens, `REVIEW_REQUIRED`.
  - 3 Products: Job 1435, requests 340-342, 17,040 total tokens, 3/3 `REVIEW_REQUIRED`.
  - 10 Products: Job 1436, requests 343-352, 62,781 total tokens, 10/10 `REVIEW_REQUIRED`.

Provider only supplied total tokens. Fixture Products 3194-3207 are inactive; Jobs, Items, Drafts and request logs remain audit evidence.

## Deployment requirements

Deploy exact annotated tag `v1.33.2`; do not use `git pull`, `composer update`, manual production code patches, bulk retry or historical status rewrite. No migration is added, but run `migrate --force` and require zero pending migrations.

Production currently has a Node 14/npm 11 mismatch. Do not run a Live npm build; deploy the certified repository build artifacts and verify `public/build/manifest.json`.

## Worker lifecycle

Capture and restore the AI desired state. Restart `dieuhoa-worker_00`, `dieuhoa-worker_01` and `dieuhoa-ai-governed` through Supervisor. Require app/worker version `1.33.2`, exact release build, matching hash, fresh heartbeat and `ai_governed` pending/processing/stuck equal zero.

## Production acceptance

After integrity and non-provider health checks, run controlled Bulk Generate in order: 1 safe Product, 3 safe Products, then 10 safe Products. Each parent must have non-empty `config_json.operation_generation`; no new child may false-block as historical `DUPLICATE_IN_PROGRESS`. Do not run 276 again.

Verify Product #987 current state remains AVAILABLE with Job #29/Item #3446 history visible separately. Product numeric smoke remains required but is a prior-release regression, not a v1.33.2 source change.

## Rollback

If a mandatory deployment gate fails, preserve AI desired state, pause safely, checkout `v1.33.1`, reinstall locked dependencies, rebuild caches and restart all workers. No destructive migration rollback is needed. Restore the verified pre-deploy database backup only for a proven database mutation failure.

## Evidence

- [Bulk self-block regression report](../reports/final/AI_PRODUCT_BULK_SELF_BLOCK_POST_DEPLOY_REGRESSION.md)
- [Live deployment runbook](LIVE_DEPLOYMENT_RUNBOOK_1.33.2.md)
