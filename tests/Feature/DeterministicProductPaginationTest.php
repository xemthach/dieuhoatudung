<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\Product\ProductFilterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DeterministicProductPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_public_sorts_use_stable_id_tie_breakers_and_pages_do_not_overlap(): void
    {
        Carbon::setTestNow('2026-11-12 12:00:00');
        $category = ProductCategory::factory()->create(['slug' => 'dieu-hoa-treo-tuong']);

        $products = Product::factory()->count(30)->create([
            'product_category_id' => $category->id,
            'regular_price' => 10_000_000,
            'sale_price' => null,
            'technical_capacity_btu' => 18_000,
            'taxonomy_status' => 'VERIFIED',
            'marketing_capacity_status' => 'VERIFIED',
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
        ]);

        $ascending = $products->pluck('id')->sort()->values()->all();
        $descending = array_reverse($ascending);

        foreach ([
            'latest' => $descending,
            'price_asc' => $ascending,
            'price_desc' => $descending,
            'btu_asc' => $ascending,
            'btu_desc' => $descending,
        ] as $sort => $expected) {
            $firstRun = $this->allPages($sort);
            $secondRun = $this->allPages($sort);

            $this->assertSame($expected, $firstRun, $sort);
            $this->assertSame($firstRun, $secondRun, $sort.' repeated');
            $this->assertCount(30, array_unique($firstRun), $sort.' duplicate IDs');
        }

        Carbon::setTestNow();
    }

    /** @return list<int> */
    private function allPages(string $sort): array
    {
        $query = app(ProductFilterService::class)->apply(
            Product::query(),
            Request::create('/san-pham', 'GET', ['sort' => $sort]),
        );

        $pages = [];
        foreach ([1, 2, 3] as $page) {
            $pages[$page] = (clone $query)->forPage($page, 12)->pluck('id')->all();
        }

        $this->assertSame([], array_values(array_intersect($pages[1], $pages[2])));
        $this->assertSame([], array_values(array_intersect($pages[2], $pages[3])));

        return array_merge($pages[1], $pages[2], $pages[3]);
    }
}
