<?php

declare(strict_types=1);

use App\Services\Calculator\BtuCalculatorService;
use App\Services\Calculator\CalculatorRuleSetResolver;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$outputDirectory = dirname(__DIR__).'/docs/reports/final/artifacts';
if (! is_dir($outputDirectory)) {
    mkdir($outputDirectory, 0775, true);
}

/** @param list<string> $headers @param list<array<int, scalar|null>> $rows */
function writeActivationCsv(string $path, array $headers, array $rows): void
{
    $stream = fopen($path, 'wb');
    fputcsv($stream, $headers);
    foreach ($rows as $row) {
        fputcsv($stream, $row);
    }
    fclose($stream);
}

$resolver = app(CalculatorRuleSetResolver::class);
$calculator = app(BtuCalculatorService::class);
$v1 = $resolver->resolve('area', 'consumer-estimate-v1');
$v2 = $resolver->resolve('area', 'consumer-estimate-v2');
$tiers = $calculator->standardTiers();

$activationRows = [];
foreach ($v2['space_types'] as $key => $space) {
    $v1Factor = $v1['space_types'][$key]['w_per_m2'];
    $decision = $space['activation'] === 'V2_CALIBRATED' ? 'ACTIVATE_V2' : 'REVIEW_ONLY';
    $reason = $decision === 'ACTIVATE_V2'
        ? 'Approved category-specific HIGH/MEDIUM calibration'
        : 'LOW confidence; exact v1 factor retained in hybrid v2 pending review';

    $activationRows[] = [
        $key,
        $space['label_vi'],
        $v1Factor,
        $space['w_per_m2'],
        $space['reference_btu_m2'],
        $space['confidence'],
        $space['source'],
        $decision,
        $reason,
    ];
}

writeActivationCsv(
    $outputDirectory.'/calculator_v2_activation_matrix.csv',
    ['key', 'label', 'v1_w_m2', 'proposed_v2_w_m2', 'reference_btu_m2', 'confidence', 'source', 'activation_decision', 'reason'],
    $activationRows,
);

$comparisonRows = [];
$largeJumps = [];
foreach ($v2['space_types'] as $key => $space) {
    if ($space['activation'] !== 'V2_CALIBRATED') {
        continue;
    }

    foreach ([15, 20, 30, 40] as $area) {
        $old = $calculator->calculate($area, 3.0, $key, 0, false, false, 'area', 'consumer-estimate-v1');
        $new = $calculator->calculate($area, 3.0, $key, 0, false, false, 'area', 'consumer-estimate-v2');
        $oldIndex = array_search($old['recommended_btu'], $tiers, true);
        $newIndex = array_search($new['recommended_btu'], $tiers, true);
        $tierDelta = is_int($oldIndex) && is_int($newIndex) ? $newIndex - $oldIndex : null;
        $hpDelta = round(($new['recommended_btu'] - $old['recommended_btu']) / 9000, 2);
        $review = abs((int) $tierDelta) >= 2 ? 'MANUALLY_REVIEWED_ACCEPTED' : 'NOT_REQUIRED';
        $reviewReason = $review === 'MANUALLY_REVIEWED_ACCEPTED'
            ? 'Explained by approved category factor plus non-uniform market-tier grid; formula remains fail-closed'
            : '';

        $row = [
            $key,
            $space['label_vi'],
            $area,
            3.0,
            $old['calculated_btu'],
            $new['calculated_btu'],
            $old['recommended_btu'],
            $new['recommended_btu'],
            $tierDelta,
            $hpDelta,
            $review,
            $reviewReason,
        ];
        $comparisonRows[] = $row;
        if ($review === 'MANUALLY_REVIEWED_ACCEPTED') {
            $largeJumps[] = $row;
        }
    }
}

writeActivationCsv(
    $outputDirectory.'/calculator_v2_activation_comparison.csv',
    ['key', 'label', 'area_m2', 'height_m', 'v1_raw_btu', 'v2_raw_btu', 'v1_market_tier', 'v2_market_tier', 'tier_delta', 'hp_equivalent_delta', 'large_jump_review', 'review_reason'],
    $comparisonRows,
);

