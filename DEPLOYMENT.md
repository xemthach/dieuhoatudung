# Deployment Guide — Dieu Hoa Tu Dung

## Requirements

- PHP 8.2+
- MySQL 8.0+
- Composer 2.x
- Node.js 20+ & npm
- Git

---

## A. First-Time Install (New Server)

### 1. Clone repository

```bash
cd /var/www
git clone https://github.com/xemthach/dieuhoatudung.git dieuhoa-tudung
cd dieuhoa-tudung
```

### 2. Install dependencies

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
```

### 3. Configure environment

```bash
cp .env.example .env
nano .env
```

**Required `.env` values:**

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_DATABASE=your_db_name
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password

ADMIN_NAME="Super Admin"
ADMIN_EMAIL=admin@your-domain.com
ADMIN_PASSWORD=YourStrongPassword!2026

MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=your@email.com
MAIL_PASSWORD=your-mail-password
MAIL_FROM_ADDRESS=noreply@your-domain.com
MAIL_FROM_NAME="Dieu Hoa Tu Dung"
```

### 4. Run install command

```bash
php artisan app:install --force
```

This runs all 9 steps automatically:
1. Check `.env`
2. Generate `APP_KEY`
3. Run migrations
4. Seed roles & permissions (130 permissions, 5 roles)
5. Create admin user (from env)
6. Seed site settings
7. Seed mail templates
8. Create storage symlink
9. Clear all caches

### 5. Set permissions

```bash
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

### 6. Configure web server (Nginx example)

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/dieuhoa-tudung/public;

    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

### 7. Login

- URL: `https://your-domain.com/admin`
- Email: (from `ADMIN_EMAIL` in .env)
- Password: (from `ADMIN_PASSWORD` in .env)

---

## B. Update Existing Server

### Quick update (most common)

```bash
cd /var/www/dieuhoa-tudung

# 1. Pull latest code
git pull origin main

# 2. Install new dependencies (if any)
composer install --no-dev --optimize-autoloader

# 3. Run new migrations
php artisan migrate --force

# 4. Update permissions/roles (if changed)
php artisan db:seed --class=RolePermissionSeeder --force

# 5. Update mail templates (if changed)
php artisan db:seed --class=MailTemplateSeeder --force

# 6. Rebuild assets (if frontend changed)
npm ci && npm run build

# 7. Clear all caches
php artisan optimize:clear
```

### One-liner update (if no new dependencies)

```bash
cd /var/www/dieuhoa-tudung && git pull origin main && php artisan migrate --force && php artisan optimize:clear
```

### Update for v1.15.0 AI Product Content release

Use this flow when updating a live server from v1.14.0 or earlier to v1.15.0:

```bash
cd /var/www/dieuhoa-tudung

# 1. Optional: enter maintenance mode during the code swap
php artisan down --secret="deploy-preview"

# 2. Fetch and deploy the tagged release
git fetch origin --tags
git checkout main
git pull --ff-only origin main
git checkout v1.15.0

# 3. Install backend and frontend dependencies
composer install --no-dev --optimize-autoloader
npm ci
npm run build

# 4. Run database updates and permissions
php artisan migrate --force
php artisan db:seed --class=RolePermissionSeeder --force

# 5. Refresh caches
php artisan optimize:clear
php artisan filament:clear-cached-components || true
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 6. Restart queue workers so AI product and blog jobs use the new code
php artisan queue:restart
sudo supervisorctl restart dieuhoa-worker:* || true

# 7. Bring the site back online
php artisan up
```

After deployment, verify:

- Product list shows AI Status, SEO Score, Last AI Run, and Warning Count.
- AI Product Jobs page loads and job items render.
- A single-product AI draft creates a queued job instead of blocking the browser.
- AI Content Job statuses render as completed, completed with warnings, needs review, or blocked.
- Lead and quote forms submit successfully.
- Import/Export permissions still block unauthorized users.
- R2/CDN Sync page loads.
- Mail Logs page loads and a test mail can be sent or queued.
- `/dieu-hoa-tu-dung` and `/bao-gia` display Vietnamese with accents and no placeholder copy.

For full details, see `docs/UPDATE_LIVE_SERVER.md` and `docs/AI_MODULE_QUEUE_SUPERVISOR.md`.

### Update for v1.14.0 AI content release

Use this flow when updating a live server from v1.13.1 or earlier to v1.14.0:

```bash
cd /var/www/dieuhoa-tudung

# 1. Optional: enter maintenance mode during the code swap
php artisan down --secret="deploy-preview"

# 2. Fetch and deploy the tagged release
git fetch origin --tags
git checkout main
git pull origin main
git checkout v1.14.0

# 3. Install backend and frontend dependencies
composer install --no-dev --optimize-autoloader
npm ci
npm run build

# 4. Run database updates and refresh caches
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 5. Restart queue workers so AI content jobs use the new engine
php artisan queue:restart

# 6. Bring the site back online
php artisan up
```

