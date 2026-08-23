<?php

use App\Services\Backup\SafeRestorePayloadBuilder;

require dirname(__DIR__).'/vendor/autoload.php';

$app = require_once dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$input = $argv[1] ?? null;
$target = $argv[2] ?? null;
$output = $argv[3] ?? null;

if (! is_string($input) || ! is_string($target) || ! is_string($output)) {
    fwrite(STDERR, "Usage: php scripts/build_safe_restore_payload.php <backup.sql> <target_db> <output.sql>\n");
    exit(2);
}

try {
    $stats = app(SafeRestorePayloadBuilder::class)->build($input, $output, $target);
    echo json_encode([
        'status' => 'PASS',
        'target' => $target,
        'output' => $output,
        'stats' => $stats,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, json_encode(['status' => 'BLOCKED', 'error' => $e->getMessage()], JSON_UNESCAPED_SLASHES).PHP_EOL);
    exit(1);
}
