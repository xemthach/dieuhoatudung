# BTU Calculator Calibration Reference Matrix

## Boundary

This matrix compares consumer sizing rules. It does not convert the calculator into an engineering heat-load tool. The original calibration verdict remains historical evidence; controlled hybrid v2 activation was approved and implemented on 2026-08-24, while v1 remains frozen for replay.

Analysis uses `1 W = 3.412141633 BTU/h`. Runtime deliberately remains on its certified `3.412` conversion until a separately approved rule version changes it.

## Normalized universal references

| Reference | BTU/h/m² at 3 m | BTU/h/m³ | W/m² | W/m³ | Evidence/use |
|---|---:|---:|---:|---:|---|
| R1: 600 BTU/h/m² | 600 | 200 | 175.843 | 58.614 | Panasonic Vietnam consumer guidance |
| R2: 200 BTU/h/m³ | 600 | 200 | 175.843 | 58.614 | Panasonic Vietnam consumer guidance |
| R3: 1 HP / 40 m³ | 675 | 225 | 197.823 | 65.941 | Consumer rule using project convention 9,000 BTU/h per HP |

R1 and R2 are mathematically identical at 3 m. R3 is 12.5% more conservative than R2. HP here is a marketing group, not mechanical horsepower.

## Source hierarchy

### Level A — manufacturer/government guidance

- [Panasonic Vietnam large-space sizing guidance](https://www.panasonic.com/vn/air-solutions/learn-more/huong-dan-chon-cac-loai-dieu-hoa-cong-suat-lon.html): 600 BTU/h/m² baseline; office/hotel 700–800; hall/restaurant/cafe 900–1000. Vietnam applicability is useful, but this remains manufacturer consumer guidance rather than a full load standard.
- [Panasonic Vietnam area/volume guidance](https://www.panasonic.com/vn/air-solutions/learn-more/dieu-hoa-am-tran-18000btu.html): explicitly publishes 600 BTU/h/m² and 200 BTU/h/m³.
- [ENERGY STAR room AC sizing](https://www.energystar.gov/products/room_air_conditioners): capacity chart assumes an 8-foot ceiling; +10% for very sunny rooms, +600 BTU/person above two, +4,000 BTU for kitchens. US climate/building assumptions are not directly transferable to Vietnam commercial spaces.
- [US DOE home heating/cooling guide](https://www.energy.gov/sites/prod/files/guide_to_home_heating_cooling.pdf): warns that old area rules omit climate/envelope and recommends ACCA Manual J for proper sizing. This supports retaining the engineering boundary.

### Reference evidence — user-supplied room table

Values supplied by the operator include residential 700, living/dining 850, retail/office 800, library/bank 850, shopping center 1,000, meeting hall 1,500 and dance hall 2,000 BTU/h/m². Provenance is not established, so this table is not treated as an HVAC authority.

The table has an internal arithmetic conflict: `0.235 kW/m² × 3.412141633 = 801.853 BTU/h/m²`, not 850. Status: `SOURCE_INTERNAL_INCONSISTENCY`. Neither conflicting value is silently selected as authoritative.

## Semantic mapping policy

- `EXACT`: same operational room class (office→office, restaurant→restaurant).
- `CLOSE`: related class but source lacks a project distinction (private/interior office).
- `AMBIGUOUS`: mapping would merge materially different use (lobby with corridor).
- `NO_MATCH`: no defensible reference (server room, factory, laboratory).

The exact per-key mapping is in `calculator_factor_gap_matrix.csv`. Restaurant is not mapped to residential dining; theatre is not mapped to dance hall.

## Calibration architecture comparison

| Option | Strength | Risk | Verdict |
|---|---|---|---|
| Direct factor per room | Clear runtime and replay | Requires 27 defensible sources; sparse evidence | Useful for well-supported major classes only |
| Common baseline + multipliers | Compact | Hides occupancy/equipment assumptions and amplifies double-count risk | Not recommended globally |
| Hybrid | Major classes have sourced factors; uncertain classes retain reviewed values | More governance metadata | Recommended for a future v2 |

For Method B v2, derive `W/m³ = Method A v2 W/m² / 3` unless an independently scoped volume table is approved. Panasonic's 200 BTU/m³ validates the residential 600 BTU/m² equivalence, but does not independently calibrate every commercial room class.

## Precision rule

Do not round a proposed 600 BTU/m² coefficient to `176 W/m²`: at 30 m² and runtime conversion 3.412 this produces about 18,015 BTU/h and can falsely jump from 18k to 24k. Preserve sufficient decimal precision or make BTU/m² the canonical v2 factor unit.

## Artifacts

- `calculator_v1_factor_inventory.csv`: all 27 v1 factors and base-scope/double-count flags.
- `calculator_factor_gap_matrix.csv`: semantic reference mapping and deltas.
- `calculator_method_benchmark.csv`: 378 method/reference scenarios.
- `calculator_adjustment_compounding.csv`: multiplicative vs additive adjustment effect.
- `calculator_catalog_tier_gap.csv`: configured versus actual eligible RAC capacity.
- `calculator_v2_factor_proposal.csv`: category-specific proposal, not activation.
- `calculator_v1_v2_comparison.csv`: 35 representative area/volume comparisons.
- `calculator_source_authority.json` and `calculator_calibration_decision.json`: reproducible source and decision records.
