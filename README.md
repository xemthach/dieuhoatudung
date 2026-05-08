# Điều Hòa Tủ Đứng

> CRM & E-commerce platform chuyên về điều hòa tủ đứng — Laravel monolith, Filament admin, HVAC BTU calculator.

![Version](https://img.shields.io/badge/version-1.0.0-blue)
![Laravel](https://img.shields.io/badge/Laravel-13-red)
![PHP](https://img.shields.io/badge/PHP-8.2+-purple)
![License](https://img.shields.io/badge/license-proprietary-gray)

---

## Features

### 🛒 E-commerce
- Product catalog với brands, categories, filters
- Product detail với reviews, Q&A, documents, FAQs
- Product comparison tool
- Google Merchant Feed
- Promotion management

### 📋 Lead & CRM System
- **3 lead flows:** General CTA, Product Quote (modal), BTU Consultation
- Multi-step quote form (5 bước, HVAC-specific)
- Lead scoring & classification (intent score)
- Source/UTM tracking trên tất cả forms
- GTM dataLayer integration

### 🌡️ BTU Calculator
- HVAC standard W/m² cooling load coefficients
- Hỗ trợ 7 loại không gian (nhà ở, văn phòng, showroom...)
- Điều chỉnh theo: người, ánh sáng, thiết bị sinh nhiệt
- Đề xuất sản phẩm phù hợp theo BTU

### ✉️ Mail System
- Template engine với biến động
- Admin notification + Customer confirmation
- Mail log & resend
- Visual template editor trong admin

### 🔍 SEO & Content
- Sitemap tự động (products, posts, categories)
- Schema.org structured data (Product, Article, FAQ, Breadcrumb, Organization)
- AI content generation (Gemini API)
- Internal link suggestions
- Redirect 301/302 management
- Blog/content management
- Case studies module

### 🛡️ Security
- Role-based access control (5 roles, 130 permissions)
- Honeypot spam protection trên tất cả forms
- Rate limiting (5-10 requests/hour per IP)
- CSRF protection
- No secrets in codebase

---

## Tech Stack

| Component | Technology |
|---|---|
| Framework | Laravel 13.7 |
| PHP | 8.2+ |
| Admin Panel | Filament v5 |
| Frontend | Blade + Alpine.js |
| CSS | Tailwind CSS v4 |
| Database | MySQL 8 |
| Queue | Database driver |
| Cache | File driver |
| RBAC | Spatie Permission |
| AI | Gemini API |
| CDN/Storage | Cloudflare R2 (S3-compatible) |

---

## Quick Start (Local Development)

### Requirements
- PHP 8.2+ | MySQL 8+ | Composer 2+ | Node.js 20+

### Setup

```bash
# Clone
git clone https://github.com/xemthach/dieuhoatudung.git
cd dieuhoatudung

# Install dependencies
composer install
npm install

# Configure
cp .env.example .env
# Edit .env: set DB_DATABASE, DB_USERNAME, DB_PASSWORD

# One-command setup
php artisan app:install --with-demo

# Dev server
npm run dev
```

### Access
- **Website:** http://dieuhoa-tudung.test
- **Admin:** http://dieuhoa-tudung.test/admin
- **Login:** `admin@dieuhoa.vn` / password from `ADMIN_PASSWORD` in `.env`

---

## Production Deployment

### First Install

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
cp .env.example .env
# Edit .env with production values
php artisan app:install --force
```

### Update Existing Server

```bash
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan db:seed --class=RolePermissionSeeder --force
npm ci && npm run build
php artisan optimize:clear
```

> 📖 See [DEPLOYMENT.md](DEPLOYMENT.md) for full deployment guide, nginx config, queue workers, rollback, and troubleshooting.

---

## Required Environment Variables

```env
# App
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

# Database
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=

# Admin (used by seeders)
ADMIN_NAME="Super Admin"
ADMIN_EMAIL=admin@example.com
ADMIN_PASSWORD=          # REQUIRED for production

# Mail
MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=
```

---

## Project Structure

```
app/
├── Console/Commands/     # Artisan commands (app:install, permissions:sync)
├── Enums/                # PHP Enums (LeadStatus, LandingSectionType...)
├── Filament/             # Admin panel resources, pages, widgets
├── Http/
│   ├── Controllers/      # Web controllers (Quote, BTU, Product, Landing...)
│   ├── Middleware/        # Redirects, CORS
│   └── Requests/         # Form request validation
├── Models/               # Eloquent models (Product, Lead, QuoteRequest...)
├── Services/
│   ├── Calculator/       # BTU calculator service
│   ├── Mail/             # Mail dispatch & template engine
│   ├── SEO/              # SEO services
│   └── Schema/           # JSON-LD schema generators
└── View/Components/      # Blade view components

config/
├── permissions.php       # RBAC permission registry (single source of truth)
└── ...

database/
├── migrations/           # 55+ migrations
├── seeders/
│   ├── DatabaseSeeder      # Orchestrator (base + conditional demo)
│   ├── RolePermissionSeeder # 130 permissions, 5 roles
│   ├── AdminUserSeeder     # Admin user from env
│   ├── SiteSettingSeeder   # 60+ default settings
│   ├── MailTemplateSeeder  # 9 email templates
│   └── DemoDataSeeder     # Sample data (local/testing only)
└── factories/            # 14 model factories
```

---

## Artisan Commands

| Command | Description |
|---|---|
| `php artisan app:install` | First-time setup (migrate, seed, storage link) |
| `php artisan app:install --with-demo` | Setup + seed demo data |
| `php artisan permissions:sync --apply` | Sync permissions from config |

---

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for version history.

## License

Proprietary. All rights reserved.
