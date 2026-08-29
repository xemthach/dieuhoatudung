# Daikin SkyAir 2026 Extraction and Import Readiness Report

## Verdict

**DAIKIN SKYAIR 2026 EXTRACTION = PASS** for the controlled inventory and source-backed combination package.

**DAIKIN SKYAIR 2026 IMPORT PACKAGE = PARTIAL / REVIEW_REQUIRED** until an operator reviews the 225-row workbook and approves the category-specific schemas for production use. Production was not imported.

## Source and review coverage

- Source: Daikin SkyAir 2026 catalog supplied in the project data directory.
- Physical PDF pages: 88; reviewed: 88/88.
- Lineup/pairing evidence: PDF pages 5–10 and corresponding printed pages.
- Features and functions: PDF pages 11–54.
- Technical tables: PDF pages 55–66.
- Accessories/controllers and conditions: PDF pages 67–69.
- Dimensions and installation drawings: PDF pages 71–87.
- Source SHA-256: `F02E3C7B0F993D636630AB4C640D3C7662AA2BF0CC9F5F1957CF460DF7C659DE`.

Printed pagination is retained separately from physical PDF page numbers because the catalog uses spread layouts.

## Inventory

| Measure | Count |
|---|---:|
| Indoor models | 125 |
| Outdoor models | 74 |
| Explicit system combinations | 225 |
| Provenance cells | 7,347 |
| Feature definitions | 14 |
| Accessory rows | 125 |
| Controller compatibility rows | 53 |
| Duplicate SKUs | 0 |

### By equipment type

| Type | Combinations |
|---|---:|
| Cassette | 90 |
| Ducted | 57 |
| Floor standing | 39 |
| Ceiling suspended | 39 |

### By outdoor family

| Family | Combinations |
|---|---:|
| RZF | 74 |
| RZA | 45 |
| RZFC | 56 |
| RNQ | 34 |
| RC | 12 |
| RN | 1 |
| RCN | 3 |

### Operating variants

- Cooling-only: 180 combinations.
- Heat-pump: 45 combinations.
- R32: 175 combinations.
- R410A: 50 combinations.
- Single phase: 138 combinations.
- Three phase: 87 combinations.

## Category/schema audit

The database already has truthful categories for all four SkyAir indoor equipment families. Existing schemas were too small for the commercial catalog appendix, so the package adds category-specific active schema versions:

- `skyair-cassette-v1`: 38 fields.
- `skyair-ducted-v1`: 38 fields.
- `skyair-floor_standing-v1`: 34 fields.
- `skyair-ceiling_suspended-v1`: 34 fields.

The schema seeder is idempotent and fails closed if it finds an unexpected active schema version. No catch-all `SkyAir` category was added.

## Evidence and normalization

The extractor records model, indoor type, outdoor family, capacity, phase, voltage/frequency source, refrigerant, operating mode, technical values, PDF page, printed page, table, row, column, extraction method and confidence. The technical appendix keeps source values instead of guessing missing values.

The following are deliberately separate artifacts:

- system combinations;
- technical field inventory;
- feature matrix;
- accessory matrix;
- controller compatibility;
- category mapping;
- database difference report;
- field-level QA source ledger.

## Import readiness

The controlled workbook contains 225 recognized rows:

- recognized: 225;
- valid after schema extension: 225;
- review-required by structural validation: 0;
- duplicate SKU: 0;
- existing Product matches in current DB: 0;

The real current-DB dry-run reported `product_writes = 0`. This is readiness evidence, not authorization to import.

## Isolated import and round-trip

The isolated Laravel test imports all 225 rows into an isolated database, confirms inactive/noindex status, confirms category assignment and capacity presence, and exports representative rows back through the application exporter. The test suite also verifies that compatibility states include controller-required and optional semantics.

## Safety and limitations

- No production Product rows were created or updated.
- The existing 132 Product rows, 212 catalog sources, 36,453 catalog models and 656,507 catalog model fields remained unchanged during the schema-only operation.
- Migration count remains 93.
- No provider call was made and the AI worker was not enabled.
- `catalog_component_matches = 0` means this current catalog-model table does not yet contain these component identities; it is not evidence that the PDF pairings are invalid.
- Feature availability is modeled as source-backed compatibility evidence, not as a universal Product boolean.
- Accessory conditions and controller dependencies require operator review before public merchandising.

## Artifacts

- `DAIKIN_SKYAIR_2026_IMPORT.xlsx`
- `docs/catalog/DAIKIN_SKYAIR_2026_IMPORT_CONTRACT.md`
- `docs/reports/final/artifacts/skyair_series_inventory.csv`
- `docs/reports/final/artifacts/skyair_indoor_model_inventory.csv`
- `docs/reports/final/artifacts/skyair_outdoor_model_inventory.csv`
- `docs/reports/final/artifacts/skyair_combination_matrix.csv`
- `docs/reports/final/artifacts/skyair_category_mapping.csv`
- `docs/reports/final/artifacts/skyair_schema_field_inventory.csv`
- `docs/reports/final/artifacts/skyair_feature_matrix.csv`
- `docs/reports/final/artifacts/skyair_accessory_matrix.csv`
- `docs/reports/final/artifacts/skyair_controller_compatibility.csv`
- `docs/reports/final/artifacts/skyair_database_difference_report.csv`
- `docs/reports/final/artifacts/skyair_2026_extraction_coverage.csv`
- `docs/reports/final/artifacts/skyair_qa_source.csv`

## Final action

Do not import production. An operator must approve the workbook and any catalog-model reconciliation separately. This task does not authorize commit, tag or push.
## Historical post-extraction operator re-audit

Historical intermediate state: a fail-closed capacity sanity review temporarily classified six rows `REVIEW_REQUIRED` and 219 `IMPORT_READY`. This is retained for chronology only. The six values were subsequently verified against PDF page images and corrected in the extractor. Current state is 225 `IMPORT_READY`, 0 `REVIEW_REQUIRED`, with all 225 operator decisions still `REVIEW`.

## Six-row visual source resolution

The six temporary anomalies were re-read from the PDF page images: FCFG140/RZFC140 on physical page 61, and FHNQ36/RNQ36 plus the four FVGR package columns on physical page 65. The extractor's flattened-cell interpretation was corrected in `scripts/extract_skyair_catalog.py` using an explicit, source-keyed visual recheck with original PDF hash, page, printed page, table row and column provenance. It does not infer from capacity class or neighbouring models. The regenerated package contains 225 `IMPORT_READY`, 0 `REVIEW_REQUIRED`, 0 duplicate SKU, and the operator workbook still defaults all 225 decisions to `REVIEW`.
