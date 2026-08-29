# Daikin Wall-Mounted Import Contract

## Scope and source

- Source: `DAIKIN TREO TUONG 2026.pdf`
- Authority: Daikin wall-mounted catalog, current at 01/2026
- SHA-256: `58323a53015fb4d0eebecc6d4e8f2e0dc3a956a625a5fbf6ab018ece17eba66d`
- Source section for technical values: `TECHNICAL_APPENDIX`
- One import row represents one complete indoor/outdoor Product combination.

## Runtime contract proven from code

The production import path is `DataImportService` → `ProductImportHandler` → `ProductTechnicalSpecWriter`.

- XLSX parsing uses the active sheet and its first row as exact column names.
- Create requires `name`.
- `product_category_id` is mandatory for every Product row so its technical schema can be checked.
- A category is usable only when its technical schema is active and non-empty.
- Update/upsert matching supports `sku`, `slug`, or `id`; this dataset uses `sku`.
- Product uniqueness is defended by `sku` and `slug`, including soft-deleted records.
- A non-numeric `brand_id` or `product_category_id` is resolved by exact name.
- Any technical value requires complete field provenance: source document/hash/page/row/column/section/extraction method.
- Technical values are written through `ProductTechnicalSpecWriter`; they are not directly forced into Product technical columns.

## Current database gate

Brand `Daikin` exists as ID 1. No wall-mounted Product category exists. Existing active categories are floor/ceiling, cassette, floor-standing, VRF/GMV, and concealed duct; none is a truthful substitute.

Therefore all 51 extracted rows are `REVIEW_REQUIRED`. `IMPORT_READY` intentionally has headers only. Import can be enabled only after a wall-mounted category and its active technical schema are approved.

> Historical note: this describes the initial extraction workbook and remains for audit chronology. The code-owned `wall-mounted-v1` schema and final import-ready workbook now close this gate; see [WALL_MOUNTED_TECHNICAL_SCHEMA.md](WALL_MOUNTED_TECHNICAL_SCHEMA.md). The normal database was deliberately not seeded or imported during closure.

## Import-column matrix

| System field | Import column | Type | Unit | Required | Unique/update | Source / transformation |
|---|---|---:|---|---|---|---|
| Product name | `name` | string | — | create: yes | no | Source-faithful generated label using series, mode, nominal BTU and model pair |
| SKU | `sku` | string | — | update-by-SKU: yes | unique/update key | `INDOOR-OUTDOOR`, preserving complete model codes |
| Model pair | `model_code` | string | — | recommended | business identity | `INDOOR/OUTDOOR`, paired from one catalog table column |
| Brand | `brand_id` | integer or exact name | — | recommended | FK | Exact name `Daikin` resolves to existing brand |
| Category | `product_category_id` | integer or exact name | yes | mandatory | FK | Blocked: no approved wall-mounted category exists |
| Series | `series` | string | — | no | no | Exact series code |
| Cooling capacity | `btu` | integer | BTU/h | no | no | Published nominal BTU; not converted from kW |
| Cooling capacity | `capacity_kw` | decimal | kW | no | no | Published nominal kW |
| Marketing HP | `hp` | decimal | HP | no | no | 1/1.5/2/2.5 groups map exactly; 3–3.5 remains blank because no single HP value is stated |
| Inverter | `inverter` | boolean | — | no | no | Catalog inverter mark; FTF is false |
| Operating mode | `cooling_type` | enum | — | no | no | `1_chieu` or `2_chieu` from catalog table grouping |
| Supply | `voltage` | string | V/Hz | no | no | Published supply text retained |
| Cooling input | `power_input_kw` | decimal | kW | no | no | Published nominal W divided by 1000; raw W remains in `QA_SOURCE` |
| Airflow | `airflow` | string | catalog: m³/min | no | no | Raw ordered levels retained; final schema unit must be approved before release |
| Indoor noise | `noise_level` | string | dB(A) | no | no | Published ordered levels |
| Indoor dimensions | `indoor_dimensions` | string | mm | no | no | Catalog `C × R × D` retained as height × width × depth |
| Outdoor dimensions | `outdoor_dimensions` | string | mm | no | no | Catalog `C × R × D` retained as height × width × depth |
| Indoor weight | `weight` | decimal | kg | no | no | Published indoor-unit weight |
| Source file | `source_pdf` | string | — | technical: yes | no | Exact filename |
| Source digest | `source_sha256` | string | — | technical: yes | no | Exact SHA-256 above |
| PDF page | `source_page` | integer | page | technical: yes | no | Physical PDF page 40–49 |
| Source row | `source_row` | string | — | technical: yes | no | Technical-table row context |
| Source column | `source_column` | string | — | technical: yes | no | Exact indoor model table header |
| Source section | `source_section` | enum | — | technical: yes | no | `TECHNICAL_APPENDIX` |
| Extraction | `extraction_method` | string | — | technical: yes | no | `VISUAL_VERIFIED` |

## QA-only fields

`QA_SOURCE` contains the full normalized specification inventory, including ranges, heating values, voltage-dependent current strings, airflow/noise levels, compressor, refrigerant charge, unit dimensions/weights, piping, and DB/WB operating ranges. These fields are not silently promoted into the Product import until the wall-mounted technical schema defines their keys and units.

`refrigerant_type` is blank/`NOT_STATED`: the technical tables publish refrigerant charge but do not identify the refrigerant type. No R32/R410A assumption was made.

## Null, precision, and boolean policy

- Unstated values are blank, never zero.
- Published decimal precision is preserved.
- Published BTU and kW are stored independently; conversion is used only for anomaly checks.
- Boolean cells use native XLSX booleans.
- Model codes are stored as text and are not stripped of meaningful suffixes.

## Release procedure after category approval

1. Create/approve the truthful wall-mounted Product category.
2. Activate a schema that explicitly defines field keys and units, especially airflow, separate indoor/outdoor weight, piping, and operating ranges.
3. Fill `product_category_id` in the review rows.
4. Re-run read-only validation until all rows are valid.
5. Move only valid rows to `IMPORT_READY`.
6. Import into an isolated database and perform import/export round-trip QA before any production import.
