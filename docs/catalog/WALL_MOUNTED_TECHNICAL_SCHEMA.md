# Wall-Mounted Technical Schema

## Contract

- Category: `Điều hòa treo tường`
- Slug: `dieu-hoa-treo-tuong`
- Schema version: `wall-mounted-v1`
- Schema status: `active`
- Public category state on creation: inactive, non-indexable, `noindex,follow`
- Source evidence: Daikin wall-mounted catalog 01/2026, SHA-256 `58323a53015fb4d0eebecc6d4e8f2e0dc3a956a625a5fbf6ab018ece17eba66d`

The category is intentionally not published while empty. The importer resolves its exact name even when the public category is inactive. Publication is a separate operator decision after a controlled production import.

## Storage architecture

The project stores the active schema in `product_categories.technical_schema_json`; there is no separate technical-schema or technical-field table. Technical values are written to `products.specs_json` through `ProductTechnicalSpecWriter`, with field-level provenance and verification state. Dedicated Product columns are compatibility mirrors only where the existing domain already defines them.

Deployment uses the idempotent `WallMountedProductCategorySeeder`. It reuses an exact name/slug match, restores a soft-deleted exact match, and refuses to overwrite a different active schema version.

```bash
php artisan db:seed --class=Database\\Seeders\\WallMountedProductCategorySeeder --force
```

Do not run the import merely because the schema seeder succeeds.

## Field scope

The active schema has 75 fields:

- 12 core/compatibility fields: technical BTU, kW, HP, inverter, operating mode, supply, nominal input, airflow/noise summaries, dimensions, and indoor weight.
- 63 detailed source-native fields selected from the 70 QA_SOURCE keys.
- 7 QA-only keys: nominal capacity duplicates already represented by core columns, raw supply/voltage duplication, DB/WB basis markers represented in canonical field names, and `refrigerant_type` because it is not stated.

Field definitions, types, units, visibility, source coverage, and classification are recorded in [wall_mounted_technical_schema_matrix.csv](../reports/final/artifacts/wall_mounted_technical_schema_matrix.csv). The complete 3,570-row source inventory is summarized in [wall_mounted_schema_field_inventory.csv](../reports/final/artifacts/wall_mounted_schema_field_inventory.csv).

## Groups

Groups are documentation classifications; the current schema builder does not persist group metadata. No parallel grouping architecture was introduced.

1. Core Compatibility
2. Capacity / Performance
3. Electrical
4. Indoor Unit
5. Outdoor Unit
6. Compressor / Refrigerant
7. Piping
8. Operating Range

## Units and semantics

- Airflow remains source-native `m³/min`; no `× 60` conversion is performed.
- Sound pressure is labeled `dB(A)`.
- Dimensions are separate height, width, and depth fields in `mm`; source `C × R × D` means height × width × depth.
- Indoor and outdoor dimensions, noise, and weight are separate.
- Cooling operating range keys end in `_c_db`; heating keys end in `_c_wb`. DB and WB are never merged.
- Voltage-dependent rated currents remain text fields rather than lossy decimals.
- Heating values are blank for cooling-only models, never zero.

## Refrigerant and HP safety

`refrigerant_charge_kg` is importable. `refrigerant_type` is not in the schema and remains null because the source does not state R32, R410A, or another type.

Exact HP is imported only for the published 1, 1.5, 2, and 2.5 HP groups. Rows in the source group 3–3.5 HP keep `Product.hp` and the canonical HP fact null; capacity remains available in BTU/h and kW.

## Features

Feature availability is not stored as a boolean technical field. The source states `YES`, `NO`, `MODEL_SPECIFIC`, `OPTIONAL`, and `OPTIONAL_ACCESSORY`; flattening these would lose meaning. Model-specific conditions have been expanded to exact model rows in [wall_mounted_model_feature_matrix.csv](../reports/final/artifacts/wall_mounted_model_feature_matrix.csv), while optional/accessory semantics remain explicit. Runtime Product feature import remains outside this package until a multi-state feature storage contract exists.

## Provenance and import gate

Every technical write requires source PDF, SHA-256, page, row, column, section, and extraction method. Unknown fields remain fail-closed. Dynamic field acceptance is limited to the resolved category's active schema; an arbitrary workbook column cannot bypass the writer allowlist.

The release candidate workbook is [DAIKIN_WALL_MOUNTED_2026_IMPORT_READY.xlsx](../../DAIKIN_WALL_MOUNTED_2026_IMPORT_READY.xlsx). It contains 51 validated rows but must not be imported into production in this task.
