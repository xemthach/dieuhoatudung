# Báo cáo forensic toàn diện AI Product

## 1. Executive verdict

**Phase A forensic audit: HOÀN TẤT.**

**AI PRODUCT MODULE: PARTIAL**
**READY_FOR_GITHUB_RELEASE: NO**

Baseline đang vận hành tốt ở mức worker/queue nhưng state lineage và lifecycle có các lỗi kiến trúc P0 đã được chứng minh. Chưa có production code, migration hoặc provider call nào được thực hiện trong Phase A. Kiến trúc mục tiêu đã được đóng băng tại `docs/ai/AI_PRODUCT_TARGET_ARCHITECTURE.md`.

## 2. Baseline

- Git branch: `main`.
- Commit/tag: `9e5cb94fa6ae52ac1c690b912c39d662e804eabd` / `v1.32.2`.
- VERSION: `1.32.2`.
- Laravel: `13.26.1`; PHP: `8.3.16`; DB: MySQL; queue: database.
- Migrations: 95 ran, 0 pending.
- Worker desired/actual: `ENABLED` / `ONLINE`.
- Application/worker version: `v1.32.2` / `v1.32.2`.
- Deployment: `UP_TO_DATE`; queue: `ai_governed`.
- Pending/processing/stuck: `0/0/0`.
- Scheduler/watchdog local: offline; đây là local limitation, không được trình bày như production PASS.
- Real provider calls trong Phase A: `0`.

Dirty browser images và các file import ngoài AI Product được giữ nguyên, không đưa vào phạm vi remediation.

## 3. Database baseline

| Entity | Count |
|---|---:|
| products | 364 |
| ai_product_jobs | 54 |
| ai_product_job_items | 92 |
| ai_product_drafts | 70 |
| ai_request_logs | 320 |
| jobs | 5 |
| failed_jobs | 17 |
| product_bulk_operations | 46 |
| product_bulk_operation_items | 160 |
| catalog_models | 36,453 |
| catalog_model_fields | 656,507 |
| ai_product_draft_apply_audits | 6 |
| ai_product_content_versions | 6 |
| ai_technical_logs | 1,712 |

## 4. Code inventory

Đã inventory Models, jobs, lifecycle/readiness services, guards, provider content system, apply services, Filament resources/pages, Livewire/status endpoint, queue commands, migrations và tests. Danh sách chi tiết nằm tại `artifacts/ai_product_code_inventory.csv`.

Kết quả scan phát hiện 51 vị trí transition hoặc direct canonical-state write trong subsystem. Đây là bằng chứng mutation boundary đang bị phân tán.

## 5. Relationship graph

`Product 1—N JobItem N—1 Job`, `Job 1—N Item`, `Product 1—N Draft`, `Item N—1 Draft`, `Draft N—1 Job`, cùng request log, technical log, bulk operation/item ledger, runtime batch, queue payload và apply audit.

DB baseline không có orphan Item→Job, Item→Product, Draft→Product hoặc Draft thiếu Item. Tuy nhiên cardinality không bảo đảm currentness: một Product có thể có nhiều draft actionable.

## 6. Current state machine

Item canonical vocabulary trong code: `QUEUED`, `RUNNING`, `VALIDATING`, `FACT_CHECKING`, `REVIEW_REQUIRED`, `DONE`, `FAILED`, `BLOCKED`, `CANCELLED`.

Draft còn dùng cả `status`, `approval_status` và `applied_at`; Job/Item dùng cả legacy `status` và `canonical_status`. Các cột này không đồng nghĩa nhưng đang được nhiều code path cập nhật độc lập.

State machine hiện cho phép `FAILED → QUEUED`, `RUNNING → QUEUED` và `BLOCKED → REVIEW_REQUIRED`. `FAILED → QUEUED` làm terminal history có thể bị reopen, trái contract immutable terminal của kiến trúc mục tiêu.

## 7. Current Product state resolver

`AiProductContentStateResolver::latestItem()` dùng `latest('id')`. Vì vậy historical recency đang đóng vai trò current lineage.

Case thực tế Product 1239:

- latest Item #166: legacy `needs_review`, canonical `QUEUED`;
- Item trỏ draft #5 đã `APPLIED`;
- draft #1 và #2 vẫn `REVIEW_REQUIRED`.

