<?php

namespace App\Services\Calculator;

use App\Models\Product;
use App\Enums\BtuCalculationMethod;
use App\Enums\ProductHvacClass;
use App\Services\Calculator\Methods\VolumeCalculationMethod;
use App\Services\Product\ProductMarketingCapacityQueryAdapter;
use App\Services\Product\ProductHvacClassResolver;

/**
 * BtuCalculatorService
 *
 * Orchestrates the certified area estimate and the configured volume estimate,
 * then applies one shared capacity-tier and fail-closed RAC recommendation contract.
 * The original authority document for the historical area table is not present.
 *
 * Formula:
 *   Base Load (W)  = area_m² × W/m² (per space type)
 *   Base Load (BTU) = Base Load (W) × 3.412
 *   + adjustments (ceiling, sunlight, heat equipment, people)
 *   → round up to nearest standard BTU tier
 *
 * HP conversion: HP = BTU / 9,000 (HVAC convention, NOT mechanical 746W)
 */
class BtuCalculatorService
{
    public const WARNING_MISSING_INPUTS = 'missing_btu_inputs';
    public const WARNING_OUT_OF_RANGE = 'btu_input_out_of_range';
    public const WARNING_INVALID_METHOD = 'invalid_calculation_method';
    public const WARNING_INVALID_RULE = 'invalid_rule_version';
    private ProductMarketingCapacityQueryAdapter $capacityQuery;
    private ProductHvacClassResolver $classes;
    private VolumeCalculationMethod $volumeMethod;
    private CalculatorRuleSetResolver $ruleSets;

    /** BTU/h constant: 1 W = 3.412 BTU/h */
    protected const W_TO_BTU = 3.412;

    /** HVAC HP: 1 HP ≈ 9,000 BTU/h (industry convention) */
    protected const BTU_PER_HP = 9000;

    /**
     * Standard BTU tiers for floor-standing AC units.
     */
    protected array $btuTiers = [
        9000, 12000, 18000, 24000, 28000, 30000, 36000, 42000, 45000, 48000, 50000, 60000, 100000,
    ];

    public function __construct(
        ?ProductMarketingCapacityQueryAdapter $capacityQuery = null,
        ?VolumeCalculationMethod $volumeMethod = null,
        ?CalculatorRuleSetResolver $ruleSets = null,
    )
    {
        $this->capacityQuery = $capacityQuery ?? app(ProductMarketingCapacityQueryAdapter::class);
        $this->classes = app(ProductHvacClassResolver::class);
        $this->volumeMethod = $volumeMethod ?? app(VolumeCalculationMethod::class);
        $this->ruleSets = $ruleSets ?? app(CalculatorRuleSetResolver::class);
        $this->btuTiers = config('hvac.btu.standard_tiers', $this->btuTiers);
    }

