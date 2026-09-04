# SkyAir commercial technical edit, BTU hydration and public presentation hardening

## Scope and safety boundary

- Local repository: `D:\laragon\www\dieuhoa-tudung` at `v1.33.6`.
- No Production database, workbook, manifest, Catalog Import provenance rule, Product System Restore contract, Product ID policy, or BTU-filter semantics was changed.
- The supplied source workbook `C:\Users\Peter\Desktop\SkyAir-Model.xlsx` was read only. It contains indoor/outdoor model rows and separate accessory/controller matrices; it was not imported or modified.

## Source and storage audit

The SkyAir controlled-import contract defines one Product as one explicitly published indoor/outdoor combination. That pair is persisted as `products.model_code` in the form `indoor_model/outdoor_model`; deterministic SKU is the same pair with `/` replaced by `-`.

The current Local database contains 378 Products, including 225 SkyAir records. SkyAir category schemas (`skyair-*-v1`) support technical facts such as rated technical BTU, kW, phase, voltage, and frequency. The public commercial field remains `products.marketing_capacity_btu`.

`SkyAir-Model.xlsx` does contain controller and panel/accessory material, but those entries are compatibility matrices scoped by source rows/series. They are not stored as per-Product controller/panel facts in the current Product import contract. Consequently this change renders only the verified indoor/outdoor pair and does not invent a remote or panel selection.

## Proven defects and remediation

| Issue | Evidence | Correction |
|---|---|---|
| Phase was coerced to voltage | `ProductImportMapper` and `ProductTechnicalFieldAliasRegistry` mapped `phase` to `voltage`. | Phase remains a distinct source-native technical fact in `specs_json`; voltage remains the dedicated Product column. |
| Phase/frequency had no audited form write path | SkyAir schemas expose these facts but the Product Edit form only exposed dedicated columns. | Added capability-gated virtual fields `technical_phase` and `technical_frequency`; they hydrate through `ProductTechnicalFactResolver` and save through `ProductTechnicalSpecWriter`. |
| Manual corrections could overwrite source meaning | Phase/frequency have no dedicated Product columns. | A correction appends/replaces only a `MANUAL_OVERRIDE` row. Original `TECHNICAL_APPENDIX` rows remain immutable audit evidence and an override reason is mandatory. |
| Public card hid marketing BTU whenever kW existed | The card used an `if/elseif` path. | Card now independently displays canonical commercial BTU and rated kW when each exists. |
| Product detail used a stale capacity variable after the presentation change | Full detail render regression exposed an undefined `$sourceNativeCapacity`. | All detail consumers use the resolver-derived commercial BTU, technical BTU, and rated kW fields. |

## Current contract

- `marketing_capacity_btu`: customer-facing commercial tier; SQL filter/facet/search contract remains unchanged.
- `technical_capacity_btu`: rated technical fact; shown in technical detail only and never used as a marketing-tier fallback.
- `capacity_kw`: rated technical capacity; independent public display where source/current canonical value exists.
- `phase` and `frequency`: source-native `specs_json` facts. Admin changes are explicit manual overrides, not catalog provenance rewrites.
- Commercial composition panel: only for a category whose schema version begins `skyair-` and a canonical `indoor/outdoor` model code. Normal RAC Products do not render it.

## SkyAir import blocker retained

`DaikinSkyAirImportReadinessTest::test_all_verified_combinations_validate_import_and_round_trip_in_isolation` currently fails before the changes in this report because the untracked `DAIKIN_SKYAIR_2026_IMPORT.xlsx` rows carry historical `brand_id=2` and `product_category_id=7`, while the canonical SkyAir category mapping is `23/24/25/27` and the isolated test does not supply IDs 2/7.

This is retained as `BLOCKED_SOURCE_FK_MAPPING`, not patched by fabricating FK rows or editing the workbook. It is unrelated to the Product technical edit/hydration path and prevents a claim that the full suite is clean.

## Certification evidence

Focused Local suite:

```text
26 tests passed
127 assertions
```

Covered:

- technical BTU hydration and legacy-BTU separation;
- manual override reason, source evidence preservation, phase/frequency override and reload semantics;
- Product System Restore round-trip technical fields;
- public marketing-capacity filter semantics and card display;
- Product detail numeric safety and SkyAir indoor/outdoor composition;
- no `phase -> voltage` coercion.

Browser Local certification:

