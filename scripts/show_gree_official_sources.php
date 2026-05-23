<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$rows = App\Models\CatalogSource::query()
    ->where('source_name', 'like', '%lac2025%')
    ->orWhere('source_name', 'like', '%LAC 2025%')
    ->orWhere('uploaded_file', 'like', '%E-CATALOGUE LAC 2025.pdf%')
    ->orWhere('source_name', 'like', '%verified_pdf_extract_gree_lac2025_strict%')
    ->get(['id', 'source_name', 'source_type', 'uploaded_file']);

foreach ($rows as $r) {
    echo "{$r->id}|{$r->source_name}|{$r->source_type}|{$r->uploaded_file}".PHP_EOL;
}

