<?php

namespace Tests\Unit;

use App\Services\Calculator\BtuCalculatorService;
use Tests\TestCase;

class BtuCalculatorBoundaryTest extends TestCase
{
    public function test_invalid_area_fails_closed(): void
    {
        $result = app(BtuCalculatorService::class)->calculate(0, 3, 'van_phong');

        $this->assertNotEmpty($result['warnings']);
        $this->assertNull($result['recommended_btu']);
    }

    public function test_valid_rac_estimate_rounds_to_a_standard_btu_tier(): void
    {
        $result = app(BtuCalculatorService::class)->calculate(
            10, 3, 'van_phong', 0, false, false, 'area', 'consumer-estimate-v1',
        );

        $this->assertSame(5800, $result['calculated_btu']);
        $this->assertSame(9000, $result['recommended_btu']);
        $this->assertGreaterThan(0, $result['recommended_hp']);
        $this->assertSame('consumer-estimate-v1', $result['rule_version']);
    }

    public function test_current_formula_golden_case_is_stable(): void
    {
        $result = app(BtuCalculatorService::class)->calculate(
            30, 4, 'nha_o', 12, true, true, 'area', 'consumer-estimate-v1',
        );

        $this->assertSame(20567, $result['calculated_btu']);
        $this->assertSame(24000, $result['recommended_btu']);
        $this->assertSame(2.7, $result['recommended_hp']);
        $this->assertSame(120, $result['cooling_w_per_m2']);
        $this->assertSame(4, count($result['adjustment_breakdown']));
    }

    public function test_higher_load_inputs_never_reduce_the_estimate(): void
    {
        $service = app(BtuCalculatorService::class);
        $base = $service->calculate(30, 3, 'nha_o', 10, false, false)['calculated_btu'];

        $this->assertGreaterThanOrEqual($base, $service->calculate(31, 3, 'nha_o', 10, false, false)['calculated_btu']);
        $this->assertGreaterThanOrEqual($base, $service->calculate(30, 4, 'nha_o', 10, false, false)['calculated_btu']);
        $this->assertGreaterThanOrEqual($base, $service->calculate(30, 3, 'nha_o', 11, false, false)['calculated_btu']);
        $this->assertGreaterThanOrEqual($base, $service->calculate(30, 3, 'nha_o', 10, true, false)['calculated_btu']);
        $this->assertGreaterThanOrEqual($base, $service->calculate(30, 3, 'nha_o', 10, false, true)['calculated_btu']);
    }

    public function test_service_rejects_out_of_contract_bounds_even_without_http_validation(): void
    {
        $service = app(BtuCalculatorService::class);

        foreach ([
            $service->calculate(4.99, 3, 'nha_o'),
            $service->calculate(5000.01, 3, 'nha_o'),
            $service->calculate(30, 1.99, 'nha_o'),
            $service->calculate(30, 15.01, 'nha_o'),
            $service->calculate(30, 3, 'nha_o', 5001),
        ] as $result) {
            $this->assertNull($result['recommended_btu']);
            $this->assertStringStartsWith(BtuCalculatorService::WARNING_OUT_OF_RANGE, $result['warnings'][0]);
        }
    }

    public function test_large_load_escalates_without_inventing_multi_unit_or_vrf_design(): void
    {
        $result = app(BtuCalculatorService::class)->calculate(
            5000, 3, 'nha_xuong_nang', 0, false, false, 'area', 'consumer-estimate-v2',
        );

        $this->assertGreaterThanOrEqual(100000, $result['recommended_btu']);
        $this->assertStringContainsString('cần khảo sát kỹ thuật', $result['note']);
        $this->assertStringNotContainsString('chia nhiều máy', $result['note']);
        $this->assertStringNotContainsString('VRF', $result['note']);
        $this->assertStringNotContainsString('đề xuất model', $result['explanation']);
    }
}
