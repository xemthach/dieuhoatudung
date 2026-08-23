# Release v1.28.2

Date: 2026-08-23

## Purpose

This production compatibility patch fixes a MariaDB 10.6 deployment blocker in `2026_08_16_030000_create_ai_bulk_runtime_executors`.

The failed v1.28.1 deployment had this proven partial state:

- `ai_bulk_runtime_batches`: exists, 0 rows;
- `ai_bulk_runtime_slots`: exists, 0 rows;
- `ai_bulk_runtime_leases`: absent;
- `ai_bulk_field_operations`: absent;
- `ai_bulk_apply_snapshots`: absent;
- migration record: absent.

## Fix

- Every table in the migration now has a `Schema::hasTable()` resume guard.
- Existing tables are preserved rather than recreated.
- Required lease fields `claimed_at` and `expires_at` use `DATETIME`, avoiding MariaDB's invalid implicit `TIMESTAMP` default under `NO_ZERO_DATE`.
- Runtime services still write both timestamps explicitly when a lease is claimed.

## Live recovery

Keep the AI worker disabled. After creating a verified database backup:

```bash
git fetch origin --tags
git pull --ff-only origin main
cat VERSION
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
npm ci
npm run build
php artisan migrate:status
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Expected migration result: the two existing empty runtime tables are skipped, the three absent tables are created, and the migration is recorded exactly once.

Verify read-only:

```bash
php artisan migrate:status
php artisan tinker --execute="foreach (['ai_bulk_runtime_batches','ai_bulk_runtime_slots','ai_bulk_runtime_leases','ai_bulk_field_operations','ai_bulk_apply_snapshots'] as \$table) dump([\$table => Schema::hasTable(\$table)]);"
php artisan ai:queue-health --json
```

Restart the reviewed OS-managed worker after code/cache deployment so web and worker load the same release. Preserve the operator's original desired state; restart does not mean enable.

## Safety

- Do not drop the two partial tables.
- Do not run `migrate:fresh` or broad rollback.
- Do not restore the v1.28.1 migration file manually on live.
- Provider calls: 0 required.
- Product/catalog writes: 0 required.
- Queue purge: not required.

## Rollback

Code rollback is not sufficient once the migration succeeds. Preserve the verified pre-deploy database backup and use the controlled deployment runbook. Do not run the migration's broad `down()` method on production merely to undo this compatibility patch.
