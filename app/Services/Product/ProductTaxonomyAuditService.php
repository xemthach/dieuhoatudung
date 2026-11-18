<?php

namespace App\Services\Product;

use App\Enums\ProductHvacClass;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class ProductTaxonomyAuditService
{
    private const SKY_AIR_MATRIX = 'resources/catalog/skyair_combination_matrix.csv';
    private const SOURCE_COVERAGE = 'storage/app/private/reports/marketing_capacity_pdf_source_coverage.json';
    private const REVIEWED_GREE_PHYSICAL_SECTIONS = 'resources/catalog/gree_physical_category_sections_reviewed.json';

    private const EQUIPMENT_CATEGORY = [
        'cassette' => 'dieu-hoa-am-tran-cassette',
        'ducted' => 'dieu-hoa-giau-tran-noi-ong-gio',
        'floor_standing' => 'dieu-hoa-tu-dung',
        'ceiling_suspended' => 'dieu-hoa-dat-san-ap-tran',
    ];

    public function __construct(
        private readonly ProductMarketingCapacityEligibility $eligibility,
    ) {}

    public function audit(): array
    {
        $categories = ProductCategory::query()->orderBy('id')->get();
        $categoriesBySlug = $categories->keyBy('slug');
        $skyAir = $this->skyAirRows();
        $coverage = $this->coverageRows();

        $rows = Product::query()
            ->with([
                'brand:id,slug,name',
                'category:id,slug,name',
                'catalogSource:id,source_name,source_type,section_type,source_status,version',
                'catalogModel:id,catalog_source_id,model,sku,verification_status',
            ])
            ->orderBy('id')
            ->get()
            ->map(function (Product $product) use ($categoriesBySlug, $skyAir, $coverage): array {
                $evidence = $this->classify($product, $skyAir[$product->sku] ?? null, $coverage[$product->id] ?? null);
                $targetSlug = $evidence['target_category_slug'];
                $target = $targetSlug ? $categoriesBySlug->get($targetSlug) : null;

                if ($targetSlug && ! $target) {
                    $evidence = array_merge($evidence, [
                        'classification' => 'CONFLICT',
                        'reason' => 'CANONICAL_TARGET_CATEGORY_MISSING',
                        'target_category_slug' => null,
                        'canonical_taxonomy' => ProductHvacClass::UNKNOWN->value,
                    ]);
                }

                $action = $evidence['classification'];
                if ($action === 'DETERMINISTIC') {
                    $action = $product->category?->slug === $targetSlug ? 'KEEP' : 'PROPOSE_UPDATE';
                }
                $proposedStatus = match ($action) {
                    'KEEP', 'PROPOSE_UPDATE' => ProductDataVerificationService::VERIFIED,
                    'CONFLICT' => ProductDataVerificationService::CONFLICT,
                    default => ProductDataVerificationService::UNRESOLVED,
                };

                return [
                    'product_id' => $product->id,
                    'sku' => $product->sku,
                    'model_code' => $product->model_code,
                    'name' => $product->name,
                    'brand' => $product->brand?->slug,
                    'series' => $product->series,
                    'current_category_id' => $product->product_category_id,
                    'current_category_slug' => $product->category?->slug,
                    'current_category_name' => $product->category?->name,
                    'current_product_type' => $product->product_type,
                    'current_taxonomy_status' => $product->taxonomy_status ?: ProductDataVerificationService::UNRESOLVED,
                    'proposed_category_id' => $target?->id,
                    'proposed_category_slug' => $targetSlug,
                    'proposed_category_name' => $target?->name,
                    // product_type is a Merchant/display field in the current
                    // schema; this remediation does not repurpose it as the
                    // canonical internal taxonomy field.
                    'proposed_product_type' => $product->product_type,
                    'canonical_taxonomy' => $evidence['canonical_taxonomy'],
                    'proposed_taxonomy_status' => $proposedStatus,
                    'filter_eligible_after_proposal' => $this->eligibility->isEligibleClass($evidence['canonical_taxonomy']),
                    'evidence_type' => $evidence['evidence_type'],
                    'evidence_source' => $evidence['evidence_source'],
                    'evidence_location' => $evidence['evidence_location'],
                    'evidence_detail' => $evidence['evidence_detail'],
                    'catalog_source_id' => $product->catalog_source_id,
                    'catalog_source' => $product->catalogSource?->source_name,
                    'catalog_source_status' => $product->catalogSource?->source_status,
                    'catalog_model_id' => $product->catalog_model_id,
                    'catalog_model' => $product->catalogModel?->model,
                    'classification' => $action,
                    'reason' => $evidence['reason'],
                ];
            })->values()->all();

        $stats = collect($rows)->countBy('classification')->all();
        $postTaxonomy = collect($rows)->countBy('canonical_taxonomy')->all();
        $eligible = collect($rows)->whereIn('classification', ['KEEP', 'PROPOSE_UPDATE'])
            ->where('filter_eligible_after_proposal', true)->count();
        $knownNotEligible = collect($rows)->whereIn('classification', ['KEEP', 'PROPOSE_UPDATE'])
            ->where('filter_eligible_after_proposal', false)->count();
        $unresolved = count($rows) - $eligible - $knownNotEligible;

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'read_only' => true,
            'product_mutation' => 'NONE',
            'taxonomy_authority' => 'CANONICAL_PRODUCT_CATEGORY_SLUG',
            'product_type_policy' => 'MERCHANT_DISPLAY_METADATA_NOT_AUTOMATIC_TAXONOMY_AUTHORITY',
            'categories' => $categories->map(fn (ProductCategory $category): array => [
                'id' => $category->id,
                'slug' => $category->slug,
                'name' => $category->name,
                'parent_id' => $category->parent_id,
                'is_active' => $category->is_active,
                'deleted_at' => Schema::hasColumn('product_categories', 'deleted_at') ? $category->getAttribute('deleted_at') : null,
                'technical_schema_status' => $category->technical_schema_status,
                'technical_schema_version' => $category->technical_schema_version,
                'canonical_semantic' => $this->eligibility->classForCategorySlug($category->slug)->value,
            ])->values()->all(),
            'eligibility_matrix' => $this->eligibility->matrix(),
            'stats' => [
                'total' => count($rows),
                'keep' => (int) ($stats['KEEP'] ?? 0),
                'propose_update' => (int) ($stats['PROPOSE_UPDATE'] ?? 0),
                'ambiguous' => (int) ($stats['AMBIGUOUS'] ?? 0),
                'no_evidence' => (int) ($stats['NO_EVIDENCE'] ?? 0),
                'conflict' => (int) ($stats['CONFLICT'] ?? 0),
                'filter_eligible' => $eligible,
                'filter_not_eligible_known' => $knownNotEligible,
                'filter_eligibility_unresolved' => $unresolved,
            ],
            'post_taxonomy_classes' => $postTaxonomy,
            'rows' => $rows,
        ];
        $payload['ledger_digest'] = hash('sha256', json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return $payload;
    }

    private function classify(Product $product, ?array $skyAir, ?array $coverage): array
    {
        if ($skyAir) {
            $slug = self::EQUIPMENT_CATEGORY[$skyAir['equipment_type']] ?? null;

            return $slug
                ? $this->deterministic(
                    $slug,
                    'VERIFIED_SKYAIR_COMBINATION_MATRIX',
                    $skyAir['source_pdf'],
                    'page '.$skyAir['source_page'].'; row '.$skyAir['source_row'],
                    $skyAir['equipment_type'].' / '.$skyAir['equipment_subtype'],
                )
                : $this->unresolved('CONFLICT', 'UNSUPPORTED_SKYAIR_EQUIPMENT_TYPE', 'VERIFIED_SKYAIR_COMBINATION_MATRIX', $skyAir['source_pdf'], $skyAir['equipment_type'] ?? '');
        }

        if (($coverage['status'] ?? null) === 'CONFLICT') {
            $conflict = $coverage['identity_conflict'] ?? [];

            return $this->unresolved(
                'CONFLICT',
                'PRODUCT_SOURCE_IDENTITY_CONFLICT',
                'REVIEWED_IDENTITY_CONFLICT',
                (string) ($conflict['source_file'] ?? ''),
                (string) (($conflict['product_model_code'] ?? '').' != '.($conflict['source_model_code'] ?? '')),
            );
        }

        if ($product->sku === 'LOCAL-AI-RETRY-PROBE-20260830') {
            return $this->unresolved('NO_EVIDENCE', 'LOCAL_PROBE_NOT_A_CATALOGUE_PRODUCT', 'LOCAL_RUNTIME_PROBE', 'products', (string) $product->sku);
        }

        $matches = collect($coverage['source_matches'] ?? []);
        if ($product->brand?->slug === 'daikin' && $matches->contains(fn (array $match): bool => str_contains($match['source'], 'ROOM AIR'))) {
            $locations = $matches->map(fn (array $match): string => $match['source'].':'.implode(',', $match['pages'] ?? []))->implode('; ');

            return $this->deterministic('dieu-hoa-treo-tuong', 'OFFICIAL_ROOM_AIR_PRODUCT_IDENTITY', 'Daikin Room Air 2026', $locations, 'Exact model or reviewed visual-table identity in the wall-mounted catalogue.');
        }

        if ($product->brand?->slug === 'gree' && $matches->contains(fn (array $match): bool => str_contains($match['source'], 'GMV6 Solar'))) {
            $locations = $matches->map(fn (array $match): string => $match['source'].':'.implode(',', $match['pages'] ?? []))->implode('; ');

            return $this->deterministic('dieu-hoa-trung-tam-vrf-gmv', 'OFFICIAL_GMV_PRODUCT_IDENTITY', 'Gree GMV6 Solar', $locations, 'Exact GMV model identity; multiple official documents are retained when present.');
        }

        if ($product->brand?->slug === 'gree' && ($reviewed = $this->reviewedGreePhysicalCategory($product, $matches))) {
            if ($reviewed['classification'] === 'CONFLICT') {
                return $this->unresolved(
                    'CONFLICT',
                    'MULTIPLE_REVIEWED_PHYSICAL_CATEGORY_MATCHES',
                    'REVIEWED_PRODUCT_LIST_SECTION',
                    $reviewed['source'],
                    $reviewed['detail'],
                );
            }

            return $this->deterministic(
                $reviewed['category_slug'],
                'REVIEWED_PRODUCT_LIST_PHYSICAL_SECTION',
                $reviewed['source'],
                $reviewed['location'],
                $reviewed['detail'],
            );
        }

        if (($coverage['status'] ?? null) === 'MISSING') {
            return $this->unresolved('NO_EVIDENCE', 'NO_EXACT_TRUSTED_SOURCE_IDENTITY', 'SOURCE_COVERAGE_AUDIT', '', (string) $product->model_code);
        }

        return $this->unresolved(
            'AMBIGUOUS',
            'IDENTITY_MATCHED_BUT_PHYSICAL_CATEGORY_NOT_REVIEWED',
            'SOURCE_IDENTITY_PLUS_REVIEW_ONLY_PRODUCT_TYPE',
            $matches->pluck('source')->implode('; '),
            (string) ($product->product_type ?: $product->series),
        );
    }

    private function deterministic(string $slug, string $type, string $source, string $location, string $detail): array
    {
        return [
            'classification' => 'DETERMINISTIC',
            'reason' => 'SOURCE_BACKED_CANONICAL_CATEGORY',
            'target_category_slug' => $slug,
            'canonical_taxonomy' => $this->eligibility->classForCategorySlug($slug)->value,
            'evidence_type' => $type,
            'evidence_source' => $source,
            'evidence_location' => $location,
            'evidence_detail' => $detail,
        ];
    }

    private function unresolved(string $classification, string $reason, string $type, string $source, string $detail): array
    {
        return [
            'classification' => $classification,
            'reason' => $reason,
            'target_category_slug' => null,
            'canonical_taxonomy' => ProductHvacClass::UNKNOWN->value,
            'evidence_type' => $type,
            'evidence_source' => $source,
            'evidence_location' => '',
            'evidence_detail' => $detail,
        ];
    }

    /** @return array<string, array<string, string>> */
    private function skyAirRows(): array
    {
        $path = base_path(self::SKY_AIR_MATRIX);
        if (! is_file($path)) {
            throw new RuntimeException('Verified SkyAir combination matrix is missing.');
        }

        $handle = fopen($path, 'rb');
        $headers = fgetcsv($handle);
        $headers[0] = ltrim((string) $headers[0], "\xEF\xBB\xBF");
        $rows = [];
        while (($values = fgetcsv($handle)) !== false) {
            $row = array_combine($headers, $values);
            if (($row['readiness'] ?? null) === 'IMPORT_READY') {
                $rows[$row['sku']] = $row;
            }
        }
        fclose($handle);

        return $rows;
    }

    /** @return array<int, array<string, mixed>> */
    private function coverageRows(): array
    {
        $path = base_path(self::SOURCE_COVERAGE);
        if (! is_file($path)) {
            throw new RuntimeException('Run the read-only PDF source coverage audit before taxonomy audit.');
        }

        $payload = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        return collect($payload['rows'] ?? [])->keyBy('product_id')->all();
    }

    /** @param \Illuminate\Support\Collection<int, array<string, mixed>> $matches */
    private function reviewedGreePhysicalCategory(Product $product, $matches): ?array
    {
        $path = base_path(self::REVIEWED_GREE_PHYSICAL_SECTIONS);
        if (! is_file($path)) {
            throw new RuntimeException('Reviewed Gree physical-category evidence ledger is missing.');
        }

        $ledger = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $resolved = [];
        $normalizedModel = $this->normalizeIdentity((string) $product->model_code);

        foreach ($ledger['documents'] ?? [] as $document) {
            $sourcePath = base_path((string) $document['relative_path']);
            if (! is_file($sourcePath) || strtoupper(hash_file('sha256', $sourcePath)) !== strtoupper((string) $document['sha256'])) {
                throw new RuntimeException('Reviewed Gree physical-category source checksum mismatch: '.($document['filename'] ?? 'unknown'));
            }

            $sourceMatch = $matches->firstWhere('source', $document['filename']);
            $matchedPages = collect($sourceMatch['pages'] ?? [])->map(fn ($page): int => (int) $page);
            foreach ($document['sections'] ?? [] as $section) {
                $pages = collect($section['pages'] ?? [])->map(fn ($page): int => (int) $page);
                $page = $matchedPages->intersect($pages)->sort()->first();
                if ($page) {
                    $resolved[] = [
                        'category_slug' => $section['category_slug'],
                        'source' => $document['filename'],
                        'location' => 'page '.$page,
                        'detail' => 'Exact Product model in reviewed section heading: '.$section['heading'],
                    ];
                }
            }

            foreach ($document['exact_models'] ?? [] as $entry) {
                if ($normalizedModel !== '' && $normalizedModel === $this->normalizeIdentity((string) $entry['model_code'])) {
                    $resolved[] = [
                        'category_slug' => $entry['category_slug'],
                        'source' => $document['filename'],
                        'location' => 'page '.(int) $entry['page'],
                        'detail' => 'Exact reviewed model row under heading: '.$entry['heading'],
                    ];
                }
            }
        }

        $resolved = collect($resolved)->unique(fn (array $row): string => $row['category_slug'].'|'.$row['source'].'|'.$row['location'])->values();
        $categories = $resolved->pluck('category_slug')->unique()->values();
        if ($categories->count() > 1) {
            return [
                'classification' => 'CONFLICT',
                'source' => $resolved->pluck('source')->unique()->implode('; '),
                'detail' => $resolved->map(fn (array $row): string => $row['category_slug'].' @ '.$row['source'].' '.$row['location'])->implode('; '),
            ];
        }
        if ($categories->count() !== 1) {
            return null;
        }

        $first = $resolved->first();

        return ['classification' => 'DETERMINISTIC', ...$first];
    }

    private function normalizeIdentity(string $value): string
    {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper($value)) ?: '';
    }
}
