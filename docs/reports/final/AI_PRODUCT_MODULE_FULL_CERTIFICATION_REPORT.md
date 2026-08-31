# AI Product Module Full Certification Report

## Final Certification Update — 2026-08-30

`AI PRODUCT MODULE = PASS`

The two remaining gates are closed. The complete browser action workflow passed on isolated local Products, and the browser/server-side RBAC matrix passed for full, generate-only, approve-only, apply-only, and no-AI-permission actors. No production-like Product, catalog row, historical AI job, draft #14, or provider request evidence was changed.

### Final Browser Action Certification

- One controlled Playwright run: 12 tests total, 11 passed, 0 failed, 1 intentional real-provider skip. The skipped provider scenario was not an unproven path: Job #60 / draft #14 remains the previously authorized real-provider browser proof.
- Preview, approve, approve-with-warning, reject, regenerate, logical discard, Apply, double-Apply protection, stale-target protection, duplicate-generate preflight, hard fact block, job detail, and Product panel states: PASS.
- Warning approval now stores `warning_override`, the warning snapshot, authenticated reviewer, timestamp, and review note.
- Reject and discard now store separate authenticated actor/timestamp evidence.
- Approval records the Product content hash. Apply fails with `STALE_PRODUCT_CONTENT` if an operator edited canonical Product content after approval.
- Regenerate uses generate authorization, preserves the old draft as rejected/superseded evidence, and creates a distinct operation identity.
- Browser fixture cleanup returned the database to 358 Products, 46 AI jobs, 32 items, 12 drafts, and 246 request logs. No provider call occurred during the final action/RBAC matrix.
- Relevant console errors, page errors, failed requests, Livewire failures, and HTTP 500 responses: 0.

### Final RBAC Certification

- Full admin: Generate, Preview, Approve, warning override, Reject, Regenerate, Discard, and Apply according to state — PASS.
- Generate only: Generate/Regenerate available; Approve/Reject/Discard/Apply unavailable — PASS.
- Approve only: Preview/Approve/Reject/Discard available; Generate/Regenerate/Apply unavailable — PASS.
- Apply only: Apply available only for an approved draft; generation/review actions unavailable — PASS.
- No AI permission: all AI mutation actions unavailable — PASS.
- Service boundaries independently reject unauthorized approve, reject, discard, regenerate, and apply calls. A manipulated hidden Livewire Generate action creates no job and dispatches nothing.
- Actor IDs cannot be supplied independently of the authenticated actor for approval/rejection/discard. Current Product authorization is global `product.edit`; there is no per-Product ownership scope to bypass.

### Final Regression and Operations Evidence

- Focused AI Product suite: 84 tests, 83 passed, 1 skipped, 395 assertions, 0 failures/errors.
- Full PHPUnit: 494 tests, 493 passed, 1 skipped, 3,068 assertions, 0 failures/errors.
- Composer validate/audit, npm audit, Vite build, PHP lint, Laravel config/route/view caches, and `git diff --check`: PASS.
- Migrations: 94 ran, 0 pending. The new migration adds review actor/timestamp, warning-override evidence, and approval-time Product content hash columns.
- Worker: desired `ENABLED`, actual `ONLINE/RUNNING`, queue `ai_governed`, deployment `UP_TO_DATE`, version `1.31.5`, pending/processing/stuck all 0.
- Scheduler/watchdog remain offline locally. This does not block the managed queue browser flow; production deployment must still verify scheduler/watchdog health.
- Job #56 is preserved as historical failed evidence. Draft #14 remains `REVIEW_REQUIRED`, unapproved and unapplied.
- `DISCARDED` and field-level `GENERATED`, `EMPTY`, `PARSE_FAILED`, `VALIDATION_FAILED`, and `NOT_REQUESTED` now have explicit Vietnamese presentation; actionable drafts no longer show those known states as “Chưa xác định”.
- `READY_FOR_GITHUB_RELEASE = YES`. This certification did not commit, tag, or push.

## Historical Certification Snapshot

The sections below preserve the earlier PARTIAL chronology before the final browser/RBAC closure.

## 1. Executive Verdict (Historical)

