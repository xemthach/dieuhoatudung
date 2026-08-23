<?php

return [
    'model_max_oversize_delta_btu' => 12000,

    /*
     * MARKET_REFERENCE_ENVELOPE and SITE_CATALOG_ENVELOPE are intentionally
     * separate. These are consumer advisory boundaries, not a load-design
     * standard and not universal manufacturer limits.
     */
    'types' => [
        'unsure' => [
            'label' => 'Chưa xác định / Cần tư vấn',
            'short_label' => 'Chưa xác định',
            'selectable' => true,
            'sort' => 0,
            'market_min_btu' => null,
            'market_common_max_btu' => null,
            'market_verified_max_btu' => null,
            'confidence' => 'HIGH',
            'rule_status' => 'USER_PREFERENCE_NOT_PROVIDED',
            'installation_notes' => [],
            'sources' => [],
        ],
        'wall_mounted' => [
            'label' => 'Điều hòa treo tường',
            'short_label' => 'Treo tường',
            'selectable' => true,
            'sort' => 10,
            'market_min_btu' => 8700,
            'market_common_max_btu' => 24000,
            'market_verified_max_btu' => 24000,
            'confidence' => 'MEDIUM',
            'rule_status' => 'MARKET_REFERENCE_ONLY_SITE_CATALOG_EMPTY',
            'installation_notes' => [
                'Cần vị trí treo dàn lạnh và dàn nóng phù hợp.',
                'Dải công suất tham chiếu không đồng nghĩa mọi model đều phù hợp công trình.',
            ],
            'sources' => [
                ['level' => 'A', 'manufacturer' => 'Panasonic Vietnam', 'range' => '8.700–20.800 BTU/h rated', 'url' => 'https://www.panasonic.com/vn/air-solutions/products/air-conditioner/single-split-wall-mount-air-conditioner/inverter-cooling-only/aero-elite-inveter-xu-series-zkh-8.html'],
                ['level' => 'A', 'manufacturer' => 'LG Vietnam', 'range' => '24.000 BTU/h', 'url' => 'https://www.lg.com/vn/dieu-hoa/f24ce/'],
            ],
        ],
        'cassette' => [
            'label' => 'Điều hòa âm trần cassette',
            'short_label' => 'Cassette',
            'selectable' => true,
            'sort' => 20,
            'market_min_btu' => 11600,
            'market_common_max_btu' => 47800,
            'market_verified_max_btu' => 48500,
            'confidence' => 'HIGH',
            'rule_status' => 'ACTIVE_VERIFIED_REFERENCE',
            'installation_question' => 'cassette_ceiling_clearance',
            'installation_notes' => [
                'Cần kiểm tra khoảng không trần và kích thước dàn lạnh/mặt nạ.',
                'Cần kiểm tra thoát nước ngưng, vị trí dàn nóng, nguồn điện và đường ống.',
            ],
            'sources' => [
                ['level' => 'A', 'manufacturer' => 'Panasonic Vietnam', 'range' => '11.600–48.500 BTU/h', 'url' => 'https://www.panasonic.com/vn/air-solutions/product-top/air-conditioner/cassette.html'],
            ],
        ],
        'ducted' => [
            'label' => 'Điều hòa giấu trần nối ống gió',
            'short_label' => 'Nối ống gió',
            'selectable' => true,
            'sort' => 30,
            'market_min_btu' => 17100,
            'market_common_max_btu' => 47800,
            'market_verified_max_btu' => 47800,
            'confidence' => 'MEDIUM',
            'rule_status' => 'CAPACITY_GATE_ONLY_INSTALLATION_REVIEW_REQUIRED',
            'installation_question' => 'duct_space',
            'always_review' => true,
            'installation_notes' => [
                'Cần thiết kế đường ống gió, lưu lượng và áp suất tĩnh.',
                'Cần kiểm tra trần kỹ thuật, thoát nước, nguồn điện và đường bảo trì.',
            ],
            'sources' => [
                ['level' => 'A', 'manufacturer' => 'Panasonic Vietnam', 'range' => '17.100–47.800 BTU/h', 'url' => 'https://www.panasonic.com/vn/air-solutions/product-top/air-conditioner/ducted.html'],
            ],
        ],
        'ceiling_exposed' => [
            'label' => 'Điều hòa áp trần',
            'short_label' => 'Áp trần',
            'selectable' => true,
            'sort' => 40,
            'market_min_btu' => 20500,
            'market_common_max_btu' => 47800,
            'market_verified_max_btu' => 60000,
            'confidence' => 'HIGH',
            'rule_status' => 'ACTIVE_MARKET_REFERENCE_SITE_CANONICAL_CAPACITY_EMPTY',
            'installation_notes' => [
                'Cần kiểm tra kết cấu/vị trí treo và hướng phân phối gió.',
                'Một số model công suất lớn dùng nguồn điện 3 pha; phải kiểm tra đúng model.',
            ],
            'sources' => [
                ['level' => 'A', 'manufacturer' => 'Panasonic Vietnam', 'range' => '20.500–60.000 BTU/h', 'url' => 'https://www.panasonic.com/vn/air-solutions/product-top/air-conditioner/ceiling-exposed.html'],
            ],
        ],
        'floor_standing' => [
            'label' => 'Điều hòa tủ đứng',
            'short_label' => 'Tủ đứng',
            'selectable' => true,
            'sort' => 50,
            'market_min_btu' => 20500,
            'market_common_max_btu' => 47750,
            'market_verified_max_btu' => 47750,
            'confidence' => 'HIGH',
            'rule_status' => 'ACTIVE_MARKET_REFERENCE',
            'installation_notes' => [
                'Cần mặt sàn và khoảng thoáng phân phối gió phù hợp.',
                'Nguồn điện, vị trí dàn nóng và đường ống phải được kiểm tra theo model.',
            ],
            'sources' => [
                ['level' => 'A', 'manufacturer' => 'Panasonic Vietnam', 'range' => '20.500–47.750 BTU/h', 'url' => 'https://www.panasonic.com/vn/air-solutions/product-top/air-conditioner/floor-standing.html'],
            ],
        ],
    ],
];
