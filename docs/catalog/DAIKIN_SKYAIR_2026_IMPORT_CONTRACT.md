# Daikin SkyAir 2026 — Controlled Import Contract

## Scope

This package is an evidence-backed preparation for Daikin SkyAir 2026 commercial systems. It is not a production import command and does not activate any product for public display.

Source: `DAIKIN - CATALOGUE MÁY LẠNH THƯƠNG MẠI 2026 - SKY AIR.pdf`
SHA-256: `F02E3C7B0F993D636630AB4C640D3C7662AA2BF0CC9F5F1957CF460DF7C659DE`
Physical pages reviewed: 88/88.

## Row grain

One workbook row represents one explicitly published indoor/outdoor system combination. Pairing is read from the catalog lineup and technical tables. No Cartesian pairing is generated.

The combination identity is retained as `indoor_model/outdoor_model`; the importer stores it as `model_code` and uses a deterministic SKU composed from the two models.

## Category mapping

SkyAir is a product family, not a single category:

| Equipment type | Existing category | Schema |
|---|---|---|
| Cassette | Điều hòa âm trần Cassette | `skyair-cassette-v1` |
| Medium/low static ducted | Điều hòa giấu trần nối ống gió | `skyair-ducted-v1` |
| Floor standing | Điều hòa tủ đứng | `skyair-floor_standing-v1` |
| Ceiling suspended | Điều hòa đặt sàn/áp trần | `skyair-ceiling_suspended-v1` |

Existing truthful categories are extended in place. A `SkyAir` catch-all category is deliberately not created.

## Technical data policy

Technical values are accepted only when they have field-level source provenance. `NULL`/`NOT_STATED` is preserved where the catalog does not state a value. Refrigerant and phase are taken from the applicable catalog variant/table; they are not inferred from an unrelated model.

Features, accessories and controllers remain separate compatibility matrices. They are not flattened into unsupported Product boolean fields.

The four category schemas are intentionally category-specific. Cassette has panel fields; ducted has external-static-pressure fields. Shared fields retain their explicit units (`kW`, `BTU`, `Pa`, `m³/min`, `dB(A)`, `mm`, `kg`, `m`, `°C`).

## Safety gates

- Workbook is for dry-run and isolated testing only.
- Production Product import is not part of this package.
- Existing production-like counts were unchanged during schema seeding.
- No AI provider or worker was used.
- Any future import must pass the real `ProductImportHandler`, duplicate checks, category-schema validation, isolated import, round-trip export, and operator approval.
