# Product Filter / Category / Catalog — Full Audit

## 1. Phạm vi và kết luận hiện tại

Audit được thực hiện từ code Laravel/Eloquent, route, Blade, service filter, database hiện tại và HTTP loopback. Không import SkyAir, không sửa Product/catalog data, không thay đổi RBAC.

**Kết luận: `PRODUCT FILTER / CATEGORY / CATALOG = PARTIAL / STOP`.**

Các đường dẫn listing/filter chính hoạt động và filter capacity vẫn dùng cột marketing canonical, loại VRF/GMV. Một lỗi link chắc chắn đã được sửa: breadcrumb Product không còn hard-code “Điều hòa tủ đứng” và không phát link tới category inactive. Tuy nhiên browser certification chuyên dụng chưa chạy trong turn này, và dữ liệu hiện tại còn hai vấn đề cần quyết định nghiệp vụ, không được tự sửa:

- 51 Product active đang thuộc category inactive `Điều hòa treo tường` (category route trả 404).
- 80 Product active chưa có `product_category_id`.

Đây là vấn đề publication/taxonomy, không phải lý do để tự động reclassify hoặc activate dữ liệu.

## 2. Baseline thực tế

| Entity | Total | Active | Inactive | Soft deleted |
|---|---:|---:|---:|---:|
| Product | 357 | 182 | 175 | 0 |
| Product category | 7 | 6 | 1 | 0 |
| Brand | 14 | 6 | 8 | 8 |

Category active-product distribution: id 23 = 2, id 24 = 28, id 25 = 0, id 26 = 0, id 27 = 21, id 28 = 0. Brand distribution: Daikin = 101, Gree = 81; tổng 182 active Product. Các số liệu này được lấy read-only từ DB hiện tại, không dùng baseline lịch sử 132.

SkyAir Products đã tồn tại trong DB từ trạng thái trước audit (tổng Product 357, inactive/noindex theo dữ liệu hiện có). Task này không tạo, xóa hoặc thay đổi chúng. File `DAIKIN_SKYAIR_2026_PRODUCTION_IMPORT.xlsx` cũng đang tồn tại và có dấu hiệu là artifact cũ ngoài phạm vi task; không dùng nó, không xóa nó.

## 3. Code inventory và contract

- `ProductController@index`: query `Product` active, eager-load `brand/category`, sau đó `ProductFilterService`, paginate với query string.
- `ProductController@category`: chỉ resolve category active; category inactive/deleted chủ động không public và trả 404.
- `ProductController@show`: Product active; related Product active.
- `BrandController@show`: Brand active, Product active, sort allowlist.
- `ProductFilterService`: whitelist brand, btu, inverter, cooling type, voltage, refrigerant, price, stock and sort. BTU dùng `ProductMarketingCapacityQueryAdapter` (marketing capacity nếu schema tồn tại, fallback legacy btu). Bucket BTU loại category có tên VRF/GMV.
- `resources/views/components/product-filter-sidebar.blade.php`: hiện hiển thị category, brand, BTU, inverter; các filter còn lại được service hỗ trợ nhưng chưa có control trong sidebar.
- `resources/views/products/_product-grid.blade.php`: dùng paginator total và giữ query string.
- `ProductMediaResolver`: là resolver chung cho main/card/gallery; deduplicate theo URL, ưu tiên main rồi gallery, fallback an toàn khi không resolve được local/CDN.

Artifact contract đã tạo trong `docs/reports/final/artifacts/`:

- `product_filter_contract.csv`
- `category_integrity.csv`
- `product_category_integrity.csv`
- `category_route_audit.csv`
- `category_brand_crosstab.csv`
- `facet_count_validation.csv`
- `catalog_category_schema_matrix.csv`
- `product_category_repair_manifest.csv` (0 repair rows)
- `filter_runtime_query_profile.json`
- `filter_category_issue_ledger.csv`

## 4. Route/category integrity

Route `/danh-muc/{categorySlug}` tồn tại và không xung đột với `/san-pham/{slug}`. Category active hợp lệ trả 200; `dieu-hoa-treo-tuong` tồn tại nhưng inactive nên trả 404 theo contract hiện tại. Đây là 404 do controller `firstOrFail`, không phải route missing.

