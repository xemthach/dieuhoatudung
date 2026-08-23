# Calculator Equipment Type Recommendation Audit

Date: 2026-08-24
Scope: deterministic post-calculation recommendation only

## Executive verdict

`EQUIPMENT TYPE RECOMMENDATION = PASS`.

The calculation formula remains brand-neutral and unchanged. The new layer separates raw load, market tier, requested equipment type and actual site availability. AI is not required and no AI provider is called.

## 1. Which equipment types exist?

The code already defines RAC split, cassette, ducted, floor/ceiling and floor-standing classes plus VRF/OTHER/UNKNOWN. The public preference selector now exposes `unsure`, wall-mounted, cassette, ducted, ceiling-exposed and floor-standing. VRF, OTHER and UNKNOWN are excluded from normal recommendations.

Current Product evidence is uneven: 14 Products resolve to cassette, 5 ducted, 7 ceiling-exposed and 7 floor-standing before the canonical-capacity gate. There are no current wall-mounted Product rows. Three records have a clear label/taxonomy conflict, 17 remain unclassified, and 28 are VRF outdoor. No data was edited.

## 2. What verified capacity ranges exist?

Observed official Vietnam product-line references:

- Wall-mounted: Panasonic rated models to 20,800 BTU/h and LG official 24,000 BTU model; consumer advisory envelope 8,700–24,000 BTU/h, MEDIUM confidence.
- Cassette: Panasonic 11,600–48,500 BTU/h, HIGH confidence.
- Ducted: Panasonic 17,100–47,800 BTU/h, MEDIUM confidence because installation design remains outside calculator scope.
- Ceiling-exposed: Panasonic 20,500–60,000 BTU/h, HIGH confidence as an observed line range.
- Floor-standing: Panasonic 20,500–47,750 BTU/h, HIGH confidence as an observed line range.

These are market reference envelopes, not universal technical maxima.

## 3. What ranges exist in the current catalog?

After active/stock, verified type, clear conflict and canonical `marketing_capacity_btu` gates:

- Cassette: 7 models, 18,000–48,000 BTU.
- Ducted: 2 models, 48,000–55,000 BTU.
- Wall-mounted, ceiling-exposed and floor-standing: no Product passes every gate.

Legacy `btu` values are not silently promoted to canonical Product recommendation capacity. This is why the strict envelope is smaller than the raw 81-row inventory.

## 4. When is wall-mounted unsuitable?

Above 24,000 BTU/h the current observed official single-split reference envelope is exceeded, so the status is `NOT_RECOMMENDED_FOR_THIS_LOAD`. At or below 24,000 BTU/h the type may exist in the market, but the current site catalog has no eligible model, so the status is `NO_MATCHING_PRODUCT`, not “technically impossible.”

## 5. When is cassette appropriate to consider?

The target must fit the verified market/site envelope, a current cassette Product must have canonical capacity at or above the target, and the user must confirm adequate ceiling clearance. Unknown ceiling clearance returns `POSSIBLE_BUT_REVIEW_REQUIRED`.

## 6. When does cassette require escalation?

Escalation applies when ceiling clearance is unknown/negative, no matching site model exists, or target capacity exceeds the verified single-unit envelope. Drainage, outdoor-unit position, power and piping still require field confirmation.

## 7. When is ducted appropriate?

Ducted is only “possible to consider” when capacity and site Product gates pass. Even with known ceiling/duct space, the result remains review-required because duct layout, airflow and static pressure are not calculator inputs.

## 8. When does the calculator stop recommending single-unit solutions?

When the target exceeds every verified normal single-unit market/site envelope (currently 60,000 BTU/h), it returns `TECHNICAL_CONSULTATION_REQUIRED`. It does not infer an exact number of units.

## 9. When is technical consultation mandatory?

- Load exceeds all verified normal single-unit envelopes.
- Requested installation type requires conditions not collected or those conditions are unknown/negative.
- Market capability exists but the site has no eligible Product.
- Product taxonomy/capacity authority is insufficient.

The CTA reuses the canonical quote flow and website contact settings.

