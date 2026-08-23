# Post-Audit Repository Cleanup

## Release

- Previous tag: `v1.24.0`
- New version: `v1.25.0`
- Version source: Git annotated release tags; no composer/package version field exists.

## Retention policy

Production source, migrations, tests, reusable operational tools, final phase evidence, and verified backups are retained. One-off phase reports, forensic scripts, generated private reports, debug output, and temporary harnesses are removed only after reference checks.

The cleanup archived 142 intermediate root reports, 193 phase scripts, 22 named one-off source/debug scripts, and 1,609 generated private report files under the external archive `D:\\laragon\\www\\dieuhoa-tudung-audit-archive-20260823`. The archive is outside the Git working tree and is not a runtime dependency.

Retained script utilities include safe restore payload construction, read-only database checks, performance measurement/explain tooling, backup snapshot support, and worker/watchdog registration utilities.

## Safety

The verified local pre-release backup is retained and ignored by Git:
`storage/backups/phase9_pre_release_verified_20260823_082500.sql`

SHA-256: `A6EF06B7E3046A15852FABED08063411D2117676D67AD128AD4B6D4E4D26A70D`.

The database dump is not release content and must not be pushed.

## Validation target

320 tests / 1,008 assertions, 0 failures, 1 skipped; Composer and npm audit clean, frontend build passed, config/route/view cache passed, database counts and BTU hash unchanged, provider calls 0, worker disabled. Historical 1,011-assertion evidence is preserved in the prior audit record.

## Validation incident and final gate

The first unscoped test invocation was stopped after it was found to be using the local production database; it emptied that database through test lifecycle behavior. A forensic backup of the empty state was created, and the verified pre-release backup was restored twice through the guarded restore payload path. The final database check is exact again: 81 / 212 / 36,453 / 656,507 with the required BTU hash.

The controlled `.env.testing` run was not the release proof because its empty fixture profile returned 2 failures and 13 errors. The final proof used a guarded populated MySQL clone with `APP_ENV=testing` and passed 320 tests / 1,008 assertions / 0 failures / 1 existing skipped test. The configured production database was verified separately after the run. The initial unsafe invocation and recovery remain historical evidence; no backup was staged or pushed.

The release commit already exists as `23459f6af58b25902e20857326607ed5cd021261` (`chore(release): prepare v1.25.0`). The annotated `v1.25.0` tag exists locally and remotely; this cleanup follow-up does not recreate or overwrite it. Branch push and GitHub Release publication are handled only after the final staged review.

## Final publication result

- Validation follow-up commit: `595faf6` (`docs(release): finalize v1.25.0 validation`).
- `main` pushed to `origin` successfully.
- Remote `v1.25.0` tag verified and points to release commit `23459f6`.
- GitHub CLI is not installed, so GitHub Release creation is `MANUAL_FOLLOWUP`. Create it from the existing `v1.25.0` tag using `docs/release/RELEASE_1.25.0.md`.
- Working tree is expected to remain clean; local SQL backups remain ignored and outside Git.
