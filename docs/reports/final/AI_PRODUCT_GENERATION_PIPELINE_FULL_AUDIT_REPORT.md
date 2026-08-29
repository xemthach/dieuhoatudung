# AI Product Generation Pipeline — Full Audit

## 1. Executive verdict

**AI PRODUCT GENERATION PIPELINE = PARTIAL / STOP**

The code audit proves a downstream evidence-loss defect for content-length validation. The provider can return a non-empty JSON payload and consume tokens, but `AIProductContentSystem::normalizePayload()` validates before `persistDraft()`. When `CONTENT_TOO_SHORT` is thrown, the job catch path calculates score and warnings from the unchanged Product, so the operations table can show a low score and all `missing_*` warnings while the provider response is not attached to the item.

The fix is implemented locally and covered by a regression test. Existing historical rows were not rewritten, retried, deleted, or provider-called.

## 2. Observed failure pattern

The supplied screenshot reports 50 items and approximately 5,000–5,300 tokens. The current local database is not that production snapshot: it contains 36 jobs, 22 items, and 7 drafts. Therefore the screenshot counts are not asserted as local facts and must be rechecked on live with read-only queries.

Local persisted evidence includes successful `product_content` request logs with `tokens_total` values such as 5,095, 5,270, 5,677 and 6,074. Some corresponding items have real `generated_payload_json` and drafts; items that fail before persistence have no payload/draft. This is consistent with the proven ordering defect.

## 3. Architecture and sequence

```text
Filament action
  -> AiProductJob / AiProductJobItem
  -> ai_governed queue
  -> AiProductContentSingleJob
  -> AIProductContentSystem::generate
  -> AIManager::generate
  -> provider adapter (OpenAI/Claude/Gemini/Fake)
  -> AIJsonResponseParser::parse
  -> normalizePayload + sanitizer + validation
  -> persistDraft
  -> fact-check / score / review or apply
```

The governed worker command is `php artisan ai:managed-worker --queue=ai_governed --sleep=3 --tries=3 --timeout=900`. The earlier local snapshot showed desired state `ENABLED`, actual worker `OFFLINE`, and `VERSION_MISMATCH`; that is historical evidence, not the current state.

## 4. Token semantics

- OpenAI adapter: `usage.total_tokens`.
- Claude adapter: `input_tokens + output_tokens`.
- Gemini adapter: `usageMetadata.totalTokenCount`.
- `AIManager` stores this as `tokens_used` and `ai_request_logs.tokens_total`.
- `tokens_input` and `tokens_output` are not populated by the current Gemini path.

Therefore the UI value is a provider-reported total token count, not generated-content length. It must not be interpreted as proof that the output had 5,000 generated tokens.

## 5. Parser and response contract

The parser accepts direct JSON, fenced JSON, and an extracted JSON object/array. Invalid or empty required JSON throws; it is not silently converted to `{}`. The canonical response fields are recorded in `artifacts/ai_product_field_contract.csv`. The prompt requires a JSON `content_layer` envelope with content, SEO, OG, Merchant, tags, FAQ, internal links and media metadata.

## 6. Proven root cause

Before the fix, the order was:

```text
provider success + token log
  -> normalizePayload()
  -> validatePayload()
  -> CONTENT_TOO_SHORT exception
  -> job catch computes score/warnings from current Product
  -> persistDraft() is never reached
```

This explains the combination of non-zero tokens, `score_before = score_after`, empty payload/draft evidence, and repeated `missing_content`, `missing_h2_h3`, `missing_seo`, `missing_merchant`, `missing_faq` warnings. Those warnings describe the unchanged Product, not necessarily the provider payload.

## 7. Validation contract

The content hard floor remains unchanged. Content below 75% of the minimum throws `CONTENT_TOO_SHORT`; content between 75% and the minimum receives a warning. FAQ requires at least three entries when requested. H2/H3 structure is enforced by the structure validator. Fact-check and forbidden Product-data fields remain fail-closed.

## 8. Fix

`AIProductContentSystem` now preserves sanitized output and validation evidence when the specific content-length validation failure occurs. It stores:

- sanitized normalized payload;
- draft with status `failed`;
- field-level status;
- structured validation warning;
- provider/model/token metadata.

The original exception remains authoritative, the Product is not mutated by this evidence path, and other validation failures continue to fail closed without broad exception swallowing.

## 9. Blocked versus failed

`BLOCKED` remains distinct from `FAILED`. Local evidence includes `DUPLICATE_IN_PROGRESS`, `BLOCKED_STALE_RECOVERY`, and fact-check states. These are not retried or rewritten by this audit. The canonical state machine is in `artifacts/ai_product_state_machine.json`.

