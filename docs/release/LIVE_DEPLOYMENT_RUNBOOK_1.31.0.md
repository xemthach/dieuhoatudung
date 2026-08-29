# Live deployment runbook — v1.31.0

Release commit: `99451a1cffa4e010de6940cdf85032bc64135ace`
Tag: `v1.31.0`
Project path: `/home/dieuhoatudungcom/dieuhoatudung.com/public_html`

## Pre-deploy

Run as root and record `php artisan ai:queue-health --json`. Capture desired state, actual state, heartbeat, pending/processing/failed jobs, leases, slots, reservations, application build and worker build. Preserve `worker_desired_state` exactly.

If the desired state is `ENABLED` and a job is processing, do not kill blindly. Use the managed worker graceful/drain contract and wait for the operation to settle. If the operator intends `DISABLED`, keep it disabled.

## Deploy

```bash
cd /home/dieuhoatudungcom/dieuhoatudung.com/public_html
git fetch origin --tags
git checkout v1.31.0
git rev-parse HEAD
runuser -u dieuhoatudungcom -- composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
runuser -u dieuhoatudungcom -- php artisan migrate --force
runuser -u dieuhoatudungcom -- php artisan optimize:clear
runuser -u dieuhoatudungcom -- php artisan config:cache
runuser -u dieuhoatudungcom -- php artisan route:cache
runuser -u dieuhoatudungcom -- php artisan view:cache
```

This release has no new migration. Do not run `npm` on the live server unless the deployment artifact policy requires it; the tracked Vite build is part of the release.

## Restart long-running processes

The verified process manager is Supervisor. The exact programs are:

```text
dieuhoa-ai-governed
dieuhoa-worker:dieuhoa-worker_00
dieuhoa-worker:dieuhoa-worker_01
```

Reload the changed configuration and restart all long-running processes through Supervisor:

```bash
supervisorctl reread
supervisorctl update
supervisorctl restart dieuhoa-ai-governed
supervisorctl restart dieuhoa-worker:*
supervisorctl status
```

The managed AI command is exactly:

```text
/usr/bin/php /home/dieuhoatudungcom/dieuhoatudung.com/public_html/artisan ai:managed-worker --queue=ai_governed --sleep=3 --tries=3 --timeout=900
```

The generic worker is exactly:

```text
/usr/bin/php /home/dieuhoatudungcom/dieuhoatudung.com/public_html/artisan queue:work database --queue=ai,default --sleep=3 --tries=3 --timeout=900 --memory=256 --max-time=3600
```

## Post-deploy gate

```bash
runuser -u dieuhoatudungcom -- php artisan about
runuser -u dieuhoatudungcom -- php artisan migrate:status
runuser -u dieuhoatudungcom -- php artisan ai:queue-health --json
```

Require application and worker version/build/hash match, `database` queue connection, queue `ai_governed`, production database `dieuhoa_tudung`, fresh heartbeat, clean leases/slots/reservations and healthy scheduler. Run the non-provider worker self-test if available; provider calls must remain zero.

If the worker does not recover, keep desired state `DISABLED` and stop normal AI operations. Report the exact blocker (`WORKER_OFFLINE_AFTER_DEPLOY`, `WORKER_STALE_AFTER_DEPLOY`, `WORKER_VERSION_MISMATCH`, `WORKER_WRONG_DATABASE`, `WORKER_WRONG_QUEUE` or `SCHEDULER_UNHEALTHY`).

Restore the pre-deploy desired state intentionally. A pre-deploy `DISABLED` state must remain disabled after restart.

## Rollback

Checkout the previous release tag, rebuild caches, restart the same Supervisor programs, then repeat the version/DB/queue/heartbeat checks. Do not purge queues or delete Product rows. Do not import SkyAir workbooks during application deployment.
