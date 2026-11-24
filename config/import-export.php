<?php

return [
    'version' => 1,
    'queue_threshold' => 500,
    'allowed_import_types' => ['csv', 'xlsx', 'json'],
    'limits' => [
        'max_file_size_mb' => 10,
        'max_records' => 50000,
        'max_columns' => 256,
        'max_cells' => 2000000,
        'max_zip_entries' => 2048,
        'max_uncompressed_bytes' => 128 * 1024 * 1024,
    ],
];
