<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\Product\MarketingCapacityFinalGateService;
use App\Services\Product\ProductTaxonomyAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ProductTaxonomyFinalGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_skyair_matrix_proposes_the_canonical_physical_category_without_writing(): void
    {
        $brand = Brand::factory()->create(['slug' => 'daikin']);
        $wall = ProductCategory::factory()->create(['id' => 29, 'slug' => 'dieu-hoa-treo-tuong']);
        ProductCategory::factory()->create(['id' => 24, 'slug' => 'dieu-hoa-am-tran-cassette']);
        $product = Product::factory()->create([
            'brand_id' => $brand->id,
            'product_category_id' => $wall->id,
            'sku' => 'FCTF50AVM-RZF50DVM',
            'model_code' => 'FCTF50AVM/RZF50DVM',
        ]);
        $before = $product->fresh()->getAttributes();

        $audit = app(ProductTaxonomyAuditService::class)->audit();
        $row = collect($audit['rows'])->sole('product_id', $product->id);

        $this->assertTrue($audit['read_only']);
        $this->assertSame('PROPOSE_UPDATE', $row['classification']);
        $this->assertSame('dieu-hoa-am-tran-cassette', $row['proposed_category_slug']);
        $this->assertSame('COMMERCIAL_CASSETTE', $row['canonical_taxonomy']);
        $this->assertTrue($row['filter_eligible_after_proposal']);
        $this->assertSame($before, $product->fresh()->getAttributes());
    }

    public function test_final_gate_rejects_technical_capacity_as_commercial_evidence(): void
    {
        $brand = Brand::factory()->create(['slug' => 'daikin']);
        $wall = ProductCategory::factory()->create(['id' => 29, 'slug' => 'dieu-hoa-treo-tuong']);
        ProductCategory::factory()->create(['id' => 24, 'slug' => 'dieu-hoa-am-tran-cassette']);
        $product = Product::factory()->create([
            'brand_id' => $brand->id,
            'product_category_id' => $wall->id,
            'sku' => 'FCTF50AVM-RZF50DVM',
            'model_code' => 'FCTF50AVM/RZF50DVM',
            'marketing_capacity_btu' => null,
            'technical_capacity_btu' => 17100,
            'capacity_kw' => '5.00',
        ]);

        $audit = app(MarketingCapacityFinalGateService::class)->audit();
        $row = collect($audit['rows'])->sole('product_id', $product->id);

        $this->assertSame('ELIGIBLE', $row['filter_eligibility']);
        $this->assertSame('NO_EVIDENCE', $row['status']);
        $this->assertNull($row['proposed_marketing_capacity_btu']);
        $this->assertNull($product->fresh()->marketing_capacity_btu);
    }

    public function test_taxonomy_command_writes_only_reports_and_not_product_state(): void
    {
        $brand = Brand::factory()->create(['slug' => 'daikin']);
        $wall = ProductCategory::factory()->create(['id' => 29, 'slug' => 'dieu-hoa-treo-tuong']);
        ProductCategory::factory()->create(['id' => 24, 'slug' => 'dieu-hoa-am-tran-cassette']);
        $product = Product::factory()->create([
            'brand_id' => $brand->id,
            'product_category_id' => $wall->id,
            'sku' => 'FCTF50AVM-RZF50DVM',
            'model_code' => 'FCTF50AVM/RZF50DVM',
        ]);
        $before = $product->fresh()->getAttributes();

        $this->assertSame(0, Artisan::call('catalog:audit-product-taxonomy', ['--json' => true]));
        $result = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertTrue($result['read_only']);
        $this->assertSame('NONE', $result['product_mutation']);
        $this->assertSame(1, $result['stats']['propose_update']);
        $this->assertSame($before, $product->fresh()->getAttributes());
    }
}
