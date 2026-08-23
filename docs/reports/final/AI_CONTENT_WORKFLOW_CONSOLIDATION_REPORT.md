# AI Content Workflow Consolidation Report

Date: 2026-08-23
Scope: Filament admin AI workflow only
Verdict: **PASS**

## 1. Current duplicated flows

The code audit confirmed one material duplicate entry point:

| Capability | PostResource | AiContentJobResource | ProductResource | AiProductJobResource | Provider/Queue |
|---|---|---|---|---|---|
| Create entity | Manual Post form | Previously created a standalone blog job and later created a Post | Existing Product | No create page | No |
| Generate | Previously absent | Primary blog generation entry | Existing Product actions | Operational retry/history | No |
| Review/apply | Previously absent | `publish_to_post` created a new Post | Existing governed draft workflow | Operational review | No |
| Status/log | Basic AI flags only | Status, retry, technical log | Live Product status | Batch/item history | Provider and queue diagnostics |

`AdminContentAutomation` is a separate synchronous form-preview helper used by Brand, Promotion and Product Category. It is not the Post generation backend and was not duplicated into this workflow.

## 2. Code evidence

- Post UI: `app/Filament/Resources/Posts/`.
- Blog AI operation: `AiContentJob`, `GenerateBlogDraftJob`, `HVACSeoContentEngine`, `AIManager`.
- Product AI operation: `AiProductJob`, `AiProductJobItem`, governed product jobs and draft tables.
- Provider evidence: `AiProvider`, `AiRequestLog`, `AIManager`.
- Worker state: `AIQueueMonitor::liveStatusHealth()` and persisted worker heartbeat/desired-state data.
- Existing blog outputs are persisted in `output_draft`, `output_meta`, `output_faq`, `output_tags` and `output_internal_links`.
- Blog states are persisted by `AIContentJobStatus`; UI labels are presentation-only.

## 3. Chosen canonical workflow

Business entities are now the primary entry points:

```text
Post create/edit
  -> create governed AiContentJob
  -> ai_governed queue
  -> GenerateBlogDraftJob
  -> persisted AI draft
  -> compare/review
  -> explicit approval
  -> allowlisted apply to Post

Product edit
  -> existing governed Product AI operation
  -> persisted field draft
  -> review/approval/apply
```

AI job resources remain available as operational history. They are no longer the normal content-creation workflow.

## 4. Post AI flow

- Create Post offers **Save draft & generate with AI**.
- Edit Post offers **Generate with AI** or **Regenerate with AI** depending on existing content.
- Input options reuse the existing HVAC category/audience vocabulary and Product/Brand context.
- A new operation references the target through `input_payload.target_post_id`; no schema migration was needed.
- Generation never overwrites the Post. The current content hash is recorded in operation context.
- Compare renders Current and AI Draft as escaped text.
- Approval changes only the AI job review state.
- Apply is explicit and idempotent (`NOOP_ALREADY_APPLIED`) and writes only requested content-layer fields plus selected tag/FAQ relationships.

## 5. Product AI flow

Product remains the primary Product AI entry point. The existing governed draft/review/apply, field independence, budget, idempotency and rollout controls were retained. The Product job page was relabeled as operational history; no second Product AI backend was introduced.

## 6. Job/history role

- `AiContentJobResource`: **Nhật ký AI bài viết**.
- `AiProductJobResource`: **Nhật ký AI sản phẩm**.
- Both are under **Vận hành** with queue/worker diagnostics.
- Direct create is disabled for the article history resource.
- The former primary dispatch action is hidden; policy-controlled retry remains for failed/stuck/cancelled operations.
- Legacy completed jobs without `target_post_id` retain a permission-limited recovery action so historical functionality is not destroyed.
- Technical logs remain available as a secondary gray utility action and are escaped.

## 7. Provider readiness

`AiProviderReadinessService` provides one read-only presentation contract:

- configured credential/endpoint;
- enabled state;
- connection state derived from persisted success/error timestamps;
- model and safe endpoint host;
- last check and last successful call;
- quota capability.

The provider table no longer reveals an API-key suffix. Connection testing is explicit and never triggered by polling. Existing adapters have no dedicated health endpoint, so the UI states that the action sends one minimal model request.

## 8. Worker/queue readiness

All new and recovered AI dispatches use `ai_governed`. Remaining blog/product recovery paths were moved off the legacy `ai` queue. A queued operation with desired worker state `DISABLED` displays that the request is stored but cannot be processed yet. Processing with stale/offline heartbeat is presented as potentially interrupted.

