# Daikin Wall-Mounted Schema and Import Readiness Report

## 1. Scope and verdict

The reported blocker was independently reproduced against code, the current database, and the original workbook. The current database has no truthful wall-mounted category, including no active or soft-deleted exact name/slug match. Existing cassette, duct, floor-standing, floor/ceiling, and VRF/GMV schemas are not substitutes.

**DAIKIN WALL-MOUNTED 2026 PRODUCTION IMPORT PACKAGE: READY**

This verdict certifies the package and isolated import proof only. Production import was not performed.

## 2. Import contract evidence

The actual path is `DataImportService` → `ProductImportHandler` → `ProductTechnicalSpecWriter`.

- XLSX imports use the active sheet and exact first-row headers.
- Category is mandatory and resolves by numeric ID or exact name.
- Category schema must be active and non-empty.
- Create/update/upsert are transactional per row.
- SKU/slug checks include soft-deleted Products.
- Technical writes require complete appendix provenance.
- Arbitrary technical fields remain rejected; only legacy canonical fields or fields in the resolved active category schema may be written.

The audit found and corrected two importer gaps before release: a full import row was incorrectly flattened as nested specs during category validation, and the canonical writer could not accept category-defined fields beyond its historical fixed allowlist. The fix retains schema and provenance gates.

## 3. Category and schema

The reproducible configuration is provided by `WallMountedProductCategorySeeder` and `WallMountedTechnicalSchema`.

| Property | Value |
|---|---|
| Name | Điều hòa treo tường |
| Slug | `dieu-hoa-treo-tuong` |
| Schema | `wall-mounted-v1` |
| Schema status | active |
| Fields | 75 |
| Runtime ID | 29 |
| Public state on creation | inactive / noindex |
| Seeder | idempotent; conflicting active version fails closed |

The idempotent seeder created runtime category ID 29 in the local configured database, then a second run proved it did not duplicate the row. The workbook still uses the portable exact category name rather than an environment-specific ID.

## 4. QA_SOURCE and field design

- QA_SOURCE rows read: 3,570
- Unique normalized source fields: 70
- Detailed source fields imported: 63
- Core/compatibility schema fields: 12
- QA-only source fields: 7
- Unresolved import fields: 0

The schema separates cooling/heating, indoor/outdoor, piping, compressor/refrigerant charge, and DB/WB operating ranges. It preserves multi-value current strings and source precision.

Airflow stays in `m³/min`; no conversion was needed or performed. Sound uses `dB(A)`.

## 5. Refrigerant and HP

All 51 rows contain published refrigerant charge. The source does not state refrigerant type, so no gas type is imported and isolated proof found zero non-null `refrigerant_gas` values.

HP mappings 1, 1.5, 2, and 2.5 are exact source-group mappings. The 3–3.5 HP group remains null; no BTU-derived HP was invented.

## 6. Feature handling

The 324 series-level evidence rows were expanded to 1,377 model-level rows:

- `YES`: 783
- `NO`: 561
- `OPTIONAL_ACCESSORY`: 25
- `OPTIONAL`: 8

The 21 source `MODEL_SPECIFIC` cases were resolved against exact series/capacity conditions, so the final model matrix has no unresolved model-specific state. No feature was imported into Product technical fields because current runtime storage cannot truthfully represent all five source states.

## 7. Workbook validation

`DAIKIN_WALL_MOUNTED_2026_IMPORT_READY.xlsx` contains:

| Sheet | Rows |
|---|---:|
| IMPORT_READY | 51 |
| FEATURE_MAPPING | 1,377 |
| QA_SOURCE | 3,570 |
| README | 9 notes |
| REVIEW_REQUIRED | 0 |

Actual `ProductImportHandler::validateRow()` result:

- Recognized: 51
- Valid: 51
- Review required: 0
- Invalid: 0
- Duplicate SKU: 0

## 8. Isolated database import

The proof process had a hard guard for an isolated SQLite path under `storage/framework/testing`, migrated it from zero, seeded only Daikin and the wall-mounted category, imported all rows, exported through the application exporter, and deleted the temporary database.

- Products before: 0
- Created: 51
- Updated: 0
- Skipped: 0
- Failed: 0
- Products after: 51
- Duplicate SKU: 0
- Category mismatches: 0
- Brand mismatches: 0

## 9. Round trip

The application JSON exporter was used because it preserves the complete `specs_json` payload without XLSX single-cell length constraints. Twelve representative series were checked across six fields each: 72 value/unit comparisons, 72 PASS, 0 FAIL.

Covered series: FTKZ, FTKM, FTKY, FTKF, ATKF, FTKB, ATKB, FTHB, FTF, FTXM, FTXV, FTHF.

## 10. Cross-category and UI behavior

Existing category schemas are not modified. Category-defined dynamic fields are accepted only after category resolution, so other category allowlists remain fail-closed. Frontend spec rendering already skips null/blank values and formats values from the active category schema; unknown refrigerant and cooling-only heating rows therefore do not render fabricated zero/default values.

The new category is inactive until an operator intentionally publishes it after import, preventing an empty public listing.

## 11. Data safety

Normal DB before and after isolated proof:

| Table | Count |
|---|---:|
| products | 81 |
| product_categories before | 6 |
| product_categories after approved schema configuration | 7 |
| catalog_sources | 212 |
| catalog_models | 36,453 |
| catalog_model_fields | 656,507 |

- Normal Product writes: 0
- Normal category/schema writes: 1 expected idempotent configuration row; second seeder run created 0 duplicates.
- Catalog writes: 0
- AI provider calls: 0
- Worker state changes: 0

## 12. Regression and release validation

- Focused category/import/export regression: 17 tests, 689 assertions, PASS.
- Full suite: 466 tests; 465 passed, 1 existing skip; 2,386 assertions; 0 failures; 0 errors.
- `composer validate --strict`: PASS.
- `composer audit`: no vulnerability advisories.
- `npm audit --audit-level=high`: 0 vulnerabilities.
- Vite production build: PASS.
- Laravel config, route, and view cache: PASS.
- PHP lint for all changed PHP files: PASS.
- `git diff --check`: PASS.
- Migrations: unchanged at 93; no schema migration was required because category technical schemas are DB-owned configuration deployed by idempotent seeder.

## 13. Artifacts

- `wall_mounted_schema_field_inventory.csv`
- `wall_mounted_technical_schema_matrix.csv`
- `wall_mounted_model_feature_matrix.csv`
- `wall_mounted_import_validation.csv`
- `wall_mounted_round_trip_verification.csv`

## 14. Deployment boundary

The package is ready, but production import remains a separate controlled operation:

1. Deploy code and run migrations as normal.
2. Run the dedicated wall-mounted category seeder with `--force`.
3. Verify the created/reused category and schema in admin.
4. Import the final workbook in preview mode first.
5. Reconfirm 51 valid/new rows and zero SKU conflicts against the then-current production database.
6. Import only after explicit operator authorization.
7. Publish/index the category only after Product QA.

No commit, tag, push, or production import was performed.
