# v1.30.0

Date: 2026-08-29

## Highlights

This backward-compatible feature release completes Website Campaign runtime/preview support, makes Promotion placements real frontend surfaces, hardens Post AI apply lineage and closes the prior browser-certification gap with a repeatable Playwright harness.

## Website Campaigns

- All eight selectable Campaign types share the production renderer: modal, slide-in, top/bottom bar, floating CTA, image/video popup and Product promotion popup.
- Runtime selection enforces status, inclusive schedule window, route placement, device, URL targeting, priority and conflict groups.
- Filament now shows derived readiness plus aggregate impressions/clicks instead of presenting `status=active` as sufficient proof.
- No Campaign result cache exists; normal edits are reflected on the next request.

## Campaign Preview

- Authorized editors can preview draft, inactive and future Campaigns from the edit page.
- Preview uses the same `x-site-campaigns` component and CSS as production.
- Preview emits no event request, dataLayer event, frequency storage write or Campaign mutation and adds no public preview route.

## Posts / Editor

- Rich HTML from AI pipelines is sanitized before entering the Post editor or public renderer.
- Chrome browser proof covers pointer hit target, click, focus, cursor movement, Vietnamese typing, delete, mouse selection, bold toolbar, paste, save and reload.
- Persisted content remains an HTML fragment compatible with Filament RichEditor/TipTap.

## AI Post Workflow

- Post-origin generation keeps target Post ID, operation, requested fields and current-content hash.
- Apply row-locks the exact Post and rejects stale content with `AI_POST_TARGET_CONTENT_CHANGED`.
- Apply updates the same Post, records apply lineage and is idempotent; it does not create a second Post.
- Job-history pages remain operational/audit surfaces rather than the primary content workflow.

## Promotions

- `banner` renders on home, `landing` on `/dieu-hoa-tu-dung`, and `popup`/`announcement_bar` render through shared layouts.
- Scope and schedule remain authoritative, with deterministic latest-record selection per placement.
- Discount arithmetic remains owned by `PromotionPriceResolver`; placement rendering does not change price semantics.
- Active Promotion snapshots are reused per request to avoid Product-card N+1 queries.

## Promotion AI

- The existing governed form action now supports both program description and detailed content.
- Generated fields stay in form preview until an operator saves the same Promotion.
- Discount type/value, schedule, Product scope, stock and other structured facts are never generated or overwritten.

## Admin UX

- Campaign readiness, latest event time and event aggregates improve operational truth.
- Promotion tables distinguish display readiness and discount configuration.
- The Lead edit form uses the Filament 5-compatible action namespace.

## Security

- Campaign unpublished preview remains behind existing server-side edit authorization.
- Rich HTML sanitization removes scripts, executable elements, event handlers, class/style/id/contenteditable attributes and unsafe URL schemes.
- Existing Post/Promotion/Campaign permissions and AI review/apply permissions remain unchanged.

## Database Changes

No new migration is included. Validated migration count remains **93**, with no pending repository migration.

## AI Worker / Queue

- Canonical governed queue: `ai_governed` on the configured `database` connection.
- Canonical worker command reported by the application:

```text
php artisan ai:managed-worker --queue=ai_governed --sleep=3 --tries=3 --timeout=900
```

- Local validation found desired state `DISABLED`, actual state `OFFLINE`, no processing/stuck AI jobs and a stale local scheduler heartbeat. This is truthful local evidence, not production certification.
- Browser and regression validation caused zero provider calls.

## Scheduler

Production must execute `php artisan schedule:run` every minute. Current scheduled AI tasks include queue-health recording and stuck-job recovery every five minutes. Scheduler health is a separate release gate from worker health.

## Upgrade Instructions

1. Record current tag/commit, application version, migration state and maintenance state.
2. Create and verify a non-zero production database backup outside Git.
3. Run `php artisan ai:queue-health`; record desired/actual state, heartbeat, queue, pending/processing/failed/stuck jobs, worker version/build/hash and scheduler status.
4. If AI work is processing, stop new claims through the existing desired-state/drain contract and allow active work to settle. Never kill or purge blindly.
5. Record `DESIRED_STATE_BEFORE_DEPLOY`.
6. Fetch the release and check out `v1.30.0` (or deploy the corresponding immutable artifact).
7. Run production dependency installation as the application service account:

```text
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
```

8. This release has no new migration, but run `php artisan migrate --force` and `php artisan migrate:status` to verify the full 93-migration state.
9. Deploy committed Vite assets or rebuild with the repository-supported Node toolchain, then run:

```text
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

10. Verify `php artisan about` reports production and debug disabled.
11. Inspect the live process manager. Restart generic workers only if their consumed `ai`/`default` job code changed; do not remove them without producer evidence.
12. Restart the canonical managed AI worker through the actual Supervisor/systemd/NSSM program. Do not spawn it from HTTP and do not assume `queue:restart` reloads the managed parent.
13. Verify old/new process identity, one intended managed parent, correct project/PHP/environment/DB, `database` connection, `ai_governed` queue and web/worker version/build/hash match.
14. Restarting the process must not change desired AI state. Preserve `DISABLED`; restore `ENABLED` only after health gates pass if that was the recorded operator intent.
15. Verify the scheduler process/cron and fresh heartbeat. Run a non-provider cross-process `php artisan ai:managed-health-check` only after the governed worker is healthy.
16. Re-run `php artisan ai:queue-health`; require no unexpected processing, stuck job, orphan lease/reservation or release drift.
17. Smoke-test public home/Product/Post/Calculator/Quote pages and Admin login, Campaign preview, Post editor, Promotions and AI operations. Use inactive/synthetic content for customer-facing smoke where possible.

## Validation

- Browser: 6 Playwright scenarios passed in Google Chrome `152.0.7977.64`; 11 screenshots; zero relevant console/page/Livewire/same-origin network errors.
- Full Laravel suite: 463 tests, 462 passed, 1 existing skip, 1,737 assertions, zero failures/errors.
- Composer validate/audit: PASS; npm high audit: PASS; Vite build: PASS.
- Config/route/view cache, changed-file PHP lint and `git diff --check`: PASS.
- Data: 81 Products / 212 catalog sources / 36,453 catalog models / 656,507 catalog fields; canonical BTU hash unchanged.
- AI provider request log remained 236 with latest historical record at `2026-08-23 10:14:31`; provider calls caused by validation: 0.

## Known Limitations

- Production Campaign/Promotion smoke, process-manager identity and scheduler heartbeat must be verified on the live host.
- Video proof certifies renderer/iframe construction, not third-party YouTube uptime.
- AI browser completion used deterministic persisted state/local governed Promotion content; no live provider was called.
- Local operator intentionally keeps AI worker disabled/offline and the local scheduler is stale.

## Rollback

1. Capture queue/runtime state and the original desired state; disable/drain governed claims safely if needed.
2. Check out `v1.29.0` (or its verified artifact) and install matching dependencies/assets.
3. No schema rollback is expected because v1.30.0 adds no migration. Restore the database backup only for an independently proven data problem.
4. Rebuild config, route and view caches.
5. Restart affected generic workers through their actual process-manager names.
6. Restart the managed AI worker through the OS process manager so it loads rollback code; do not rely only on `queue:restart`.
7. Verify one intended worker, rollback web/worker version/build/hash match, correct DB/queue and scheduler health.
8. Run the non-provider worker self-test if the worker is intended healthy.
9. Restore the original desired AI state intentionally only after all rollback health checks pass.
10. Smoke-test public and Admin Campaign, Post editor, Promotion and queue-health surfaces.
