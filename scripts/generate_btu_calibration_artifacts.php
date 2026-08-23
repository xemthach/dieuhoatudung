<?php

declare(strict_types=1);

use App\Enums\ProductHvacClass;
use App\Models\Product;
use App\Services\Calculator\BtuCalculatorService;
use App\Services\Calculator\CalculatorRuleSetResolver;
use App\Services\Product\ProductHvacClassResolver;
use App\Services\Product\ProductMarketingCapacityQueryAdapter;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Schema;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

const ANALYSIS_W_TO_BTU = 3.412141633;

$artifactDir = dirname(__DIR__).'/docs/reports/final/artifacts';
if (! is_dir($artifactDir) && ! mkdir($artifactDir, 0775, true) && ! is_dir($artifactDir)) {
    throw new RuntimeException("Cannot create {$artifactDir}");
}

$service = app(BtuCalculatorService::class);
$ruleSets = app(CalculatorRuleSetResolver::class);
$areaFactors = $ruleSets->resolve('area', 'consumer-estimate-v1')['space_types'];
$volumeFactors = $ruleSets->resolve('volume', 'volume-consumer-estimate-v1')['space_types'];
$tiers = array_map('intval', config('hvac.btu.standard_tiers', []));

$writeCsv = static function (string $path, array $header, iterable $rows): void {
    $handle = fopen($path, 'wb');
    if ($handle === false) {
        throw new RuntimeException("Cannot write {$path}");
    }
    fputcsv($handle, $header);
    foreach ($rows as $row) {
        fputcsv($handle, $row);
    }
    fclose($handle);
};

$roundTier = static function (float $btu) use ($tiers): int {
    foreach ($tiers as $tier) {
        if ($btu <= $tier) {
            return $tier;
        }
    }

    return (int) (ceil($btu / 1000) * 1000);
};

$marketingHp = static fn (int $btu): float => round($btu / 9000, 1);

