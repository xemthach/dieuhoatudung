<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\Catalog\CatalogProductMatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CatalogMatchProductsCommand extends Command
{
    protected $signature = 'catalog:match-products
        {--apply : Persist catalog match to products table}
        {--report : Write JSON report}';

    protected $description = 'Match products to internal catalog models by SKU/model normalization.';

    public function handle(CatalogProductMatcher $matcher): int
    {
        $apply = (bool) $this->option('apply');
        $rows = [];
        $summary = ['matched' => 0, 'catalog_source_missing' => 0, 'ambiguous_catalog_match' => 0, 'total' => 0];

        foreach (Product::query()->with(['brand', 'category'])->get() as $product) {
            $summary['total']++;
            $match = $matcher->match($product);
            $status = $match['status'];
            $summary[$status] = ($summary[$status] ?? 0) + 1;

            if ($apply && $status === 'matched' && $match['model']) {
                $product->update([
                    'catalog_source_id' => $match['model']->catalog_source_id,
                    'catalog_model_id' => $match['model']->id,
                    'catalog_match_status' => 'matched',
                ]);
            }

            $rows[] = [
                'product_id' => $product->id,
                'sku' => $product->sku,
                'model' => $product->model_code,
                'status' => $status,
                'catalog_model_id' => $match['model']?->id,
                'candidate_ids' => $match['candidates'],
                'action' => $status === 'matched' ? ($apply ? 'linked' : 'can_link') : 'skip',
            ];
        }

        $this->table(
            ['Metric', 'Count'],
            [
                ['Matched', $summary['matched']],
                ['Missing', $summary['catalog_source_missing']],
                ['Ambiguous', $summary['ambiguous_catalog_match']],
                ['Total', $summary['total']],
                ['Apply mode', $apply ? 'yes' : 'no (dry-run)'],
            ]
        );

        if ($this->option('report')) {
            $payload = [
                'generated_at' => now()->toIso8601String(),
                'apply' => $apply,
                'summary' => $summary,
                'rows' => $rows,
            ];
            $path = 'private/reports/catalog-match-products-'.now()->format('Ymd_His').'.json';
            Storage::disk('local')->put($path, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
            $this->info('Report written: '.storage_path('app/'.$path));
        }

        return self::SUCCESS;
    }
}