HTTP proof local:

| URL | Result |
|---|---:|
| `/san-pham` | 200 |
| `/danh-muc/dieu-hoa-am-tran-cassette` | 200 |
| `/danh-muc/dieu-hoa-treo-tuong` | 404 |

Category facet counts được recompute bằng cùng predicate `Product.is_active = 1` như listing và khớp số hiển thị. Tuy nhiên các facet active-category không phải là partition toàn bộ catalog vì 80 Product chưa category và 51 Product thuộc category inactive.

## 5. Defect đã sửa

### Breadcrumb Product

Trước đây `resources/views/products/show.blade.php` luôn hiển thị breadcrumb đầu là “Điều hòa tủ đứng”, kể cả Product wall-mounted/commercial. Nó cũng tạo URL category dù category inactive.

Đã sửa tối thiểu:

- root label thành “Sản phẩm”;
- chỉ tạo category URL khi `$product->category->is_active`;
- giữ category label nhưng để item không link nếu category inactive;
- áp dụng cùng guard cho Breadcrumb JSON-LD.

### Internal-link suggestions

`InternalLinkSuggestionService` trước đây lọc category chỉ bằng `is_indexable`. Đã thêm `is_active = true` để không persist suggestion dẫn tới public 404.

## 6. Media audit

Admin/frontend dùng contract `media_url()`/`ProductMediaResolver` thay vì resolver riêng. Product detail lấy `$product->gallery_images`; resolver hợp nhất `main_image` + `gallery_json`, deduplicate URL và fallback `product-default.jpg`. Card dùng `main_image_url`, cũng cùng resolver. R2/CDN được kiểm tra qua `MediaFile` sync cache và disk `r2`; local chỉ trả URL khi file tồn tại. Không có mass rebuild/upload và không có media write trong audit.

Browser visual proof cho từng asset/CDN URL chưa được thực hiện; đây là giới hạn còn mở, không kết luận toàn bộ media PASS chỉ từ code.

## 7. Filter and performance

Read-only in-process profiling sau warm-up:

| Route | HTTP | Queries | Query time |
|---|---:|---:|---:|
| `/san-pham` | 200 | 15 | 26.71 ms |
| active category listing | 200 | 36 | 44.08 ms |

Profile nằm tại `filter_runtime_query_profile.json`. Đây là single request profile, không phải load test. Không thấy provider call hay per-row provider lookup. Category/brand counts hiện dùng count queries tương ứng với từng facet; nếu scale lớn cần cân nhắc aggregate query sau khi có measurement, chưa thêm index hoặc tối ưu đoán.

## 8. Security and safety

- Filter values đi qua allowlist; `sort` dùng các nhánh cố định và biểu thức raw cố định.
- BTU matcher giữ RAC safety, không cho category VRF/GMV lọt vào bucket.
- Không có Product/catalog mutation, migration mới, provider call hoặc AI worker toggle.
- Không thay đổi permission/RBAC.
- `product_category_repair_manifest.csv` rỗng để chứng minh chưa tự sửa 51/80 row.

## 9. Regression tests

Đã thêm `tests/Feature/ProductCatalogIntegrityTest.php`:

- inactive category không xuất hiện trong Product breadcrumb dưới dạng dead URL;
- active category vẫn có breadcrumb URL.

Focused result: **5 tests, 5 passed, 9 assertions** (bao gồm ProductFilter UX tests). Full suite cần chạy lại trước release.

## 10. Browser certification

Repo có Playwright marketing harness (`tests/browser/marketing-content.spec.ts`, `@playwright/test`), nhưng chưa có product listing/category spec trong phạm vi audit này và chưa thực hiện browser session/screenshot cho các route Product. Vì vậy không claim browser PASS. Cần chạy thêm desktop/mobile proof cho listing, filter, category route, Product breadcrumb/media, empty/no-result và console/network errors.

## 11. Remaining issues / operator decisions

