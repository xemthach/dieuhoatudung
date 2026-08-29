# Live deployment runbook — v1.31.3

## Deploy and verify

```bash
cd /home/dieuhoatudungcom/dieuhoatudung.com/public_html
runuser -u dieuhoatudungcom -- php artisan about
runuser -u dieuhoatudungcom -- php artisan migrate:status
runuser -u dieuhoatudungcom -- php artisan ai:queue-health --json
git fetch origin --tags --prune
git checkout v1.31.3
git rev-parse HEAD
runuser -u dieuhoatudungcom -- composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
runuser -u dieuhoatudungcom -- php artisan migrate --force
runuser -u dieuhoatudungcom -- php artisan optimize:clear
runuser -u dieuhoatudungcom -- php artisan config:cache
runuser -u dieuhoatudungcom -- php artisan route:cache
runuser -u dieuhoatudungcom -- php artisan view:cache
supervisorctl reread
supervisorctl update
supervisorctl restart dieuhoa-ai-governed
supervisorctl restart dieuhoa-worker:*
supervisorctl status
runuser -u dieuhoatudungcom -- php artisan about
runuser -u dieuhoatudungcom -- php artisan migrate:status
runuser -u dieuhoatudungcom -- php artisan ai:queue-health --json
```

v1.31.3 has no new migration. Preserve the pre-deploy `worker_desired_state`; restarting a disabled worker must not enable AI. Verify version/build/hash, fresh heartbeat, production database `dieuhoa_tudung`, queue `ai_governed`, no stuck jobs and healthy scheduler.

Do not bulk retry historical FAILED/BLOCKED items. The new recovery is limited to one additional request for a newly executed item whose response is `CONTENT_TOO_SHORT`; it does not lower the validator threshold.

## Rollback

```bash
cd /home/dieuhoatudungcom/dieuhoatudung.com/public_html
git checkout v1.31.2
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

Verify the rollback worker is using `v1.31.2` before restoring the previous desired state. Do not purge queues, delete AI history, modify Product content, or import catalog workbooks.
