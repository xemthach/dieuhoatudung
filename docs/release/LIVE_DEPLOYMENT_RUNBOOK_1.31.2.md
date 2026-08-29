# Live deployment runbook — v1.31.2

Project path: `/home/dieuhoatudungcom/dieuhoatudung.com/public_html`
Release tag: `v1.31.2`

## Pre-deploy

Run as root and record the worker state before changing anything:

```bash
cd /home/dieuhoatudungcom/dieuhoatudung.com/public_html
runuser -u dieuhoatudungcom -- php artisan about
runuser -u dieuhoatudungcom -- php artisan migrate:status
runuser -u dieuhoatudungcom -- php artisan ai:queue-health --json
```

Preserve `worker_desired_state`. If it is `DISABLED`, keep it disabled. Do not deploy if the environment/database identity is wrong or a live job is processing without a safe drain plan.

## Deploy exact release

```bash
cd /home/dieuhoatudungcom/dieuhoatudung.com/public_html
git fetch origin --tags --prune
git checkout v1.31.2
git rev-parse HEAD
runuser -u dieuhoatudungcom -- composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
runuser -u dieuhoatudungcom -- php artisan migrate --force
runuser -u dieuhoatudungcom -- php artisan optimize:clear
runuser -u dieuhoatudungcom -- php artisan config:cache
runuser -u dieuhoatudungcom -- php artisan route:cache
runuser -u dieuhoatudungcom -- php artisan view:cache
```

There is no new migration in v1.31.2. Do not run Product/catalog imports or AI retries during deployment.

## Restart PHP and workers

Restart the PHP handler through the normal hosting service mechanism to clear old OPcache. Then:

```bash
supervisorctl reread
supervisorctl update
supervisorctl restart dieuhoa-ai-governed
supervisorctl restart dieuhoa-worker:*
supervisorctl status
```

Managed AI command:

```text
/usr/bin/php /home/dieuhoatudungcom/dieuhoatudung.com/public_html/artisan ai:managed-worker --queue=ai_governed --sleep=3 --tries=3 --timeout=900
```

Restarting a process does not enable AI. Restore the exact pre-deploy desired state intentionally.

## Post-deploy verification

```bash
runuser -u dieuhoatudungcom -- php artisan about
runuser -u dieuhoatudungcom -- php artisan migrate:status
runuser -u dieuhoatudungcom -- php artisan ai:queue-health --json
```

Require application/worker version and build alignment, fresh heartbeat, production database `dieuhoa_tudung`, `database` connection, queue `ai_governed`, no stuck jobs and healthy scheduler. Do not bulk retry historical FAILED/BLOCKED items.

## Rollback

```bash
cd /home/dieuhoatudungcom/dieuhoatudung.com/public_html
git checkout v1.31.1
runuser -u dieuhoatudungcom -- composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
runuser -u dieuhoatudungcom -- php artisan optimize:clear
runuser -u dieuhoatudungcom -- php artisan config:cache
runuser -u dieuhoatudungcom -- php artisan route:cache
runuser -u dieuhoatudungcom -- php artisan view:cache
supervisorctl restart dieuhoa-ai-governed
supervisorctl restart dieuhoa-worker:*
runuser -u dieuhoatudungcom -- php artisan about
runuser -u dieuhoatudungcom -- php artisan ai:queue-health --json
```

Verify the rollback worker uses `v1.31.1` before restoring the previous desired state. Do not purge queues, delete AI history, modify Product content, or import SkyAir workbooks.