Resolver chỉ nhìn lineage mới nhất nên che hai actionable draft và không báo invariant conflict.

## 8. Active/actionable lineage

Current code không có một resolver chung cho active operation, actionable draft, approved draft và latest history. Eligibility, Product panel, bulk và worker sử dụng các query/status khác nhau.

Target đã freeze:

1. active operation;
2. actionable/approved draft;
3. Product available;
4. latest history display-only.

## 9. Duplicate detection

`ProductContentEligibilityPolicy` kiểm tra Draft legacy status và Item legacy status `queued/processing/needs_review`. Trong khi DB có canonical mismatch, duplicate detection có thể false-positive hoặc false-negative. Transaction cũng chưa gom row lock + active Item + actionable Draft thành một atomic decision dùng chung single/bulk.

## 10. Historical poisoning và Product special-case

Policy chứa Product ID hard-code `[1237, 1241, 1242, 1261]` với blocker `HISTORICAL_ROLLOUT_DISPOSITION_PRESERVED`. Đây là operational history được nhúng vào production eligibility, không phải domain fact.

Các Product này có trạng thái khác nhau: 1237 có 2 Items/no draft; 1241 no item/no draft; 1242 có item/draft approved; 1261 no item/no draft. Hard-code che nguyên nhân thật và làm cùng state cho kết quả khác theo ID.

## 11. Ghost active và parent/child mismatch

Mười bốn parent Jobs #36, #40, #42, #43, #44, #47, #48, #49, #50, #53, #54, #55, #56, #57 ở canonical `QUEUED/RUNNING` dù không có child active. Job #57 còn có `finished_at` nhưng canonical `RUNNING`.

Đây là ghost active ở parent layer, không phải queue runtime: governed queue pending/processing/stuck đều 0. Danh sách nằm tại `ai_product_ghost_active_report.csv`.

## 12. Orphans và invariants

- Orphan relationship: 0.
- Multiple actionable draft: Product 1239, draft #1/#2.
- Approved draft thiếu `approved_content_hash`: draft #6, Product 1242.
- Legacy/canonical mismatches: Item #166, #169, #171, #172, #176.
- Unknown anomaly: 0; tất cả anomaly phát hiện đã được phân loại trong ledger.

## 13. Parent aggregation

`AiProductContentSingleJob::refreshJobStats()` chứa aggregation ngay trong queue job và đếm legacy statuses. Reconciliation không phải mutation boundary dùng chung cho Filament, bulk, cancel và recovery. Vì vậy terminal child có thể để parent active.

## 14. Cancellation

Product Edit recovery action trực tiếp update Job/Item trong transaction. Queue monitor recovery cũng direct update. Worker chỉ có early no-op cho `BLOCKED_FINAL`; chưa có durable cancel intent và checkpoint trước idempotency/provider. Bulk cancellation được kiểm tra sau khi đã tiến sâu hơn vào runtime path.

Rủi ro: token vẫn có thể bị tiêu thụ sau cancel, lease/slot không giải phóng nhất quán và parent không reconcile.

## 15. Retry và recovery

Manual retry và queue retry chưa tách contract. State machine cho terminal FAILED quay lại QUEUED. Recovery xác định stuck từ tuổi/status nhưng thiếu `dispatch_uuid` để correlate queue payload với Item chắc chắn.

Target: queue retry chỉ cho Item chưa terminal; manual retry tạo operation mới; recover chỉ cho stale operation có heartbeat/lease/queue evidence.

## 16. Queue correlation

`jobs` có 5 payload legacy trên queue `ai`; governed queue `ai_governed` hiện không pending. Không xóa các hàng lịch sử. Scanner/lifecycle mới phải phân loại wrong-queue payload và worker governed phải từ chối payload không correlate với Item/dispatch UUID.

## 17. Idempotency

Đã có `AIProductIdempotencyService`, runtime gate và bulk manifest, nhưng quyết định duplicate/currentness vẫn phụ thuộc các query legacy phân tán. Idempotency không thay thế canonical lineage hoặc Product row lock.

## 18. Provider gateway và parser

Provider integration, logging, token usage, sanitizer và response normalization nằm chủ yếu trong `AIProductContentSystem`, `ProductAIContentService` và provider/gateway services. Phase A không gọi provider. Fake-provider matrix sẽ chạy trước real provider theo remediation order.

