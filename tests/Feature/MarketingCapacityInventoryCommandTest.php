<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class MarketingCapacityInventoryCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_is_read_only_and_emits_source_matching_context(): void
    {
        $brand = Brand::factory()->create(['slug' => 'daikin']);
        $category = ProductCategory::factory()->create([
            'slug' => 'dieu-hoa-treo-tuong',
            'name' => 'Điều hòa treo tường',
        ]);
        $product = Product::factory()->create([
            'brand_id' => $brand->id,
            'product_category_id' => $category->id,
            'sku' => 'FTKF50ZVMV-RKF50ZVMV',
            'model_code' => 'FTKF50ZVMV/RKF50ZVMV',
            'series' => 'FTKF',
            'product_type' => 'Điều hòa treo tường',
            'marketing_capacity_btu' => null,
            'technical_capacity_btu' => 17100,
            'capacity_kw' => '5.00',
        ]);
        $before = $product->fresh()->getAttributes();

        $this->assertSame(0, Artisan::call('catalog:export-marketing-capacity-inventory', ['--json' => true]));
        $result = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertTrue($result['read_only']);
        $this->assertSame(1, $result['count']);
        $this->assertSame([
            'product_id' => $product->id,
            'brand_slug' => 'daikin',
            'category_slug' => 'dieu-hoa-treo-tuong',
            'category_name' => 'Điều hòa treo tường',
            'sku' => 'FTKF50ZVMV-RKF50ZVMV',
            'model_code' => 'FTKF50ZVMV/RKF50ZVMV',
            'series' => 'FTKF',
            'product_type' => 'Điều hòa treo tường',
            'marketing_capacity_btu' => null,
            'technical_capacity_btu' => 17100,
            'capacity_kw' => '5.00',
        ], $result['products'][0]);
        $this->assertSame($before, $product->fresh()->getAttributes());
    }
}
