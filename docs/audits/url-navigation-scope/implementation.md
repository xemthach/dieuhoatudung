# Audit URL, điều hướng và phạm vi sản phẩm

Baseline ngày 23/11/2026: VERSION `1.33.15`; HEAD và origin/main cùng
`c971dfc2b047a8a0623f73cf0f7486de2008cd1e`; tag remote mới nhất `v1.33.15`.
Sau fetch, ahead/behind = `0/0`. Worktree đã có 41 file tracked thay đổi và
4 file untracked liên quan trước khi tiếp tục công việc. Không merge/rebase.
Các con số audit so sánh **HEAD với kết quả cuối**, bao gồm phần sửa đã có sẵn.

## Compare: nguyên nhân và hợp đồng

Ở HEAD, nút X trong `resources/views/pages/compare.blade.php` là form POST
đúng route và có CSRF, nhưng không có handler submit async. Form được submit
bình thường. `CompareController::remove()` luôn trả JSON bất kể client yêu cầu
HTML hay JSON. Phân loại: **FORM_SUBMITS_NORMALLY + JS_HANDLER_NOT_BOUND +
MIXED_HTML_JSON_ENDPOINT**. Không phải anchor và không phải state mutation GET.

Đã chứng minh regression bằng cách chạy test fallback với controller HEAD,
khôi phục ngay controller worktree trong `finally`: kỳ vọng redirect nhưng nhận
HTTP 200; exit 1. Bằng chứng: `storage/logs/url-scope-red-repro.txt`.

Luồng cuối:

```text
Product card / Product detail -> addToCompare
Compare table -> form submit + preventDefault
Sticky bar -> removeItem / clearAll
    -> resources/js/compare.js: updateComparison
    -> csrfFetch, POST, Accept JSON, CSRF
    -> CompareController
    -> ProductCompareSession
    -> ProductComparisonService::getProducts
    -> session compare.products (slug canonical, tối đa 4, active, không trùng)
    -> JSON {success, message, count, items}
    -> compare:updated -> cập nhật bảng, export links, count, sticky bar, storage
```

`localStorage.compare_items` chỉ là cache trình bày. Session server là nguồn
chính. Add/remove/clear dùng chung một hợp đồng và khóa pending trên frontend.
Remove nhận slug hoặc product_id nguyên dương có thật; add yêu cầu sản phẩm active.
Request HTML thành công redirect đến named route Compare và ghi flash status.
GET các mutation endpoint chỉ redirect, không đổi session. Validation và CSRF
không xóa state khi lỗi. Khi request lỗi, giữ bảng và mở lại controls; khi đang
pending, controls disabled/aria-busy, chặn submit trùng. Không dùng reload để
cập nhật Compare, kể cả lỗi CSRF sau lần refresh token.

Xóa cột làm mới số lượng, highlight khác biệt, colspan và danh sách export;
xóa món cuối hiển thị empty state hiện có. Sticky bar mở trang Compare được
khi có một sản phẩm. Nhãn X có tên sản phẩm để hỗ trợ trình đọc màn hình.

## Scope và URL

`SiteProductScope` phụ trách phạm vi và nội dung; `PublicNavigationResolver`
phụ trách phân giải đích. Named routes Laravel vẫn cố định, không tạo path từ DB.

| Cấu hình | Hành vi |
|---|---|
| site.product_scope = all_products | Nội dung chung: So sánh sản phẩm / Cần tư vấn chọn điều hòa? |
| site.product_scope = primary_category | Đọc category canonical từ site.primary_product_category_id |
| Category thiếu, inactive, soft-deleted | Về nội dung và URL sản phẩm chung |
| Category active nhưng noindex | Vẫn là đích hợp lệ cho người dùng; giữ nguyên chính sách SEO |
| site.product_label / compare_title / compare_description | Override tùy chọn; trống dùng mặc định |
| site.consultation_title / consultation_description / product_cta_label | Override tùy chọn; trống dùng mặc định |
| site.product_cta_* / consultation_cta_* | auto, route, product_category, brand, page, custom_url, phone, quote |
| navigation.header_primary / header_top / footer_products | Cùng resolver và schema repeater dùng lại |

Trong chế độ auto, nút sản phẩm theo danh mục chính nếu có, còn all_products
về `products.index`. Có thể chọn route products.index tường minh để giữ đích
toàn bộ sản phẩm ngay cả khi site có danh mục chính. Header/mobile/sticky CTA,
homepage CTA và CTA bảng giá cùng đọc consultationUrl. URL báo giá cũ được đọc
qua resolver làm fallback tương thích; không còn hai field URL báo giá độc lập
trong form Admin. Đích theo từng hero slide vẫn là nội dung được quản trị riêng.

Custom URL cho phép path nội bộ bắt đầu bằng một dấu `/`, own-domain HTTP hoặc
HTTPS, và external HTTPS. Chặn javascript/data/vbscript, URL không hợp lệ,
protocol-relative, backslash, whitespace/control characters. Route chọn từ
allowlist; category/brand/page tham chiếu ID của bản ghi còn hoạt động.

Mixed category comparison được phép. Tiêu đề mixed luôn là “So sánh sản phẩm”.
Schema hiển thị lấy hợp các field compare của các danh mục; mỗi sản phẩm dùng
type/unit của chính schema đó. Thiếu dữ liệu hiển thị `—`. Regression đã bắt
được việc schema cũ lấy riêng danh mục đầu và làm mất `panel_dimensions_mm`.
Không đổi canonical technical fact resolver, dữ liệu BTU/kW hay dữ liệu catalogue.

