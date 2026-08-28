# Marketing / Content Runtime Full Audit

> Current status after browser closure: **MARKETING / CONTENT RUNTIME AUDIT = PASS**. The original PARTIAL chronology below is retained as the pre-browser verdict.

## 1. Executive Verdict

**MARKETING / CONTENT RUNTIME AUDIT = PARTIAL / STOP.**

All reproducible server, database, Livewire, renderer, authorization and build gates are green. Website Campaign, AI Post lineage and Promotion runtime defects were fixed. The remaining blocker is narrowly scoped: this checkout has no Playwright, Dusk, Cypress or authenticated Chrome/CDP transport, so mouse/cursor/selection/toolbar behavior in Filament RichEditor and the requested visual scenarios cannot be certified truthfully.

## 2. Environment / Baseline

- Application version: `1.29.0`; HEAD at audit start: `083ad12380fc138ac05ab4c86486d75191c30554`.
- Environment: `local`; PHP `8.3.16`; database `mysql/dieuhoa-tudung`; queue connection `database`; AI queue `ai_governed`.
- Migration rows: 93.
- Initial/final domain counts: Posts 10 including soft-deleted history; AI article jobs 5; Website Campaigns 0; campaign events 0; Promotions 1; Leads 2.
- Worker desired state: `DISABLED`; actual process: `OFFLINE`; processing/stuck AI jobs: 0/0. Scheduler heartbeat is stale and scheduler is not running locally.
- No provider call was made. Tests used SQLite `:memory:` and a local governed Promotion draft.

## 3. Code Inventory

The permanent inventory is in [runtime_feature_inventory.csv](artifacts/runtime_feature_inventory.csv). Campaign, Promotion, Post editor, Post AI, frontend layouts, tracking and permissions were traced to their actual classes, routes and tables.

## 4. Website Campaign Architecture

`SiteCampaignForm` persists to `site_campaigns`. `SiteCampaignResolver` applies active window, route placement, device and URL targeting before deterministic conflict reduction. Both public layout variants invoke the same `x-site-campaigns` production component. Events are accepted only for a currently active campaign by `SiteCampaignEventController`.

## 5. Campaign Types

All eight admin types have one production renderer: modal, slide-in, top bar, bottom bar, floating CTA, image popup, video popup and Product promotion popup. Image and video variants now have type-specific readiness checks. See [campaign_type_matrix.csv](artifacts/campaign_type_matrix.csv).

## 6. Campaign Status / Scheduling

Stored status alone was the observability defect. `SiteCampaignReadinessService` now derives `READY`, `SCHEDULED`, `INACTIVE`, `EXPIRED`, `MISCONFIGURED`, `NO_RENDERER` or `NO_MATCHING_PLACEMENT` from current runtime truth. End time is validated after/equal start time. Existing inclusive boundaries (`start_at <= now`, `end_at >= now`) remain unchanged.

The application timezone is UTC. Admin display and runtime evaluation use Laravel's same timezone contract; no second browser-time schedule source was found.

## 7. Campaign Placements

All eleven persisted options map to actual named routes. URL rules are applied before priority collision resolution, avoiding a high-priority non-match suppressing a valid lower-priority campaign. See [campaign_placement_matrix.csv](artifacts/campaign_placement_matrix.csv).

## 8. Campaign Cache

No campaign result cache exists. Create/edit/status/schedule changes are read on the next request; no manual cache invalidation is required.

## 9. Campaign Observability

The Filament table now separates stored status from runtime readiness, exposes a human reason tooltip, and shows the latest persisted event timestamp. Impression and click counts are loaded with aggregate subqueries, eliminating table-row event N+1 queries.

## 10. Campaign Preview

The authorized Edit page has `Xem trước campaign`. It passes the current record to the exact production Blade component. Preview mode:

- can render draft/inactive/scheduled records;
- does not resolve/activate the production record;
- emits no event endpoint, dataLayer event, local/session frequency write or campaign mutation;
- has no public preview route.

See [campaign_preview_contract.json](artifacts/campaign_preview_contract.json).

