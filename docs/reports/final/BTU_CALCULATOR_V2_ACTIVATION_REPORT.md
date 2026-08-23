# BTU Calculator V2 Activation Report

Activation date: 2026-08-24
Scope: controlled category-specific hybrid activation

## 1. Approval Scope

Approved: category-specific HIGH/MEDIUM proposals already recorded by the calibration audit. Not approved: a global multiplier, guessed LOW-confidence values, Product technical-data changes, recommendation relaxation, or engineering-design claims.

## 2. V1 Freeze

`consumer-estimate-v1` and `volume-consumer-estimate-v1` remain immutable, directly resolvable, and covered by their original exact golden outputs. Historical rows are not rewritten. New calculations use explicitly configured active versions rather than lexical version discovery.

## 3. Activation Matrix

The 27-row decision artifact is `artifacts/calculator_v2_activation_matrix.csv`:

- `ACTIVATE_V2`: 13;
- `REVIEW_ONLY` with exact v1 retention: 14;
- LOW-confidence changes activated: 0.

## 4. Activated Factors

| Category | V1 W/m² | V2 W/m² | Confidence |
|---|---:|---:|---|
| Căn hộ, nhà ở | 120 | 175.843 | HIGH |
| Phòng khách | 120 | 205.150 | MEDIUM |
| Khách sạn | 120 | 219.803 | HIGH |
| Văn phòng viền ngoài | 170 | 219.803 | HIGH |
| Văn phòng bên trong | 100 | 205.150 | MEDIUM |
| Văn phòng cá nhân | 180 | 219.803 | MEDIUM |
| Cửa hàng | 165 | 234.457 | MEDIUM |
| Ngân hàng | 175 | 249.110 | MEDIUM |
| Nhà hàng | 330 | 278.417 | HIGH |
| Cafe | 350 | 278.417 | HIGH |
| Fastfood | 270 | 293.071 | MEDIUM |
| Hội trường | 280 | 278.417 | HIGH |
| Thư viện | 150 | 249.110 | MEDIUM |

Restaurant and cafe intentionally decrease. V2 is not an uplift program.

## 5. Retained V1 Factors

The following LOW-confidence categories retain exact v1 values in the v2 profile: supermarket, showroom, meeting room, classroom, theatre, clinic, medical office, light factory, heavy factory, server/computer room, laboratory, beauty shop, lobby/corridor, and basement. Their governance status is `V1_RETAINED_REVIEW_PENDING`.

## 6. V2 Area Contract

Active rule: `consumer-estimate-v2`. W/m² is the single canonical coefficient unit. Values preserve three-decimal precision; 175.843 is not rounded to 176, preventing the proven false 18k-to-24k boundary crossing at 30 m².

Method A height semantics remain unchanged: only height above 3 m applies the existing rounded `height / 3` multiplier.

## 7. V2 Volume Contract

Active rule: `volume-consumer-estimate-v2`. The resolver derives `W/m³ = selected area W/m² / 3m` with authority `PROJECT_DERIVED_FROM_AREA_V2`. It is not independently calibrated and does not apply Method A's height multiplier. All 27 categories have exact Method A/Method B raw-BTU equivalence at 3 m under the runtime rounding contract.

## 8. Adjustment Contract

Unchanged:

- direct sun: sequential `×1.10`;
- heat equipment: sequential `×1.10`;
- people above 10: `+400 BTU/person`.

Governance state: `ADJUSTMENT_SCOPE_REVIEW_PENDING`. Server rooms, F&B, meeting/classroom/hall and equipment-heavy spaces retain an explicit possible double-count limitation; no hidden compensating formula was introduced.

## 9. Precision

V2 stores one W/m² value per category and derives W/m³. There are no independently editable dual units. Residential 15/20/30/40 m² produces raw 9,000/12,000/17,999/23,999 BTU/h and tiers 9k/12k/18k/24k, avoiding precision-driven upward jumps.

## 10. V1/V2 Comparison

`artifacts/calculator_v2_activation_comparison.csv` contains 52 comparisons across all 13 activated categories and 15/20/30/40 m² at 3 m. It records raw BTU, market tier, tier delta and HP-equivalent delta.

## 11. Tier Changes

Seventeen representative rows change by at least two configured tier positions. They were manually reviewed and marked `MANUALLY_REVIEWED_ACCEPTED`: each is explained by the approved category coefficient plus the non-uniform market-tier grid. The largest observed representative changes are three tier positions for hotel, retail, bank and library at 40 m². Restaurant/cafe at 40 m² decrease by two positions. Calculation remains fail-closed and catalog availability does not alter raw demand or tier.

## 12. Catalog Gap Behavior

The customer result continues to show three separate facts: estimated raw need, calculated market tier, and nearest eligible RAC Product. Matching remains `capacity >= target`, verified RAC only, with VRF/GMV/OTHER/UNKNOWN/unverified excluded. Missing 9k/12k/28k/60k products cannot lower or rewrite the formula result.

## 13. Admin Governance

The BTU history page now has a read-only “Quy tắc đang áp dụng” modal. It shows active area/volume versions plus every category's coefficient, confidence, source and `V2 calibrated`/`V1 retained` status. No freeform editor was introduced; existing page permission remains the authorization boundary.

## 14. Tests

- dedicated v2 activation suite: 38 tests, 187 assertions, PASS;
- full Laravel suite: 427 tests, 1,580 assertions, 0 failures/errors, 1 existing skip;
- 30 exact v2 golden scenarios cover all requested primary classes, retained specialists, five heights and all adjustment combinations;
- all 27 categories pass 3 m equivalence and no-double-height assertions;
- v1 exact replay, LOW-confidence retention, monotonic properties, invalid rule/method, catalog gaps, fail-closed RAC matching and read-only governance are covered;
- Composer validate/audit, npm high audit, Vite build, config/route/view cache, changed-PHP lint and `git diff --check`: PASS;
- HTTP GET smoke: 200 with both method selectors and estimate disclaimer; area/volume POST and invalid-method behavior are covered through the real Laravel HTTP test stack;
- runtime measurement: rule resolution/calculation 0 DB queries; bounded Product matching 3 queries;
- browser automation: unavailable, so no browser PASS is claimed.

## 15. Data Safety

No migration is required: migration count remains 92. Existing `calculation_method` and `rule_version` columns store active versions for contact-origin calculations, while anonymous calculations remain non-persistent.

Read-only verification after activation:

- Products/catalog: `81 / 212 / 36,453 / 656,507`;
- canonical BTU hash: `3e981c60fcadd3461746fd8f3b94855dc5205bad6c446c55c17066d40c47e3ba`;
- Product/catalog technical writes: 0;
- AI provider calls during activation: 0 (request-log count stayed 236; latest remained `2026-08-23 10:14:31`);
- AI worker desired state: `DISABLED`;
- unrelated item #169 remained `FACT_CHECKING`, unchanged at `2026-08-23 10:14:32`.

## 16. Final Verdict

**CALCULATOR V2 ACTIVATION = PASS.** Runtime is configured for:

- area: `consumer-estimate-v2`;
- volume: `volume-consumer-estimate-v2`;
- v1: preserved for history/replay.

No commit, tag, version bump or push was performed.
