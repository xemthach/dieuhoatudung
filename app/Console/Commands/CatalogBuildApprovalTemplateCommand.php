<?php

namespace App\Console\Commands;

use App\Models\CatalogModel;
use App\Models\Product;
use App\Services\Catalog\CatalogSourcePriorityResolver;
use App\Services\HVAC\HVACTechnicalNormalizer;
use Illuminate\Console\Command;

class CatalogBuildApprovalTemplateCommand extends Command
{
    protected $signature = 'catalog:build-approval-template {--limit=5 : Top candidates per product}';

    protected $description = 'Export source approval template for ambiguous product->catalog matches.';

    public function handle(HVACTechnicalNormalizer $normalizer, CatalogSourcePriorityResolver $resolver): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $stamp = now()->format('Ymd_His');
        $path = storage_path("app/private/reports/catalog-source-approval-template-{$stamp}.csv");
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $fp = fopen($path, 'wb');
        if (! $fp) {
            $this->error('Cannot write template file.');

            return self::FAILURE;
        }

        // UTF-8 BOM for Excel on Windows
        fwrite($fp, "\xEF\xBB\xBF");

        fputcsv($fp, [
            'product_id', 'product_name', 'sku', 'model_code',
            'candidate_rank', 'catalog_model_id', 'catalog_source_id',
            'source_name', 'source_type', 'source_file',
            'score', 'approve_source', 'note',
        ]);

        $rows = 0;
        foreach (Product::query()->with(['brand', 'category'])->get() as $product) {
            $normalizedSku = $normalizer->normalizeSku($product->sku);
            $normalizedModel = $normalizer->normalizeModel($product->model_code);
            if ($normalizedSku === '' && $normalizedModel === '') {
                continue;
            }

            $candidates = CatalogModel::query()
                ->with(['source', 'fields'])
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

            $resolved = $resolver->resolve($candidates, [
                'normalized_sku' => $normalizedSku,
                'normalized_model' => $normalizedModel,
            ]);

            $ranked = collect($resolved['ranked'])->take($limit)->values();
            foreach ($ranked as $idx => $rank) {
                $model = $candidates->firstWhere('id', $rank['id']);
                if (! $model) {
                    continue;
                }

                fputcsv($fp, [
                    $product->id,
                    $product->name,
                    $product->sku,
                    $product->model_code,
                    $idx + 1,
                    $model->id,
                    $model->catalog_source_id,
                    $model->source?->source_name,
                    $model->source?->source_type,
                    $model->source?->uploaded_file,
                    $rank['score'],
                    '',
                    '',
                ]);
                $rows++;
            }
        }

        fclose($fp);
        $this->info("Template written: {$path}");
        $this->info("Rows: {$rows}");

        return self::SUCCESS;
    }
}
