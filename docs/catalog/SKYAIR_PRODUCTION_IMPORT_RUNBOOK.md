# Daikin SkyAir 2026 Production Import Runbook

Status: PRE-PRODUCTION ONLY — import is not authorized by this package.

## Gate before import

1. Obtain an explicit row-level operator decision for every row in `DAIKIN_SKYAIR_2026_OPERATOR_REVIEW.xlsx`.
2. Only rows marked `APPROVE` may be copied into a newly generated `DAIKIN_SKYAIR_2026_PRODUCTION_IMPORT.xlsx`.
3. Any `REVIEW` or `REJECT` row blocks generation of the production workbook.
4. Recompute and record the production workbook SHA-256. The importer must use that exact file.
5. Re-run the real Product import preview and confirm recognized = valid = approved rows, invalid = duplicate = 0.

## Pre-import snapshot

Record database name, application version/commit, migration status, Product count, category count, and the manifest hash. Create a verified database backup and retain it outside the web root. Do not stage or upload the backup.

The current audit snapshot is Product 132, catalog sources 212, catalog models 36,453, catalog fields 656,507, migrations 93. These are evidence only; re-read them immediately before an authorized import.

## Import procedure

Use the existing admin Data Transfer Product import flow and its preview/confirm contract. The current handler is `App\Services\DataTransfer\Modules\ProductImportHandler`; validation requires catalog provenance and the category technical schema. Use `create` with matching key `sku` for genuinely new rows, unless a separately approved collision decision authorizes another mode.

The service applies one database transaction per row, not one all-or-nothing transaction for the entire workbook. A failed row is rolled back and recorded while other rows may commit. Therefore stop after preview if any row is invalid, and preserve the import job/error report and manifest.

New products must remain inactive/noindex according to the workbook policy. Do not invent price, stock, AI copy, or public publication state.

## Post-import verification

Compare created/updated IDs against the manifest. Verify no duplicate SKU, expected Product delta, category distribution, technical field count, inactive/noindex policy, RAC matcher safety, and unchanged catalog baseline. Export representative imported rows and compare SKU, model pair, category, capacity, phase, refrigerant, and provenance.

## Rollback

Do not delete “the last N products”. Use the verified backup as the primary rollback mechanism. The manifest is the audit identity list, but any row-level rollback must be reviewed against the actual importer result and foreign-key relationships. If a partial import occurred, stop further imports, preserve the import job and database logs, and restore through the approved database recovery procedure or an explicitly reviewed SKU/id rollback script.

## Abort conditions

Abort on any unresolved review row, workbook hash mismatch, collision, invalid row, unexpected active/public record, changed catalog baseline, missing provenance, schema mismatch, or importer error. Production import remains prohibited until all gates are signed off.