// Explicit semantic mappings. Missing/ambiguous mappings are intentionally not inferred.
$references = [
    'nha_o' => ['class' => 'residential', 'quality' => 'CLOSE', 'low' => 600, 'typical' => 650, 'high' => 700, 'source' => 'Panasonic 600 + user residential 700'],
    'phong_khach' => ['class' => 'living_dining', 'quality' => 'CLOSE', 'low' => 600, 'typical' => 725, 'high' => 850, 'source' => 'Panasonic residential + user living/dining 850'],
    'khach_san' => ['class' => 'hotel_bedroom', 'quality' => 'EXACT', 'low' => 700, 'typical' => 750, 'high' => 800, 'source' => 'Panasonic Vietnam hotel 700-800'],
    'van_phong' => ['class' => 'office', 'quality' => 'EXACT', 'low' => 700, 'typical' => 750, 'high' => 800, 'source' => 'Panasonic Vietnam office 700-800'],
    'van_phong_interior' => ['class' => 'office_interior', 'quality' => 'CLOSE', 'low' => 700, 'typical' => 750, 'high' => 800, 'source' => 'Panasonic office; no interior distinction'],
    'van_phong_private' => ['class' => 'private_office', 'quality' => 'CLOSE', 'low' => 700, 'typical' => 750, 'high' => 800, 'source' => 'Panasonic office; no private distinction'],
    'cua_hang' => ['class' => 'retail', 'quality' => 'EXACT', 'low' => 750, 'typical' => 800, 'high' => 850, 'source' => 'User-supplied retail 800'],
    'sieu_thi' => ['class' => 'retail_supermarket', 'quality' => 'CLOSE', 'low' => 800, 'typical' => 900, 'high' => 1000, 'source' => 'User retail/shopping-center references'],
    'showroom' => ['class' => 'showroom', 'quality' => 'AMBIGUOUS', 'low' => null, 'typical' => null, 'high' => null, 'source' => 'No semantically safe direct reference'],
    'ngan_hang' => ['class' => 'bank', 'quality' => 'EXACT', 'low' => 800, 'typical' => 850, 'high' => 900, 'source' => 'User-supplied bank 850'],
    'nha_hang' => ['class' => 'restaurant', 'quality' => 'EXACT', 'low' => 900, 'typical' => 950, 'high' => 1000, 'source' => 'Panasonic Vietnam restaurant 900-1000'],
    'cafe' => ['class' => 'cafe', 'quality' => 'EXACT', 'low' => 900, 'typical' => 950, 'high' => 1000, 'source' => 'Panasonic Vietnam cafe 900-1000'],
    'fastfood' => ['class' => 'fast_food', 'quality' => 'CLOSE', 'low' => 900, 'typical' => 950, 'high' => 1000, 'source' => 'Panasonic restaurant/cafe range'],
    'hoi_truong' => ['class' => 'auditorium', 'quality' => 'EXACT', 'low' => 900, 'typical' => 950, 'high' => 1000, 'source' => 'Panasonic Vietnam hall 900-1000'],
    'phong_hop' => ['class' => 'meeting_hall', 'quality' => 'CLOSE', 'low' => 1000, 'typical' => 1250, 'high' => 1500, 'source' => 'User-supplied meeting hall 1500; Panasonic hall 900-1000'],
    'phong_hoc' => ['class' => 'classroom', 'quality' => 'NO_MATCH', 'low' => null, 'typical' => null, 'high' => null, 'source' => 'No supplied comparable class'],
    'thu_vien' => ['class' => 'library', 'quality' => 'EXACT', 'low' => 800, 'typical' => 850, 'high' => 900, 'source' => 'User-supplied library/museum 850'],
    'rap_hat' => ['class' => 'theatre', 'quality' => 'NO_MATCH', 'low' => null, 'typical' => null, 'high' => null, 'source' => 'Dance hall is not theatre'],
    'benh_vien' => ['class' => 'clinic', 'quality' => 'NO_MATCH', 'low' => null, 'typical' => null, 'high' => null, 'source' => 'No supplied comparable class'],
    'phong_duoc' => ['class' => 'medical_office', 'quality' => 'AMBIGUOUS', 'low' => null, 'typical' => null, 'high' => null, 'source' => 'Generic office mapping may hide medical loads'],
    'nha_xuong' => ['class' => 'light_factory', 'quality' => 'NO_MATCH', 'low' => null, 'typical' => null, 'high' => null, 'source' => 'No supplied industrial reference'],
    'nha_xuong_nang' => ['class' => 'heavy_factory', 'quality' => 'NO_MATCH', 'low' => null, 'typical' => null, 'high' => null, 'source' => 'No supplied industrial reference'],
    'phong_may_tinh' => ['class' => 'server_room', 'quality' => 'NO_MATCH', 'low' => null, 'typical' => null, 'high' => null, 'source' => 'Generic office references are inapplicable'],
    'phong_thi_nghiem' => ['class' => 'laboratory', 'quality' => 'NO_MATCH', 'low' => null, 'typical' => null, 'high' => null, 'source' => 'No supplied laboratory reference'],
    'tham_my_vien' => ['class' => 'beauty_shop', 'quality' => 'NO_MATCH', 'low' => null, 'typical' => null, 'high' => null, 'source' => 'No supplied comparable class'],
    'sanh_hanh_lang' => ['class' => 'lobby_or_corridor', 'quality' => 'AMBIGUOUS', 'low' => null, 'typical' => null, 'high' => null, 'source' => 'Panasonic lobby 900-1000 does not safely map to corridor'],
    'tang_ham' => ['class' => 'basement', 'quality' => 'NO_MATCH', 'low' => null, 'typical' => null, 'high' => null, 'source' => 'No supplied comparable class'],
];

