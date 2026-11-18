<?php

namespace App\Services\Product;

use App\Models\Product;
use Illuminate\Http\Request;

final class MarketingCapacityFinalGateService
{
    public function __construct(
        private readonly ProductTaxonomyAuditService $taxonomy,
        private readonly ProductMarketingCapacityAuditService $capacity,
        private readonly ProductFilterService $filters,
    ) {}

    public function audit(): array
    {
        $taxonomy = $this->taxonomy->audit();
        $taxonomyRows = collect($taxonomy['rows'])->keyBy('product_id');
        $capacityAudit = $this->capacity->audit();
        $capacityRows = collect($capacityAudit['products'])->keyBy('product_id');

        $rows = Product::query()->with('brand:id,slug')->orderBy('id')->get()
            ->map(function (Product $product) use ($taxonomyRows, $capacityRows): array {
                $taxonomyRow = $taxonomyRows->get($product->id);
                $capacityRow = $capacityRows->get($product->id);
                $taxonomyResolved = in_array($taxonomyRow['classification'], ['KEEP', 'PROPOSE_UPDATE'], true);
                $eligible = $taxonomyResolved && (bool) $taxonomyRow['filter_eligible_after_proposal'];
                $eligibilityStatus = $taxonomyResolved
                    ? ($eligible ? 'ELIGIBLE' : 'NOT_ELIGIBLE')
                    : 'UNRESOLVED_FAIL_CLOSED';

                if ($capacityRow['action'] === 'CONFLICT') {
                    $status = 'CONFLICT';
                } elseif (! $eligible) {
                    $status = 'NOT_ELIGIBLE';
                } elseif ($capacityRow['action'] === 'KEEP') {
                    $status = 'KEEP';
                } elseif ($capacityRow['action'] === 'PROPOSE_UPDATE') {
                    $status = 'PROPOSE_UPDATE';
                } else {
                    // Technical/legacy/range facts do not make commercial
                    // evidence ambiguous; accepted commercial evidence is
                    // simply absent.
                    $status = 'NO_EVIDENCE';
                }

                return [
                    'product_id' => $product->id,
                    'sku' => $product->sku,
                    'model_code' => $product->model_code,
                    'brand' => $product->brand?->slug,
                    'taxonomy' => $taxonomyRow['canonical_taxonomy'],
                    'taxonomy_classification' => $taxonomyRow['classification'],
                    'filter_eligibility' => $eligibilityStatus,
                    'current_marketing_capacity_btu' => $product->marketing_capacity_btu,
                    'proposed_marketing_capacity_btu' => $capacityRow['proposed_marketing'],
                    'evidence_source' => $capacityRow['source'],
                    'source_sha256' => null,
                    'source_page' => data_get($capacityRow, 'evidence.source_page'),
                    'source_row' => null,
                    'source_column' => null,
                    'evidence_value' => $capacityRow['proposed_marketing'] ?? $product->marketing_capacity_btu,
                    'evidence_type' => $capacityRow['source_section'],
                    'status' => $status,
                    'reason' => $capacityRow['reason'],
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                ];
            })->values()->all();

        $counts = collect($rows)->countBy('status');
        $eligibleRows = collect($rows)->where('filter_eligibility', 'ELIGIBLE');
        $knownNotEligible = collect($rows)->where('filter_eligibility', 'NOT_ELIGIBLE')->count();
        $unresolved = collect($rows)->where('filter_eligibility', 'UNRESOLVED_FAIL_CLOSED')->count();
        $sourceCoverage = json_decode(file_get_contents(config('catalog_evidence.coverage_path')), true, 512, JSON_THROW_ON_ERROR);

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'read_only' => true,
            'product_mutation' => 'NONE',
            'accepted_commercial_evidence' => [
                'VERIFIED_PRODUCT_LIST_EXPLICIT_MARKETING_TIER',
                'APPROVED_SOURCE_BACKED_MANUFACTURER_COMMERCIAL_MAPPING',
                'VERIFIED_PRODUCT_TRANSFER_MARKETING_CAPACITY',
            ],
            'rejected_inference_inputs' => [
                'technical_capacity_btu', 'capacity_kw', 'hp', 'legacy_btu',
                'model_suffix', 'product_title', 'nearest_standard_tier',
            ],
            'stats' => [
                'total_products' => count($rows),
                'filter_eligible' => $eligibleRows->count(),
                'filter_not_eligible_known' => $knownNotEligible,
                'filter_eligibility_unresolved' => $unresolved,
                'eligible_with_verified_marketing_capacity' => $eligibleRows->where('status', 'KEEP')->count(),
                'eligible_missing_capacity' => $eligibleRows->whereIn('status', ['NO_EVIDENCE', 'AMBIGUOUS', 'CONFLICT'])->count(),
                'commercial_tier_proven' => collect($rows)->whereIn('status', ['KEEP', 'PROPOSE_UPDATE'])->count(),
                'keep' => (int) $counts->get('KEEP', 0),
                'propose_update' => (int) $counts->get('PROPOSE_UPDATE', 0),
                'no_evidence' => (int) $counts->get('NO_EVIDENCE', 0),
                'ambiguous' => (int) $counts->get('AMBIGUOUS', 0),
                'conflict' => (int) $counts->get('CONFLICT', 0),
                'not_eligible' => (int) $counts->get('NOT_ELIGIBLE', 0),
                'existing_marketing_values' => collect($rows)->whereNotNull('current_marketing_capacity_btu')->count(),
                'existing_marketing_values_verified' => collect($rows)->whereNotNull('current_marketing_capacity_btu')->where('status', 'KEEP')->count(),
            ],
            'source_identity' => [
                'counts' => $sourceCoverage['counts'],
                'multiple_source_evidence' => collect($sourceCoverage['rows'])->where('multiple_source_evidence', true)->count(),
            ],
            'taxonomy_ledger_digest' => $taxonomy['ledger_digest'],
            'current_filter_self_tests' => $this->filterSelfTests(),
            'rows' => $rows,
        ];
        $payload['ledger_digest'] = hash('sha256', json_encode([
            'taxonomy_digest' => $taxonomy['ledger_digest'],
            'source_digests' => collect($sourceCoverage['sources'])->pluck('sha256')->sort()->values()->all(),
            'rows' => $rows,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return $payload;
    }

    private function filterSelfTests(): array
    {
        $cases = [
            '9000-12000' => ['btu' => ['9000-12000']],
            '18000' => ['btu' => ['18000']],
            '24000' => ['btu' => ['24000']],
            '28000' => ['btu' => ['28000']],
            '36000' => ['btu' => ['36000']],
            '42000' => ['btu' => ['42000']],
            '48000' => ['btu' => ['48000']],
            '18000+48000' => ['btu' => ['18000', '48000']],
            'gree+18000' => ['brand' => ['gree'], 'btu' => ['18000']],
            'inverter+18000' => ['inverter' => '1', 'btu' => ['18000']],
        ];
        $results = [];
        foreach ($cases as $name => $input) {
            $query = Product::query()->where('is_active', true);
            $rows = $this->filters->apply($query, Request::create('/san-pham', 'GET', $input))
                ->orderBy('id')->get(['id', 'sku', 'model_code']);
            $results[$name] = ['input' => $input, 'count' => $rows->count(), 'products' => $rows->toArray()];
        }
        $categoryInput = ['btu' => ['18000']];
        $categoryRows = $this->filters->apply(
            Product::query()->where('is_active', true)->whereHas('category', fn ($query) => $query->where('slug', 'dieu-hoa-treo-tuong')),
            Request::create('/san-pham', 'GET', $categoryInput),
        )->orderBy('id')->get(['id', 'sku', 'model_code']);
        $results['wall-mounted-category+18000'] = [
            'input' => ['category' => 'dieu-hoa-treo-tuong', ...$categoryInput],
            'count' => $categoryRows->count(),
            'products' => $categoryRows->toArray(),
        ];

        return $results;
    }
}
