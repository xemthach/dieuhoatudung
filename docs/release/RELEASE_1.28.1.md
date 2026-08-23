# Release v1.28.1

Date: 2026-08-23

## Highlights

This patch closes two production-facing inconsistencies from v1.28.0: Product galleries now render the real CDN media shape expected by Alpine, and Product AI status/actions now share one actionable item/draft contract.

## Admin UX

- Product list status and filters no longer trust stale `products.ai_status` values.
- Product edit shows latest AI operation, draft availability, approval state and apply state.
- Review is visible only for a real reviewable draft; Apply is visible only for an approved, unapplied draft.

## AI Content

- Added `AiProductContentStateResolver` as the Product-level state source for table badges, live polling and review/apply eligibility.
- Applied drafts resolve to **Đã áp dụng** even when historical item/Product flags are stale.
- A legacy `REVIEW_REQUIRED` item without an actionable draft resolves to a safe blocked state rather than offering an impossible review action.
- No AI backend, governance, provider, draft or apply architecture was duplicated.

## AI Operations

- Governed queue remains `ai_governed` on the configured queue connection.
- Exact managed-worker entrypoint remains:

  ```text
  php artisan ai:managed-worker --queue=ai_governed --sleep=3 --tries=3 --timeout=900
  ```

- PHPUnit now uses isolated desired/managed worker-state paths; tests cannot read or toggle the operator's runtime file.
- Admin controls still persist desired state only. HTTP does not spawn, restart or kill long-running processes.

## Product / Media / CDN

- Added `ProductMediaResolver`, delegating URL resolution to the existing `MediaDiskService`/`media_url()` contract.
- Product detail now passes image objects to Alpine through `@js`, matching `currentImage.url` and `img.url` bindings.
- Missing/broken paths are omitted when valid media exists; fallback is emitted once only when no real Product image resolves.
- Main/gallery duplicates are removed by resolved URL. Product cards, comparison and related Products use the same main-image resolution.

## Reliability

- Dashboard Product AI counts use each Product's latest item and exclude applied, approved and rejected drafts from review-required totals.
- Product AI polling remains bounded and bulk-loaded; it does not call the provider.
- Existing deep links, Product routes, models and server-side authorization remain unchanged.

## Security

- Review/apply actions retain existing server-side RBAC.
- No prompt, provider response, credential, token or stack trace was added to Product status output.
- No SQL dump, `.env`, backup or private runtime artifact is part of this release.

## Upgrade Notes

- No new migration is introduced by v1.28.1.
- No Product/catalog backfill is required. Historical stale `products.ai_status` values remain non-authoritative and are safely ignored by the presentation/action resolver.
- Build assets are unchanged, but production environments that build locally should still run the reviewed `npm ci && npm run build` flow.

## Live Deployment

### 1. Pre-deploy backup and runtime capture

```bash
cd /path/to/dieuhoa-tudung
git rev-parse HEAD
php artisan migrate:status
php artisan ai:queue-health --json
```

Create and verify an authoritative database backup using the deployment platform's approved backup command. Record `DESIRED_STATE_BEFORE_DEPLOY`, actual worker state, heartbeat, web/worker version/build, queue, pending/processing/failed/stuck counts, leases, slots and reservations.

If a job is processing, do not kill it. Set the canonical desired state to `DISABLED` only when draining is required, then allow active work to settle under the existing lease/recovery contract.

### 2. Update application