$proposals = [
    'nha_o' => [175.843, 'HIGH', '600 BTU/m² Panasonic Vietnam baseline; precision prevents false tier crossing'],
    'phong_khach' => [205.150, 'MEDIUM', '700 BTU/m² lower reference bound; avoid automatic 850 jump'],
    'khach_san' => [219.803, 'HIGH', '750 BTU/m² midpoint of Panasonic hotel 700-800'],
    'van_phong' => [219.803, 'HIGH', '750 BTU/m² midpoint of Panasonic office 700-800'],
    'van_phong_interior' => [205.150, 'MEDIUM', '700 BTU/m² lower bound because source lacks interior distinction'],
    'van_phong_private' => [219.803, 'MEDIUM', '750 BTU/m²; source lacks private-office distinction'],
    'cua_hang' => [234.457, 'MEDIUM', 'User retail 800 BTU/m²; provenance not independently proven'],
    'sieu_thi' => [263.764, 'LOW', '900 BTU/m² between retail and shopping-center references; semantic variation high'],
    'showroom' => [300, 'LOW', 'Keep v1 pending a showroom-specific source'],
    'ngan_hang' => [249.110, 'MEDIUM', 'User bank 850 BTU/m²'],
    'nha_hang' => [278.417, 'HIGH', '950 BTU/m² midpoint of Panasonic restaurant 900-1000'],
    'cafe' => [278.417, 'HIGH', '950 BTU/m² midpoint of Panasonic cafe 900-1000'],
    'fastfood' => [293.071, 'MEDIUM', '1000 BTU/m² upper Panasonic restaurant range for higher turnover'],
    'hoi_truong' => [278.417, 'HIGH', '950 BTU/m² midpoint of Panasonic hall 900-1000'],
    'phong_hop' => [275, 'LOW', 'Keep v1: user 1500 and Panasonic 900-1000 disagree materially'],
    'phong_hoc' => [95, 'LOW', 'Keep v1 pending classroom-specific authority and occupancy scope'],
    'thu_vien' => [249.110, 'MEDIUM', 'User library/museum 850 BTU/m²'],
    'rap_hat' => [280, 'LOW', 'Keep v1; dance-hall reference is not theatre'],
    'benh_vien' => [190, 'LOW', 'Keep v1 pending healthcare-specific authority'],
    'phong_duoc' => [185, 'LOW', 'Keep v1 pending medical-office authority'],
    'nha_xuong' => [275, 'LOW', 'Keep v1 pending process-load authority'],
    'nha_xuong_nang' => [490, 'LOW', 'Keep v1 pending process-load authority'],
    'phong_may_tinh' => [480, 'LOW', 'Keep v1; IT load requires equipment-specific sizing'],
    'phong_thi_nghiem' => [230, 'LOW', 'Keep v1 pending laboratory-specific authority'],
    'tham_my_vien' => [260, 'LOW', 'Keep v1 pending beauty-shop-specific authority'],
    'sanh_hanh_lang' => [135, 'LOW', 'Keep v1; split lobby from corridor before calibration'],
    'tang_ham' => [125, 'LOW', 'Keep v1 pending basement-specific authority'],
];

