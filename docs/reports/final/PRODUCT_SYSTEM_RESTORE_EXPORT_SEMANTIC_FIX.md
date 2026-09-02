# Product System Restore Export Semantic Fix

## Scope

This change fixes only the semantic detection of a complete Product XLSX
export. It does not change Product import provenance rules, category technical
schemas, BTU semantics, or Production data.

## Root cause

The Data Transfer export UI represents **all Product fields** by submitting all
registered Product field groups. `DataExportService` previously recognized a
Product System Restore only when `fieldGroups` was an empty array. Consequently
the full UI request was written as a presentation workbook containing only the
`Data` sheet, despite being an unfiltered `scope=all` export.

Local `DataExportJob #22` is the reproduction evidence:

- module: `product`; type: `xlsx`; total rows: `378`;
- scope semantics: all products; no selected IDs; no filters;
- submitted groups: `basic`, `pricing`, `seo`, `specs`, `media`, `merchant`;
- pre-fix workbook: no `_SYSTEM_EXPORT` or `_SYSTEM_PAYLOAD` sheet.

## Canonical semantic contract

`DataExportService::isProductSystemRestoreExport()` is the sole detector. A
request is a `PRODUCT_SYSTEM_RESTORE v1` only when all are true:

1. module is `product`, type is `xlsx`, and scope is `all`;
2. there are no selected IDs and no effective filters;
3. submitted groups are empty **or** their set exactly equals the registered
   Product group-key set, independent of ordering.

The current registered group keys are:

`basic`, `pricing`, `specs`, `seo`, `media`, `merchant`.

Partial groups, unknown/extra groups, selected/current-page/filter scopes,
effective filters (including an empty-array constraint under the current query
contract), and CSV/XML/JSON exports remain ordinary presentation exports.

When the detector is true, export fields are forcibly taken from
`ProductSystemRestoreContract::fields()` and the explicit boolean is passed to
the XLSX writer. The writer no longer re-infers user intent from field-array
equality. The existing manifest, checksum, hidden metadata/payload sheets, and
import verification remain unchanged.

## Caller audit

| Caller | Scope/selection behavior | Result |
| --- | --- | --- |
| `DataTransferPage::getExportAction()` | unfiltered all scope; UI can submit all groups | System Restore when full-group set is selected |
| `HasDataTransferActions` header export | selected/current-page/filter snapshot | Never System Restore |
| `ProductsTable` selected export | selected IDs | Never System Restore |
| `DataExportController` | delegates to service contract | Determined centrally by service |

## Regression evidence

- Focused PHP: `ProductSystemRestoreRoundTripTest` plus Product header actions:
  **22 passed, 134 assertions**.
- The test suite covers empty groups; canonical and shuffled full-group sets;
  partial and unknown group sets; selected/current-page/filter scopes;
  effective filters; CSV/XML/JSON; a real `DataTransferPage` Livewire action;
  manifest validation; payload chunking; empty-target round trip; catalog
  provenance preservation; and schema false-classification regression.
- Browser: `product-system-restore.spec.ts`: **1 passed**. Its fixture uses the
  same all-groups payload as the UI and the current Local Product population
  (378 rows); the Import Preview showed `SYSTEM RESTORE`, zero validation
  errors, and no relevant console/page/network/HTTP 500 errors.
- Full PHPUnit: **566 tests; 564 passed; 1 skipped; 3,063 assertions; 1
  failure**. The sole failure is the established, excluded SkyAir workbook
  fixture (`DaikinSkyAirImportReadinessTest`, missing fixture Brand ID 2 and
  Category ID 7), outside this Product System Restore path. No new failure was
  introduced by this change.

## Current Local result

The current Local database has 378 active Product records. The all-group XLSX
path uses the System Restore field contract and is accepted by the import
preview as an update preview (`TOTAL=378`, `VALID=378`, `ERROR=0`,
`CREATE=0`, `UPDATE=378`). The inspected actual workbook sheets are `Data`,
`_SYSTEM_PAYLOAD`, and `_SYSTEM_EXPORT`; payload is required by existing long
Product JSON content. The isolated empty-target round trip is covered by the
feature test and restores Product IDs and persisted fields without unexplained
differences.

## Safety

- Catalog import with technical fields but no catalog provenance still fails.
- No Product row, workbook, or Production system was mutated for this fix.
- Existing untracked SkyAir/Wall-mounted workbooks and browser images are
  outside this change and remain unmodified/untracked by this work.

## Release readiness

The export semantic fix is locally certified by focused and browser tests.
Publication and deployment were intentionally not performed in this task.
