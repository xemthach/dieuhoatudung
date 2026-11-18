# Triển khai Điều Hòa Tủ Đứng

## Cài lần đầu

Dùng tài khoản sở hữu website. Xác nhận đường dẫn hosting và PHP CLI 8.3 trở lên trong nhánh 8.x, cùng phiên bản và extension với PHP-FPM. Nginx phục vụ thư mục `/home/lps_chonmaylanhcom/public_html/public`. Chỉ clone khi `public_html` trống hoặc chưa tồn tại.

```bash
set -e
cd /home/lps_chonmaylanhcom
git clone https://github.com/xemthach/dieuhoatudung.git public_html
cd public_html
git fetch --tags origin
git checkout --detach v1.33.11
git rev-parse HEAD
cat VERSION
test "$(cat VERSION)" = 1.33.11
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
APP_URL=https://chonmaylanh.com
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

Chạy Composer và các lệnh Artisan tạo cache bằng tài khoản site `lps_chonmaylanhcom`. Nếu trước đó đã cài bằng `root`, kiểm tra quyền theo mục xử lý HTTP 500 bên dưới. Cài dependency từ lock bằng Composer 2 và kiểm tra extension trên máy đích.

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

Đối chiếu liên kết với `storage/app/public`. Nếu thiếu, chạy `php artisan storage:link`. Mở `https://chonmaylanh.com/san-pham`, kiểm tra CSS/JS tải thành công và đăng nhập `/admin`.

## Dữ liệu riêng cho đối chiếu catalogue

Chức năng duyệt taxonomy và công suất cần hồ sơ PDF của đúng bộ sản phẩm. Trên máy vận hành có Python, PyMuPDF và PHP CLI kết nối đúng database, đặt năm PDF đã duyệt trong cây `data dieu hoa/DAIKIN AIR` và `data dieu hoa/GREE AIR`. Đặt hai tệp xét duyệt `daikin_room_air_pages_23_24_reviewed.json` và `product_identity_conflicts_reviewed.json` trong `storage/app/private/catalog-evidence/`. Operator phải đối chiếu model và ID với database đích vì sổ xung đột tham chiếu ID sản phẩm.

Chạy `python scripts/build_catalog_source_coverage.py` từ project root. Công cụ đọc danh mục qua Artisan và tạo `storage/app/private/reports/marketing_capacity_pdf_source_coverage.json`. Giữ báo cáo cùng hồ sơ nguồn trong khu vực riêng; dịch vụ Gree đối chiếu SHA-256 của PDF trước khi chấp nhận nguồn. Khi thiếu hồ sơ, tạm dừng bước duyệt dữ liệu. Trang sản phẩm và form đăng nhập có thể kiểm tra độc lập.

## Scheduler và Supervisor

Xác minh binary bằng `command -v php`. Nếu là `/usr/bin/php`, thêm cron sau cho tài khoản website. Scheduler trong `routes/console.php` theo dõi queue, thu hồi job bị kẹt và chạy các tác vụ định kỳ.

```cron
* * * * * cd /home/lps_chonmaylanhcom/public_html && /usr/bin/php artisan schedule:run >> storage/logs/scheduler.log 2>&1
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

Dùng tài khoản site cho các lệnh Composer và Artisan dưới đây. Sao lưu database và ghi lại tag/SHA hiện tại cùng trạng thái worker. Kiểm tra các thay đổi local trước khi checkout. Cho job đang chạy hoàn tất rồi dừng hai chương trình Supervisor của site trong cửa sổ bảo trì. Nhập tên tag đã phát hành khi được hỏi.

```bash
set -e
cd /home/lps_chonmaylanhcom/public_html
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

## HTTP 500 sau khi chạy Artisan bằng root

Trên `chonmaylanh.com`, cả `/up` và `/` từng trả HTTP 500 dù Artisan chạy được bằng `root`. Nginx đã trỏ đúng vào `/home/lps_chonmaylanhcom/public_html/public`. File `.env` có quyền `600 root:root`, còn `storage/logs` và `bootstrap/cache` là `775 root:root`. PHP-FPM báo `storage/logs/laravel.log ... Permission denied` trong error log của Nginx. Vì vậy, ngay cả khi chưa có Laravel log, vẫn có thể tìm được lỗi ở phía Nginx.

**Kiểm tra trên server** bằng tài khoản quản trị. Gọi thẳng origin để giữ đúng hostname HTTPS, sau đó đối chiếu thời điểm request với error log.

```bash
cd /home/lps_chonmaylanhcom/public_html || exit 1
date -u
curl --resolve chonmaylanh.com:443:127.0.0.1 -sS --max-time 10 \
  -o /dev/null -w 'Origin /up: HTTP %{http_code}\n' https://chonmaylanh.com/up
ps -eo user:32,group:32,args | grep '[p]hp-fpm: pool lps_chonmaylanhcom'
stat -c '%A %U:%G %n' .env storage/logs bootstrap/cache
tail -n 20 /var/log/nginx/chonmaylanh.com_error.log
```

