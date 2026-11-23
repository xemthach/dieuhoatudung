<?php

// Read-only comparison against the verified local backup; prints no row payloads.
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! app()->environment('local') || config('database.default') !== 'mysql') throw new RuntimeException('Local MySQL only.');
$proof = json_decode(file_get_contents(storage_path('app/private/comprehensive-audit/backup-proof.json')), true, 512, JSON_THROW_ON_ERROR);
$business = ['brands', 'product_categories', 'products', 'dealers', 'dealer_reviews', 'product_reviews', 'administrative_units', 'site_settings', 'posts', 'leads', 'quote_requests', 'media_files', 'users'];
$pdo = Illuminate\Support\Facades\DB::connection()->getPdo();
$changed = [];
foreach ($business as $table) {
    if (! isset($proof['tables'][$table])) continue;
    $count = (int) $pdo->query('SELECT COUNT(*) FROM `'.$table.'`')->fetchColumn();
    $checksum = $pdo->query('CHECKSUM TABLE `'.$table.'` EXTENDED')->fetch(PDO::FETCH_ASSOC)['Checksum'];
    if ($count !== $proof['tables'][$table]['rows'] || $checksum !== $proof['tables'][$table]['checksum']) $changed[] = $table;
}
$media = new ZipArchive();
if ($media->open(storage_path('app/private/comprehensive-audit/public-media.zip')) !== true) throw new RuntimeException('Backup missing.');
for ($index = 0; $index < $media->numFiles; $index++) {
    $name = $media->getNameIndex($index);
    $path = storage_path('app/public/'.$name);
    if (! is_file($path) || ! hash_equals(hash('sha256', $media->getFromIndex($index)), hash_file('sha256', $path))) $changed[] = 'media:'.$name;
}
$media->close();
echo json_encode(['business_data'=>$changed === [] ? 'UNCHANGED' : 'CHANGED', 'changed'=>$changed], JSON_THROW_ON_ERROR).PHP_EOL;
exit($changed === [] ? 0 : 1);