## 11. Post Editor Architecture

The editor is Filament 5 `RichEditor` backed by TipTap. The canonical database format is an HTML fragment in `posts.content`. No `disabled`, `readOnly`, permission or published-state rule was found on the content component. The AI status panel is a separate nested Livewire component polling every 10 seconds.

## 12. Editor Root Cause

The reported mouse/cursor failure was not reproducible through available server transport. Read-only forensics of the current AI-applied Post sample found no full document tags, scripts, style blocks, `contenteditable`, pointer-event styles or fixed/absolute overlays. A Livewire regression proves an AI-shaped HTML fragment mounts, changes, saves and reloads on the same Post.

Future Post AI apply and generic AI rich-content mapping now pass through `RichHtmlSanitizer`, removing executable elements, event handlers, style/class/id/contenteditable attributes and unsafe link schemes before editor/public use. This is a safety fix, not a fabricated browser root-cause claim. See [post_editor_runtime_forensics.json](artifacts/post_editor_runtime_forensics.json).

## 13. AI Post Workflow

Post-origin generation persists target type, target Post ID, operation, requested fields and current content hash. History pages remain operational/audit views. Legacy standalone jobs with no target remain a distinct explicit restore-old-draft mode; they are not treated as Post-origin updates.

## 14. Post Target / Lineage

The concrete gap was that `current_content_hash` was captured but never enforced. Apply now locks the exact target row, verifies the hash, sanitizes draft HTML, updates that same Post, and writes `applied_at/applied_by/applied_fields` to job lineage in one transaction. If an editor changed the Post after generation, apply fails with `AI_POST_TARGET_CONTENT_CHANGED` instead of silently overwriting it.

Observed history has targeted jobs 9/10/11 for Post 10 and a successful same-target apply on job 11; standalone historical jobs remain separate. See [post_ai_lineage_matrix.json](artifacts/post_ai_lineage_matrix.json).

## 15. AI Preview / Apply

Compare, approve, reject and apply remain available in Post Edit according to existing permissions/status. Tests prove review is explicit, first apply updates the target, second apply is a no-op, and `Post::count()` does not increase.

## 16. Promotion Architecture

Discount calculation and marketing display are separate concerns. `PromotionPriceResolver` still owns percent/fixed pricing and scope precedence. `PromotionDisplayResolver` now owns schedule, placement, context scope and deterministic display selection. No Promotion and Website Campaign models were merged.

## 17. Discount Types

Percent and fixed discount behavior remains covered and unchanged. `custom` remains a configuration-only discount value with no invented arithmetic. See [promotion_contract_matrix.csv](artifacts/promotion_contract_matrix.csv).

## 18. Promotion Placements

Root cause: `landing`, `banner`, `popup` and `announcement_bar` were selectable and persisted but no frontend code consumed `Promotion.placement`.

The shared `x-promotions` component is now included in both application layouts. Runtime ownership is explicit:

- `landing`: route `landing`;
- `banner`: route `home`;
- `popup`: all routes, filtered by optional Product scope when a Product context exists;
- `announcement_bar`: all routes with the same scope rules.

Schedule is evaluated by `Promotion::currentlyActive`; latest ID wins deterministically within each placement. Rich content is sanitized at render. See [promotion_placement_matrix.csv](artifacts/promotion_placement_matrix.csv).

## 19. Promotion AI

Root cause: `detailed_content => content` existed in the field map and normalizer aliases, but `detailed_content` was absent from selectable fields and absent from the local governed Promotion draft. Both are now present. Generated rich content is sanitized before entering form state; existing fill-empty/overwrite/append review behavior remains. The record is not saved until the operator saves the form, preserving manual review and same-record semantics. No discount percentage, dates, price, stock or technical facts are generated.

See [promotion_ai_field_matrix.csv](artifacts/promotion_ai_field_matrix.csv).

## 20. Shared Rendering / Overlap

Website Campaign is a targeted display/analytics system; Promotion is a discount entity plus optional marketing display. They overlap visually but not semantically, so they share layout integration and sanitization policy only—not persistence, status or tracking.

