# Equipment Type Recommendation Method

## Scope

This layer runs **after** the BTU calculator. It does not change calculated load, market capacity tier, V1/V2 factors, or RAC under-sizing controls. It gives a deterministic, brand-neutral advisory about the user's preferred equipment type and then lists eligible site models.

The result is a consumer estimate. It is not an engineering load design, zoning plan, electrical design, duct design, or installation approval.

## Four separate concepts

1. `calculated_btu`: estimated cooling load.
2. `recommended_btu`: rounded market capacity tier.
3. `requested_equipment_type`: user preference.
4. Actual Product availability after type, canonical marketing-capacity, active/stock and minimum-capacity gates.

Product availability never changes the formula result.

## Type taxonomy

| Identifier | Display label | Runtime Product class | Public selection |
|---|---|---|---|
| `unsure` | Chưa xác định / Cần tư vấn | none | yes, default |
| `wall_mounted` | Điều hòa treo tường | `RAC_SPLIT` | yes |
| `cassette` | Điều hòa âm trần cassette | `RAC_CASSETTE` | yes |
| `ducted` | Điều hòa giấu trần nối ống gió | `RAC_DUCTED` | yes |
| `ceiling_exposed` | Điều hòa áp trần | `RAC_FLOOR_CEILING` | yes |
| `floor_standing` | Điều hòa tủ đứng | `RAC_FLOOR_STANDING` | yes |

`VRF_*`, `OTHER` and `UNKNOWN` are never normal calculator recommendations. Multi-split is not offered because the current verified site Product taxonomy does not support it as a selectable recommendation class.

## Product classification

`ProductHvacClassResolver` evaluates both Product category and `product_type`. A generic/non-HVAC category no longer suppresses a valid explicit `product_type`. If both recognized sources conflict, classification fails closed.

`ProductEquipmentTypeResolver` maps only verified HVAC classes. Strong Product-name tokens are used solely to reject a clear label/taxonomy conflict; they never promote an `UNKNOWN` Product.

## Market reference envelopes

These are observed official product-line ranges, not universal limits.

| Type | Reference range | Confidence | Authority |
|---|---:|---|---|
| Wall-mounted | 8,700–24,000 BTU/h | MEDIUM | Panasonic Vietnam + LG Vietnam official product pages |
| Cassette | 11,600–48,500 BTU/h | HIGH | Panasonic Vietnam official cassette line |
| Ducted | 17,100–47,800 BTU/h | MEDIUM | Panasonic Vietnam official ducted line; site catalog independently reaches 55,000 |
| Ceiling exposed | 20,500–60,000 BTU/h | HIGH | Panasonic Vietnam official ceiling-exposed lines |
| Floor standing | 20,500–47,750 BTU/h | HIGH | Panasonic Vietnam official floor-standing line |

Official references are maintained in `config/hvac_equipment_types.php`. The system keeps these separate from the live site catalog envelope.

## Current site catalog envelope

Snapshot 2026-08-24, read-only, 81 Products:

| Type | Products with verified type + canonical marketing BTU | Range |
|---|---:|---:|
| Wall-mounted | 0 | none |
| Cassette | 7 | 18,000–48,000 BTU |
| Ducted | 2 | 48,000–55,000 BTU |
| Ceiling exposed | 0 | none |
| Floor standing | 0 | none |

Three Products are blocked by a clear Product label/taxonomy conflict. Seventeen remain fail-closed because classification evidence is absent. Twenty-eight verified VRF outdoor Products are excluded from this recommendation layer. No Product/catalog data was edited.

## Decision rules

- Capacity is always `product capacity >= market tier`; no under-sized model is returned.
- Exact type models are ranked by capacity delta, then explicit price preference only as a tie-breaker, then stable Product ID.
- A maximum oversize search window of 12,000 BTU limits model suggestions.
- `unsure` does not claim final suitability; it shows types/models currently capable of meeting the tier.
- Type within market range but without an eligible site Product returns `NO_MATCHING_PRODUCT`, not technical impossibility.
- Requested type above both the market and verified site envelope returns `NOT_RECOMMENDED_FOR_THIS_LOAD`.
- Load above every verified normal single-unit envelope returns `TECHNICAL_CONSULTATION_REQUIRED`.
- Cassette with unknown ceiling clearance requires review.
- Ducted always requires design review even when capacity matches because duct routing/static pressure are outside the calculator input contract.
- No rule calculates an exact number of indoor units or automatically recommends VRF.

## Canonical statuses

- `SUITABLE_FOR_CONSIDERATION`
- `POSSIBLE_BUT_REVIEW_REQUIRED`
- `NOT_RECOMMENDED_FOR_THIS_LOAD`
- `INSUFFICIENT_DATA`
- `NO_MATCHING_PRODUCT`
- `TECHNICAL_CONSULTATION_REQUIRED`

## Consultation handoff

The server-side session bridge to the quote form carries method, area, height, space type, raw BTU, market tier, requested type and recommendation status. No PII is placed in the URL. The official contact settings remain the CTA source.

## AI policy

AI is not required and is disabled for core recommendation. A future optional AI layer may explain an already-final structured result but may not change capacity, type suitability, model eligibility, quantity or installation feasibility.
