# Product Import/Export Governance and BTU audit

## Executive verdict

Local code and data prove two defects: an all-failed execution could be recorded
as `completed`, and the public `9000-12000` range used inclusive SQL while its
label implied an exclusive upper bound. Both have been corrected locally. The
actual format/routing of Production Job #40 cannot be concluded without its
read-only audit snapshot; it does not exist in the Local database.

## Export and import contracts

| Use case | Current export | Import routing | Technical provenance |
|---|---|---|---|
| Full Product backup | XLSX, `scope=all`, no effective filters, no IDs, all groups or no groups | `SYSTEM_PRODUCT_RESTORE` | Manifest/checksum required; restore contract governs writes |
| Filtered/selected/current-page Product export | Normal CSV/XLSX/XML/JSON presentation export | `create`/`update`/`upsert` Product import | Catalog technical fields require appendix provenance |
| Manufacturer catalog ingestion | External import | Product catalog handler | Strict provenance and category schema |
| Basic/manual Product import | External import | Product catalog handler | Strict for technical input today |

`DataExportService::isProductSystemRestoreExport()` deliberately excludes
selected, filtered and current-page scopes. Therefore a Product export of 81
rows is not automatically a round-trip package unless it actually meets the
full-population contract and includes `_SYSTEM_EXPORT`. This is a documented UX
contract gap, not evidence that the manifest or provenance guard is wrong.

## Production evidence received

The read-only Production report `product-import-btu-audit-production-20260904_160943.json` establishes the previously pending facts without changing Live:

- Live is `v1.33.7`, tag `v1.33.7`, SHA `43c620e878542ee63f34d2075b6e350f2b251c67`.
- Population is 276 active Products, all Daikin; Local is a different population and code head.
- `marketing_capacity_btu` is null for all 276 Live Products, while technical BTU and kW are populated for all 276. The canonical SQL is present and correct, so the zero BTU-filter result is a **marketing-capacity data gap**, not a frontend/request-parser defect.
- Job #40 has `total_rows=81`, `success_rows=0`, `failed_rows=81`, `created_rows=0`, `updated_rows=0`, but old deployed code persisted `status=completed`.
- Its workbook contains only visible `Data`; no `_SYSTEM_EXPORT` or `_SYSTEM_PAYLOAD` sheet and no `format_context_json`. It was therefore routed as normal Product/catalog import, where technical input correctly triggered `Catalog technical import requires TECHNICAL_APPENDIX provenance` for all 81 rows.

This proves `EXPORT_FORMAT_BUG`/workflow mismatch for that historical workbook and a result-state presentation defect in the old deployed code. It does not justify weakening manifest or catalog provenance guards.

## Job #40 evidence boundary

Production Job #40 is not present locally. Its historical workbook and handler
branch are now proven by the received read-only Production report; its file hash
was `161b7b18e5b86c56110fcf05b158d613ab9d5ac0cec6932efdbaac406f6d287a`.

## Result-state defect

Before the local change `DataImportService::confirmImport()` always persisted
`completed` after iterating rows. `ImportPreviewPage`, the result page and recent
jobs treated that value as green even when `success_rows=0` and
`failed_rows=81`.

`DataImportJob::terminalStatusFor()` now produces:

- `completed`: all executed rows succeeded;
- `completed_with_errors`: at least one succeeded and at least one failed;
- `failed`: no row succeeded and at least one failed;
- `empty`: no rows.

The preview toast, result banner and recent-job badge distinguish these states.

## BTU audit

The public source is still `products.marketing_capacity_btu` through
`ProductMarketingCapacityQueryAdapter`. The Local read-only query proof for
`btu[]=9000-12000` uses inclusive `BETWEEN 9000 AND 12000`, returning Product
`#1257` (`GUD35PS1-A-S-GUD35W1-NhA-S`, marketing capacity 12000). The user-facing
label is now `9.000 - 12.000 BTU`, matching the contract.

Local snapshot generated on 2026-09-04: 372 Products, 183 active, 6 soft-deleted,
16 marketing capacities present. Local filter checks returned 1 row for the
range, 2 for 18k, 4 for 24k, 2 for 48k and 4 for 18k+48k.

## Governance and configuration audit

The canonical DB-backed setting infrastructure is `SiteSetting` / `SettingService`.
It already owns import/export operational values (file-size, allowed formats,
chunk sizes, retention and CSV BOM). Product catalog provenance and schema guards
remain hard-coded domain/integrity safeguards in `ProductImportHandler`; they are
not currently a single Admin-managed policy matrix. No unsafe toggle has been
introduced before the full guard inventory and Live Job #40 evidence are known.

Manifest/checksum validation, malformed workbook handling, FK integrity and
authorisation remain system-required invariants and must never become Admin OFF
switches.

## Live audit package

The following files are self-contained and read-only:

- `tools/live-product-import-btu-audit/LIVE_AUDIT.php`
- `tools/live-product-import-btu-audit/RUN_LIVE_AUDIT.sh`
- `tools/live-product-import-btu-audit/RUN_LOCAL_AUDIT.cmd`
- `tools/live-product-import-btu-audit/COMPARE_AUDITS.php`

Run the audit file once and return the generated `.json`/`.md` report. It writes
only `storage/logs/audits`, captures Job #40/workbook metadata when present, and
does not update data, settings, caches, queues or workers.

### Mandatory uploadable package

The final package is physically generated at
`artifacts/LIVE_PRODUCT_IMPORT_BTU_AUDIT_PACKAGE.zip` with SHA-256
`e545e67152a138f735dd8dd100af57bd7a863eb45e005fad5c9d637aa71d79dd`.
It contains the one-command CLI runner, Windows runner, comparison tool, and a
randomized one-file browser fallback. The final Local run generated
`LOCAL_PRODUCT_IMPORT_BTU_AUDIT_20260905_080452.json`, Markdown
and HTML, observed 77 SELECT queries and zero write queries, and preserved
Product count including trashed 378, ImportJob count 14, queued rows 5 and the
settings hash.

The browser token is held only in untracked `AUDIT_RUN_INFO.txt` and the
generated artifact; it must not be committed. The static runner uses
`hash_equals`, rejects invalid tokens with HTTP 403 without disclosing the token,
allows download only for signed audit report basenames, and tells the operator to
delete the temporary PHP file after download.

## Current certification

- Composer validation/audit, npm high audit, Vite build, PHP lint and `git diff --check`: PASS.
- Received old-runner Live evidence: confirms data/code parity defects above.
- Product Transfer/governance focused suite: PASS (47 tests, 1,411 assertions).
- Browser governance/transfer/BTU/technical/System Restore matrix: PASS (5 tests).
- Full PHPUnit: PASS (587 total; 586 passed; 1 skipped; 0 failed; 3,667 assertions).
- New uploadable package Local dry run: PASS (`LOCAL_PRODUCT_IMPORT_BTU_AUDIT_20260905_080452.*`).
- Production mutation: NONE.
