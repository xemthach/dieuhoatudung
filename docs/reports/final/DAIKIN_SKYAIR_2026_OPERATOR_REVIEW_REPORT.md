# Daikin SkyAir 2026 Operator Review Report

Status: `PRE-PRODUCTION CERTIFICATION = REVIEW_REQUIRED`

Production import was not executed. No commit, tag, or push was performed for this review package.

## 1. Current package and re-audit

The source is the 88-page Daikin SkyAir 2026 catalogue. Its SHA-256 is `F02E3C7B0F993D636630AB4C640D3C7662AA2BF0CC9F5F1957CF460DF7C659DE`. The extraction and import package were re-read from the workbook, combination matrix, QA source ledger, category mapping, schema inventory, feature matrix, accessory matrix, and controller compatibility matrix.

The package contains 225 explicit indoor/outdoor combinations, 125 indoor models, 74 outdoor models, 7,347 provenance cells, 14 feature definitions, 125 accessory mappings, and 53 controller mappings. Duplicate SKU count is 0.

## 2. Automated validation

Historical intermediate state: the first operator package classified 219 rows as `IMPORT_READY` and 6 as `REVIEW_REQUIRED`. Those six were re-read from the PDF page images and corrected in the extractor with explicit visual provenance:

- `FCFG140AV1V-RZFC140AY19` — malformed extracted capacity text.
- `FHNQ36MV1V-RNQ36MV1V` — capacity text contains an anomalous `42 34,500` sequence.
- `FVGR8PV1-RN80H(E)Y18` — 23.5 kW / 80,000 BTU/h.
- `FVGR10PV1-RCN100H(E)Y18` — 29.3 kW / 100,000 BTU/h.
- `FVGR13PV1-RCN125H(E)Y18` — 35.5 kW / 121,000 BTU/h.
- `FVGR15PV1-RCN150H(E)Y18` — 44.8 kW / 153,000 BTU/h.

The FCFG row is 14.07 kW / 48,000 BTU/h and the FHNQ row is 10.1 kW / 34,500 BTU/h. The corrected source package now contains 225 `IMPORT_READY` and 0 `REVIEW_REQUIRED`.

All 225 rows remain in the operator workbook. All 225 decisions default to `REVIEW`; approved = 0 and rejected = 0.

## 3. Operator review method

`DAIKIN_SKYAIR_2026_OPERATOR_REVIEW.xlsx` contains `OPERATOR_REVIEW`, `HIGH_RISK_REVIEW`, `REVIEW_GROUPS`, `CAPACITY_REVIEW`, `SOURCE_IMPORT_DATA`, and README sheets. The technical source values are not altered. A row can enter a future production workbook only after an explicit `APPROVE` decision and note where required.

## 4. High-risk combinations

221 rows are surfaced in `HIGH_RISK_REVIEW` because the code-based risk rules identify shared outdoor models, multiple indoor pairings, phase/family boundaries, suffix variants, or standard-family table variants. This is a review-prioritization sheet, not an automatic rejection list. All 225 rows still require row-level disposition.

Current severity breakdown: HIGH 217, MEDIUM 6, NORMAL 2. Every high/medium row has explicit `risk_reasons`; risk does not itself change import eligibility.

## 5. Counts

| Dimension | Verified count |
|---|---:|
| Cassette | 90 |
| Ducted | 57 |
| Floor standing | 39 |
| Ceiling suspended | 39 |
| R32 | 175 |
| R410A | 50 |
| Single phase | 138 |
| Three phase | 87 |
| Cooling-only | 180 |
| Heat-pump | 45 |

Pairing is represented by explicit catalogue table rows, not capacity/prefix inference. Category IDs map to the existing truthful commercial categories and each category has a distinct SkyAir technical schema.

## 6. Technical/schema review

The four schemas are `skyair-cassette-v1`, `skyair-ducted-v1`, `skyair-floor_standing-v1`, and `skyair-ceiling_suspended-v1`. Cassette-only panel fields and ducted-only static-pressure fields are kept category-specific. Features, accessories, and controller compatibility remain reference/compatibility data; they are not flattened into product booleans or separate accessory Products.

## 7. Database collision and safety

The current dry-run against the real Product handler reports: recognized 225, valid 225, invalid 0, duplicate 0, exact/soft-deleted Product matches 0, normalized catalog component matches 0, writes 0. Current local counts are Product 132, catalog sources 212, catalog models 36,453, catalog fields 656,507, migrations 93. No production Product or catalog write was performed by this review task; schema seeding only updated the four existing category schema definitions.

The absence of catalog component matches is not treated as permission to infer or rewrite pairing. It means the SkyAir component master is not currently present in the catalog tables used by that comparison.

## 8. Calculator and merchandising safety

No SkyAir Product was imported, so no Product matcher leakage occurred. Existing RAC safety tests remain the gate: no under-sized recommendation and no VRF/VRV/GMV/UNKNOWN catch-all. Commercial candidates may only be surfaced through their verified equipment category. Price, stock, AI content, and public activation are not supplied by this package.

## 9. Workbooks and manifest

- Review workbook: `DAIKIN_SKYAIR_2026_OPERATOR_REVIEW.xlsx`
- Source import workbook: `DAIKIN_SKYAIR_2026_IMPORT.xlsx`
- Manifest: `docs/reports/final/artifacts/skyair_production_import_manifest.csv`
- Capacity review: `docs/reports/final/artifacts/skyair_capacity_review.csv`
- Review summary: `docs/reports/final/artifacts/skyair_operator_review_summary.json`
- Previous review workbook SHA-256: `747C4A48A14A7D42E405734CC01C0CED36B0E4132372F1DC06B25BAAA3B02EDD`
- Previous pre-UX-review workbook SHA-256: `AE1BC2A20FC8C5F4DB416E80D3177F5F40C546357458FBD77B2C7A4179FF5917`
- Review workbook SHA-256: `CB3EEEDFF31E2086D8E8E5F2A021E060098809009C199E87488FDD698FD589F5`
- Source data hash: `F0B6335EE6BF0AAC191E57546D3C1B0854FE69DF45D3618A136F47C91B3CED27`

The manifest has 225 rows with action `REVIEW_PENDING`, approval `REVIEW`, and expected target `NEW`. A production workbook was intentionally not generated because there are no approved rows.

## 10. Dry-run, isolated import, and round-trip

The current source-ready workbook dry-run is PASS for all 225 rows: 225 recognized, 225 valid, 0 invalid, 0 duplicates, 0 writes. The focused regression suite includes exact assertions for all six visual corrections. An approved-workbook dry-run and approved-workbook isolated import are `NOT RUN` because no row has been explicitly approved.

## 11. Production runbook and rollback

The runbook is `docs/catalog/SKYAIR_PRODUCTION_IMPORT_RUNBOOK.md`. It requires a fresh backup, exact workbook hash, preview, row-level approval, and post-import manifest verification. The actual importer uses one transaction per row; rollback must use the backup/import evidence, never “delete the last 225 rows”.

## 12. Final verdict

`DAIKIN SKYAIR 2026 SOURCE ANOMALIES = PASS`

`DAIKIN SKYAIR 2026 PRE-PRODUCTION IMPORT = REVIEW_REQUIRED`

The extraction and automated validation are complete. The current blocker is operator approval: no row has been explicitly approved or rejected. Production import is not authorized. No production import, commit, tag, or push was performed.
