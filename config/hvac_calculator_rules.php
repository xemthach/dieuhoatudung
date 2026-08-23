<?php

return [
    'active' => [
        'area' => 'consumer-estimate-v2',
        'volume' => 'volume-consumer-estimate-v2',
    ],

    'rules' => [
        'consumer-estimate-v1' => [
            'method' => 'area',
            'factor_profile' => 'v1',
            'factor_unit' => 'W/m²',
            'methodology' => 'SIMPLIFIED_CONSUMER_ESTIMATE',
            'status' => 'FROZEN_FOR_HISTORY_REPLAY',
        ],
        'volume-consumer-estimate-v1' => [
            'method' => 'volume',
            'derived_from' => 'consumer-estimate-v1',
            'reference_height_m' => 3.0,
            'factor_unit' => 'W/m³',
            'methodology' => 'CONFIGURED_CONSUMER_VOLUME_ESTIMATE',
            'authority' => 'PROJECT_DERIVED_FROM_AREA_V1',
            'status' => 'FROZEN_FOR_HISTORY_REPLAY',
        ],
        'consumer-estimate-v2' => [
            'method' => 'area',
            'factor_profile' => 'v2',
            'factor_unit' => 'W/m²',
            'methodology' => 'CATEGORY_CALIBRATED_CONSUMER_ESTIMATE',
            'status' => 'ACTIVE_HYBRID',
            'adjustment_scope' => 'ADJUSTMENT_SCOPE_REVIEW_PENDING',
        ],
        'volume-consumer-estimate-v2' => [
            'method' => 'volume',
            'derived_from' => 'consumer-estimate-v2',
            'reference_height_m' => 3.0,
            'factor_unit' => 'W/m³',
            'methodology' => 'CATEGORY_CALIBRATED_CONSUMER_VOLUME_ESTIMATE',
            'authority' => 'PROJECT_DERIVED_FROM_AREA_V2',
            'status' => 'ACTIVE_HYBRID',
            'adjustment_scope' => 'ADJUSTMENT_SCOPE_REVIEW_PENDING',
        ],
    ],

    /*
     * W/m² is the sole canonical factor unit. Volume factors are derived by the
     * resolver from the selected area profile and reference height; no second
     * independently editable W/m³ table exists.
     */
    'space_types' => [
        'nha_o' => ['label_vi' => 'Căn hộ, nhà ở', 'label_en' => 'Apartments, Residence', 'group' => 'Nhà ở', 'v1' => 120, 'v2' => 175.843, 'reference_btu_m2' => 600, 'confidence' => 'HIGH', 'source' => 'Panasonic Vietnam 600 BTU/h/m² consumer baseline', 'activation' => 'V2_CALIBRATED'],
        'phong_khach' => ['label_vi' => 'Phòng khách (nhà ở)', 'label_en' => 'Residence living room', 'group' => 'Nhà ở', 'v1' => 120, 'v2' => 205.150, 'reference_btu_m2' => 700, 'confidence' => 'MEDIUM', 'source' => 'Approved lower reference bound 700 BTU/h/m²', 'activation' => 'V2_CALIBRATED'],
        'khach_san' => ['label_vi' => 'Khách sạn, nhà nghỉ', 'label_en' => 'Hotel, Motel Rooms', 'group' => 'Nhà ở', 'v1' => 120, 'v2' => 219.803, 'reference_btu_m2' => 750, 'confidence' => 'HIGH', 'source' => 'Panasonic Vietnam hotel range midpoint 700–800 BTU/h/m²', 'activation' => 'V2_CALIBRATED'],
        'van_phong' => ['label_vi' => 'Văn phòng (viền ngoài)', 'label_en' => 'Office - General (perimeter)', 'group' => 'Văn phòng', 'v1' => 170, 'v2' => 219.803, 'reference_btu_m2' => 750, 'confidence' => 'HIGH', 'source' => 'Panasonic Vietnam office range midpoint 700–800 BTU/h/m²', 'activation' => 'V2_CALIBRATED'],
        'van_phong_interior' => ['label_vi' => 'Văn phòng (bên trong)', 'label_en' => 'Office - General (interior)', 'group' => 'Văn phòng', 'v1' => 100, 'v2' => 205.150, 'reference_btu_m2' => 700, 'confidence' => 'MEDIUM', 'source' => 'Approved lower office bound; source has no interior distinction', 'activation' => 'V2_CALIBRATED'],
        'van_phong_private' => ['label_vi' => 'Văn phòng cá nhân', 'label_en' => 'Office - Private', 'group' => 'Văn phòng', 'v1' => 180, 'v2' => 219.803, 'reference_btu_m2' => 750, 'confidence' => 'MEDIUM', 'source' => 'Approved office midpoint; source has no private-office distinction', 'activation' => 'V2_CALIBRATED'],
        'cua_hang' => ['label_vi' => 'Cửa hàng', 'label_en' => 'Clothing / Shoe Stores', 'group' => 'Thương mại', 'v1' => 165, 'v2' => 234.457, 'reference_btu_m2' => 800, 'confidence' => 'MEDIUM', 'source' => 'Approved calibration retail reference 800 BTU/h/m²', 'activation' => 'V2_CALIBRATED'],
        'sieu_thi' => ['label_vi' => 'Siêu thị', 'label_en' => 'Supermarkets', 'group' => 'Thương mại', 'v1' => 160, 'v2' => 160, 'reference_btu_m2' => 900, 'confidence' => 'LOW', 'source' => 'Semantic variation remains unresolved', 'activation' => 'V1_RETAINED_REVIEW_PENDING'],
        'showroom' => ['label_vi' => 'Showroom', 'label_en' => 'Showroom (commercial)', 'group' => 'Thương mại', 'v1' => 300, 'v2' => 300, 'reference_btu_m2' => 1023.64, 'confidence' => 'LOW', 'source' => 'No showroom-specific source', 'activation' => 'V1_RETAINED_REVIEW_PENDING'],
        'ngan_hang' => ['label_vi' => 'Ngân hàng', 'label_en' => 'Banks', 'group' => 'Thương mại', 'v1' => 175, 'v2' => 249.110, 'reference_btu_m2' => 850, 'confidence' => 'MEDIUM', 'source' => 'Approved calibration bank reference 850 BTU/h/m²', 'activation' => 'V2_CALIBRATED'],
        'nha_hang' => ['label_vi' => 'Nhà hàng', 'label_en' => 'Restaurants', 'group' => 'F&B', 'v1' => 330, 'v2' => 278.417, 'reference_btu_m2' => 950, 'confidence' => 'HIGH', 'source' => 'Panasonic Vietnam restaurant range midpoint 900–1000 BTU/h/m²', 'activation' => 'V2_CALIBRATED'],
        'cafe' => ['label_vi' => 'Quán cà phê', 'label_en' => 'Cafeterias', 'group' => 'F&B', 'v1' => 350, 'v2' => 278.417, 'reference_btu_m2' => 950, 'confidence' => 'HIGH', 'source' => 'Panasonic Vietnam cafe range midpoint 900–1000 BTU/h/m²', 'activation' => 'V2_CALIBRATED'],
        'fastfood' => ['label_vi' => 'Thức ăn nhanh, giải khát', 'label_en' => 'Milk Bars, Fast food', 'group' => 'F&B', 'v1' => 270, 'v2' => 293.071, 'reference_btu_m2' => 1000, 'confidence' => 'MEDIUM', 'source' => 'Approved upper Panasonic restaurant range for higher turnover', 'activation' => 'V2_CALIBRATED'],
        'hoi_truong' => ['label_vi' => 'Hội trường, giảng đường', 'label_en' => 'Auditorium', 'group' => 'Hội trường / Giáo dục', 'v1' => 280, 'v2' => 278.417, 'reference_btu_m2' => 950, 'confidence' => 'HIGH', 'source' => 'Panasonic Vietnam hall range midpoint 900–1000 BTU/h/m²', 'activation' => 'V2_CALIBRATED'],
        'phong_hop' => ['label_vi' => 'Phòng họp', 'label_en' => 'Conference Rooms', 'group' => 'Hội trường / Giáo dục', 'v1' => 275, 'v2' => 275, 'reference_btu_m2' => 938.34, 'confidence' => 'LOW', 'source' => 'Available references disagree materially', 'activation' => 'V1_RETAINED_REVIEW_PENDING'],
        'phong_hoc' => ['label_vi' => 'Phòng học', 'label_en' => 'Classroom', 'group' => 'Hội trường / Giáo dục', 'v1' => 95, 'v2' => 95, 'reference_btu_m2' => 324.15, 'confidence' => 'LOW', 'source' => 'Classroom and occupancy scope unresolved', 'activation' => 'V1_RETAINED_REVIEW_PENDING'],
        'thu_vien' => ['label_vi' => 'Thư viện', 'label_en' => 'Library', 'group' => 'Hội trường / Giáo dục', 'v1' => 150, 'v2' => 249.110, 'reference_btu_m2' => 850, 'confidence' => 'MEDIUM', 'source' => 'Approved calibration library/museum reference 850 BTU/h/m²', 'activation' => 'V2_CALIBRATED'],
        'rap_hat' => ['label_vi' => 'Rạp hát', 'label_en' => 'Theatres', 'group' => 'Hội trường / Giáo dục', 'v1' => 280, 'v2' => 280, 'reference_btu_m2' => 955.40, 'confidence' => 'LOW', 'source' => 'Available reference is not theatre-specific', 'activation' => 'V1_RETAINED_REVIEW_PENDING'],
        'benh_vien' => ['label_vi' => 'Bệnh viện, phòng khám', 'label_en' => 'Clinics', 'group' => 'Y tế', 'v1' => 190, 'v2' => 190, 'reference_btu_m2' => 648.31, 'confidence' => 'LOW', 'source' => 'Healthcare-specific authority pending', 'activation' => 'V1_RETAINED_REVIEW_PENDING'],
        'phong_duoc' => ['label_vi' => 'Văn phòng dược', 'label_en' => 'Medical Offices', 'group' => 'Y tế', 'v1' => 185, 'v2' => 185, 'reference_btu_m2' => 631.25, 'confidence' => 'LOW', 'source' => 'Medical-office authority pending', 'activation' => 'V1_RETAINED_REVIEW_PENDING'],
        'nha_xuong' => ['label_vi' => 'Nhà xưởng (CN nhẹ)', 'label_en' => 'Factory Light Manufacture', 'group' => 'Công nghiệp', 'v1' => 275, 'v2' => 275, 'reference_btu_m2' => 938.34, 'confidence' => 'LOW', 'source' => 'Process-load authority pending', 'activation' => 'V1_RETAINED_REVIEW_PENDING'],
        'nha_xuong_nang' => ['label_vi' => 'Nhà xưởng (CN nặng)', 'label_en' => 'Factory Heavy Manufacture', 'group' => 'Công nghiệp', 'v1' => 490, 'v2' => 490, 'reference_btu_m2' => 1671.95, 'confidence' => 'LOW', 'source' => 'Process-load authority pending', 'activation' => 'V1_RETAINED_REVIEW_PENDING'],
        'phong_may_tinh' => ['label_vi' => 'Phòng máy tính / Server', 'label_en' => 'Computer Room', 'group' => 'Đặc biệt', 'v1' => 480, 'v2' => 480, 'reference_btu_m2' => 1637.83, 'confidence' => 'LOW', 'source' => 'Equipment-specific sizing required', 'activation' => 'V1_RETAINED_REVIEW_PENDING'],
        'phong_thi_nghiem' => ['label_vi' => 'Phòng thí nghiệm', 'label_en' => 'Laboratory', 'group' => 'Đặc biệt', 'v1' => 230, 'v2' => 230, 'reference_btu_m2' => 784.79, 'confidence' => 'LOW', 'source' => 'Laboratory-specific authority pending', 'activation' => 'V1_RETAINED_REVIEW_PENDING'],
        'tham_my_vien' => ['label_vi' => 'Thẩm mỹ viện', 'label_en' => 'Beauty shops', 'group' => 'Đặc biệt', 'v1' => 260, 'v2' => 260, 'reference_btu_m2' => 887.16, 'confidence' => 'LOW', 'source' => 'Beauty-shop-specific authority pending', 'activation' => 'V1_RETAINED_REVIEW_PENDING'],
        'sanh_hanh_lang' => ['label_vi' => 'Sảnh, hành lang', 'label_en' => 'Mall', 'group' => 'Đặc biệt', 'v1' => 135, 'v2' => 135, 'reference_btu_m2' => 460.64, 'confidence' => 'LOW', 'source' => 'Lobby and corridor semantics must be split', 'activation' => 'V1_RETAINED_REVIEW_PENDING'],
        'tang_ham' => ['label_vi' => 'Tầng hầm', 'label_en' => 'Basement', 'group' => 'Đặc biệt', 'v1' => 125, 'v2' => 125, 'reference_btu_m2' => 426.52, 'confidence' => 'LOW', 'source' => 'Basement-specific authority pending', 'activation' => 'V1_RETAINED_REVIEW_PENDING'],
    ],
];