1. **Active Product + inactive category:** cần quyết định publication contract: deactivate Product, activate category, remap category, hoặc giữ Product public nhưng không category landing. Không được chọn tự động.
2. **Uncategorized active Product:** cần source-backed mapping hoặc chấp nhận là “uncategorized”; không suy luận category từ tên/SKU.
3. **Zero-count active categories:** hiện vẫn là route hợp lệ nhưng không có Product active; cần UX decision hide/disable/retain.
4. **Stale production workbook artifact:** tồn tại ngoài task và không được sử dụng; cần xử lý release-governance riêng sau khi đóng file đang lock.
5. **Browser proof:** chưa đủ để nâng verdict.

## 12. Files changed by this audit

- `resources/views/products/show.blade.php`
- `app/Services/Seo/InternalLinkSuggestionService.php`
- `tests/Feature/ProductCatalogIntegrityTest.php`
- audit artifacts trong `docs/reports/final/artifacts/`
- report này

No database writes were performed by this audit.

## 13. PUBLIC NAVIGATION / MENU GOVERNANCE

### Previous architecture and root cause

The public header was not driven by a menu table or navigation setting. `resources/views/partials/header.blade.php` contained separate desktop and mobile literal anchor lists. The old primary item used `/dieu-hoa-tu-dung` and the literal label “Điều hòa tủ đứng”; top-bar links and footer product links were also independently literal. `PolicyLinks` is a separate, intentional policy-location resolver. There was no public menu model/table.

### Canonical architecture implemented

`App\Services\Navigation\PublicNavigationResolver` is now the sole header/footer item resolver. Configuration is stored through the existing `site_settings` JSON infrastructure under `navigation.header_primary`, `navigation.header_top`, and `navigation.footer_products`. When settings are empty, a bounded safe fallback uses named routes and the general `products.index` catalog route; it does not restore the floor-standing landing link.

Supported values are explicitly allowlisted: named system routes, `product_category` by category ID, and safe `custom_url`. Category resolution requires an existing active and indexable category, then generates the current slug route. Inactive, deleted, missing, unknown-route, JavaScript, data, and vbscript targets are hidden fail-closed.

### Admin and parity

The existing `ManageSiteSettings` page now contains a collapsible “Điều hướng public” repeater. Operators can edit label, location group, type, category target, route/custom target, order, active state, and new-tab behavior. The desktop and mobile header loops consume the same `header_primary` collection. Footer product links consume the same resolver with a distinct `footer_products` location; policy links remain logically separate.

Setting writes clear the existing SettingService cache. No new migration or second menu schema was required. The category selector stores category identity, not a hard-coded slug. Reordering is by explicit `sort_order`; disabling preserves configuration and removes the item from all consumers.

### Evidence artifacts

- `public_navigation_inventory.csv`
- `navigation_target_validation.csv`
- `navigation_route_health.csv`
- `navigation_issue_ledger.csv`
- `navigation_migration_manifest.csv` (migration not required)

### Tests and limitations

`PublicNavigationResolverTest` proves safe fallback, category-ID resolution, inactive-category hiding, and unsafe custom URL rejection. HTTP checks prove `/san-pham` and an active category return 200, while inactive wall-mounted category returns 404 and is not emitted by the resolver/breadcrumb.

Playwright release evidence is now complete. The controlled combined run executed 9 tests with one worker: 9 passed, 0 failed, 0 skipped. It proves Product listing/filter/detail, desktop/mobile navigation parity, Admin label/category target/reorder/disable/restore round-trip, and console/network cleanliness. The fixture was cleaned after the run. `PUBLIC NAVIGATION = PASS` for the implemented scope; inactive category targets remain fail-closed and taxonomy data issues remain separate operator decisions.

### Browser Certification Closure

Reference: `MARKETING_CONTENT_BROWSER_CERTIFICATION_REPORT.md`.

- Admin navigation round-trip: PASS; Livewire update responses were 200 and no manual cache clear was required.
- Product/filter/navigation browser suite: PASS (2/2).
- Marketing/content browser suite: PASS (6/6).
- Combined release browser suite: PASS (9/9).
- Provider calls: 0; Product/catalog writes by browser fixtures: 0; fixture cleanup: PASS.
