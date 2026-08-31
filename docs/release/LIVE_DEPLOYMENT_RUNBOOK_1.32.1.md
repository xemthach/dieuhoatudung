# Live deployment runbook — v1.32.1

## Pre-deploy

Record the current commit/tag, database identity, migration state, Supervisor status, AI desired/actual state, heartbeat, queue counts, scheduler/watchdog state, and application/worker version/build/hash.

Do not deploy while `ai_governed` has active processing. Do not change the operator's desired worker state merely to deploy code.

```bash
cd /home/dieuhoatudungcom/dieuhoatudung.com/public_html
git fetch origin --tags --prune
git checkout v1.32.1
git rev-parse HEAD
runuser -u dieuhoatudungcom -- php artisan about
runuser -u dieuhoatudungcom -- php artisan migrate:status
runuser -u dieuhoatudungcom -- php artisan ai:queue-health --json
supervisorctl status
```

Require the expected production database, queue connection `database`, queue `ai_governed`, and zero pending/processing/stuck AI Product jobs before replacing worker code.

## Deploy

This patch has no migration, but `migrate:status` must remain clean.

```bash
runuser -u dieuhoatudungcom -- composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
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

Restart the PHP handler/OPcache using the hosting platform's reviewed command. Do not guess the service name.

## Post-deploy gates

Require all of the following:

- application and worker version `1.32.1`;
- matching build ID and worker code hash;
- fresh managed-worker heartbeat and one intended managed process;
- correct production database and `ai_governed` queue;
- pending, processing and stuck counts all zero;
- scheduler and watchdog healthy in production;
- AI desired state restored exactly to the pre-deploy operator state;
- Product Edit can resolve AI readiness and existing drafts without creating duplicate jobs.

Do not run a real-provider smoke call unless separately authorized. Do not bulk retry historical failed/blocked jobs.

## Rollback

```bash
git checkout v1.32.0
runuser -u dieuhoatudungcom -- composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
runuser -u dieuhoatudungcom -- php artisan optimize:clear
runuser -u dieuhoatudungcom -- php artisan config:cache
runuser -u dieuhoatudungcom -- php artisan route:cache
runuser -u dieuhoatudungcom -- php artisan view:cache
supervisorctl restart dieuhoa-ai-governed
supervisorctl restart dieuhoa-worker:*
supervisorctl status
runuser -u dieuhoatudungcom -- php artisan about
runuser -u dieuhoatudungcom -- php artisan migrate:status
runuser -u dieuhoatudungcom -- php artisan ai:queue-health --json
```

Verify rollback web/worker version, build/hash, database, queue and heartbeat alignment. Preserve AI history, drafts, request logs and Product rows.
