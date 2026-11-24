# Audit Import / Export

Audit ngày 2026-11-24 trên `main`. Repository sạch trước khi sửa và không có divergence.

| Mốc | Giá trị |
| --- | --- |
| `VERSION` | `1.33.16` lúc bắt đầu |
| `LOCAL_TAG` | `v1.33.16` |
| `LOCAL_SHA` | `a47e5c90cc92e048b7779923d67c6d13c7d2cc` |
| `REMOTE_MAIN_SHA` | `a47e5c90cc92e048b7779923d67c6d13c7d2cc` |
| `LATEST_REMOTE_TAG` | `v1.33.16`, trỏ đến cùng commit |

## Điểm vào và luồng xử lý

| Điểm vào | Chuỗi xử lý | Quyền và kết quả |
| --- | --- | --- |
| `/admin/data-transfer-page`, `HasDataTransferActions` trên danh sách Product, Lead, Quote Request và BTU Calculation | Livewire upload → `SpreadsheetFileGuard` → `DataImportService` → mapping → validator theo module → `/admin/import-preview-page` → xác nhận → `/admin/import-result-page` | Quyền `{module}.import`, quyền riêng khi Transfer/Restore và quyền của người tạo job. File private, lưu actor, mode, checksum, lỗi và receipts. |
| Export ở trang trung tâm, header danh sách và bulk Product | `BulkSelectionResolver` cho selected/current page/filtered của bảng → `DataExportService` → XLSX/CSV/JSON/XML theo mục đích → tải file hoặc xem lịch sử | Quyền `{module}.export`. `/admin/export/{dataExportJob}/download` kiểm tra chủ sở hữu, quyền theo hợp đồng, thời hạn và checksum đối với export mới. |
| Product Data Edit | `BusinessProductImport` → `ProductDataImportContract` → ghi các trường nghiệp vụ trong một transaction | CSV/XLSX, khóa SKU hoặc slug, brand/category theo slug hoặc tên duy nhất, preview trước khi ghi. Blank xóa các trường nullable đã khai báo, cột vắng giữ giá trị hiện có. |
| Product Transfer | `_PRODUCT_TRANSFER` → kiểm tra cột và nội dung → `ProductTransferImportHandler` → transaction và receipts | SKU rồi slug, FK đích theo slug. Với Catalog, Transfer v2 bắt buộc khớp SHA-256 nguồn, Brand/Category và danh tính model trong nguồn đó. Dừng nếu thiếu, trùng hoặc sai. Update phụ thuộc Import Governance. |
| Product System Backup/Restore | `_SYSTEM_EXPORT` → kiểm tra schema, số dòng, nội dung và danh tính FK v2 → `ProductRestoreImportHandler` ghi theo ID | Snapshot bảng `products` có bản ghi xóa mềm. Quyền `system_restore.run`. Không xóa những Product ngoài file. Brand, category và catalog references phải có sẵn. Full backup của site gồm DB và storage theo `DEPLOY.md`. |
| Catalog và Product Data Governance | `catalog:scan`, `catalog:extract-pdf-models`, `catalog:extract-specs`, `catalog:match-products`, `catalog:apply-source-approval`, `catalog:ingest-marketing-capacity-product-list` và trang Product Data Governance | Evidence PDF, source hash và section policy đi qua luồng catalog. `PRODUCT_LIST` quản lý BTU thương mại, `TECHNICAL_APPENDIX` quản lý BTU kỹ thuật. |
| Luồng khác | `dealer:import-locations` đọc master data đã ghim, `products:import-category-technical-schema` đọc schema, `/so-sanh-san-pham/export/{pdf,excel,csv}` xuất so sánh công khai, Merchant Feed xuất XML | Mỗi luồng có hợp đồng riêng. Brands và Categories là tham chiếu trong trung tâm, chưa có module nhập danh mục/brand tổng quát. |

`ExecuteDataImport` dùng queue `default` khi số dòng vượt ngưỡng 500. Web và worker kiểm tra cùng checksum và quyền trước khi ghi. Các giao dịch Product lưu receipts cùng dữ liệu và tuần tự hóa job bằng cache lock dùng chung. Export chạy đồng bộ. `php artisan route:list` không có API Product Import/Export. Hai route tải file generic của Filament do framework đăng ký.

