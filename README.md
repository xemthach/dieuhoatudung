# Điều Hòa Tủ Đứng - Website

Website chuyên sâu về điều hòa tủ đứng, xây dựng trên Laravel monolith.

## Yêu cầu hệ thống

- Laragon (PHP 8.2+, MySQL 8+, Apache/Nginx)
- Node.js 18+
- Composer 2+

## Cài đặt local (Laragon)

### 1. Clone và cài đặt dependencies

```bash
cd d:\laragon\www\dieuhoa-tudung
composer install
npm install
```

### 2. Cấu hình môi trường

File `.env` đã được cấu hình sẵn cho Laragon:

- **Fake domain:** `dieuhoa-tudung.test`
- **Database:** `dieuhoa-tudung`
- **DB User:** `root` (password trống)

### 3. Tạo database

```bash
mysql -u root -e "CREATE DATABASE IF NOT EXISTS `dieuhoa-tudung` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

### 4. Chạy migrations

```bash
php artisan migrate
```

### 5. Tạo storage link

```bash
php artisan storage:link
```

### 6. Build assets

```bash
npm run build
```

Hoặc chạy dev server cho hot-reload:

```bash
npm run dev
```

### 7. Truy cập

- **Website:** http://dieuhoa-tudung.test
- **Admin:** http://dieuhoa-tudung.test/admin
- **Admin login:** admin@dieuhoa-tudung.test / admin123

## Stack

| Component | Technology |
|-----------|-----------|
| Framework | Laravel 13 |
| Template | Blade |
| CSS | Tailwind CSS v4 |
| Admin | Filament v5 |
| Database | MySQL 8 |
| Queue | Database driver |
| Cache | File driver |
| AI | Gemini API |
| CDN | Cloudflare R2 (S3-compatible) |

## Cấu trúc thư mục

```
app/
  Actions/          # Business logic actions
  Data/             # Data transfer objects
  Enums/            # PHP Enums
  Filament/         # Filament admin resources
  Http/Controllers/ # Web controllers
  Jobs/             # Queue jobs
  Models/           # Eloquent models
  Services/
    SEO/            # SEO services
    Schema/         # JSON-LD schema
    Gemini/         # AI content generation
    Media/          # Media management
    Sitemap/        # Sitemap generation
  View/Components/  # Blade view components

config/
  seo.php           # SEO configuration
  schema.php        # Schema.org configuration
  media.php         # Media/R2 configuration
  gemini.php        # Gemini AI configuration

resources/views/
  components/       # Blade components
  layouts/          # Layout templates
  pages/            # Page templates
  products/         # Product templates
  categories/       # Category templates
  blog/             # Blog templates
  partials/         # Partial includes
```

## Phases triển khai

- [x] Phase 1: Setup nền tảng local
- [x] Phase 2: Database, models, migrations
- [x] Phase 3: Filament Admin
- [x] Phase 4: Frontend foundation
- [x] Phase 5: Landing page
- [x] Phase 6: Product catalog và detail
- [x] Phase 7: Blog SEO hub
- [x] Phase 8: AI Blog Gemini
- [x] Phase 9: SEO technical
- [x] Phase 10: QA và hoàn thiện

## License

Proprietary. All rights reserved.
