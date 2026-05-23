<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$matcher = app(App\Services\Catalog\CatalogProductMatcher::class);
$products = App\Models\Product::query()->with(['brand', 'category'])->get();

$rows = [];
foreach ($products as $product) {
    $match = $matcher->match($product);
    if (($match['status'] ?? '') !== 'ambiguous_catalog_match') {
        continue;
    }

    $candidateIds = $match['candidates'] ?? [];
    $candidates = App\Models\CatalogModel::query()
        ->with(['source', 'fields'])
        ->whereIn('id', $candidateIds)
        ->get();

    $rows[] = [
        'product_id' => $product->id,
        'sku' => $product->sku,
        'model_code' => $product->model_code,
        'candidate_count' => count($candidateIds),
        'candidates' => $candidates->map(function ($c) {
            return [
                'catalog_model_id' => $c->id,
                'source_id' => $c->catalog_source_id,
                'source_type' => $c->source?->source_type,
                'source_name' => $c->source?->source_name,
                'uploaded_file' => $c->source?->uploaded_file,
                'fields_count' => $c->fields->count(),
                'confidence' => $c->confidence_score,
            ];
        })->values()->all(),
    ];
}

$path = __DIR__.'/../storage/app/private/reports/ambiguous-analysis-'.date('Ymd_His').'.json';
file_put_contents($path, json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
echo $path.PHP_EOL;
echo 'rows='.count($rows).PHP_EOL;

