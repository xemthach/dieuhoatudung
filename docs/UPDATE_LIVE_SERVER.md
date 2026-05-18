# Live Server Update Guide

Use this guide after the release commit, tag, and GitHub release are published.

Current release: `v1.20.0`

Affected areas:

- Home Benefits device targeting
- FAQ admin search and FAQPage schema output
- Site Campaigns / Popups admin, frontend rendering, and tracking
- Product card mobile action UI
- Product AI metadata length safety
- Product, quote, lead, import/export, R2, and mail admin flows
- Frontend Vite/Tailwind built assets

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
- Home Benefits, FAQ, and Site Campaigns admin pages load.
- Product list and product detail pages load.
- Lead and quote forms submit.
- Import/export, R2/CDN Sync, and mail logs still load.

---

## 2. Pull Release

```bash
git fetch origin --tags
git checkout main
git pull --ff-only origin main
git checkout v1.20.0
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

- `home_benefit_items.display_device` for desktop/mobile/both targeting
- `faqs.normalized_search_text` with a backfill for accent-insensitive FAQ search
- `site_campaigns` for popup, announcement, floating CTA, and site campaign configuration
- `site_campaign_events` for impression, click, close, and conversion tracking

If a previous deploy showed `site_campaigns` missing in `/admin/site-campaigns`, this step is mandatory.

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

## 6. Queue Workers

Confirm queue configuration:

```bash
php artisan tinker --execute="dump([
  'queue' => config('queue.default'),
  'queued_jobs' => DB::table('jobs')->count(),
  'failed_jobs' => DB::table('failed_jobs')->count(),
]);"
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

---

## 7. Smoke Test Before Enabling Traffic

Run automated checks:

```bash
php artisan test
npm run build
```

Manual admin checks:

- Login to `/admin`.
- Open Landing & Pages > Home Benefits and confirm the `Hiển thị trên thiết bị` field and device badges render.
- Open Landing & Pages > FAQ and confirm searching `bao hanh` can match FAQ content normalized from Vietnamese text.
- Open Landing & Pages > Site Campaigns and confirm the list page loads even when there are no campaigns.
- Create a draft Site Campaign, keep it inactive/draft, and confirm it does not show on the frontend.
- Open Products and confirm Product AI status columns still render.
- Submit one quote request and one lead form.
- Open Import/Export and confirm unauthorized roles cannot run restricted actions.
- Open R2/CDN Sync and confirm the page loads.
- Send or queue one test email and confirm Mail Logs still work.

Manual frontend checks:

- Visit `/`, `/san-pham`, one `/danh-muc/{slug}`, one product detail page, and `/tim-kiem?q=36000`.
- On mobile width below 640px, product cards must show a blue eye icon for detail and the compare icon beside it.
- On desktop/tablet width 640px and above, product cards must still show the text button `Xem chi tiết`.
- Confirm Site Campaigns do not display unless a matching active campaign exists.

Log checks:

```bash
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

Prefer restoring the SQL backup for this release because new campaign tables and FAQ/Home Benefits columns are introduced.

Code rollback:

```bash
git fetch origin --tags
git checkout v1.19.0
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan optimize:clear
php artisan queue:restart
```

Database rollback is not recommended if new Site Campaign records or campaign events were created after deploy. Restore the SQL backup for a full rollback.

---

## 10. GitHub Release Manual Steps

1. Open the repository releases page.
2. Draft a new release from tag `v1.20.0`.
3. Use title `v1.20.0`.
4. Copy the `CHANGELOG.md` section for `1.20.0` into the release notes.
5. Publish the release.