$inventoryRows = [];
$gapRows = [];
$proposalRows = [];
foreach ($areaFactors as $key => $factor) {
    $areaW = (float) $factor['w_per_m2'];
    $volumeW = (float) ($volumeFactors[$key]['w_per_m3'] ?? ($areaW / 3));
    $reference = $references[$key];
    $currentBtuM2 = $areaW * ANALYSIS_W_TO_BTU;
    $typical = $reference['typical'];
    $delta = $typical === null ? null : (($currentBtuM2 - $typical) / $typical) * 100;
    $classification = match (true) {
        $typical === null => 'NO_COMPARABLE_REFERENCE',
        $currentBtuM2 < $reference['low'] => 'LOWER_THAN_REFERENCE',
        $currentBtuM2 > $reference['high'] => 'HIGHER_THAN_REFERENCE',
        default => 'WITHIN_REFERENCE',
    };
    $severity = match (true) {
        $delta === null => 'INFO',
        abs($delta) >= 35 => 'HIGH',
        abs($delta) >= 20 => 'MEDIUM',
        abs($delta) >= 10 => 'LOW',
        default => 'INFO',
    };
    $doubleCountRisk = match (true) {
        in_array($key, ['nha_hang', 'cafe', 'fastfood', 'phong_may_tinh', 'nha_xuong', 'nha_xuong_nang', 'phong_thi_nghiem'], true) => 'HIGH_REVIEW',
        in_array($key, ['van_phong', 'van_phong_interior', 'van_phong_private', 'cua_hang', 'sieu_thi', 'phong_hop', 'hoi_truong', 'phong_hoc'], true) => 'MEDIUM_REVIEW',
        default => 'UNKNOWN',
    };

    $inventoryRows[] = [
        $key, $factor['label_vi'], $areaW, round($currentBtuM2, 3), round($volumeW, 6),
        round($volumeW * ANALYSIS_W_TO_BTU, 3), 'YES_AREA_DIVIDED_BY_3M',
        'consumer-estimate-v1', 'volume-consumer-estimate-v1',
        'UNKNOWN', $doubleCountRisk,
    ];
    $gapRows[] = [
        $key, $factor['label_vi'], $reference['class'], $reference['quality'], $areaW,
        round($currentBtuM2, 3), $reference['low'], $typical, $reference['high'],
        $delta === null ? null : round($delta, 2), $classification, $severity,
        $reference['source'],
    ];

    [$proposed, $confidence, $reason] = $proposals[$key];
    $proposalRows[] = [
        $key, $factor['label_vi'], $areaW, $proposed, $proposed - $areaW,
        round((($proposed - $areaW) / $areaW) * 100, 2), round($proposed * ANALYSIS_W_TO_BTU, 2),
        round($proposed / 3, 6), $confidence, $reason,
        $confidence === 'LOW' ? 'REVIEW_ONLY_KEEP_V1' : 'PROPOSAL_NOT_ACTIVATED',
    ];
}

$writeCsv($artifactDir.'/calculator_v1_factor_inventory.csv', [
    'key', 'label', 'area_factor_w_m2', 'area_factor_btu_m2', 'volume_factor_w_m3',
    'volume_factor_btu_m3', 'derived', 'area_rule_version', 'volume_rule_version',
    'base_factor_scope', 'double_count_risk',
], $inventoryRows);

$writeCsv($artifactDir.'/calculator_factor_gap_matrix.csv', [
    'key', 'label', 'reference_class', 'mapping_quality', 'current_w_m2', 'current_btu_m2',
    'reference_low_btu_m2', 'reference_typical_btu_m2', 'reference_high_btu_m2',
    'delta_vs_typical_percent', 'classification', 'severity', 'reference_evidence',
], $gapRows);

$writeCsv($artifactDir.'/calculator_v2_factor_proposal.csv', [
    'key', 'label', 'current_w_m2', 'proposed_w_m2', 'delta_w_m2', 'delta_percent',
    'proposed_btu_m2', 'derived_volume_w_m3', 'confidence', 'reason', 'activation_status',
], $proposalRows);

$compoundRows = [];
foreach ([9000, 12000, 18000, 24000, 36000, 60000] as $baseBtu) {
    $sunOnly = (int) round($baseBtu * 1.10);
    $equipmentOnly = (int) round($baseBtu * 1.10);
    $compound = (int) round($sunOnly * 1.10);
    $additive = (int) round($baseBtu * 1.20);
    $compoundRows[] = [
        $baseBtu, $sunOnly, $equipmentOnly, $compound, $additive, $compound - $additive,
        round((($compound - $additive) / $baseBtu) * 100, 2),
        $roundTier($compound), $roundTier($additive),
    ];
}
$writeCsv($artifactDir.'/calculator_adjustment_compounding.csv', [
    'base_btu', 'sun_10_result', 'equipment_10_result', 'compound_1_21_result',
    'additive_1_20_result', 'raw_delta_btu', 'delta_percent_of_base',
    'compound_market_tier', 'additive_market_tier',
], $compoundRows);

