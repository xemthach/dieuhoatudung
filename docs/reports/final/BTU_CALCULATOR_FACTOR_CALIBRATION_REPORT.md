# BTU Calculator Factor Calibration Report

Audit date: 2026-08-23
Current runtime: `consumer-estimate-v1` / `volume-consumer-estimate-v1`
Decision: **V2 PROPOSAL READY — NOT ACTIVATED**

## 1. Executive Verdict

**CALIBRATION AUDIT = PASS**
**CURRENT V1 = REVIEW_REQUIRED**
**METHOD A = V1 RUNTIME PRESERVED; CALIBRATION REVIEW REQUIRED**
**METHOD B = USEFUL HEIGHT-AWARE DERIVATION; NOT INDEPENDENTLY CALIBRATED**
**V2 = PROPOSAL_READY_NOT_ACTIVATED**

Current production factors were not changed. Evidence is strong enough to show systematic low sizing across common residential/office classes and high sizing in restaurant/cafe, but explicit operator approval and adjustment-scope decisions are required before v2 activation.

## 2. Current V1 Baseline

- Method A: area × W/m² × 3.412; height above 3 m; sun ×1.10; equipment ×1.10; +400 BTU/person above 10; ceil market tier.
- Method B: area × height × derived W/m³; the same separately configured adjustments; shared tier and RAC-only matcher.
- 27 area factors: 95–490 W/m².
- Every Method B factor is exactly Method A factor divided by 3 m.
- Tiers: 9/12/18/24/28/30/36/42/45/48/50/60/100k BTU/h; HP display = tier / 9,000.

Full frozen inventory: `calculator_v1_factor_inventory.csv`.

## 3. Source Authority

The historical spreadsheet named by source comments is absent. Therefore v1 base-factor scope is `UNKNOWN` for all 27 keys. It cannot be proven whether typical occupancy, lighting, equipment or solar load is already embedded.

Panasonic Vietnam is the strongest applicable consumer reference found; ENERGY STAR and DOE define useful adjustment/engineering boundaries but use US assumptions. Source records and limitations are in `calculator_source_authority.json` and `docs/calculator/CALIBRATION_REFERENCE_MATRIX.md`.

## 4. Unit Normalization

Analysis uses exact `3.412141633 BTU/h per W`. Runtime remains unchanged at 3.412. At 3 m:

- 600 BTU/m² = 200 BTU/m³ = 175.843 W/m² = 58.614 W/m³.
- 1 HP/40 m³, with project 9,000-BTU HP, = 225 BTU/m³ = 675 BTU/m² = 197.823 W/m².

## 5. 600 BTU/m² Reference

Panasonic Vietnam publishes 600 BTU/h/m² as a consumer baseline. It is reasonable as a **candidate residential baseline for this Vietnam-facing project**, not an engineering standard. Current residential `120 W/m² = 409.457 BTU/m²`, 31.76% below 600 and 41.51% below the user-supplied 700 reference.

## 6. 200 BTU/m³ Reference

Panasonic also publishes 200 BTU/h/m³. This is exactly consistent with 600 BTU/m² at 3 m. Current residential Method B is `40 W/m³ = 136.486 BTU/m³`, 31.76% lower.

## 7. 1 HP / 40 m³ Reference

Using the project's marketing convention, this equals 225 BTU/m³. It is 12.5% above the Panasonic 200-volume rule and is the most conservative of the three universal references. It is not a mechanical HP conversion and remains comparison-only.

## 8. User-Supplied Room-Type Table

The table is useful reference evidence but has unknown provenance. Its `0.235 kW/m²` row converts to 801.853 BTU/m², conflicting with a displayed 850. Status: `SOURCE_INTERNAL_INCONSISTENCY`. Restaurant is not equated to residential dining, and theatre is not equated to dance hall.

## 9. Current 27 Factor Analysis

Of 27 types, 15 have a defensible exact/close comparison. Among those:

