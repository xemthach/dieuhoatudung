# Post-release GitHub update report

## Release

- Old version: `1.30.0`
- New version: `1.31.0`
- Commit: `99451a1cffa4e010de6940cdf85032bc64135ace`
- Tag: `v1.31.0`
- Branch: `main`
- Remote branch verification: PASS
- Remote tag verification: PASS; annotated tag dereferences to the release commit.

## Gates

- Browser combined suite: 9 passed, 0 failed, 0 skipped.
- Admin navigation round-trip: PASS.
- PHPUnit: 475 tests, 474 passed, 1 skipped, 2,948 assertions, 0 failures/errors.
- Composer/npm/build/cache/lint/diff/migration gates: PASS.
- Staged secret/private/production-workbook checks: PASS.

## Data and runtime safety

- Products: 357 (182 active, 175 inactive).
- Categories: 7; Brands: 14.
- Catalog sources: 212; models: 36,453; model fields: 656,507.
- Migrations: 93, all ran; no pending migration.
- AI provider calls: 0.
- SkyAir production import: not executed.
- Worker desired state: unchanged; no worker was enabled by release certification.

## GitHub Release

Branch and tag were pushed normally without force. The `gh` CLI is not installed on the release workstation, so GitHub Release creation remains `MANUAL_FOLLOWUP`; use `docs/release/RELEASE_1.31.0.md` as the release body.

## Known limitations

Existing active Products assigned to inactive categories and active uncategorized Products were not auto-reclassified. Local browser screenshots and operator/production workbooks remain unstaged local artifacts.
