<?php

namespace Tests\Unit;

use App\Services\Calculator\BtuCalculatorService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BtuCalculatorVolumeMethodTest extends TestCase
{
    #[DataProvider('volumeGoldenCases')]
    public function test_volume_golden_cases_are_deterministic(
        float $area,
        float $height,
        string $spaceType,
        int $people,
        bool $sunlight,
        bool $equipment,
        int $expectedRawBtu,
        int $expectedTier,
    ): void {
        $result = app(BtuCalculatorService::class)->calculate(
            $area, $height, $spaceType, $people, $sunlight, $equipment,
            'volume', 'volume-consumer-estimate-v1',
        );

        $this->assertSame($expectedRawBtu, $result['calculated_btu']);
        $this->assertSame($expectedTier, $result['recommended_btu']);
    }

    /** @return array<string, array{float, float, string, int, bool, bool, int, int}> */
    public static function volumeGoldenCases(): array
    {
        return [
            'normal bedroom' => [20.0, 3.0, 'nha_o', 2, false, false, 8189, 9000],
            'office' => [50.0, 3.0, 'van_phong', 8, false, false, 29002, 30000],
            'high ceiling' => [30.0, 4.5, 'nha_o', 4, false, false, 18425, 24000],
            'direct sun' => [30.0, 3.0, 'nha_o', 4, true, false, 13511, 18000],
            'many people' => [30.0, 3.0, 'nha_o', 15, false, false, 14283, 18000],
            'equipment load' => [30.0, 3.0, 'nha_o', 4, false, true, 13511, 18000],
            'combined adjustments' => [30.0, 3.0, 'nha_o', 12, true, true, 15662, 18000],
        ];
    }

    public function test_volume_method_uses_a_distinct_rule_and_true_volume_formula(): void
    {
        $result = app(BtuCalculatorService::class)->calculate(
            30, 4, 'nha_o', 2, false, false, 'volume', 'volume-consumer-estimate-v1',
        );

        $this->assertSame('volume', $result['method']);
        $this->assertSame('volume-consumer-estimate-v1', $result['rule_version']);
        $this->assertSame(120.0, $result['volume_m3']);
        $this->assertSame(40.0, $result['volume_w_per_m3']);
        $this->assertSame(4800.0, $result['raw_watts']);
        $this->assertSame(16378, $result['calculated_btu']);
        $this->assertSame(18000, $result['recommended_btu']);
        $this->assertArrayNotHasKey('ceiling', $result['adjustments']);
    }

    public function test_volume_method_does_not_apply_the_area_height_multiplier_again(): void
    {
        $result = app(BtuCalculatorService::class)->calculate(
            30, 4, 'nha_o', 0, false, false, 'volume', 'volume-consumer-estimate-v1',
        );
        $expected = (int) round((30 * 4 * 40) * 3.412);
        $doubleCounted = (int) round($expected * round(4 / 3, 2));

        $this->assertSame($expected, $result['calculated_btu']);
        $this->assertNotSame($doubleCounted, $result['calculated_btu']);
    }

    public function test_volume_need_never_decreases_when_height_increases(): void
    {
        $service = app(BtuCalculatorService::class);
        $low = $service->calculate(30, 3, 'van_phong', 4, false, false, 'volume');
        $high = $service->calculate(30, 4, 'van_phong', 4, false, false, 'volume');

        $this->assertGreaterThan($low['calculated_btu'], $high['calculated_btu']);
        $this->assertGreaterThanOrEqual($high['calculated_btu'], $high['recommended_btu']);
    }

    public function test_area_v1_remains_replayable_with_its_certified_golden_result(): void
    {
        $service = app(BtuCalculatorService::class);
        $explicit = $service->calculate(
            30, 4, 'nha_o', 12, true, true, 'area', 'consumer-estimate-v1',
        );

        $this->assertSame(20567, $explicit['calculated_btu']);
        $this->assertSame('consumer-estimate-v1', $explicit['rule_version']);
    }

    public function test_invalid_method_fails_closed(): void
    {
        $result = app(BtuCalculatorService::class)->calculate(30, 3, 'nha_o', 0, false, false, 'Some\\Class');

        $this->assertNull($result['recommended_btu']);
        $this->assertStringStartsWith(BtuCalculatorService::WARNING_INVALID_METHOD, $result['warnings'][0]);
    }

    public function test_volume_adjustments_are_explicit_and_deterministic(): void
    {
        $result = app(BtuCalculatorService::class)->calculate(
            30, 3, 'nha_o', 12, true, true, 'volume', 'volume-consumer-estimate-v1',
        );

        $this->assertSame(15662, $result['calculated_btu']);
        $this->assertSame(18000, $result['recommended_btu']);
        $this->assertSame(['sunlight', 'heat_equipment', 'extra_people'], array_keys($result['adjustments']));
    }
}
