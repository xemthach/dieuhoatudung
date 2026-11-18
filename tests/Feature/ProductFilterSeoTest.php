<?php

namespace Tests\Feature;

use App\Models\Brand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductFilterSeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_brand_sort_variation_is_noindex_with_clean_canonical(): void
    {
        $brand = Brand::factory()->create(['is_active' => true]);

        $response = $this->get(route('brands.show', $brand->slug).'?sort=price_asc');

        $response->assertOk();
        $response->assertSee('<meta name="robots" content="noindex,follow">', false);
        $response->assertSee('<link rel="canonical" href="'.route('brands.show', $brand->slug).'">', false);
    }

    public function test_invalid_brand_sort_is_equivalent_to_default_for_seo(): void
    {
        $brand = Brand::factory()->create(['is_active' => true]);

        $response = $this->get(route('brands.show', $brand->slug).'?sort=invalid');

        $response->assertOk();
        $response->assertDontSee('<meta name="robots" content="noindex,follow">', false);
    }
}
