<?php

namespace App\Services\Product;

use App\Models\CatalogModelField;
use App\Models\Product;
use App\Services\HVAC\HVACTechnicalNormalizer;

class ProductMarketingCapacityAuditService
{
    private const VERIFIED = ['verified', 'verified_candidate', 'approved'];

    public function __construct(
        private readonly ProductTechnicalFactResolver $facts,
        private readonly HVACTechnicalNormalizer $normalizer,
        private readonly ProductDataVerificationService $verification,
        private readonly ProductDataApprovalService $approvals,
        private readonly ProductDataApprovalCenterService $approvalCenter,
    ) {}

    public function audit(?int $productId = null): array
    {
        $stats = [
            'total' => 0, 'marketing_present' => 0, 'safe_backfill' => 0,
            'legacy_btu_present' => 0, 'technical_only' => 0,
            'specs_capacity_available' => 0, 'ambiguous_range' => 0,
            'no_reliable_capacity_evidence' => 0,
            'conflict' => 0,
        ];
        $rows = [];
        $currentGroups = collect();
        foreach ($this->approvalCenter->marketingReview()['groups'] as $group) {
            foreach ($group['product_ids'] as $id) {
                $currentGroups->put((int) $id, $group);
            }
        }

        Product::query()
            ->when($productId, fn ($query) => $query->whereKey($productId))
            ->select([
                'id', 'brand_id', 'sku', 'model_code', 'btu', 'marketing_capacity_btu',
                'marketing_capacity_status', 'marketing_capacity_mapping_digest',
                'technical_capacity_btu', 'specs_json', 'catalog_model_id',
            ])
            ->orderBy('id')
            ->each(function (Product $product) use (&$stats, &$rows, $currentGroups): void {
                $stats['total']++;
                $row = $this->classify($product, $currentGroups->get($product->id));
                $rows[] = $row;

                if ($product->marketing_capacity_btu !== null) $stats['marketing_present']++;
                if ($row['action'] === 'PROPOSE_UPDATE') $stats['safe_backfill']++;
                if ($product->marketing_capacity_btu === null && $product->btu !== null) $stats['legacy_btu_present']++;
                if ($product->marketing_capacity_btu === null && $product->technical_capacity_btu !== null) $stats['technical_only']++;
                if ($row['specs_capacity_btu'] !== null) $stats['specs_capacity_available']++;
                if ($row['is_range']) $stats['ambiguous_range']++;
                if ($row['action'] === 'NO_EVIDENCE') $stats['no_reliable_capacity_evidence']++;
                if ($row['action'] === 'CONFLICT') $stats['conflict']++;
            });

        return ['read_only' => true, 'stats' => $stats, 'products' => $rows];
    }

