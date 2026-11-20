<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\Search\ProductSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class SearchFilterIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_reuses_brand_btu_and_sort_contracts(): void
    {
        $brand = Brand::factory()->create(['name' => 'Gree', 'slug' => 'gree']);
        $otherBrand = Brand::factory()->create(['name' => 'Daikin', 'slug' => 'daikin']);
        $category = ProductCategory::factory()->create(['slug' => 'dieu-hoa-treo-tuong']);
        $governance = [
            'product_category_id' => $category->id,
            'taxonomy_status' => 'VERIFIED',
            'marketing_capacity_status' => 'VERIFIED',
            'technical_capacity_btu' => 18000,
            'is_active' => true,
        ];
        $higher = Product::factory()->create($governance + ['brand_id' => $brand->id, 'name' => 'Máy lạnh Alpha', 'regular_price' => 20_000_000]);
        $lower = Product::factory()->create($governance + ['brand_id' => $brand->id, 'name' => 'Máy lạnh Beta', 'regular_price' => 10_000_000]);
        Product::factory()->create($governance + ['brand_id' => $otherBrand->id, 'name' => 'Máy lạnh Gamma', 'regular_price' => 5_000_000]);

        $request = Request::create('/tim-kiem', 'GET', [
            'q' => 'Máy lạnh',
            'brand' => ['gree'],
            'btu' => ['18000'],
            'sort' => 'price_asc',
        ]);
        app()->instance('request', $request);

        $results = app(ProductSearchService::class)->search('Máy lạnh', 12, $request);

        $this->assertSame([$lower->id, $higher->id], $results->pluck('id')->all());
    }
}
