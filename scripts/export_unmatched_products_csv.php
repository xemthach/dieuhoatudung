<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$stamp = date('Ymd_His');
$path = __DIR__."/../storage/app/private/reports/products-unmatched-catalog-{$stamp}.csv";
$dir = dirname($path);
if (! is_dir($dir)) {
    mkdir($dir, 0777, true);
}

$fp = fopen($path, 'wb');
fwrite($fp, "\xEF\xBB\xBF");
fputcsv($fp, ['product_id', 'name', 'sku', 'model_code']);

$rows = App\Models\Product::query()
    ->where(function ($q): void {
        $q->whereNull('catalog_match_status')
            ->orWhere('catalog_match_status', '!=', 'matched');
    })
    ->orderBy('id')
    ->get(['id', 'name', 'sku', 'model_code']);

foreach ($rows as $r) {
    fputcsv($fp, [$r->id, $r->name, $r->sku, $r->model_code]);
}
fclose($fp);

echo $path.PHP_EOL;
echo 'count='.$rows->count().PHP_EOL;

