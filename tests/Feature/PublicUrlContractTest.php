<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicUrlContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_navigation_destinations_render_and_use_canonical_routes(): void
    {
        $category = ProductCategory::factory()->create(['is_active' => true, 'is_indexable' => true]);
        $brand = Brand::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(['is_active' => true, 'brand_id' => $brand->id, 'product_category_id' => $category->id]);
        foreach (['home', 'products.index', 'brands.index', 'compare.index', 'dealers.index', 'blog.index', 'quote.index', 'price-list', 'search.index', 'faq.dieu-hoa', 'contact', 'btu-calculator.index'] as $name) {
            $this->get(route($name))->assertOk();
        }
        foreach (['product.show' => $product->slug, 'category.show' => $category->slug, 'brands.show' => $brand->slug] as $name => $slug) {
            $this->get(route($name, $slug))->assertOk();
        }
    }

    public function test_legacy_generic_urls_redirect_with_filters_to_their_canonical_pages(): void
    {
        foreach ([
            '/bang-gia/dieu-hoa-tu-dung' => 'price-list',
            '/faq/dieu-hoa-tu-dung' => 'faq.dieu-hoa',
            '/cong-cu/chon-cong-suat-dieu-hoa-tu-dung' => 'btu-calculator.index',
        ] as $legacy => $name) {
            $this->get($legacy.'?brand=test')->assertStatus(301)->assertRedirect(route($name, ['brand' => 'test']));
            $this->get(route($name))->assertOk()->assertSee('rel="canonical" href="'.route($name).'"', false);
        }
    }

    public function test_sitemap_uses_current_generic_urls_and_keeps_the_specialist_landing(): void
    {
        $xml = $this->get('/sitemap-static.xml')->assertOk()->getContent();
        foreach (['price-list', 'faq.dieu-hoa', 'btu-calculator.index', 'landing'] as $name) {
            $this->assertStringContainsString('<loc>'.route($name).'</loc>', $xml);
        }
        $this->assertStringNotContainsString('/bang-gia/dieu-hoa-tu-dung', $xml);
        $this->assertStringNotContainsString('/faq/dieu-hoa-tu-dung', $xml);
        $this->assertStringNotContainsString('/cong-cu/chon-cong-suat-dieu-hoa-tu-dung', $xml);
    }
}
