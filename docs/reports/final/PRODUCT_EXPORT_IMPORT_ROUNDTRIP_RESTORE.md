# Product Export → Import Round-trip Restore

## Mục tiêu và phạm vi

Khôi phục Product từ workbook do chính ứng dụng Local xuất ra vào một bảng `products` đích trống, không thay đổi quy tắc nghiêm ngặt của Catalog Import. Không có thao tác Production trong hạng mục này.

## Nguyên nhân gốc

`ProductImportHandler` chỉ có contract Catalog Import. Bất kỳ trường kỹ thuật nào trong một dòng Product đều phải có provenance catalog đầy đủ. Điều đó đúng cho dữ liệu nhà sản xuất, nhưng sai cho trạng thái Product đã được ứng dụng lưu trước đó. Đồng thời `validateSpecsAgainstCategorySchema()` nhận cả dòng Product và biến metadata như `id`, `sort_order`, ảnh, JSON media, `condition` và `product_type` thành technical specs giả.

## Contract mới

Workbook Product XLSX đầy đủ (scope `all`, không chọn nhóm trường) mang metadata sheet ẩn `_SYSTEM_EXPORT`:

- `format=PRODUCT_SYSTEM_RESTORE`, `format_version=1`;
- số Product, checksum cột và checksum nội dung;
- chính sách Product ID `PRESERVE`.

Trường dài vượt giới hạn 32.767 ký tự của XLSX được chunk trong sheet ẩn `_SYSTEM_PAYLOAD`, sau đó ghép lại trước khi kiểm tra manifest. Import chỉ vào `SYSTEM_PRODUCT_RESTORE` khi metadata, version, cột, số dòng và checksum đều đúng; không dựa vào tên file.

Catalog Import vẫn là contract riêng: technical field thiếu provenance catalog vẫn bị chặn.

## Phân loại export fields

- Product identity: `id`, `name`, `slug`, `sku`, `model_code`, brand/category và catalog references.
- Business/marketing: giá, tồn kho, marketing capacity, mô tả, media, SEO, Merchant, flags và sort order.
- Technical canonical: technical capacity/status, kW, HP, inverter, cooling type, điện áp, gas, công suất, airflow/noise/dimensions/weight và `specs_json`.
- Technical raw/source: `catalog_source_id`, `catalog_model_id`, catalog match và technical source/override metadata.

AI runtime fields, timestamps và soft-delete state không thuộc restore payload: chúng là workflow/audit runtime, không phải Product content snapshot. `marketing_capacity_btu` và `technical_capacity_btu` được giữ là hai trường khác nghĩa.

## Validation và an toàn dữ liệu

SYSTEM restore vẫn bắt buộc Product ID, name, slug; xác thực FK brand/category/catalog source/catalog model, quan hệ model-source, JSON và uniqueness ID/SKU/slug. FK đích thiếu là lỗi rõ ràng; không còn âm thầm đổi thành `null`. Restore giữ Product ID, không gọi `ProductTechnicalSpecWriter`, không bịa provenance, không chạy category technical-schema guard. Một system restore được revalidate trước confirm và sẽ không import một phần nếu manifest/FK trở nên không hợp lệ.

Category technical schema giờ chỉ nhận `specs_json` và allowlist technical top-level. Product metadata không còn thành technical schema candidate.

## Parity Local

Ngày 2026-09-02, Local DB có 378 Product, 378 `withTrashed`, 0 soft-deleted và 184 active. Export mới tạo 378 dòng; preview lại chính workbook trả 378 valid, 0 lỗi, `SYSTEM_PRODUCT_RESTORE`, 0 CREATE và 378 UPDATE vì Local đích đang có Product. Với Production Product table trống và FK đã parity, cùng preview phải là 378 CREATE, 0 UPDATE.

Chênh lệch filename/preview 378/377 không còn tái hiện với export format mới: expected export count = 378, actual workbook rows = 378, unexplained difference = 0.

## Chứng nhận

- Isolated empty-Product-table round trip: 2/2 CREATE, Product IDs và toàn bộ field contract so sánh bằng nhau, 0 unexplained differences.
- Current Local DB round trip: source 378 → empty isolated SQLite target; preview 378 valid / 0 error / 378 CREATE / 0 UPDATE; import 378 CREATE / 0 failed / 0 skipped; 378 IDs identical and 0 unexplained field differences.
- Long `specs_json` lớn hơn giới hạn cell XLSX: PASS qua `_SYSTEM_PAYLOAD`.
- Catalog technical field không provenance: vẫn BLOCKED.
- Metadata Product như `id`, `sort_order`, media và Merchant không bị false schema classification: PASS.
- Browser Local: export/upload preview hiển thị `SYSTEM RESTORE`, `Không có lỗi dữ liệu`, không có HTTP 500/page/console/network lỗi: PASS.

Focused suite cuối: 14 tests, 45 assertions, 0 failures/errors. Playwright: 1 passed.

After synchronizing README release metadata, full PHPUnit: 561 tests, 559 passed, 1 skipped, 3,035 assertions and exactly 1 failure. The remaining `DaikinSkyAirImportReadinessTest` uses the excluded/untracked SkyAir workbook with FK IDs absent from its isolated fixture. It has no System Restore call path; full-suite certification is `KNOWN_UNRELATED_SKYAIR_FAILURE`, not PASS.

## Production readiness

Không deploy hoặc mutate Production. Trước restore Live phải export workbook mới từ Local, đối chiếu preview FK trên Live và chỉ confirm khi preview `SYSTEM RESTORE`, `CREATE = 378`, `UPDATE = 0`, `ERROR = 0`. Không dùng workbook cũ không có metadata contract.

## Kết luận

| Gate | Kết quả |
| --- | --- |
| Export format | `PRODUCT_SYSTEM_RESTORE v1` |
| False provenance block | FIXED |
| False schema classification | FIXED |
| Marketing capacity restore | PASS |
| Technical capacity/specs restore | PASS |
| FK parity | PASS in isolated contract; Live preflight required |
| ID restore policy | PRESERVE |
| Catalog provenance guard | PASS |
| System restore | PASS |
| Round-trip field parity | PASS |
| Unexplained field differences | 0 |
| Production mutation | NONE |
