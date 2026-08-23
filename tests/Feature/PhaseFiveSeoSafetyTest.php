<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\Merchant\MerchantFeedService;
use App\Services\Schema\SchemaService;
use App\Services\Sitemap\SitemapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhaseFiveSeoSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_schema_omits_unproven_optional_values_and_uses_real_images_only(): void
    {
        Storage::fake('public');
        $product = Product::factory()->create([
            'name' => 'Schema safety product',
            'sku' => null,
            'model_code' => 'MODEL-1',
            'main_image' => 'missing.jpg',
            'regular_price' => null,
            'sale_price' => null,
            'short_description' => '<p>Safe description</p>',
        ]);

        $schema = app(SchemaService::class)->product($product);

        $this->assertSame('Safe description', $schema['description']);
        $this->assertArrayNotHasKey('sku', $schema);
        $this->assertArrayNotHasKey('mpn', $schema);
        $this->assertArrayNotHasKey('image', $schema);
        $this->assertArrayNotHasKey('offers', $schema);
    }

    public function test_static_sitemap_does_not_claim_current_time_as_lastmod(): void
    {
        $xml = app(SitemapService::class)->buildStatic();

        $this->assertStringNotContainsString('<lastmod>', $xml);
        $this->assertStringNotContainsString(route('compare.index'), $xml);
    }

    public function test_merchant_feed_does_not_emit_default_category_or_unverified_mpn(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('products/real.jpg', 'test-image');
        $product = Product::factory()->create([
            'main_image' => 'products/real.jpg',
            'regular_price' => 100000,
            'model_code' => 'MODEL-1',
            'google_product_category' => null,
            'identifier_exists' => false,
        ]);

        $xml = app(MerchantFeedService::class)->generateXml();

        $this->assertStringContainsString('<g:image_link>', $xml);
        $this->assertStringNotContainsString('<g:google_product_category>604</g:google_product_category>', $xml);
        $this->assertStringNotContainsString('<g:mpn>', $xml);
    }
}
