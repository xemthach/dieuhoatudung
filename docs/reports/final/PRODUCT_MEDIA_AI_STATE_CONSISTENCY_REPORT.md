# Product Media / AI State Consistency Report

Date: 2026-08-23

Scope: production-UX remediation only
Verdict: **PASS**

## 1. Media root cause

The affected representative Product is `1248` (`GU100T/A1-K/GU100W/A1-K`). Its database references contain one main image and four gallery images. All five objects exist on the configured R2 disk, resolve under `https://cloud-data.dieuhoatudung.com/`, and representative main/gallery HTTP HEAD requests returned `200`.

The frontend defect was a data-shape mismatch in `resources/views/products/show.blade.php`: the page supplied `gallery_image_urls` (an array of strings), while Alpine read each entry as an object through `currentImage.url` and `img.url`. The resulting image source was undefined even though storage and CDN data were valid.

A second media consistency problem existed in the old Product accessor: every missing stored path was resolved immediately to the fallback. A missing main path could therefore add a placeholder thumbnail beside otherwise valid gallery media.

## 2. Admin/frontend source comparison

- Admin Product uploads use `MediaDiskService::getUploadDisk()` for main image, gallery, documents, rich-text attachments and OG media.
- At audit time R2 was enabled, the active upload disk was `r2`, and all 15 tracked `media_files` rows were marked synchronized.
- Frontend Product detail/cards previously assembled media independently in Product accessors using `media_url()`.
- Product table now explicitly resolves its image disk through `MediaDiskService`, while frontend Product composition delegates to `ProductMediaResolver`, which itself delegates URL ownership to `MediaDiskService`/`media_url()`.

No media row, Product field or R2 object was modified.

## 3. Canonical CDN resolver contract

`ProductMediaResolver` now owns Product-specific composition:

1. evaluate main image, then gallery paths;
2. resolve each path through the existing canonical media service;
3. omit unresolved/broken paths;
4. de-duplicate by resolved URL;
5. emit exactly one safe fallback only when no real Product media resolves.

Cards, comparison and related Product cards continue through `main_image_url`, so they share the same Product resolver.

## 4. Media fix

- Product detail now passes `gallery_images` objects to Alpine using Blade `@js`.
- Main image is no longer inserted twice when also present in `gallery_json`.
- A missing main path no longer creates a fake thumbnail when a real gallery image exists.
- Empty/broken Product media still degrades to the configured/site placeholder.

## 5. AI status source matrix

Representative Product `1248` before presentation remediation:

| Source | Value |
|---|---|
| `products.ai_status` | `needs_review` |
| Latest Product AI item | none |
| Latest AI draft | none |
| Approval/apply record | none |
| Old Product-list result | `Chờ duyệt` |
| Review action result | no draft available |

Read-only full-dataset reconciliation found:

- stored Product status: 80 `needs_review`, 1 `queued`;
- canonical current state: 76 `NOT_GENERATED`, 4 `APPLIED`, 1 `BLOCKED`;
- 76 `STALE_DENORMALIZED_STATUS` records;
- 0 current reviewable drafts;
- 0 approved-unapplied drafts.

These historical Product flags were reported but not bulk-mutated.

## 6. Review/apply query contract

`AiProductContentStateResolver` is now the shared Product-state boundary.

- Reviewable means the latest current item has canonical review status and a real Product draft whose status is review-required, which is neither approved, applied nor rejected.
- Apply is available only for an `APPROVED_FOR_APPLY` draft with no `applied_at`.
- A draft with `applied_at` resolves to `APPLIED`, regardless of stale item/Product status.
- `REVIEW_REQUIRED` without a persisted actionable draft resolves to `BLOCKED` with `REVIEWABLE_DRAFT_MISSING` rather than advertising an impossible review action.
- A Product with no item/draft resolves to `NOT_GENERATED`; stale `products.ai_status` is diagnostic evidence only.

## 7. Canonical AI state usage

The resolver is used by:

- Product table badge and tooltip;
- Product edit Review/Apply action visibility and lookup;
- lightweight Product live-status polling/API;
- AI status filter semantics.

Dashboard Product counts now aggregate only the latest item per Product and exclude applied/approved/rejected drafts from review-required totals. Historical jobs remain available in operational history but no longer determine current Product actionability.

## 8. Product edit UX

The live AI panel now displays the latest operation, draft availability, review state and apply state. Review and Apply actions are hidden unless the same canonical resolver can return the corresponding actionable draft. This removes the former `Chờ duyệt` / `Không có AI draft để duyệt` contradiction.

## 9. Worker/runtime verification

- Web and managed worker: version `1.28.0`, build `fe4eea2...`.
- Database: `mysql`, `127.0.0.1:3306`, `dieuhoa-tudung` for both.
- Queue: database connection, `ai_governed` for both.
- Desired state: `DISABLED`; actual process: `ONLINE / PAUSED`; accepting new jobs: false.
- Pending/processing/stuck: `0 / 0 / 0`.
- Release-validation commands initiated `0` provider calls. During the validation window, a separate authenticated Super Admin action enabled the worker and produced provider request-log row `236`; the action completed before the release gate and was not used as test evidence. The canonical worker desired state was then restored to `DISABLED`.

## 10. Tests and evidence

- Focused media/AI tests: **19 tests, 91 assertions, PASS**.
- Full suite: **363 tests, 1,260 assertions, 0 failures/errors, 1 existing skip**.
- Blade cache: PASS.
- `git diff --check`: PASS.
- Product/catalog counts: `81 / 212 / 36,453 / 656,507`.
- Migrations: `90`.
- Canonical JSON-row BTU hash: `3e981c60fcadd3461746fd8f3b94855dc5205bad6c446c55c17066d40c47e3ba`.

HTTP/server-rendered proof confirmed that Product 1248 now emits five image objects (not a flat string array), while representative CDN assets return `200`. No Playwright/Dusk or authenticated CDP browser transport was available, so no browser PASS is claimed.

## 11. Data safety

No Product technical/catalog data was written. No historical AI draft/job was deleted or reset. The worker finished in the operator-controlled disabled state. No release-validation command called the AI provider; the concurrent operator action described above is preserved as separate runtime evidence.
