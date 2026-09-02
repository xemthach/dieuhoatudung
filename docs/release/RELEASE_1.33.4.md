# Release v1.33.4

## Tổng quan

v1.33.4 bổ sung `PRODUCT_SYSTEM_RESTORE v1`: Product XLSX do ứng dụng xuất có thể được khôi phục vào Product table trống mà không làm suy yếu Catalog Import cho dữ liệu nhà sản xuất.

## Export và restore contract

- Workbook restore có `_SYSTEM_EXPORT` metadata: format/version, row count, column checksum, content checksum và chính sách `PRESERVE` Product ID.
- `_SYSTEM_PAYLOAD` chunk các JSON/text dài quá giới hạn 32.767 ký tự của Excel; importer ghép lại rồi kiểm manifest.
- Restore chỉ được nhận diện bởi metadata + manifest hợp lệ, không dựa vào tên file.
- Product identity, business, marketing, technical canonical fields, media, SEO, Merchant và `specs_json` được khôi phục theo Product persistence contract.

## Safety

- `marketing_capacity_btu` vẫn là commercial field, tách biệt `technical_capacity_btu`.
- Catalog Import technical fields thiếu provenance vẫn fail-closed.
- System restore không bịa source provenance, không null FK không hợp lệ và không đưa Product metadata vào technical category schema.
- FK brand/category/catalog source/catalog model và quan hệ model-source phải tồn tại tại target; lỗi preview phải được xử lý trước confirm.

## Local evidence

- Local source: 378 Product, 378 with trashed, 0 trashed; export mới: 378 rows; chênh lệch không giải thích: 0.
- Focused restore/schema suite: 14 tests, 45 assertions, 0 failures/errors.
- Playwright SYSTEM RESTORE preview: 1 passed, 0 failures.
- Isolated empty-table round trip: ID and field parity PASS, 0 unexplained differences; long `specs_json` payload PASS.

## Known unrelated test state

Full PHPUnit is expected to retain only the excluded SkyAir workbook fixture failure. The release also updates README/version metadata so `AdminUxInformationArchitectureTest` must no longer fail from a stale README badge.

## Deployment requirements

Run the exact `v1.33.4` tag, `composer install --no-dev`, migrations and caches. Before any Product restore, make a DB backup and run a read-only FK parity preflight. Upload only a newly generated v1 system export. Production Product count of zero is intentional: preview must show `CREATE=378`, `UPDATE=0`, `ERROR=0` only when all target FKs exist.

## Rollback

If code/preflight gates fail before import, checkout `v1.33.3`, rebuild caches and restart the managed workers while restoring the previous AI desired state. If data has already been restored, use the verified DB backup only through an approved, separately controlled rollback procedure.