`AI PRODUCT MODULE = PARTIAL`

The generation, parser, persistence, editorial-warning review, hard technical block, queue retry, parent aggregation, duplicate preflight, and Product preview paths are proven. A real browser flow reached `REVIEW_REQUIRED` with Product content unchanged. Release readiness remains partial because browser execution of approve/reject/regenerate/discard/apply and the complete role matrix were not exercised end-to-end in this run.

## 2. Current Architecture

`Product Edit -> preflight -> AiProductJob/Item -> ai_governed -> managed worker -> AIManager -> provider -> parser -> normalized draft -> validation/fact check -> human review -> explicit approval -> apply to same Product`.

## 3. Database Model

Canonical records are `ai_product_jobs`, `ai_product_job_items`, `ai_product_drafts`, `ai_request_logs`, content versions, and apply audits. Drafts retain normalized payload, field status, validation evidence, warnings, token usage, approval identity/hash, and apply timestamps. No raw provider payload is exposed by the new UI.

## 4. State Machine

Current and target contracts are in `artifacts/ai_product_current_state_machine.csv` and `artifacts/ai_product_target_state_machine.csv`. Queue retry now uses `FAILED -> QUEUED -> RUNNING`; direct `FAILED -> RUNNING` is prohibited. Parent aggregation publishes both legacy and canonical terminal state.

## 5. Provider Contract

The controlled provider was `custom / gemini-2.5-flash`. This adapter supplied total tokens only; input/output split was unavailable. The UI value therefore means provider-reported total tokens, not generated output length.

## 6. Parsing

Both controlled responses passed provider transport and parser. Job #59 reached hard fact checking; Job #60 reached review. Parse/schema/system errors remain hard failures.

## 7. Draft Persistence

Job #59 persisted blocked draft #13. Job #60 persisted review draft #14. Product count and Product content hash were unchanged before Apply.

## 8. Field Mapping

The canonical map is `artifacts/ai_product_field_contract.csv`. Job #60 persisted 11 field states: content had an editorial warning, Merchant description had a warning, internal links were skipped, and the remaining generated fields were valid.

## 9. Quality Validation

`content_too_short` and related content-structure/SEO/Merchant/FAQ findings are editorial warnings when usable output exists. Job #60 contained 390/800 words and correctly became `needs_review`, not `failed`.

## 10. Hard Blocks

Technical safety was not weakened. Job #59 was blocked after real generation because fact-check found ambiguous `2,400 BTU` and `9,900 BTU` claims. The draft remains evidence and cannot be approved through the normal soft-warning path.

## 11. Concurrency

Product Edit now preflights an actionable draft or active item before creating a job. Job creation locks the Product row. Every explicit generation receives a unique operation identity, so terminal historical failures no longer masquerade as active duplicates while concurrent double-clicks remain bounded.

## 12. Review Workflow

Product Edit now exposes `Xem bản nháp AI`, approval, rejection with reason, explicit regeneration, logical discard, and Apply for approved drafts. A second normal Generate against a reviewable draft creates no blocked-job spam.

## 13. Approval

Approval remains explicit and stores reviewer, timestamp, payload hash, technical-context hash, Product identity, approved fields, and review note.

## 14. Warning Override

When warnings exist, the action is labelled `Duyệt kèm cảnh báo`, shows the draft and warning list, requires confirmation, and records `[WARNING_OVERRIDE]` plus the warnings in the review note. The warning evidence remains on the draft.

## 15. Reject

Reject requires a reason, sets terminal `rejected / REJECTED`, keeps all provider and draft evidence, and no longer blocks future generation.

## 16. Regenerate

Regenerate marks the prior draft rejected with an audit reason and creates a new operation generation. It never overwrites the historical draft/job.

## 17. Discard

Discard is logical only: `discarded / DISCARDED` plus a required note. No provider log, job, item, or draft row is deleted.

## 18. Apply

Only an approved draft can Apply. Payload hash, technical context, Product identity, authorization, allowed fields, and fact-check are rechecked. Soft warning approval does not re-block Apply; technical hard blocks still do. PHPUnit proves same Product ID, unchanged Product count, atomicity, stale-context protection, and idempotent double Apply.

