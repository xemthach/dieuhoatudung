# Live Server Update Guide

Use this guide after a release tag has been pushed and the GitHub release has been created.

## 1. Backup

```bash
cd /path/to/dieuhoa-tudung
php artisan down
php artisan backup:run || true
mysqldump -u DB_USER -p DB_NAME > backup-$(date +%F-%H%M).sql
```

## 2. Pull Release

```bash
git fetch origin --tags
git checkout main
git pull --ff-only origin main
git checkout v1.13.0
```

## 3. Install Dependencies

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
```

## 4. Update Application

```bash
php artisan migrate --force
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan filament:clear-cached-components || true
php artisan optimize
```

## 5. Apply Upload Settings

If the live admin upload settings are blank or still capped at 12 MB, set them in Site Settings or run:

```bash
php artisan tinker --execute="app(\App\Services\Settings\SettingService::class)->set('document_max_size_kb','51200','upload'); app(\App\Services\Settings\SettingService::class)->set('file_max_size_kb','51200','upload'); app(\App\Services\Settings\SettingService::class)->set('allowed_file_types','application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document','upload'); app(\App\Services\Settings\SettingService::class)->clearAllCache();"
```

## 6. Smoke Test

Check these flows before enabling traffic:

- Admin login and dashboard load without 500 errors.
- Product creation from a post relation manager creates a slug automatically.
- Product document upload accepts files up to the configured 50 MB limit.
- Lead and quote request import/export permissions are visible for the expected roles.
- Quote request mail still sends and no full customer payload is written to logs.
- R2 media URLs and upload disk behavior still work.

## 7. Enable Traffic

```bash
php artisan up
```

## 8. GitHub Release Manual Steps

1. Open the repository releases page.
2. Draft a new release from tag `v1.13.0`.
3. Use title `v1.13.0`.
4. Copy the `CHANGELOG.md` section for `1.13.0` into the release notes.
5. Publish the release.
