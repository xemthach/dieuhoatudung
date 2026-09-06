# Live deployment v1.33.9 — code-ready, data-rollout-blocked draft

This is not an executable deployment runbook. Candidate v1.33.9 has no release commit or tag. No Production access or mutation occurred.

## Current gates

- Local remains `1.33.8` at baseline `5ead4241b42915dd6172747cdffe213242c0a0ed`.
- Source identity: 368 MATCH, 2 MISSING, 0 AMBIGUOUS, 2 CONFLICT.
- Taxonomy ledger: 64 KEEP, 304 PROPOSE_UPDATE, 0 AMBIGUOUS, 2 NO_EVIDENCE, 2 CONFLICT.
- Partial taxonomy dry-run: 368 verifiable, 4 fail-closed, zero Product mutation.
- Public active denominator after taxonomy simulation: 143 physical-DX Products.
- Commercial tier: 0 verified, 69 review groups, 143 Products requiring an explicit Admin mapping.
- Existing 16 marketing values: UNVERIFIED and excluded from certified public filter results.
- Browser: PASS on isolated database.
- Focused: 136 tests, 1,778 assertions, PASS.
- Full PHPUnit: 616 tests, 615 passed, 1 skipped, 3,767 assertions, zero failures/errors.
- Composer/npm/build/PHP lint/secret scan/diff check: PASS.

## Release interpretation

`CODE_RELEASE_READY=YES`: the schema, verification states, Admin approval center, append-only digests, controlled apply tools, and public fail-closed filter are certified.

`DATA_ROLLOUT_READY=NO`: no taxonomy batch or commercial mapping has been approved, and Product 1238/1366 remain identity conflicts, Product 1271 needs a trusted source, and Product 1597 needs a separate probe-remediation decision.

The future v1.33.9 code release may therefore be prepared in a separately authorized release task, but deployment must not mutate Product data. Initial Live behavior will intentionally hide unverified BTU values until the operator completes approved data batches.

## Future controlled data order

1. Deploy the certified code/schema only.
2. Approve the exact taxonomy ledger digest.
3. Apply taxonomy through the controlled command and post-audit.
4. Regenerate the marketing review after taxonomy changes.
5. Approve exact source-backed mappings in Admin.
6. Run and review the capacity dry-run batch.
7. Apply only the approved batch.
8. Certify filters and facets.

Never infer commercial tiers from technical BTU, kW, HP, model suffix, title, or a nearest bucket. Never combine taxonomy and capacity mutation. Never apply Product data from a migration.

## Evidence

See `docs/reports/final/PRODUCT_DATA_VERIFICATION_AND_APPROVAL_CENTER_1.33.9.md` and the generated private ledgers under `storage/app/private/reports/`.
