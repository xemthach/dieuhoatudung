# AI Product Current/History State Post-Deploy Regression

## Kết luận

Regression đã được tái hiện và sửa tại ranh giới domain. Trạng thái top-level của resolver giờ chỉ mô tả lineage đang hoạt động/có thể thao tác. Item/draft terminal gần nhất chỉ còn nằm trong `latest_history`; không còn khóa Generate.

- `ROOT_CAUSE_CONFIRMED`: YES
- `CURRENT_HISTORY_CONTRACT`: PASS
- `PRODUCT_987_EQUIVALENT_FIXTURE`: PASS
- Production DB mutation: **NONE**
- Historical evidence preserved: **YES**

## Bằng chứng production do operator cung cấp

Product #987 có Item #3446 thuộc Job #29, terminal `BLOCKED / DUPLICATE_IN_PROGRESS`, không có draft, không bắt đầu chạy. Parent đã `cancelled`, có `finished_at`; 272/272 child terminal BLOCKED. Resolver cũ đồng thời trả `status=BLOCKED`, `product_state=AVAILABLE`, `next_actions=[GENERATE]`. Action resolver lại lấy `status=BLOCKED`, nên UI coi lịch sử là current block.

Không có thao tác sửa Product #987, Job #29, Item #3446 hoặc retry 272 item trong nhiệm vụ này.

## Root cause

Nhánh historical-only trong `AiProductContentStateResolver` gán history status/item/draft vào các field current. `ProductAiActionResolver` sau đó dựng state machine thứ hai từ `resolved.status` thay vì dùng `product_state` và `next_actions`. Vì vậy hai authority trả kết quả trái nhau: resolver cho Generate nhưng action resolver ẩn Generate.

## Contract trước và sau

| Field | Trước | Sau |
|---|---|---|
| `status` | Có thể là terminal history | Chỉ là current actionable state |
| `product_state` | Current state | Current state, authority chính |
| `item` | Có thể là latest historical item | Chỉ current active/actionable item |
| `draft` | Có thể là historical draft | Chỉ actionable/approved/applying draft |
| `active_operation` | Current | Current |
| `actionable_draft` | Current | Current |
| `approved_draft` | Current | Current |
| `latest_history` | Chỉ chứa object | Chứa object cùng `status`, `reason`, `applied` lịch sử |
| `next_actions` | Có nhưng action resolver không tôn trọng | Là input canonical cho action resolver |

Khi không có active/actionable lineage và không có invariant:

- `status = AVAILABLE`
- `product_state = AVAILABLE`
- `item = null`, `draft = null`
- `blockers = []`
- `next_actions = [GENERATE]`
- terminal evidence vẫn có trong `latest_history`.

## Consumer audit

| Consumer | Field dùng | Kỳ vọng | Bug | Fix |
|---|---|---|---|---|
| Product Edit header | action policy/current item | Current + history link | Historical BLOCKED ẩn Generate | Action policy dùng `product_state` + `next_actions`; Job link fallback history |
| Product AI panel | live status | Current badge, history riêng | Badge/history bị trộn | Live payload thêm `history_*`; Blade hiển thị “Lịch sử gần nhất” riêng |
| ProductsTable badge/filter | resolver status/item | Current list/filter | Latest item terminal đại diện current | Filter query canonical active/actionable; history filters đặt tên rõ |
| Product status polling API | live payload | Current status | Failed/blocked history có thể phát như current | Current IDs/reason tách khỏi history IDs/reason |
| `ProductAiActionResolver` | `product_state`, `next_actions` | Current actions | Dựng state machine từ history-leaking status | Bỏ duplicated terminal authority |
| `ProductAiGenerationReadiness` | eligibility + current invariant | Generate parity | Không biết invariant resolver | Thêm canonical invariant gate; terminal history không block |
| `ProductContentEligibilityPolicy` | active DB queries | Current conflict | Không phát hiện bug history | Giữ query chỉ active/actionable; parity tests |
| `AiProductLifecycleService` | active/actionable resolver fields | Transactional create | Phụ thuộc current references | Contract mới trả null cho history nên create operation mới đúng |
| Bulk preflight/workflow | resolver status/draft/item | Current classification | Terminal history bị phân loại actionable hiện tại | `AVAILABLE` current + history columns; regenerate xét history riêng |
| Dashboard | DB aggregate riêng | Operational history | Không dùng resolver | Không thay đổi |
| Job pages | Job/Item diagnostics | Historical | Không phải current Product authority | Không thay đổi |

## Invariant safety

