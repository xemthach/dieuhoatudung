<?php

namespace App\Services\Catalog;

use App\Models\CatalogModel;
use App\Models\Product;
use App\Services\HVAC\HVACTechnicalNormalizer;

class CatalogProductMatcher
{
    public function __construct(
        private readonly HVACTechnicalNormalizer $normalizer,
        private readonly CatalogSourcePriorityResolver $priorityResolver,
    ) {}

    /**
     * @return array{status: string, model: ?CatalogModel, candidates: array<int>}
     */
    public function match(Product $product): array
    {
        $preferOfficialGree = $this->isGreeProduct($product);

        if ($product->catalog_model_id) {
            $model = CatalogModel::query()
                ->with(['source', 'fields'])
                ->whereHas('fields')
                ->when($preferOfficialGree, function ($q): void {
                    $q->whereIn('catalog_source_id', [160, 203]);
                })
                ->find($product->catalog_model_id);

            return [
                'status' => $model ? 'matched' : 'catalog_source_missing',
                'model' => $model,
                'candidates' => $model ? [$model->id] : [],
            ];
        }

        $normalizedSku = $this->normalizer->normalizeSku($product->sku);
        $normalizedModel = $this->normalizer->normalizeModel($product->model_code);

        $candidates = $this->identityCandidates($normalizedSku, $normalizedModel, true, $product->brand_id, $product->product_category_id, $preferOfficialGree);
        if ($candidates->isEmpty()) {
            $candidates = $this->identityCandidates($normalizedSku, $normalizedModel, false, null, null, $preferOfficialGree);
        }

        if ($candidates->isEmpty()) {
            return ['status' => 'catalog_source_missing', 'model' => null, 'candidates' => []];
        }

        if ($candidates->count() > 1) {
            if ($preferOfficialGree && $candidates->every(fn (CatalogModel $m): bool => in_array((int) $m->catalog_source_id, [160, 203], true))) {
                $selected = $candidates
                    ->sortByDesc(fn (CatalogModel $m): array => [$m->fields->count(), (float) ($m->confidence_score ?? 0), $m->id])
                    ->first();

                return ['status' => 'matched', 'model' => $selected, 'candidates' => $candidates->pluck('id')->all()];
            }

            $resolved = $this->priorityResolver->resolve($candidates, [
                'normalized_sku' => $normalizedSku,
                'normalized_model' => $normalizedModel,
            ]);
            if ($resolved['selected']) {
                return ['status' => 'matched', 'model' => $resolved['selected'], 'candidates' => collect($resolved['ranked'])->pluck('id')->all()];
            }

            return ['status' => 'ambiguous_catalog_match', 'model' => null, 'candidates' => collect($resolved['ranked'])->pluck('id')->all()];
        }

        return ['status' => 'matched', 'model' => $candidates->first(), 'candidates' => $candidates->pluck('id')->all()];
    }

    private function identityCandidates(
        string $normalizedSku,
        string $normalizedModel,
        bool $useSourceFilters,
        ?int $brandId,
        ?int $categoryId,
        bool $preferOfficialGree = false,
    ) {
        $query = CatalogModel::query()
            ->with(['source', 'fields'])
            ->whereHas('fields');
        $this->applyTrustedSourceFilter($query);
        if ($preferOfficialGree) {
            $query->whereIn('catalog_source_id', [160, 203]);
        }

        if ($useSourceFilters && $brandId) {
            $query->whereHas('source', fn ($source) => $source->where('brand_id', $brandId));
        }
        if ($useSourceFilters && $categoryId) {
            $query->whereHas('source', fn ($source) => $source->where(function ($builder) use ($categoryId): void {
                $builder->whereNull('category_id')->orWhere('category_id', $categoryId);
            }));
        }

        $candidates = $query
            ->where(function ($builder) use ($normalizedSku, $normalizedModel): void {
                if ($normalizedSku !== '') {
                    $builder->orWhere('normalized_sku', $normalizedSku);
                }
                if ($normalizedModel !== '') {
                    $builder->orWhere('normalized_model', $normalizedModel);
                }
            })
            ->get();

        // Deduplicate same identity from repeated extraction runs.
        return $candidates
            ->groupBy(function (CatalogModel $model): string {
                $identity = $this->bestIdentity($model);
                return implode('|', [
                    (string) $model->catalog_source_id,
                    $identity,
                ]);
            })
            ->map(function ($group) {
                return $group
                    ->sortByDesc(function (CatalogModel $model): array {
                        return [
                            $model->fields->count(),
                            (float) ($model->confidence_score ?? 0),
                            $model->id,
                        ];
                    })
                    ->first();
            })
            ->filter()
            ->values();
    }

    private function bestIdentity(CatalogModel $model): string
    {
        $sku = (string) $model->normalized_sku;
        $mod = (string) $model->normalized_model;

        if ($sku === '' && $mod === '') {
            return strtoupper(preg_replace('/[^A-Z0-9]/i', '', (string) $model->model));
        }

        return strlen($sku) >= strlen($mod) ? $sku : $mod;
    }

    private function isGreeProduct(Product $product): bool
    {
        $product->loadMissing('brand');
        $brandName = strtolower((string) ($product->brand?->name ?? ''));
        $brandSlug = strtolower((string) ($product->brand?->slug ?? ''));
        $name = strtolower((string) ($product->name ?? ''));
        $model = strtolower((string) ($product->model_code ?? ''));
        $sku = strtolower((string) ($product->sku ?? ''));

        return $brandName === 'gree'
            || $brandSlug === 'gree'
            || str_contains($name, 'gree')
            || str_contains($model, 'gree')
            || str_contains($sku, 'gree');
    }

    private function applyTrustedSourceFilter($query): void
    {
        $query->whereHas('source', function ($source): void {
                    $source->where(function ($trusted): void {
                        $trusted
                            ->where('uploaded_file', 'like', '%/data dieu hoa/%')
                            ->orWhere('uploaded_file', 'like', '%/storage/catalogs/%')
                            ->orWhere('uploaded_file', 'like', '%/storage/imports/%')
                            ->orWhere('uploaded_file', 'like', '%/storage/app/private/data-imports/%')
                            ->orWhere('uploaded_file', 'like', '%/storage/uploads/%')
                            ->orWhere('uploaded_file', 'like', '%/public/uploads/%')
                            ->orWhere('uploaded_file', 'like', '%/public/storage/%');
                    })->where(function ($blocked): void {
                        $blocked
                            ->where('uploaded_file', 'not like', '%/storage/app/audit/%')
                            ->where('uploaded_file', 'not like', '%/storage/app/private/reports/%')
                            ->where('uploaded_file', 'not like', '%/storage/app/private/data-exports/%');
                    });
        });
    }
}
