# Product marketing capacity data parity remediation

Ngày audit: 2026-09-02. Phạm vi: Local remediation và Production evidence do operator cung cấp. Không có write Production.

## Root cause xác nhận

`routes/web.php:39` → `ProductController::index()` → `ProductFilterService::apply()` → `ProductMarketingCapacityQueryAdapter::column()`.

Do Production có `products.marketing_capacity_btu`, actual filter source là chính cột này. `btu[]=18000` tạo equality predicate trên `products.marketing_capacity_btu`; Product có commercial tier hiển thị nhưng cột canonical NULL sẽ không vào kết quả.

Đây là data parity defect, không phải checkbox/frontend defect. `technical_capacity_btu` hoặc `specs_json.capacity_btu` không được dùng thay marketing capacity.

## Canonical contract

| Field | Meaning | Customer filter/search/sort/card/price list/calculator |
|---|---|---|
| `marketing_capacity_btu` | Commercial/customer-facing BTU tier | Canonical persisted value |
| `technical_capacity_btu` | Rated/manufacturer technical capacity | Never used as commercial tier |
| `btu` | Legacy evidence pending provenance review | Never used as public query/display fallback |
| `specs_json.capacity_btu` | Technical/raw source value; may be range | Never inferred into marketing tier |

`ProductMarketingCapacityQueryAdapter` now keeps `column()`, `applyPresent()`, `applyBetween()`, `value()` and `distance()` on canonical marketing capacity whenever the schema contains that column. Product cards/price list/quote/calculator use the same `ProductTechnicalFactResolver` marketing read path, which no longer exposes legacy `btu` as marketing display.

## JSON normalization

`ProductTechnicalFactResolver::specs()` now reads all supported historical structures:

- list `{key, value}` records;
- enriched list records with provenance;
- associative objects such as `{ "capacity_btu": "18000" }`.

This makes source audit truthful, but does not make a technical value into a commercial tier. `16400 / 17100` and `18000 / 19100` remain `AMBIGUOUS`.

## Safe evidence hierarchy

1. Existing `marketing_capacity_btu` → `KEEP`.
2. Verified `CatalogModelField(field_key=marketing_capacity_btu, source_section=PRODUCT_LIST)` with strict positive integer → `PROPOSE_UPDATE`.
3. Legacy `btu`, technical-only scalar, raw specs scalar or any range without the above authority → `AMBIGUOUS`.
4. No capacity evidence → `NO_EVIDENCE`.

No title-only mapping, numeric nearest-tier mapping, or technical range conversion exists.

## Commands

Read-only audit:

```bash
php artisan catalog:audit-marketing-capacity --json
php artisan catalog:audit-marketing-capacity --product=331 --json
```

It emits per Product: ID, SKU/model, current/proposed marketing value, technical/legacy/specs values, source, section, evidence, confidence, reason and action. It writes no Product data.

Controlled backfill:

```bash
# Default dry-run; emits an immutable JSON ledger path.
php artisan catalog:backfill-marketing-capacity --batch=marketing-20260902

# Only after source review approval.
php artisan catalog:backfill-marketing-capacity --apply --approved --batch=marketing-20260902 --batch-size=50
```

The apply path locks each Product and catalog field, verifies current marketing is still NULL and confirms the same verified PRODUCT_LIST source/value before writing only `marketing_capacity_btu`. Existing marketing values are skipped; stale/invalid source evidence aborts the transaction. Technical fields, `btu`, `specs_json` and source evidence remain untouched. Ledger files are stored under `storage/app/private/reports/`.

## Local distribution

Read-only Local audit: 378 Products; 16 marketing present; 66 legacy `btu` present; 296 technical without marketing; 27 with specs capacity; 12 range-shaped; 0 with no capacity evidence. These dimensions can overlap. No Local catalog data was backfilled by this audit.

The local default backfill command completed as a dry-run with `proposals=0` and `database_mutation=NONE`; this is expected because the local data has no verified `PRODUCT_LIST` marketing-capacity facts. It produced only a private local ledger.

Production has 357 Products according to operator evidence. Exact A–G production counts require the read-only command on the authenticated Live host; no production DB session is available here.

## Regression evidence

- Production-equivalent fixtures cover commercial 18k/technical 16.4k, 18k/range, 18k/technical 18k, 48k, trusted Product-list evidence, legacy evidence and ambiguous technical-only records.
- Focused filter/capacity/resolver/calculator/backfill/Quote suite: 34 passed, 194 assertions.
- Playwright Product navigation covers 18k, 48k and 18k+48k URLs: 2 passed; no relevant HTTP 500, console, page or network error.
- Full PHPUnit: 557 tests, 555 passed, 1 skipped, 1 failed, 3,013 assertions, exit 1. The preserved untracked `DAIKIN_SKYAIR_2026_IMPORT.xlsx` is the remaining SkyAir failure: its `IMPORT_READY` rows use `brand_id=2` and `product_category_id=7`, while the versioned SkyAir import contract requires the truthful category mapping 23/24/25/27 and category-specific schemas. The isolated test correctly rejects those foreign keys; creating an arbitrary category 7 would hide an import-contract violation. An earlier null-unsafe Quote read exposed by the canonical marketing resolver is fixed by a null-safe `QuoteController` read and covered by the focused Quote suite. The remaining SkyAir failure reproduces in isolation and is not caused by this remediation.

## Deployment/backfill plan

1. Deploy code only after full-suite external workbook fixture is reconciled.
2. Run Live dry-run and retain JSON ledger.
3. Review only `PROPOSE_UPDATE` rows with Product-list provenance.
4. Approve a batch; run one controlled Product, then a small batch; re-run audit and public browser filter checks.
5. Never mass-update technical/range/legacy-only candidates.

## Verdict

- Actual filter source: `products.marketing_capacity_btu`.
- Filter logic: PASS locally.
- Marketing capacity data parity: FAIL pending production dry-run and approved Product-list-backed backfill.
- Production mutation: NONE.
