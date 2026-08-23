# Live Server Update Guide

Current release: `v1.29.0`

This guide is mandatory for every deployment. Updating only the web files is not a complete release because the AI worker is a long-running PHP process.

## 1. Before deployment

1. Verify the authoritative production database and create a tested, non-zero backup.
2. Record the current tag/commit and rollback target.
3. Capture the complete AI runtime snapshot:

   ```bash
   php artisan ai:queue-health --json
   php artisan migrate:status
   php artisan schedule:list
   ```

4. Record `DESIRED_STATE_BEFORE_DEPLOY`, actual worker state, heartbeat, web/worker version and build, queue connection/name, pending/processing/failed/stuck counts, leases, slots and reservations.
5. If an operation is processing, do not restart or kill it blindly. Stop new claims through the canonical desired-state contract when needed and allow the active operation to settle.

## 2. Controlled maintenance choice

The current live checkout is updated in place, so use Laravel maintenance mode after the backup and worker drain have been verified:

```bash
php artisan down --retry=60
```

An atomic release-directory/symlink deployment may omit maintenance mode only when that mechanism is already proven on the host.

## 3. Deploy v1.29.0

```bash
cd /path/to/dieuhoa-tudung
git fetch origin --tags
git status --short
git checkout v1.29.0
cat VERSION
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
php artisan migrate:status
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

The tag contains reviewed `public/build` assets, so live does not need Node merely to serve this release. If deployment policy rebuilds assets on the host, first verify a Node/npm version compatible with the lock file, then run `npm ci` and `npm run build`; a failed build blocks deployment.

Inspect pending migrations before running `migrate --force`. v1.29.0 adds exactly:

- `2026_08_23_120000_add_rule_version_to_btu_calculations_table.php`;
- `2026_08_23_130000_add_calculation_method_to_btu_calculations_table.php`;
- `2026_08_24_000000_add_workflow_context_to_quote_requests_table.php`.

Expected final migration count is 93. Do not use `migrate:fresh` or broad rollback.

## 4. Mandatory managed-worker restart

The application worker command is:

```text
php artisan ai:managed-worker --queue=ai_governed --sleep=3 --tries=3 --timeout=900
```

Restart the reviewed OS-managed definition after code and caches are updated:

- Local Windows Task Scheduler:

  ```powershell
  powershell -File scripts\restart_ai_worker_task.ps1
  powershell -File scripts\restart_ai_worker_task.ps1 -Restart
  ```

- Linux systemd: `systemctl restart <reviewed-ai-worker-service>`
- Supervisor: `supervisorctl restart <reviewed-ai-worker-program>`
- Windows production: restart the reviewed NSSM/Windows Service/Task Scheduler definition.

The live process-manager target must be selected on the deployment host. Never spawn the worker from HTTP and do not rely on `php artisan queue:restart` alone for this custom managed parent/child worker.

Restarting the process must not change operator intent:

- pre-deploy `DISABLED` -> remain `DISABLED`;
- pre-deploy `ENABLED` -> restore only after post-deploy health is green.

## 5. Worker and scheduler verification

```bash
php artisan ai:queue-health --json
php artisan ai:managed-health-check
php artisan schedule:list
```

Require:

- fresh worker heartbeat and expected process identity;
- web and worker both report v1.29.0/the deployed build;
- correct project, PHP runtime, `APP_ENV`, authoritative DB and queue connection;
- queue exactly `ai_governed`, never legacy `ai`;
- no duplicate process, unexpected processing, orphan lease or stale reservation;
- non-provider self-test completes in an independent worker process with Product/catalog mutation false;
- scheduler integration exists and heartbeat is fresh where implemented.

Keep AI processing disabled and block deployment on any worker version, DB, queue or scheduler mismatch.

## 6. Application smoke

Public:

- homepage, Product listing/detail, search and filters;
- Calculator Area and Volume methods, raw need versus market tier, catalog gap and at least one equipment-type result;
- direct Quote, Calculator -> Quote and Product -> Quote prefill without creating an uncontrolled production Lead;
- representative Product main image, gallery, thumbnails, related cards and fallback;
- sitemap and Merchant feed.

Admin:

- login and Dashboard;
- Product list/edit and Post edit;
- AI status/history/provider pages;
- Media/CDN and Import/Export.

Confirm no Product shows **Chờ duyệt** unless its edit page has a real reviewable draft. Do not call the AI provider solely for deployment smoke.

## 7. Restore desired state and enable traffic

After web, DB, worker, scheduler, queue and smoke checks pass, restore `DESIRED_STATE_BEFORE_DEPLOY` intentionally. Then return the site from maintenance mode if the deployment used it:

```bash
php artisan up
```

## 8. Deployment evidence

Record:

```text
APPLICATION VERSION:
GIT COMMIT/TAG:

WEB: status / version
AI WORKER: desired / actual / heartbeat / version / queue / DB / restart result
SCHEDULER: status / heartbeat
QUEUE: pending / processing / failed / stuck / leases / slots / reservations
SELF TEST: PASS / NOT AVAILABLE / BLOCKED
PUBLIC/ADMIN/MEDIA SMOKE: PASS / BLOCKED
FINAL: DEPLOYMENT PASS / BLOCKED
```

## 9. Rollback

1. Stop new AI claims safely and record queue state.
2. Let active work settle under the governed lease/recovery contract.
3. Check out reviewed rollback tag `v1.28.2` with a database-aware rollback plan.
4. Reinstall matching dependencies/assets. The three v1.29.0 columns are additive and can normally remain; restore or roll back DB only if a reviewed migration/data decision requires it.
5. Rebuild config, route and view caches.
6. Restart the OS-managed worker again so it loads rollback code.
7. Verify worker/web version, DB, `ai_governed`, scheduler and non-provider self-test.
8. Restore the original desired state only after smoke checks pass.

See [AI Worker Deployment Runbook](operations/AI_WORKER_DEPLOYMENT_RUNBOOK.md) for process-manager contracts and blocker classifications.
