# Product Transfer Governance and BTU Data Completion

Date: 2026-09-05

Release baseline: v1.33.7 / `43c620e878542ee63f34d2075b6e350f2b251c67`

Local post-release SHA: `697bb4f99bb2f1ff2fe5e5cbfbb1e136f0b8aa07`

Production mutation: NONE

## Contract frozen

`PRODUCT_TRANSFER` format version 1 remains separate from System Restore and
strict Catalog Import. Signed XLSX files use `_PRODUCT_TRANSFER` and
`_PRODUCT_TRANSFER_PAYLOAD`, canonical columns and payload checksums. Brand and
Category resolve by exact slug; Product matching is SKU then slug; Product ID is
not preserved. Marketing BTU, technical BTU and kW remain independent fields.

Catalog source/model lineage is preserved only when exactly provable. Otherwise
the transfer is blocked unless the governed detach policy is enabled. Governed
detach clears target catalog lineage while preserving transferred technical
values with `PRODUCT_TRANSFER` provenance.

## Governance backend and Admin UI

`ImportGovernanceService` is the single DB-backed runtime source over the
existing SiteSetting/SettingService infrastructure. It defines 19 policies:
10 Admin-managed business policies and 9 non-disableable system-integrity
policies. The Filament page **Import / Export & Data Governance** displays the
policy key, module, effective/default mode, risk, lock state and last change.

High-risk changes require confirmation and a reason. Append-only
`import_governance_audits` rows retain operator, old/new value, reason and time.
The permission matrix includes governance view/change, Product Transfer,
catalog/manual/system imports and bulk import/update/retry permissions. Runtime
actions perform server-side permission checks; integrity rules cannot be
bypassed.

Every import job snapshots detected contract/version, matching and mapping
policies, lineage mode, effective governance values, operator, package hash and
timestamp. Historical jobs render their stored snapshot.

## Preview and result behavior

Preview reports format/mode, matching key, integrity, create/update/blocked/
warning counts, Brand/Category mapping and catalog-lineage outcome. Import
terminal states are deterministic:

- all success: `completed`;
- mixed success/error: `completed_with_errors`;
- all rows failed: `failed`;
- precondition failure: `blocked`;
- zero rows: `empty`.

Repeated errors are grouped by machine code with count and example rows while
retaining row-level user and technical messages.

## Historical Job #40 reproduction

A controlled 81-row Data-only workbook with technical values, no Product
Transfer/System Restore metadata and no provenance was auto-detected as the
normal/catalog path. Preview exposed the provenance block before writes. Under
strict Catalog mode all 81 rows failed `TECHNICAL_PROVENANCE_REQUIRED`, zero
Products were written, and the final state was `failed`, not green/completed.

The same logical 81 rows exported as signed `PRODUCT_TRANSFER v1` imported into
an isolated target whose Brand/Category numeric IDs differed. Exact slug mapping
created 81 Products with zero row errors. Detach OFF blocked unprovable catalog
lineage; governed detach ON cleared target lineage and preserved technical data
with `PRODUCT_TRANSFER` provenance.

## Marketing capacity and BTU

Future authoritative Product Transfer and Product-list import paths preserve or
write `marketing_capacity_btu`. Public filters remain SQL-backed by
`products.marketing_capacity_btu`; technical BTU and kW are never substituted.
The `9000-12000` query is inclusive and the UI label is
`9.000 - 12.000 BTU`.

Current Local data is not fully populated: 372 non-deleted Products, 378 with
trashed, 16 marketing BTU values, 306 technical BTU values and 317 kW values.
The controlled remediation audit found 0 safe source-backed proposals. Existing
missing marketing values therefore remain untouched; arithmetic rounding,
technical-value coercion and title-only inference are prohibited. Pipeline and
transfer behavior are fixed, but historical population completion remains
blocked pending authoritative commercial-capacity evidence.

Exact filter fixtures passed for 9000-12000, 18000, 24000, 48000, multi-BTU and
Brand/Category/Inverter combinations.

## SkyAir, wall-mounted and technical edit regression

Focused coverage passed for SkyAir indoor/outdoor identity, 1P/3P electrical
separation, remote/panel exact-versus-compatibility semantics, technical BTU/kW,
manual override provenance and technical form save/reload. Wall-mounted RAC
remains a standard single Product without commercial component requirements.
The browser technical-edit scenario verified save/reopen persistence and no
Livewire, page, console or network error.

## Certification

- Focused: 47 passed, 1,411 assertions, 0 failed.
- Browser: 5 passed, 0 failed.
- Full PHPUnit: 587 total; 586 passed; 1 skipped; 0 failed; 3,667 assertions.
- Composer validate/audit: PASS.
- npm audit high/build: PASS (0 vulnerabilities; Vite build complete).
- Changed PHP lint and `git diff --check`: PASS.
- Live audit Local dry-run: PASS; 77 SELECT, 0 write queries.
- Local mutation proof: Products 372 -> 372, ImportJobs 14 -> 14, settings hash
  unchanged (`8c38b46d54ebc12183601dfe25a649908f3c5201e283d93f5b36dc587917fdb8`).

## Uploadable read-only audit package

Package: `artifacts/LIVE_PRODUCT_IMPORT_BTU_AUDIT_PACKAGE.zip`

SHA-256: `e545e67152a138f735dd8dd100af57bd7a863eb45e005fad5c9d637aa71d79dd`

The package contains the one-command Linux runner, Windows runner, comparison
tool, templates, a randomly named standalone token-protected web runner and its
operator info file. ZIP entries were listed after creation. The local canonical
report is `LOCAL_PRODUCT_IMPORT_BTU_AUDIT_20260905_080452.json`; Local/Live
comparison classifies expected code, data, policy and configuration differences.

## Changeset isolation

Task scope includes Product Transfer/governance services, Filament pages/views,
permissions, migration, tests, browser fixtures, reports and audit tooling.
SkyAir and wall-mounted source workbooks, the old Product export, unrelated
browser screenshots and unrelated release reports remain excluded/untracked.
Vite assets are generated build output and require explicit release staging
review. No file was staged, committed, tagged or pushed.

## Verdict

Implementation and all requested release gates pass. The marketing-capacity
pipeline is release-ready because authoritative values are preserved and written
without semantic coercion, while unprovable historical values correctly remain
untouched. The 356 current Local Products without marketing capacity and the
zero safe-backfill proposals are an explicit data limitation for later
source-backed remediation, not permission to fabricate values and not a defect
in the certified transfer/filter pipeline.
