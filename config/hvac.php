<?php

return [
    'btu' => [
        'methodology' => 'SIMPLIFIED_CONSUMER_ESTIMATE',
        'authority' => [
            'cooling_load_table' => 'SEE_HVAC_CALCULATOR_RULES_AND_CALIBRATION_EVIDENCE',
            'engineering_standard_verified' => false,
        ],
        'w_to_btu' => 3.412,
        'btu_per_hp' => 9000,
        'baseline_ceiling_m' => 3.0,

        // Frozen adjustment contract shared intentionally by v1 and v2.
        'sunlight_multiplier' => 1.10,
        'heat_equipment_multiplier' => 1.10,
        'people_included_in_base' => 10,
        'extra_person_btu' => 400,
        'adjustment_scope' => 'ADJUSTMENT_SCOPE_REVIEW_PENDING',

        'input_bounds' => [
            'area_m2' => ['min' => 5, 'max' => 5000],
            'ceiling_height' => ['min' => 2, 'max' => 15],
            'people_count' => ['min' => 0, 'max' => 5000],
        ],
        'standard_tiers' => [
            9000, 12000, 18000, 24000, 28000, 30000, 36000,
            42000, 45000, 48000, 50000, 60000, 100000,
        ],
        'area_ranges' => [
            9000 => '10 - 15 m²',
            12000 => '15 - 20 m²',
            18000 => '20 - 30 m²',
            24000 => '25 - 40 m²',
            28000 => '35 - 48 m²',
            30000 => '38 - 52 m²',
            36000 => '45 - 62 m²',
            42000 => '55 - 72 m²',
            45000 => '58 - 78 m²',
            48000 => '60 - 83 m²',
            50000 => '65 - 86 m²',
            60000 => '80 - 103 m²',
            100000 => '100 m² trở lên',
        ],
        'required_inputs' => [
            'area_m2',
            'ceiling_height',
            'space_type',
        ],
        'faq' => [
            [
                'question' => 'Nên tính theo diện tích hay thể tích?',
                'answer' => 'Theo diện tích phù hợp để ước tính nhanh cho không gian có chiều cao thông thường. Theo thể tích đưa chiều cao trực tiếp vào thể tích cần làm lạnh và hữu ích khi trần cao hoặc thấp hơn thông thường. Cả hai đều là phương pháp ước tính tham khảo theo quy tắc của dự án, không thay thế khảo sát kỹ thuật.',
            ],
            [
                'question' => 'BTU là gì? Tại sao phải chọn đúng nhóm công suất?',
                'answer' => 'BTU/h là đơn vị biểu thị công suất làm lạnh. Công cụ này ước tính nhu cầu tham khảo từ diện tích, loại không gian và các yếu tố tăng tải; kết quả thực tế vẫn cần được kiểm tra theo điều kiện công trình.',
            ],
            [
                'question' => 'Điều hòa 24.000 BTU phù hợp với diện tích bao nhiêu?',
                'answer' => 'Không có một diện tích cố định cho mọi công trình. Cùng một diện tích nhưng loại không gian, chiều cao trần, số người, nắng trực tiếp và thiết bị sinh nhiệt có thể làm thay đổi kết quả. Hãy nhập điều kiện thực tế vào công cụ để nhận ước tính tham khảo.',
            ],
            [
                'question' => '1 HP, 1,5 HP và 2 HP được hiểu như thế nào?',
                'answer' => 'Trong công cụ, HP là nhãn nhóm công suất thị trường quy ước từ BTU/h với mốc cấu hình 9.000 BTU/h cho 1 HP. Đây không phải phép quy đổi công suất cơ học 746 W và không thay thế thông số rated capacity của model.',
            ],
            [
                'question' => 'Nên chọn inverter hay on/off cho điều hòa tủ đứng?',
                'answer' => 'Inverter hỗ trợ máy nén điều chỉnh tải theo nhu cầu vận hành. Hiệu quả điện năng và độ ồn cần đối chiếu theo thông số từng model, điều kiện lắp đặt và thời gian sử dụng thực tế.',
            ],
        ],
    ],
];
