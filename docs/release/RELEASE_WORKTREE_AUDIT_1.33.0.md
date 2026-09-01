# v1.33.0 working-tree release audit

## SemVer decision

- `VERSION` before release: `1.32.2`.
- Latest repository tag before release: `v1.32.2`.
- Decision: `1.33.0` (minor), because the diff adds a backward-compatible canonical AI Product lifecycle architecture, an integrity command, and additive schema; `PRODUCT-DETAIL-001` is included as a patch within that minor release.

## INCLUDE

- Release metadata: `VERSION`, `README.md`, `CHANGELOG.md`, `docs/UPDATE_LIVE_SERVER.md`, and `docs/release/*1.33.0*`.
- AI Product runtime: modified/new files under `app/Console/Commands`, `app/Filament/Resources/AiProductJobs`, `app/Filament/Resources/Products`, `app/Jobs`, `app/Models`, `app/Services/AI`, and `app/Services/Product` shown by the release diff; deletion of the superseded `BulkRuntimeControlledResumeService.php` is intentional.
- Additive schema: `database/migrations/2026_08_31_000001_add_ai_product_lifecycle_integrity_columns.php`.
- Product rendering: the three Product Blade/component files in the diff.
- Tests: changed AI Product feature tests, new canonical lifecycle and Product numeric-format regression tests, `tests/browser/product-detail-numeric-formatting.spec.ts`, and the Product navigation assertion aligned with the active-category/dead-link contract.
- Architecture/audit evidence: `docs/ai/`, the two final reports, AI Product CSV artifacts, provider ledger, and `browser_certification_issue_ledger.csv` containing `PRODUCT-DETAIL-001`.

## EXCLUDE

- Eleven modified PNG files under `docs/reports/final/artifacts/browser/`: unrelated campaign, post, and promotion screenshots owned by existing local work; they are preserved unstaged.

## PRIVATE

- `DAIKIN_SKYAIR_2026_IMPORT.xlsx`.
- `DAIKIN_WALL_MOUNTED_2026_IMPORT.xlsx`.
- `DAIKIN_WALL_MOUNTED_2026_IMPORT_READY.xlsx`.
- `docs/reports/final/artifacts/skyair_production_import_manifest.csv`.

These import workbooks/manifest contain catalog source or operational import data and are not part of the AI Product lifecycle release.

## GENERATED

- Ignored `vendor/`, `node_modules/`, `public/build/`, framework caches, test logs, Playwright output, and temporary runtime artifacts are generated locally and must not be staged.

## Staging rule

Stage only explicit INCLUDE paths. Before commit, inspect `git diff --cached --name-status` and reject any PNG, XLSX, SkyAir manifest, cache, build, log, secret, or unrelated path.
