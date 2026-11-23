# Kết quả cuối — URL / Navigation / Product Scope

Đã sửa Compare async, fallback HTML, phạm vi sản phẩm, cấu hình CTA/menu/footer,
URL chung và redirects. Giữ nguyên dữ liệu sản phẩm/danh mục tủ đứng thật.
Chi tiết nguyên nhân, kiến trúc và sửa dữ liệu local: [implementation.md](implementation.md).
Inventory thực tế: [route-inventory.md](route-inventory.md).
Từng legacy reference còn giữ: [legacy-remaining.md](legacy-remaining.md).
Gate và bằng chứng: [evidence/gate-summary.json](evidence/gate-summary.json).

```text
OVERALL = PASS
CURRENT_VERSION = 1.33.16
RELEASE_VERSION = 1.33.16
CURRENT_SHA = c971dfc2b047a8a0623f73cf0f7486de2008cd1e
LOCAL_VERSION = 1.33.15
LOCAL_SHA = c971dfc2b047a8a0623f73cf0f7486de2008cd1e
REMOTE_MAIN_SHA = c971dfc2b047a8a0623f73cf0f7486de2008cd1e
LATEST_REMOTE_TAG = v1.33.15
WORKTREE_DIRTY = YES, đã dirty trước khi thực hiện
DIVERGED = NO, ahead/behind 0/0

COMPARE_REMOVE_ROOT_CAUSE = FORM_SUBMITS_NORMALLY + JS_HANDLER_NOT_BOUND + MIXED_HTML_JSON_ENDPOINT
COMPARE_REMOVE = PASS
COMPARE_REMOVE_LAST_ITEM = PASS
COMPARE_CLEAR_ALL = PASS
COMPARE_RAW_JSON_NAVIGATION = FIXED
COMPARE_CURRENT_URL_AFTER_REMOVE = https://dieuhoa-tudung.test/so-sanh-san-pham
COMPARE_EMPTY_STATE = PASS
COMPARE_STICKY_BAR = PASS
COMPARE_CSRF = PASS
COMPARE_FAILURE_AND_DOUBLE_SUBMIT = PASS
COMPARE_MIXED_CATEGORY_SPECS = PASS

SITE_PRODUCT_SCOPE = PASS
SITE_SCOPE_MODES = all_products, primary_category
PRIMARY_CATEGORY_SETTING = PASS
GENERIC_SITE_COPY = PASS
COMPARE_DYNAMIC_TITLE = PASS
CONSULTATION_DYNAMIC_TITLE = PASS
PRODUCT_CTA_TARGET = PASS
URL_RESOLVER = PASS

HARDCODED_URLS_FOUND_BEFORE = 152
HARDCODED_URLS_FIXED = 24
HARDCODED_URLS_REMAINING_VALID = 151
UNEXPLAINED_HARDCODED_URLS = 0
LEGACY_FLOOR_STANDING_REFERENCES_BEFORE = 209
LEGACY_FLOOR_STANDING_REFERENCES_FIXED = 92
LEGACY_REFERENCES_REMAINING_VALID = 130
UNEXPLAINED_LEGACY_HARDCODE = 0

OLD_URL_REDIRECTS = PASS
CANONICAL_URLS = PASS
SITEMAP = PASS
HOMEPAGE = PASS
FOOTER = PASS
MENU = PASS
PRICING_LINK = PASS
PRODUCT_LIST_LINK = PASS
CATEGORY_LINKS = PASS
BRAND_LINKS = PASS
DEALER_LINKS = PASS
ADMIN_SETTINGS = PASS
NAVIGATION_REGRESSION = PASS
PRODUCT_REGRESSION = PASS
DEALER_REGRESSION = PASS
INTERNAL_LINK_CRAWL = PASS
HTTP_404_INTERNAL = 0
HTTP_500_INTERNAL = 0

FOCUSED_TESTS = PASS
FOCUSED_COUNTS = 80 test, 80 pass, 440 assertion, 0 fail; exit 0
BROWSER = PASS
BROWSER_COUNTS = 49/49 pass; chạy lại Compare/Admin sau sửa resolver cuối: 9/9 pass
LOCAL_BROWSER = PASS, không có pageerror
FULL_PHPUNIT = PASS
FULL_PHPUNIT_COUNTS = 725 test, 724 pass, 1 skip, 4436 assertion, 0 fail; exit 0
VITE_BUILD = PASS
COMPOSER_VALIDATE_STRICT = PASS
PHP_LINT = PASS, 61 file PHP/Blade thay đổi
GIT_DIFF_CHECK = PASS

VERSION_BUMP = PERFORMED (1.33.16)
COMMIT = e309ad2b2c5c247de7e3a9a747f52d6c20d036ab
TAG = v1.33.16
GITHUB_PUSH = PASS (main + v1.33.16)
PRODUCTION_DEPLOY = NOT_PERFORMED
READY_FOR_RELEASE_TASK = YES
```

Số hardcode tính theo dòng nguồn so với HEAD. Có thêm **23 dòng URL** hợp lệ
(route/redirect/test) và **13 dòng legacy** hợp lệ, nên không dùng phép trừ đơn
giản để suy số còn lại. CSV có đầy đủ file/dòng/nội dung/phân loại. Các default
site-level đã sửa; tên/category thật, migration cũ và landing chuyên tủ đứng giữ lại.
Domain cố định còn một lần trong mock browser test, không phải public URL runtime.

Crawl Laragon: **83 URL / 83 HTTP 200**; crawl browser isolated: **51 URL / 51 HTTP 200**.
GET 301 của ba URL cũ, canonical và sitemap được kiểm tra thêm bằng feature test.
Không xác nhận trạng thái index trên Google/Search Console từ môi trường local.

Test skip duy nhất là
`Phase2CControlledDraftApplyTest::test_exact_read_only_selected_1239_payload_is_blocked_if_evidence_is_not_eligible`,
vì thiếu artifact read-only selection của quy trình AI riêng; không thuộc Compare/Navigation/Scope.
Không đổi cấu hình để che skip hoặc failure.

Hai dữ liệu local đã sửa có backup: link blog `/gool` và menu treo tường còn lưu
route chung. Đường dẫn backup và chi tiết nằm trong implementation.md; phải rà
DB production riêng trong release task. Chưa có thao tác phát hành nào.

STOP.
