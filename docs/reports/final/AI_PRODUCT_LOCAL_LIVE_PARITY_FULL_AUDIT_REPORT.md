# AI Product Local / Live Parity Full Audit Report

Audit date: 2026-08-31. Release remains frozen. Historical AI evidence was preserved, no bulk generation was executed, and no Product content was applied.

## 1. Executive Verdict

Local AI Product is **PASS**. Local ↔ Live parity is **PARTIAL** because only Live HTTP/version/charset/auth-redirect behavior is observable. Live Git HEAD, migrations, settings, policy cache, database state, provider, queue, worker, scheduler and watchdog cannot be verified without the reviewed production access channel. Therefore GitHub release and Live deployment remain **BLOCKED**.

## 2. Local Runtime

- HEAD/build: `8afea4f78a8ecb7526f25180542f1fb53fcc0903`.
- Version: `1.32.1`; PHP `8.3.16`; Laravel `13.26.1`.
- Queue: `database / ai_governed`; desired `ENABLED`; actual `ONLINE`; deployment `UP_TO_DATE`.
- Pending/processing/stuck: `0/0/0`; worker was restarted and has a fresh heartbeat.
- Policy: `ai-guard-policy-v1:96dd130eae61`; five editorial rules are `WARN`.
- Provider: `custom / gemini-2.5-flash`.
- Scheduler/watchdog are offline locally and recorded as a local environment limitation.

## 3. Live Runtime

Public `https://dieuhoatudung.com` and `/admin/login` return HTTP 200 with `text/html; charset=utf-8`. The public footer reports `v1.32.1`. A protected Product URL returns 302 to the login page. SSH to the documented host on port 22 timed out, and the isolated local operator credential is not accepted by Live. No credential was changed or included in an artifact.

Consequently Live worker, queue, provider, DB, cache and policy values are `UNVERIFIED`, not inferred from the public version string.

## 4. Git/Version Parity

Local `main` and remote `origin/main` both point to `8afea4f78a8ecb7526f25180542f1fb53fcc0903`; tag `v1.32.1` exists remotely. Live shows version `1.32.1`, but its checked-out commit cannot be proven. Code parity is therefore not certified.

## 5. Migration Parity

Local has 95 migrations ran and 0 pending. Live migration status is unavailable. No new migration is part of the current local fix.

## 6. Config Parity

Local provider, rollout and guard settings were read from runtime. Live `.env`, database settings and cached config were not accessible. Public HTTP cannot prove these values.

## 7. Guard Policy Parity

Local editorial rules remain warnings. Optional data is now a distinct non-blocking notice. Technical contradictions, stale targets, authorization, parser/schema failures and concurrency remain locked/fail-closed. Live policy parity remains unverified.

## 8. Worker Parity

The local managed worker was restarted, not merely signalled with `queue:restart`; its parent/child heartbeat is fresh and its app version/code hash matches the web runtime. Live Supervisor state and loaded worker hash are unknown, which is a release blocker.

## 9. Queue Parity

Local queue drained after the controlled provider test. No orphan processing item remains. Live pending/processing/stuck counts cannot be read.

## 10. Scheduler/Watchdog

Local scheduler/watchdog are offline and not required by the completed foreground/browser generation proof. Production scheduler and watchdog are mandatory deployment gates and remain unverified.

## 11. Provider

One new controlled call was made after the fix through Product Edit → `ai_governed` → managed worker. Product #2877 produced Job #1118, Item #1289, Draft #1101 and request log #320 using `custom / gemini-2.5-flash`, 6,564 provider-reported total tokens. It reached `REVIEW_REQUIRED`; parser succeeded; critical validation errors were empty; Product hash/count and catalog tables were unchanged by generation.

The earlier failed Playwright attempt stopped at an unavailable local HTTP port and made zero provider calls.

