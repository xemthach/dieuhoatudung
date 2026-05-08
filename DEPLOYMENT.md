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
| Missing permissions | `php artisan db:seed --class=RolePermissionSeeder --force` |
| Missing settings | `php artisan db:seed --class=SiteSettingSeeder --force` |
| Missing mail templates | `php artisan db:seed --class=MailTemplateSeeder --force` |
| CSS/JS not loading | `npm run build` then `php artisan optimize:clear` |
| Storage images broken | `php artisan storage:link` |

---

## G. Important Notes

> **NEVER** run `migrate:fresh` on production — it drops ALL tables!

> **NEVER** commit `.env` to Git — it contains secrets.

> Always use `--force` flag for `migrate` and `db:seed` on production.

> After every deploy, run `php artisan optimize:clear`.
