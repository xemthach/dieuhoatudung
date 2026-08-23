<?php

namespace App\Services\Product;

use App\Enums\ProductHvacClass;
use App\Models\Product;

final class ProductHvacClassResolver
{
    public function resolve(Product $product): array
    {
        $sources = array_filter([
            'PRODUCT_CATEGORY' => (string) ($product->category?->name ?? ''),
            'PRODUCT_TYPE' => (string) ($product->product_type ?? ''),
        ], static fn (string $value): bool => trim($value) !== '');
        $model = mb_strtoupper((string) $product->model_code);

        if ($sources === []) {
            return ['class' => ProductHvacClass::UNKNOWN, 'source' => 'MISSING_CLASSIFICATION_SOURCE', 'verified' => false, 'reason' => 'fail_closed'];
        }

        $resolved = [];
        foreach ($sources as $source => $value) {
            $classification = $this->classify($value);
            if ($classification !== null) {
                $resolved[$source] = $classification;
            }
        }

        if ($resolved === []) {
            return ['class' => ProductHvacClass::UNKNOWN, 'source' => 'UNSUPPORTED_CATEGORY:'.($model !== '' ? 'MODEL_PRESENT' : 'MODEL_MISSING'), 'verified' => false, 'reason' => 'fail_closed'];
        }

        if (count(array_unique(array_map(static fn (ProductHvacClass $class): string => $class->value, $resolved))) > 1) {
            return ['class' => ProductHvacClass::UNKNOWN, 'source' => implode('+', array_keys($resolved)), 'verified' => false, 'reason' => 'CONFLICTING_CLASSIFICATION_SOURCES'];
        }

        $source = array_key_first($resolved);
        $class = $resolved[$source];

        return ['class' => $class, 'source' => $source, 'verified' => true, 'reason' => 'verified_taxonomy_mapping'];
    }

    private function classify(string $value): ?ProductHvacClass
    {
        $value = mb_strtolower($value);

        if (str_contains($value, 'vrf') || str_contains($value, 'gmv')) {
            return ProductHvacClass::VRF_OUTDOOR;
        }
        if (str_contains($value, 'treo tường') || str_contains($value, 'wall mounted') || str_contains($value, 'wall-mounted')) {
            return ProductHvacClass::RAC_SPLIT;
        }
        if (str_contains($value, 'cassette')) {
            return ProductHvacClass::RAC_CASSETTE;
        }
        if (str_contains($value, 'ống gió') || str_contains($value, 'giấu trần') || str_contains($value, 'ducted')) {
            return ProductHvacClass::RAC_DUCTED;
        }
        if (str_contains($value, 'đặt sàn') || str_contains($value, 'áp trần') || str_contains($value, 'ceiling exposed')) {
            return ProductHvacClass::RAC_FLOOR_CEILING;
        }
        if (str_contains($value, 'tủ đứng') || str_contains($value, 'floor standing')) {
            return ProductHvacClass::RAC_FLOOR_STANDING;
        }

        return null;
    }
}
