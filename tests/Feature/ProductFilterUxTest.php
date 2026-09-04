<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\Product\ProductFilterService;
use App\Services\Product\ProductTechnicalFactResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class ProductFilterUxTest extends TestCase
{
    use RefreshDatabase;

    public function test_capacity_filter_uses_marketing_capacity_not_legacy_btu(): void
    {
        $canonical = Product::factory()->create(['marketing_capacity_btu' => 24000, 'btu' => 9999]);
        $legacyOnly = Product::factory()->create(['marketing_capacity_btu' => null, 'btu' => 24000]);

        $query = app(ProductFilterService::class)->apply(
            Product::query(),
            Request::create('/san-pham', 'GET', ['btu' => ['24000']]),
        );

        $this->assertSame([$canonical->id], $query->pluck('id')->all());
        $this->assertNotContains($legacyOnly->id, $query->pluck('id')->all());
    }

    public function test_unknown_capacity_bucket_is_ignored(): void
    {
        $product = Product::factory()->create(['marketing_capacity_btu' => 24000]);

        $query = app(ProductFilterService::class)->apply(
            Product::query(),
            Request::create('/san-pham', 'GET', ['btu' => ['1-2 OR 1=1']]),
        );

        $this->assertSame([$product->id], $query->pluck('id')->all());
    }

    public function test_vrf_is_not_returned_by_rac_btu_bucket(): void
    {
        $category = ProductCategory::factory()->create(['name' => 'VRF Outdoor']);
        $vrf = Product::factory()->create([
            'product_category_id' => $category->id,
            'marketing_capacity_btu' => 24000,
        ]);

        $query = app(ProductFilterService::class)->apply(
            Product::query(),
            Request::create('/san-pham', 'GET', ['btu' => ['24000']]),
        );

        $this->assertNotContains($vrf->id, $query->pluck('id')->all());
    }

    public function test_capacity_filter_does_not_promote_technical_or_legacy_json_capacity_to_marketing_capacity(): void
    {
        $technicalOnly = Product::factory()->create([
            'marketing_capacity_btu' => null,
            'technical_capacity_btu' => 18000,
            'btu' => null,
            'specs_json' => [['key' => 'capacity_btu', 'value' => '18000']],
        ]);
        $rangeOnly = Product::factory()->create([
            'marketing_capacity_btu' => null,
            'technical_capacity_btu' => null,
            'btu' => null,
            'specs_json' => [['key' => 'capacity_btu', 'value' => '16400 / 17100']],
        ]);
        $legacyCommercial = Product::factory()->create([
            'marketing_capacity_btu' => null,
            'technical_capacity_btu' => null,
            'btu' => 18000,
            'specs_json' => [['key' => 'capacity_btu', 'value' => '18000']],
        ]);

        $ids = app(ProductFilterService::class)->apply(
            Product::query(),
            Request::create('/san-pham', 'GET', ['btu' => ['18000']]),
        )->pluck('id')->all();

        $this->assertNotContains($legacyCommercial->id, $ids);
        $this->assertNotContains($technicalOnly->id, $ids);
        $this->assertNotContains($rangeOnly->id, $ids);
    }

    public function test_multiple_capacity_buckets_are_or_conditions_and_combine_with_other_filters(): void
    {
        $category = ProductCategory::factory()->create();
        $brand = \App\Models\Brand::factory()->create();
        $eighteen = Product::factory()->create(['product_category_id' => $category->id, 'brand_id' => $brand->id, 'marketing_capacity_btu' => 18000, 'inverter' => true]);
        $fortyEight = Product::factory()->create(['product_category_id' => $category->id, 'brand_id' => $brand->id, 'marketing_capacity_btu' => 48000, 'inverter' => true]);
        $otherBrand = Product::factory()->create(['product_category_id' => $category->id, 'marketing_capacity_btu' => 18000, 'inverter' => true]);
        $otherTier = Product::factory()->create(['product_category_id' => $category->id, 'brand_id' => $brand->id, 'marketing_capacity_btu' => 24000, 'inverter' => true]);

        $ids = app(ProductFilterService::class)->apply(
            Product::query(),
            Request::create('/san-pham', 'GET', [
                'btu' => ['18000', '48000'],
                'brand' => [$brand->slug],
                'inverter' => '1',
            ]),
        )->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$eighteen->id, $fortyEight->id], $ids);
        $this->assertNotContains($otherBrand->id, $ids);
        $this->assertNotContains($otherTier->id, $ids);
    }

    public function test_category_listing_uses_the_same_marketing_capacity_contract(): void
    {
        $category = ProductCategory::factory()->create(['is_active' => true]);
        $matching = Product::factory()->create([
            'product_category_id' => $category->id,
            'marketing_capacity_btu' => 18000,
            'btu' => 9999,
            'is_active' => true,
        ]);
        $otherTier = Product::factory()->create([
            'product_category_id' => $category->id,
            'marketing_capacity_btu' => 24000,
            'is_active' => true,
        ]);

        $this->get(route('category.show', $category->slug).'?btu[]=18000')
            ->assertOk()
            ->assertSee($matching->name)
            ->assertDontSee($otherTier->name);
    }

    public function test_technical_fact_resolver_normalizes_list_associative_and_enriched_specs_shapes(): void
    {
        $resolver = app(ProductTechnicalFactResolver::class);

        foreach ([
            [['key' => 'capacity_btu', 'value' => '18000']],
            ['capacity_btu' => '18000'],
            [[
                'key' => 'capacity_btu',
                'value' => '18000',
                'source_pdf' => 'catalog.pdf',
                'verification_status' => 'verified_candidate',
            ]],
        ] as $specs) {
            $product = Product::factory()->create(['specs_json' => $specs]);

            $this->assertSame('18000', $resolver->specs($product)['capacity_btu'] ?? null);
        }
    }

    public function test_public_card_displays_distinct_marketing_btu_and_rated_kw_when_both_are_available(): void
    {
        $product = Product::factory()->create([
            'marketing_capacity_btu' => 18000,
            'technical_capacity_btu' => 17100,
            'capacity_kw' => 5.2,
            'specs_json' => null,
        ]);

        $html = Blade::render('<x-product-card :product="$product" />', ['product' => $product]);

        $this->assertStringContainsString('18,000 BTU', $html);
        $this->assertStringContainsString('5,2 kW', $html);
        $this->assertStringNotContainsString('17,100 BTU', $html);
    }
}
