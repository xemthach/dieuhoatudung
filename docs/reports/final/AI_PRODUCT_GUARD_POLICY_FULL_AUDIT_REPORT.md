# AI Product Guard Policy Full Audit Report

Date: 2026-08-31

Application: `dieuhoa-tudung`

Application/worker version: `1.32.0`
Final verdict: **PASS**

## 1. Executive Verdict

The AI Product guard pipeline now has one policy source for configurable editorial rules, immutable fail-closed safety families, shared single/bulk generation preflight, terminal event evidence, and a validator registry that is independent from prompt exposure. A final real-provider job reached `REVIEW_REQUIRED` through `ai_governed`; no Product field was changed before Apply.

No historical job was deleted or rewritten. No bulk retry, commit, tag, or push was performed.

## 2. Baseline and Scope

- Laravel 13.26.1, PHP 8.3.16, local environment.
- 95 migrations ran, 0 pending.
- Worker desired `ENABLED`, actual `ONLINE/RUNNING`, queue `ai_governed`.
- App and worker version/build aligned; deployment `UP_TO_DATE`.
- Queue after certification: pending 0, processing 0, stuck 0.
- Local scheduler and watchdog are offline. The tested request/worker path does not require them; production deployment must still verify both.

## 3. Architecture and Guard Stages

Canonical flow:

```text
Product Edit/List
  -> RBAC + rollout
  -> ProductAiGenerationReadiness
  -> ProductContentEligibilityPolicy
  -> job/item dispatch on ai_governed
  -> provider gateway
  -> parser/normalizer
  -> AiGuardPolicy editorial decision
  -> HVAC technical fact validation
  -> persisted draft/evidence
  -> REVIEW_REQUIRED or terminal BLOCKED/FAILED
  -> approval/apply readiness
  -> confirmed, stale-safe, allowlisted Apply
```

The detailed stage contract is in `artifacts/ai_guard_stage_matrix.csv`.

## 4. Guard Inventory

The inventory covers authorization, rollout, worker/provider readiness, minimum Product identity, active conflicts, duplicate dispatch, parser/schema integrity, editorial quality, technical fact safety, stale targets, Apply confirmation, and forbidden Product fields.

Source artifact: `artifacts/ai_guard_inventory.csv`.

## 5. Canonical Policy

`App\Services\AI\AiGuardPolicy` resolves canonical codes to:

- `BLOCK`: terminal stop with evidence.
- `WARN`: reviewable output with visible warning.
- `IGNORE`: omitted from operator-facing outcome while retained in guard diagnostics.

Every job stores `guard_policy_version` and `guard_policy_snapshot`, making the decision reproducible after settings change.

## 6. Locked Safety Rules

Authorization, permissions, stale/concurrency guards, duplicate protection, Apply guards, fact-check blocks, contradicted/ambiguous/unsafe facts, forbidden fields, parser/schema integrity and cross-Product safety always resolve to `BLOCK`.

Neither settings storage nor a crafted admin request can weaken these families. Tests cover the service-level lock and HTTP 403 settings denial.

## 7. Configurable Editorial Rules

The admin may configure `CONTENT_TOO_SHORT`, `MISSING_H2_H3`, `MISSING_SEO`, `MISSING_MERCHANT`, and `MISSING_FAQ` as `BLOCK`, `WARN`, or `IGNORE` under Website Settings -> AI Guard Policy.

Defaults remain `WARN`. Changes require `settings.edit` and write old/new values, actor and policy version to technical logs. Locked rules are displayed read-only.

Source artifact: `artifacts/ai_guard_policy_matrix.csv`.

## 8. Generation Preflight

`ProductAiGenerationReadiness` is shared by Product Edit, selected bulk generation and filter/current-page generation. It combines:

- Product content eligibility;
- active draft/apply conflicts;
- worker readiness;
- provider readiness.

Known blockers stop before job creation and provider use. Single Product notifications show every blocker, not only the first, and a sanitized `generation_preflight_blocked` event records Product, actor, guard codes, next actions and `provider_called=false`.

## 9. Duplicate and Concurrency Protection

Active conflict detection is centralized in `ProductContentEligibilityPolicy::activeConflictProductIds()`. Batch preflight excludes actionable conflicts with bounded queries. Dispatch retains a lock/idempotency check for races. Terminal duplicate outcomes emit `item_blocked`; historical duplicate jobs remain evidence.

## 10. Provider Readiness

Missing provider configuration is `PROVIDER_NOT_CONFIGURED` and blocks before a job/provider call. Provider/model information is not used as a substitute for runtime readiness.

## 11. Worker Readiness

Missing or stale worker heartbeat is `WORKER_OFFLINE`. Final runtime evidence:

- desired: `ENABLED`;
- actual: `ONLINE`, process `RUNNING`;
- queue: `ai_governed`;
- deployment: `UP_TO_DATE`;
- app/worker version: `1.32.0` / `1.32.0`;
- pending/processing/stuck: `0/0/0`.

## 12. Parser and Persistence

Parser/schema integrity remains locked. Failed or blocked generation persists sanitized output, provider/model/tokens, field status, warnings and structured validation errors. It does not silently collapse a parse or validation failure into an empty payload.

## 13. Editorial Validation

Content length and completeness thresholds remain intact. The policy changes disposition, not measurements:

