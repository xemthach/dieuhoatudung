<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\Product\ProductFilterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class BtuSortGovernanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_btu_sort_puts_verified_eligible_products_first_and_invalid_states_last(): void
    {
        $eligibleCategory = ProductCategory::factory()->create([
            'slug' => 'dieu-hoa-treo-tuong',
        ]);
        $vrfCategory = ProductCategory::factory()->create([
            'slug' => 'dieu-hoa-trung-tam-vrf-gmv',
        ]);

        $verified12000 = $this->product($eligibleCategory->id, 12000, 'VERIFIED', 'VERIFIED');
        $verified18000 = $this->product($eligibleCategory->id, 18000, 'VERIFIED', 'VERIFIED');
        $unverified9000 = $this->product($eligibleCategory->id, 9000, 'VERIFIED', 'UNVERIFIED');
        $unresolved12000 = $this->product($eligibleCategory->id, 12000, 'UNRESOLVED', 'VERIFIED');
        $vrf18000 = $this->product($vrfCategory->id, 18000, 'VERIFIED', 'VERIFIED');
        $nullCapacity = $this->product($eligibleCategory->id, null, 'VERIFIED', 'VERIFIED');

        $allIds = [
            $verified12000->id,
            $verified18000->id,
            $unverified9000->id,
            $unresolved12000->id,
            $vrf18000->id,
            $nullCapacity->id,
        ];

        $asc = $this->sortedIds('btu_asc');
        $desc = $this->sortedIds('btu_desc');

        $this->assertSame([$verified12000->id, $verified18000->id], array_slice($asc, 0, 2));
        $this->assertSame([$verified18000->id, $verified12000->id], array_slice($desc, 0, 2));
        $this->assertEqualsCanonicalizing($allIds, $asc);
        $this->assertEqualsCanonicalizing($allIds, $desc);
        $this->assertSame($asc, $this->sortedIds('btu_asc'));
        $this->assertSame($desc, $this->sortedIds('btu_desc'));
    }

    private function product(
        int $categoryId,
        ?int $capacity,
        string $taxonomyStatus,
        string $capacityStatus,
    ): Product {
        return Product::factory()->create([
            'product_category_id' => $categoryId,
            'is_active' => true,
            'marketing_capacity_btu' => $capacity,
            'taxonomy_status' => $taxonomyStatus,
            'marketing_capacity_status' => $capacityStatus,
        ]);
    }

    /** @return list<int> */
    private function sortedIds(string $sort): array
    {
        $query = Product::query()->where('is_active', true);
        app(ProductFilterService::class)->apply(
            $query,
            Request::create('/san-pham', 'GET', ['sort' => $sort]),
        );

        return $query->pluck('id')->all();
    }
}
