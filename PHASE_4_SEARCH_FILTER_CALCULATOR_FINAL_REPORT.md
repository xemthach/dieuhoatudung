# PHASE 4 — Search / Filter / Calculator Final Report

## Baseline and safety

Read-only verification: 81 Products, 212 catalog sources, 36,453 catalog models, 656,507 catalog fields, 90 migrations. The canonical `products(id,btu)` JSON-row hash remains `3e981c60fcadd3461746fd8f3b94855dc5205bad6c446c55c17066d40c47e3ba`. No Product/catalog mutation, AI provider call or worker enable occurred.

## Search

Exact model/SKU ranking is preserved; prefix/partial model, brand, name, category and Vietnamese-normalized search are deterministic. Input is bounded and escaped. Brand-only search is regression-covered. No fuzzy identity matching was introduced.

## Filters and URL state

GET filter state, sort and pagination remain query-preserving. Capacity buckets are canonical marketing BTU/RAC semantics and reject explicit VRF/GMV classes. Unknown bucket values are discarded. No kW/BTU mixing or conversion was introduced.

## Calculator

The existing advisory BTU formula and validation contract were retained. Product matching uses marketing capacity and excludes explicit VRF classes from RAC recommendations. Boundary, RAC semantic and VRF exclusion tests pass.

## Performance

Observed read-only query logs: search exact model 8 queries, filtered listing 3 queries, calculator matching 5 queries. These include framework, relationship and schema checks; no per-result query explosion was observed. No new cache or speculative index was added.

## Browser and remaining scope

No Playwright/Dusk/browser dependency is present in the repository/package, so browser PASS is not fabricated. Server/feature/component paths are covered by tests. Media, mojibake and category-schema data issues remain outside Phase 4 as instructed.

## Tests

Final `php artisan test --no-ansi`: 312 tests, 988 assertions, 0 failures. PHP lint, Blade cache and `git diff --check` pass.

## Gate decision

**PHASE 4 = PASS.** READY FOR PHASE 5 — SEO / MERCHANT / STRUCTURED DATA.
