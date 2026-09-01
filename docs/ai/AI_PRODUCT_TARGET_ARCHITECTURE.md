# Kiến trúc mục tiêu AI Product

## Trạng thái tài liệu

- Baseline đóng băng: `v1.32.2`, commit `9e5cb94fa6ae52ac1c690b912c39d662e804eabd`.
- Ngày đóng băng: 2026-08-31.
- Phạm vi: AI Product single, bulk, queue worker, draft review/apply và vận hành.
- Mọi thay đổi sau tài liệu này phải gắn Issue ID trong `AI_PRODUCT_MASTER_ISSUE_LEDGER.csv`.

## 1. Nguyên tắc nền tảng

1. Trạng thái hiện tại của Product không được suy ra từ bản ghi lịch sử mới nhất.
2. Chỉ operation thực sự active hoặc draft thực sự actionable mới khóa thao tác tiếp theo.
3. Lịch sử terminal luôn được giữ làm bằng chứng nhưng không được đầu độc readiness.
4. Filament, bulk, worker và command không tự cập nhật lifecycle state; mọi mutation đi qua domain service chung.
5. Parent state được tổng hợp từ child state bằng một reconciler duy nhất.
6. Cancel, retry, recover và regenerate là bốn intent khác nhau, không dùng thay thế lẫn nhau.
7. Output dùng được phải được lưu thành draft trước quyết định của con người.
8. Apply chỉ ghi các field allowlisted, giữ Product identity, kiểm tra stale target và idempotency.
9. Hard technical conflict fail-closed; editorial warning có thể override có audit.
10. Scanner integrity chỉ đọc và báo cáo, tuyệt đối không tự sửa dữ liệu.

## 2. Canonical lineage resolver

Một resolver duy nhất trả về cấu trúc:

```text
active_operation
actionable_draft
approved_draft
latest_history
product_state
blockers[]
next_actions[]
invariant_violations[]
```

Thứ tự phân giải bắt buộc:

1. Tìm active Item theo canonical state.
2. Tìm draft actionable hoặc approved chưa Apply.
3. Nếu không có hai nhóm trên, Product là `AVAILABLE`.
4. Tìm lịch sử mới nhất chỉ để trình bày.

Nếu có nhiều active operation hoặc nhiều actionable draft, resolver không chọn `latest(id)`; trả blocker `MULTIPLE_ACTIVE_OPERATIONS` hoặc `MULTIPLE_ACTIONABLE_DRAFTS` cùng danh sách ID.

## 3. State contract

### Item canonical states

- Active: `QUEUED`, `RUNNING`, `VALIDATING`, `FACT_CHECKING`.
- Actionable terminal: `REVIEW_REQUIRED`.
- Terminal: `DONE`, `FAILED`, `BLOCKED`, `CANCELLED`.

`FAILED`, `BLOCKED`, `CANCELLED` không được chuyển ngược về active. Queue retry chỉ xảy ra khi Item chưa terminal. Manual retry luôn tạo Job/Item mới.

### Draft governance states

- Actionable: `REVIEW_REQUIRED`, `APPROVED_FOR_APPLY`.
- Active lock: `APPLYING`.
- Terminal: `REJECTED`, `DISCARDED`, `APPLIED`.

Legacy `status` được đọc qua compatibility adapter. `approval_status` là authority cho human decision. `applied_at` là bằng chứng Apply terminal.

### Product derived states

`AVAILABLE`, `PROCESSING`, `REVIEW_REQUIRED`, `APPROVED`, `APPLYING`, `APPLIED_HISTORY`, `INVARIANT_BLOCKED`.

`APPLIED_HISTORY` không khóa Generate mới.

## 4. Lifecycle service

`AiProductLifecycleService` là cổng mutation duy nhất cho:

- `generate`
- `cancel`
- `retry`
- `recover`
- `regenerate`
- `reconcileParent`

Generate khóa Product row và kiểm tra active Item + actionable Draft trong cùng transaction. Regenerate atomically supersede draft cũ rồi tạo operation mới. Lịch sử không bị overwrite.