```bash
git fetch origin --tags
git checkout main
git pull --ff-only origin main
git checkout v1.28.1
composer install --no-dev --prefer-dist --optimize-autoloader
npm ci
npm run build
php artisan migrate:status
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Inspect pending migrations before `migrate --force`. This patch adds none, but the command keeps a tagged deployment aligned with repository state.

## Worker Deployment — mandatory gate

Updating web code is not a complete deployment. Restart/reload the OS-managed AI worker after code/config replacement so it loads v1.28.1.

Current application command:

```text
php artisan ai:managed-worker --queue=ai_governed --sleep=3 --tries=3 --timeout=900
```

Use the actual reviewed process manager:

- Local Windows Task Scheduler proof:

  ```powershell
  powershell -File scripts\restart_ai_worker_task.ps1
  powershell -File scripts\restart_ai_worker_task.ps1 -Restart
  ```

- Linux systemd: `systemctl restart <reviewed-ai-worker-service>`
- Supervisor: `supervisorctl restart <reviewed-ai-worker-program>`
- Windows production: restart the reviewed NSSM/Windows Service/Task Scheduler definition.

The production OS/process-manager name is deployment-owned and must be selected before executing the live update. Do not replace it with an HTTP `exec`, `shell_exec`, `nohup` or `start /B` action. Do not rely on `queue:restart` alone for the custom managed parent/child process.

Restart does not mean Enable. If `DESIRED_STATE_BEFORE_DEPLOY=DISABLED`, keep it disabled. If it was enabled, restore it only after all health checks pass.

After restart:

```bash
php artisan ai:queue-health --json
php artisan ai:managed-health-check
php artisan schedule:list
```

Require a fresh heartbeat; matching web/worker version and build; correct `APP_ENV`, DB identity, queue connection and `ai_governed`; one intended managed process; no unexpected processing, orphan lease or stale reservation. The self-test must show separate dispatcher/worker PIDs, `provider_call=false`, and `product_mutation=false`.

## Scheduler

The scheduler is required for runtime health recording and stale-job recovery. Production must run one reviewed scheduler integration:

```cron
* * * * * cd /path/to/dieuhoa-tudung && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

or the platform-equivalent service/Task Scheduler definition. Verify the post-deploy scheduler heartbeat; an unknown/stale production heartbeat blocks AI runtime readiness.

## Post-deploy smoke

1. Confirm application version `1.28.1`, migrations and caches.
2. Open Admin Dashboard, Product list/edit, Post edit, AI history, AI status and Media/CDN.
3. Confirm Product 1248 (or an equivalent stale-status Product) does not show **Chờ duyệt** without a draft.
4. Confirm representative Product main/gallery thumbnails and related cards load from CDN or degrade to one safe fallback.
5. Check homepage, Product listing/detail, search, filters, calculator, sitemap and Merchant feed.
6. Restore the original desired worker state intentionally and recheck queue/scheduler health.

## Validation

- Focused changed-area suite: 63 tests, 376 assertions, PASS.
- Full Laravel suite: 363 tests, 1,260 assertions, 0 failures/errors, one existing skip.
- Composer validation/audit: PASS / no advisories.
- npm high-severity audit: PASS / 0 vulnerabilities.
- Vite production build, config cache, route cache, view cache, PHP lint and `git diff --check`: PASS.
- Data baseline: 81 Products / 212 catalog sources / 36,453 catalog models / 656,507 catalog fields / 90 migrations.
- Canonical BTU hash: `3e981c60fcadd3461746fd8f3b94855dc5205bad6c446c55c17066d40c47e3ba`.

## Known Limitations

- No Playwright/Dusk browser certification exists; release evidence is tests plus HTTP/server-rendered and CDN checks.
- Production process-manager identity is deployment-specific and must be selected/verified on the live host.
- Scheduler heartbeat is stale in the local Laragon environment; live scheduler proof remains mandatory.
- Existing historical media, mojibake and category-schema backlogs remain outside this patch.

## Rollback

1. Record queue/runtime state and set desired state to `DISABLED` if new claims must stop.
2. Let active work settle; do not force-kill an active provider operation.
3. Check out the previous reviewed tag `v1.28.0` and reinstall matching dependencies/assets.
4. Restore the database only if a migration/data rollback actually requires it; v1.28.1 itself adds no migration.
5. Rebuild config, route and view caches.
6. **Restart the OS-managed worker again** so it loads rollback code/config.
7. Require worker/web version match, correct DB/queue and fresh heartbeat; verify scheduler and run the non-provider self-test.
8. Restore the pre-rollback desired state only after smoke checks pass.

Operational details: `docs/operations/AI_WORKER_DEPLOYMENT_RUNBOOK.md`.
