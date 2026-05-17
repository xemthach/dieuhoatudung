<?php

namespace Tests\Feature;

use App\Enums\DiscountType;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Promotion;
use App\Services\Merchant\MerchantFeedService;
use App\Services\Product\PromotionPriceResolver;
use App\Services\Seo\SeoAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PromotionSeoPhaseTwoTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_scoped_promotion_updates_resolved_price(): void
    {
        Carbon::setTestNow('2026-05-17 12:00:00');

        $product = Product::factory()->create([
            'regular_price' => 10000000,
            'sale_price' => null,
        ]);

        $promotion = Promotion::factory()->create([
            'scope' => 'product',
            'discount_type' => DiscountType::Percent,
            'discount_value' => 20,
            'start_at' => now()->subDay(),
            'end_at' => now()->addDay(),
        ]);
        $promotion->products()->attach($product);

        $price = app(PromotionPriceResolver::class)->resolve($product);

        $this->assertTrue($price['has_discount']);
        $this->assertSame(8000000.0, $price['final_price']);
        $this->assertSame($promotion->id, $price['promotion_id']);
        $this->assertSame('promotion_product', $price['promotion_source']);

        Carbon::setTestNow();
    }

    public function test_category_brand_and_global_promotions_choose_best_price(): void
    {
        Carbon::setTestNow('2026-05-17 12:00:00');

        $brand = Brand::factory()->create();
        $category = ProductCategory::factory()->create();
        $product = Product::factory()->create([
            'brand_id' => $brand->id,
            'product_category_id' => $category->id,
            'regular_price' => 10000000,
            'sale_price' => null,
        ]);

        Promotion::factory()->create(['scope' => 'global', 'discount_type' => DiscountType::Percent, 'discount_value' => 5]);
        $categoryPromotion = Promotion::factory()->create(['scope' => 'category', 'discount_type' => DiscountType::Percent, 'discount_value' => 10]);
        $brandPromotion = Promotion::factory()->create(['scope' => 'brand', 'discount_type' => DiscountType::Fixed, 'discount_value' => 3000000]);
        $categoryPromotion->categories()->attach($category);
        $brandPromotion->brands()->attach($brand);

        $price = app(PromotionPriceResolver::class)->resolve($product);

        $this->assertSame(7000000.0, $price['final_price']);
        $this->assertSame($brandPromotion->id, $price['promotion_id']);
        $this->assertSame('promotion_brand', $price['promotion_source']);

        Carbon::setTestNow();
    }

    public function test_seo_audit_reports_brand_issues(): void
    {
        app(SeoAuditService::class)->clearCache();

        Brand::factory()->create([
            'name' => 'Brand thiếu SEO',
            'slug' => 'brand-thieu-seo',
            'is_active' => true,
            'seo_title' => null,
            'seo_description' => null,
            'description' => 'Ngắn',
            'logo' => null,
        ]);

        $brandIssues = app(SeoAuditService::class)->run(fresh: true)
            ->where('entity', 'Brand')
            ->pluck('message')
            ->values()
            ->all();

        $this->assertContains('Thiếu SEO Title', $brandIssues);
        $this->assertContains('Thiếu SEO Description', $brandIssues);
        $this->assertContains('Mô tả thương hiệu quá ngắn', $brandIssues);
        $this->assertContains('Thiếu logo thương hiệu', $brandIssues);
    }

    public function test_merchant_diagnostics_include_sale_and_category_counters(): void
    {
        Carbon::setTestNow('2026-05-17 12:00:00');

        Product::factory()->create([
            'regular_price' => 10000000,
            'sale_price' => 9000000,
            'promotion_start_at' => now()->subDays(3),
            'promotion_end_at' => now()->subDay(),
            'google_product_category' => null,
            'is_active' => true,
        ]);
        Product::factory()->create([
            'regular_price' => 10000000,
            'sale_price' => 8000000,
            'promotion_start_at' => now()->subDay(),
            'promotion_end_at' => now()->addDay(),
            'google_product_category' => '',
            'is_active' => true,
        ]);

        $diagnostics = app(MerchantFeedService::class)->getDiagnostics();

        $this->assertSame(1, $diagnostics['expired_sale_prices']);
        $this->assertSame(1, $diagnostics['active_sale_prices']);
        $this->assertSame(2, $diagnostics['missing_google_product_category']);

        Carbon::setTestNow();
    }
}
