<?php

namespace App\Services\Product;

use App\Models\Product;

final class ProductDataApprovalCenterService
{
    public function __construct(
        private readonly ProductTaxonomyAuditService $taxonomy,
        private readonly ProductDataVerificationService $verification,
        private readonly ProductDataApprovalService $approvals,
    ) {}

    public function taxonomyReview(): array
    {
        $audit = $this->taxonomy->audit();

        return [
            'ledger_digest' => $audit['ledger_digest'],
            'approved' => $this->approvals->taxonomyLedgerIsApproved($audit['ledger_digest']),
            'stats' => $audit['stats'],
            'rows' => $audit['rows'],
        ];
    }

    public function marketingReview(): array
    {
        $taxonomy = $this->taxonomy->audit();
        $taxonomyRows = collect($taxonomy['rows'])->keyBy('product_id');
        // Review only Products that can participate in the current public
        // catalogue. Inactive operational probes remain in the taxonomy audit
        // but cannot inflate the public capacity rollout denominator.
        $products = Product::query()->where('is_active', true)->with('brand:id,slug,name')->orderBy('id')->get();
        $rows = $products->map(function (Product $product) use ($taxonomyRows): array {
            $tax = $taxonomyRows->get($product->id);
            $taxonomyResolved = in_array($tax['classification'], ['KEEP', 'PROPOSE_UPDATE'], true);
            $eligible = $taxonomyResolved && (bool) $tax['filter_eligible_after_proposal'];

            return [
                'product_id' => $product->id,
                'brand' => $product->brand?->slug,
                'sku' => $product->sku,
                'model_code' => $product->model_code,
                'taxonomy' => $tax['canonical_taxonomy'],
                'taxonomy_resolved' => $taxonomyResolved,
                'filter_eligible' => $eligible,
                'technical_capacity_btu' => $product->technical_capacity_btu,
                'capacity_kw' => $product->capacity_kw,
                'current_marketing_capacity_btu' => $product->marketing_capacity_btu,
                'marketing_capacity_status' => $this->verification->marketingCapacityStatus($product),
                'source' => $tax['evidence_source'],
                'source_location' => $tax['evidence_location'],
                'review_state' => $eligible ? 'ADMIN_MAPPING_REQUIRED' : 'NOT_IN_REVIEW_SCOPE',
            ];
        })->values();

        $eligible = $rows->where('filter_eligible', true);
        $groups = $eligible->groupBy(function (array $row): string {
            return hash('sha256', json_encode([
                $row['brand'], $row['source'], $row['taxonomy'],
                $row['technical_capacity_btu'], $row['capacity_kw'],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        })->map(function ($members, string $groupKey) use ($taxonomy): array {
            $first = $members->first();
            $ids = $members->pluck('product_id')->map(fn ($id): int => (int) $id)->sort()->values()->all();
            $sourceDigest = hash('sha256', json_encode([
                'source' => $first['source'],
                'locations' => $members->pluck('source_location')->unique()->sort()->values()->all(),
                'product_ids' => $ids,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return [
                'group_key' => $groupKey,
                'ledger_digest' => $taxonomy['ledger_digest'],
                'source_digest' => $sourceDigest,
                'brand' => $first['brand'],
                'source' => $first['source'],
                'taxonomy' => $first['taxonomy'],
                'technical_capacity_btu' => $first['technical_capacity_btu'],
                'capacity_kw' => $first['capacity_kw'],
                'product_ids' => $ids,
                'products' => $members->map(fn (array $row): array => [
                    'id' => $row['product_id'], 'sku' => $row['sku'], 'model_code' => $row['model_code'],
                    'current_marketing_capacity_btu' => $row['current_marketing_capacity_btu'],
                    'status' => $row['marketing_capacity_status'],
                ])->values()->all(),
                'affected_count' => count($ids),
                'suggested_marketing_capacity_btu' => null,
            ];
        })->values();

        $approved = $this->approvals->approvedMarketingMappings();

        return [
            'ledger_digest' => $taxonomy['ledger_digest'],
            'stats' => [
                'products_to_review' => $eligible->count(),
                'review_groups' => $groups->count(),
                'source_explicit_tier_products' => 0,
                'admin_mapping_required_products' => $eligible->count(),
                'unresolvable_with_current_source' => 0,
                'unverified_current_values' => $rows->where('marketing_capacity_status', ProductDataVerificationService::UNVERIFIED)->count(),
                'approved_product_mappings' => count($approved),
            ],
            'groups' => $groups->all(),
            'rows' => $rows->all(),
        ];
    }
}
