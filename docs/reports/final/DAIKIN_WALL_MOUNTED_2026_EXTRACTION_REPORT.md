# Daikin Wall-Mounted 2026 Extraction Report

## 1. Source

- Document: `DAIKIN TREO TUONG 2026.pdf`
- Manufacturer authority: Daikin Air Conditioning (Vietnam)
- PDF pages: 60/60 reviewed
- File size: 131,660,642 bytes
- SHA-256: `58323a53015fb4d0eebecc6d4e8f2e0dc3a956a625a5fbf6ab018ece17eba66d`
- Catalog currency statement: technical characteristics, designs, and contents current at 01/2026 and subject to change.
- Extraction method: two-pass extraction with page rendering and visual table-column verification. The PDF text layer was not treated as authoritative for dense tables.

No external source was required. Official Daikin web research was therefore not used to replace or supplement catalog facts.

## 2. System import contract

The proven path is `DataImportService` → `ProductImportHandler` → `ProductTechnicalSpecWriter`. One Product row represents one indoor/outdoor combination, consistent with existing Product model-code and SKU conventions.

- Create required field: `name`
- Mandatory catalog gate: `product_category_id` resolving to a category with an active technical schema
- Update key selected: `sku`
- Defensive uniqueness: `sku` and `slug`, including soft-deleted Products
- Technical values: accepted only with complete appendix provenance
- Detailed contract: [DAIKIN_WALL_MOUNTED_IMPORT_CONTRACT.md](../../catalog/DAIKIN_WALL_MOUNTED_IMPORT_CONTRACT.md)

## 3. Catalog structure

The catalog contains product-family/feature matrices on PDF pages 4–5, feature explanations and series marketing sections, cooling-only series pages 29–35, heat-pump series pages 37–39, and technical tables on PDF pages 40–49. Feature definitions continue on pages 50–51; sizing and service information follows.

## 4. Series and model inventory

Twelve exact series codes were found:

`FTKZ`, `FTKM`, `FTKY`, `FTKF`, `ATKF`, `FTKB`, `ATKB`, `FTHB`, `FTF`, `FTXM`, `FTXV`, `FTHF`.

The matrix presents FTKF/ATKF and FTKB/ATKB as shared family columns, but the distinct model prefixes are preserved as separate business products.

| Group | Pair count |
|---|---:|
| 1 HP | 12 |
| 1.5 HP | 12 |
| 2 HP | 10 |
| 2.5 HP | 9 |
| 3–3.5 HP | 8 |
| **Total** | **51** |

- Indoor model codes: 51 unique
- Outdoor model codes: 51 unique
- Verified indoor/outdoor pairs: 51
- Cooling-only pairs: 32
- Heat-pump pairs: 19
- Inverter pairs: 47
- Non-Inverter pairs: 4, all in the FTF series

Each pair was read from one technical-table column; no suffix-based pairing was used.

## 5. Technical fields

`QA_SOURCE` contains 3,570 field-level source rows across 51 model pairs. It preserves:

- exact identity, mode, Inverter mark, HP catalog group, and market channel;
- published cooling/heating nominal/min/max kW and BTU/h;
- CSPF;
- supply, phase/frequency, supply location, and voltage-dependent current strings;
- cooling/heating nominal/min/max power input;
- all published indoor airflow and noise levels;
- separate indoor/outdoor dimensions and weights;
- compressor type/output and refrigerant charge;
- separate cooling/heating outdoor noise;
- liquid/gas/drain pipe diameters, maximum length and height difference;
- cooling °CDB and heating °CWB operating ranges.

Catalog dimensions `C × R × D` were normalized as height × width × depth. Published BTU values remain authoritative; kW-to-BTU conversion was used only as an anomaly detector.

### Explicitly missing

- `refrigerant_type`: `NOT_STATED`. The technical tables identify refrigerant charge but not refrigerant type. No R32/R410A inference was made.
- Min/max values absent for fixed-speed FTF models remain blank.
- A single normalized HP is blank for the catalog group “3–3.5 HP”; the source group is retained in QA rather than guessed per model.

## 6. Feature taxonomy

Twenty-seven canonical feature rows were transcribed across ten printed catalog groups and expanded into the twelve exact business series, producing 324 series-feature records:

| Availability | Rows |
|---|---:|
| YES | 173 |
| NO | 122 |
| MODEL_SPECIFIC | 21 |
| OPTIONAL_ACCESSORY | 6 |
| OPTIONAL | 2 |

The taxonomy covers humidity, air quality, comfort, airflow, energy, smart control, durability, and installation. Marketing claims were not converted into technical measurements.

## 7. Conditional and accessory applicability

High-risk matrix conditions were preserved, including 25/35-only, 50/60/71-only, 60/71-only, AT-only, Enzyme Blue without a PM2.5 mark, 24-hour timer annotations, and optional smartphone adapters. `OPTIONAL`, `OPTIONAL_ACCESSORY`, and `MODEL_SPECIFIC` were not reduced to `YES`.

## 8. Model pairing

The complete pairing ledger is [daikin_model_pairing_audit.csv](artifacts/daikin_model_pairing_audit.csv). FTKF/ATKF and FTKB/ATKB rows sharing a technical column were expanded into separate exact indoor/outdoor combinations only where both business variants are printed in the catalog.

## 9. Normalization rules

