<?php

namespace Tests\Unit;

use App\Enums\ProductHvacClass;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\Product\ProductMarketingCapacityEligibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProductMarketingCapacityEligibilityTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('categoryMatrix')]
    public function test_category_slug_is_the_single_public_filter_eligibility_source(
        string $slug,
        ProductHvacClass $class,
        bool $eligible,
    ): void {
        $service = app(ProductMarketingCapacityEligibility::class);

        $this->assertSame($class, $service->classForCategorySlug($slug));
        $this->assertSame($eligible, $service->isEligibleClass($class));
    }

    public function test_unknown_category_fails_closed(): void
    {
        $service = app(ProductMarketingCapacityEligibility::class);

        $this->assertSame(ProductHvacClass::UNKNOWN, $service->classForCategorySlug('translated-display-label'));
        $this->assertFalse($service->isEligibleClass(ProductHvacClass::UNKNOWN));
    }

    public function test_inactive_product_is_not_eligible_even_when_all_capacity_states_are_verified(): void
    {
        $category = ProductCategory::factory()->create(['slug' => 'dieu-hoa-treo-tuong']);
        $product = Product::factory()->create([
            'product_category_id' => $category->id,
            'is_active' => false,
            'marketing_capacity_btu' => 18000,
            'taxonomy_status' => 'VERIFIED',
            'marketing_capacity_status' => 'VERIFIED',
        ]);

        $this->assertFalse(app(ProductMarketingCapacityEligibility::class)->isEligible($product));
    }

    public static function categoryMatrix(): array
    {
        return [
            'wall mounted' => ['dieu-hoa-treo-tuong', ProductHvacClass::RAC_WALL_MOUNTED, true],
            'cassette' => ['dieu-hoa-am-tran-cassette', ProductHvacClass::COMMERCIAL_CASSETTE, true],
            'ducted' => ['dieu-hoa-giau-tran-noi-ong-gio', ProductHvacClass::COMMERCIAL_DUCTED, true],
            'floor standing' => ['dieu-hoa-tu-dung', ProductHvacClass::COMMERCIAL_FLOOR_STANDING, true],
            'floor ceiling' => ['dieu-hoa-dat-san-ap-tran', ProductHvacClass::COMMERCIAL_FLOOR_CEILING, true],
            'vrf gmv' => ['dieu-hoa-trung-tam-vrf-gmv', ProductHvacClass::VRF_OUTDOOR, false],
        ];
    }
}
