<?php

declare(strict_types=1);

use App\Models\Product;
use App\Services\AI\ProductAiGenerationReadiness;
use App\Services\Product\ProductContentEligibilityPolicy;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$readiness = app(ProductAiGenerationReadiness::class);
$allIds = Product::query()->orderBy('id')->pluck('id')->map(fn ($id): int => (int) $id)->all();
$samples = [
    'single' => array_slice($allIds, 0, 1),
    'bulk_10' => array_slice($allIds, 0, 10),
    'bulk_all' => $allIds,
];
$rows = [];

foreach ($samples as $name => $ids) {
    Event::forget(QueryExecuted::class);
    $queries = 0;
    $listener = static function (QueryExecuted $query) use (&$queries): void { $queries++; };
    DB::listen($listener);
    $started = hrtime(true);
    $result = $readiness->resolveMany($ids, [ProductContentEligibilityPolicy::LONG_DESCRIPTION]);
    $durationMs = round((hrtime(true) - $started) / 1_000_000, 2);

    $rows[] = [
        'scenario' => $name,
        'selected' => $result['selected'],
        'ready' => $result['ready'],
        'blocked' => $result['blocked'],
        'queries' => $queries,
        'duration_ms' => $durationMs,
    ];
}

$tables = [
    'products', 'ai_product_jobs', 'ai_product_job_items', 'ai_product_drafts',
    'ai_request_logs', 'product_bulk_operations', 'product_bulk_operation_items',
    'catalog_models', 'catalog_model_fields',
];
$counts = [];
foreach ($tables as $table) {
    $counts[$table] = Schema::hasTable($table) ? DB::table($table)->count() : null;
}

echo json_encode(['performance' => $rows, 'counts' => $counts], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