After deployment, verify these admin workflows:

- AI Providers: test the active ShopAIKey/OpenAI-compatible provider.
- AI Content Job: create one test job and confirm it moves from pending to completed or failed with a clear validation message.
- Lead and quote forms: submit one test lead and one quote request.
- Import/Export: open the import/export admin screen and confirm it loads.
- R2/CDN Sync: open the R2/CDN Sync admin screen and confirm existing sync records load.
- Mail: send or queue one test email and confirm it appears in Mail Logs.

---

## C. Rollback

### Rollback last migration

```bash
php artisan migrate:rollback --step=1 --force
```

### Rollback to specific commit

```bash
git log --oneline -10          # Find the commit to rollback to
git checkout <commit-hash>     # Switch to that commit
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
```

---

## D. Maintenance Mode

```bash
# Enable maintenance mode
php artisan down --secret="your-bypass-token"

# Access site during maintenance:
# https://your-domain.com/your-bypass-token

# Disable maintenance mode
php artisan up
```

---

## E. Queue Worker (if using database queue)

For AI modules, queue worker setup is required. See the detailed guide:

- [AI Module Queue + Supervisor Setup](docs/AI_MODULE_QUEUE_SUPERVISOR.md)

```bash
# Start queue worker
php artisan queue:work --queue=default --sleep=3 --tries=3 --max-time=3600

# With supervisor (recommended for production)
# /etc/supervisor/conf.d/dieuhoa-worker.conf
```

```ini
[program:dieuhoa-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/dieuhoa-tudung/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/dieuhoa-tudung/storage/logs/worker.log
stopwaitsecs=3600
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start dieuhoa-worker:*
```

---

## F. Troubleshooting

| Issue | Solution |
|---|---|
| 500 error after deploy | `php artisan optimize:clear` then check `storage/logs/laravel.log` |
| Permission denied | `chmod -R 775 storage bootstrap/cache` |
| Admin can't login | `php artisan db:seed --class=AdminUserSeeder --force` |
| Changed ADMIN_PASSWORD but can't login | Must re-run: `php artisan db:seed --class=AdminUserSeeder --force` |
| Missing permissions/roles | `php artisan db:seed --class=RolePermissionSeeder --force` |
| Missing settings | `php artisan db:seed --class=SiteSettingSeeder --force` |
| Missing mail templates | `php artisan db:seed --class=MailTemplateSeeder --force` |
| CSS/JS not loading | `npm run build` then `php artisan optimize:clear` |
| Storage images broken | `php artisan storage:link` |
| Livewire JS 404 / MIME mismatch | `php artisan livewire:publish --assets && php artisan optimize:clear` |
| Admin page blank / no interactivity | Livewire assets issue — see Cloudflare section below |
| Login page shows but can't submit | Check Livewire routes: `php artisan route:list --name=livewire` |

---

## G. Nginx Config (Reference)

```nginx
server {
    listen 443 ssl;
    server_name dieuhoatudung.com;

    # IMPORTANT: root must point to /public
    root /home/dieuhoatudung.com/public_html/public;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

> **Key:** `root` must point to the `public/` subdirectory, NOT the project root.

---

## H. Cloudflare Notes

### After Every Deploy

Cloudflare caches static assets aggressively. After deploying new CSS/JS:

1. Go to **Cloudflare Dashboard** → domain → **Caching** → **Purge Everything**
2. Or enable **Development Mode** temporarily (bypasses cache for 3 hours)

### Livewire + Cloudflare

Livewire v3 uses hashed route prefixes (e.g., `livewire-3ba6fe55/`). If Livewire stops working:

```bash
# 1. Publish Livewire static assets
php artisan livewire:publish --assets

# 2. Clear Laravel cache
php artisan optimize:clear

# 3. Purge Cloudflare cache (from dashboard or API)
```

### Cloudflare Page Rules (Recommended)

| URL Pattern | Setting |
|---|---|
| `dieuhoatudung.com/admin/*` | Cache Level: Bypass |
| `dieuhoatudung.com/livewire*` | Cache Level: Bypass |

This prevents Cloudflare from caching admin/Livewire routes.

---

## I. Changing Admin Password

Simply editing `.env` does NOT update the database. You must re-seed:

```bash
# 1. Edit .env
nano .env
# Change: ADMIN_PASSWORD=NewPassword123!

# 2. Re-run admin seeder
php artisan db:seed --class=AdminUserSeeder --force

# 3. Login with new password
```

---

## J. Important Notes

> **NEVER** run `migrate:fresh` on production — it drops ALL tables!

> **NEVER** commit `.env` to Git — it contains secrets.

> Always use `--force` flag for `migrate` and `db:seed` on production.

> After every deploy, run `php artisan optimize:clear`.

> After every deploy with CSS/JS changes, **purge Cloudflare cache**.
