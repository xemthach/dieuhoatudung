<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

class ExportMarketingCapacityInventoryCommand extends Command
{
    protected $signature = 'catalog:export-marketing-capacity-inventory {--json : Emit machine-readable Product identities}';

    protected $description = 'Read-only Product identity inventory for source-backed marketing-capacity evidence extraction.';

    public function handle(): int
    {
        $rows = Product::query()
            ->with(['brand:id,slug', 'category:id,slug,name'])
            ->orderBy('id')
            ->get([
                'id', 'brand_id', 'product_category_id', 'sku', 'model_code', 'series',
                'product_type', 'marketing_capacity_btu', 'technical_capacity_btu', 'capacity_kw',
            ])
            ->map(fn (Product $product): array => [
                'product_id' => $product->id,
                'brand_slug' => $product->brand?->slug,
                'category_slug' => $product->category?->slug,
                'category_name' => $product->category?->name,
                'sku' => $product->sku,
                'model_code' => $product->model_code,
                'series' => $product->series,
                'product_type' => $product->product_type,
                'marketing_capacity_btu' => $product->marketing_capacity_btu,
                'technical_capacity_btu' => $product->technical_capacity_btu,
                'capacity_kw' => $product->capacity_kw,
            ])->values()->all();

        if ($this->option('json')) {
            $this->line((string) json_encode(['read_only' => true, 'count' => count($rows), 'products' => $rows], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } else {
            $this->info('Products: '.count($rows));
            $this->warn('Read-only inventory; no Product data was changed.');
        }

        return self::SUCCESS;
    }
}