## 19. Prompt/requested-field contract

Config `ai_product_allowed_fields.php`, field status, validator và Apply allowlist đã tồn tại nhưng cần parity test để chứng minh field không requested không sinh warning và provider payload không thể mass-fill protected fields.

## 20. Draft persistence/currentness

Draft persistence đã có evidence-rich columns và review audit, nhưng currentness không được giải bởi canonical lineage. Multiple actionable drafts và approved draft thiếu hash chứng minh compatibility path chưa đủ an toàn.

## 21. Quality warnings

`AiProductWarningClassifier`, content structure validator và guard policy đã phân biệt nhiều warning/hard gate. Remediation phải giữ policy: editorial quality đi review; không biến warning thành system failure; không dùng warning override để vượt technical contradiction.

## 22. Technical guards/facts

`AiGuardPolicy` cùng fact-check flow là safety authority hiện hành. Optional missing fact, unsupported claim và verified contradiction phải được test thành ba outcomes khác nhau: omit/warn, sanitize/revalidate, hard block.

## 23. Approval/Reject/Discard

Draft có actor/timestamp/warning snapshot columns. Single và bulk có implementations gần nhau nhưng chưa đi qua một lifecycle boundary duy nhất. Approval không được mutate Product; Reject/Discard phải giữ evidence.

## 24. Apply

Apply service đã có allowlist, confirmation/readiness, hash và audit concepts. Draft #6 thiếu approved content hash chứng minh legacy compatibility cần fail-safe classification. Apply phải lock Product/Draft, stale-check, giữ identity/count và idempotent.

## 25. Single/bulk parity

Filament `EditProduct::queueAiGeneration()`/recovery action, `ProductAiBulkWorkflowService`, queue job aggregation và queue monitor đang sở hữu các phần lifecycle riêng. UI khác nhau được phép; business rules khác nhau không được phép.

## 26. RBAC/rollout

Code truth permissions gồm `product.ai_generate`, `bulk_ai_approve`, `bulk_ai_apply`, `bulk_ai_view`, `bulk_ai_view_all`, `ai_worker.manage`. `SingleOperatorControlledRolloutPolicy` tiếp tục là gate bổ sung. Remediation không đổi operator ID/policy để test.

## 27. Encoding

Artifact encoding hiện có ghi 26 occurrence xác nhận và 30 candidate độ tin cậy thấp. Active operator copy phải UTF-8; historical raw evidence được giữ và phân loại, không rewrite mù.

## 28. Performance baseline

Query-count certification chưa thực hiện trong Phase A vì cần canonical resolver trước để số đo có ý nghĩa. Final gate phải đo Product list, AI panel, Job detail và bulk preflight 10/50/all, không N+1 regression.

## 29. Security baseline

Current system có server-side permissions, rollout gate, sanitizer và Apply allowlist. Chưa chứng nhận final vì lifecycle/refactor có thể đổi call paths. Final suite phải kiểm tra crafted Livewire calls, HTML sanitization, secret scan và dependency audits.

## 30. Root causes

- RC-001: historical recency bị conflated với current lineage.
- RC-002: state mutation và parent aggregation phân tán.
- RC-003: legacy/canonical/draft governance states chồng lấn, thiếu compatibility authority.
- RC-004: cancel/retry/recover thiếu durable intent và queue correlation.
- RC-005: operational dispositions được hard-code vào Product eligibility.

## 31. Kiến trúc target và remediation

Kiến trúc đã freeze tại `docs/ai/AI_PRODUCT_TARGET_ARCHITECTURE.md`. Thứ tự remediation và acceptance từng Issue nằm tại `docs/ai/AI_PRODUCT_REMEDIATION_PLAN.md` và master ledger.

## 32. Release impact

Các P0 state/lifecycle issues là release-blocking cho một đợt release AI Product mới. Baseline worker online không đủ để chứng nhận module khi DB còn ghost parent và multiple actionable drafts.

## 33. Local/live parity

Local baseline được chứng minh. Live runtime chưa có SSH/session vận hành trong task; public HTTP không đủ để suy luận migration/settings/worker hash/scheduler. Issue AI-LIVE-016 là `BLOCKED_BY_EXTERNAL_DEPENDENCY` cho đến khi có read-only access.

## 34. Phase A final

