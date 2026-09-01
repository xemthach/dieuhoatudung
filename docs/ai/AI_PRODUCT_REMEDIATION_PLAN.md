# Kế hoạch remediation AI Product

## Baseline và quy tắc

Baseline `v1.32.2` / `9e5cb94`. Không xóa/reclassify lịch sử, không sửa status trực tiếp bằng SQL, không hạ hard guard, không commit/tag/push/deploy. Mỗi issue chỉ `FIXED` khi regression test, browser proof và DB invariant đều PASS.

## Thứ tự phụ thuộc

| Bước | Issue | Thay đổi chính | Migration | Test bắt buộc | Acceptance |
|---|---|---|---|---|---|
| R1 | AI-SCHEMA-008 | Cancel audit fields, `dispatch_uuid`, canonical indexes | Có, additive | migration/schema rollback | schema đúng, history không rewrite |
| R2 | AI-STATE-001, AI-STATE-010 | Canonical lineage resolver + legacy adapter | Không | state/property tests | history terminal không khóa Generate; multiple lineage trả invariant |
| R3 | AI-STATE-002 | Parent reconciler dùng canonical child states | Không | all-success/review/blocked/failed/cancelled/mixed | all-terminal luôn parent terminal |
| R4 | AI-OPS-011 | Read-only integrity command JSON/CSV | Không | command exit/output tests | phát hiện toàn bộ anomaly đã biết, không mutation |
| R5 | AI-DUP-006 | Transactional active conflict + row lock | Không | double Generate, tabs, single+bulk | một active operation hiệu lực |
| R6 | AI-LIFE-004 | Lifecycle service chung, cancel checkpoints | Không | queued/running/bulk cancel, provider checkpoints | no provider khi cancelled-before-call; lease sạch |
| R7 | AI-RETRY-005 | Manual retry tạo operation mới; bỏ terminal reopen | Không | failed history + retry, queue retry | terminal immutable |
| R8 | AI-SPECIAL-007 | Bỏ Product ID hard-code, chuyển disposition sang audit data | Không | four historical IDs + generic fixtures | cùng state cho mọi Product |
| R9 | AI-DATA-003, AI-APPROVAL-009 | Draft lineage/currentness compatibility | Chỉ backfill khi chứng minh chắc chắn | multiple draft, approved hash | không chọn latest mù; không mất evidence |
| R10 | AI-PARITY-012 | Single/bulk dùng lifecycle/resolver/reconciler chung | Không | parity + RBAC + rollout | cùng input state cho cùng outcome |
| R11 | AI-OBS-014 | Loại direct state writes, structured events | Không | mutation scan + behavior tests | mutation sites đi qua services |
| R12 | AI-QUEUE-015 | Queue correlation và disposition legacy queue | Không | payload UUID, generic-worker guard | queue mismatch được phát hiện/no-op |
| R13 | AI-ENC-013 | Chuẩn hóa operator copy/encoding | Không | UTF-8 browser/API tests | không mojibake trong workflow chính |
| R14 | AI-LIVE-016 | Live parity read-only | Không | SSH/runtime evidence | PASS hoặc `BLOCKED_BY_EXTERNAL_DEPENDENCY` trung thực |

## Chiến lược regression-first

Trước mỗi implementation:

1. Chứng minh hiện trạng bằng fixture hoặc read-only DB evidence.
2. Viết test fail cho contract mục tiêu.
3. Sửa root cause tối thiểu theo kiến trúc freeze.
4. Chạy focused tests.
5. Chạy browser path liên quan.
6. Chạy integrity audit và so DB trước/sau.
7. Cập nhật issue ledger.

## Matrix D1-D28

- D1-D5: normal/double Generate và terminal history.
- D6-D8: cancel active/bulk và worker crash.
- D9-D14: provider failures, quality warning, short content, optional facts, unsupported claim, verified conflict.
- D15-D22: approve, override, reject, discard, regenerate, apply, double apply, stale target.
- D23-D28: mixed bulk, single/bulk parity, RBAC, rollout, encoding và legacy history.

Property tests bắt buộc:

- terminal history không khóa Generate;
- chỉ active operation tạo duplicate conflict;
- all-terminal child tạo terminal parent;
- Cancel không để lease/slot active;
- Apply không đổi Product identity/protected fields.

Concurrency tests bắt buộc: hai Generate, bulk + single, hai Approve, hai Apply, Cancel khi worker chạy, Retry khi operation active.

## Provider và browser

Fake fixtures phải PASS trước real provider. Fake cases: valid, fenced/malformed/partial/empty/truncated JSON, timeout, 429/5xx, Unicode và unsafe HTML.

Real provider dùng tối thiểu một call cho mỗi runtime path khác biệt, có ledger Product/Job/Item/Draft/request/model/tokens/result; không bulk và không Apply vào Product quan trọng.

Playwright bao phủ Generate, processing, preview, approve/override, reject, regenerate, discard, apply, cancel, recover, bulk, RBAC, encoding và responsive. Console/page/network/Livewire errors phải bằng 0 ngoài skip có giải thích.

## Final gates

- Focused suite và full PHPUnit exit 0.
- Composer validate/audit, npm high audit/build, Laravel caches, PHP lint, secret scan, `git diff --check` PASS.
- Integrity audit không còn invariant unknown.
- Worker `ONLINE`, `UP_TO_DATE`, queue `ai_governed`, pending/processing/stuck 0, self-test cross-process PASS.
- DB chỉ có fixture/audit deltas; không catalog mutation không giải thích.
- Live chỉ read-only; thiếu SSH/session được ghi `BLOCKED_BY_EXTERNAL_DEPENDENCY`.

Chỉ khi tất cả gate đạt mới trả `AI PRODUCT MODULE = PASS` và `READY_FOR_GITHUB_RELEASE = YES`.