$goldenInputs = [
    ['residential-15', 15, 3, 'nha_o', 0, false, false, 'area'],
    ['residential-20', 20, 3, 'nha_o', 0, false, false, 'area'],
    ['residential-30', 30, 3, 'nha_o', 0, false, false, 'area'],
    ['residential-40', 40, 3, 'nha_o', 0, false, false, 'area'],
    ['living-room', 25, 3, 'phong_khach', 0, false, false, 'area'],
    ['hotel', 25, 3, 'khach_san', 0, false, false, 'area'],
    ['office', 30, 3, 'van_phong', 0, false, false, 'area'],
    ['office-interior', 30, 3, 'van_phong_interior', 0, false, false, 'area'],
    ['office-private', 30, 3, 'van_phong_private', 0, false, false, 'area'],
    ['retail', 30, 3, 'cua_hang', 0, false, false, 'area'],
    ['bank', 30, 3, 'ngan_hang', 0, false, false, 'area'],
    ['restaurant', 30, 3, 'nha_hang', 0, false, false, 'area'],
    ['cafe', 30, 3, 'cafe', 0, false, false, 'area'],
    ['fastfood', 30, 3, 'fastfood', 0, false, false, 'area'],
    ['hall', 30, 3, 'hoi_truong', 0, false, false, 'area'],
    ['library', 30, 3, 'thu_vien', 0, false, false, 'area'],
    ['retained-server', 20, 3, 'phong_may_tinh', 0, false, false, 'area'],
    ['retained-classroom', 30, 3, 'phong_hoc', 0, false, false, 'area'],
    ['retained-factory', 30, 3, 'nha_xuong_nang', 0, false, false, 'area'],
    ['volume-height-2.5', 30, 2.5, 'nha_o', 0, false, false, 'volume'],
    ['volume-height-3', 30, 3, 'nha_o', 0, false, false, 'volume'],
    ['volume-height-3.5', 30, 3.5, 'nha_o', 0, false, false, 'volume'],
    ['volume-height-4', 30, 4, 'nha_o', 0, false, false, 'volume'],
    ['volume-height-5', 30, 5, 'nha_o', 0, false, false, 'volume'],
    ['sun', 30, 3, 'nha_o', 0, true, false, 'area'],
    ['equipment', 30, 3, 'nha_o', 0, false, true, 'area'],
    ['people', 30, 3, 'nha_o', 15, false, false, 'area'],
    ['combined', 30, 3, 'nha_o', 15, true, true, 'area'],
    ['volume-office-high', 30, 4, 'van_phong', 0, false, false, 'volume'],
    ['volume-cafe-low', 30, 2.5, 'cafe', 0, false, false, 'volume'],
];

$goldenRows = array_map(function (array $input) use ($calculator): array {
    [$name, $area, $height, $space, $people, $sun, $equipment, $method] = $input;
    $result = $calculator->calculate($area, $height, $space, $people, $sun, $equipment, $method);

    return [
        'name' => $name,
        'area_m2' => $area,
        'height_m' => $height,
        'space_type' => $space,
        'people' => $people,
        'sunlight' => $sun,
        'equipment' => $equipment,
        'method' => $method,
        'rule_version' => $result['rule_version'],
        'raw_btu' => $result['calculated_btu'],
        'market_tier_btu' => $result['recommended_btu'],
    ];
}, $goldenInputs);

file_put_contents(
    $outputDirectory.'/calculator_v2_golden_tests.json',
    json_encode($goldenRows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL,
);

echo json_encode([
    'activation_rows' => count($activationRows),
    'activated' => count(array_filter($activationRows, fn (array $row): bool => $row[7] === 'ACTIVATE_V2')),
    'kept_v1_review_only' => count(array_filter($activationRows, fn (array $row): bool => $row[7] === 'REVIEW_ONLY')),
    'comparison_rows' => count($comparisonRows),
    'large_jump_rows' => count($largeJumps),
    'golden_rows' => count($goldenRows),
    'active_area_rule' => $resolver->activeVersion('area'),
    'active_volume_rule' => $resolver->activeVersion('volume'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