## URL cũ và SEO

| URL cũ | Canonical hiện tại | Tương thích |
|---|---|---|
| /bang-gia/dieu-hoa-tu-dung | /bang-gia | GET 301, giữ query |
| /faq/dieu-hoa-tu-dung | /faq | GET 301, giữ query |
| /cong-cu/chon-cong-suat-dieu-hoa-tu-dung | /cong-cu/chon-cong-suat-dieu-hoa | GET 301, giữ query; POST form cũ tiếp tục xử lý |
| /dieu-hoa-tu-dung | Không đổi | Landing SEO chuyên tủ đứng, nội dung riêng hợp lệ |

Bảng giá truy vấn tất cả sản phẩm active có giá; không khóa vào category tủ đứng.
Canonical của các trang chung và sitemap static dùng named route mới. Landing
chuyên tủ đứng vẫn có canonical riêng và nằm trong sitemap theo chủ đích.
Không có xác nhận Search Console/index công khai từ local; redirect được tạo
để bảo toàn link/bookmark đã tồn tại bất kể trạng thái index ngoài Internet.

## Inventory và phương pháp đếm

Chạy `python scripts/audit-public-navigation.py`. Dữ liệu xuất:

- `route-inventory.md`: method, route name, controller, mục đích, phạm vi và caller.
- `full-text-url-inventory.csv`: toàn bộ dòng khớp các từ khóa URL/phạm vi được yêu cầu.
- `legacy-before.csv`, `legacy-after.csv`, `legacy-remaining.md`: từng file/dòng và lý do giữ.
- `hardcoded_urls-before.csv`, `hardcoded_urls-after.csv`: literal URL public còn lại.
- `domain-before.csv`, `domain-after.csv`, `counts.json`: domain và số lượng.

Đơn vị là **dòng nguồn khớp mẫu**, không phải số destination riêng biệt. Một dòng
có nhiều từ khóa chỉ đếm một lần trong mỗi nhóm. “Fixed” là số dòng ở HEAD bị
loại hoặc viết lại; có thể đồng thời thêm dòng regression/redirect hợp lệ nên
before - fixed không nhất thiết bằng remaining. Quét app/config/database/resources/
routes/public/tests; dependency vendor/node_modules không thuộc phạm vi nguồn.

209 dòng legacy trước sửa; 92 dòng bị loại/viết lại; thêm 13 dòng hợp lệ;
còn 130 dòng. Tất cả được giải thích riêng: category/product thật, taxonomy,
nguồn catalogue, landing chuyên mục, migration history, fixture hoặc regression.
Không có legacy site-level hardcode chưa giải thích.

152 dòng literal URL trước sửa; 24 bị loại/viết lại; thêm 23 dòng hợp lệ;
còn 151. Chủ yếu là định nghĩa routes/redirects, test fixture, ví dụ Admin,
robots và dữ liệu mẫu URL tùy chỉnh theo kiến trúc hiện có. Không phải 151 link
Blade còn dựng cứng. Domain cố định được yêu cầu chỉ còn một Playwright mock
trong `tests/browser/marketing-content.spec.ts`; không có public link gắn domain đó.

## Sửa dữ liệu local có sao lưu

Không chạy migration/seed phá dữ liệu trên database Laragon. Browser regression
chính dùng SQLite riêng tại `storage/framework/testing/product-data-governance-browser-isolated.sqlite`.
Chỉ sửa chính xác hai lỗi dữ liệu đã kiểm tra trên local:

1. Blog slug `chon-may-cong-suat-the-nao` chứa link `/gool` trả 404. Thay bằng
   URL landing chuyên mục từ named route. Backup:
   `storage/framework/testing/url-scope-audit/local-post-link-backup.json`.
2. Menu “ĐIỀU HOÀ TREO TƯỜNG” còn lưu `type=route`, `target=products.index`,
   category_target=null. Đã sửa `type=product_category`, `category_target=29`,
   loại target route/custom cũ. Không thay cờ noindex của category. Backup:
   `storage/framework/testing/url-scope-audit/local-category-menu-backup.json`.

Các chỉnh dữ liệu local này không tự mang sang production; cần rà dữ liệu tương
tự trong nhiệm vụ release riêng. Không sửa trực tiếp identity/copy do Admin đã
chủ động cấu hình từ trước. Defaults mới áp dụng với field bỏ trống.

## Regression và giới hạn

Đã sửa thêm lỗi sort product listing: inline `new URL` bị shadow bởi document.URL;
dùng `new window.URL` và kiểm tra điều hướng sort trên browser. Test menu được
đồng bộ đúng request Livewire đổi loại thay vì bắt nhầm response hydrate trước đó.
Test product navigation dùng category/brand thực sự có sản phẩm trong fixture
isolated và bấm nút áp dụng bộ lọc đúng UX hiện tại.

Một test kiểm chứng evidence của quy trình AI controlled draft đã skip vì không
có artifact read-only selection tương ứng. Đây là skip công khai, không tính
thành pass; không có test thất bại. Các kiểm thử provider trong lượt này không
gọi API trả phí. Các count cuối và bằng chứng nằm trong `final-report.md`.

Không bump VERSION, commit, tag, push hoặc deploy. Build asset được tạo tại local
để kiểm chứng mã; toàn bộ thay đổi vẫn nằm trong worktree để review.
