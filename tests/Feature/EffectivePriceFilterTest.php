<?php

namespace Tests\Feature;

use App\Enums\DiscountType;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Promotion;
use App\Services\Product\ProductFilterService;
use App\Services\Product\ProductEffectivePriceQuery;
use App\Services\Product\PromotionPriceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EffectivePriceFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_expired_raw_sale_is_not_used_by_price_max_filter(): void
    {
        Carbon::setTestNow('2026-11-12 12:00:00');

        $product = Product::factory()->create([
            'regular_price' => 39_390_000,
            'sale_price' => 29_666_666,
            'promotion_start_at' => now()->subMonths(4),
            'promotion_end_at' => now()->subMonths(2),
        ]);

        $displayed = app(PromotionPriceResolver::class)->resolve($product);
        $expression = app(ProductEffectivePriceQuery::class)->expression();
        $queryPrice = Product::query()
            ->selectRaw('('.$expression['sql'].') as effective_price', $expression['bindings'])
            ->whereKey($product)
            ->value('effective_price');
        $query = app(ProductFilterService::class)->apply(
            Product::query(),
            Request::create('/san-pham', 'GET', ['price_max' => 30_000_000]),
        );
        $ids = $query->pluck('id')->all();

        $this->assertSame(39_390_000.0, $displayed['final_price']);
        $this->assertEquals(39_390_000.0, $queryPrice);
        $this->assertNotContains($product->id, $ids, $query->toRawSql());
    }

    public function test_active_global_promotion_is_used_by_price_max_filter(): void
    {
        Carbon::setTestNow('2026-11-12 12:00:00');

        $product = Product::factory()->create([
            'regular_price' => 10_000_000,
            'sale_price' => null,
        ]);
        Promotion::factory()->create([
            'scope' => 'global',
            'discount_type' => DiscountType::Percent,
            'discount_value' => 20,
            'start_at' => now()->subDay(),
            'end_at' => now()->addDay(),
            'is_active' => true,
        ]);

        $displayed = app(PromotionPriceResolver::class)->resolve($product);
        $ids = app(ProductFilterService::class)->apply(
            Product::query(),
            Request::create('/san-pham', 'GET', ['price_max' => 8_000_000]),
        )->pluck('id')->all();

        $this->assertSame(8_000_000.0, $displayed['final_price']);
        $this->assertContains($product->id, $ids);
    }

    public function test_price_boundaries_are_inclusive_for_regular_and_active_sale_prices(): void
    {
        Carbon::setTestNow('2026-11-12 12:00:00');

        $regularOnly = Product::factory()->create([
            'regular_price' => 10_000_000,
            'sale_price' => null,
        ]);
        $activeSale = Product::factory()->create([
            'regular_price' => 12_000_000,
            'sale_price' => 9_000_000,
            'promotion_start_at' => now()->subDay(),
            'promotion_end_at' => now()->addDay(),
        ]);

        $atMinimum = $this->filterIds(['price_min' => 10_000_000]);
        $atMaximum = $this->filterIds(['price_max' => 9_000_000]);

        $this->assertContains($regularOnly->id, $atMinimum);
        $this->assertContains($activeSale->id, $atMaximum);
    }

    public function test_future_sale_and_sale_not_lower_than_regular_use_regular_price(): void
    {
        Carbon::setTestNow('2026-11-12 12:00:00');

        $future = Product::factory()->create([
            'regular_price' => 10_000_000,
            'sale_price' => 7_000_000,
            'promotion_start_at' => now()->addDay(),
            'promotion_end_at' => now()->addDays(2),
        ]);
        $notLower = Product::factory()->create([
            'regular_price' => 10_000_000,
            'sale_price' => 11_000_000,
        ]);

        $ids = $this->filterIds(['price_max' => 9_000_000]);

        $this->assertNotContains($future->id, $ids);
        $this->assertNotContains($notLower->id, $ids);
    }

    public function test_inactive_promotion_does_not_change_effective_price(): void
    {
        Carbon::setTestNow('2026-11-12 12:00:00');

        $product = Product::factory()->create([
            'regular_price' => 10_000_000,
            'sale_price' => null,
        ]);
        Promotion::factory()->create([
            'scope' => 'global',
            'discount_type' => DiscountType::Percent,
            'discount_value' => 50,
            'is_active' => false,
        ]);

        $this->assertNotContains($product->id, $this->filterIds(['price_max' => 5_000_000]));
    }

    public function test_product_category_and_brand_scoped_promotions_match_card_prices(): void
    {
        Carbon::setTestNow('2026-11-12 12:00:00');
        $brand = Brand::factory()->create();
        $category = ProductCategory::factory()->create();
        $products = collect([
            'product' => Product::factory()->create(['regular_price' => 10_000_000]),
            'category' => Product::factory()->create([
                'regular_price' => 10_000_000,
                'product_category_id' => $category->id,
            ]),
            'brand' => Product::factory()->create([
                'regular_price' => 10_000_000,
                'brand_id' => $brand->id,
            ]),
        ]);

        foreach ($products as $scope => $product) {
            $promotion = Promotion::factory()->create([
                'scope' => $scope,
                'discount_type' => DiscountType::Percent,
                'discount_value' => 20,
            ]);
            match ($scope) {
                'product' => $promotion->products()->attach($product),
                'category' => $promotion->categories()->attach($category),
                'brand' => $promotion->brands()->attach($brand),
            };
        }

        $matching = $this->filterIds(['price_max' => 8_000_000]);
        foreach ($products as $product) {
            $this->assertSame(8_000_000.0, app(PromotionPriceResolver::class)->resolve($product)['final_price']);
            $this->assertContains($product->id, $matching);
        }
    }

    public function test_null_regular_and_zero_prices_follow_resolver_fallbacks(): void
    {
        Carbon::setTestNow('2026-11-12 12:00:00');

        $rawOnly = Product::factory()->create([
            'regular_price' => null,
            'sale_price' => 4_000_000,
            'promotion_end_at' => now()->subDay(),
        ]);
        $zero = Product::factory()->create([
            'regular_price' => 0,
            'sale_price' => null,
        ]);

        $this->assertContains($rawOnly->id, $this->filterIds(['price_max' => 4_000_000]));
        $this->assertContains($zero->id, $this->filterIds(['price_max' => 0]));
    }

    /** @param array<string, mixed> $filters */
    private function filterIds(array $filters): array
    {
        return app(ProductFilterService::class)->apply(
            Product::query(),
            Request::create('/san-pham', 'GET', $filters),
        )->pluck('id')->all();
    }
}
