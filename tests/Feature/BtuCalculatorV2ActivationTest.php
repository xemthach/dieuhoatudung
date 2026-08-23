<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\Calculator\BtuCalculatorService;
use App\Services\Calculator\CalculatorRuleSetResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BtuCalculatorV2ActivationTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('v2GoldenCases')]
    public function test_v2_golden_cases_are_stable(
        float $area,
        float $height,
        string $space,
        int $people,
        bool $sun,
        bool $equipment,
        string $method,
        int $expectedRaw,
        int $expectedTier,
    ): void {
        $result = app(BtuCalculatorService::class)->calculate(
            $area, $height, $space, $people, $sun, $equipment, $method,
        );

        $this->assertSame($method === 'area' ? 'consumer-estimate-v2' : 'volume-consumer-estimate-v2', $result['rule_version']);
        $this->assertSame($expectedRaw, $result['calculated_btu']);
        $this->assertSame($expectedTier, $result['recommended_btu']);
    }

    /** @return array<string, array{float,float,string,int,bool,bool,string,int,int}> */
    public static function v2GoldenCases(): array
    {
        return [
            'residential-15' => [15, 3, 'nha_o', 0, false, false, 'area', 9000, 9000],
            'residential-20' => [20, 3, 'nha_o', 0, false, false, 'area', 12000, 12000],
            'residential-30' => [30, 3, 'nha_o', 0, false, false, 'area', 17999, 18000],
            'residential-40' => [40, 3, 'nha_o', 0, false, false, 'area', 23999, 24000],
            'living-room' => [25, 3, 'phong_khach', 0, false, false, 'area', 17499, 18000],
            'hotel' => [25, 3, 'khach_san', 0, false, false, 'area', 18749, 24000],
            'office' => [30, 3, 'van_phong', 0, false, false, 'area', 22499, 24000],
            'office-interior' => [30, 3, 'van_phong_interior', 0, false, false, 'area', 20999, 24000],
            'office-private' => [30, 3, 'van_phong_private', 0, false, false, 'area', 22499, 24000],
            'retail' => [30, 3, 'cua_hang', 0, false, false, 'area', 23999, 24000],
            'bank' => [30, 3, 'ngan_hang', 0, false, false, 'area', 25499, 28000],
            'restaurant' => [30, 3, 'nha_hang', 0, false, false, 'area', 28499, 30000],
            'cafe' => [30, 3, 'cafe', 0, false, false, 'area', 28499, 30000],
            'fastfood' => [30, 3, 'fastfood', 0, false, false, 'area', 29999, 30000],
            'hall' => [30, 3, 'hoi_truong', 0, false, false, 'area', 28499, 30000],
            'library' => [30, 3, 'thu_vien', 0, false, false, 'area', 25499, 28000],
            'retained-server' => [20, 3, 'phong_may_tinh', 0, false, false, 'area', 32755, 36000],
            'retained-classroom' => [30, 3, 'phong_hoc', 0, false, false, 'area', 9724, 12000],
            'retained-factory' => [30, 3, 'nha_xuong_nang', 0, false, false, 'area', 50156, 60000],
            'volume-height-2.5' => [30, 2.5, 'nha_o', 0, false, false, 'volume', 14999, 18000],
            'volume-height-3' => [30, 3, 'nha_o', 0, false, false, 'volume', 17999, 18000],
            'volume-height-3.5' => [30, 3.5, 'nha_o', 0, false, false, 'volume', 20999, 24000],
            'volume-height-4' => [30, 4, 'nha_o', 0, false, false, 'volume', 23999, 24000],
            'volume-height-5' => [30, 5, 'nha_o', 0, false, false, 'volume', 29999, 30000],
            'sun' => [30, 3, 'nha_o', 0, true, false, 'area', 19799, 24000],
            'equipment' => [30, 3, 'nha_o', 0, false, true, 'area', 19799, 24000],
            'people' => [30, 3, 'nha_o', 15, false, false, 'area', 19999, 24000],
            'combined' => [30, 3, 'nha_o', 15, true, true, 'area', 23779, 24000],
            'volume-office-high' => [30, 4, 'van_phong', 0, false, false, 'volume', 29999, 30000],
            'volume-cafe-low' => [30, 2.5, 'cafe', 0, false, false, 'volume', 23749, 24000],
        ];
    }

    public function test_active_versions_and_all_27_activation_decisions_are_explicit(): void
    {
        $resolver = app(CalculatorRuleSetResolver::class);
        $area = $resolver->resolve('area');

        $this->assertSame('consumer-estimate-v2', $area['version']);
        $this->assertSame('volume-consumer-estimate-v2', $resolver->resolve('volume')['version']);
        $this->assertCount(27, $area['space_types']);
        $this->assertSame(13, collect($area['space_types'])->where('activation', 'V2_CALIBRATED')->count());
        $this->assertSame(14, collect($area['space_types'])->where('activation', 'V1_RETAINED_REVIEW_PENDING')->count());
        $this->assertTrue(collect($area['space_types'])->where('confidence', 'LOW')->every(
            fn (array $space): bool => $space['activation'] === 'V1_RETAINED_REVIEW_PENDING',
        ));
    }

    public function test_v1_is_replayable_and_low_confidence_v2_factors_equal_v1(): void
    {
        $resolver = app(CalculatorRuleSetResolver::class);
        $v1 = $resolver->resolve('area', 'consumer-estimate-v1');
        $v2 = $resolver->resolve('area', 'consumer-estimate-v2');

        foreach ($v2['space_types'] as $key => $space) {
            if ($space['confidence'] === 'LOW') {
                $this->assertSame($v1['space_types'][$key]['w_per_m2'], $space['w_per_m2'], $key);
            }
        }

        $replay = app(BtuCalculatorService::class)->calculate(
            30, 4, 'nha_o', 12, true, true, 'area', 'consumer-estimate-v1',
        );
        $this->assertSame(20567, $replay['calculated_btu']);
        $this->assertSame('consumer-estimate-v1', $replay['rule_version']);
    }

    public function test_area_and_volume_v2_are_equivalent_at_three_metres_for_every_category(): void
    {
        $calculator = app(BtuCalculatorService::class);
        foreach (config('hvac_calculator_rules.space_types') as $key => $space) {
            $area = $calculator->calculate(30, 3, $key, 0, false, false, 'area');
            $volume = $calculator->calculate(30, 3, $key, 0, false, false, 'volume');

            $this->assertSame($area['calculated_btu'], $volume['calculated_btu'], $key);
            $this->assertArrayNotHasKey('ceiling', $volume['adjustments'], $key);
        }
    }

    public function test_v2_monotonic_properties_hold(): void
    {
        $calculator = app(BtuCalculatorService::class);
        $base = $calculator->calculate(30, 3, 'nha_o', 10, false, false, 'area')['calculated_btu'];

        $this->assertGreaterThanOrEqual($base, $calculator->calculate(31, 3, 'nha_o', 10, false, false, 'area')['calculated_btu']);
        $this->assertGreaterThanOrEqual($base, $calculator->calculate(30, 3, 'nha_o', 11, false, false, 'area')['calculated_btu']);
        $this->assertGreaterThanOrEqual($base, $calculator->calculate(30, 3, 'nha_o', 10, true, false, 'area')['calculated_btu']);
        $this->assertGreaterThanOrEqual($base, $calculator->calculate(30, 3, 'nha_o', 10, false, true, 'area')['calculated_btu']);

        $lowVolume = $calculator->calculate(30, 2.5, 'nha_o', 10, false, false, 'volume')['calculated_btu'];
        $highVolume = $calculator->calculate(30, 5, 'nha_o', 10, false, false, 'volume')['calculated_btu'];
        $this->assertGreaterThanOrEqual($lowVolume, $highVolume);
    }

    public function test_rule_method_mismatch_fails_closed(): void
    {
        $result = app(BtuCalculatorService::class)->calculate(
            30, 3, 'nha_o', 0, false, false, 'area', 'volume-consumer-estimate-v2',
        );

        $this->assertNull($result['recommended_btu']);
        $this->assertStringStartsWith(BtuCalculatorService::WARNING_INVALID_RULE, $result['warnings'][0]);
    }

    public function test_real_catalog_gap_shapes_keep_fail_closed_rac_matching(): void
    {
        $rac = ProductCategory::factory()->create(['name' => 'Điều hòa tủ đứng']);
        $vrf = ProductCategory::factory()->create(['name' => 'VRF Outdoor']);

        $rac18 = Product::factory()->create(['product_category_id' => $rac->id, 'marketing_capacity_btu' => 18000]);
        $rac30 = Product::factory()->create(['product_category_id' => $rac->id, 'marketing_capacity_btu' => 30000]);
        Product::factory()->create(['product_category_id' => $vrf->id, 'marketing_capacity_btu' => 60000]);

        $calculator = app(BtuCalculatorService::class);
        $this->assertSame([$rac18->id], $calculator->matchProducts(9000)->pluck('id')->all());
        $this->assertSame([$rac18->id], $calculator->matchProducts(12000)->pluck('id')->all());
        $this->assertSame([$rac30->id], $calculator->matchProducts(28000)->pluck('id')->all());
        $this->assertSame([], $calculator->matchProducts(60000)->pluck('id')->all());
    }

    public function test_admin_governance_view_is_read_only_and_lists_active_rules(): void
    {
        $html = view('filament.btu-calculator-governance', [
            'rules' => app(CalculatorRuleSetResolver::class)->governance(),
        ])->render();

        $this->assertStringContainsString('consumer-estimate-v2', $html);
        $this->assertStringContainsString('volume-consumer-estimate-v2', $html);
        $this->assertStringContainsString('V1 retained', $html);
        $this->assertStringNotContainsString('<input', $html);
    }

    public function test_v2_rule_resolution_and_calculation_add_no_database_queries(): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        app(BtuCalculatorService::class)->calculate(30, 3, 'nha_o', 12, true, true, 'area');
        app(BtuCalculatorService::class)->calculate(30, 4, 'nha_o', 12, true, true, 'volume');

        $this->assertCount(0, DB::getQueryLog());
        DB::disableQueryLog();
    }
}
