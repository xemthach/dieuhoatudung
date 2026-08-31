# Product Bulk Operations Full Audit Report

## Verdict

AI Product bulk review, approval, rejection, discard, regenerate and apply now use canonical Product AI state, server-side authorization, immutable selection snapshots and per-Product result ledgers. The legacy selected-failure retry action was removed because it could act on historical items rather than the canonical current state.

## Selection Contract

Filament resolves checked records from the current table query. When the user chooses all matching records, the framework reapplies active filters/search before executing the bulk action. The workflow receives only the resolved record collection and intersects it with the modal selection, preventing IDs submitted outside the original selection.

Each mutation creates `product_bulk_operations` and one `product_bulk_operation_items` row per Product. The operation stores actor, action, selected IDs, deterministic hash, filter/search context, preflight counts and final success/skipped/blocked/failed counts.

## Canonical Workflow

`ProductAiBulkWorkflowService` uses `AiProductContentStateResolver`, `AiProductWarningClassifier`, `ProductAiApplyReadiness`, `AIProductDraftApplyService`, `AIBulkApplyManifestService` and `AIBulkApplyExecutor`. It does not directly overwrite Product AI workflow states.

- Approve requires `bulk_ai_approve`; soft warnings require an explicit override and hard blockers cannot be overridden.
- Reject and discard require a reason. Discard is logical and preserves draft/job/provider evidence.
- Regenerate requires `product.ai_generate`, supersedes only a reviewable draft via the canonical service, freezes a new generation manifest and dispatches on `ai_governed`.
- Apply requires `bulk_ai_apply`, approved/fresh/safe drafts and exact `APPLY <N> PRODUCTS` confirmation. It uses the existing allowlist, stale-target and technical fact gates per Product.

## Single-Operator Rollout Gate

The local configuration currently enables `SINGLE_OPERATOR_CONTROLLED_ROLLOUT` for user ID 1. A different user with otherwise valid RBAC cannot mutate AI state. The Product bulk menu now disables mutation actions before dispatch and provides the operator-only explanation; read-only preflight remains available. Server-side enforcement remains authoritative.

## Browser Proof

Playwright against `http://dieuhoa-tudung.test` passed the isolated Product List flow: search/select one fixture, open AI preflight, verify only that Product is in scope, then verify non-operator mutation actions are disabled by rollout policy. No console, page, request or HTTP 5xx error was recorded.

## Automated Evidence

- Current focused retest: 26 tests passed, 206 assertions; the earlier broader focused run was 39 tests, 38 passed, 1 intentional skip, 214 assertions.
- Final Product List browser bulk suite: 2 passed, 0 failed, 0 skipped.
- Composer validation/audit, npm audit, Vite build, Laravel cache compilation and `git diff --check`: passed.

## Operator Browser Mutation Certification

The final Playwright run used the actual configured rollout operator, user ID 1. The rollout was left enabled throughout. The operator completed approve, approve-with-warning, reject, logical discard, partial batch apply and regenerate from the Product List. The batch apply (operation 36) selected all 16 filtered fixtures and produced 2 success, 13 skipped, 1 blocked and 0 failed; only the two fresh approved fixtures changed. Stale and hard-blocked fixtures did not mutate.

The regenerate fixture dispatched through `ai_governed`, reached a terminal truthful result, and retained the provider request log. The final policy-conformant run used `custom / gemini-2.5-flash` (request log 256; provider-reported total 8,454 tokens) and reached `needs_review`; earlier disposable fixture probes demonstrated hard technical blocking. Both results are valid governed outcomes and no Product/catalog record was duplicated.

The non-operator browser actor retained read-only preflight access but mutation controls were disabled. Focused feature certification covers the corresponding server-side denial; the configured operator was permitted by the same canonical policy.

## Full PHPUnit Final Certification

`php artisan test --no-ansi` was captured to `storage/logs/full-phpunit-release-20260830-policy-final.txt` and completed with exit code 0: 513 tests, 512 passed, 1 skipped, 3,182 assertions, 0 failures/errors, duration 125.964 seconds.

## Known Local Limitation

The governed worker is online and the `ai_governed` queue is drained. Local scheduler/watchdog heartbeats are offline; they are not required for the synchronous approve/reject/discard/apply paths or for a directly managed worker, but must be checked in the production deployment runbook.

The local scheduler/watchdog remains offline. It is not required for the completed managed-worker certification, but remains a mandatory production deployment check.

## No Release Operation

No commit, tag, push or release was created by this audit.
