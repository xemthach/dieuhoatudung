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

    public function test_rated_capacity_entered_in_admin_matches_the_listing_without_a_marketing_tier(): void
    {
        $category = ProductCategory::factory()->create(['slug' => 'dieu-hoa-treo-tuong']);
        $product = Product::factory()->create([
            'product_category_id' => $category->id, 'is_active' => true,
            'technical_capacity_btu' => 11900, 'capacity_kw' => 3.5, 'hp' => 1.5,
            'marketing_capacity_btu' => null, 'taxonomy_status' => null, 'marketing_capacity_status' => null,
        ]);
        $this->get('/san-pham?btu[]=9000-12000')->assertOk()->assertSee('id="product-card-'.$product->id.'"', false);
        $this->get('/san-pham?btu[]=18000')->assertOk()->assertDontSee('id="product-card-'.$product->id.'"', false);
    }

    public function test_rated_buckets_use_inclusive_boundaries_and_group_with_other_filters(): void
    {
        $category = ProductCategory::factory()->create(['slug' => 'dieu-hoa-giau-tran-noi-ong-gio']);
        $base = ['product_category_id' => $category->id, 'is_active' => true, 'inverter' => true,
            'marketing_capacity_status' => 'VERIFIED', 'taxonomy_status' => 'VERIFIED'];
        foreach ([8999, 9000, 11999, 12000, 12001, 18000, 24000, 49999, 50000, 55000, 60000, 100001] as $capacity) {
            Product::factory()->create([...$base, 'technical_capacity_btu' => $capacity]);
        }
        foreach ([['is_active' => false], ['deleted_at' => now()], ['inverter' => false]] as $override) {
            Product::factory()->create([...$base, 'technical_capacity_btu' => 12000, ...$override]);
        }

        $service = app(ProductFilterService::class);
        foreach ([
            'btu[]=9000%2D12000%20' => [9000, 11999, 12000],
            'btu[]=9000-12000&btu[]=18000' => [9000, 11999, 12000, 18000],
            'btu[]=24000' => [24000],
            'btu[]=50000-100000' => [50000, 55000, 60000, 100001],
        ] as $query => $expected) {
            $ids = $service->apply(Product::where('is_active', true), Request::create('/san-pham?inverter=1&'.$query))
                ->reorder('technical_capacity_btu')->pluck('technical_capacity_btu')->all();
            $this->assertSame($expected, $ids);
        }
    }

    public function test_capacity_filter_uses_rated_capacity_not_legacy_btu(): void
    {
        $category = ProductCategory::factory()->create(['slug' => 'dieu-hoa-treo-tuong']);
        $canonical = Product::factory()->create(['product_category_id' => $category->id, 'technical_capacity_btu' => 24000, 'btu' => 9999, 'taxonomy_status' => 'VERIFIED', 'marketing_capacity_status' => 'VERIFIED']);
        $legacyOnly = Product::factory()->create(['product_category_id' => $category->id, 'technical_capacity_btu' => null, 'btu' => 24000]);

        $query = app(ProductFilterService::class)->apply(
            Product::query(),
            Request::create('/san-pham', 'GET', ['btu' => ['24000']]),
        );

        $this->assertSame([$canonical->id], $query->pluck('id')->all());
        $this->assertNotContains($legacyOnly->id, $query->pluck('id')->all());
    }

    public function test_unknown_capacity_bucket_is_ignored(): void
    {
        $product = Product::factory()->create(['technical_capacity_btu' => 24000]);

        $query = app(ProductFilterService::class)->apply(
            Product::query(),
            Request::create('/san-pham', 'GET', ['btu' => ['1-2 OR 1=1']]),
        );

        $this->assertSame([$product->id], $query->pluck('id')->all());
    }

    public function test_vrf_is_not_returned_by_rac_btu_bucket(): void
    {
        $category = ProductCategory::factory()->create([
            'slug' => 'dieu-hoa-trung-tam-vrf-gmv',
            'name' => 'A translated display label that does not contain an exclusion token',
        ]);
        $vrf = Product::factory()->create([
            'product_category_id' => $category->id,
            'technical_capacity_btu' => 24000,
            'taxonomy_status' => 'VERIFIED',
            'marketing_capacity_status' => 'VERIFIED',
        ]);

        $query = app(ProductFilterService::class)->apply(
            Product::query(),
            Request::create('/san-pham', 'GET', ['btu' => ['24000']]),
        );

        $this->assertNotContains($vrf->id, $query->pluck('id')->all());
    }

    public function test_only_the_persisted_rated_column_is_queryable(): void
    {
        $category = ProductCategory::factory()->create(['slug' => 'dieu-hoa-treo-tuong']);
        $technicalOnly = Product::factory()->create([
            'product_category_id' => $category->id,
            'marketing_capacity_btu' => null,
            'technical_capacity_btu' => 18000,
            'btu' => null,
            'specs_json' => [['key' => 'capacity_btu', 'value' => '18000']],
        ]);
        $rangeOnly = Product::factory()->create([
            'product_category_id' => $category->id,
            'marketing_capacity_btu' => null,
            'technical_capacity_btu' => null,
            'btu' => null,
            'specs_json' => [['key' => 'capacity_btu', 'value' => '16400 / 17100']],
        ]);
        $legacyCommercial = Product::factory()->create([
            'product_category_id' => $category->id,
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
        $this->assertContains($technicalOnly->id, $ids);
        $this->assertNotContains($rangeOnly->id, $ids);
    }

    public function test_multiple_capacity_buckets_are_or_conditions_and_combine_with_other_filters(): void
    {
        $category = ProductCategory::factory()->create(['slug' => 'dieu-hoa-treo-tuong']);
        $brand = \App\Models\Brand::factory()->create();
        $verified = ['taxonomy_status' => 'VERIFIED', 'marketing_capacity_status' => 'VERIFIED'];
        $eighteen = Product::factory()->create($verified + ['product_category_id' => $category->id, 'brand_id' => $brand->id, 'technical_capacity_btu' => 18000, 'inverter' => true]);
        $fortyEight = Product::factory()->create($verified + ['product_category_id' => $category->id, 'brand_id' => $brand->id, 'technical_capacity_btu' => 48000, 'inverter' => true]);
        $otherBrand = Product::factory()->create($verified + ['product_category_id' => $category->id, 'technical_capacity_btu' => 18000, 'inverter' => true]);
        $otherTier = Product::factory()->create($verified + ['product_category_id' => $category->id, 'brand_id' => $brand->id, 'technical_capacity_btu' => 24000, 'inverter' => true]);

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

    public function test_category_listing_uses_the_same_rated_capacity_contract(): void
    {
        $category = ProductCategory::factory()->create(['slug' => 'dieu-hoa-treo-tuong', 'is_active' => true]);
        $matching = Product::factory()->create([
            'product_category_id' => $category->id,
            'technical_capacity_btu' => 18000,
            'btu' => 9999,
            'is_active' => true,
            'taxonomy_status' => 'VERIFIED',
            'marketing_capacity_status' => 'VERIFIED',
        ]);
        $otherTier = Product::factory()->create([
            'product_category_id' => $category->id,
            'technical_capacity_btu' => 24000,
            'is_active' => true,
            'taxonomy_status' => 'VERIFIED',
            'marketing_capacity_status' => 'VERIFIED',
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

    public function test_public_card_displays_rated_btu_and_rated_kw_when_both_are_available(): void
    {
        $product = Product::factory()->create([
            'marketing_capacity_btu' => 18000,
            'technical_capacity_btu' => 17100,
            'capacity_kw' => 5.2,
            'specs_json' => null,
        ]);

        $html = Blade::render('<x-product-card :product="$product" />', ['product' => $product]);

        $this->assertStringContainsString('17.100 BTU', $html);
        $this->assertStringContainsString('5,2 kW', $html);
        $this->assertStringNotContainsString('18.000 BTU', $html);
    }

    public function test_unverified_numeric_capacity_is_quarantined_until_both_states_are_verified(): void
    {
        $category = ProductCategory::factory()->create(['slug' => 'dieu-hoa-treo-tuong']);
        $unverifiedCapacity = Product::factory()->create([
            'product_category_id' => $category->id,
            'taxonomy_status' => 'VERIFIED',
            'marketing_capacity_btu' => 18000,
            'marketing_capacity_status' => 'UNVERIFIED',
        ]);
        $unresolvedTaxonomy = Product::factory()->create([
            'product_category_id' => $category->id,
            'taxonomy_status' => 'UNRESOLVED',
            'marketing_capacity_btu' => 18000,
            'marketing_capacity_status' => 'VERIFIED',
        ]);
        $verified = Product::factory()->create([
            'product_category_id' => $category->id,
            'taxonomy_status' => 'VERIFIED',
            'marketing_capacity_btu' => 18000,
            'marketing_capacity_status' => 'VERIFIED',
        ]);

        $ids = app(\App\Services\Product\ProductMarketingCapacityEligibility::class)->apply(Product::where('marketing_capacity_btu', 18000))->pluck('id')->all();

        $this->assertSame([$verified->id], $ids);
        $this->assertNotContains($unverifiedCapacity->id, $ids);
        $this->assertNotContains($unresolvedTaxonomy->id, $ids);
    }

    public function test_legacy_wall_category_does_not_override_conflict_states(): void
    {
        $category = ProductCategory::factory()->create(['slug' => 'dieu-hoa-treo-tuong']);
        Product::factory()->create([
            'product_category_id' => $category->id,
            'taxonomy_status' => 'CONFLICT',
            'marketing_capacity_btu' => 24000,
            'marketing_capacity_status' => 'VERIFIED',
        ]);
        Product::factory()->create([
            'product_category_id' => $category->id,
            'taxonomy_status' => 'VERIFIED',
            'marketing_capacity_btu' => 24000,
            'marketing_capacity_status' => 'CONFLICT',
        ]);

        $ids = app(\App\Services\Product\ProductMarketingCapacityEligibility::class)->apply(Product::where('marketing_capacity_btu', 24000))->pluck('id')->all();

        $this->assertSame([], $ids);
    }
}
