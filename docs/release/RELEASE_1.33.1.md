# Release v1.33.1

## Tổng quan

Patch release sửa regression sau deploy làm trạng thái AI Product lịch sử bị hiển thị như trạng thái hiện tại. Release không rewrite lịch sử, không bulk retry và không thêm migration.

## AI Product Current/History Contract

Contract cũ có thể đồng thời trả `status=BLOCKED` và `product_state=AVAILABLE`. Contract mới chỉ dùng các field top-level (`status`, `product_state`, `item`, `draft`) cho lineage hiện tại. Item/draft terminal gần nhất được bảo toàn riêng trong `latest_history` cùng `status`, `reason` và trạng thái applied lịch sử.

Khi không có active operation, actionable/approved/applying draft hoặc invariant issue, resolver trả `AVAILABLE`, current `item/draft=null`, `blockers=[]` và `next_actions=[GENERATE]`.

## Product #987 Production Regression

Production fixture có Job #29, Item #3446, lịch sử `BLOCKED / DUPLICATE_IN_PROGRESS`, parent đã cancelled, không active operation và không draft actionable. Release xử lý tương thích ở application resolver; không sửa/xóa Job #29, Item #3446 hay 272 item BLOCKED lịch sử.

## AI Product Action Resolver

Action resolver không còn dựng authority riêng từ historical `status`. Action hiện tại lấy từ `product_state` và `next_actions`; Apply readiness chỉ bổ sung các guard dành riêng cho Apply.

## Generation Readiness

Generation readiness dùng cùng canonical resolver. Terminal history không block Generate, nhưng invariant hiện tại và eligibility/provider/worker guard vẫn fail-closed.

## Single/Bulk/Live Status

Product Edit, Product List, live polling, bulk preflight và action matrix cùng hiển thị trạng thái hiện tại. Job/draft lịch sử chỉ xuất hiện trong vùng lịch sử và link diagnostics.

## Invariant Safety

`MULTIPLE_ACTIVE_OPERATIONS`, `MULTIPLE_ACTIONABLE_DRAFTS`, `MULTIPLE_APPLYING_DRAFTS`, `REVIEWABLE_DRAFT_MISSING`, stale Apply và hard technical blockers vẫn chặn đúng. Release không biến mọi `BLOCKED` thành generate-capable.

## Product Numeric Formatting

Release kế thừa đầy đủ fix `PRODUCT-DETAIL-001` từ v1.33.0. BTU range `24225.2 / 28660.8` được render thành `24,225.2 / 28,660.8 BTU`; monetary input chỉ nhận numeric primitive/plain decimal string và dùng fallback rõ ràng cho null, empty, formatted string hoặc nhãn nghiệp vụ.

## Database / Migrations

Không có migration mới trong v1.33.1. Migration lifecycle additive của v1.33.0 phải vẫn ở trạng thái `Ran`. Không được rewrite historical status hoặc xóa AI evidence.

## Automated Tests

- Final focused AI/current-history/numeric suite: 119 tests, 705 assertions, PASS.
- Final contract/bulk subset: 11 tests, 165 assertions, PASS.
- Full PHPUnit: 550 tests; 549 passed, 1 skipped; 3,452 assertions; 0 failures/errors.

## Browser Certification

- Combined Playwright: 19 passed, 1 documented legacy rollout skip, 0 failed.
- Single Product: 14 executable scenarios PASS; Bulk: 2/2 PASS; Product detail/navigation: 3/3 PASS, bao gồm numeric formatting.
- Không có HTTP 500, Livewire, page, console hoặc network error liên quan trong các run PASS.

## Worker / Queue

Deploy phải restart generic workers và `dieuhoa-ai-governed` qua Supervisor. Sau restart, application/worker phải cùng version, build ID, code hash, DB và queue `ai_governed`; pending/processing/stuck phải về 0. Giữ nguyên desired state trước deploy.

## Production Upgrade

Deploy đúng annotated tag `v1.33.1` theo [LIVE_DEPLOYMENT_RUNBOOK_1.33.1.md](LIVE_DEPLOYMENT_RUNBOOK_1.33.1.md). Live không chạy npm build với Node 14/npm 11; dùng assets đã được chứng nhận và commit theo convention của repository.

## Post-deploy Verification

Chạy migration status, integrity audit, managed health check và queue health. Xác nhận Product #987 là `AVAILABLE`, history #29/#3446 vẫn hiển thị riêng, Generate tạo operation mới không bị `DUPLICATE_IN_PROGRESS`; sau đó kiểm tra Product #1316/#1238/#1243.

## Known Historical Data

Job #29, Item #3446 và 272 historical BLOCKED items là immutable evidence. Chúng có thể tiếp tục xuất hiện ở history/integrity diagnostics nhưng không được trở thành current blocker.

## Rollback

Rollback về `v1.33.0`, cài đúng lock dependencies, rebuild cache và restart toàn bộ workers. Không rollback migration lifecycle theo cách phá dữ liệu, không xóa history và không restore DB trừ khi một mutation/schema failure thực sự yêu cầu backup.

## Evidence

- [Post-deploy regression report](../reports/final/AI_PRODUCT_CURRENT_HISTORY_STATE_POST_DEPLOY_REGRESSION.md)
- [v1.33.1 deployment runbook](LIVE_DEPLOYMENT_RUNBOOK_1.33.1.md)