    /**
     * Calculate a consumer estimate using the requested allowlisted method.
     *
     * @return array{
     *     calculated_btu: int,
     *     recommended_btu: int,
     *     recommended_hp: float,
     *     cooling_w_per_m2: int,
     *     base_load_w: float,
     *     area_range: string,
     *     explanation: string,
     *     adjustment_breakdown: array,
     *     steps: array,
     *     note: string|null,
     * }
     */
    public function calculate(
        float  $areaMq,
        float  $ceilingH    = 3.0,
        string $spaceType   = 'van_phong',
        int    $people      = 0,
        bool   $sunlight    = false,
        bool   $heatEquip   = false,
        string $method      = BtuCalculationMethod::AREA->value,
        ?string $ruleVersion = null,
    ): array {
        $calculationMethod = BtuCalculationMethod::tryFrom($method);
        if ($calculationMethod === null) {
            return $this->invalidResult([self::WARNING_INVALID_METHOD.':'.$method], $method);
        }

        try {
            $ruleSet = $this->ruleSets->resolve($calculationMethod, $ruleVersion);
        } catch (\InvalidArgumentException) {
            return $this->invalidResult(
                [self::WARNING_INVALID_RULE.':'.($ruleVersion ?? 'active')],
                $calculationMethod->value,
                $ruleVersion,
            );
        }

        $warnings = $this->validateInputs($areaMq, $ceilingH, $spaceType, $people, $ruleSet['space_types']);

        if ($warnings !== []) {
            return $this->invalidResult($warnings, $calculationMethod->value);
        }

        if ($calculationMethod === BtuCalculationMethod::VOLUME) {
            return $this->calculateVolume($areaMq, $ceilingH, $spaceType, $people, $sunlight, $heatEquip, $ruleSet);
        }

        $steps      = [];
        $adjustments = [];

        // ── 1. Base load from W/m² table ─────────────────────
        $spaceData   = $ruleSet['space_types'][$spaceType];
        $wPerM2      = $spaceData['w_per_m2'];
        $spaceLabel  = $spaceData['label_vi'];

        $baseLoadW   = $areaMq * $wPerM2;
        $baseBtu     = round($baseLoadW * $this->wToBtu());

        $steps[] = "Diện tích {$areaMq}m² × {$wPerM2} W/m² ({$spaceLabel}) = " . number_format($baseLoadW) . " W";
        $steps[] = number_format($baseLoadW) . " W × " . $this->wToBtu() . " = " . number_format($baseBtu) . " BTU";

        $btu = $baseBtu;

        // ── 2. Ceiling height adjustment ─────────────────────
        $baselineCeiling = (float) config('hvac.btu.baseline_ceiling_m', 3.0);
        if ($ceilingH > $baselineCeiling) {
            $hFactor = round($ceilingH / $baselineCeiling, 2);
            $before  = $btu;
            $btu     = round($btu * $hFactor);
            $delta   = $btu - $before;
            $steps[] = "Trần cao {$ceilingH}m → hệ số ×{$hFactor} (+{$delta} BTU)";
            $adjustments['ceiling'] = ['factor' => $hFactor, 'delta_btu' => $delta];
        }

        // ── 3. Direct sunlight +10% ─────────────────────────
        if ($sunlight) {
            $before = $btu;
            $sunlightMultiplier = (float) config('hvac.btu.sunlight_multiplier', 1.10);
            $btu    = round($btu * $sunlightMultiplier);
            $delta  = $btu - $before;
            $sunlightPercent = round(($sunlightMultiplier - 1) * 100, 2);
            $steps[] = "Có nắng trực tiếp (+{$sunlightPercent}% = +{$delta} BTU)";
            $adjustments['sunlight'] = ['factor' => $sunlightMultiplier, 'delta_btu' => $delta];
        }

        // ── 4. Heat-generating equipment +10% ───────────────
        if ($heatEquip) {
            $before = $btu;
            $heatMultiplier = (float) config('hvac.btu.heat_equipment_multiplier', 1.10);
            $btu    = round($btu * $heatMultiplier);
            $delta  = $btu - $before;
            $heatPercent = round(($heatMultiplier - 1) * 100, 2);
            $steps[] = "Nhiều thiết bị sinh nhiệt (+{$heatPercent}% = +{$delta} BTU)";
            $adjustments['heat_equipment'] = ['factor' => $heatMultiplier, 'delta_btu' => $delta];
        }

        // ── 5. People load (400 BTU/person above 10) ────────
        $peopleIncluded = (int) config('hvac.btu.people_included_in_base', 10);
        $extraPersonBtu = (int) config('hvac.btu.extra_person_btu', 400);
        if ($people > $peopleIncluded) {
            $extra = ($people - $peopleIncluded) * $extraPersonBtu;
            $btu  += $extra;
            $steps[] = ($people - $peopleIncluded) . " người vượt mức × {$extraPersonBtu} BTU = +" . number_format($extra) . " BTU";
            $adjustments['extra_people'] = ['count' => $people - $peopleIncluded, 'delta_btu' => $extra];
        }

        $calculatedBtu  = (int) round($btu);
        $recommendedBtu = $this->roundUpToTier($calculatedBtu);
        $recommendedHp  = round($recommendedBtu / $this->btuPerHp(), 1);

        // ── Area range & note ────────────────────────────────
        $areaRange = $this->btuToAreaRange($recommendedBtu);

        // Loads beyond the consumer single-unit range require site review. Do
        // not infer unit count, zoning, or a VRF design from this estimate.
        $note = null;
        if ($recommendedBtu >= 100000) {
            $note = 'Nhu cầu vượt 100.000 BTU/h. Kết quả tính vẫn hợp lệ nhưng cần khảo sát kỹ thuật; công cụ không tự chia số lượng máy hoặc lựa chọn hệ thống thương mại.';
        }

        // ── Explanation ──────────────────────────────────────
        $explanation = "Căn cứ diện tích {$areaMq}m², {$spaceLabel} ({$wPerM2} W/m²)" .
            ($ceilingH > 3 ? ", trần cao {$ceilingH}m" : '') .
            ($sunlight ? ", có nắng trực tiếp" : '') .
            ($heatEquip ? ", nhiều thiết bị sinh nhiệt" : '') .
            ($people > $peopleIncluded ? ", {$people} người thường xuyên" : '') .
            ". Công suất tính toán là " . number_format($calculatedBtu) . " BTU" .
            " — nhóm công suất tính toán " . number_format($recommendedBtu) . " BTU (~{$recommendedHp} HP)" .
            " theo nguyên tắc làm tròn lên nhóm công suất thị trường.";

        return [
            'method'                => BtuCalculationMethod::AREA->value,
            'method_label'          => BtuCalculationMethod::AREA->label(),
            'calculated_btu'        => $calculatedBtu,
            'recommended_btu'       => $recommendedBtu,
            'recommended_hp'        => $recommendedHp,
            'cooling_w_per_m2'      => $wPerM2,
            'base_load_w'           => $baseLoadW,
            'area_range'            => $areaRange,
            'explanation'           => $explanation,
            'adjustment_breakdown'  => $adjustments,
            'steps'                 => $steps,
            'note'                  => $note,
            'warnings'              => [],
            'calculation_source'    => self::class,
            'rule_version'          => $ruleSet['version'],
            'methodology'           => $ruleSet['methodology'],
            'factor_authority'      => $spaceData['source'],
            'factor_confidence'     => $spaceData['confidence'],
            'factor_activation'     => $spaceData['activation'],
            'raw_watts'             => $baseLoadW,
            'market_tier_btu'       => $recommendedBtu,
            'recommendation_target' => $recommendedBtu,
            'adjustments'           => $adjustments,
            'volume_m3'             => null,
            'volume_w_per_m3'       => null,
            'factor_value'          => $wPerM2,
            'factor_unit'           => 'W/m²',
            // BC compat with old keys
            'raw_btu'               => $calculatedBtu,
        ];
    }