```text
tests/browser/product-technical-edit.spec.ts: 1 passed
```

The browser fixture verified editable BTU, kW, HP, phase, and frequency; audited save; reload persistence; and absence of page/console/request/HTTP-500 errors.

Full PHPUnit was captured to `storage/logs/full-phpunit-skyair-commercial-hardening-20260904.log`:

```text
573 tests total
571 passed
1 skipped
1 failed
3,097 assertions
160.659 seconds
exit code 1
```

The sole failure is the exact retained `DaikinSkyAirImportReadinessTest::test_all_verified_combinations_validate_import_and_round_trip_in_isolation` source-FK fixture failure described above. No new failure in this change set was observed.

Static and build gates:

```text
PHP lint (all changed PHP/Blade files): PASS
git diff --check: PASS
composer validate --strict: PASS
composer audit: PASS (no advisories)
npm audit --audit-level=high: PASS (0 vulnerabilities)
npm run build: PASS
```

The build produced the normal tracked `public/build` asset delta. It is classified as `GENERATED` and is not part of this Local-only certification change set.

## Status

- Technical form hydration/edit/save: PASS.
- Manual override provenance preservation: PASS.
- Marketing versus technical capacity separation: PASS.
- Public marketing BTU filter contract: unchanged and PASS in focused tests.
- Full PHPUnit: superseded by the final clean run below.
- Production mutation: NONE.
- Production deployment: NOT PERFORMED.

## SkyAir FK fixture root cause and canonical mapping

The controlled import workbook is a historical test artifact: all 225 IMPORT_READY rows use brand ID 2 and category ID 7. Those IDs are not portable source authority. The reviewed skyair_combination_matrix.csv is keyed by the immutable combination SKU and carries the canonical category for each source pair; Brand is resolved in the isolated test by its unique daikin slug.

DaikinSkyAirImportReadinessTest now maps the fixture row by SKU before invoking the strict import handler:

- Brand: brands.slug = daikin to the isolated fixture Brand ID.
- Category: skyair_combination_matrix.csv SKU to one of 23, 24, 25, 27.
- The historical 2/7 values are neither inserted nor accepted by ProductImportHandler.

The test still exercises source row to canonical Brand/category to category-schema validation to Catalog Import to JSON round-trip. The former failure is fixed without weakening numeric-FK validation or modifying the workbook.

## Remote/panel source classification

scripts/extract_skyair_bundle_components.py is a reproducible, read-only extractor. It processed C:\Users\Peter\Desktop\SkyAir-Model.xlsx and generated artifacts/skyair_bundle_component_matrix.csv.

The source contains 408 explicit commercial bundle rows for 219 indoor/outdoor pairs. A row such as FCTF50AVM / RZF50DVM + BRC1H63W + BYCQ125EAF8 is classified BUNDLE_ASSIGNMENT, with source sheet and row retained in the artifact.

| Component | Exact pair assignment | Compatibility-only pair | No component evidence |
|---|---:|---:|---:|
| Remote | 25 | 170 | 24 |
| Panel | 65 | 0 | 154 |

An exact component is persisted only when every bundle row for the same indoor/outdoor pair supplies exactly one value. For example, FCTF50AVM/RZF50DVM persists BRC1H63W and BYCQ125EAF8. FCF50CVM/RZF50DVM has both BRC1E63 and BRC7M635F, so no selected remote is persisted; its uniquely sourced panel remains BYCQ125EAF8.

remote_model is now a governed SkyAir schema field. Both component facts are written through the existing provenance-preserving technical writer; no component module or ungrounded selected value was introduced. The public bundle panel renders remote/panel only when a persisted exact fact exists. Standard wall-mounted RAC never enters this SkyAir panel.

## 1P/3P, capacity and wall-mounted regression

- SkyAir combination identity remains one explicit indoor/outdoor pair and distinct SKU; one-phase and three-phase rows remain distinct in the source matrix.
- phase, voltage, and frequency remain separate canonical facts. The phase-to-voltage alias was removed and focused/browser edit-save-reload proof covers the corrected path.
- Local current population: 378 Products; 225 SkyAir Products; 16 marketing capacities; 312 technical BTU values; 323 kW values.
- Current marketing buckets: 18k [1246,1258]; 24k [1237,1240,1247,1259]; 28k []; 36k [1241,1248,1260]; 42k [1239,1242,1249]; 48k [1250,1261].
- The wall-mounted regression renders no commercial bundle panel, even if unrelated specs contain a remote value.

