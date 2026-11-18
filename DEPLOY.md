# Triển khai Điều Hòa Tủ Đứng

## Cài lần đầu

Dùng tài khoản sở hữu website. Xác nhận đường dẫn hosting và PHP CLI 8.3 trở lên trong nhánh 8.x, cùng phiên bản và extension với PHP-FPM. Nginx phục vụ thư mục `/home/dieuhoatudungcom/dieuhoatudung.com/public_html/public`. Chỉ clone khi `public_html` trống hoặc chưa tồn tại.

```bash
set -e
cd /home/dieuhoatudungcom/dieuhoatudung.com
git clone https://github.com/xemthach/dieuhoatudung.git public_html
cd public_html
git fetch --tags origin
git checkout --detach v1.33.10
git rev-parse HEAD
cat VERSION
test "$(cat VERSION)" = 1.33.10
test -f public/build/manifest.json
```

Đối chiếu HEAD với commit của tag trên GitHub. Với repo riêng tư, cấp SSH deploy key chỉ đọc cho hosting và dùng URL `git@github.com:xemthach/dieuhoatudung.git`. Git đã chứa bộ build Vite, Live không cần chạy npm.

```bash
test -f .env || (umask 077 && cp .env.example .env)
```

Điền vào `.env` bằng trình soạn thảo trên server. Thay các giá trị trong ngoặc nhọn bằng thông tin của hosting.

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://dieuhoatudung.com
DB_CONNECTION=mysql
DB_HOST=<host MySQL/MariaDB>
DB_PORT=3306
DB_DATABASE=<database>
DB_USERNAME=<user>
DB_PASSWORD=<mật khẩu>
CACHE_STORE=file
SESSION_DRIVER=file
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local
```

Trên database mới, đặt `ADMIN_EMAIL`, `ADMIN_NAME` và `ADMIN_PASSWORD` riêng trước khi tạo quản trị viên. Cấu hình `MAIL_*` khi gửi SMTP, `AI_PROVIDER` và `GEMINI_API_KEY` khi dùng Gemini, `CLOUDFLARE_R2_*` khi dùng R2. Giữ bí mật trong môi trường của hosting.

Xác nhận owner và group PHP-FPM có quyền phù hợp. Cài dependency từ lock bằng Composer 2 và kiểm tra extension trên máy đích.

```bash
chmod -R ug+rwX storage bootstrap/cache
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
composer check-platform-reqs --no-dev
```

Chỉ khi cài mới và APP_KEY đang trống, chạy `php artisan key:generate --force`. Giữ nguyên khóa khi nâng cấp. Trên **database mới**, chạy lệnh khởi tạo của project bên dưới. Lệnh thực hiện migration, tạo quyền, quản trị viên, cấu hình, mẫu mail và liên kết public storage. Dữ liệu demo chỉ được thêm khi truyền tùy chọn riêng.

```bash
php artisan app:install --force
test -L public/storage && readlink -f public/storage
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan about --no-ansi
php artisan migrate:status
```

Đối chiếu liên kết với `storage/app/public`. Nếu thiếu, chạy `php artisan storage:link`. Mở `https://dieuhoatudung.com/san-pham`, kiểm tra CSS/JS tải thành công và đăng nhập `/admin`.

## Scheduler và Supervisor

Xác minh binary bằng `command -v php`. Nếu là `/usr/bin/php`, thêm cron sau cho tài khoản website. Scheduler trong `routes/console.php` theo dõi queue, thu hồi job bị kẹt và chạy các tác vụ định kỳ.

```cron
* * * * * cd /home/dieuhoatudungcom/dieuhoatudung.com/public_html && /usr/bin/php artisan schedule:run >> storage/logs/scheduler.log 2>&1
```

Trong hai chương trình Supervisor của site, đặt working directory là project root, user là owner của site, bật autostart và autorestart. Dùng lần lượt hai lệnh sau làm giá trị `command` (điều chỉnh đường dẫn PHP nếu cần).

```ini
; Worker chung
command=/usr/bin/php artisan queue:work database --queue=ai,default --sleep=3 --tries=3 --timeout=300 --memory=256 --max-time=3600
; Worker AI có kiểm soát, đặt trong chương trình Supervisor riêng
command=/usr/bin/php artisan ai:managed-worker --queue=ai_governed --sleep=3 --tries=3 --timeout=900
```

Giữ thời gian giữ chỗ của database queue dài hơn timeout worker (mặc định `DB_QUEUE_RETRY_AFTER=960`). Dùng `supervisorctl status` kiểm tra process và kiểm tra heartbeat trong Admin. Worker AI đọc trạng thái cho phép nhận job do quản trị viên đặt.

## Cập nhật site đang chạy

Sao lưu database và ghi lại tag/SHA hiện tại cùng trạng thái worker. Kiểm tra các thay đổi local trước khi checkout. Cho job đang chạy hoàn tất rồi dừng hai chương trình Supervisor của site trong cửa sổ bảo trì. Nhập tên tag đã phát hành khi được hỏi.

```bash
set -e
cd /home/dieuhoatudungcom/dieuhoatudung.com/public_html
git status --short
test -z "$(git status --porcelain)"
git rev-parse HEAD
git fetch --prune --tags origin
read -r -p 'Tag cần triển khai: ' tag
git rev-parse --verify "refs/tags/${tag}^{commit}"
php artisan down
git checkout --detach "refs/tags/$tag"
test "v$(cat VERSION)" = "$tag"
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
test -f public/build/manifest.json
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate:status
php artisan up
```

Khởi động lại đúng hai chương trình qua Supervisor, kiểm tra heartbeat và trang sản phẩm. Khi lệnh lỗi, xử lý trong cửa sổ bảo trì. Nếu cần quay lại tag cũ, kiểm tra tương thích schema trước khi khôi phục code hoặc database.
