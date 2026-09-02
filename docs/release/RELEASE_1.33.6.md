# Release v1.33.6

## Tổng quan

v1.33.6 sửa Product Edit → Thông số kỹ thuật: thông tin kỹ thuật hiện được
hydrate theo canonical technical contract và có thể được quản trị viên sửa có audit.

## Root cause và remediation

Trường **Công suất BTU** trước đây bind trực tiếp vào `products.btu`, là field
legacy và có thể null. Product tái hiện `#1331` có `btu=null` nhưng
`technical_capacity_btu=12300`, vì vậy UI hiển thị trống. Trường nay bind
`technical_capacity_btu`; resolver vẫn dùng dedicated technical field trước
khi đọc `specs_json.capacity_btu`.

Toàn bộ technical inputs chuẩn cũng đã bị cấu hình `readOnly`/`disabled` và
`dehydrated(false)`. v1.33.6 mở editing cho các Product columns hiện có:
technical BTU, kW, HP, inverter, cooling type, điện áp, gas, điện năng, lưu
lượng, độ ồn, kích thước, trọng lượng và diện tích.

## Audit và provenance

Một thay đổi kỹ thuật yêu cầu `technical_specs_override_reason`. Save đánh dấu
`technical_specs_source=manual_override` và thời điểm override; evidence
catalog source-native trong `specs_json` không bị ghi đè. Resolver chỉ ưu tiên
current manual mirror khi metadata override hiện hữu.

## Safety

- `marketing_capacity_btu` vẫn là commercial/customer-facing capacity và là
  nguồn duy nhất cho public BTU filter; không có suy diễn từ technical BTU,
  kW, HP hoặc `btu` legacy.
- Catalog Import vẫn fail-closed khi technical input thiếu complete appendix
  provenance.
- Product System Restore, manifest/checksum, Product ID policy, technical
  schema và SkyAir workbook không đổi.

## Certification

- Focused Product technical edit/System Restore/numeric suite: 15 passed,
  91 assertions.
- Browser Local: 1 passed. Xác nhận `12300 → 12400`, `3.6 → 3.70`,
  `1.5 → 1.6`, save/reload persistence, metadata override và raw catalog
  `specs_json.capacity_kw=3.6` preserved.
- Full PHPUnit: 569 tests, 567 passed, 1 skipped, 1 known unrelated failure:
  `DaikinSkyAirImportReadinessTest` isolated fixture thiếu Brand ID 2 và
  Category ID 7. Đây không phải PASS và không thuộc release scope.

## Deployment

Không có migration. Deploy exact tag, chạy Composer install và cache lifecycle
theo runbook. Restart long-lived workers theo production policy dù thay đổi
này nằm ở web form, để tránh worker cũ tiếp tục giữ code release trước.

## Rollback

Checkout `v1.33.5`, rebuild caches và restart managed/generic workers. Không
cần rollback database vì release không có migration; manual overrides được
ghi sau deploy vẫn là audit data và phải được xử lý bằng quy trình nghiệp vụ.
