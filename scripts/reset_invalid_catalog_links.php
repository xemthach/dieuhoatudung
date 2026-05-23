<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$ids = App\Models\Product::query()
    ->whereNotNull('catalog_model_id')
    ->whereDoesntHave('catalogModel.fields')
    ->pluck('id');

echo 'reset_count='.$ids->count().PHP_EOL;

if ($ids->isNotEmpty()) {
    App\Models\Product::query()
        ->whereIn('id', $ids)
        ->update([
            'catalog_model_id' => null,
            'catalog_source_id' => null,
            'catalog_match_status' => null,
        ]);
}