## 10. Why no automatic multiple-unit recommendation?

Dividing total BTU by a model size does not determine zoning, placement or airflow. The engine therefore returns no quantity and only states that multiple indoor units or a commercial system may need technical review.

## 11. Brand neutrality

No brand is part of the capacity/type decision. Models are ranked by type match and non-negative capacity delta. The explicit price priority is only a tie-breaker after capacity fit. Brand remains descriptive metadata on model cards.

## 12. Actual model ranking

Gate order:

1. active and not explicitly out of stock;
2. canonical marketing capacity present;
3. verified supported Product type with no clear label/taxonomy conflict;
4. capacity at least the market tier and no more than 12,000 BTU above it;
5. requested type match;
6. smallest capacity delta;
7. explicit price tie-breaker or stable sort/ID.

Up to five models are shown. Under-sized, UNKNOWN, OTHER and VRF Products are never included.

## 13. AI required?

No. `EquipmentTypeRecommendationService` is deterministic and operates with the AI worker disabled and provider unavailable.

## 14. Optional AI explanation

Not implemented. A future optional layer may verbalize the final structured result but cannot change BTU, type status, Product eligibility, unit quantity or installation feasibility.

## Implementation

- Added `EquipmentType` and `EquipmentSuitabilityStatus` enums.
- Added `ProductEquipmentTypeResolver` with fail-closed conflict detection.
- Corrected `ProductHvacClassResolver` to evaluate both category and `product_type`; conflicting recognized sources now fail closed.
- Added `EquipmentTypeRecommendationService` and governed source config.
- Added equipment preference and conditional cassette/ducted questions to the calculator.
- Rebuilt result hierarchy around raw need, market tier, selected type, assessment, models, installation notes and consultation CTA.
- Extended the server-side Calculator → Quote session bridge with requested type and status.
- Added read-only Filament governance for market and site envelopes.

Existing routes and Product/catalog schema remain unchanged.

## Tests

Focused matrix covers wall-mounted fit/range, cassette installation state, ducted review, unspecified type, catalog/type gap, large-load escalation, VRF exclusion, taxonomy conflict, deterministic ranking, request allowlists and quote handoff. Existing Calculator V1/V2 and Quote tests remain part of the gate.

Validation result:

- Focused Calculator/Quote matrix: 86 tests, 418 assertions, PASS.
- Full suite: 449 tests, 1,686 assertions, 448 passed, one existing skip, zero failures/errors.
- Composer validate/audit: PASS; no known security advisories.
- npm audit: PASS; zero vulnerabilities at the requested threshold.
- Vite production build: PASS.
- Laravel config, route and view caches: PASS.
- PHP lint: 46 changed/new PHP files, PASS.
- `git diff --check`: PASS.
- Browser proof: NOT CLAIMED; no browser transport was used for this task.

Read-only data proof after validation:

- Products: 81.
- Catalog sources: 212.
- Catalog models: 36,453.
- Catalog fields: 656,507.
- Migrations: 93.
- Canonical `products(id,btu)` JSON-row SHA-256: `3e981c60fcadd3461746fd8f3b94855dc5205bad6c446c55c17066d40c47e3ba`.
- AI worker desired state: `DISABLED`.
- Provider calls caused by this task: 0; latest persisted request log remains the historical record at `2026-08-23 10:14:31`.
- Product/catalog technical writes: 0.

## Artifacts

- `calculator_equipment_type_inventory.csv`
- `calculator_type_capacity_envelopes.csv`
- `calculator_type_source_matrix.csv`
- `calculator_type_suitability_rules.json`
- `calculator_type_alternative_rules.json`
- `calculator_type_scenario_matrix.csv`

## Remaining limitations

- Current Product taxonomy and canonical capacity coverage are incomplete; this is surfaced as catalog evidence gaps, not silently repaired.
- Market envelopes use official observed product lines and do not represent universal manufacturer maxima.
- Electrical suitability and installation feasibility require model-level/site evidence.
- Browser evidence is pending if no authenticated browser transport is available.