$benchmarkSpaces = [
    'nha_o' => 700,
    'phong_khach' => 850,
    'van_phong' => 800,
    'cua_hang' => 800,
    'nha_hang' => 950,
    'phong_hop' => 1500,
    'showroom' => 1000,
];
$benchmarkRows = [];
foreach (array_keys($benchmarkSpaces) as $spaceKey) {
    foreach ([10, 15, 20, 25, 30, 40, 50, 70, 100] as $area) {
        foreach ([2.5, 2.7, 3.0, 3.5, 4.0, 5.0] as $height) {
            $areaW = (float) $areaFactors[$spaceKey]['w_per_m2'];
            $base = (int) round($area * $areaW * 3.412);
            $methodA = $height > 3 ? (int) round($base * round($height / 3, 2)) : $base;
            $methodB = (int) round($area * $height * (float) $volumeFactors[$spaceKey]['w_per_m3'] * 3.412);
            $ref600 = (int) round($area * 600);
            $ref200 = (int) round($area * $height * 200);
            $ref225 = (int) round($area * $height * 225);
            $userRef = (int) round($area * $benchmarkSpaces[$spaceKey]);
            $benchmarkRows[] = [
                $spaceKey, $areaFactors[$spaceKey]['label_vi'], $area, $height,
                $methodA, $roundTier($methodA), $marketingHp($roundTier($methodA)),
                $methodB, $roundTier($methodB), $marketingHp($roundTier($methodB)),
                $ref600, $roundTier($ref600), $ref200, $roundTier($ref200),
                $ref225, $roundTier($ref225), $userRef, $roundTier($userRef),
                ($roundTier($methodB) - $roundTier($methodA)) / 9000,
            ];
        }
    }
}
$writeCsv($artifactDir.'/calculator_method_benchmark.csv', [
    'space_key', 'space_label', 'area_m2', 'height_m',
    'method_a_raw_btu', 'method_a_tier', 'method_a_marketing_hp',
    'method_b_raw_btu', 'method_b_tier', 'method_b_marketing_hp',
    'reference_600_raw_btu', 'reference_600_tier',
    'reference_200_m3_raw_btu', 'reference_200_m3_tier',
    'reference_225_m3_raw_btu', 'reference_225_m3_tier',
    'user_room_reference_raw_btu', 'user_room_reference_tier',
    'method_b_minus_a_tier_hp_equivalent',
], $benchmarkRows);

$classes = app(ProductHvacClassResolver::class);
$capacity = app(ProductMarketingCapacityQueryAdapter::class);
$allowedRac = [
    ProductHvacClass::RAC_SPLIT,
    ProductHvacClass::RAC_CASSETTE,
    ProductHvacClass::RAC_DUCTED,
    ProductHvacClass::RAC_FLOOR_CEILING,
    ProductHvacClass::RAC_FLOOR_STANDING,
];
$eligible = Product::query()->with('category')->where('is_active', true)->get()
    ->filter(static function (Product $product) use ($classes, $capacity, $allowedRac): bool {
        if (Schema::hasColumn('products', 'stock_status') && $product->stock_status === 'out_of_stock') {
            return false;
        }

        return in_array($classes->resolve($product)['class'], $allowedRac, true)
            && ($capacity->value($product) ?? 0) > 0;
    });
$capacityCounts = $eligible
    ->map(fn (Product $product): int => (int) $capacity->value($product))
    ->countBy()
    ->sortKeys();
$catalogRows = [];
foreach ($tiers as $tier) {
    $exactCount = (int) ($capacityCounts[$tier] ?? 0);
    $nextActual = collect($capacityCounts->keys())->first(fn (int $actual): bool => $actual >= $tier);
    $catalogRows[] = [
        $tier, 'YES', $exactCount > 0 ? 'YES' : 'NO', $exactCount,
        $nextActual, $nextActual === null ? null : $nextActual - $tier,
        $nextActual === null ? 'NO_ELIGIBLE_PRODUCT' : ($nextActual === $tier ? 'EXACT' : 'CATALOG_GAP'),
    ];
}
$writeCsv($artifactDir.'/calculator_catalog_tier_gap.csv', [
    'configured_tier_btu', 'configured', 'exact_eligible_product', 'exact_product_count',
    'next_actual_eligible_capacity_btu', 'gap_btu', 'status',
], $catalogRows);