    private function classify(Product $product, ?array $currentGroup): array
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
            'mapping_digest' => null,
            'approval_event_id' => null,
            'current_marketing_status' => $this->verification->marketingCapacityStatus($product),
            'is_range' => is_string($specsCapacity) && str_contains($specsCapacity, '/'),
        ];
        $mapping = $this->approvals->approvedMarketingMappings()[$product->id] ?? null;
        if (($mapping['conflict'] ?? false) === true) {
            return array_merge($base, ['action' => 'CONFLICT', 'reason' => 'MULTIPLE_ACTIVE_APPROVED_MARKETING_MAPPINGS']);
        }
        if ($mapping) {
            if (! $currentGroup
                || ! hash_equals((string) $mapping['ledger_digest'], (string) $currentGroup['ledger_digest'])
                || ! hash_equals((string) $mapping['source_digest'], (string) $currentGroup['source_digest'])
                || ! hash_equals((string) $mapping['group_key'], (string) $currentGroup['group_key'])) {
                return array_merge($base, [
                    'action' => 'CONFLICT',
                    'reason' => 'STALE_APPROVED_MARKETING_MAPPING_SOURCE_OR_GROUP_CHANGED',
                    'mapping_digest' => $mapping['mapping_digest'],
                    'approval_event_id' => $mapping['event_id'],
                ]);
            }
            $proposed = (int) $mapping['capacity'];
            if ($product->marketing_capacity_btu !== null && (int) $product->marketing_capacity_btu !== $proposed) {
                return array_merge($base, [
                    'action' => 'CONFLICT', 'reason' => 'CURRENT_VALUE_DIFFERS_FROM_APPROVED_MAPPING',
                    'proposed_marketing' => $proposed, 'mapping_digest' => $mapping['mapping_digest'],
                    'approval_event_id' => $mapping['event_id'],
                ]);
            }
            $alreadyVerified = $this->verification->marketingCapacityStatus($product) === ProductDataVerificationService::VERIFIED
                && $product->marketing_capacity_mapping_digest === $mapping['mapping_digest'];

            return array_merge($base, [
                'action' => $alreadyVerified ? 'KEEP' : 'PROPOSE_UPDATE',
                'reason' => $alreadyVerified ? 'APPROVED_MAPPING_ALREADY_APPLIED' : 'ADMIN_APPROVED_SOURCE_BACKED_MAPPING',
                'proposed_marketing' => $proposed,
                'source' => 'APPROVED_COMMERCIAL_TIER_MAPPING',
                'source_section' => 'PRODUCT_LIST',
                'evidence' => ['approval_event_id' => $mapping['event_id'], 'source_digest' => $mapping['source_digest']],
                'mapping_digest' => $mapping['mapping_digest'],
                'approval_event_id' => $mapping['event_id'],
            ]);
        }
        $evidence = $this->verifiedProductListEvidence($product);
        if ($evidence['status'] === 'CONFLICT') {
            return array_merge($base, [
                'action' => 'CONFLICT',
                'reason' => $evidence['reason'],
                'evidence' => $evidence['items'],
            ]);
        }
        if ($product->marketing_capacity_btu !== null) {
            if ($evidence['value'] === null) {
                return array_merge($base, [
                    'action' => 'CONFLICT',
                    'reason' => 'CURRENT_MARKETING_VALUE_UNVERIFIED',
                ]);
            }
            if ($evidence['value'] !== null && (int) $product->marketing_capacity_btu !== $evidence['value']) {
                return array_merge($base, [
                    'action' => 'CONFLICT',
                    'reason' => 'CANONICAL_VALUE_DIFFERS_FROM_VERIFIED_PRODUCT_LIST',
                    'proposed_marketing' => $evidence['value'],
                    'evidence' => $evidence['items'],
                ]);
            }
            $field = $evidence['field'];
            $alreadyVerified = $this->verification->marketingCapacityStatus($product) === ProductDataVerificationService::VERIFIED;
            return array_merge($base, [
                'action' => $alreadyVerified ? 'KEEP' : 'PROPOSE_UPDATE',
                'reason' => $alreadyVerified ? 'CANONICAL_MARKETING_MATCHES_VERIFIED_PRODUCT_LIST' : 'VERIFIED_PRODUCT_LIST_REQUIRES_STATE_APPLY',
                'proposed_marketing' => (int) $product->marketing_capacity_btu,
                'source' => 'CATALOG_PRODUCT_LIST',
                'source_section' => $field?->source_section,
                'catalog_field_id' => $field?->id,
                'evidence' => $evidence['items'],
            ]);
        }

        $field = $evidence['field'];
        $value = $evidence['value'];
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

    /**
     * Resolve verified PRODUCT_LIST evidence without trusting a database ID or
     * selecting an arbitrary first row. A Product may retain technical catalog
     * lineage while its commercial Product List evidence lives in another
     * CatalogModel, so matching is performed by exact normalized SKU/model.
     *
     * @return array{status:string,field:?CatalogModelField,value:?int,reason:?string,items:array<int,array<string,mixed>>}
     */
    private function verifiedProductListEvidence(Product $product): array
    {
        $normalizedSku = $this->normalizer->normalizeSku($product->sku);
        $normalizedModel = $this->normalizer->normalizeModel($product->model_code);
        if ($normalizedSku === '' && $normalizedModel === '') {
            return ['status' => 'NONE', 'field' => null, 'value' => null, 'reason' => null, 'items' => []];
        }

        $fields = CatalogModelField::query()
            ->with('catalogModel.source')
            ->where('field_key', 'marketing_capacity_btu')
            ->where('source_section', 'PRODUCT_LIST')
            ->whereIn('verification_status', self::VERIFIED)
            ->whereHas('catalogModel', function ($query) use ($product, $normalizedSku, $normalizedModel): void {
                $query->where(function ($identity) use ($product, $normalizedSku, $normalizedModel): void {
                    if ($product->catalog_model_id) $identity->orWhere('id', $product->catalog_model_id);
                    if ($normalizedSku !== '') $identity->orWhere('normalized_sku', $normalizedSku);
                    if ($normalizedModel !== '') $identity->orWhere('normalized_model', $normalizedModel);
                });
            })
            ->orderBy('id')
            ->get()
            ->filter(function (CatalogModelField $field) use ($product): bool {
                $sourceBrandId = $field->catalogModel?->source?->brand_id;

                // Product List evidence is commercial Brand-scoped evidence.
                // Missing Brand lineage is not portable authority and must
                // fail closed instead of matching a cross-brand model name.
                return $sourceBrandId !== null
                    && (int) $sourceBrandId === (int) $product->brand_id;
            });

        $items = $fields->map(fn (CatalogModelField $field): array => [
            'catalog_field_id' => $field->id,
            'catalog_model_id' => $field->catalog_model_id,
            'value' => $this->strictPositiveInteger($field->normalized_value ?: $field->field_value),
            'source_page' => $field->source_page,
            'source_text' => $field->source_text,
            'verification_status' => $field->verification_status,
        ])->values()->all();
        $valid = $fields->filter(fn (CatalogModelField $field): bool =>
            $this->strictPositiveInteger($field->normalized_value ?: $field->field_value) !== null
        );
        $values = $valid->map(fn (CatalogModelField $field): int =>
            $this->strictPositiveInteger($field->normalized_value ?: $field->field_value)
        )->unique()->values();

        if ($values->count() > 1) {
            return ['status' => 'CONFLICT', 'field' => null, 'value' => null,
                'reason' => 'CONFLICTING_VERIFIED_PRODUCT_LIST_VALUES', 'items' => $items];
        }
        if ($valid->count() > 1) {
            return ['status' => 'CONFLICT', 'field' => null, 'value' => null,
                'reason' => 'DUPLICATE_VERIFIED_PRODUCT_LIST_EVIDENCE', 'items' => $items];
        }
        $field = $valid->first();

        return [
            'status' => $field ? 'UNIQUE' : 'NONE',
            'field' => $field,
            'value' => $field ? $this->strictPositiveInteger($field->normalized_value ?: $field->field_value) : null,
            'reason' => null,
            'items' => $items,
        ];
    }

    private function strictPositiveInteger(mixed $value): ?int
    {
        if (is_int($value) && $value > 0) return $value;
        if (is_string($value) && preg_match('/^[1-9]\d*$/D', trim($value))) return (int) trim($value);

        return null;
    }
}
