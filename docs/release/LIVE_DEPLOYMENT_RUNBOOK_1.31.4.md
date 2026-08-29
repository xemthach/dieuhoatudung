# Live deployment runbook — v1.31.4

```bash
cd /home/dieuhoatudungcom/dieuhoatudung.com/public_html
git fetch origin --tags --prune
git checkout v1.31.4
git rev-parse HEAD
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
runuser -u dieuhoatudungcom -- php artisan ai:queue-health --json
```

Preserve the pre-deploy AI desired state. Restarting a disabled worker must not enable AI. Do not bulk retry historical jobs. Product content is changed only after explicit review and Apply.

## Rollback

Checkout `v1.31.3`, rebuild caches, restart both worker groups, and verify rollback worker version/build and queue heartbeat. Do not delete AI history or Product rows.