## 19. Job Finalization

Terminal statuses include completed variants, failed, blocked, needs-review, and cancelled. A parent with all terminal children can no longer remain processing. Review children aggregate to `needs_review / REVIEW_REQUIRED`; failures aggregate to `completed_with_errors / FAILED`.

## 20. Historical Jobs

Job #56 actual DB state is `completed_with_errors`; item #178 is historical `FAILED`, draft #12 contains usable output and 11 field states, and total tokens are 5,537. It was not reclassified, retried, deleted, approved, or applied. Item #179 had a stale canonical `RUNNING` projection despite legacy `failed`; it was reconciled through the state machine to `FAILED` without changing outcome or content.

## 21. Product Panel UX

The latest actionable draft drives Preview and review actions. Field coverage comes from persisted draft field states. A reviewable draft no longer appears merely as an unexplained blocked/unknown state.

## 22. Job Detail UX

The generic Save action is removed from the system-managed job detail page. Item rows offer draft preview and a link to the Product review workflow; technical diagnostics remain secondary.

## 23. RBAC

Generate/regenerate require `product.ai_generate`; approve/reject/discard require `bulk_ai_approve`; Apply requires `bulk_ai_apply`. Apply and approval services also enforce server-side authorization. The full actor-by-actor browser matrix remains a release blocker.

## 24. Real Provider Evidence

- Job #58: blocked before provider, zero tokens; exposed the terminal-failure idempotency bug.
- Job #59 / item #181 / Product #1319: provider success, 5,432 total tokens, draft #13, hard fact-check block.
- Job #60 / item #182 / Product #1320: provider success, 5,740 total tokens, draft #14, `REVIEW_REQUIRED`, browser preview PASS.

The detailed ledger is `artifacts/ai_product_real_provider_test_ledger.csv`.

## 25. Browser Evidence

The real-provider Product Edit scenario passed on Product #1320: login, Generate, governed queue, worker, provider, terminal review state, persisted draft, preview, no relevant console/network error, Product count unchanged, and Product content hash unchanged. The complete ordinary Playwright run was 10 total: 9 passed and 1 intentionally skipped real-provider test. The separately authorized real-provider run was 1 passed.

## 26. Worker

Before real generation: desired `ENABLED`, actual `ONLINE/RUNNING`, queue `ai_governed`, deployment `UP_TO_DATE`, application/worker version `1.31.5`, pending 0, processing 0, stuck 0. After generation, pending/processing/stuck returned to 0. Scheduler heartbeat is stale and was not enabled by this audit.

## 27. Performance

Status polling reads persisted state and does not call the provider. Product generation preflight uses bounded latest/exists queries. No provider polling or draft payload list rendering was added.

## 28. Tests

- Focused AI/catalog/Filament: 86 tests, 85 passed, 1 skipped, 351 assertions.
- Full PHPUnit: 487 tests, 486 passed, 1 skipped, 3,004 assertions, 0 failures/errors.
- Playwright ordinary suite: 10 total, 9 passed, 1 intentional provider skip.
- Playwright authorized real-provider scenario: 1 passed.
- Composer validate/audit, npm audit, Vite build, Laravel caches, PHP lint, and `git diff --check`: PASS.

## 29. Known Limitations

- Historical Job #56 remains historical failed evidence and is not automatically promoted to review.
- Draft #14 remains pending explicit operator disposition; no Apply was performed.
- Browser approve/reject/regenerate/discard/apply and exhaustive RBAC scenarios were not all executed.
- Scheduler/watchdog are not currently running locally; managed queue worker is online.

## 30. Release Readiness

Core runtime defect resolution and generation-to-review path: PASS. Complete module certification: PARTIAL. `READY_FOR_GITHUB_RELEASE = NO` until the remaining browser action/RBAC matrix is certified. No commit, tag, push, bulk retry, Product Apply, or catalog write was performed.

## 31. Final Browser Action and RBAC Certification

