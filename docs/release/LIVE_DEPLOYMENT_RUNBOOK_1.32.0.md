# Live deployment runbook — v1.32.0

## Pre-deploy

Capture the desired and actual AI worker state before changing code. If the desired state is disabled, it must remain disabled after the deployment restart.

```bash
cd /home/dieuhoatudungcom/dieuhoatudung.com/public_html
git fetch origin --tags --prune
git checkout v1.32.0
git rev-parse HEAD
runuser -u dieuhoatudungcom -- php artisan about
runuser -u dieuhoatudungcom -- php artisan ai:queue-health --json
supervisorctl status
```

Drain/disable workers according to the captured operator state; do not enable AI merely because a process is restarted.

## Deploy

```bash
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

Verify a fresh worker heartbeat, `database` queue connection, `ai_governed` queue, matching application/worker version and build/hash, no pending/processing/stuck AI work, production DB identity and scheduler health. Run only the non-provider queue health probe.

Restore the prior operator desired state intentionally. Restarting a managed worker does not authorize or enable AI generation.

## Data limitations

Do not auto-fix unresolved category assignments during code deployment. Do not import the SkyAir production workbook during code deployment; both are separate controlled mutations.

## Rollback

```bash
git checkout v1.31.5
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

Verify the restarted worker is running rollback code. Preserve the pre-rollback desired worker state and do not delete Product, AI draft, request-log or ledger evidence.
