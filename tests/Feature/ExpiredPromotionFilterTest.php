<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\Product\ProductFilterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ExpiredPromotionFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_is_sale_only_returns_products_with_an_active_effective_discount(): void
    {
        Carbon::setTestNow('2026-11-12 12:00:00');

        $expired = Product::factory()->create([
            'regular_price' => 10_000_000,
            'sale_price' => 8_000_000,
            'promotion_end_at' => now()->subDay(),
        ]);
        $active = Product::factory()->create([
            'regular_price' => 10_000_000,
            'sale_price' => 8_000_000,
            'promotion_start_at' => now()->subDay(),
            'promotion_end_at' => now()->addDay(),
        ]);

        $ids = app(ProductFilterService::class)->apply(
            Product::query(),
            Request::create('/san-pham', 'GET', ['is_sale' => '1']),
        )->pluck('id')->all();

        $this->assertSame([$active->id], $ids);
        $this->assertNotContains($expired->id, $ids);
    }
}
