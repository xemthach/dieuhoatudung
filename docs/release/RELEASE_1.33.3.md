# v1.33.3

## Tổng quan

Patch này chuẩn hóa `products.marketing_capacity_btu` thành nguồn SQL duy nhất cho BTU thương mại hiển thị cho khách hàng. Sự cố Live `/san-pham?btu[]=18000` trả về rỗng là lỗi data parity: Product có bằng chứng kỹ thuật hoặc legacy nhưng cột marketing canonical còn `NULL`.

## Root cause và nguồn filter

Luồng `/san-pham` là `routes/web.php` → `ProductController` → `ProductFilterService` → `ProductMarketingCapacityQueryAdapter`. Khi schema có cột này, predicate BTU dùng chính `products.marketing_capacity_btu`.

Không thay query sang `technical_capacity_btu`, `btu`, `specs_json`, hoặc Product title. Các giá trị đó có thể là công suất rated/range và không chứng minh commercial tier.

## Canonical contract

- `marketing_capacity_btu`: tier thương mại/customer-facing; dùng cho filter, search, sort, bảng giá, calculator candidate và display marketing.
- `technical_capacity_btu`: giá trị kỹ thuật/rated của hãng; không thay thế tier marketing.

## Audit và backfill an toàn

```bash
# Read-only: không ghi Product.
php artisan catalog:audit-marketing-capacity --json

# Dry-run mặc định; tạo ledger private.
php artisan catalog:backfill-marketing-capacity --batch=marketing-20260902

# Chỉ sau khi review proposal có PRODUCT_LIST đã xác minh.
php artisan catalog:backfill-marketing-capacity --apply --approved --product=<ID> --batch=marketing-20260902
```

Backfill chỉ cập nhật `marketing_capacity_btu`. Mỗi row lock Product và source fact, kiểm tra stale state, provenance `PRODUCT_LIST`, verification status và giá trị trước khi ghi. Không thay đổi technical capacity, `btu`, `specs_json` hay catalog source evidence.

## Triển khai Live

1. Deploy chính xác tag `v1.33.3` theo runbook.
2. Chạy audit read-only và lưu JSON trước khi thay đổi dữ liệu.
3. Nếu `PROPOSE_UPDATE=0`, dừng remediation data; không được suy diễn tier từ technical/range/title.
4. Review từng proposal có `source_section=PRODUCT_LIST`, áp dụng một Product, rồi batch tối đa 10.
5. Chỉ khi browser/public checks PASS mới chạy batch size 50 cho các proposal đã được review.

## Validation

- Focused marketing/filter/resolver/calculator/Quote suite: 34 passed, 194 assertions.
- Playwright Product navigation/filter URLs: 2 passed.
- Composer validation/audit, npm high audit/build, PHP lint, secret scan và `git diff --check`: PASS.
- Full PHPUnit: `KNOWN_UNRELATED_FAILURE` — `DaikinSkyAirImportReadinessTest` đọc workbook SkyAir untracked có `brand_id=2`, `product_category_id=7`, mâu thuẫn với contract category-specific 23/24/25/27. Workbook không thuộc patch này, không bị sửa hay stage.

## Rollback

Checkout tag `v1.33.2`, chạy `php artisan optimize:clear`, rebuild config/route/view cache. Code rollback không tự đảo dữ liệu backfill; chỉ backfill nào đã được review với ledger mới được xem xét rollback riêng sau khi xác minh trước/sau.