    private function calculateVolume(
        float $areaM2,
        float $heightM,
        string $spaceType,
        int $people,
        bool $sunlight,
        bool $heatEquipment,
        array $ruleSet,
    ): array {
        $factor = $ruleSet['space_types'][$spaceType] ?? null;
        if (! is_array($factor) || ! ($factor['enabled'] ?? false)) {
            return $this->invalidResult([self::WARNING_MISSING_INPUTS.':space_type'], BtuCalculationMethod::VOLUME->value);
        }

        $result = $this->volumeMethod->calculate(
            $areaM2,
            $heightM,
            $factor,
            $people,
            $sunlight,
            $heatEquipment,
            $ruleSet,
        );
        $recommendedBtu = $this->roundUpToTier($result['calculated_btu']);
        $recommendedHp = round($recommendedBtu / $this->btuPerHp(), 1);
        $peopleIncluded = (int) $result['people_included_in_base'];

        $result['recommended_btu'] = $recommendedBtu;
        $result['recommended_hp'] = $recommendedHp;
        $result['market_tier_btu'] = $recommendedBtu;
        $result['recommendation_target'] = $recommendedBtu;
        $result['area_range'] = $this->btuToAreaRange($recommendedBtu);
        $result['note'] = $recommendedBtu >= 100000
            ? 'Nhu cầu vượt 100.000 BTU/h. Kết quả tính vẫn hợp lệ nhưng cần khảo sát kỹ thuật; công cụ không hạ mục tiêu để gợi ý máy nhỏ hơn.'
            : null;
        $result['warnings'] = [];
        $result['calculation_source'] = VolumeCalculationMethod::class;
        $result['factor_authority'] = $factor['source'];
        $result['factor_confidence'] = $factor['confidence'];
        $result['factor_activation'] = $factor['activation'];
        $result['explanation'] = 'Căn cứ thể tích '.number_format($result['volume_m3'], 2)."m³, {$factor['label']} ({$result['volume_w_per_m3']} W/m³)".
            ($sunlight ? ', có nắng trực tiếp' : '').
            ($heatEquipment ? ', nhiều thiết bị sinh nhiệt' : '').
            ($people > $peopleIncluded ? ", {$people} người thường xuyên" : '').
            '. Nhu cầu ước tính là '.number_format($result['calculated_btu']).' BTU/h'.
            ' — đề xuất nhóm '.number_format($recommendedBtu)." BTU (~{$recommendedHp} HP) theo nguyên tắc làm tròn lên.";

        unset($result['people_included_in_base']);

        return $result;
    }