## 21. Security / RBAC

- Campaign preview is an action inside the authorized Edit resource; no public unpublished route was added.
- Existing `site_campaign.edit`, `promotion.edit`, `post.edit` and AI permissions remain unchanged.
- Campaign CTA URLs accept only internal paths or HTTP(S) at render time.
- Rich HTML sanitizer blocks executable tags, event handlers, style overlays, `contenteditable` and unsafe schemes.
- Preview cannot write analytics. Normal campaign tracking contract remains CSRF-protected and validates event types/current activity.

## 22. Performance

Campaign table aggregates no longer issue per-row event counts. Promotion price lookups were found to issue one active-Promotion query per Product card because each Blade card resolved a fresh service. `PromotionPriceResolver` is now request-scoped and reuses one eager-loaded active Promotion snapshot. A 12-Product regression caps the active Promotion select at one. Local homepage measurement fell from 96 total queries during forensic reproduction to 24 after this correction; the final feature-specific result was two Promotion selects (one pricing snapshot, one display resolver) and one Campaign select.

## 23. Tests

- Focused final set: 26 tests / 89 assertions, PASS before the performance guard; subsequent Promotion/runtime focused set also PASS.
- Full suite: **463 tests; 462 passed; 1,737 assertions; 1 existing skip; 0 failures/errors**.
- Composer validate/audit, npm high audit, Vite build, config/route/view cache, PHP lint and `git diff --check`: PASS.

## 24. Browser Evidence

No Playwright, Dusk, Cypress, Selenium or authenticated Chrome/CDP process was available. Therefore no browser screenshot, console/network trace, mouse/cursor/selection/toolbar proof, campaign visual proof or Promotion visual proof is claimed. HTTP/Blade/Livewire rendering and persistence proofs pass, but the browser-only acceptance gates remain unproven.

## 25. Remaining Limitations

1. Browser interaction certification is required to close the RichEditor report and visual placement checks.
2. Runtime database currently has zero Website Campaign records, so production-data campaign analytics cannot be demonstrated without an authorized fixture/content record.
3. Worker is intentionally disabled/offline and scheduler heartbeat is stale locally. These did not affect fixture-only AI/UI tests; production AI generation remains unavailable until operations intentionally enables a healthy worker.

## 26. Final Verdict

- Website Campaigns: **SERVER/RUNTIME PASS; BROWSER PROOF UNAVAILABLE**.
- Post Editor: **SERVER/LIVEWIRE PASS; BROWSER INTERACTION NOT PROVEN**.
- AI Post workflow: **PASS** for target lineage, stale-write protection, idempotency and unchanged Post count.
- Promotions: **SERVER/RUNTIME PASS; BROWSER PROOF UNAVAILABLE**.
- Overall: **PARTIAL / STOP** solely because mandatory browser interaction evidence is unavailable. No commit, tag or push was performed.

## 27. Browser Certification Closure

The historical PARTIAL verdict above was closed on 2026-08-29 by [MARKETING_CONTENT_BROWSER_CERTIFICATION_REPORT.md](MARKETING_CONTENT_BROWSER_CERTIFICATION_REPORT.md).

- Playwright `1.62.1` reused Google Chrome `152.0.7977.64` against a dedicated migrated SQLite database.
- Six browser scenarios passed with zero relevant console, page, same-origin network or Livewire errors.
- Campaign active/inactive/future, authorized preview, image and video renderers were visually proven; preview event count remained unchanged.
- RichEditor passed pointer hit testing, click, focus, cursor, mouse selection, Vietnamese typing, delete, paste, toolbar, save and reload.
- AI Post compare/approve/apply preserved the same Post ID and count; double apply remained idempotent.
- Promotion banner, landing, popup, announcement and AI description/detailed-content flows passed at desktop/mobile sizes without changing structured discount facts.
- Provider calls: `0`. MySQL Product/catalog data was not used by or exposed to the browser fixture.

**Current final status: MARKETING / CONTENT RUNTIME AUDIT = PASS.**
