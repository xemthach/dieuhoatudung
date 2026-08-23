# Post-Release GitHub Update Report

Date: 2026-08-24

## 1. Release identity

- Previous version/tag: `v1.28.2`.
- Released version/tag: `v1.29.0`.
- Semantic version decision: MINOR; this is a backward-compatible feature release with Calculator V2, volume sizing, equipment-type guidance, and a revised Quote workflow.
- Canonical version source: `VERSION`.
- Release commit: `252366d11460a3ff8dbadad587a8213212503b1a`.
- Branch/remote: `main` / `origin`.
- Annotated tag: `v1.29.0`; the tag remains attached to the release commit.

## 2. Included release scope

- Calculator V2 with explicit active Area and Volume rule sets while preserving V1 replay contracts.
- Category-specific hybrid calibration: supported HIGH/MEDIUM-confidence factors are activated and LOW-confidence factors retain V1 values.
- True volume calculation without applying the Area height adjustment twice.
- Deterministic, brand-neutral equipment-type guidance with fail-closed Product matching.
- Three-step Quote workflow, server-side Calculator/Product context handoff, idempotent submission, and transactional Quote/Lead creation.
- Read-only Calculator governance, calibration evidence, release documentation, focused regression coverage, and committed Vite production assets.
- Three additive migrations for calculation lineage and Quote workflow context.

No Product/catalog technical mutation, AI provider integration change, queue purge, worker enable, dependency-version change, or unrelated architecture program is part of this release.

## 3. Validation evidence

- Focused changed/risk-area suite: 137 tests, 707 assertions, PASS.
- Full Laravel suite: 449 tests, 448 passed, one existing skip, 1,686 assertions, zero failures/errors.
- Post-version focused smoke: 32 tests, 166 assertions, PASS.
- Composer `validate --strict`: PASS.
- Composer audit: PASS; no known advisories.
- npm audit at high threshold: PASS; zero vulnerabilities.
- Vite production build: PASS.
- Laravel config, route and Blade view caches: PASS.
- PHP lint: 46 changed/new PHP files, PASS.
- `git diff --check`: PASS.
- Staged secret/private-artifact scan: PASS; no SQL dump, backup, `.env`, credential, session, cookie, provider payload, or private runtime artifact was included.

## 4. Data and backup safety

- Products: 81.
- Catalog sources: 212.
- Catalog models: 36,453.
- Catalog fields: 656,507.
- Applied/repository migrations: 93 / 93.
- Canonical BTU hash: `3e981c60fcadd3461746fd8f3b94855dc5205bad6c446c55c17066d40c47e3ba`.
- Product/catalog technical writes caused by release validation: 0.
- Verified local backup remains ignored at `storage/backups/phase9_pre_release_verified_20260823_082500.sql`.
- Expected and rechecked backup SHA-256: `A6EF06B7E3046A15852FABED08063411D2117676D67AD128AD4B6D4E4D26A70D`.
- The SQL backup is not tracked or uploaded to GitHub.

## 5. AI worker deployment gate

Canonical managed command:

```text
php artisan ai:managed-worker --queue=ai_governed --sleep=3 --tries=3 --timeout=900
```

The local Windows Task Scheduler definition `DieuHoaTuDung-AIGovernedWorker` was inspected and restarted after the release commit so the long-running worker loaded the new code. Admin controls desired state only; the OS process manager controls process lifecycle.

- Desired state before/after restart: `DISABLED` / `DISABLED`.
- Actual state after restart: `ONLINE / PAUSED`.
- Accepting new jobs: false.
- Old supervisor/child PIDs: `12096 / 21892`.
- New supervisor/child PIDs: `16000 / 18204`.
- Web/worker version: `1.29.0 / 1.29.0`.
- Web/worker build: `252366d11460a3ff8dbadad587a8213212503b1a` for both.
- Environment/PHP: `local` / `8.3.16` for both.
- Database: MySQL `dieuhoa-tudung` for both.
- Queue: database connection, `ai_governed` for both.
- Worker deployment status: `UP_TO_DATE`.
- Queue: 1 pending safe self-test, 0 processing, 13 historical failed, 0 stuck.
- Active runtime leases/slots: 0 / 0.
- The pending self-test remains unclaimed because the operator's `DISABLED` state was preserved; it has `provider_call=false` and `product_mutation=false` and was not purged.
- Provider request-log count/max ID remained `236 / 236`; release validation caused zero provider calls.

The local scheduler command compiles and lists six tasks, but its persisted heartbeat is stale (`2026-05-16 21:50:02`). This is recorded as a local environment limitation, not production scheduler evidence. A live deployment cannot PASS until the live scheduler and heartbeat are verified.

## 6. Git and GitHub publication

- Release commit: PASS.
- Annotated tag `v1.29.0`: created without replacing an existing tag.
- `main` push: PASS (`b9a470c..252366d`).
- Tag push: PASS.
- Force push: not used.
- GitHub CLI: unavailable on this workstation.
- GitHub Release page: `MANUAL_FOLLOWUP` using `docs/release/RELEASE_1.29.0.md`.

This report is intentionally published in a follow-up documentation commit. The immutable `v1.29.0` tag continues to identify the validated release commit rather than being moved.

## 7. Live update and rollback

The exact deployment checklist is maintained in `docs/UPDATE_LIVE_SERVER.md`. A live update must capture desired/actual worker state and active operations, take a verified database backup, deploy `v1.29.0`, install production dependencies, apply the three migrations, rebuild caches, restart the reviewed OS-managed worker, verify web/worker version, DB and queue alignment, verify scheduler health, and intentionally restore the original desired state.

Updating web files alone is not a passing deployment. If worker restart, version alignment, database/queue binding, scheduler health, or post-deploy queue checks fail, keep AI desired state `DISABLED` and mark the live deployment BLOCKED.

Rollback target is `v1.28.2`. The three migrations are additive and normally remain in place during code rollback; database rollback/restore requires a separate reviewed decision. Rebuild caches and restart the worker after rollback so it does not retain v1.29.0 code.

## 8. Final repository verdict

- Release validation: PASS.
- Version/tag: `v1.29.0`.
- Repository cleanup and release diff: PASS.
- Commit/tag/branch publication: PASS.
- GitHub Release page: MANUAL_FOLLOWUP because `gh` is unavailable.
- Local worker reload proof: PASS with processing intentionally disabled.
- Live deployment: READY FOR OPERATOR EXECUTION; not claimed as executed or certified by local evidence.
