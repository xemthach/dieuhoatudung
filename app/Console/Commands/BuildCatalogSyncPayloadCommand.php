<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class BuildCatalogSyncPayloadCommand extends Command
{
    protected $signature = 'products:build-sync-payload-from-catalog
        {--batch= : Batch ID to stamp into metadata}
        {--min-confidence=0.85 : Minimum confidence score}
        {--output= : Optional output path under storage/app}';

    protected $description = 'Build sync payload JSON from matched catalog model fields with strict source metadata.';

    public function handle(): int
    {
        $batch = trim((string) $this->option('batch'));
        if ($batch === '') {
            $batch = 'catalog_sync_'.now()->format('Ymd_His');
        }

        $minConfidence = (float) $this->option('min-confidence');
        $output = trim((string) $this->option('output'));
        if ($output === '') {
            $output = 'catalogs/sync_payload_'.$batch.'.json';
        }

        $rows = [];
        $totalProducts = 0;
        $eligibleProducts = 0;
        $skippedMissingCatalog = 0;
        $skippedNoFields = 0;

        $products = Product::query()
            ->with(['catalogModel.source', 'catalogModel.fields'])
            ->get();

        foreach ($products as $product) {
            $totalProducts++;
            $catalogModel = $product->catalogModel;

            if (! $catalogModel) {
                $skippedMissingCatalog++;
                continue;
            }

            $row = [
                'model' => (string) ($product->model_code ?: $catalogModel->model),
                'sku' => (string) $product->sku,
            ];

            $validFieldCount = 0;
            foreach ($catalogModel->fields as $field) {
                $fieldKey = trim((string) $field->field_key);
                $fieldValue = trim((string) $field->field_value);
                if ($fieldKey === '' || $fieldValue === '') {
                    continue;
                }

                $sourceText = trim((string) $field->source_text);
                $sourceFile = trim((string) ($catalogModel->source?->source_name ?? ''));
                $sourcePage = $field->source_page ?: $catalogModel->source_page;
                $confidence = (float) ($field->confidence_score ?? $catalogModel->confidence_score ?? 0);

                if ($sourceText === '' || $sourceFile === '' || $confidence < $minConfidence) {
                    continue;
                }

                $row[$fieldKey] = $fieldValue;
                $row[$fieldKey.'__source_file'] = $sourceFile;
                $row[$fieldKey.'__source_page'] = $sourcePage;
                $row[$fieldKey.'__source_text'] = $sourceText;
                $row[$fieldKey.'__confidence'] = round($confidence, 4);
                $validFieldCount++;
            }

            if ($validFieldCount === 0) {
                $skippedNoFields++;
                continue;
            }

            $row['__batch_id'] = $batch;
            $rows[] = $row;
            $eligibleProducts++;
        }

        Storage::disk('local')->put(
            $output,
            json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
        );

        $this->table(['Metric', 'Count'], [
            ['Total products scanned', $totalProducts],
            ['Eligible payload rows', $eligibleProducts],
            ['Skipped (missing catalog match)', $skippedMissingCatalog],
            ['Skipped (no valid fields)', $skippedNoFields],
        ]);
        $this->info('Payload written: '.storage_path('app/'.$output));
        $this->info('Batch: '.$batch);

        return self::SUCCESS;
    }
}