$comparisonRows = [];
foreach (array_keys($benchmarkSpaces) as $spaceKey) {
    foreach ([[20, 2.7], [30, 3.0], [30, 4.0], [50, 3.0], [50, 4.0]] as [$area, $height]) {
        $currentW = (float) $areaFactors[$spaceKey]['w_per_m2'];
        $proposedW = (float) $proposals[$spaceKey][0];
        $v1Base = (int) round($area * $currentW * 3.412);
        $v1Raw = $height > 3 ? (int) round($v1Base * round($height / 3, 2)) : $v1Base;
        $v2Base = (int) round($area * $proposedW * 3.412);
        $v2Raw = $height > 3 ? (int) round($v2Base * round($height / 3, 2)) : $v2Base;
        $v1Tier = $roundTier($v1Raw);
        $v2Tier = $roundTier($v2Raw);
        $v1VolumeRaw = (int) round($area * $height * ($currentW / 3) * 3.412);
        $v2VolumeRaw = (int) round($area * $height * ($proposedW / 3) * 3.412);
        $v1VolumeTier = $roundTier($v1VolumeRaw);
        $v2VolumeTier = $roundTier($v2VolumeRaw);
        $comparisonRows[] = [
            $spaceKey, $area, $height, $v1Raw, $v1Tier, $marketingHp($v1Tier),
            $v2Raw, $v2Tier, $marketingHp($v2Tier), $v2Raw - $v1Raw,
            $v2Tier - $v1Tier, round(($v2Tier - $v1Tier) / 9000, 2),
            $v1VolumeRaw, $v1VolumeTier, $v2VolumeRaw, $v2VolumeTier,
            $proposals[$spaceKey][1], 'PROPOSAL_NOT_ACTIVATED',
        ];
    }
}
$writeCsv($artifactDir.'/calculator_v1_v2_comparison.csv', [
    'space_key', 'area_m2', 'height_m', 'v1_raw_btu', 'v1_tier', 'v1_marketing_hp',
    'v2_proposal_raw_btu', 'v2_proposal_tier', 'v2_marketing_hp', 'raw_delta_btu',
    'tier_delta_btu', 'tier_delta_hp_equivalent',
    'v1_volume_raw_btu', 'v1_volume_tier', 'v2_volume_proposal_raw_btu', 'v2_volume_proposal_tier',
    'confidence', 'status',
], $comparisonRows);