**Sửa quyền** khi đã xác nhận user và group của pool đều là `lps_chonmaylanhcom` và log báo lỗi quyền như trên. Chạy khối sau bằng `root`. Quyền `640` cho phép pool đọc `.env`. Bit setgid trên các thư mục runtime giúp file mới kế thừa group của site.

```bash
(
  set -e
  cd /home/lps_chonmaylanhcom/public_html
  pool=lps_chonmaylanhcom
  chgrp "$pool" .env
  chmod 640 .env
  chgrp -R "$pool" storage bootstrap/cache
  chmod -R g+rwX storage bootstrap/cache
  find storage bootstrap/cache -type d -exec chmod g+s {} +
  runuser -u "$pool" -- sh -ec '
    test -r .env
    test -r vendor/autoload.php
    test -w storage/logs
    test -w storage/framework/views
    test -w bootstrap/cache
    echo "Quyền PHP-FPM OK"
  '
)
```

**Xác minh sau sửa.** Hai request trực tiếp tới origin và ba request qua domain công khai dưới đây đều cần trả HTTP 200. Nếu còn 500, đối chiếu lỗi mới nhất trong `storage/logs/laravel*.log` và error log Nginx với thời điểm gọi lại.

```bash
for path in /up /; do
  curl --resolve chonmaylanh.com:443:127.0.0.1 -sS --max-time 10 \
    -o /dev/null -w "Origin $path HTTP %{http_code}\n" "https://chonmaylanh.com$path"
done
for path in /up / /san-pham; do
  curl -sS --max-time 10 -o /dev/null -w "Public $path HTTP %{http_code}\n" \
    "https://chonmaylanh.com$path"
done
```

## Form đăng nhập hiện nhưng bấm đăng nhập chưa vào được

Mở DevTools → Network, tải lại `/admin/login` và tìm `livewire.min.js`. Trên `chonmaylanh.com` từng ghi nhận HTML và CSS Filament trả 200, còn script này trả 404 với trang lỗi Nginx. Filament dùng script để gửi form bằng POST tới endpoint Livewire. Kiểm tra script trước khi xử lý tài khoản.

Tại project root trên Live, liệt kê các route và kiểm tra bundle Composer:

```bash
php artisan route:list --path=livewire
test -s vendor/livewire/livewire/dist/livewire.min.js && echo 'Bundle OK'
```

Sao chép **đường dẫn và query string thực tế** của script từ Network. Tiền tố `livewire-…` thay đổi theo APP_KEY. Chạy GET qua origin và qua domain để so sánh:

```bash
read -r -p 'Đường dẫn script từ Network (bắt đầu bằng /): ' path
curl --resolve chonmaylanh.com:443:127.0.0.1 -sS -o /dev/null \
  -w 'Origin HTTP %{http_code} %{content_type}\n' "https://chonmaylanh.com$path"
curl -sS -o /dev/null -w 'Public HTTP %{http_code} %{content_type}\n' \
  "https://chonmaylanh.com$path"
```

Nếu route của CLI khớp với HTML, bundle tồn tại và **origin** vẫn trả trang 404 của Nginx, kiểm tra cấu hình vhost cùng các include bằng `nginx -T`. Khi xác nhận rule file tĩnh bắt mọi URL kết thúc bằng `.js`, ưu tiên chuyển prefix động của Livewire tới front controller trong vhost này:

```nginx
location ^~ /livewire- {
    rewrite ^ /index.php last;
}
```

Sau khi operator kiểm tra `nginx -t` và reload cấu hình, chạy lại hai GET. Cả hai phải trả 200 với MIME JavaScript. Nếu origin đã đúng nhưng public còn trả 404, kiểm tra cache của URL đó tại CDN. Nếu prefix trong HTML khác route CLI, đối chiếu release, cấu hình đã cache và APP_KEY đang dùng bởi CLI và PHP-FPM. Giữ nguyên APP_KEY hiện hữu.

Khi script đã tải được, dùng tài khoản được cấp để kiểm tra POST `/livewire-…/update`, redirect tới `/admin` và tải lại dashboard. Nếu POST trả 419, kiểm tra cookie phiên trên đúng hostname/HTTPS và quyền ghi session (`SESSION_DRIVER=file`) hoặc bảng `sessions` (`database`). Khi form báo sai thông tin đăng nhập, có thể kiểm tra tài khoản hiện hữu ở chế độ chỉ đọc:

```bash
php artisan tinker
```

```php
$u = App\Models\User::where('email', '<email quản trị>')->first();
[$u !== null, $u?->is_active, filled($u?->getAuthPassword()), $u?->getRoleNames()];
```

Kết quả thứ ba chỉ xác nhận đã lưu trường mật khẩu. Panel dùng guard phiên `web`, model User kiểm tra `is_active`, còn role/permission quyết định quyền theo chức năng. Chỉ dùng `app:install --force` cho lần khởi tạo database mới. AdminUserSeeder có thể cập nhật hash và đồng bộ role của email đã tồn tại.
