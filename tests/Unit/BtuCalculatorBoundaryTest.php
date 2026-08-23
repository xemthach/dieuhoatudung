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
        $result = app(BtuCalculatorService::class)->calculate(10, 3, 'van_phong');

        $this->assertSame(5800, $result['calculated_btu']);
        $this->assertSame(9000, $result['recommended_btu']);
        $this->assertGreaterThan(0, $result['recommended_hp']);
    }
}