- Code files audited/inventoried: 62 file trực tiếp, cộng state machine/provider/shared dependencies liên quan.
- Tables audited: 13 core/ledger/runtime tables.
- Canonical Item states discovered: 9.
- Proven issues: 16 (P0: 4, P1: 10, P2: 2, P3: 0).
- Orphan relationships: 0.
- Unknown issues in ledger: 0.
- Production code changed: 0.
- Migrations created/run: 0.
- Provider calls: 0.
- Historical rows mutated: 0.

**Kiến trúc được freeze; remediation có thể bắt đầu bằng regression tests và migration additive.**

## 35. Remediation certification (2026-09-01)

Phase A chronology above remains immutable. Remediation was implemented after architecture freeze and is linked to the Issue IDs in the master ledger.

- Additive lifecycle migration ran successfully: 96 migrations ran, 0 pending; no historical status rewrite.
- Canonical lineage now resolves active operation, actionable/approved draft, latest history, blockers, next actions and invariant violations independently of `latest(id)`.
- Terminal `FAILED`/`BLOCKED` history cannot reopen. Manual retry creates a new operation identity.
- Generate, cancel, retry, recover and parent reconciliation use shared lifecycle services. Queue payloads carry a unique `dispatch_uuid`; stale or mismatched payloads no-op before provider execution.
- Parent aggregation uses canonical child states. The read-only integrity scanner reports 21 known historical violations and 0 unknown violations.
- Product-ID rollout exclusions were removed from eligibility. Existing historical rows remain unchanged.
- Product status polling now eager-loads canonical lineage; the 20-Product bounded-query regression test passes.

Final dispositions are recorded in `artifacts/ai_product_master_issue_disposition.csv`. AI-ENC-013 and AI-OBS-014 are accepted limitations with explicit scope; AI-LIVE-016 remains `BLOCKED_BY_EXTERNAL_DEPENDENCY` because no authenticated live operations session was supplied.

## 36. Browser and real-provider certification

Playwright executed the single and bulk governed workflows on disposable local Products using configured operator user ID 1:

- Single matrix: 13 passed, 1 intentional legacy skip.
- Bulk shared-domain regression: 2 passed.
- Combined: 15 passed, 0 unexpected failures, 1 explained skip.
- Console errors, page errors, failed requests, HTTP 500 and relevant Livewire errors: 0.

Three controlled real-provider calls were made, one for each distinct runtime path: single Generate, single Regenerate and one-Product bulk Regenerate. Provider/model were `custom / gemini-2.5-flash`; provider-reported total tokens were 6,008 + 6,166 + 5,178 = 17,352. Input/output token breakdown was not supplied. Request logs #324-#326 remain; fixture Products/Jobs/Items/Drafts were cleaned after assertions. No important Product was applied or mutated.

## 37. Final regression, build and data safety

- Focused AI Product suite: 119 passed, 598 assertions, 0 failures/errors.
- Full PHPUnit: 543 tests, 542 passed, 1 skipped, 3,306 assertions, 0 failures/errors, exit code 0, duration 131.358 seconds.
- Composer strict validation and audit: PASS, 0 advisories.
- npm high audit: PASS, 0 vulnerabilities; Vite production build: PASS.
- Laravel config/route/view caches: PASS.
- PHP lint for every changed PHP file: PASS.
- High-confidence diff secret scan: 0 matches; `git diff --check`: PASS.
- Products, AI Jobs, Items, Drafts and catalog counts are unchanged. Retained deltas are three provider request logs and six bulk operation/twenty-one per-Product ledger rows.
- Worker final state: desired ENABLED, actual ONLINE, queue `ai_governed`, deployment UP_TO_DATE, pending/processing/stuck 0/0/0, cross-process self-test PASS.
- Scheduler/watchdog remain offline locally and are documented as a local limitation; production verification remains mandatory.

## 38. Final verdict

`LOCAL AI PRODUCT MODULE = PASS`.

`LIVE PARITY = BLOCKED_BY_EXTERNAL_DEPENDENCY` because repository and public HTTP evidence cannot certify live migration count, settings precedence, Supervisor worker hash or scheduler/watchdog.

Therefore the combined task verdict is `AI PRODUCT MODULE = PARTIAL` and `READY_FOR_GITHUB_RELEASE = NO` until the read-only Live parity gate is executed. No commit, tag, push or deployment was performed.
