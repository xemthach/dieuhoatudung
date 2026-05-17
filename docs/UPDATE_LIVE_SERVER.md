# Live Server Update Guide

Use this guide after the release commit, tag, and GitHub release are published.

Current release: `v1.18.0`

Affected areas:

- Product AI content generation and audit jobs
- Blog AI content governance and fact checking
- Product AI governance rule engine, verified fact registry, and real-time status polling
- Product VAT display flag and VAT-aware AI fact checking
- Product, quote, lead, import/export, R2, and mail admin flows
- Public Vietnamese/UTF-8 copy cleanup
- Queue worker configuration for long AI jobs

---

## 1. Backup

```bash
cd /path/to/dieuhoa-tudung
php artisan down --secret="deploy-preview"
php artisan backup:run || true
mysqldump -u DB_USER -p DB_NAME > backup-$(date +%F-%H%M).sql
```

Keep the SQL backup until these checks pass:

- Admin dashboard loads.
- Product list loads with AI columns.
- AI Product Job page loads.
- Lead and quote forms submit.
- Import/export, R2/CDN Sync, and mail logs still load.

---

## 2. Pull Release

```bash
git fetch origin --tags
git checkout main
git pull --ff-only origin main
git checkout v1.18.0
```

If the server should track `main` instead of a tag, stop after:

```bash
git pull --ff-only origin main
```

---

## 3. Install Dependencies and Assets

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
```

This release includes a rebuilt Vite asset manifest. Do not skip `npm run build` on a server that builds assets locally.

---

## 4. Run Database Updates

```bash
php artisan migrate --force
php artisan db:seed --class=RolePermissionSeeder --force
```

The migrations add:

- Product AI status, score, warning count, timestamps, and error fields
- AI product job tables
- AI product content version backups
- Product `price_includes_vat` flag for admin-controlled VAT display and AI VAT governance
- Cleanup for old public content that had unaccented Vietnamese or placeholder quote commitment text

If a previous deploy showed `ai_product_jobs` missing, this step is mandatory.

---

## 5. Clear and Warm Caches

```bash
php artisan optimize:clear
php artisan filament:clear-cached-components || true
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

If any route/cache command fails because of environment-specific settings, run:

```bash
php artisan optimize:clear
```

and keep the site uncached until the issue is corrected.

---

## 6. Queue Worker for AI Modules

AI content jobs must be processed by a real queue worker. Confirm:

```bash
php artisan tinker --execute="dump([
  'queue' => config('queue.default'),
  'queued_jobs' => DB::table('jobs')->count(),
  'failed_jobs' => DB::table('failed_jobs')->count(),
]);"
```

Expected queue connection for this project:

```env
QUEUE_CONNECTION=database
```

Restart workers after deploy:

```bash
php artisan queue:restart
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl restart dieuhoa-worker:* || true
sudo supervisorctl status
```

If Supervisor is not installed or the worker is not running, follow:

- `docs/AI_MODULE_QUEUE_SUPERVISOR.md`

Do not rely on cron as the primary AI queue worker.

---

## 7. Smoke Test Before Enabling Traffic

Run these checks while maintenance mode is still enabled, using the secret URL if needed:

```bash
php artisan test --testsuite=Feature
```

Manual admin checks:

- Login to `/admin`.
- Open Products and confirm AI Status, SEO Score, Last AI Run, and Warning Count columns render.
- Click `Refresh AI Status` and confirm AI status updates without reloading the whole page.
- Edit a product and confirm `Giá đã bao gồm VAT` can be toggled; verify the public product page shows the VAT badge.
- Trigger a single-product AI draft in preview mode and confirm it creates a queued job, not a long web request.
- Open AI Product Jobs and confirm job item rows render.
- Open AI Content Job and confirm statuses render without enum errors.
- Submit one quote request and one lead form.
- Open Import/Export and confirm unauthorized roles cannot run restricted actions.
- Open R2/CDN Sync and confirm the page loads.
- Send or queue one test email and confirm Mail Logs still work.
- Visit `/dieu-hoa-tu-dung` and `/bao-gia`; public copy must be Vietnamese with accents and must not show placeholder text.

Queue checks:

```bash
php artisan tinker --execute="dump(DB::table('jobs')->select('id','queue','attempts','reserved_at','available_at','created_at')->orderByDesc('id')->limit(10)->get()->toArray());"
tail -n 150 storage/logs/laravel.log
tail -n 150 storage/logs/queue-worker.log || true
```

---

## 8. Enable Traffic

```bash
php artisan up
```

Then hard-refresh public pages and admin pages in the browser.

---

## 9. Rollback

Prefer restoring the SQL backup for this release because new tables and product AI metadata are introduced.

Code rollback:

```bash
git fetch origin --tags
git checkout v1.14.0
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan optimize:clear
php artisan queue:restart
```

Database rollback is not recommended unless you have confirmed no AI product content jobs have been created after deploy. Restore the SQL backup if you need a full rollback.

---

## 10. GitHub Release Manual Steps

1. Open the repository releases page.
2. Draft a new release from tag `v1.18.0`.
3. Use title `v1.18.0`.
4. Copy the `CHANGELOG.md` section for `1.18.0` into the release notes.
5. Publish the release.
