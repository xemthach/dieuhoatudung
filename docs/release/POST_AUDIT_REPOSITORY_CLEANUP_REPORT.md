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

Current validation passes with 326 tests / 1,053 assertions, 0 failures/errors, and 1 existing skipped test. Composer and npm audits are clean, the frontend build and config/route/view caches pass, database counts and BTU hash are unchanged, provider calls are 0, and the worker remains disabled. Historical validation totals remain preserved in the prior audit record.

## Validation incident and final gate

The first unscoped test invocation was stopped after it was found to be using the local production database; it emptied that database through test lifecycle behavior. A forensic backup of the empty state was created, and the verified pre-release backup was restored twice through the guarded restore payload path. The final database check is exact again: 81 / 212 / 36,453 / 656,507 with the required BTU hash.

The controlled `.env.testing` run was not the release proof because its empty fixture profile returned 2 failures and 13 errors. The final proof used a guarded populated MySQL clone with `APP_ENV=testing` and passed 320 tests / 1,008 assertions / 0 failures / 1 existing skipped test. The configured production database was verified separately after the run. The initial unsafe invocation and recovery remain historical evidence; no backup was staged or pushed.

During v1.26.0 verification, running PHPUnit after `config:cache` again selected cached local MySQL settings and emptied Product/catalog tables. Processing stopped before any Git publication. A forensic empty-state dump was created at `storage/backups/v126_current_empty_before_restore_20260823_161500.sql` (SHA-256 `91CFEB7D3DDDD268D79D7EEB6193DB1B0D5A86232BD9DA144A1E2575414851BE`). The Phase 9 source SHA was rechecked, a current-target payload was built and validated by `SafeRestorePayloadBuilder`, and restore completed successfully. Exact 81 / 212 / 36,453 / 656,507 counts, 90 migrations, BTU hash, queue state, and disabled worker state were restored. PHPUnit now bootstraps through `tests/bootstrap.php`; a deliberate cached-config test passed and left MySQL unchanged.

The release commit already exists as `23459f6af58b25902e20857326607ed5cd021261` (`chore(release): prepare v1.25.0`). The annotated `v1.25.0` tag exists locally and remotely; this cleanup follow-up does not recreate or overwrite it. Branch push and GitHub Release publication are handled only after the final staged review.

## Final publication result

- Validation follow-up commit: `595faf6` (`docs(release): finalize v1.25.0 validation`).
- `main` pushed to `origin` successfully.
- The existing `v1.25.0` tag points to release-preparation commit `23459f6`, but that tagged tree contains canonical `VERSION=1.24.0`. The tag was not recreated, moved, or overwritten.
- Operator authorization selected a new semantic minor release, `v1.26.0`, for the validated Admin UX consolidation. The historical tag conflict is preserved in documentation rather than rewritten.
- Commit, tag, push, and GitHub Release results for `v1.26.0` are recorded after final validation.
- Local SQL backups remain ignored and outside Git.
