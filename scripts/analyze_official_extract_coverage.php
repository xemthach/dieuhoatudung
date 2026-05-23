<?php

$base = __DIR__.'/../storage/catalogs/';
$allPath = $base.'verified_pdf_extract_gree.json';
$strictPath = $base.'verified_pdf_extract_gree_lac2025_strict.json';

$all = json_decode((string) @file_get_contents($allPath), true) ?: [];
$strict = json_decode((string) @file_get_contents($strictPath), true) ?: [];

$rowsFromOfficial = 0;
$modelsFromOfficial = [];
$fieldKeys = [];

foreach ($all as $row) {
    if (! is_array($row)) {
        continue;
    }
    $sourceFile = '';
    foreach ($row as $k => $v) {
        if (is_string($k) && str_ends_with($k, '__source_file') && is_string($v) && $v !== '') {
            $sourceFile = strtolower($v);
            break;
        }
    }
    if (! str_contains($sourceFile, 'e-catalogue lac 2025.pdf')) {
        continue;
    }
    $rowsFromOfficial++;
    $model = trim((string) ($row['model'] ?? ''));
    if ($model !== '') {
        $modelsFromOfficial[$model] = true;
    }
    foreach ($row as $k => $v) {
        if (! is_string($k)) {
            continue;
        }
        if (in_array($k, ['model', 'sku'], true)) {
            continue;
        }
        if (str_contains($k, '__')) {
            continue;
        }
        $fieldKeys[$k] = true;
    }
}

echo 'official_rows_in_verified_pdf_extract_gree='.$rowsFromOfficial.PHP_EOL;
echo 'official_models_in_verified_pdf_extract_gree='.count($modelsFromOfficial).PHP_EOL;
echo 'strict_rows='.count($strict).PHP_EOL;
echo 'strict_models='.count(array_unique(array_filter(array_map(fn ($r) => is_array($r) ? (string) ($r['model'] ?? '') : '', $strict)))).PHP_EOL;
echo 'field_keys='.implode(',', array_keys($fieldKeys)).PHP_EOL;
echo 'models_list:'.PHP_EOL;
foreach (array_keys($modelsFromOfficial) as $model) {
    echo $model.PHP_EOL;
}
