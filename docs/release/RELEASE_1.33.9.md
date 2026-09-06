# Release 1.33.9 — verified Product data governance

Release date: 2026-09-06
Release model: code and additive schema only; Product data rollout is separate.

## Summary

Version 1.33.9 separates Product physical taxonomy from its verification state and separates the commercial `marketing_capacity_btu` value from its verification state. Public BTU filtering is now fail-closed: only active Products with verified taxonomy, an eligible physical-DX category, verified marketing capacity, and a positive canonical value may participate.

**THIS RELEASE DOES NOT POPULATE MARKETING CAPACITY DATA.**

## Public filter contract

- The SQL value remains `products.marketing_capacity_btu`.
- `taxonomy_status` must be `VERIFIED`.
- `marketing_capacity_status` must be `VERIFIED`.
- The stable physical category slug must be eligible.
- VRF/GMV, unresolved taxonomy, conflicting taxonomy, and unverified capacity are excluded.
- Technical BTU, kW, HP, legacy `btu`, Product title, and model suffix are never commercial-tier fallbacks.

Immediately after code deployment, legacy null verification states resolve as `UNRESOLVED` and `UNVERIFIED`. Zero certified BTU results before an approved data rollout are therefore expected safety behavior, not a query regression.

## Governance and approval

The new Product Data Governance Admin page exposes taxonomy evidence, exact-digest approval, per-Product rejection/review, grouped marketing-capacity review, explicit tier approval, and append-only audit history. High-risk actions require permissions, confirmation, an operator, and a reason.

Taxonomy and capacity changes use separate commands and batches. Both default to dry-run. Apply paths require explicit approval, reviewed ledger identity, stale-value checks, source checks, and transactional row locks.

## Migration safety

The release adds nullable taxonomy state/evidence fields, nullable marketing-capacity state/evidence fields, governance event infrastructure, and a nullable catalog source hash/index. Migrations contain no Product backfill, no category rewrite, and no marketing-capacity rewrite.

## Current Local evidence

- Products: 372.
- Simulated taxonomy: 368 verified, 2 unresolved, 2 conflict.
- Existing marketing-capacity values: 16; verified: 0; all 16 quarantined.
- Active physical-DX Products requiring an explicit commercial-tier decision: 143 across 69 review groups.
- Approved marketing mappings: 0.
- Filter leakage: 0 unverified capacity, 0 unresolved taxonomy, 0 VRF/GMV.
- Filter safety: PASS; filter data coverage: EMPTY.

Outstanding identities remain intentionally unresolved:

- Product 1238 (`GCC36S6IA/GMC36S6IA`): conflict.
- Product 1271 (`GULD100T1/A-S/GULD100W1/NhA-S`): source required.
- Product 1366 (`FTHF71AVMV/RHF71AVMV`): conflict.
- Product 1597 (`LOCAL-AI-RETRY-PROBE-20260830`): remediation required.

## Certification

- Focused: 136 tests, 1,778 assertions, PASS.
- Full PHPUnit: 616 total, 615 passed, 1 skipped, 3,767 assertions, zero failures/errors.
- Browser: 1 passed; no HTTP 500, Livewire, console, page, or unexpected network errors.
- Composer validate/audit: PASS.
- npm audit high and Vite production build: PASS.
- PHP lint, secret scan, and `git diff --check`: PASS.

## Deployment and rollout boundary

Deploy the exact signed release, run Composer, migrations, cache lifecycle, and restart all worker families while preserving the predeploy AI desired state. During deployment, do not approve or apply taxonomy, do not approve marketing mappings, and do not run capacity backfill with `--apply`.

After deployment, Production must generate its own read-only taxonomy and marketing ledgers. A Local digest must never be reused as a Production approval digest. Product data rollout requires a separate operator-approved phase.

Detailed evidence: `docs/reports/final/PRODUCT_DATA_VERIFICATION_AND_APPROVAL_CENTER_1.33.9.md`.
