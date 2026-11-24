<?php

// Local read-only source backup plus synthetic MySQL queue verification.
// Never migrates, restores, seeds or writes the configured application database.
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Services\DataTransfer\DataImportService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Symfony\Component\Process\Process;

if (! app()->environment('local') || config('database.default') !== 'mysql') {
    throw new RuntimeException('Local MySQL only.');
}
$connection = config('database.connections.mysql');
if (! in_array(strtolower((string) $connection['host']), ['localhost', '127.0.0.1', '::1'], true)
    || filled($connection['url'] ?? null)) {
    throw new RuntimeException('Loopback MySQL without a URL override required.');
}
$source = $connection['database'];
if (! preg_match('/^[A-Za-z0-9_-]+$/', $source)) {
    throw new RuntimeException('Unsupported source identifier.');
}
$run = now()->format('Ymd_His').'_'.bin2hex(random_bytes(4));
$directory = storage_path('app/private/import-export-audit/'.$run);
if (! mkdir($directory, 0700, true) || realpath(dirname($directory)) !== realpath(storage_path('app/private/import-export-audit'))) {
    throw new RuntimeException('Unsafe backup directory.');
}
$options = $directory.'/mysql-options.cnf';
$quote = static fn ($value) => '"'.addcslashes((string) $value, "\\\"\n\r").'"';
file_put_contents($options, "[client]\nhost=".$quote($connection['host'])."\nport=".(int) $connection['port']."\nuser=".$quote($connection['username'])."\npassword=".$quote($connection['password'])."\n");
chmod($options, 0600);
$pdo = DB::connection()->getPdo();
$clone = 'audit_import_'.$run;
if (! preg_match('/^audit_import_[0-9]{8}_[0-9]{6}_[a-f0-9]{8}$/', $clone) || $clone === $source) {
    throw new RuntimeException('Unsafe disposable target.');
}
$created = false;
$files = [];
$snapshot = static function (PDO $pdo, string $database): array {
    $tables = $pdo->query('SHOW TABLES FROM `'.$database.'`')->fetchAll(PDO::FETCH_COLUMN);
    $proof = [];
    foreach ($tables as $table) {
        $identifier = str_replace('`', '``', $table);
        $proof[$table] = ['rows' => (int) $pdo->query('SELECT COUNT(*) FROM `'.$database.'`.`'.$identifier.'`')->fetchColumn(), 'checksum' => $pdo->query('CHECKSUM TABLE `'.$database.'`.`'.$identifier.'` EXTENDED')->fetch(PDO::FETCH_ASSOC)['Checksum']];
    }

    return $proof;
};
try {
    $before = $snapshot($pdo, $source);
    $sql = $directory.'/database.sql';
    $dump = new Process(['mysqldump', '--defaults-extra-file='.$options, '--single-transaction', '--no-tablespaces', '--skip-lock-tables', '--set-gtid-purged=OFF', '--result-file='.$sql, $source]);
    $dump->setTimeout(300)->mustRun();
    chmod($sql, 0600);
    $proof = ['source_host' => 'loopback', 'source_tables' => count($before), 'source_products' => $before['products']['rows'] ?? null, 'backup_file' => $sql, 'backup_bytes' => filesize($sql), 'backup_sha256' => hash_file('sha256', $sql), 'source_before' => $before];
    file_put_contents($directory.'/backup-proof.json', json_encode($proof, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

    // A newly generated database receives only synthetic fixtures, never a
    // restore of the source's business data.
    $pdo->exec('CREATE DATABASE `'.$clone.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $created = true;
    config(['database.connections.mysql' => array_replace($connection, ['database' => $clone]), 'queue.default' => 'database', 'queue.connections.database.connection' => 'mysql', 'cache.default' => 'array']);
    DB::purge('mysql');
    Artisan::call('migrate', ['--force' => true]);
    $actor = User::factory()->create(['is_active' => true]);
    $actor->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
    $category = ProductCategory::factory()->create(['is_active' => true, 'technical_schema_status' => 'active', 'technical_schema_json' => ['fields' => [['key' => 'btu', 'label' => 'BTU', 'type' => 'number']]]]);
    $service = app(DataImportService::class);
    $csv = $directory.'/synthetic-worker.csv';
    $files[] = $csv;
    $stream = fopen($csv, 'wb');
    fputcsv($stream, ['sku', 'name', 'category_slug', 'regular_price']);
    for ($row = 1; $row <= 10000; $row++) {
        fputcsv($stream, ['AUDIT-'.$row, 'Synthetic audit product '.$row, $category->slug, 100000]);
    }
    fclose($stream);
    $started = microtime(true);
    $job = $service->uploadAndPreview('product', $csv, 'synthetic-worker.csv', 'csv', 'create', 'sku', $actor->id, 'business');
    $files[] = storage_path('app/private/'.$job->file_path);
    if ($job->failed_rows !== 0 || $service->confirmImport($job)->status !== 'queued') {
        throw new RuntimeException('Synthetic preview/queue failed.');
    }
    Artisan::call('queue:work', ['--queue' => 'default', '--once' => true, '--tries' => 1, '--timeout' => 900]);
    $job->refresh();
    $queue = ['status' => $job->status, 'products' => Product::count(), 'receipts' => DB::table('data_import_receipts')->count(), 'failed_jobs' => DB::table('failed_jobs')->count(), 'seconds' => round(microtime(true) - $started, 2), 'peak_mb' => round(memory_get_peak_usage(true) / 1048576, 1)];
    if ($queue['status'] !== 'completed' || $queue['products'] !== 10000 || $queue['receipts'] !== 10000 || $queue['failed_jobs'] !== 0) {
        throw new RuntimeException('Synthetic worker failed: '.json_encode($queue));
    }

    // Force a runtime failure after the first row has been written. Preview is
    // valid; a DB trigger rejects only the final row during worker execution.
    $rollback = $directory.'/synthetic-rollback.csv';
    $files[] = $rollback;
    $stream = fopen($rollback, 'wb');
    fputcsv($stream, ['sku', 'name', 'category_slug', 'regular_price']);
    for ($row = 1; $row <= 501; $row++) {
        fputcsv($stream, ['ROLLBACK-'.$row, 'Synthetic rollback '.$row, $category->slug, 100000]);
    }
    fclose($stream);
    $job = $service->uploadAndPreview('product', $rollback, 'synthetic-rollback.csv', 'csv', 'create', 'sku', $actor->id, 'business');
    $files[] = storage_path('app/private/'.$job->file_path);
    if ($job->failed_rows !== 0) {
        throw new RuntimeException('Rollback preview failed.');
    }
    DB::unprepared("CREATE TRIGGER audit_import_fail_late BEFORE INSERT ON products FOR EACH ROW BEGIN IF NEW.sku = 'ROLLBACK-501' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Synthetic late import failure'; END IF; END");
    if ($service->confirmImport($job)->status !== 'queued') {
        throw new RuntimeException('Rollback job not queued.');
    }
    Artisan::call('queue:work', ['--queue' => 'default', '--once' => true, '--tries' => 1, '--timeout' => 900]);
    $job->refresh();
    $rollbackProof = ['status' => $job->status, 'success_rows' => $job->success_rows, 'products' => Product::where('sku', 'like', 'ROLLBACK-%')->count(), 'receipts' => DB::table('data_import_receipts')->where('data_import_job_id', $job->id)->count()];
    if ($rollbackProof['status'] !== 'failed' || $rollbackProof['success_rows'] !== 0 || $rollbackProof['products'] !== 0 || $rollbackProof['receipts'] !== 0) {
        throw new RuntimeException('Atomic rollback failed: '.json_encode($rollbackProof));
    }
    $after = $snapshot($pdo, $source);
    if ($before !== $after) {
        throw new RuntimeException('Source database changed during isolated verification.');
    }
    $proof += ['source_unchanged' => true, 'queue_10000' => $queue, 'atomic_rollback' => $rollbackProof, 'disposable_database' => $clone];
    file_put_contents($directory.'/verification-proof.json', json_encode($proof, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    echo json_encode(array_diff_key($proof, ['source_before' => true]), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
} finally {
    DB::purge('mysql');
    if ($created) {
        $pdo->exec('DROP DATABASE `'.$clone.'`');
    }
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    if (is_file($options)) {
        unlink($options);
    }
}