Cancel queued chuyển Item sang `CANCELLED` ngay. Cancel running ghi cancel intent; worker kiểm tra trước runtime gate, trước provider, sau provider, trước draft activation và trước terminal transition. Evidence đã nhận từ provider được sanitize và lưu non-actionable khi cần.

Recover chỉ nhận operation stale khi đồng thời có bằng chứng heartbeat/lease/queue correlation; không áp dụng cho terminal history.

## 5. Dispatch và idempotency

- Mỗi Item có nullable unique `dispatch_uuid`.
- Queue payload mang `dispatch_uuid`, không dùng serialized model internals làm identity.
- Worker đối chiếu Item, Product, queue và dispatch UUID trước khi chạy.
- Idempotency lease được giải phóng ở mọi terminal/cancel path.
- Hai Generate đồng thời chỉ tạo một active operation hiệu lực.

## 6. Parent reconciler

Một service tách khỏi queue job tính lại counts và parent state:

- Có child active: parent active theo phase cao nhất.
- Tất cả `DONE`: parent `DONE`.
- Có `REVIEW_REQUIRED`, không failure/block: parent `REVIEW_REQUIRED`.
- Tất cả `BLOCKED`: parent `BLOCKED`.
- Tất cả `CANCELLED`: parent `CANCELLED`.
- Mixed terminal có failure/block/cancel: parent `FAILED` với counts chi tiết.

`processed` đếm child terminal hoặc actionable. Khi không còn child active, `finished_at` bắt buộc có giá trị.

## 7. Provider và field contract

Requested-field contract là nguồn duy nhất cho prompt, parser, validator, field status, preview và Apply allowlist. Field `NOT_REQUESTED` không sinh warning missing.

Preflight hard blockers chạy trước tạo provider request. Provider envelope được normalize vào canonical payload; raw payload công khai không được lưu. Output usable được persist trước human review.

Phân loại guard:

- Editorial/quality: warning, đưa vào review.
- Missing optional fact: omit hoặc warning.
- Unsupported claim: sanitize/rewrite rồi revalidate.
- Contradiction với verified authority: hard block.
- System/concurrency: không được masquerade thành editorial warning.

## 8. Human workflow và Apply

Approve, warning override, Reject, Discard và Apply dùng cùng domain services cho single/bulk.

Apply yêu cầu:

- draft approved;
- quyền server-side và rollout gate;
- explicit confirmation;
- không hard blocker;
- payload/context/current Product hash hợp lệ;
- row locks Product + Draft/audit;
- allowlisted fields duy nhất;
- idempotent manifest/audit.

SKU, model, brand, category, price, stock và technical specs không thuộc allowlist mặc định.

## 9. UI và observability

Product Edit là workflow chính. Job Log là diagnostics/history. Machine code phải có tiếng Việt, giải thích và next action. Header tối đa hai action trực tiếp + More. Recovery chỉ hiển thị với stale operation thực sự recoverable.

Command `ai:product-integrity-audit --json|--csv` chỉ đọc, exit khác 0 khi có invariant violation chưa được phân loại.

## 10. Schema additive

Thêm vào Job/Item các trường audit cancel: `cancel_requested_at`, `cancel_requested_by`, `cancel_reason`, `cancelled_at`. Thêm Item `dispatch_uuid` nullable unique. Thêm index Item `(product_id, canonical_status)` và Draft `(product_id, approval_status, applied_at)`.

Không mass rewrite historical state. Chỉ backfill field kỹ thuật khi xác định chắc chắn; legacy anomalies được compatibility adapter và scanner phân loại.

## 11. Cổng thay đổi kiến trúc

Kiến trúc này đã freeze. Nếu bằng chứng mới buộc thay đổi, commit tài liệu trước code với bốn mục: `NEW EVIDENCE`, `OLD ASSUMPTION`, `WHY ARCHITECTURE MUST CHANGE`, `IMPACT`.