- 11 are below reference range;
- 2 are within range (`fastfood`, `hoi_truong`);
- 2 are above range (`nha_hang`, `cafe`);
- median delta versus the chosen typical reference is **-29.62%**.

For eight common residential/office/retail/bank classes, mean delta is -35.07% and median -33.38%. The system is materially low in common classes, but not globally low across all categories.

## 10. Room-Type Mapping

Materially low examples:

- office interior: 341 vs 700–800 BTU/m² (-54.50% vs midpoint);
- hotel: 409 vs 700–800 (-45.41%);
- living room: 409 vs 600–850 (-43.52%);
- library: 512 vs 800–900 (-39.79%);
- residential: 409 vs 600–700 (-37.01% vs midpoint);
- retail: 563 vs 750–850 (-29.62%);
- standard office: 580 vs 700–800 (-22.66%).

Restaurant (1,126) and cafe (1,194) exceed Panasonic's 900–1000 range, proving a global uplift would be unsafe. Twelve specialist/ambiguous types remain `NO_MATCH`/`AMBIGUOUS` and retain v1 in the proposal.

## 11. Method A Analysis

Method A is deterministic and monotonic. Its main calibration concern is the base table, not arithmetic. Below 3 m it refuses to reduce load. Above 3 m it uses `round(H/3, 2)`, introducing small rounding steps.

For a 30 m² residential room, raw results around height are approximately: 12,283 BTU at 2.9/3.0/3.01 m; 13,143 at 3.2; 14,371 at 3.5; 16,336 at 4; 20,513 at 5; 24,566 at 6. The meaningful tier jumps are mostly expected tier boundaries, while the 3.01 rounding plateau is a minor discontinuity.

## 12. Method B Analysis

Method B is not independent: `q_volume=q_area/3`. Its value is explicit treatment of volume, especially below 3 m. It remains deterministic and does not double-apply the Method A height multiplier.

## 13. 3 m Equivalence

Mathematically:

`A × q_area = A × 3 × (q_area/3)`.

Benchmark proof: all **63/63** representative 3 m scenarios have identical Method A/Method B raw BTU and market tiers.

## 14. Height Divergence

Across 63 scenarios per height:

- 2.5 m: 26 same-tier; 37 differ, including 7 by more than one 9k-HP equivalent.
- 2.7 m: 43 same-tier; 20 differ, including 3 by more than one equivalent HP.
- 3.0 m: 63 same-tier.
- 3.5 m: 63 same-tier; only small raw rounding differences.
- 4.0 m: 55 same-tier; 8 differ due rounding/tier edges.
- 5.0 m: 62 same-tier.

Method B is genuinely useful for nonstandard low ceilings, while above 3 m it is mostly an alternate representation of Method A.

## 15. People Adjustment

Current: first 10 included, then +400 BTU/person. ENERGY STAR uses +600 above two for its own US room-AC chart. Neither can be transplanted blindly. For office/restaurant/classroom/hall, high room factors may already embed typical occupancy; current source scope is unknown. The v2 proposal does not change this rule.

## 16. Solar Adjustment

The optional +10% is independently consistent with ENERGY STAR's sunny-room guidance. Applicability to every high commercial factor is not proven, but this is the strongest-supported current adjustment. Keep pending explicit base-scope documentation.

## 17. Equipment Adjustment

Binary +10% lacks a direct project authority. It has high double-count risk for server rooms, factories, laboratories, restaurants and cafes, whose base labels/factors likely represent load-heavy use. ENERGY STAR instead uses a fixed +4,000 BTU kitchen adjustment, illustrating that equipment semantics vary materially.

## 18. Double-Counting Risk

Because the historical factor source is missing, every base scope remains `UNKNOWN`. Highest review priorities are:

