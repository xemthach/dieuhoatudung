# Phương pháp tính công suất điều hòa

## Mục đích và phạm vi

Module cung cấp **ước tính công suất tham khảo cho người dùng**, không phải phép tính tải lạnh kỹ thuật dùng để thiết kế công trình. Runtime hoàn toàn deterministic, không phụ thuộc AI worker hoặc AI provider.

- Active Method A rule: `consumer-estimate-v2`
- Active Method B rule: `volume-consumer-estimate-v2`
- Frozen replay rules: `consumer-estimate-v1`, `volume-consumer-estimate-v1`
- Rule owner: source/config của ứng dụng
- Công suất dùng để ghép sản phẩm RAC: `products.marketing_capacity_btu`
- Công suất kỹ thuật/rated và VRF kW không được quy đổi ngầm để ghép sản phẩm.

Nguồn được ghi trong code là một bảng Excel lịch sử về tải lạnh kinh nghiệm, nhưng tài liệu gốc không còn trong repository. Vì vậy các hệ số dưới đây là **business estimate hiện hành**, không được mô tả là tiêu chuẩn HVAC đã được xác minh.

## Method A — Theo diện tích

Với diện tích `A` (m²), hệ số loại không gian `q` (W/m²), chiều cao `H` (m), số người `P`:

1. `base_w = A × q`
2. `base_btu = round(base_w × 3.412)`
3. Nếu `H > 3.0`: `btu = round(btu × round(H / 3.0, 2))`. Chiều cao dưới hoặc bằng 3 m không làm giảm tải.
4. Nếu có nắng trực tiếp: `btu = round(btu × 1.10)`.
5. Nếu có nhiều thiết bị sinh nhiệt: `btu = round(btu × 1.10)`.
6. Mười người đầu tiên được coi là đã nằm trong tải nền. Nếu `P > 10`: cộng `(P - 10) × 400 BTU/h`.
7. `calculated_btu = round(btu)`.
8. `recommended_btu` là tier cấu hình đầu tiên lớn hơn hoặc bằng `calculated_btu`. Nếu vượt tier tối đa, làm tròn lên 1.000 BTU/h.
9. `recommended_hp = round(recommended_btu / 9,000, 1)`.

Điều chỉnh nắng và thiết bị được áp dụng tuần tự, vì vậy khi cả hai cùng bật, tổng hệ số là `1.10 × 1.10 = 1.21`, không phải cộng tuyến tính 20%.

Method A là mặc định và toàn bộ thứ tự phép tính, rounding, tier và golden outputs được giữ nguyên.

## Method B — Theo thể tích

Method B là một **configured consumer estimate** của dự án, không phải tiêu chuẩn thiết kế HVAC đã được xác minh độc lập.

Với diện tích `A` (m²), chiều cao `H` (m) và hệ số thể tích `qv` (W/m³):

1. `volume_m3 = A × H`.
2. `base_w = volume_m3 × qv`.
3. `base_btu = round(base_w × 3.412)`.
4. Nếu có nắng trực tiếp: `btu = round(btu × 1.10)`.
5. Nếu có nhiều thiết bị sinh nhiệt: `btu = round(btu × 1.10)`.
6. Nếu `P > 10`: cộng `(P - 10) × 400 BTU/h`.
7. Raw BTU, market tier và HP dùng chung contract với Method A.

Chiều cao đã nằm trong `volume_m3`, vì vậy Method B **không** áp dụng thêm multiplier `H / 3`. Golden regression test chặn double-counting này.

### Nguồn hệ số Method B

Repository không có bảng W/m³/BTU/m³ hoặc tài liệu hệ số thể tích lịch sử. Hệ số Method B được resolver dẫn xuất từ profile Method A tương ứng tại chiều cao tham chiếu 3 m:

`qv = q_area / 3m`

Ví dụ nhà ở: `120 W/m² / 3m = 40 W/m³`. Trạng thái authority là `PROJECT_DERIVED_NOT_INDEPENDENTLY_VERIFIED`. Việc dẫn xuất giúp hai method có cùng tải nền tại 3 m nhưng không được diễn giải thành tiêu chuẩn kỹ thuật. Mọi thay đổi các hệ số này phải có phê duyệt, đổi rule version và cập nhật golden tests.

Các adjustment nắng, thiết bị và người đang dùng cùng giá trị với Method A nhưng được khai báo riêng dưới cấu hình Method B; đây là reuse có chủ đích, không phải chia sẻ ngầm.

## Loại không gian

Nguồn canonical cho cả v1/v2 nằm tại `config/hvac_calculator_rules.php`. `CalculatorRuleSetResolver` chọn đúng method/rule version và dẫn xuất W/m³ từ W/m²; không có bảng volume thứ hai để chỉnh độc lập. Form dùng nhãn trung tính theo unit; kết quả hiển thị hệ số đúng W/m² hoặc W/m³. Không có bản sao công thức phía client.