## 9. Live status UX

The Post panel polls every 10 seconds and reads only:

- latest target job;
- persisted state and timestamps;
- requested/output field presence;
- the latest request-log evidence;
- bounded provider readiness;
- bounded worker/queue snapshot.

Single-operation progress is step-based (waiting, writing, validating, review). No artificial percentage is generated. Product and job tables retain bounded 10-second polling with persisted batch/item counts.

## 10. Provider-call evidence

The deterministic context ID `hvac_blog_job_{job_id}` links a Post operation to `AiRequestLog`. UI states are **Not sent**, **Retrying**, **Completed** or **Failed**, with attempt count. Raw prompts, responses, headers and secrets are not exposed.

## 11. Credit/quota capability

Current provider adapters do not expose a trustworthy remaining-credit endpoint. The canonical UI therefore reports:

> Credit/Quota: Not provided by provider

Configured local daily/minute limits remain runtime policy counters, not provider billing credit.

## 12. Review/apply UX

- Ready drafts expose compare and approve actions.
- Apply appears only after approval.
- Existing Post content is unchanged during generation and approval.
- Regeneration creates a new operation and draft.
- Technical failures use safe reason mapping; full logs remain operator diagnostics.

## 13. Menu changes

| Before | After | Reason |
|---|---|---|
| AI article jobs under AI Content | Article AI history under Operations | Operational history, not content authoring |
| AI Product jobs under AI Content | Product AI history under Operations | Product is the primary workflow |
| AI Providers under AI Content | AI Providers under Operations | Infrastructure/configuration ownership |
| Create AI article job | Hidden/denied | Removes the duplicate creator flow |

## 14. Security

- Post generation requires both entity and AI-create permissions.
- Review/apply requires Post edit and AI job view permissions.
- Target Post identity is checked before approval/apply.
- Polling authorizes server-side and does not expose provider secrets, prompts, raw response bodies or stack traces.
- Legacy Post creation is limited to untargeted historical jobs and requires `post.create`.

## 15. Performance

- Poll interval: 10 seconds.
- No provider call occurs during polling.
- The status panel uses one latest job, one latest request log, aggregate attempt count, one bounded health snapshot and one bounded provider inventory.
- No new index or cache was introduced without query-plan evidence.

## 16. Tests

Focused consolidation coverage proves:

- Post entry creates a governed operation on `ai_governed`;
- no direct Post overwrite;
- explicit approval and idempotent apply;
- persisted queued/review status updates through Livewire polling;
- provider configured/connectivity/quota honesty;
- AI article history cannot create duplicate operations;
- existing Product live-status and queue/provider gateway regression tests remain green.

Final validation: **342 tests, 1,147 assertions, 0 failures/errors, 1 existing skipped test**. Focused consolidation coverage: **6 tests, 23 assertions**. PHP lint, route cache, Blade cache and `git diff --check` passed.

## 17. Browser proof

No Playwright/Dusk harness or safe authenticated CDP transport was available for this run. No browser PASS is claimed. Livewire component tests prove the poll-refresh transition from persisted database state without a full page reload.

## 18. Remaining limitations

- Provider credit cannot be verified automatically because current adapters expose no credit/quota API.
- Explicit **Test connection** may perform a minimal provider request; it was not executed during this task.
- Historical untargeted AI article drafts keep a recovery-only conversion action to avoid destructive loss of old functionality.
- Worker remains intentionally disabled; queued work will not execute until enabled by an operator with `ai_worker.manage`.

## 19. AI Worker admin control

The `Trạng thái vận hành AI` page now controls only the existing canonical desired state. It never spawns or kills a worker from HTTP. The managed child checks desired state before every new queue claim and emits a truthful `paused` heartbeat while its OS-managed process remains online. Enable/disable actions require `ai_worker.manage`, create bounded audit events, and Product/Post generation notifications use the same readiness service. Full evidence and deployment commands are recorded in `AI_WORKER_ADMIN_CONTROL_REPORT.md`.

## Data and runtime safety

- Products / catalog sources / catalog models / catalog fields: **81 / 212 / 36,453 / 656,507**.
- Canonical BTU hash: `3e981c60fcadd3461746fd8f3b94855dc5205bad6c446c55c17066d40c47e3ba`.
- Product/catalog technical writes: **0**.
- Provider calls during implementation and validation: **0**.
- AI worker: **DISABLED_BY_OPERATOR**.