1. server/equipment-heavy rooms plus equipment +10%;
2. restaurant/cafe factor plus people and equipment;
3. meeting/classroom/hall factor plus people over 10;
4. office factor plus typical computers/occupants;
5. commercial factors plus sun where facade assumptions are unknown.

This uncertainty blocks automatic v2 activation.

## 19. Tier Amplification

Small raw differences can cause large tier changes. A concrete diagnostic found that rounding a 600-BTU proposal to 176 W/m² makes 30 m² evaluate to about 18,015 BTU, falsely jumping from 18k to 24k. The proposal now preserves three-decimal W precision; a future v2 should consider canonical BTU/m² storage.

Sun/equipment compounding is less material: ×1.21 versus additive ×1.20 differs by only 1% of base and produced the same tier in all six sampled base loads. It is not the primary calibration problem.

## 20. Catalog Capacity Gaps

There are 36 currently eligible verified RAC Products. Actual capacities include 18k, 24k/24.2k, 30k, 35.8/36k, 40.9/42/42.6k, 47.8/48/48.1k and 51.2–55k.

- 9k target → nearest 18k (9k gap).
- 12k → 18k (6k gap).
- 28k → 30k (2k gap).
- 45k → 47.8k (2.8k gap).
- 50k → 51.2k (1.2k gap).
- 60k/100k → no eligible product.

Thus a large displayed Product can be caused by catalog availability even when the formula/tier is correct.

## 21. HP-Level Impact

The proposal's representative 35 scenarios show residential/office/retail uplifts often crossing one or more tiers, while restaurant can move downward. Some apparent +1 HP changes are tier/catalog amplification, not a proportional raw-load difference. HP remains secondary display; BTU/h is primary.

## 22. Under/Over-Sizing Comparison

These are comparative flags, not engineering safety verdicts:

- `REFERENCE_UNDERSIZE_RISK`: residential, hotel, office interior, living room, library, retail, bank and standard office.
- `REFERENCE_OVERSIZE_RISK`: restaurant and cafe relative to Panasonic's 900–1000 range.
- Specialist high-load classes: `NO_COMPARABLE_REFERENCE`, not automatically high/low.

## 23. Proposed V2 Factors

Category-specific candidates include:

- residential 175.843 W/m² (600 BTU/m²), HIGH;
- hotel/office 219.803 W/m² (750 BTU/m²), HIGH;
- living room/interior office 205.150 W/m² (700 BTU/m²), MEDIUM;
- retail 234.457 W/m² (800 BTU/m²), MEDIUM;
- restaurant/cafe 278.417 W/m² (950 BTU/m²), HIGH;
- hall 278.417 W/m², HIGH;
- library/bank 249.110 W/m² (850 BTU/m²), MEDIUM.

Low-confidence categories remain at v1 and are explicitly review-only. No global multiplier is proposed.

## 24. Confidence Matrix

`calculator_v2_factor_proposal.csv` contains all 27 current/proposed values, deltas, evidence, confidence and activation state. HIGH/MEDIUM means proposal evidence is reviewable—not production authorization. LOW means retain v1 pending a better source.

## 25. V1 vs V2 Scenarios

`calculator_v1_v2_comparison.csv` contains 35 representative Method A and Method B scenarios. `calculator_method_benchmark.csv` contains 378 v1/reference scenarios across nine areas, six heights and seven room classes. V1 historical tests are preserved; v2 golden outputs must be added separately only after approval.

## 26. UX Impact

The result now distinguishes:

1. calculated need;
2. market tier;
3. nearest eligible RAC Product capacity.

When the nearest Product exceeds the tier, UI states that this is a catalog gap, not the formula output. No product below target is allowed. If methods differ by a tier, future v2 copy should explain height semantics without claiming one method is always superior.

## 27. Admin Governance

A new coefficient editor is not justified. Existing admin history already displays method/rule version. A future read-only calibration view may consume the proposal/reference matrix, but editable coefficients require source, approval, audit, rollback and v1 replay contracts.