## V2 — kích hoạt hybrid ngày 2026-08-24

V2 là activation theo từng category, không phải multiplier toàn cục:

- 13 category HIGH/MEDIUM dùng hệ số calibrated;
- 14 category LOW giữ nguyên chính xác hệ số v1 và mang trạng thái `V1_RETAINED_REVIEW_PENDING`;
- hệ số canonical lưu bằng W/m² với độ chính xác đến ba chữ số thập phân;
- Method B v2 dùng `q_volume = q_area_v2 / 3m`, authority `PROJECT_DERIVED_FROM_AREA_V2`;
- tại đúng 3 m, baseline Method A và Method B bằng nhau theo cùng rounding contract;
- nắng `×1.10`, thiết bị `×1.10`, và người vượt 10 `+400 BTU/người` giữ nguyên;
- adjustment scope vẫn là `ADJUSTMENT_SCOPE_REVIEW_PENDING` và không được diễn giải là đã giải quyết double-count.

Các category calibrated: nhà ở, phòng khách, khách sạn, ba nhóm văn phòng, cửa hàng, ngân hàng, nhà hàng, cafe, fastfood, hội trường và thư viện. Các category còn lại giữ v1 vì nguồn LOW confidence hoặc semantics chưa đủ rõ.

V1 không bị sửa. Replay phải truyền rule version cụ thể vào service; calculation mới không truyền version sẽ dùng active version khai báo rõ trong config, không suy luận từ tên version.

## Bounds đầu vào

| Input | Unit | Min | Max | Default |
|---|---:|---:|---:|---:|
| Diện tích | m² | 5 | 5.000 | — |
| Chiều cao | m | 2 | 15 | 3 |
| Số người | người | 0 | 5.000 | 0 |

HTTP validation và domain service đều kiểm tra bounds. `method` bắt buộc thuộc allowlist `area|volume`; Method B bắt buộc có chiều cao hợp lệ. `space_type` phải thuộc bảng canonical. Priority chỉ sắp xếp sản phẩm, không thay đổi công suất.

## Tier và HP

Tier hiện tại: `9k, 12k, 18k, 24k, 28k, 30k, 36k, 42k, 45k, 48k, 50k, 60k, 100k BTU/h`.

HP là nhãn nhóm thị trường theo cấu hình 9.000 BTU/h/HP, không phải phép quy đổi công suất cơ học. Mapping diện tích tĩnh trong config là nội dung tham khảo cũ và không được dùng để tính hoặc hiển thị kết quả chính.

## Product recommendation

Sản phẩm hợp lệ phải:

- active;
- không `out_of_stock` khi column tồn tại;
- có `marketing_capacity_btu > 0`;
- được resolver xác minh thuộc một class RAC;
- có công suất **không thấp hơn** `recommended_btu`.

Lần tìm đầu dùng khoảng từ target đến tier kế tiếp. Nếu dưới 4 kết quả, khoảng được mở rộng từ target đến target + 12.000 BTU/h. Không có kết quả vẫn là calculation success; UI báo catalog không có sản phẩm đủ công suất. Ranking mặc định theo capacity delta, hoặc giá thấp trước nếu người dùng chọn.

## Persistence và riêng tư

Anonymous calculation không tạo `btu_calculations`, không tạo Lead và không gửi mail. Khi người dùng chủ động cung cấp phone/email, hệ thống lưu calculation method, rule version và tạo/gửi luồng tư vấn phù hợp. Mọi record lịch sử có trước Method B được backfill `area` vì code và rule lineage chứng minh chỉ Method A tồn tại. IP, user-agent và Referer không được ghi vào record mới.

## Quản trị và thay đổi rule

Rule hiện là `CODE_CONSTANT`/`SYSTEM_CONFIG`, không phải admin-editable business data. Admin chỉ xem lịch sử và rule version. Mọi thay đổi hệ số phải:

1. có nguồn/phê duyệt;
2. đổi `rule_version`;
3. cập nhật golden tests và tài liệu này;
4. ghi trong release notes;
5. chạy migration nếu persistence contract thay đổi.

## Giới hạn

- Chưa có authority document độc lập trong repository cho bảng W/m², +10%, 400 BTU/người hoặc area-range cũ.
- Không mô hình hóa vật liệu, kính, hướng công trình, khí hậu, độ ẩm, thông gió hoặc heat gain chi tiết.
- Bounds lớn phục vụ fail-safe input contract, không chứng nhận phương pháp phù hợp cho tải công nghiệp lớn.
- Kết quả cần khảo sát kỹ thuật trước khi chốt thiết kế/lắp đặt.