    /** @param list<string> $warnings */
    private function invalidResult(array $warnings, string $method, ?string $ruleVersion = null): array
    {
        $resolved = BtuCalculationMethod::tryFrom($method);

        return [
            'method' => $method,
            'method_label' => $resolved?->label(),
            'calculated_btu' => null,
            'recommended_btu' => null,
            'recommended_hp' => null,
            'cooling_w_per_m2' => null,
            'volume_w_per_m3' => null,
            'volume_m3' => null,
            'raw_watts' => null,
            'base_load_w' => null,
            'area_range' => null,
            'explanation' => 'Không đủ dữ liệu đầu vào hợp lệ để tính BTU.',
            'adjustment_breakdown' => [],
            'adjustments' => [],
            'steps' => [],
            'note' => null,
            'warnings' => $warnings,
            'calculation_source' => self::class,
            'rule_version' => $ruleVersion ?? ($resolved ? $this->ruleSets->activeVersion($resolved) : null),
            'methodology' => null,
            'raw_btu' => null,
            'market_tier_btu' => null,
            'recommendation_target' => null,
            'factor_value' => null,
            'factor_unit' => null,
        ];
    }

    /**
     * Get full cooling load table for UI select options.
     *
     * @return array<string, string>  [key => "Label (xxx W/m²)"]
     */
    public static function spaceTypeOptions(): array
    {
        $spaces = app(CalculatorRuleSetResolver::class)
            ->resolve(BtuCalculationMethod::AREA)['space_types'];
        $options = [];
        foreach ($spaces as $key => $data) {
            $options[$key] = $data['label_vi'] . ' (' . $data['w_per_m2'] . ' W/m²)';
        }
        return $options;
    }

    /**
     * Get simple label map for admin display.
     *
     * @return array<string, string>
     */
    public static function spaceTypeLabels(): array
    {
        $spaces = app(CalculatorRuleSetResolver::class)
            ->resolve(BtuCalculationMethod::AREA)['space_types'];
        $labels = [];
        foreach ($spaces as $key => $data) {
            $labels[$key] = $data['label_vi'];
        }
        return $labels;
    }

    /**
     * Get space types grouped by category for <optgroup> rendering.
     *
     * @return array<string, array<string, string>>  [group => [key => "Label (xxx W/m²)"]]
     */
    public static function spaceTypeGrouped(): array
    {
        $spaces = app(CalculatorRuleSetResolver::class)
            ->resolve(BtuCalculationMethod::AREA)['space_types'];
        $grouped = [];
        foreach ($spaces as $key => $data) {
            $group = $data['group'];
            $grouped[$group][$key] = $data['label_vi'] . ' (' . $data['w_per_m2'] . ' W/m²)';
        }
        return $grouped;
    }

    /**
     * Unit-neutral labels for the method-switching form. The selected method
     * exposes its W/m² or W/m³ factor in the result instead.
     *
     * @return array<string, array<string, string>>
     */
    public static function spaceTypeGroupedLabels(): array
    {
        $spaces = app(CalculatorRuleSetResolver::class)
            ->resolve(BtuCalculationMethod::AREA)['space_types'];
        $grouped = [];
        foreach ($spaces as $key => $data) {
            $grouped[$data['group']][$key] = $data['label_vi'];
        }

        return $grouped;
    }

    /**
     * Get W/m² value for a specific space type.
     */
    public function getCoolingLoad(string $spaceType, ?string $ruleVersion = null): float
    {
        $spaces = $this->ruleSets
            ->resolve(BtuCalculationMethod::AREA, $ruleVersion)['space_types'];

        return (float) ($spaces[$spaceType]['w_per_m2'] ?? $spaces['van_phong']['w_per_m2']);
    }

    /**
     * Find matching products for recommended BTU.
     */
    public function matchProducts(int $recommendedBtu, string $priority = ''): \Illuminate\Database\Eloquent\Collection
    {
        $query = $this->capacityQuery->applyPresent(Product::with('category')->where('is_active', true));

        // Exclude out of stock
        if (\Illuminate\Support\Facades\Schema::hasColumn('products', 'stock_status')) {
            $query->where(function ($q) {
                $q->whereNull('stock_status')
                  ->orWhere('stock_status', '!=', 'out_of_stock');
            });
        }

        // Never recommend below the calculated market tier.
        $lowerBound = $recommendedBtu;
        $upperBound = $this->nextTier($recommendedBtu);

        $products = $this->withoutVrfProducts(
            $this->capacityQuery->applyBetween($query, $lowerBound, $upperBound)->get(),
        );

        // Widen if less than 4
        if ($products->count() < 4) {
            $fallback = $this->capacityQuery->applyPresent(Product::with('category')->where('is_active', true));
            $products = $this->withoutVrfProducts($this->capacityQuery->applyBetween(
                $fallback,
                $recommendedBtu,
                $recommendedBtu + 12000
            )->get());
        }

        // Sort by priority
        return match ($priority) {
            'gia_tot' => $products->sortBy(fn (Product $product): array => [
                $product->sale_price ?? $product->regular_price ?? PHP_INT_MAX,
                abs(($this->capacityQuery->value($product) ?? 0) - $recommendedBtu),
            ])->values(),
            default               => $this->capacityQuery->distance($products, $recommendedBtu),
        };
    }