## Ticket và nghiệm thu

| Mức | Ticket | Cách kiểm chứng |
| --- | --- | --- |
| P0 | IE-01. Product edit chạy validator Catalog và bị chặn vì thiếu schema. Tách allowlist và handler, không ghi trường kỹ thuật hoặc provenance từ file sửa nghiệp vụ. | `ProductDataEditContractTest`, `BusinessImportWorkflowTest`, Playwright `comprehensive.spec.ts`. |
| P0 | IE-02. File backup thiếu manifest hoặc dữ liệu không còn khớp manifest. Chọn System Backup XLSX, đối chiếu `_SYSTEM_EXPORT`, kiểm tra version và checksum trước preview và execute. | `ProductSystemRestoreRoundTripTest`, Playwright `product-system-restore.spec.ts`. |
| P0 | IE-03. Lỗi ghi một dòng có thể để lại dữ liệu Product đã ghi từ các dòng trước. Gộp Product và receipts vào cùng transaction, từ chối file có lỗi preview, chỉ báo hoàn tất khi đủ số dòng và không lỗi. | `ProductDataEditContractTest`, `DataImportResultStateTest`, `ProductTransferEightyOneRowTest`. |
| P0 | IE-04. Công thức và HTML. Chặn công thức XLSX trước khi đọc giá trị, ghi XLSX dưới dạng text, bảo vệ CSV khi mở bằng spreadsheet và lọc HTML khi render mô tả Product. | `ImportFileSafetyTest`, `ProductDataEditContractTest`. |
| P1 | IE-05. ID Brand/Category không mang cùng ý nghĩa giữa hai môi trường. Transfer lấy FK bằng slug, Restore v2 đối chiếu danh tính FK trước khi dùng ID, file edit có ID đơn lẻ bị từ chối. | `ProductTransferTest`, `ProductTransferEightyOneRowTest`, `ProductSystemRestoreRoundTripTest`. |
| P1 | IE-06. Selected và Filtered cần giữ đúng tập sản phẩm, kể cả khi xuất file để nhập lại. | `ProductDataEditContractTest`, `ProductTransferTest`. |
| P1 | IE-07. Snapshot cần phân biệt cột `btu` cũ, `marketing_capacity_btu` và `technical_capacity_btu`, đồng thời giữ JSON lớn, khoảng trắng, `NULL` và bản ghi xóa mềm. | `ProductSystemRestoreRoundTripTest`, `ProductTransferEightyOneRowTest`, `ProductTechnicalEditContractTest`. |
| P2 | IE-08. Đọc CSV theo record có dấu nháy và xuống dòng, kiểm tra extension/nội dung, giới hạn file nén và workbook, giữ tên gốc khi upload Livewire. | `ImportFileSafetyTest`, Playwright `comprehensive.spec.ts`, worker smoke. |
| P2 | IE-09. Lịch sử và tải file phải giữ ranh giới quyền. File export có SHA-256 trong lịch sử, đường dẫn riêng theo job và thời hạn. | `BusinessImportWorkflowTest`, `DataExportDownloadTest`. |

System Backup v2 xuất giá trị gốc từng cột và ghi SHA-256 cho nội dung cùng danh tính các bản ghi FK được tham chiếu. Restore v1 bị từ chối vì thiếu bằng chứng danh tính FK, dấu thời gian, trạng thái xóa mềm và các cột governance mới. SHA-256 kiểm tra tính toàn vẹn. File vẫn cần đến từ nguồn vận hành tin cậy.

## Kiểm chứng phát hành

Đã kiểm tra phục hồi bản sao lưu, đối chiếu dữ liệu trước và sau migration và thử rollback khi worker lỗi. PHPUnit và Playwright đã chạy trên DB thử nghiệm riêng. Bản clone sạch đã cài dependencies từ lockfile, chạy migration, Vite build và test Import/Export. Biên bản vận hành chi tiết được lưu riêng tại máy kiểm thử.
