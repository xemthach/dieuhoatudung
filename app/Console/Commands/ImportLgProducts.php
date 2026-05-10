<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Brand;
use App\Models\ProductCategory;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ImportLgProducts extends Command
{
    protected $signature = 'import:lg-products {--dry-run : Preview without importing}';
    protected $description = 'Import LG products from extracted JSON catalogue data';

    public function handle(): int
    {
        $jsonPath = base_path('data dieu hoa/import_output/lg_products_import.json');
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
        $this->info(($dryRun ? '[DRY RUN] ' : '') . "Processing " . count($products) . " LG products...");

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        $brand = Brand::where('name', 'LG')->first();
        if (!$brand) {
            $this->error('Brand LG not found in database.');
            return 1;
        }

        foreach ($products as $i => $row) {
            try {
                // Find existing by brand + model_code
                $existing = Product::where('brand_id', $brand->id)
                    ->where('model_code', $row['model_code'])
                    ->first();

                if (!$existing) {
                    // Also try by SKU
                    $existing = Product::where('sku', $row['sku'])->first();
                }

                // Prepare data
                $data = [
                    'name' => $row['name'],
                    'brand_id' => $brand->id,
                    'product_category_id' => $row['product_category_id'] ?? null,
                    'sku' => $row['sku'],
                    'model_code' => $row['model_code'],
                    'btu' => $row['btu'],
                    'capacity_kw' => $row['capacity_kw'] ?? null,
                    'hp' => $row['hp'] ?? null,
                    'inverter' => $row['inverter'] ?? false,
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
                    $this->line("  [{$action}] #{$i} {$row['name']}");
                    if ($existing) $updated++;
                    else $created++;
                    continue;
                }

                if ($existing) {
                    $existing->update($data);
                    $updated++;
                } else {
                    // Generate unique slug
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
        $this->info("Skipped: {$skipped}");
        $this->info("Errors: " . count($errors));

        if (!empty($errors)) {
            $this->newLine();
            $this->warn("Errors:");
            foreach (array_slice($errors, 0, 10) as $err) {
                $this->line("  - {$err}");
            }
        }

        return 0;
    }
}
