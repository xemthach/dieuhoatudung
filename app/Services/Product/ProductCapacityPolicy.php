<?php

namespace App\Services\Product;

use App\Enums\ProductHvacClass;
use App\Models\Product;

final class ProductCapacityPolicy
{
    public const OUTDOOR_UNIT_FACTS = 'OUTDOOR_UNIT_FACTS';
    public const INDOOR_UNIT_FACTS = 'INDOOR_UNIT_FACTS';
    public const SYSTEM_COMBINATION_FACTS = 'SYSTEM_COMBINATION_FACTS';

    public function __construct(
        private readonly ProductHvacClassResolver $classes,
        private readonly ProductTechnicalFactResolver $facts,
    ) {}

    public function evaluate(Product $product, array $capacityFacts = [], array $authority = [], string $claimScope = self::OUTDOOR_UNIT_FACTS): array
    {
        $classification = $this->classes->resolve($product);
        $class = $classification['class'];
        if (! $classification['verified'] || $class === ProductHvacClass::UNKNOWN) {
            return $this->blocked($classification, 'UNKNOWN_OR_UNVERIFIED_PRODUCT_CLASS');
        }

        $technical = $capacityFacts['technical'] ?? $this->legacyTechnical($product);
        $marketing = $capacityFacts['marketing'] ?? $this->legacyMarketing($product);
        $hasTechnical = is_array($technical) && ($technical['verified'] ?? false) && ($technical['source_native'] ?? false);
        $hasMarketing = is_array($marketing) && ($marketing['verified'] ?? false);
        $hasPair = $hasTechnical && $hasMarketing;
        $combination = (bool) ($authority['combination_verified'] ?? false);
        $transport = (bool) ($authority['transport_verified'] ?? false);
        $regional = (bool) ($authority['regional_verified'] ?? false);

        if (in_array($class, [ProductHvacClass::VRF_OUTDOOR, ProductHvacClass::VRF_INDOOR], true)) {
            if (! $hasTechnical) return $this->blocked($classification, 'SOURCE_NATIVE_TECHNICAL_CAPACITY_REQUIRED');
            if ($claimScope === self::SYSTEM_COMBINATION_FACTS && ! $combination) return $this->blocked($classification, 'COMBINATION_LINEAGE_REQUIRED');
            if (! $transport) return $this->blocked($classification, 'TRANSPORT_AUTHORITY_REQUIRED');
            if (! $regional) return $this->blocked($classification, 'REGIONAL_AUTHORITY_REQUIRED');
            return $this->allowed($classification, $technical, $claimScope, $hasMarketing, $hasPair, $combination);
        }
        if ($class === ProductHvacClass::VRF_SYSTEM && ! $combination) return $this->blocked($classification, 'COMBINATION_LINEAGE_REQUIRED');
        if (! $hasPair) return $this->blocked($classification, 'RAC_MARKETING_TECHNICAL_PAIR_REQUIRED');
        if (! $transport) return $this->blocked($classification, 'TRANSPORT_AUTHORITY_REQUIRED');
        if (! $regional) return $this->blocked($classification, 'REGIONAL_AUTHORITY_REQUIRED');
        return $this->allowed($classification, $technical, $claimScope, $hasMarketing, $hasPair, $combination);
    }

    private function legacyTechnical(Product $product): ?array
    {
        $value = $this->facts->getVerified($product, 'technical_capacity_btu');
        return $value ? ['verified' => true, 'source_native' => true, 'role' => 'RATED', 'value' => $value['value'], 'unit' => 'BTU_PER_HOUR'] : null;
    }

    private function legacyMarketing(Product $product): ?array
    {
        $value = $this->facts->get($product, 'marketing_capacity_btu');
        return $value ? ['verified' => false, 'source_native' => true, 'role' => 'MARKETING', 'value' => $value['value'], 'unit' => 'BTU_PER_HOUR'] : null;
    }

    private function blocked(array $classification, string $reason): array
    {
        return ['eligible' => false, 'class' => $classification['class']->value, 'classification' => $classification, 'reason' => $reason];
    }

    private function allowed(array $classification, ?array $technical, string $claimScope, bool $hasMarketing, bool $hasPair, bool $combination): array
    {
        return ['eligible' => true, 'class' => $classification['class']->value, 'classification' => $classification, 'reason' => 'CAPACITY_AND_AUTHORITY_CONTRACT_SATISFIED', 'technical' => $technical, 'marketing_present' => $hasMarketing, 'pair_present' => $hasPair, 'combination_verified' => $combination, 'allowed_claim_scope' => $claimScope];
    }
}
