<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$ids = [40,41,42,43,44,45,48,49,50,133,134,135,136,137,138,139,140,141,150,160,172,192,193,195,196,197,198,199,200,201,202];
foreach ($ids as $id) {
    $s = App\Models\CatalogSource::find($id);
    if (! $s) {
        continue;
    }
    echo $id.'|'.$s->source_type.'|'.$s->source_name.'|'.$s->uploaded_file.PHP_EOL;
}