    private function withoutVrfProducts(\Illuminate\Database\Eloquent\Collection $products): \Illuminate\Database\Eloquent\Collection
    {
        return $products->filter(function (Product $product): bool {
            $class = $this->classes->resolve($product)['class'];

            return in_array($class, [
                ProductHvacClass::RAC_SPLIT,
                ProductHvacClass::RAC_CASSETTE,
                ProductHvacClass::RAC_DUCTED,
                ProductHvacClass::RAC_FLOOR_CEILING,
                ProductHvacClass::RAC_FLOOR_STANDING,
            ], true);
        })->values();
    }

    // ──────────────────────────────────────────────────────
    // Private helpers
    // ──────────────────────────────────────────────────────

    protected function roundUpToTier(float $btu): int
    {
        foreach ($this->btuTiers as $tier) {
            if ($btu <= $tier) return $tier;
        }
        // Over max tier — return actual rounded up to 1000
        return (int) (ceil($btu / 1000) * 1000);
    }

    protected function prevTier(int $btu): int
    {
        $prev = $this->btuTiers[0];
        foreach ($this->btuTiers as $tier) {
            if ($tier >= $btu) return $prev;
            $prev = $tier;
        }
        return $prev;
    }

    protected function nextTier(int $btu): int
    {
        foreach ($this->btuTiers as $tier) {
            if ($tier > $btu) return $tier;
        }
        return end($this->btuTiers);
    }

    /**
     * @return list<string>
     */
    public function validateInputs(
        ?float $areaMq,
        ?float $ceilingH,
        ?string $spaceType,
        int $people = 0,
        ?array $spaceTypes = null,
    ): array
    {
        $missing = [];

        if ($areaMq === null || $areaMq <= 0) {
            $missing[] = 'area_m2';
        }

        if ($ceilingH === null || $ceilingH <= 0) {
            $missing[] = 'ceiling_height';
        }

        $spaceTypes ??= $this->ruleSets->resolve(BtuCalculationMethod::AREA)['space_types'];
        if ($spaceType === null || $spaceType === '' || ! isset($spaceTypes[$spaceType])) {
            $missing[] = 'space_type';
        }

        if ($missing !== []) {
            return [self::WARNING_MISSING_INPUTS . ':' . implode(',', $missing)];
        }

        $bounds = config('hvac.btu.input_bounds', []);
        $outOfRange = [];
        foreach ([
            'area_m2' => $areaMq,
            'ceiling_height' => $ceilingH,
            'people_count' => $people,
        ] as $field => $value) {
            $range = $bounds[$field] ?? null;
            if ($range && ($value < $range['min'] || $value > $range['max'])) {
                $outOfRange[] = $field;
            }
        }

        return $outOfRange === []
            ? []
            : [self::WARNING_OUT_OF_RANGE . ':' . implode(',', $outOfRange)];
    }

    public function ruleVersion(): string
    {
        return $this->ruleSets->activeVersion(BtuCalculationMethod::AREA);
    }

    /** @return list<int> */
    public function standardTiers(): array
    {
        return array_values(array_map('intval', $this->btuTiers));
    }

    /** @return array<string, string> */
    public static function priorityOptions(): array
    {
        return [
            '' => 'Gần công suất yêu cầu nhất',
            'gia_tot' => 'Giá thấp trước',
        ];
    }

    protected function btuToAreaRange(int $btu): string
    {
        $map = config('hvac.btu.area_ranges', []);

        return $map[$btu] ?? 'Cần khảo sát tải lạnh thực tế';
    }

    private function wToBtu(): float
    {
        return (float) config('hvac.btu.w_to_btu', self::W_TO_BTU);
    }

    private function btuPerHp(): int
    {
        return (int) config('hvac.btu.btu_per_hp', self::BTU_PER_HP);
    }
}
