<?php

namespace App\Console\Commands;

use App\Models\CatalogModel;
use App\Models\Product;
use App\Services\Catalog\CatalogSourcePriorityResolver;
use App\Services\HVAC\HVACTechnicalNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CatalogAutoApproveAmbiguousCommand extends Command
{
    protected $signature = 'catalog:auto-approve-ambiguous
        {--apply : Persist selected catalog links}
        {--min-margin=15 : Minimum score gap between rank 1 and rank 2}
        {--preferred-types=csv,xlsx,xls,json,pdf : Preferred source type order}
        {--report : Write JSON report}';

    protected $description = 'Auto-select best catalog source for ambiguous product matches using strict score margin.';

    public function handle(HVACTechnicalNormalizer $normalizer, CatalogSourcePriorityResolver $resolver): int
    {
        $apply = (bool) $this->option('apply');
        $minMargin = max(1, (int) $this->option('min-margin'));
        $preferredTypes = collect(explode(',', (string) $this->option('preferred-types')))
            ->map(fn (string $v) => strtolower(trim($v)))
            ->filter()
            ->values()
            ->all();

        $summary = [
            'ambiguous_total' => 0,
            'auto_selected' => 0,
            'updated' => 0,
            'skipped_low_margin' => 0,
            'skipped_no_candidates' => 0,
        ];
        $items = [];

        foreach (Product::query()->with(['brand', 'category'])->get() as $product) {
            $normalizedSku = $normalizer->normalizeSku((string) $product->sku);
            $normalizedModel = $normalizer->normalizeModel((string) $product->model_code);
            if ($normalizedSku === '' && $normalizedModel === '') {
                continue;
            }

            $candidates = CatalogModel::query()
                ->with(['source', 'fields'])
                ->whereHas('fields')
                ->whereHas('source', function ($source): void {
                    $source->where(function ($trusted): void {
                        $trusted
                            ->where('uploaded_file', 'like', '%/data dieu hoa/%')
                            ->orWhere('uploaded_file', 'like', '%/storage/catalogs/%')
                            ->orWhere('uploaded_file', 'like', '%/storage/imports/%')
                            ->orWhere('uploaded_file', 'like', '%/storage/uploads/%')
                            ->orWhere('uploaded_file', 'like', '%/public/uploads/%')
                            ->orWhere('uploaded_file', 'like', '%/public/storage/%');
                    })->where(function ($blocked): void {
                        $blocked
                            ->where('uploaded_file', 'not like', '%/storage/app/audit/%')
                            ->where('uploaded_file', 'not like', '%/storage/app/private/reports/%')
                            ->where('uploaded_file', 'not like', '%/storage/app/private/data-exports/%')
                            ->where('uploaded_file', 'not like', '%/storage/app/private/data-imports/%');
                    });
                })
                ->where(function ($q) use ($normalizedSku, $normalizedModel): void {
                    if ($normalizedSku !== '') {
                        $q->orWhere('normalized_sku', $normalizedSku);
                    }
                    if ($normalizedModel !== '') {
                        $q->orWhere('normalized_model', $normalizedModel);
                    }
                })
                ->get();

            if ($candidates->count() <= 1) {
                continue;
            }

            $summary['ambiguous_total']++;
            if ($candidates->isEmpty()) {
                $summary['skipped_no_candidates']++;
                continue;
            }

            $resolved = $resolver->resolve($candidates, [
                'normalized_sku' => $normalizedSku,
                'normalized_model' => $normalizedModel,
            ]);
            $ranked = collect($resolved['ranked'])->values();
            if ($ranked->count() < 2) {
                continue;
            }

            $top = $ranked->get(0);
            $second = $ranked->get(1);
            $margin = (int) ($top['score'] ?? 0) - (int) ($second['score'] ?? 0);
            if ($margin < $minMargin) {
                $summary['skipped_low_margin']++;
                continue;
            }

            $topModel = $candidates->firstWhere('id', $top['id']);
            if (! $topModel) {
                continue;
            }

            $topType = strtolower((string) ($topModel->source?->source_type ?? ''));
            if ($preferredTypes !== [] && ! in_array($topType, $preferredTypes, true)) {
                $summary['skipped_low_margin']++;
                continue;
            }

            $summary['auto_selected']++;

            if ($apply) {
                $product->update([
                    'catalog_source_id' => $topModel->catalog_source_id,
                    'catalog_model_id' => $topModel->id,
                    'catalog_match_status' => 'matched',
                ]);
                $summary['updated']++;
            }

            $items[] = [
                'product_id' => $product->id,
                'sku' => $product->sku,
                'model_code' => $product->model_code,
                'selected_catalog_model_id' => $topModel->id,
                'selected_catalog_source_id' => $topModel->catalog_source_id,
                'selected_source_name' => $topModel->source?->source_name,
                'selected_source_type' => $topType,
                'top_score' => (int) ($top['score'] ?? 0),
                'second_score' => (int) ($second['score'] ?? 0),
                'score_margin' => $margin,
                'action' => $apply ? 'updated' : 'would_update',
            ];
        }

        $this->table(['Metric', 'Count'], [
            ['Ambiguous products', $summary['ambiguous_total']],
            ['Auto-selected', $summary['auto_selected']],
            ['Updated', $summary['updated']],
            ['Skipped (low margin)', $summary['skipped_low_margin']],
            ['Skipped (no candidates)', $summary['skipped_no_candidates']],
            ['Apply mode', $apply ? 'yes' : 'no (dry-run)'],
        ]);

        if ($this->option('report')) {
            $path = 'private/reports/catalog-auto-approve-ambiguous-'.now()->format('Ymd_His').'.json';
            Storage::disk('local')->put($path, json_encode([
                'generated_at' => now()->toIso8601String(),
                'apply' => $apply,
                'min_margin' => $minMargin,
                'preferred_types' => $preferredTypes,
                'summary' => $summary,
                'items' => $items,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
            $this->info('Report written: '.storage_path('app/'.$path));
        }

        return self::SUCCESS;
    }
}