The isolated Product action/RBAC suite now passes all 12 scenarios together. Preview, approval, warning override, reject, logical discard, regenerate, Apply, double-Apply protection, stale-target handling, hard fact blocking, role visibility, server authorization, desktop hierarchy, and 390px layout are certified. Temporary Products and browser users are removed after the run.

## 32. Apply Confirmation Contract

### Symptom and root cause

The single-operator policy requires the exact confirmation `APPLY <model_code>#<product_id>`. The Product Edit Apply action previously invoked `AIProductDraftApplyService::apply()` with the default `null` confirmation, so the domain guard correctly returned `APPLY_CONFIRMATION_REQUIRED`. Approval and Apply authorization are intentionally separate decisions; the backend guard was not removed.

### New flow

`APPROVED` → operator clicks **Áp dụng** → modal shows approved fields, protected fields, warning classification and technical blockers → operator types the exact confirmation → service rechecks permission, approval, payload hash, technical context, Product identity, Product content hash, fact safety and the allowlist → Product and draft rows are locked → content-layer fields update atomically → draft/item/job/Product workflow projections become terminal `APPLIED`.

Normal operator errors are now human-readable. `APPLY_CONFIRMATION_REQUIRED` maps to “Bạn cần xác nhận trước khi áp dụng nội dung AI”; `STALE_PRODUCT_CONTENT` maps to a safe stale-target explanation. Machine codes remain secondary diagnostics.

### Warning and hard-block semantics

Editorial warnings can be explicitly overridden during approval and remain snapshotted. `unverified_technical_claim:<value>` is a hard Apply blocker when that exact value remains in an applicable draft field. If the value was removed or rewritten, it remains technical audit evidence without blocking. `blocked_claims`, contradicted facts and other canonical fact-check blocks remain fail-closed.

The real Draft #14 for Product #1320 is approved but not Apply-ready: seven unverified technical values remain in its content (`242 mm`, `244 mm`, `40 đến 46 dB`, `0.7 kW`, `19 dB`, `46 dB`, `16mm`). Apply is therefore hidden and the preview explains the blockers. Product #1320 was not mutated; none of those seven claims exists in its current canonical Product content.

### Shared readiness and preview

`ProductAiApplyReadiness` is the canonical UI resolver for approval state, confirmation, hard blockers, soft warnings, processed technical warnings, approved fields and stale target. Product header actions and the preview/confirmation modal consume the same result. The preview leads with generated content and concise warning counts; raw warning codes and used facts are collapsed under technical detail.

### Proof

- Focused confirmation/readiness/Filament/RBAC tests cover absent confirmation, valid confirmation, warning override, technical block, stale target, idempotency, authorization and field allowlisting.
- Playwright verifies the confirmation modal, same Product ID/count, applied Content/SEO/Merchant/FAQ, double-Apply removal, stale-target UX and editable RichEditor persistence after Apply.
- No real-provider call was needed for this defect; existing Jobs #59/#60 remain provider evidence and historical Draft #14 remains unchanged.

## 33. Current Final Verdict

- Apply confirmation/readiness focused matrix: PASS.
- Full PHPUnit: 506 tests, 505 passed, 1 skipped, 3,125 assertions, 0 failures/errors.
- Complete Playwright action/RBAC suite: 12/12 passed in one controlled run, including confirmation Apply, stale target, RichEditor post-Apply persistence, desktop and mobile.
- Composer validation/audit, npm audit, Vite build, Laravel caches, PHP lint and `git diff --check`: PASS.
- Product #1320 hash remains `4baa9dce746b7848d0a298e77549ef8f01062a16344e38a5dfd1494997c1c386`; Draft #14 remains approved but unapplied. No production-like Product was changed.
- Browser fixture cleanup now removes its queued `AiProductContentSingleJob` payloads before deleting fixture domain rows. Three proven orphan fixture queue rows were removed; final governed pending/processing/stuck are all 0 and the desired-enabled managed worker is ONLINE/UP_TO_DATE.

`AI PRODUCT APPLY WORKFLOW = PASS`. `AI PRODUCT MODULE = PASS`. `READY_FOR_GITHUB_RELEASE = YES` for this module. This task did not commit, tag or push.
