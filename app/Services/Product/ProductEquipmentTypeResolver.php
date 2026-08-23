<?php

namespace App\Services\Product;

use App\Enums\EquipmentType;
use App\Enums\ProductHvacClass;
use App\Models\Product;

final class ProductEquipmentTypeResolver
{
    public function __construct(
        private readonly ProductHvacClassResolver $classes,
    ) {}

    /**
     * Resolve only from the canonical HVAC class. Product-name tokens are used
     * solely as a fail-closed conflict check; they never promote UNKNOWN data.
     *
     * @return array{type: EquipmentType|null, verified: bool, source: string, reason: string}
     */
    public function resolve(Product $product): array
    {
        $classification = $this->classes->resolve($product);
        $type = match ($classification['class']) {
            ProductHvacClass::RAC_SPLIT => EquipmentType::WALL_MOUNTED,
            ProductHvacClass::RAC_CASSETTE => EquipmentType::CASSETTE,
            ProductHvacClass::RAC_DUCTED => EquipmentType::DUCTED,
            ProductHvacClass::RAC_FLOOR_CEILING => EquipmentType::CEILING_EXPOSED,
            ProductHvacClass::RAC_FLOOR_STANDING => EquipmentType::FLOOR_STANDING,
            default => null,
        };

        if (! $classification['verified'] || $type === null) {
            return [
                'type' => null,
                'verified' => false,
                'source' => $classification['source'],
                'reason' => $type === null && $classification['verified']
                    ? 'UNSUPPORTED_RECOMMENDATION_CLASS:'.$classification['class']->value
                    : $classification['reason'],
            ];
        }

        $labelType = $this->strongLabelType((string) $product->name);
        if ($labelType !== null && $labelType !== $type) {
            return [
                'type' => null,
                'verified' => false,
                'source' => $classification['source'],
                'reason' => 'CONFLICTING_PRODUCT_LABEL_AND_TAXONOMY',
            ];
        }

        return [
            'type' => $type,
            'verified' => true,
            'source' => $classification['source'],
            'reason' => 'VERIFIED_HVAC_CLASS_MAPPING',
        ];
    }

    private function strongLabelType(string $name): ?EquipmentType
    {
        $name = mb_strtolower($name);

        return match (true) {
            str_contains($name, 'cassette') => EquipmentType::CASSETTE,
            str_contains($name, 'ống gió'), str_contains($name, 'duct') => EquipmentType::DUCTED,
            str_contains($name, 'áp trần'), str_contains($name, 'đặt sàn') => EquipmentType::CEILING_EXPOSED,
            str_contains($name, 'tủ đứng') => EquipmentType::FLOOR_STANDING,
            str_contains($name, 'treo tường') => EquipmentType::WALL_MOUNTED,
            default => null,
        };
    }
}