- Model codes: uppercase and whitespace-trimmed only; suffix letters/numbers are preserved.
- Product model: `INDOOR/OUTDOOR`; SKU: `INDOOR-OUTDOOR`.
- Missing values: blank/`NOT_STATED`, never zero.
- Booleans: native boolean values.
- Numeric values: numeric XLSX cells with published precision.
- Multi-voltage current: original ordered string retained; no collapse to one number.
- Source pages: physical PDF page numbers, with technical tables at 40–49.

## 10. Existing database comparison

Read-only checks found:

- Daikin brand: existing, ID 1
- Exact Product matches: 0
- Exact normalized catalog-model/component matches: 0
- New catalog-only combinations: 51
- Possible duplicates: 0

The detailed ledger is [daikin_catalog_difference_report.csv](artifacts/daikin_catalog_difference_report.csv).

## 11. Ambiguous values

No visually ambiguous model pairing or numeric table cell was released as a verified value. Unstated fields remain blank. The unresolved issue is not a PDF ambiguity: it is the absence of a truthful wall-mounted category/schema in the current application database.

## 12. External official verification

Not required. The catalog itself was legible for all identity, pairing, technical-table, and feature-matrix facts used. This avoids introducing a cross-version conflict from web content.

## 13. Excel structure

Workbook: `DAIKIN_WALL_MOUNTED_2026_IMPORT.xlsx`

| Sheet | Purpose | Rows |
|---|---|---:|
| `IMPORT_READY` | Exact importer columns; intentionally header-only | 0 |
| `FEATURE_MAPPING` | DO_NOT_IMPORT series-feature matrix | 324 |
| `QA_SOURCE` | DO_NOT_IMPORT field-level provenance | 3,570 |
| `README` | Source, safety, and release gate | 10 notes |
| `REVIEW_REQUIRED` | Complete Product candidate rows pending category/schema | 51 |

The workbook has no formulas, merged cells, images, hidden calculations, or duplicate headers. Model codes are text-safe and Vietnamese Unicode is preserved.

## 14. Import dry run

The real `ProductImportHandler::validateRow()` was invoked read-only against `REVIEW_REQUIRED`:

- Recognized rows: 51
- Structurally parsed rows: 51
- Valid rows: 0
- Invalid rows: 51
- Duplicate SKU: 0
- Duplicate model code: 0
- Error class: `MISSING_PRODUCT_CATEGORY` on all 51 rows
- Import executed: no
- Database writes: 0

The active `IMPORT_READY` sheet is intentionally empty, so a normal import cannot accidentally create misclassified Products.

## 15. Automated QA

- Duplicate indoor models: 0
- Duplicate outdoor models: 0
- Duplicate system pairs: 0
- Cooling/heating min ≤ nominal ≤ max anomalies: 0
- Material published kW/BTU relation anomalies above 4%: 0
- Negative capacity/power/dimension/weight values: 0
- Feature matrix cells without an explicit availability state: 0
- Workbook formula cells: 0
- Workbook duplicate headers: 0

Coverage detail is in [daikin_2026_extraction_coverage.csv](artifacts/daikin_2026_extraction_coverage.csv).

## 16. Data safety

- Product writes: 0
- `catalog_sources` writes: 0
- `catalog_models` writes: 0
- `catalog_model_fields` writes: 0
- AI provider calls: 0
- Worker state changes: 0
- Import execution: 0

## 17. Remaining review items

1. Create and approve the “Điều hòa treo tường” Product category.
2. Define and activate its technical schema, including explicit units for airflow and separate indoor/outdoor data.
3. Decide the system-normalized HP treatment for the source group “3–3.5 HP”; do not infer it from BTU without approval.
4. Revalidate all 51 rows and move only valid rows into `IMPORT_READY`.
5. Run an isolated test-database import and import/export round trip.

## 18. Final verdict

**CATALOG EXTRACTION: PASS**

**TECHNICAL AND FEATURE NORMALIZATION: PASS**

**PRODUCTION IMPORT READINESS: PARTIAL / REVIEW_REQUIRED**

Exact blocker: `MISSING_WALL_MOUNTED_CATEGORY_AND_ACTIVE_TECHNICAL_SCHEMA`.

No data was guessed and no production data was changed.

## 19. Schema / import readiness closure

The historical blocker above is retained for chronology. It has since been closed by the separately audited package documented in [DAIKIN_WALL_MOUNTED_SCHEMA_AND_IMPORT_READINESS_REPORT.md](DAIKIN_WALL_MOUNTED_SCHEMA_AND_IMPORT_READINESS_REPORT.md).

- Category configuration: created as runtime ID 29 and reproducible as `Điều hòa treo tường`, slug `dieu-hoa-treo-tuong`; no duplicate current or soft-deleted match existed, and a second seeder run remained at one row.
- Public category state: inactive/noindex until Products are deliberately imported and verified.
- Active schema: `wall-mounted-v1`, 75 fields.
- Final workbook: `DAIKIN_WALL_MOUNTED_2026_IMPORT_READY.xlsx`.
- Actual handler validation: 51 recognized, 51 valid, 0 review required, 0 invalid, 0 duplicate SKU.
- Isolated SQLite import: 51 created, 0 updated/skipped/failed.
- Import/export verification: 72/72 representative value/unit checks PASS.
- Refrigerant type: remains unknown/null.
- Source group 3–3.5 HP: remains null; no inferred exact HP.
- Normal Product/catalog technical writes: 0; one authorized category/schema configuration row was created.

Current readiness verdict: **PRODUCTION IMPORT PACKAGE = READY**. Production import itself remains unauthorized and was not performed.
