# Release v1.33.8 — Product Transfer, Import Governance and BTU pipeline

Release date: 2026-09-05

## Summary

This patch introduces the signed `PRODUCT_TRANSFER v1` contract as a distinct
workflow from Product System Restore and strict external Catalog Import. It also
adds centralized Import Governance, deterministic import result states and
source-safe marketing-capacity transfer.

## Product Transfer v1

- Supports full, filtered, selected and current-set Product exports.
- Uses hidden `_PRODUCT_TRANSFER` metadata and `_PRODUCT_TRANSFER_PAYLOAD`.
- Verifies canonical-column and payload checksums before preview or import.
- Resolves Brand and ProductCategory only by exact stable slug.
- Matches target Products by SKU first and slug second; source Product IDs are
  diagnostic and are not preserved.
- Keeps `marketing_capacity_btu`, `technical_capacity_btu` and `capacity_kw`
  semantically separate.
- Preserves CatalogSource/CatalogModel lineage only when exactly provable.
- Fails closed when lineage is not portable unless the authorized governed
  detach policy is explicitly enabled. Detached technical snapshots carry
  `PRODUCT_TRANSFER` provenance, never fabricated catalog provenance.

## Import Governance

- One DB-backed runtime service owns 19 policy definitions.
- 10 business policies are Admin-managed; 9 integrity policies are displayed
  as system-locked and cannot be disabled.
- High-risk changes require confirmation and a reason and create an append-only
  audit row with operator, old/new mode and timestamp.
- Explicit permissions govern policy view/change, Product Transfer, Product,
  Catalog and System Restore imports, plus bulk import/update/retry operations.
- Import jobs snapshot effective policies, detected format/version, matching and
  mapping policy, lineage mode, operator, package hash and time.

## Preview and results

Preview exposes integrity, format, mode, row/create/update/blocked/warning
counts, Brand/Category mapping, lineage decision and effective governance.
Terminal states are deterministic: `completed`, `completed_with_errors`,
`failed`, `blocked` and `empty`. Identical row errors are grouped without losing
machine code, row number, user message or technical details.

The historical Job #40 behavior was reproduced with an 81-row Data-only file:
strict Catalog provenance correctly blocked all technical rows and the final
state is now `failed`, not green/completed. The same 81 logical rows passed as a
signed Product Transfer against differing numeric Brand/Category IDs.

## BTU and Product regressions

- Product Transfer preserves authoritative marketing capacity.
- Public filtering remains exclusively SQL-backed by
  `products.marketing_capacity_btu`.
- The inclusive `9000-12000` query now has the truthful label
  `9.000 - 12.000 BTU`.
- SkyAir identity/electrical/component behavior, wall-mounted RAC behavior and
  technical edit/save/provenance behavior remain unchanged and certified.

## Certification

- Focused: 56 passed, 1,477 assertions, 0 failed.
- Browser: 5 passed, 0 failed.
- Full PHPUnit: 587 total; 586 passed; 1 skipped; 0 failed; 3,667 assertions.
- Composer validation/audit, npm high audit, Vite build, PHP lint and Git diff
  check: PASS.
- Read-only Live audit package SHA-256:
  `e545e67152a138f735dd8dd100af57bd7a863eb45e005fad5c9d637aa71d79dd`.

## Known data limitation

The current Local database has 372 non-deleted Products but only 16 proven
marketing-capacity values. The safe remediation audit produced zero additional
source-backed proposals. This release does not fabricate missing values by
rounding technical BTU, converting kW, parsing titles or guessing model classes.
Production currently has a separate historical data gap; code deployment alone
does not make those rows filterable by marketing capacity.

## Deployment and rollback

Deploy only annotated tag `v1.33.8` through
`tools/release/DEPLOY_V1_33_8.sh`. The script requires a clean worktree, verifies
the exact tag/version, creates a non-zero checksummed database backup, preserves
AI desired state, installs production dependencies, migrates, rebuilds Laravel
caches, restarts both worker families and records runtime evidence. Frontend
assets are built locally and committed; do not run npm on Production.

Rollback target is immutable `v1.33.7` at
`43c620e878542ee63f34d2075b6e350f2b251c67`. Do not roll back the database
blindly; use the verified backup only after compatibility review. A rollback
must rebuild caches, restart generic and managed workers and prove the rollback
SHA plus restored AI desired state.
