# Release v1.33.7

## Tổng quan

v1.33.7 hoàn tất hardening SkyAir thương mại đã chứng nhận ở Local: fixture
import dùng identity nghiệp vụ di động, pha/tần số được giữ độc lập, và giao diện
public chỉ trình bày dữ liệu capacity/component đúng ngữ nghĩa nguồn.

## Thay đổi đã sửa

- Test SkyAir không còn phụ thuộc workbook nguồn untracked có Brand/Category ID
  lịch sử. Fixture dùng các ma trận nguồn đã review, resolve `brands.slug=daikin`
  và category SkyAir chuẩn trước khi kiểm tra FK nghiêm ngặt của Catalog Import.
- `phase` và `frequency` là technical fact source-native, không còn alias sang
  `voltage`. Product Edit hydrate, override có audit, save và reopen các giá trị
  này mà vẫn bảo toàn evidence catalog gốc.
- Product card/detail tách rõ marketing BTU và rated kW. Cấu hình indoor/outdoor
  SkyAir hiển thị remote/panel chỉ khi ma trận nguồn chứng minh exact bundle;
  compatibility-only không bị suy diễn thành component đã chọn.

## An toàn và phạm vi

- Public BTU filter tiếp tục dùng duy nhất `products.marketing_capacity_btu`.
- Catalog Import provenance, Product System Restore/manifest/checksum, Product ID
  policy, schema kỹ thuật và wall-mounted RAC không bị nới lỏng hoặc đổi nghĩa.
- Các workbook SkyAir/Wall-mounted và workbook export local là source evidence,
  không được commit hay deploy. Ma trận source-derived cần cho fixture được version
  cùng test.

## Chứng nhận Local

- Focused SkyAir/technical/detail/filter suite: 22 tests, 639 assertions, PASS.
- Playwright Local: 2 passed; kiểm tra technical edit/save/reload, 1P/3P,
  exact components, wall-mounted regression và exact card/filter IDs.
- Full PHPUnit: 574 tests; 573 passed, 1 skipped, 0 failed; 3,587 assertions;
  exit code 0.
- `composer validate --strict`, `composer audit`, `npm audit --audit-level=high`,
  `npm run build`, PHP lint và `git diff --check`: PASS.

## Deployment

Không có migration. Deploy đúng tag `v1.33.7`, chạy `composer install --no-dev`,
cache lifecycle, và restart Supervisor generic/managed worker theo runbook. Dùng
build assets đã tạo và commit từ Local; không build Node trên Production.

## Rollback

Rollback code về `v1.33.6`, khôi phục cache và restart workers. Không có migration
hoặc thay đổi dữ liệu tự động trong release này.
