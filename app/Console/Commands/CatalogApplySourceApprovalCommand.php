<?php

namespace App\Console\Commands;

use App\Models\CatalogModel;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CatalogApplySourceApprovalCommand extends Command
{
    protected $signature = 'catalog:apply-source-approval
        {file : CSV approval template path}
        {--apply : Persist approved links}
        {--report : Write JSON report}';

    protected $description = 'Apply approved catalog source/model selections from approval template CSV.';

    public function handle(): int
    {
        $file = (string) $this->argument('file');
        $absolute = str_starts_with($file, '/') || preg_match('/^[A-Za-z]:[\/\\\\]/', $file) ? $file : base_path($file);
        if (! is_file($absolute)) {
            $this->error("File not found: {$absolute}");

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $rows = $this->readCsv($absolute);
        $approved = array_values(array_filter($rows, fn (array $row): bool => in_array(strtolower(trim((string) ($row['approve_source'] ?? ''))), ['1', 'yes', 'y', 'true', 'approved'], true)));

        $updated = 0;
        $skipped = 0;
        $items = [];

        foreach ($approved as $row) {
            $productId = (int) ($row['product_id'] ?? 0);
            $catalogModelId = (int) ($row['catalog_model_id'] ?? 0);
            if ($productId <= 0 || $catalogModelId <= 0) {
                $skipped++;
                continue;
            }

            $product = Product::query()->find($productId);
            $catalogModel = CatalogModel::query()->with('source')->find($catalogModelId);
            if (! $product || ! $catalogModel) {
                $skipped++;
                continue;
            }

            if ($apply) {
                $product->update([
                    'catalog_source_id' => $catalogModel->catalog_source_id,
                    'catalog_model_id' => $catalogModel->id,
                    'catalog_match_status' => 'matched',
                    'technical_specs_source' => 'catalog_verified_specs',
                ]);
                $updated++;
            }

            $items[] = [
                'product_id' => $product->id,
                'sku' => $product->sku,
                'model_code' => $product->model_code,
                'catalog_model_id' => $catalogModel->id,
                'catalog_source_id' => $catalogModel->catalog_source_id,
                'catalog_source_name' => $catalogModel->source?->source_name,
                'action' => $apply ? 'updated' : 'would_update',
            ];
        }

        $this->table(['Metric', 'Count'], [
            ['Rows total', count($rows)],
            ['Rows approved', count($approved)],
            ['Updated', $updated],
            ['Skipped', $skipped],
            ['Apply mode', $apply ? 'yes' : 'no (dry-run)'],
        ]);

        if ($this->option('report')) {
            $path = 'private/reports/catalog-apply-source-approval-'.now()->format('Ymd_His').'.json';
            Storage::disk('local')->put($path, json_encode([
                'generated_at' => now()->toIso8601String(),
                'file' => $absolute,
                'apply' => $apply,
                'summary' => [
                    'rows_total' => count($rows),
                    'rows_approved' => count($approved),
                    'updated' => $updated,
                    'skipped' => $skipped,
                ],
                'items' => $items,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
            $this->info('Report written: '.storage_path('app/'.$path));
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int,array<string,string>>
     */
    private function readCsv(string $path): array
    {
        $fp = fopen($path, 'rb');
        if (! $fp) {
            return [];
        }

        $headers = fgetcsv($fp) ?: [];
        $headers = array_map(fn ($h) => trim((string) $h), $headers);
        $rows = [];

        while (($data = fgetcsv($fp)) !== false) {
            $row = [];
            foreach ($headers as $index => $header) {
                if ($header === '') {
                    continue;
                }
                $row[$header] = (string) ($data[$index] ?? '');
            }
            $rows[] = $row;
        }

        fclose($fp);

        return $rows;
    }
}

