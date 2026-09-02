# Product Technical Edit Form + BTU Hydration Fix

Ngày: 2026-09-03. Phạm vi Local; không deploy và không ghi Production.

## Reproduction evidence

Product tái hiện đúng ảnh: `#1331`, SKU `ATKF35ZVMV-ARKF35ZVMV`, model `ATKF35ZVMV/ARKF35ZVMV`, brand `1`, category `29`.

| Field | Raw value |
|---|---|
| `btu` | `null` |
| `marketing_capacity_btu` | `null` |
| `technical_capacity_btu` | `12300` (`verified_candidate`) |
| `capacity_kw` | `3.60` |
| `hp` | `1.5` |
| `inverter` | true |
| `cooling_type` | `1_chieu` |
| `voltage` | `1 pha, 220-240V, 50Hz / 220-230V, 60Hz` |
| `power_consumption` | `1.24` |
| `airflow` | `10.8 / 8.9 / 7.1 / 5.5` |
| `noise_level` | `37 / 33 / 28 / 20` |
| `indoor_dimensions` | `291x775x242` |
| `outdoor_dimensions` | `550x675x284` |
| `weight` | `9` |

`specs_json` retains source-native `capacity_kw=3.6`, `hp=1.5` and other appendix facts with PDF `DAIKIN TREO TUONG 2026.pdf`, SHA-256, page, row, column, `TECHNICAL_APPENDIX`, and `verified_candidate` evidence.

## Root cause

1. `ProductForm` bound the label **Công suất BTU** to legacy `btu`, not canonical `technical_capacity_btu`. `ProductTechnicalFactResolver` resolves technical BTU from `technical_capacity_btu`, then `specs_json.capacity_btu`; it only uses legacy `btu` as display-only historical fallback. Thus Product #1331 showed blank even though `technical_capacity_btu=12300`.
2. Every standard technical input was explicitly `readOnly()`/`disabled()` and `dehydrated(false)`. This was configuration, not RBAC, Livewire, importer, or schema failure: the browser could neither edit nor submit the fields.

## Canonical form contract after fix

| UI field | Form path / write target | Current resolver source |
|---|---|---|
| Công suất BTU | `technical_capacity_btu` | dedicated technical canonical field, then `specs_json.capacity_btu` |
| Công suất kW | `capacity_kw` | manual override mirror when present, else source-native `specs_json.capacity_kw`, then column |
| HP / inverter / cooling / voltage / gas / power / airflow / noise / dimensions / weight / area | same named Product column | manual override mirror when present, else existing specs/legacy precedence |

`marketing_capacity_btu` remains the commercial, public filter field. It is not displayed as technical BTU and no conversion from marketing, kW, HP, or legacy `btu` was introduced.

## Override and provenance

Admin edits use the existing `ProductTechnicalSpecWriter` as the canonical mutation boundary. A changed technical field requires `technical_specs_override_reason`; save records `technical_specs_source=manual_override` and `technical_specs_overridden_at`. Source-native `specs_json` entries are not rewritten, so original catalog evidence remains auditable. Manual current values are distinguishable and take precedence only while explicit override metadata exists.

Normal Catalog Import is unchanged: `ProductImportHandler` continues to require complete `TECHNICAL_APPENDIX` provenance for technical input. System Restore retains its separate preserved-field path; its v1 contract includes `btu`, marketing and technical BTU, status, kW and HP. The defect occurred only at the final Form binding/hydration stage.

## Certification

- Focused: 15 tests, 91 assertions, PASS.
- Browser Local: 1 passed. It opened an isolated Product with `btu=null` and `technical_capacity_btu=12300`, verified BTU/kW/HP editable, saved `12400`, `3.70`, `1.6`, and voltage, reloaded, and verified persistence. The raw source `specs_json.capacity_kw=3.6` remained unchanged. No HTTP 500, page, console, or network errors.
- Full PHPUnit: 569 tests; 567 passed; 1 skipped; 1 known unrelated SkyAir fixture failure. Exact failure remains `DaikinSkyAirImportReadinessTest::test_all_verified_combinations_validate_import_and_round_trip_in_isolation`: isolated fixture lacks Brand ID 2 and Category ID 7. It is outside Product Edit/System Restore technical hydration scope.

## Result

```text
REPRO_PRODUCT_ID = 1331
REPRO_SKU = ATKF35ZVMV-ARKF35ZVMV

TECHNICAL_FIELDS_READONLY_ROOT_CAUSE = ProductForm explicitly used readOnly/disabled plus dehydrated(false) for every standard technical input.
BTU_BLANK_ROOT_CAUSE = ProductForm bound the technical BTU label to legacy btu while the product held canonical technical_capacity_btu.
BTU_FORM_SEMANTIC = technical
BTU_FORM_STATE_PATH = technical_capacity_btu
BTU_CANONICAL_SOURCE = products.technical_capacity_btu, then specs_json.capacity_btu; legacy btu is display-only fallback.

SYSTEM_RESTORE_BTUS = PASS
FORM_HYDRATION = PASS
FORM_EDIT = PASS
FORM_SAVE = PASS
REOPEN_PERSISTENCE = PASS
MANUAL_OVERRIDE_PROVENANCE = PASS
MARKETING_TECHNICAL_BTU_SEPARATION = PASS
CATALOG_PROVENANCE_REGRESSION = PASS
PUBLIC_MARKETING_BTU_FILTER_UNCHANGED = PASS
FOCUSED = PASS
BROWSER = PASS
FULL_PHPUNIT = KNOWN_UNRELATED_FAILURE
PRODUCTION_MUTATION = NONE
READY_FOR_PATCH_RELEASE = YES
```
