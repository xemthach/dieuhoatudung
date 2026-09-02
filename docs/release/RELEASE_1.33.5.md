# Release v1.33.5

## Tổng quan

v1.33.5 sửa lỗi semantic của Product Export: UI chọn toàn bộ nhóm trường
Product đã bị hiểu là export trình bày bình thường thay vì
`PRODUCT_SYSTEM_RESTORE v1`.

## Root cause và sửa lỗi

Data Transfer UI gửi đủ sáu nhóm `basic`, `pricing`, `specs`, `seo`, `media`
và `merchant` khi người vận hành chọn toàn bộ trường. Trước đây backend chỉ
nhận diện System Restore nếu mảng nhóm trống, vì vậy workbook 378 Product chỉ
có sheet `Data`.

`DataExportService` nay nhận diện một System Restore khi request là Product
XLSX, `scope=all`, không selection, không filter có hiệu lực, và nhóm trường
là rỗng hoặc đúng tập sáu nhóm Product (không phụ thuộc thứ tự). Khi đúng hợp
đồng, exporter bắt buộc dùng `ProductSystemRestoreContract::fields()` và truyền
explicit intent tới writer.

Workbook mới có `Data`, `_SYSTEM_EXPORT`, và `_SYSTEM_PAYLOAD` khi JSON/text
dài cần chia chunk. Metadata/manifest checksum, format v1 và chính sách
`PRESERVE` Product ID vẫn bắt buộc.

## Safety

- Export partial, selected, current-page, filtered và CSV/XML/JSON không trở
  thành System Restore.
- Product Import catalog vẫn bắt technical provenance thiếu bằng chứng.
- Không thay đổi Product technical schema, BTU marketing/technical semantics,
  AI Product hoặc SkyAir workbook.
- Restore đòi FK hợp lệ và không tự null hóa tham chiếu sai.

## Certification

- Local Product: 378 records, 378 with trashed, 0 trashed.
- Full-group UI semantic export: 378 rows; preview System Restore:
  valid 378, errors 0, create 0, update 378.
- Focused semantic/round-trip/header suite: 22 passed, 134 assertions.
- Browser System Restore preview: 1 passed, no relevant HTTP 500, console,
  page or network errors.
- Full PHPUnit: 566 tests, 564 passed, 1 skipped, 3,063 assertions, with only
  the known excluded SkyAir workbook fixture failure (missing isolated Brand ID
  2 and Category ID 7). It is not a System Restore failure.

## Deployment and restore order

Deploy this exact tag, run Composer/caches and restart generic plus managed AI
workers. Before importing, back up Production DB and run FK parity preflight.
Upload only a newly generated v1 workbook and require a successful System
Restore preview before confirm. A Production Product count of zero is expected
before the restore; it must preview `CREATE=378`, `UPDATE=0`, `ERROR=0` only
when all FKs match.

## Rollback

Before restore, revert code to `v1.33.4`, rebuild caches and restart workers.
After restore, use the verified pre-restore database backup only through an
approved controlled recovery procedure.