## 10. Current UI finding

The Filament relation table previously exposed too many technical columns by default and rendered raw warning codes. It now shows compact generated-field coverage, keeps Data% as a technical toggleable column, and maps known warning codes to readable Vietnamese labels while preserving raw codes in the tooltip. The audit does not change status history or fabricate Data%/Fact-check values. Data completeness is a governance-context score; Fact check is the persisted payload fact-check status. A missing payload legitimately renders those fields as unavailable, but after the fix failed content-length items retain actionable draft evidence.

## 11. Runtime and safety

The initial local `ai:queue-health --json` snapshot reported:

- queue connection: `database`;
- queue: `ai_governed`;
- desired worker state: `ENABLED`;
- actual worker: `OFFLINE`;
- scheduler: not running / stale heartbeat;
- pending jobs: 2;
- failed jobs: 13;
- provider calls before the authorized probe: 0; one authorized real-provider probe was run afterward on a transactional fixture.

That initial snapshot was not healthy enough for an asynchronous retry. It has since been reconciled on localhost by the operator.

### Local worker verification after operator enablement

The subsequent read-only health check confirmed:

- desired worker state: `ENABLED`;
- actual worker: `ONLINE`, `RUNNING`, accepting new jobs;
- deployment: `UP_TO_DATE`;
- application and worker version: `1.31.1`;
- queue: `ai_governed`;
- pending jobs after processing: `0`.

The worker processed the remaining queued item without a provider call. It ended as `FAILED` with `CONTENT_ELIGIBILITY_BLOCKED:ACTIVE_DRAFT_OR_APPLY_CONFLICT`, which is an intentional eligibility guard and not a parser failure. No historical item was rewritten and no retry batch was started.

### Authorized single-provider probe

One real provider request was executed only after explicit operator authorization. It used a temporary Product fixture inside a database transaction with `draft_only_strict=true`; the transaction was rolled back after inspection. The provider was `custom / gemini-2.5-flash`, `tokens_total=4764`, the response parsed as JSON, and the generated content measured 344 words. The pipeline returned `CONTENT_TOO_SHORT: 344/800`, while the fixed code persisted a failed draft containing the sanitized payload and validation evidence. No persistent Product content or catalog data remained after rollback. This was a synchronous pipeline proof.

## 12. Existing failed-job disposition

No historical job was changed. The controlled plan is:

- failed after provider success but before draft persistence: `REGENERATE_REQUIRED` after deploy and explicit operator approval;
- transient provider/network failures: `RETRY_SAFE` only after worker/provider gates;
- stale recovery, duplicate, permission, eligibility or budget blockers: `NOT_RETRYABLE` until their blocker is resolved;
- existing review drafts: `HISTORICAL_ONLY`.

See `artifacts/ai_product_retry_disposition.csv`.

## 13. Tests

- Focused AI Product suite: 39 passed, 134 assertions.
- Full PHPUnit suite: 478 tests, 477 passed, 1 skipped, 2,961 assertions, 0 failures/errors.
- New regression: `test_content_length_failure_preserves_sanitized_output_and_validation_evidence`.

## 14. Release impact

The defect is release-blocking for AI Product generation if confirmed on live at the same code path, because provider usage can be incurred while downstream evidence is discarded. Do not retry the existing production items until this fix is deployed, worker/runtime health is restored, and a fake/no-provider integration proof passes.

## 15. Final verdict

`AI PRODUCT GENERATION PIPELINE = PARTIAL / STOP`

Local code fix and regression coverage: **PASS**.

Live 50-item classification, live provider-response correlation and browser certification remain **BLOCKED pending live evidence**. Local worker recovery is now **PASS**; the queue health result is recorded above.

### Authorized worker cross-process probe

A second, single controlled call was dispatched through `ai_governed` and claimed by the managed worker. The worker and application were both `1.31.1`, `UP_TO_DATE`, and the request log was retained as ID `239`. The real response was accepted by the gateway/parser, reported `4553` total provider tokens, and produced `CONTENT_TOO_SHORT: 376/800`. The item persisted a failed draft (`draft_id=9`), provider metadata, and validation evidence; the temporary Product, job, item, and draft were then removed after the evidence was captured. No Product or catalog mutation remained. The call ledger is `artifacts/ai_product_real_provider_test_ledger.csv`.

No bulk retry, historical mutation, catalog mutation, history deletion, commit, tag, or push was performed for this audit. One authorized real-provider probe was performed as documented above.
