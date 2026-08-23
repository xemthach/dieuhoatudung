<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$batchId = $argv[1] ?? null;
if (! is_string($batchId) || $batchId === '') {
    fwrite(STDERR, "Usage: php scripts/backup_gree_specs_snapshot.php <batch_id>\n");
    exit(1);
}

$backupDir = base_path("storage/backups/{$batchId}");
if (! is_dir($backupDir) && ! mkdir($backupDir, 0777, true) && ! is_dir($backupDir)) {
    fwrite(STDERR, "Cannot create backup directory: {$backupDir}\n");
    exit(1);
}

$greeBrandIds = DB::table('brands')
    ->whereRaw('LOWER(name) like ?', ['%gree%'])
    ->pluck('id')
    ->map(fn ($id) => (int) $id)
    ->all();

$products = DB::table('products')
    ->leftJoin('brands', 'products.brand_id', '=', 'brands.id')
    ->select(
        'products.id',
        'products.name',
        'products.slug',
        'products.sku',
        'products.model_code',
        'products.brand_id',
        'brands.name as brand_name',
        'products.specs_json',
        'products.btu',
        'products.power_consumption',
        'products.airflow',
        'products.noise_level',
        'products.indoor_dimensions',
        'products.outdoor_dimensions',
        'products.weight',
        'products.updated_at'
    )
    ->where(function ($q) use ($greeBrandIds) {
        if ($greeBrandIds !== []) {
            $q->whereIn('products.brand_id', $greeBrandIds);
        }

        $q->orWhereRaw("UPPER(COALESCE(products.model_code, '')) REGEXP '(^|/)G[A-Z0-9]+'")
          ->orWhereRaw("UPPER(COALESCE(products.sku, '')) REGEXP '(^|[-_/])G[A-Z0-9]+'")
          ->orWhereRaw("UPPER(COALESCE(products.name, '')) LIKE '%GREE%'");
    })
    ->orderBy('products.id')
    ->get();

$jsonPath = "{$backupDir}/gree_products_specs_snapshot.json";
$csvPath = "{$backupDir}/gree_products_specs_snapshot.csv";
$metaPath = "{$backupDir}/gree_backup_meta.json";

file_put_contents(
    $jsonPath,
    json_encode(
        [
            'batch_id' => $batchId,
            'generated_at' => now()->toIso8601String(),
            'count' => $products->count(),
            'products' => $products,
        ],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    )
);

$fh = fopen($csvPath, 'wb');
fwrite($fh, "\xEF\xBB\xBF");
fputcsv($fh, [
    'id',
    'name',
    'sku',
    'model_code',
    'brand_id',
    'brand_name',
    'btu',
    'power_consumption',
    'airflow',
    'noise_level',
    'indoor_dimensions',
    'outdoor_dimensions',
    'weight',
    'updated_at',
    'specs_json',
]);

foreach ($products as $p) {
    fputcsv($fh, [
        $p->id,
        $p->name,
        $p->sku,
        $p->model_code,
        $p->brand_id,
        $p->brand_name,
        $p->btu,
        $p->power_consumption,
        $p->airflow,
        $p->noise_level,
        $p->indoor_dimensions,
        $p->outdoor_dimensions,
        $p->weight,
        $p->updated_at,
        is_string($p->specs_json) ? $p->specs_json : json_encode($p->specs_json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
}
fclose($fh);

file_put_contents(
    $metaPath,
    json_encode(
        [
            'batch_id' => $batchId,
            'generated_at' => now()->toIso8601String(),
            'gree_brand_ids' => $greeBrandIds,
            'product_count' => $products->count(),
            'files' => [
                basename($jsonPath),
                basename($csvPath),
                'products_table.sql',
                'brands_categories.sql',
                'catalog_parsed_tables.sql',
            ],
        ],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    )
);

echo "Backup completed for batch {$batchId}\n";
echo "GREE-like products snapshot count: {$products->count()}\n";
