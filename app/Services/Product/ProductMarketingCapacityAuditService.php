<?php

namespace App\Services\Product;

use App\Models\CatalogModelField;
use App\Models\Product;

class ProductMarketingCapacityAuditService
{
    private const VERIFIED = ['verified', 'verified_candidate', 'approved'];

    public function __construct(private readonly ProductTechnicalFactResolver $facts) {}

    public function audit(?int $productId = null): array
    {
        $stats = [
            'total' => 0, 'marketing_present' => 0, 'safe_backfill' => 0,
            'legacy_btu_present' => 0, 'technical_only' => 0,
            'specs_capacity_available' => 0, 'ambiguous_range' => 0,
            'no_reliable_capacity_evidence' => 0,
        ];
        $rows = [];

        Product::query()
            ->when($productId, fn ($query) => $query->whereKey($productId))
            ->select(['id', 'sku', 'model_code', 'btu', 'marketing_capacity_btu', 'technical_capacity_btu', 'specs_json', 'catalog_model_id'])
            ->orderBy('id')
            ->each(function (Product $product) use (&$stats, &$rows): void {
                $stats['total']++;
                $row = $this->classify($product);
                $rows[] = $row;

                match ($row['action']) {
                    'KEEP' => $stats['marketing_present']++,
                    'PROPOSE_UPDATE' => $stats['safe_backfill']++,
                    default => null,
                };
                if ($product->marketing_capacity_btu === null && $product->btu !== null) $stats['legacy_btu_present']++;
                if ($product->marketing_capacity_btu === null && $product->technical_capacity_btu !== null) $stats['technical_only']++;
                if ($row['specs_capacity_btu'] !== null) $stats['specs_capacity_available']++;
                if ($row['is_range']) $stats['ambiguous_range']++;
                if ($row['action'] === 'NO_EVIDENCE') $stats['no_reliable_capacity_evidence']++;
            });

        return ['read_only' => true, 'stats' => $stats, 'products' => $rows];
    }

    private function classify(Product $product): array
    {
        $specsCapacity = $this->facts->specs($product)['capacity_btu'] ?? null;
        $base = [
            'product_id' => $product->id,
            'sku' => $product->sku,
            'model' => $product->model_code,
            'current_marketing' => $product->marketing_capacity_btu,
            'proposed_marketing' => null,
            'technical_capacity' => $product->technical_capacity_btu,
            'legacy_btu' => $product->btu,
            'specs_capacity_btu' => $specsCapacity,
            'source' => null, 'source_section' => null, 'evidence' => null,
            'confidence' => null, 'reason' => null, 'action' => null,
            'catalog_field_id' => null,
            'is_range' => is_string($specsCapacity) && str_contains($specsCapacity, '/'),
        ];
        if ($product->marketing_capacity_btu !== null) {
            return array_merge($base, ['action' => 'KEEP', 'reason' => 'CANONICAL_MARKETING_PRESENT']);
        }

        $field = $this->verifiedProductListField($product);
        $value = $field ? $this->strictPositiveInteger($field->normalized_value ?: $field->field_value) : null;
        if ($field && $value !== null) {
            return array_merge($base, [
                'proposed_marketing' => $value, 'source' => 'CATALOG_PRODUCT_LIST',
                'source_section' => $field->source_section, 'evidence' => [
                    'catalog_field_id' => $field->id, 'source_page' => $field->source_page,
                    'source_text' => $field->source_text, 'verification_status' => $field->verification_status,
                ], 'confidence' => $field->confidence_score, 'reason' => 'VERIFIED_PRODUCT_LIST_MARKETING_CAPACITY',
                'action' => 'PROPOSE_UPDATE', 'catalog_field_id' => $field->id,
            ]);
        }
        if ($base['is_range']) return array_merge($base, ['action' => 'AMBIGUOUS', 'reason' => 'TECHNICAL_RANGE_IS_NOT_A_COMMERCIAL_TIER']);
        if ($product->btu !== null) return array_merge($base, ['action' => 'AMBIGUOUS', 'reason' => 'LEGACY_BTU_REQUIRES_PRODUCT_LIST_AUTHORITY']);
        if ($product->technical_capacity_btu !== null || $specsCapacity !== null) return array_merge($base, ['action' => 'AMBIGUOUS', 'reason' => 'TECHNICAL_CAPACITY_IS_NOT_A_COMMERCIAL_TIER']);

        return array_merge($base, ['action' => 'NO_EVIDENCE', 'reason' => 'NO_RELIABLE_COMMERCIAL_CAPACITY_EVIDENCE']);
    }

    private function verifiedProductListField(Product $product): ?CatalogModelField
    {
        if (! $product->catalog_model_id) return null;

        return CatalogModelField::query()
            ->where('catalog_model_id', $product->catalog_model_id)
            ->where('field_key', 'marketing_capacity_btu')
            ->where('source_section', 'PRODUCT_LIST')
            ->whereIn('verification_status', self::VERIFIED)
            ->orderByDesc('verified_at')
            ->orderByDesc('id')
            ->first();
    }

    private function strictPositiveInteger(mixed $value): ?int
    {
        if (is_int($value) && $value > 0) return $value;
        if (is_string($value) && preg_match('/^[1-9]\d*$/D', trim($value))) return (int) trim($value);

        return null;
    }
}