## Final browser and filter evidence

Browser certification passed:

    product-technical-edit.spec.ts: 1 passed
    product-marketing-capacity-filter.spec.ts: 1 passed

The technical browser scenario covers one-phase exact bundle components, a three-phase SkyAir pair with phase 3 and voltage 380-415V, and a wall-mounted RAC record with no commercial bundle panel.

The public filter test asserts exact local Product cards:

- btu[]=18000 to 1246, 1258.
- btu[]=24000 to 1237, 1240, 1247, 1259.
- btu[]=48000 to 1250, 1261.
- btu[]=18000 and btu[]=48000 to 1246, 1250, 1258, 1261.
- Daikin + 18k, wall-mounted category + 18k, and inverter + 18k also pass.

No browser HTTP 500, console error, page error, or failed request occurred.

## Final PHPUnit

    574 tests total
    573 passed
    1 skipped
    0 failed
    3,587 assertions
    137.286 seconds
    exit code 0

The prior SkyAir fixture failure is FIXED_CORRECTLY; no new failures were introduced.

## Live read-only predeploy commands

Run as the production application user from the release checkout. These commands only read runtime/version/data state:

    git rev-parse HEAD
    git describe --tags --always
    cat VERSION
    php artisan tinker --execute="dump([
      'products' => App\Models\Product::count(),
      'marketing_present' => App\Models\Product::whereNotNull('marketing_capacity_btu')->count(),
      'technical_btu_present' => App\Models\Product::whereNotNull('technical_capacity_btu')->count(),
      'capacity_kw_present' => App\Models\Product::whereNotNull('capacity_kw')->count(),
      'tiers' => collect([18000,24000,28000,36000,42000,48000])->mapWithKeys(fn ($tier) => [$tier => App\Models\Product::where('marketing_capacity_btu', $tier)->count()]),
      'skyair_samples' => App\Models\Product::where('series', 'like', 'SkyAir %')->orderBy('id')->limit(10)->get(['id','sku','model_code','marketing_capacity_btu','technical_capacity_btu','capacity_kw']),
    ]);"
    sha256sum app/Services/Product/ProductMarketingCapacityQueryAdapter.php app/Services/Product/ProductFilterService.php

Confirm the deployed SHA against the release SHA, then use the public read-only URLs:

    /san-pham?btu[]=18000
    /san-pham?btu[]=24000
    /san-pham?btu[]=48000
    /san-pham?btu[]=18000&btu[]=48000

## Final closure verdict

    OVERALL_VERDICT = READY
    SKYAIR_FK_ROOT_CAUSE = stale historical fixture IDs 2/7, not Catalog Import validation
    SKYAIR_FK_MAPPING = PASS
    BRAND_MAPPING = PASS
    CATEGORY_MAPPING = PASS
    REMOTE_SOURCE_CLASSIFICATION = 25 exact, 170 compatibility-only, 24 no source evidence
    PANEL_SOURCE_CLASSIFICATION = 65 exact, 0 compatibility-only, 154 no source evidence
    REMOTE_MODEL_PERSISTENCE = PASS
    PANEL_MODEL_PERSISTENCE = PASS
    INDOOR_OUTDOOR_IDENTITY = PASS
    PHASE = PASS
    VOLTAGE = PASS
    FREQUENCY = PASS
    TECHNICAL_FORM = PASS
    MANUAL_OVERRIDE_PROVENANCE = PASS
    MARKETING_TECHNICAL_CAPACITY = PASS
    PUBLIC_CARD = PASS
    FILTER_18000 = PASS
    FILTER_24000 = PASS
    FILTER_48000 = PASS
    FILTER_MULTI = PASS
    WALL_MOUNTED_REGRESSION = PASS
    FOCUSED = PASS
    FOCUSED_COUNTS = 22 tests, 639 assertions
    BROWSER = PASS
    FULL_PHPUNIT = PASS
    FULL_PHPUNIT_COUNTS = 574 total, 573 passed, 1 skipped, 0 failed, 3,587 assertions, exit 0
    NEW_FAILURES = 0
    LIVE_READONLY_AUDIT_COMMANDS = CREATED
    PRODUCTION_MUTATION = NONE
    READY_FOR_PATCH_RELEASE = YES
