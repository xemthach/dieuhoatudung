# Marketing Capacity Product List Ingestion

This workflow imports evidence, not Product values. Public Product filtering remains SQL-backed by `products.marketing_capacity_btu`.

## Accepted source

Use an official structured Product List in XLSX, XLS, CSV, or JSON format. Each row must identify exactly one existing Product and provide an explicit commercial capacity tier.

Required columns:

- `brand_slug`, or pass one exact default `--brand` slug.
- At least one of `sku` or `model_code`.
- `marketing_capacity_btu` as a positive integer.

Optional evidence columns are `source_page` and `source_text`. For XLSX/XLS, the sheet name and physical row number are captured automatically. The complete source file is protected by SHA-256.

The template is [marketing_capacity_product_list_template.csv](templates/marketing_capacity_product_list_template.csv).

## Safety contract

- Matching is exact after canonical SKU/model normalization and is scoped by exact Brand slug.
- Zero matches are `NO_EVIDENCE`; multiple matches are `AMBIGUOUS`.
- Duplicate assignments or disagreement with an existing canonical value are `CONFLICT`.
- No value is inferred from `technical_capacity_btu`, `capacity_kw`, legacy `btu`, Product names, or ranges.
- The default command is read-only. Evidence persistence requires `--apply`, `--approved`, and a stable `--batch`.
- Evidence persistence does not update Products. Product updates remain a separate reviewed backfill.

## Controlled lifecycle

1. Run `catalog:ingest-marketing-capacity-product-list` without `--apply` and review the generated JSON ledger.
2. Resolve every `AMBIGUOUS`, `NO_EVIDENCE`, and `CONFLICT` row against the original Product List.
3. Persist only an all-clear ledger using explicit approval and batch identity.
4. Run `catalog:audit-marketing-capacity --json` and review `PROPOSE_UPDATE` rows.
5. Run `catalog:backfill-marketing-capacity` in dry-run mode with a stable batch.
6. Apply only the exact reviewed batch using `--apply --approved --batch=<reviewed-batch>`.

The apply command aborts if current proposals differ from the reviewed dry-run ledger. Only `marketing_capacity_btu` may change.
