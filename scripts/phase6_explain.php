<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$queries = [
    'active_product_listing' => \App\Models\Product::query()->where('is_active', true)->orderByDesc('created_at')->limit(12),
    'category_capacity_listing' => \App\Models\Product::query()->where('is_active', true)->where('product_category_id', 1)->where('marketing_capacity_btu', '>=', 24000)->orderByDesc('created_at'),
    'exact_model_search' => \App\Models\Product::query()->where('is_active', true)->whereRaw('LOWER(model_code) = ?', ['gcc24s6i/gmc24s6i']),
];

$result = [];
foreach ($queries as $name => $query) {
    $sql = $query->toRawSql();
    $result[$name] = ['sql' => $sql, 'explain' => DB::select('EXPLAIN '.$sql)];
}
$result['products_indexes'] = DB::select('SHOW INDEX FROM products');
$target = storage_path('app/private/reports/phase6b_explain_after.json');
if (! is_dir(dirname($target))) mkdir(dirname($target), 0777, true);
file_put_contents($target, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT).PHP_EOL;
