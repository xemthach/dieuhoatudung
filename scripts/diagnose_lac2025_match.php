<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "== Catalog sources (LAC 2025) ==\n";
$sources = App\Models\CatalogSource::query()
    ->where('source_name', 'like', '%LAC 2025%')
    ->orWhere('uploaded_file', 'like', '%E-CATALOGUE LAC 2025.pdf%')
    ->get(['id', 'source_name', 'source_type', 'uploaded_file']);

foreach ($sources as $s) {
    echo "{$s->id} | {$s->source_name} | {$s->source_type} | {$s->uploaded_file}\n";
}

echo "\n== Sample products ==\n";
foreach ([1243, 1246, 1316] as $id) {
    $p = App\Models\Product::find($id);
    if (! $p) {
        continue;
    }
    echo "{$p->id} | {$p->sku} | {$p->model_code} | status={$p->catalog_match_status}\n";
}

echo "\n== Candidate catalog models ==\n";
$models = App\Models\CatalogModel::query()
    ->where('model', 'like', '%GULD50T1%')
    ->orWhere('model', 'like', '%GUD50T%')
    ->orWhere('model', 'like', '%GVH24AKXF%')
    ->withCount('fields')
    ->get(['id', 'catalog_source_id', 'model', 'normalized_model', 'normalized_sku', 'confidence_score']);

foreach ($models as $m) {
    echo "{$m->id} | src={$m->catalog_source_id} | fields={$m->fields_count} | {$m->model} | nm={$m->normalized_model} | ns={$m->normalized_sku} | c={$m->confidence_score}\n";
}
