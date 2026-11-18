<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Product;
use App\Services\Product\ProductFilterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class ProductFilterInputHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_sort_falls_back_to_latest_without_becoming_an_active_filter(): void
    {
        $older = Product::factory()->create(['created_at' => now()->subDay()]);
        $newer = Product::factory()->create(['created_at' => now()]);
        $request = Request::create('/san-pham', 'GET', ['sort' => 'DROP TABLE products']);

        $query = app(ProductFilterService::class)->apply(Product::query(), $request);

        $this->assertSame([$newer->id, $older->id], $query->pluck('id')->all());
        $this->assertFalse(app(ProductFilterService::class)->hasActiveFilters($request));
    }

    public function test_multi_value_inputs_are_deduplicated_and_capped(): void
    {
        $slugs = [];
        $ids = [];
        foreach (range(1, 21) as $number) {
            $brand = Brand::factory()->create(['slug' => 'brand-'.$number]);
            $product = Product::factory()->create(['brand_id' => $brand->id]);
            $slugs[] = $brand->slug;
            $ids[] = $product->id;
        }

        $request = Request::create('/san-pham', 'GET', [
            'brand' => [...$slugs, $slugs[0], $slugs[1]],
        ]);
        $actual = app(ProductFilterService::class)
            ->apply(Product::query(), $request)
            ->pluck('id')
            ->all();

        $this->assertCount(ProductFilterService::MAX_ARRAY_VALUES, $actual);
        $this->assertEqualsCanonicalizing(array_slice($ids, 0, ProductFilterService::MAX_ARRAY_VALUES), $actual);
    }

    public function test_non_finite_or_excessive_prices_are_ignored(): void
    {
        $product = Product::factory()->create(['regular_price' => 10_000_000]);

        foreach (['1e9999', '1000000000001'] as $input) {
            $request = Request::create('/san-pham', 'GET', ['price_min' => $input]);
            $ids = app(ProductFilterService::class)->apply(Product::query(), $request)->pluck('id')->all();
            $this->assertContains($product->id, $ids);
        }
    }
}