## 28. Testing Strategy

Existing v1 golden tests remain intact. The reproducible diagnostic script generates 378 benchmark cases and 35 v1/v2 comparisons. Any later activation must preserve v1 replay tests and add at least 30 v2 golden/property cases covering room classes, heights, adjustments, catalog gaps and no-result behavior.

Validation completed on 2026-08-24:

- full Laravel suite: 389 tests, 1,394 assertions, 0 failures/errors, 1 existing skip;
- focused calculator suite: 27 tests, 132 assertions, 0 failures/errors;
- Composer validation/audit: PASS, no known advisories;
- npm audit at high severity: PASS, zero vulnerabilities;
- Vite build and config/route/view cache compilation: PASS;
- changed PHP lint and `git diff --check`: PASS;
- HTTP smoke: calculator returned 200 and rendered both method selectors;
- browser automation: NOT AVAILABLE; no browser PASS is claimed.

Read-only safety verification remained at 81 Products, 212 catalog sources, 36,453 catalog models, 656,507 catalog fields, 92 migrations, and canonical BTU hash `3e981c60fcadd3461746fd8f3b94855dc5205bad6c446c55c17066d40c47e3ba`. AI worker desired state remained `DISABLED`; provider request-log count remained 236 with latest record at `2026-08-23 10:14:31`.

## 29. Limitations

- No original authority document for v1 factors or adjustment scope.
- Panasonic pages are manufacturer consumer guidance, not full engineering calculations.
- User table provenance is unknown and internally inconsistent.
- No climate/envelope/material inputs; no claim of engineering safety.
- Proposed factors are not operator-approved.

## 30. Final Decision

**DECISION B — V2 PROPOSAL READY, NOT ACTIVATED.**

Current v1 should remain live until the operator explicitly approves a category-specific v2 and resolves base-factor/adjustment scope. Method B v2 should remain transparently derived from approved Method A v2 at 3 m unless independent room-scoped volume authority is obtained.

### Answers to critical questions

1. Residential v1 is materially low versus 600/700 consumer references.
2. Standard office is low; interior office is materially lower still; private office is moderately low.
3. Eleven of 15 comparable classes are low; restaurant/cafe are high; 12 cannot be safely compared.
4. Method B is useful mainly because it handles low/nonstandard ceiling volume explicitly, despite derivation.
5. 600 BTU/m² is a defensible Vietnam consumer baseline candidate, not an engineering standard.
6. 200 BTU/m³ is exactly consistent at 3 m.
7. 1 HP/40 m³ equals 225 BTU/m³ and is 12.5% more conservative.
8. R3 is the most conservative universal rule; room-specific 1,500/2,000 references are higher where applicable.
9. Double-count risk is highest for occupancy/equipment-heavy classes because base scope is unknown.
10. One-HP jumps arise when small raw changes cross sparse configured tiers.
11. Some jumps are formula/tier effects; others are actual catalog gaps, now shown separately.
12. Category-specific changes merit approval; a global change does not.
13. Exact proposals and reasons are in the 27-row proposal matrix.
14. v2 requires explicit operator approval and is not active.

## V2 Activation Decision — 2026-08-24

The operator subsequently approved category-specific hybrid activation. The original calibration verdict above is preserved as historical evidence.

- Active area rule: `consumer-estimate-v2`.
- Active volume rule: `volume-consumer-estimate-v2`.
- Activated: 13 HIGH/MEDIUM categories from the existing proposal.
- Retained: 14 LOW-confidence categories at exact v1 values.
- Method B remains transparently derived from the selected area profile at 3 m.
- Current adjustment semantics remain unchanged with `ADJUSTMENT_SCOPE_REVIEW_PENDING`.
- V1 rule identifiers and golden replay outputs remain preserved.

Exact decisions and comparisons are recorded in `calculator_v2_activation_matrix.csv` and `calculator_v2_activation_comparison.csv`.