- in `WARN`, a short draft reaches review with `content_too_short` evidence;
- in `BLOCK`, it stops with evidence;
- in `IGNORE`, diagnostics remain but the operator outcome does not claim a warning.

Requested-output scope remains respected; a non-requested field does not become a false missing-field failure.

## 14. Technical Fact Safety

Prompt exposure and validator authority are now separate contracts:

- category `use_for_ai` decides which verified facts may be sent to the provider;
- `VerifiedFactRegistry` contains every source-verified Product fact needed to validate any provider-emitted claim.

This closes the systemic bug where a real response could use a correct source-backed range but the validator could not see its authority.

## 15. Capacity Semantics Root Cause

Three proven defects were corrected:

1. Range wording such as `từ 2.400 BTU đến 9.900 BTU` was not consistently classified as a technical capacity range.
2. The validator registry was filtered by the prompt `use_for_ai` list, hiding verified min/max facts.
3. An exact authoritative technical value such as `9.200 BTU` remained ambiguous when wording omitted “danh định”, even though the resolver had a unique verified technical value.

The final logic verifies exact authoritative values and exact verified range bounds, but continues to block unmatched, contradicted, legacy-only, or truly ambiguous claims.

## 16. Legacy Schema Compatibility

The prompt mapping accepts both legacy `capacity_btu` and explicit `technical_capacity_btu` category schema keys. This preserved the pre-existing schema contract and fixed the only first-pass full-suite regression.

## 17. Warning and Block Evidence

Hard fact outcomes now persist critical entries in `validation_errors`. This fixes the historical contradiction where a row showed `BLOCKED` while the detail UI reported zero hard blockers. Editorial and critical evidence are separately classified for UI and approval governance.

## 18. Terminal Observability

Single-item jobs emit terminal technical events for blocked, failed, review-required, completed and cancelled outcomes. Events include job/item/Product IDs, reason, guard stage, provider-called state and draft ID without raw provider payload or secrets.

## 19. Historical Blocked Samples

Historical jobs #55, #58 and #59 were inspected and preserved. Controlled jobs #1083, #1084 and #1085 intentionally captured successive root causes during repair. Their state was not rewritten. The exact matrix is in `artifacts/ai_guard_blocked_root_cause_matrix.csv`.

## 20. Real Provider Certification

Four controlled calls used inactive local fixtures only:

- provider/model: `custom` / `gemini-2.5-flash`;
- total provider-reported tokens: 33,510;
- no bulk generation;
- no Product mutation before Apply;
- request/draft/job evidence preserved.

Final proof: Product #2875, Job #1116, Item #1235, Draft #1049, Request log #268, 5,495 total tokens, final `REVIEW_REQUIRED`. The 416/800 content result and Merchant gap remained truthful editorial warnings; critical validation errors were empty.

Source artifact: `artifacts/ai_guard_real_provider_ledger.csv`.

## 21. Browser Certification

Final controlled browser runs:

- 14 passed;
- 0 failed;
- 1 intentional skip for a legacy non-rollout RBAC presentation case superseded by the enforced single-operator rollout test.

Covered policy edit/restore, locked controls, unauthorized 403, preview, clean approval, warning approval, reject, logical discard, Apply/double-Apply, stale target, duplicate generation, hard fact block, rollout denial, responsive action hierarchy, and real-provider generation to review. No relevant console, page, network, Livewire, or HTTP 500 error was observed.

Source artifact: `artifacts/ai_guard_browser_matrix.csv`.

## 22. Performance and Database Safety

Read-only preflight probe:

| Scenario | Selected | Queries | Duration |
|---|---:|---:|---:|
| Single | 1 | 20 | 28.47 ms |
| Bulk 10 | 10 | 21 | 16.02 ms |
| Bulk all | 362 | 21 | 74.32 ms |

The query count is bounded rather than proportional to Product count.

Final table counts: Products 362; AI jobs 52; items 38; drafts 18; request logs 265; bulk operations 39; bulk items 129; catalog models 36,453; catalog fields 656,507. The controlled provider tests left four explicitly named inactive Product evidence fixtures and their AI evidence. Catalog model/field counts were unchanged by this certification.

## 23. Regression, Build and Security

- Focused AI/guard/catalog suite: 162 tests, 161 passed, 1 skipped, 572 assertions, 0 failures/errors.
- Full PHPUnit: 525 tests, 524 passed, 1 skipped, 3,231 assertions, 0 failures/errors, exit code 0, 130.484 seconds.
- Composer validate strict: PASS.
- Composer audit: no advisories.
- npm audit high: 0 vulnerabilities.
- Vite production build: PASS.
- Config, route and view caches: PASS.
- PHP lint: 23 changed/new PHP files, PASS.
- `git diff --check`: PASS.
- Migrations: 95 ran, 0 pending.

Full output is retained at `storage/logs/full-phpunit-ai-guard.txt`.

## 24. Final Verdict and Release Impact

`AI PRODUCT GUARD POLICY = PASS`

The release-blocking defect—provider output consuming tokens while valid verified facts were invisible to the validator—is fixed and covered by fake, persisted-response replay, real-provider, worker, browser, focused and full-suite evidence.

Remaining operational note: production deployment must start/verify scheduler and watchdog according to the live runbook. This is a deployment gate, not a failure of the local AI Product generation path.

No commit, tag or push was performed.
