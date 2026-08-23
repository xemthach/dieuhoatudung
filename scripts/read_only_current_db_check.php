<?php

require dirname(__DIR__) . '/vendor/autoload.php';
$app = require_once dirname(__DIR__) . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$counts = [];
foreach (['products', 'catalog_sources', 'catalog_models', 'catalog_model_fields'] as $table) {
    $counts[$table] = Illuminate\Support\Facades\DB::table($table)->count();
}
$columns = [];
foreach (['marketing_capacity_btu', 'technical_capacity_btu', 'technical_capacity_status'] as $column) {
    if (Illuminate\Support\Facades\Schema::hasColumn('products', $column)) {
        $columns[] = $column;
    }
}
$rows = Illuminate\Support\Facades\DB::table('products')->select(['id', 'btu'])->orderBy('id')->get();
$values = $rows->pluck('btu')->map(fn ($value) => (string) $value)->all();
$pairs = $rows->map(fn ($row) => $row->id . ':' . (string) $row->btu)->all();
echo json_encode([
    'database' => config('database.connections.mysql.database'),
    'counts' => $counts,
    'new_product_columns' => $columns,
    'btu_hash_candidates' => [
        'values_pipe' => hash('sha256', implode('|', $values)),
        'pairs_pipe' => hash('sha256', implode('|', $pairs)),
        'pairs_newline' => hash('sha256', implode(PHP_EOL, $pairs)),
        'json_rows' => hash('sha256', json_encode($rows->all(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
        'json_values' => hash('sha256', json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
        'serialize_rows' => hash('sha256', serialize($rows->all())),
        'serialize_values' => hash('sha256', serialize($values)),
        'values_comma' => hash('sha256', implode(',', $values)),
        'values_newline' => hash('sha256', implode(PHP_EOL, $values)),
        'values_concat' => hash('sha256', implode('', $values)),
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
