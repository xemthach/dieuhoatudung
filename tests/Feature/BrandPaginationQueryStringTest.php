<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandPaginationQueryStringTest extends TestCase
{
    use RefreshDatabase;

    public function test_brand_pagination_preserves_the_selected_sort(): void
    {
        $brand = Brand::factory()->create(['is_active' => true]);
        Product::factory()->count(13)->create([
            'brand_id' => $brand->id,
            'is_active' => true,
            'regular_price' => 10_000_000,
            'sale_price' => null,
        ]);

        $response = $this->get(route('brands.show', [
            'slug' => $brand->slug,
            'sort' => 'price_asc',
        ]));

        $response->assertOk();
        $pageTwoUrl = $response->viewData('products')->url(2);
        $this->assertStringContainsString('sort=price_asc', $pageTwoUrl);
        $this->assertStringContainsString('page=2', $pageTwoUrl);
    }
}
