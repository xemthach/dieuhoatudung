<?php

namespace App\Services\Product;

use App\Enums\ProductHvacClass;
use App\Models\Product;

final class ProductHvacClassResolver
{
    public function resolve(Product $product): array
    {
        $category = mb_strtolower((string) ($product->category?->name ?? $product->product_type ?? ''));
        $model = mb_strtoupper((string) $product->model_code);

        if ($category === '') {
            return ['class' => ProductHvacClass::UNKNOWN, 'source' => 'MISSING_CLASSIFICATION_SOURCE', 'verified' => false, 'reason' => 'fail_closed'];
        }

        if (str_contains($category, 'vrf') || str_contains($category, 'gmv')) {
            return ['class' => ProductHvacClass::VRF_OUTDOOR, 'source' => 'PRODUCT_CATEGORY_OR_PRODUCT_TYPE', 'verified' => true, 'reason' => 'vrf_category'];
        }
        if (str_contains($category, 'cassette')) {
            return ['class' => ProductHvacClass::RAC_CASSETTE, 'source' => 'PRODUCT_CATEGORY_OR_PRODUCT_TYPE', 'verified' => true, 'reason' => 'cassette_category'];
        }
        if (str_contains($category, 'ống gió') || str_contains($category, 'giấu trần')) {
            return ['class' => ProductHvacClass::RAC_DUCTED, 'source' => 'PRODUCT_CATEGORY_OR_PRODUCT_TYPE', 'verified' => true, 'reason' => 'ducted_category'];
        }
        if (str_contains($category, 'đặt sàn') || str_contains($category, 'áp trần')) {
            return ['class' => ProductHvacClass::RAC_FLOOR_CEILING, 'source' => 'PRODUCT_CATEGORY_OR_PRODUCT_TYPE', 'verified' => true, 'reason' => 'floor_ceiling_category'];
        }
        if (str_contains($category, 'tủ đứng')) {
            return ['class' => ProductHvacClass::RAC_FLOOR_STANDING, 'source' => 'PRODUCT_CATEGORY_OR_PRODUCT_TYPE', 'verified' => true, 'reason' => 'standing_category'];
        }

        return ['class' => ProductHvacClass::UNKNOWN, 'source' => 'UNSUPPORTED_CATEGORY:'.($model !== '' ? 'MODEL_PRESENT' : 'MODEL_MISSING'), 'verified' => false, 'reason' => 'fail_closed'];
    }
}