Database delta from this certification is explicit: Products `362 → 364` (inactive evidence clones #2876 and #2877), AI jobs `53 → 54`, items `91 → 92`, drafts `69 → 70`, request logs `316 → 317`, catalog models `36,453 → 36,453`, and catalog fields `656,507 → 656,507`. Product #2876 has no AI job/request because its browser run never reached login. Product #2877 owns the single expected AI evidence chain above. No unexplained catalog mutation exists.

## 12. Technical Grounding

The root contract defect had two parts: provider input could bypass category `use_for_ai`, while the validator needed a broader source-authority registry; and a range whose two boundaries were stored separately was not recognized. Provider-visible facts now come only from canonical public allowed facts. Validator authority remains complete and server-side. Separate verified endpoints can prove a range.

All seven historical Draft #14 values are present in Product #1320 authority: `242 mm`, `244 mm`, `40 đến 46 dB`, `0.7 kW`, `19 dB`, `46 dB`, and `16mm`. They are technical evidence, not current hard blockers. An unsupported value such as fixture `54 dB` remains blocked if it survives, or is deterministically removed before persistence and revalidated when policy marks it rewritable.

## 13. Draft State

Draft #14 remains approved and unapplied. Readiness is now `can_apply=true`, hard blockers `0`, stale target `false`, with explicit confirmation still required. Four editorial warnings remain truthful; four missing optional data notices no longer require an override. Historical rows were not rewritten.

## 14. Bulk Workflow

Read-only preflight over Products #1319, #1320 and #2877 returned one blocked historical row, one ready-to-apply row and one ready-to-approve row. Optional-data counts are separate from editorial and hard-block counts. Existing browser bulk certification passed without bulk provider generation.

## 15. Encoding

Source audit scanned 877 files and passed. Corrupted Vietnamese literals in Product AI UI/test adapters were corrected. Local DB is `utf8mb4 / utf8mb4_unicode_ci`; however 26 legacy mojibake occurrences remain across brands, posts and tags. Dry-run produced 30 low-confidence/manual-review candidates, so no automatic DB mutation was performed. Live public HTML declares UTF-8; authenticated Live UI/DB encoding remains unverified.

## 16. Root Causes

1. Prompt fact exposure and validator authority were conflated.
2. Verified range endpoints were not composable.
3. Historical warning codes were treated as permanent truth instead of rechecked against current catalog authority.
4. Rewritable unsupported claims were repaired too late.
5. Successful encoding/language diagnostics polluted warning counts.
6. Optional catalog gaps were collapsed into editorial warnings.
7. Several source literals and legacy DB rows contained mojibake.
8. `normalizeConfig()` dereferenced a missing `apply_mode` key in its default branch; this was corrected and covered.

## 17. Fixes

- Canonical provider fact envelope respecting `use_for_ai`.
- Full server-side registry retained for validation.
- Range endpoint matching with normalized units.
- Product-aware historical warning reclassification.
- Deterministic repair-before-persist followed by sanitization and revalidation.
- Separate editorial, optional, processed-technical, informational and hard-block buckets.
- Positive diagnostics removed from generated warning lists.
- Vietnamese UI/test literals fixed; low-confidence DB data preserved for manual repair.

## 18. Browser Evidence

- Controlled real-provider browser: 1 passed, 0 failed.
- Ordinary AI policy/single/bulk browser suite: 19 total; 17 passed, 2 intentional skips, 0 failed.
- Covered preview, approve, warning approval, reject, discard, Apply, stale target, duplicate Generate, governed Generate, regenerate, hard block, rollout/RBAC, responsive actions and bulk operations.
- Authenticated Live parity smoke could not log in because local and Live operator credentials are intentionally not assumed identical. This is recorded as an access blocker, not a product failure.

## 19. Tests

- Focused AI/guard/catalog/RBAC: 222 tests, 221 passed, 1 skipped, 871 assertions, 0 failures/errors.
- Full PHPUnit: 528 tests, 527 passed, 1 skipped, 3,244 assertions, 0 failures/errors, exit code 0, 129.387 seconds.
- Composer validate/audit: PASS, no advisories.
- npm audit high: 0 vulnerabilities; Vite build PASS.
- Config/route/view cache PASS; PHP lint PASS; `git diff --check` PASS.
- Migrations: 95 ran, 0 pending.

## 20. Release Readiness

No SemVer change is made because the mandatory Live parity gate is open. No CHANGELOG/release note/tag/GitHub release is created for an uncertified release. `gh` is also unavailable locally, but that is secondary to the Live runtime blocker.

To close the gate, provide the reviewed SSH host/port/key or an authenticated Live operations session, then capture: exact HEAD, `migrate:status`, DB schema/counts/charset, provider and guard settings, cache state, `ai:queue-health --json`, Supervisor status/command, scheduler/watchdog, controlled Live Product generation, optional-data behavior, hard-conflict behavior, small bulk preflight, browser console/network, and before/after data invariants. Restore the original worker desired state afterward.

Final: `LOCAL AI PRODUCT = PASS`; `LOCAL/LIVE PARITY = PARTIAL`; `GITHUB RELEASE = BLOCKED`; `LIVE DEPLOYMENT READY = NO`.
