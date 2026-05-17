<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\Merchant\MerchantFeedService;
use App\Services\Product\PromotionPriceResolver;
use App\Services\Seo\SeoAuditService;
use App\Services\Sitemap\SitemapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ProductPromotionSeoPhaseOneTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_sale_price_is_not_treated_as_active_discount(): void
    {
        Carbon::setTestNow('2026-05-17 12:00:00');

        $product = Product::factory()->create([
            'regular_price' => 10000000,
            'sale_price' => 8000000,
            'promotion_start_at' => now()->subDays(10),
            'promotion_end_at' => now()->subDay(),
        ]);

        $price = app(PromotionPriceResolver::class)->resolve($product);

        $this->assertFalse($price['has_discount']);
        $this->assertSame(10000000.0, $price['final_price']);
        $this->assertNull($price['sale_price']);

        Carbon::setTestNow();
    }

    public function test_merchant_feed_uses_regular_price_with_active_sale_price_and_effective_date(): void
    {
        Carbon::setTestNow('2026-05-17 12:00:00');

        Product::factory()->create([
            'name' => 'Gree test feed',
            'slug' => 'gree-test-feed',
            'regular_price' => 10000000,
            'sale_price' => 8000000,
            'promotion_start_at' => now()->subDay(),
            'promotion_end_at' => now()->addDay(),
            'main_image' => 'products/gree.jpg',
            'is_active' => true,
        ]);

        $xml = app(MerchantFeedService::class)->generateXml();

        $this->assertStringContainsString('<g:price>10000000 VND</g:price>', $xml);
        $this->assertStringContainsString('<g:sale_price>8000000 VND</g:sale_price>', $xml);
        $this->assertStringContainsString('<g:sale_price_effective_date>', $xml);

        Carbon::setTestNow();
    }

    public function test_sitemap_excludes_noindex_products_categories_and_compare_page(): void
    {
        $indexableProduct = Product::factory()->create(['slug' => 'indexable-product', 'is_active' => true, 'robots' => 'index,follow']);
        Product::factory()->create(['slug' => 'noindex-product', 'is_active' => true, 'robots' => 'noindex,follow']);

        $indexableCategory = ProductCategory::factory()->create(['slug' => 'indexable-category', 'is_active' => true, 'robots' => 'index,follow']);
        ProductCategory::factory()->create(['slug' => 'noindex-category', 'is_active' => true, 'robots' => 'noindex,follow']);

        $sitemap = app(SitemapService::class);
        $productsXml = $sitemap->buildProducts();
        $categoriesXml = $sitemap->buildCategories();
        $staticXml = $sitemap->buildStatic();

        $this->assertStringContainsString(route('product.show', $indexableProduct->slug), $productsXml);
        $this->assertStringNotContainsString('noindex-product', $productsXml);
        $this->assertStringContainsString(route('category.show', $indexableCategory->slug), $categoriesXml);
        $this->assertStringNotContainsString('noindex-category', $categoriesXml);
        $this->assertStringNotContainsString(route('compare.index'), $staticXml);
    }

    public function test_merchant_readiness_issues_are_classified_with_supported_severities(): void
    {
        app(SeoAuditService::class)->clearCache();

        Product::factory()->create([
            'name' => 'Missing merchant basics',
            'regular_price' => null,
            'sale_price' => null,
            'main_image' => null,
            'brand_id' => null,
            'model_code' => null,
            'gtin' => null,
            'identifier_exists' => false,
            'is_active' => true,
        ]);

        $issues = app(SeoAuditService::class)->run(fresh: true)
            ->where('entity', 'Merchant')
            ->values();

        $this->assertNotEmpty($issues);
        $this->assertContains('critical', $issues->pluck('severity')->all());
        $this->assertContains('warning', $issues->pluck('severity')->all());
        $this->assertContains('notice', $issues->pluck('severity')->all());
        $this->assertFalse($issues->pluck('message')->contains('Product'));
    }
}
