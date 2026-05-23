<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$rows = App\Models\Product::query()
    ->where(function ($q): void {
        $q->whereNull('catalog_match_status')
            ->orWhere('catalog_match_status', '!=', 'matched');
    })
    ->orderBy('id')
    ->get(['id', 'name', 'sku', 'model_code']);

echo 'count='.$rows->count().PHP_EOL;
foreach ($rows as $r) {
    echo $r->id."\t".$r->name."\t".$r->sku."\t".$r->model_code.PHP_EOL;
}

