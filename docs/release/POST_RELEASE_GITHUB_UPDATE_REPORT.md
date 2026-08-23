# Post-Release GitHub Update Report

Date: 2026-08-23

## Release identity

- Previous version/tag: `v1.28.0`
- Released version/tag: `v1.28.1`
- Canonical version source: `VERSION`
- Release commit: `84536e08ea240cd6463a9dc02ca12596aa2f3c54`
- Branch/remote: `main` / `origin`
- Release purpose: Product media/CDN rendering consistency and actionable Product AI state reconciliation.

## Validation

- Laravel: 363 tests, 362 passed, 1 existing skip, 1,260 assertions, 0 failures/errors.
- Focused changed-area suite: 63 tests, 376 assertions, PASS.
- Composer validation/audit: PASS / no advisories.
- npm high-severity audit: PASS / 0 vulnerabilities.
- Vite production build: PASS.
- Config, route and Blade view caches: PASS.
- Changed PHP lint and `git diff --check`: PASS.
- Secret/private-artifact scan: PASS; no SQL dump, backup, `.env` or private runtime artifact was staged.

## Data and backup safety

- Data: 81 Products / 212 catalog sources / 36,453 catalog models / 656,507 catalog fields.
- Migrations: 90.
- Canonical BTU hash: `3e981c60fcadd3461746fd8f3b94855dc5205bad6c446c55c17066d40c47e3ba`.
- Verified local backup remains ignored at `storage/backups/phase9_pre_release_verified_20260823_082500.sql`.
- Backup SHA-256: `A6EF06B7E3046A15852FABED08063411D2117676D67AD128AD4B6D4E4D26A70D`.
- Product/catalog technical writes by release validation: 0.

## AI runtime deployment gate

The Windows Task Scheduler definition `DieuHoaTuDung-AIGovernedWorker` was validated against the exact managed command and restarted after the release commit:

```text
php artisan ai:managed-worker --queue=ai_governed --sleep=3 --tries=3 --timeout=900
```

- Desired state before/after restart: `DISABLED` / `DISABLED`.
- Actual state after restart: `ONLINE / PAUSED`; accepting new jobs: false.
- Old supervisor/child PIDs: `2416 / 19120`.
- New supervisor/child PIDs: `7964 / 15216`.
- Web/worker version: `1.28.1 / 1.28.1`.
- Web/worker build: release commit `84536e0...` for both.
- DB: MySQL `dieuhoa-tudung` for web and worker.
- Queue: database connection, `ai_governed` for web and worker.
- Deployment status: `UP_TO_DATE`.
- Processing/stuck: `0 / 0`.

`ai:managed-health-check` created one non-provider diagnostic job while desired state was disabled. It remains safely queued and unclaimed; its recorded contract is `provider_call=false` and `product_mutation=false`. It was not purged or processed by bypassing operator intent. Scheduler heartbeat is stale in the local Laragon environment and remains a mandatory live-deployment gate.

Release-validation commands initiated zero provider calls. A separate authenticated Super Admin action occurred concurrently during validation and created provider request-log row `236`; this external runtime action was completed, distinguished from release proof, and the desired state was restored to `DISABLED`.

## GitHub publication

- `main` push: PASS (`fe4eea2..84536e0`).
- Annotated tag `v1.28.1`: created and pushed without force.
- Remote branch contains release commit: PASS.
- Remote tag exists: PASS.
- GitHub CLI: unavailable on this workstation.
- GitHub Release page: `MANUAL_FOLLOWUP` using `docs/release/RELEASE_1.28.1.md`.

## Live deployment requirement

The release is published, but each live deployment must follow `docs/UPDATE_LIVE_SERVER.md`: capture worker state, drain active work safely, deploy code/dependencies/caches, restart the OS-managed worker, prove DB/queue/version alignment, verify scheduler health, and restore the original desired state intentionally. Updating only web code is not a passing deployment.

## Verdict

- Release validation: PASS.
- Repository cleanup: PASS.
- Commit/tag/branch publication: PASS.
- GitHub Release page: MANUAL_FOLLOWUP because `gh` is not installed.
