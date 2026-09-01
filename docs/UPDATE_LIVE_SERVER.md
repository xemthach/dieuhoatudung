# Live Server Update Guide

Current release candidate: `v1.33.2`

Use the release-specific runbook:

[LIVE_DEPLOYMENT_RUNBOOK_1.33.2.md](release/LIVE_DEPLOYMENT_RUNBOOK_1.33.2.md)

## Quick command sequence

Run only after `v1.33.2` has been committed, tagged and pushed:

```bash
cd /home/dieuhoatudungcom/dieuhoatudung.com/public_html

git status --short
git rev-parse HEAD
runuser -u dieuhoatudungcom -- php artisan migrate:status
runuser -u dieuhoatudungcom -- php artisan ai:queue-health --json
supervisorctl status

runuser -u dieuhoatudungcom -- php artisan down --retry=60

git fetch origin --tags --prune
git checkout v1.33.2
git rev-parse HEAD
cat VERSION

runuser -u dieuhoatudungcom -- composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
runuser -u dieuhoatudungcom -- php artisan migrate:status
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
runuser -u dieuhoatudungcom -- php artisan ai:managed-health-check
sleep 5
runuser -u dieuhoatudungcom -- php artisan ai:queue-health --json
runuser -u dieuhoatudungcom -- php artisan schedule:list

runuser -u dieuhoatudungcom -- php artisan up
```

Mandatory result:

- web and worker both report `1.33.2`;
- build ID and worker code hash match;
- deployment is `UP_TO_DATE`;
- queue is `ai_governed`;
- pending/processing/stuck are all zero;
- managed health probe completes without provider or Product mutation;
- scheduler/watchdog are healthy on production;
- the v1.33.0 additive AI Product lifecycle migration remains applied and no migration is pending; v1.33.2 adds no migration;
- public and authenticated Product AI smoke tests pass.

Do not bulk retry historical AI jobs and do not call the real provider merely for deployment smoke.
