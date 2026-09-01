# AI Product Bulk self-block post-deploy regression

Ngày audit: 2026-09-01. Baseline local: `v1.33.1` / `005cda03e7b3671d9fdc00f9b92570331a9b7127`.

## Evidence production và giới hạn truy cập

Forensic production do operator xác nhận cho Job #31: 276 Product qua preflight (`0` loại), sau đó parent `BLOCKED`, `processed=276`, `success=0`, `failed=276`, `needs_review=0`. Cả 276 child đều là `blocked / BLOCKED / DUPLICATE_IN_PROGRESS`, với `provider`, `model`, `draft_id` là `null`, `tokens_used=0`, `attempts=0`, `started_at=null`; request log trong incident window bằng `0`. `config_json` của Job #31 không có `operation_generation`.

Không có SSH hoặc authenticated production database/log session trong nhiệm vụ local này, nên forensic trên là bằng chứng do operator trích xuất, không phải truy vấn trực tiếp của local runner. Job #31 và 276 historical blocked Items không bị sửa, retry hoặc xóa.

## Root cause đã tái hiện local

`AIProductIdempotencyService::key()` dùng `operation_generation`, nhưng fallback thành `legacy-generation` khi field bị thiếu. Single lifecycle luôn sinh UUID; hai normal Bulk submit path trước fix chỉ tạo guard snapshot và không tạo `operation_generation`.

Do đó chuỗi sai là:

`Bulk preflight AVAILABLE` → `Job mới config thiếu identity` → `Batch tạo Item QUEUED` → idempotency lookup thấy historical non-DONE Item cùng Product/config/key `legacy-generation` → child bị `BLOCKED/DUPLICATE_IN_PROGRESS` trước dispatch Single worker/provider.

Self-match không phải nguyên nhân: `existing($key, $item->id)` loại trừ current Item. Regression fail trước fix tạo ba Product AVAILABLE, ba historical `BLOCKED/DUPLICATE_IN_PROGRESS` và một Bulk Job mới; expected dispatch ba Single Job nhưng thực tế không dispatch được do collision lịch sử.

## Contract sau fix

`AiProductLifecycleService::prepareGenerationConfig()` là canonical identity boundary:

- Mỗi authorized operation nhận một `operation_generation` UUID duy nhất.
- Mọi item của cùng Bulk Job dùng chung identity đó.
- Header Bulk, table Bulk, lifecycle single và bulk regenerate cùng gọi boundary này.
- `ensureJobGenerationIdentity()` chỉ bổ sung UUID khi một queued legacy Job thực sự thiếu; không xoay identity đã frozen và không chạm history terminal.
- Batch và Single worker gọi compatibility boundary trước idempotency check để không tự chặn queue legacy còn pending.

Duplicate protection không bị nới: test existing active operation cùng Product/same explicit identity vẫn nhận `DUPLICATE_IN_PROGRESS`; terminal historical key khác operation mới không thể collide.

## Parent aggregation

Parent reconciler vẫn là canonical source: child active giữ parent active; all `REVIEW_REQUIRED` đưa parent `REVIEW_REQUIRED`; all blocked đưa parent `BLOCKED`. Cột legacy `failed` hiện gộp FAILED/BLOCKED/CANCELLED để tương thích schema/UI cũ; canonical status và per-item reason là phân loại chuẩn. Không có migration hay data repair trong fix này.

## Controlled provider proof

| Scope | Job | Items | Provider requests | Result | Tokens |
|---|---:|---:|---|---|---:|
| 1 Product | 1434 | 1605 | 339 | 1/1 `REVIEW_REQUIRED` | 5,592 |
| 3 Products | 1435 | 1606-1608 | 340-342 | 3/3 `REVIEW_REQUIRED` | 17,040 |
| 10 Products | 1436 | 1609-1618 | 343-352 | 10/10 `REVIEW_REQUIRED` | 62,781 |

Tất cả dùng `custom / gemini-2.5-flash`, `ai_governed`, managed cross-process worker. Provider chỉ trả total tokens; input/output là unavailable. 14 fixture Product 3194-3207 đã đánh dấu inactive. Job/Item/Draft/request evidence được giữ. Không Apply Product, không catalog mutation, không bulk retry 276.

## Automated verification

- Red-before-fix / green-after-fix regression: `AIProductContentSystemTest::test_bulk_generation_assigns_a_new_operation_identity_and_does_not_conflict_with_terminal_legacy_history`.
- Focused lifecycle/current-history/header/bulk: 80 passed, 439 assertions.
- Full PHPUnit: 551 tests, 550 passed, 1 skipped, 3,459 assertions, result `passed` (134.177s).
- Playwright `ai-product-bulk-workflow.spec.ts`: 2 passed, 0 failed. Includes governed browser workflow, rollout gate and no recorded console/network/server error.
- `composer validate --strict`, `composer audit`, `npm audit --audit-level=high`, `npm run build`, PHP lint changed files and `git diff --check`: PASS.

## Verdict

- Root cause local/source: confirmed.
- Production Job #31 pre-provider lifecycle block, thiếu `operation_generation` và historical idempotency collision: CONFIRMED từ forensic operator. Read-only SSH verification vẫn là cổng deploy riêng.
- New Bulk lifecycle, identity, true duplicate protection, 1/3/10 governed provider path, parent reconciliation and browser path: PASS locally.
- Historical Job #31 preservation: YES.
- Automatic deployment: not performed.
