# Live Server Update Guide

Current release: `v1.28.2`

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

## 2. Deploy v1.28.2

```bash
cd /path/to/dieuhoa-tudung
git fetch origin --tags
git checkout main
git pull --ff-only origin main
git checkout v1.28.2
composer install --no-dev --prefer-dist --optimize-autoloader
npm ci
npm run build
php artisan migrate:status
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Inspect pending migrations before running `migrate --force`. v1.28.2 repairs the existing AI bulk-runtime migration so a MariaDB deployment that stopped after creating the first two empty tables can resume without dropping them.

## 3. Mandatory managed-worker restart

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

## 4. Worker and scheduler verification

```bash
php artisan ai:queue-health --json
php artisan ai:managed-health-check
php artisan schedule:list
```

Require:

- fresh worker heartbeat and expected process identity;
- web and worker both report v1.28.2/the deployed build;
- correct project, PHP runtime, `APP_ENV`, authoritative DB and queue connection;
- queue exactly `ai_governed`, never legacy `ai`;
- no duplicate process, unexpected processing, orphan lease or stale reservation;
- non-provider self-test completes in an independent worker process with Product/catalog mutation false;
- scheduler integration exists and heartbeat is fresh where implemented.

Keep AI processing disabled and block deployment on any worker version, DB, queue or scheduler mismatch.

## 5. Application smoke

Public:

- homepage, Product listing/detail, search, filters and calculator;
- representative Product main image, gallery, thumbnails, related cards and fallback;
- sitemap and Merchant feed.

Admin:

- login and Dashboard;
- Product list/edit and Post edit;
- AI status/history/provider pages;
- Media/CDN and Import/Export.

Confirm no Product shows **Chờ duyệt** unless its edit page has a real reviewable draft. Do not call the AI provider solely for deployment smoke.

## 6. Restore desired state and enable traffic

After web, DB, worker, scheduler, queue and smoke checks pass, restore `DESIRED_STATE_BEFORE_DEPLOY` intentionally. Then return the site from maintenance mode if the deployment used it:

```bash
php artisan up
```

## 7. Deployment evidence

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

## 8. Rollback

1. Stop new AI claims safely and record queue state.
2. Let active work settle under the governed lease/recovery contract.
3. Check out the reviewed rollback tag only with a database-aware rollback plan. Do not roll back to v1.28.1 on MariaDB before resolving the failed AI runtime migration.
4. Reinstall matching dependencies/assets and restore DB only if migration/data rollback requires it.
5. Rebuild config, route and view caches.
6. Restart the OS-managed worker again so it loads rollback code.
7. Verify worker/web version, DB, `ai_governed`, scheduler and non-provider self-test.
8. Restore the original desired state only after smoke checks pass.

See [AI Worker Deployment Runbook](operations/AI_WORKER_DEPLOYMENT_RUNBOOK.md) for process-manager contracts and blocker classifications.
