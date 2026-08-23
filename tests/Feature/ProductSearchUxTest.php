<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Product;
use App\Services\Search\ProductSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSearchUxTest extends TestCase
{
    use RefreshDatabase;

    public function test_brand_only_search_returns_matching_products(): void
    {
        $brand = Brand::factory()->create(['name' => 'Gree']);
        Product::factory()->create([
            'brand_id' => $brand->id,
            'name' => 'Điều hòa treo tường',
            'model_code' => 'GCC42S6I',
        ]);

        $results = app(ProductSearchService::class)->search('Gree');

        $this->assertSame(1, $results->total());
        $this->assertSame('GCC42S6I', $results->first()->model_code);
    }

    public function test_vietnamese_unaccented_search_matches_product_slug(): void
    {
        Product::factory()->create([
            'name' => 'Điều hòa Gree',
            'slug' => 'dieu-hoa-gree',
            'model_code' => 'VN-SEARCH-1',
        ]);

        $results = app(ProductSearchService::class)->search('dieu hoa');

        $this->assertSame(1, $results->total());
        $this->assertSame('VN-SEARCH-1', $results->first()->model_code);
    }
}
