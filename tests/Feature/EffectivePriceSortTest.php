<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\Product\ProductFilterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EffectivePriceSortTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_price_sort_uses_the_same_effective_price_as_product_cards(): void
    {
        Carbon::setTestNow('2026-11-12 12:00:00');

        $expiredLowRaw = Product::factory()->create([
            'regular_price' => 30_000_000,
            'sale_price' => 5_000_000,
            'promotion_end_at' => now()->subDay(),
        ]);
        $activeSale = Product::factory()->create([
            'regular_price' => 20_000_000,
            'sale_price' => 10_000_000,
            'promotion_start_at' => now()->subDay(),
            'promotion_end_at' => now()->addDay(),
        ]);

        $ascending = $this->sortedIds('price_asc');
        $descending = $this->sortedIds('price_desc');

        $this->assertSame([$activeSale->id, $expiredLowRaw->id], $ascending);
        $this->assertSame([$expiredLowRaw->id, $activeSale->id], $descending);
    }

    public function test_products_without_any_price_are_last_in_both_directions(): void
    {
        $priced = Product::factory()->create([
            'regular_price' => 10_000_000,
            'sale_price' => null,
        ]);
        $missing = Product::factory()->create([
            'regular_price' => null,
            'sale_price' => null,
        ]);

        $this->assertSame([$priced->id, $missing->id], $this->sortedIds('price_asc'));
        $this->assertSame([$priced->id, $missing->id], $this->sortedIds('price_desc'));
    }

    private function sortedIds(string $sort): array
    {
        return app(ProductFilterService::class)->apply(
            Product::query(),
            Request::create('/san-pham', 'GET', ['sort' => $sort]),
        )->pluck('id')->all();
    }
}
