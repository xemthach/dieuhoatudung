# Live deployment runbook v1.33.4

## Preconditions

- Deploy exact annotated tag `v1.33.4`; do not use `git pull`, `composer update` or Production code patches.
- Product count `0` before restore is intentional.
- Do not upload/confirm a Product workbook until backup, FK parity and SYSTEM RESTORE preview have passed.

## Predeploy and backup

```bash
cd /home/dieuhoatudungcom/dieuhoatudung.com/public_html
git status --short
git fetch --prune --tags origin
git rev-parse v1.33.4^{commit}
php artisan about --no-ansi
php artisan migrate:status --no-ansi
php artisan ai:queue-health --json
supervisorctl status
```

Require an empty Git status. Record the AI desired state before pausing/draining through the managed-worker contract. Create and verify a non-zero DB backup with path, timestamp, size and checksum before continuing.

## Deploy exact release

```bash
git checkout v1.33.4
git rev-parse HEAD
cat VERSION
runuser -u dieuhoatudungcom -- composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
runuser -u dieuhoatudungcom -- php artisan migrate --force
runuser -u dieuhoatudungcom -- php artisan migrate:status --no-ansi
runuser -u dieuhoatudungcom -- php artisan optimize:clear
runuser -u dieuhoatudungcom -- php artisan config:cache
runuser -u dieuhoatudungcom -- php artisan route:cache
runuser -u dieuhoatudungcom -- php artisan view:cache
supervisorctl reread
supervisorctl update
supervisorctl restart dieuhoa-worker_00
supervisorctl restart dieuhoa-worker_01
supervisorctl restart dieuhoa-ai-governed
supervisorctl status
runuser -u dieuhoatudungcom -- php artisan ai:queue-health --json
```

Do not run npm build on Live. Verify the certified release asset manifest is present. Require app/worker `1.33.4`, exact build/hash parity, fresh heartbeat and restoration of the exact predeploy AI desired state.

## Read-only FK parity and preview

Use the newly exported Local `PRODUCT_SYSTEM_RESTORE v1` workbook only. Before confirmation, compare all referenced `brand_id`, `product_category_id`, `catalog_source_id` and `catalog_model_id` plus model-source relationships against Live. Any missing/mismatched FK is a stop condition; never null it.

Upload workbook through Import/Export. Preview must show:

- `SYSTEM RESTORE`;
- total/valid/create = 378 (or the current Local export count recorded at deployment);
- update/error = 0;
- no provenance, category schema, FK, manifest or checksum error.

Only then confirm. Expected result: created = export count, updated/skipped/errors = 0.

## Post-restore

Verify Product count, representative IDs/SKU/slug/FKs/capacities/specs/media/SEO, then test 18k, 48k and combined BTU URLs. Do not run marketing-capacity backfill when restored Product rows already carry canonical `marketing_capacity_btu`.

## Rollback

Before any restore confirmation, return to `v1.33.3`, rebuild caches and restart the same Supervisor workers. After a successful data restore, use only the verified DB backup through a separately approved recovery procedure.
