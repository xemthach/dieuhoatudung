# Post-Release GitHub Update Report

Date: 2026-08-29

## 1. Release identity

- Previous version/tag: `v1.29.0`.
- Released version/tag: `v1.30.0`.
- Semantic version decision: MINOR; this backward-compatible release adds complete Campaign preview/runtime behavior, frontend Promotion placements, hardened Post AI lineage, and repeatable browser certification tooling.
- Canonical version source: `VERSION`.
- Previous branch HEAD: `083ad12380fc138ac05ab4c86486d75191c30554`.
- Release commit: `fb9cd890709ece59232a6e6267a71115ee78d8bc`.
- Branch/remote: `main` / `origin` (`https://github.com/xemthach/dieuhoatudung.git`).
- Annotated tag: `v1.30.0`; the tag remains attached to the validated release commit.

## 2. Included release scope

- Website Campaign production rendering, targeting, readiness, event aggregates, and authorized no-side-effect preview.
- Promotion banner, landing, popup, and announcement surfaces with request-scoped resolution.
- Rich HTML sanitization and Chrome-certified Post editor interaction.
- Exact-target, stale-safe, idempotent Post AI review/apply lineage.
- Promotion AI support for both description and detailed content while preserving structured facts.
- Filament 5 Lead form action compatibility fix.
- Playwright release harness, synthetic SQLite fixtures, screenshots, issue ledger, and final audit reports.

No migration, Product/catalog technical mutation, queue purge, AI worker enable, or live provider call is part of this release.

## 3. Validation evidence

- Playwright browser suite: 6/6 scenarios passed in Google Chrome `152.0.7977.64`; 11 screenshots; zero relevant console, page, Livewire, or same-origin request errors.
- Full Laravel suite: 463 tests, 462 passed, one existing skip, 1,737 assertions, zero failures/errors.
- Composer `validate --strict`: PASS.
- Composer audit: PASS; no known advisories.
- npm audit at high threshold: PASS; zero vulnerabilities.
- Vite production build: PASS.
- Laravel config, route, and Blade view caches: PASS.
- PHP lint: 24 changed/new PHP files, PASS.
- `git diff --check`: PASS.
- Staged release set: 63 files; secret/private-artifact scan found zero credential patterns and zero SQL, SQLite, backup, `.env`, or private runtime files.

## 4. Data and runtime safety

- Products: 81.
- Catalog sources: 212.
- Catalog models: 36,453.
- Catalog fields: 656,507.
- Recorded migrations: 93; this release adds none.
- Canonical BTU hash: `3e981c60fcadd3461746fd8f3b94855dc5205bad6c446c55c17066d40c47e3ba`.
- Product/catalog technical writes caused by release work: 0.
- Browser fixtures used an ignored isolated SQLite database and were removed by exact fixture IDs; the MySQL application dataset was not used for browser mutation.
- Provider calls caused by validation: 0.

## 5. AI worker deployment gate

Canonical command reported by current code:

```text
php artisan ai:managed-worker --queue=ai_governed --sleep=3 --tries=3 --timeout=900
```

Local evidence at release time was intentionally non-processing: desired state `DISABLED`, actual state `OFFLINE`, database queue `ai_governed`, zero processing/stuck jobs, one pending job, and 13 historical failed jobs. The local scheduler heartbeat was stale. These are truthful local observations, not live-server certification.

A live deployment must capture desired/actual state and active runtime records, safely drain active work, update code and caches, restart the long-running managed worker through the actual OS process manager, verify web/worker version, project path, PHP, environment, database, queue, heartbeat, scheduler, leases/slots/reservations, and restore the original desired state intentionally. Updating web files alone is not a passing deployment.

## 6. Git and GitHub publication

- Release commit: PASS.
- Annotated tag `v1.30.0`: created without replacing an existing local or remote tag.
- `main` push: PASS (`083ad12..fb9cd89`).
- Tag push: PASS.
- Remote `main` resolves to the release commit.
- Force push: not used.
- GitHub CLI: not installed on this workstation.
- GitHub Release page: `MANUAL_FOLLOWUP` using `docs/release/RELEASE_1.30.0.md`.

This publication report is intentionally committed after the release tag. The immutable `v1.30.0` tag continues to identify the tested release commit and is not moved to the documentation-only commit.

## 7. Live update and rollback

The detailed deployment and rollback procedure is in `docs/release/RELEASE_1.30.0.md`. Production deployment remains an operator action and must not be reported PASS until the live worker, scheduler, queue, database binding, release identity, smoke tests, and original desired state have been verified.

Rollback target is `v1.29.0`. Because v1.30.0 has no migration, schema rollback is not expected. Reinstall matching dependencies/assets, rebuild caches, and restart affected long-running processes through their real process managers so no worker retains v1.30.0 code after rollback.

## 8. Files included and excluded

Included:

- Campaign, Promotion, Post AI, sanitizer, and Filament compatibility source changes proven by the release diff.
- Focused Laravel regressions and the Playwright harness/fixtures.
- Version, changelog, release notes, final reports, issue ledgers, non-PII screenshots, and rebuilt Vite assets.

Excluded:

- `.env` files, credentials, cookies/sessions, private browser profiles, provider payloads, SQL/SQLite databases, backups, logs, and ignored runtime caches.
- The isolated browser SQLite database and fixture state, which were removed after certification.
- Unrelated source or user changes; none remained unstaged at release commit time.

## 9. Final repository verdict

- Release validation: PASS.
- Browser certification: PASS.
- Version/tag: `v1.30.0`.
- Commit/tag/branch publication: PASS.
- GitHub Release page: MANUAL_FOLLOWUP because `gh` is unavailable.
- Live deployment: READY FOR OPERATOR EXECUTION; not claimed as executed or certified by local evidence.
