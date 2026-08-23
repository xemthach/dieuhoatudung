<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\Product\ProductFilterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class ProductFilterUxTest extends TestCase
{
    use RefreshDatabase;

    public function test_capacity_filter_uses_marketing_capacity_not_legacy_btu(): void
    {
        $canonical = Product::factory()->create(['marketing_capacity_btu' => 24000, 'btu' => 9999]);
        $legacyOnly = Product::factory()->create(['marketing_capacity_btu' => null, 'btu' => 24000]);

        $query = app(ProductFilterService::class)->apply(
            Product::query(),
            Request::create('/san-pham', 'GET', ['btu' => ['24000']]),
        );

        $this->assertSame([$canonical->id], $query->pluck('id')->all());
        $this->assertNotContains($legacyOnly->id, $query->pluck('id')->all());
    }

    public function test_unknown_capacity_bucket_is_ignored(): void
    {
        $product = Product::factory()->create(['marketing_capacity_btu' => 24000]);

        $query = app(ProductFilterService::class)->apply(
            Product::query(),
            Request::create('/san-pham', 'GET', ['btu' => ['1-2 OR 1=1']]),
        );

        $this->assertSame([$product->id], $query->pluck('id')->all());
    }

    public function test_vrf_is_not_returned_by_rac_btu_bucket(): void
    {
        $category = ProductCategory::factory()->create(['name' => 'VRF Outdoor']);
        $vrf = Product::factory()->create([
            'product_category_id' => $category->id,
            'marketing_capacity_btu' => 24000,
        ]);

        $query = app(ProductFilterService::class)->apply(
            Product::query(),
            Request::create('/san-pham', 'GET', ['btu' => ['24000']]),
        );

        $this->assertNotContains($vrf->id, $query->pluck('id')->all());
    }
}