$sourceAuthority = [
    'analysis_conversion' => ['w_to_btu' => ANALYSIS_W_TO_BTU, 'runtime_unchanged' => 3.412],
    'sources' => [
        [
            'id' => 'PANASONIC_VN_LARGE_SPACE_GUIDANCE',
            'level' => 'A_MANUFACTURER_CONSUMER_GUIDANCE',
            'url' => 'https://www.panasonic.com/vn/air-solutions/learn-more/huong-dan-chon-cac-loai-dieu-hoa-cong-suat-lon.html',
            'scope' => 'Vietnam consumer/commercial rule-of-thumb',
            'values' => ['600 BTU/h/m² baseline', 'office/hotel 700-800', 'hall/restaurant/cafe 900-1000'],
            'limitations' => 'Marketing guidance, not an engineering load standard; assumptions not fully enumerated',
        ],
        [
            'id' => 'PANASONIC_VN_AREA_VOLUME_GUIDANCE',
            'level' => 'A_MANUFACTURER_CONSUMER_GUIDANCE',
            'url' => 'https://www.panasonic.com/vn/air-solutions/learn-more/dieu-hoa-am-tran-18000btu.html',
            'scope' => 'Vietnam consumer cassette sizing',
            'values' => ['600 BTU/h/m²', '200 BTU/h/m³'],
            'limitations' => 'Consumer guidance, not full heat-load calculation',
        ],
        [
            'id' => 'ENERGY_STAR_ROOM_AC',
            'level' => 'A_GOVERNMENT_PROGRAM_GUIDANCE',
            'url' => 'https://www.energystar.gov/products/room_air_conditioners',
            'scope' => 'US room air conditioners, 8-foot ceiling',
            'values' => ['capacity chart', 'sun +10%', '600 BTU per person above two', 'kitchen +4000 BTU'],
            'limitations' => 'US climate/building assumptions; not directly transferable to Vietnam commercial spaces',
        ],
        [
            'id' => 'US_DOE_HOME_HVAC_GUIDE',
            'level' => 'A_GOVERNMENT_ENGINEERING_BOUNDARY',
            'url' => 'https://www.energy.gov/sites/prod/files/guide_to_home_heating_cooling.pdf',
            'scope' => 'US residential HVAC',
            'values' => ['old estimate 1 ton per 400-500 ft²'],
            'limitations' => 'Explicitly warns rule ignores climate/envelope and recommends Manual J',
        ],
        [
            'id' => 'USER_SUPPLIED_ROOM_TYPE_TABLE',
            'level' => 'REFERENCE_EVIDENCE',
            'url' => null,
            'scope' => 'Room-type screenshot/table supplied by operator',
            'values' => ['residential 700', 'living/dining 850', 'retail/office 800', 'library/bank 850', 'shopping center 1000', 'meeting hall 1500', 'dance hall 2000'],
            'limitations' => 'Provenance unknown; 0.235 kW/m² equals about 801.85 BTU/h/m², inconsistent with a displayed 850 value',
            'status' => 'SOURCE_INTERNAL_INCONSISTENCY',
        ],
    ],
];
file_put_contents(
    $artifactDir.'/calculator_source_authority.json',
    json_encode($sourceAuthority, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL,
);

$comparable = collect($gapRows)->filter(fn (array $row): bool => $row[9] !== null);
$lowCount = $comparable->where(10, 'LOWER_THAN_REFERENCE')->count();
$highCount = $comparable->where(10, 'HIGHER_THAN_REFERENCE')->count();
$withinCount = $comparable->where(10, 'WITHIN_REFERENCE')->count();
$deltas = $comparable->pluck(9)->sort()->values();
$medianDelta = $deltas->isEmpty() ? null : (float) $deltas->median();
$decision = [
    'decision' => 'V2_PROPOSAL_READY_NOT_ACTIVATED',
    'current_v1' => 'REVIEW_REQUIRED',
    'method_a' => 'V1_RUNTIME_PRESERVED_CALIBRATION_REVIEW_REQUIRED',
    'method_b' => 'USEFUL_HEIGHT_AWARE_DERIVATION_NOT_INDEPENDENTLY_CALIBRATED',
    'activation_authorized' => false,
    'comparable_space_types' => $comparable->count(),
    'lower_than_reference' => $lowCount,
    'within_reference' => $withinCount,
    'higher_than_reference' => $highCount,
    'median_delta_vs_typical_percent' => round((float) $medianDelta, 2),
    'global_multiplier_recommended' => false,
    'architecture_recommendation' => 'HYBRID_CATEGORY_FACTORS_WITH_EXPLICIT_ADJUSTMENT_SCOPE',
    'required_before_activation' => [
        'operator approval',
        'confirm base-factor occupancy/equipment scope',
        'resolve low-confidence space types',
        'preserve v1 replay',
        'add v2 golden tests and release version',
    ],
];
file_put_contents(
    $artifactDir.'/calculator_calibration_decision.json',
    json_encode($decision, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL,
);

echo json_encode([
    'factor_count' => count($inventoryRows),
    'gap_summary' => ['comparable' => $comparable->count(), 'low' => $lowCount, 'within' => $withinCount, 'high' => $highCount, 'median_delta_percent' => $medianDelta],
    'benchmark_rows' => count($benchmarkRows),
    'comparison_rows' => count($comparisonRows),
    'eligible_rac_products' => $eligible->count(),
    'actual_capacity_counts' => $capacityCounts,
    'decision' => $decision['decision'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;