Các current blockers thật vẫn fail-closed:

- `MULTIPLE_ACTIVE_OPERATIONS`
- `MULTIPLE_ACTIONABLE_DRAFTS`
- `MULTIPLE_APPLYING_DRAFTS`
- `REVIEWABLE_DRAFT_MISSING`
- apply hard blockers/stale target qua Apply readiness.

Regression test xác nhận nhiều active operation trả `INVARIANT_BLOCKED`, ẩn Generate, hiện lý do và Generation Readiness cũng từ chối.

## Files changed

Production code/UI:

- `app/Services/AI/AiProductContentStateResolver.php`
- `app/Services/AI/ProductAiActionResolver.php`
- `app/Services/AI/ProductAiGenerationReadiness.php`
- `app/Services/AI/AiProductLiveStatusService.php`
- `app/Services/AI/ProductAiBulkWorkflowService.php`
- `app/Services/AI/AiContentStatusPresenter.php`
- `app/Filament/Resources/Products/Pages/EditProduct.php`
- `app/Filament/Resources/Products/Tables/ProductsTable.php`
- `app/Livewire/AiProductLiveStatus.php`
- `resources/views/livewire/ai-product-live-status.blade.php`
- `resources/views/filament/product-ai-bulk-preflight.blade.php`

Tests:

- `tests/Feature/AiProductCurrentHistoryContractTest.php`
- existing state/action/live/bulk regression suites updated to the frozen contract
- Product #987-equivalent fixture and Playwright assertion added to the single-product matrix
- bulk browser assertions distinguish current state from hard Apply blocker.

No migration was added.

## Regression evidence

### Focused AI

- 119 tests passed
- 705 assertions
- 0 failure / 0 error

Final contract/bulk rerun after the last parity adjustment: 11 tests passed, 165 assertions, exit code 0.

Exact contract suite covers terminal item history `FAILED`, `CANCELLED`, `BLOCKED`, `DONE`; terminal draft history `REJECTED`, `DISCARDED`, `APPLIED`; current `QUEUED`, `RUNNING`, `VALIDATING`, `FACT_CHECKING`; and true invariant blocking.

### Full PHPUnit

- 550 tests
- 549 passed
- 1 skipped
- 3,452 assertions
- exit code 0
- duration 134.360s
- captured at `storage/logs/full-phpunit-current-history-final.txt`

### Browser

- Combined Playwright release run: 19 passed, 1 documented legacy rollout scenario SKIPPED, 0 failed.
- Single Product action matrix: 14 executable scenarios PASS; 1 documented legacy rollout scenario SKIPPED.
- Exact historical duplicate regression: current badge “Sẵn sàng tạo nội dung”, historical Job visible separately, Generate visible, no current block action, new operation created.
- Bulk workflow: 2/2 PASS, including current/history and hard Apply-block parity.
- Product navigation/detail suites: 3/3 PASS.
- No relevant HTTP 500, page error, request failure or Livewire error in passing runs.

Local browser fixtures were disposable and cleaned by fixture lifecycle. Provider request logs remain as audit evidence. No production data was contacted or modified.

## Build, dependency and runtime gates

- Composer validate strict: PASS
- Composer audit: PASS, no advisory
- npm audit high: PASS, 0 vulnerability
- Vite production build: PASS
- Changed PHP lint: PASS
- `git diff --check`: PASS
- Migrations: all 96 records ran, 0 pending
- Managed worker: ENABLED / ONLINE / `ai_governed` / UP_TO_DATE
- Product governed queue `ai_governed`: pending 0, processing 0, stuck 0. The shared `jobs` table separately contains five pre-existing `GenerateBlogDraftJob` rows on queue `ai`; they are outside this Product regression and were not modified.
- Scheduler/watchdog: offline local; not required by this managed-worker test path.

## Database and history guarantee

- Production DB mutation: **NONE**
- No historical status rewrite migration.
- Job #29 / Item #3446 untouched.
- No retry of 272 blocked items.
- Compatibility is implemented entirely in current-state resolution and presentation.

## Final

`ROOT_CAUSE_CONFIRMED = YES`

`CURRENT_HISTORY_CONTRACT = PASS`

`PRODUCT_987_EQUIVALENT_FIXTURE = PASS`

`STATE_RESOLVER = PASS`

`ACTION_RESOLVER = PASS`

`GENERATION_READINESS = PASS`

`SINGLE_BULK_PARITY = PASS`

`BROWSER = PASS`

`FULL_PHPUNIT = PASS`

`READY_FOR_PATCH_RELEASE = YES`
