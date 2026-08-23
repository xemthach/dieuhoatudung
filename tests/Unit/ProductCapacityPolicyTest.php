<?php

namespace Tests\Unit;

use App\Services\Product\ProductCapacityPolicy;
use App\Services\Product\ProductHvacClassResolver;
use App\Services\Product\ProductTechnicalFactResolver;
use App\Models\Product;
use Tests\TestCase;

class ProductCapacityPolicyTest extends TestCase
{
    private function policy(): ProductCapacityPolicy
    {
        return new ProductCapacityPolicy(new ProductHvacClassResolver(), new ProductTechnicalFactResolver());
    }

    private function product(string $type): Product
    {
        $product = new Product();
        $product->product_type = $type;
        $product->brand_id = 4;
        $product->model_code = 'TEST-MODEL';
        $product->sku = 'TEST-SKU';
        return $product;
    }

    public function test_verified_vrf_source_native_kw_does_not_require_marketing_pair_but_keeps_authority_gates(): void
    {
        $result = $this->policy()->evaluate($this->product('Điều hòa trung tâm VRF/GMV'), [
            'technical' => ['verified' => true, 'source_native' => true, 'role' => 'RATED', 'value' => 14.0, 'unit' => 'KW'],
        ], ['transport_verified' => true, 'regional_verified' => true], ProductCapacityPolicy::OUTDOOR_UNIT_FACTS);

        $this->assertTrue($result['eligible']);
        $this->assertSame('KW', $result['technical']['unit']);
        $this->assertFalse($result['pair_present']);
    }

    public function test_vrf_does_not_implicitly_accept_a_converted_btu_claim(): void
    {
        $result = $this->policy()->evaluate($this->product('Điều hòa trung tâm VRF/GMV'), [
            'technical' => ['verified' => true, 'source_native' => false, 'role' => 'RATED', 'value' => 47768, 'unit' => 'BTU_PER_HOUR', 'derived' => true],
        ], ['transport_verified' => true, 'regional_verified' => true]);

        $this->assertFalse($result['eligible']);
        $this->assertSame('SOURCE_NATIVE_TECHNICAL_CAPACITY_REQUIRED', $result['reason']);
    }

    public function test_vrf_combination_claim_requires_lineage(): void
    {
        $result = $this->policy()->evaluate($this->product('Điều hòa trung tâm VRF/GMV'), [
            'technical' => ['verified' => true, 'source_native' => true, 'role' => 'RATED', 'value' => 14.0, 'unit' => 'KW'],
        ], ['transport_verified' => true, 'regional_verified' => true, 'combination_verified' => false], ProductCapacityPolicy::SYSTEM_COMBINATION_FACTS);

        $this->assertFalse($result['eligible']);
        $this->assertSame('COMBINATION_LINEAGE_REQUIRED', $result['reason']);
    }

    public function test_rac_keeps_marketing_technical_pair_requirement(): void
    {
        $result = $this->policy()->evaluate($this->product('Điều hòa âm trần Cassette'), [
            'technical' => ['verified' => true, 'source_native' => true, 'role' => 'RATED', 'value' => 42650, 'unit' => 'BTU_PER_HOUR'],
        ], ['transport_verified' => true, 'regional_verified' => true]);

        $this->assertFalse($result['eligible']);
        $this->assertSame('RAC_MARKETING_TECHNICAL_PAIR_REQUIRED', $result['reason']);
    }

    public function test_unknown_class_fails_closed(): void
    {
        $result = $this->policy()->evaluate($this->product(''), [
            'technical' => ['verified' => true, 'source_native' => true, 'role' => 'RATED', 'value' => 14.0, 'unit' => 'KW'],
        ], ['transport_verified' => true, 'regional_verified' => true]);

        $this->assertFalse($result['eligible']);
        $this->assertSame('UNKNOWN_OR_UNVERIFIED_PRODUCT_CLASS', $result['reason']);
    }
}
