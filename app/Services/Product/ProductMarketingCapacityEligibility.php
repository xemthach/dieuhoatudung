<?php

namespace App\Services\Product;

use App\Enums\ProductHvacClass;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;

/**
 * Canonical public capacity-filter universe for physical DX Products.
 *
 * Stable ProductCategory slugs own public eligibility. Product display names,
 * merchant product_type text, model codes and technical capacities do not.
 */
final class ProductMarketingCapacityEligibility
{
    public function __construct(
        private readonly ProductDataVerificationService $verification,
    ) {}

    /** @var array<string, ProductHvacClass> */
    public const CATEGORY_CLASSES = [
        'dieu-hoa-treo-tuong' => ProductHvacClass::RAC_WALL_MOUNTED,
        'dieu-hoa-am-tran-cassette' => ProductHvacClass::COMMERCIAL_CASSETTE,
        'dieu-hoa-giau-tran-noi-ong-gio' => ProductHvacClass::COMMERCIAL_DUCTED,
        'dieu-hoa-tu-dung' => ProductHvacClass::COMMERCIAL_FLOOR_STANDING,
        'dieu-hoa-dat-san-ap-tran' => ProductHvacClass::COMMERCIAL_FLOOR_CEILING,
        'dieu-hoa-trung-tam-vrf-gmv' => ProductHvacClass::VRF_OUTDOOR,
    ];

    /** Residential and commercial physical DX classes; VRF is excluded. @var list<ProductHvacClass> */
    private const ELIGIBLE_CLASSES = [
        ProductHvacClass::RAC_WALL_MOUNTED,
        ProductHvacClass::COMMERCIAL_CASSETTE,
        ProductHvacClass::COMMERCIAL_DUCTED,
        ProductHvacClass::COMMERCIAL_FLOOR_STANDING,
        ProductHvacClass::COMMERCIAL_FLOOR_CEILING,
    ];

    public function classForCategorySlug(?string $slug): ProductHvacClass
    {
        return self::CATEGORY_CLASSES[$slug ?? ''] ?? ProductHvacClass::UNKNOWN;
    }

    public function isEligibleClass(ProductHvacClass|string $class): bool
    {
        $resolved = $class instanceof ProductHvacClass ? $class : ProductHvacClass::tryFrom($class);

        return $resolved !== null && in_array($resolved, self::ELIGIBLE_CLASSES, true);
    }

    public function isEligible(Product $product): bool
    {
        $product->loadMissing('category:id,slug');

        return (bool) $product->is_active
            && $this->verification->taxonomyStatus($product) === ProductDataVerificationService::VERIFIED
            && $this->verification->marketingCapacityStatus($product) === ProductDataVerificationService::VERIFIED
            && $product->marketing_capacity_btu !== null
            && $this->isEligibleClass($this->classForCategorySlug($product->category?->slug));
    }

    public function apply(Builder $query): Builder
    {
        $this->verification->applyVerifiedPublicCapacityState($query);

        return $query->where('is_active', true)->whereHas('category', function (Builder $category): void {
            $category->whereIn('slug', $this->eligibleCategorySlugs());
        });
    }

    /** @return list<string> */
    public function eligibleCategorySlugs(): array
    {
        return array_keys(array_filter(
            self::CATEGORY_CLASSES,
            fn (ProductHvacClass $class): bool => $this->isEligibleClass($class),
        ));
    }

    /** @return array<string, array{class: string, eligible: bool}> */
    public function matrix(): array
    {
        $matrix = [];
        foreach (self::CATEGORY_CLASSES as $slug => $class) {
            $matrix[$slug] = ['class' => $class->value, 'eligible' => $this->isEligibleClass($class)];
        }

        return $matrix;
    }
}
