<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Brand;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ImportPanasonicProducts extends Command
{
    protected $signature = 'import:panasonic-products {--dry-run : Preview without importing}';
    protected $description = 'Import Panasonic products from extracted JSON catalogue data';

    public function handle(): int
    {
        $jsonPath = base_path('data dieu hoa/import_output/panasonic_products_import.json');
        if (!file_exists($jsonPath)) {
            $this->error("File not found: {$jsonPath}");
            return 1;
        }

        $products = json_decode(file_get_contents($jsonPath), true);
        if (empty($products)) {
            $this->error('No products found in JSON file.');
            return 1;
        }

        $dryRun = $this->option('dry-run');
        $this->info(($dryRun ? '[DRY RUN] ' : '') . "Processing " . count($products) . " Panasonic products...");

        $brand = Brand::where('name', 'Panasonic')->first();
        if (!$brand) {
            $this->error('Brand Panasonic not found. Please create it first.');
            return 1;
        }

        $created = $updated = 0;
        $errors = [];

        foreach ($products as $i => $row) {
            try {
                $existing = Product::where('brand_id', $brand->id)
                    ->where('model_code', $row['model_code'])
                    ->first();

                if (!$existing) {
                    $existing = Product::where('sku', $row['sku'])->first();
                }

                $data = [
                    'name' => $row['name'],
                    'brand_id' => $brand->id,
                    'product_category_id' => $row['product_category_id'] ?? null,
                    'sku' => $row['sku'],
                    'model_code' => $row['model_code'],
                    'btu' => $row['btu'],
                    'capacity_kw' => $row['capacity_kw'] ?? null,
                    'hp' => $row['hp'] ?? null,
                    'inverter' => $row['inverter'] ?? true,
                    'cooling_type' => $row['cooling_type'] ?? null,
                    'voltage' => $row['voltage'] ?? null,
                    'refrigerant_gas' => $row['refrigerant_gas'] ?? null,
                    'power_consumption' => $row['power_consumption'] ?? null,
                    'airflow' => $row['airflow'] ?? null,
                    'noise_level' => $row['noise_level'] ?? null,
                    'indoor_dimensions' => $row['indoor_dimensions'] ?? null,
                    'outdoor_dimensions' => $row['outdoor_dimensions'] ?? null,
                    'weight' => $row['weight'] ?? null,
                    'short_description' => $row['short_description'] ?? null,
                    'specs_json' => isset($row['specs_json']) ? json_decode($row['specs_json'], true) : null,
                    'is_active' => $row['is_active'] ?? true,
                    'is_featured' => $row['is_featured'] ?? false,
                    'is_new' => $row['is_new'] ?? false,
                    'seo_title' => $row['seo_title'] ?? null,
                    'seo_description' => $row['seo_description'] ?? null,
                ];

                if ($dryRun) {
                    $action = $existing ? 'UPDATE' : 'CREATE';
                    $this->line("  [{$action}] {$row['model_code']}");
                    if ($existing) $updated++;
                    else $created++;
                    continue;
                }

                if ($existing) {
                    $existing->update($data);
                    $updated++;
                } else {
                    $slug = Str::slug($row['name']);
                    if (mb_strlen($slug) > 200) $slug = mb_substr($slug, 0, 200);
                    $baseSlug = $slug;
                    $counter = 1;
                    while (Product::withTrashed()->where('slug', $slug)->exists()) {
                        $slug = mb_substr($baseSlug, 0, 195) . '-' . $counter++;
                    }
                    $data['slug'] = $slug;
                    Product::create($data);
                    $created++;
                }
            } catch (\Throwable $e) {
                $errors[] = "Row {$i}: {$e->getMessage()}";
                $this->error("  Error #{$i}: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info("=== Import Summary ===");
        $this->info("Created: {$created}");
        $this->info("Updated: {$updated}");
        $this->info("Errors: " . count($errors));

        return 0;
    }
}
