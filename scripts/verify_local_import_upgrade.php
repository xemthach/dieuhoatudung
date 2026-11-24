<?php

// Explicit local upgrade tool: verify an existing private dump in a disposable
// database, rehearse only the audit's additive migrations, then apply locally.
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

if (($argv[2] ?? '') !== '--apply-local') {
    throw new RuntimeException('Explicit --apply-local is required.');
}
if (! app()->environment('local') || config('database.default') !== 'mysql') {
    throw new RuntimeException('Local MySQL only.');
}
$connection = config('database.connections.mysql');
if (! in_array(strtolower((string) $connection['host']), ['localhost', '127.0.0.1', '::1'], true) || filled($connection['url'] ?? null)) {
    throw new RuntimeException('Loopback MySQL without URL override required.');
}
$source = $connection['database'];
if (! preg_match('/^[A-Za-z0-9_-]+$/', $source)) {
    throw new RuntimeException('Unsupported source identifier.');
}
$directory = realpath($argv[1] ?? '');
$root = realpath(storage_path('app/private/import-export-audit'));
if ($directory === false || $root === false || dirname($directory) !== $root) {
    throw new RuntimeException('A private audit backup directory is required.');
}
$proof = json_decode(file_get_contents($directory.'/backup-proof.json'), true, 512, JSON_THROW_ON_ERROR);
$sql = $directory.'/database.sql';
if (! is_file($sql) || ! hash_equals($proof['backup_sha256'], hash_file('sha256', $sql))) {
    throw new RuntimeException('Backup checksum mismatch.');
}
$options = $directory.'/upgrade-options-'.bin2hex(random_bytes(6)).'.cnf';
$quote = static fn ($value) => '"'.addcslashes((string) $value, "\\\"\n\r").'"';
file_put_contents($options, "[client]\nhost=".$quote($connection['host'])."\nport=".(int) $connection['port']."\nuser=".$quote($connection['username'])."\npassword=".$quote($connection['password'])."\n");
chmod($options, 0600);
$pdo = DB::connection()->getPdo();
$clone = 'audit_restore_'.now()->format('Ymd_His').'_'.bin2hex(random_bytes(4));
if (! preg_match('/^audit_restore_[0-9]{8}_[0-9]{6}_[a-f0-9]{8}$/', $clone) || $clone === $source) {
    throw new RuntimeException('Unsafe disposable target.');
}
$tables = $pdo->query('SHOW TABLES FROM `'.$source.'`')->fetchAll(PDO::FETCH_COLUMN);
$columns = [];
foreach ($tables as $table) {
    $columns[$table] = array_column($pdo->query('SHOW COLUMNS FROM `'.$source.'`.`'.str_replace('`', '``', $table).'`')->fetchAll(PDO::FETCH_ASSOC), 'Field');
}
$digest = static function (PDO $pdo, string $database, array $columns): array {
    $result = [];
    foreach ($columns as $table => $fields) {
        $quoted = array_map(static fn ($field) => '`'.str_replace('`', '``', $field).'`', $fields);
        $primary = $pdo->query('SHOW KEYS FROM `'.$database.'`.`'.str_replace('`', '``', $table).'` WHERE Key_name = \'PRIMARY\'')->fetchAll(PDO::FETCH_ASSOC);
        usort($primary, static fn ($a, $b) => $a['Seq_in_index'] <=> $b['Seq_in_index']);
        $order = $primary === [] ? $quoted : array_map(static fn ($key) => '`'.str_replace('`', '``', $key['Column_name']).'`', $primary);
        $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
        $statement = $pdo->query('SELECT '.implode(',', $quoted).' FROM `'.$database.'`.`'.str_replace('`', '``', $table).'` ORDER BY '.implode(',', $order));
        $hash = hash_init('sha256');
        $count = 0;
        while ($row = $statement->fetch(PDO::FETCH_NUM)) {
            $serialized = json_encode(array_map(static fn ($value) => $value === null ? null : base64_encode((string) $value), $row), JSON_THROW_ON_ERROR);
            hash_update($hash, strlen($serialized).':'.$serialized);
            $count++;
        }
        $statement->closeCursor();
        $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
        $result[$table] = ['rows' => $count, 'old_columns_sha256' => hash_final($hash)];
    }

    return $result;
};
$before = $digest($pdo, $source, $columns);
$pending = array_values(array_diff(array_map(static fn ($file) => pathinfo($file, PATHINFO_FILENAME), glob(database_path('migrations/*.php'))), DB::table('migrations')->pluck('migration')->all()));
$allowed = ['2026_11_24_000001_create_data_import_receipts', '2026_11_24_000002_add_format_context_to_data_export_jobs'];
if (array_diff($pending, $allowed) !== []) {
    throw new RuntimeException('Unexpected pending migrations; refusing local upgrade.');
}
$created = false;
try {
    $pdo->exec('CREATE DATABASE `'.$clone.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $created = true;
    $restore = new Process(['mysql', '--defaults-extra-file='.$options, $clone]);
    $restore->setInput(fopen($sql, 'rb'))->setTimeout(300)->mustRun();
    if ($digest($pdo, $clone, $columns) !== $before) {
        throw new RuntimeException('Restore data does not exactly match current local data.');
    }
    config(['database.connections.audit_upgrade' => array_replace($connection, ['database' => $clone])]);
    Artisan::call('migrate', ['--database' => 'audit_upgrade', '--force' => true]);
    $rehearsed = $digest($pdo, $clone, $columns);
    foreach ($before as $table => $snapshot) {
        if ($table !== 'migrations' && $snapshot !== $rehearsed[$table]) {
            throw new RuntimeException('Rehearsal changed existing column data: '.$table);
        }
    }
    DB::purge('audit_upgrade');
    if ($digest($pdo, $source, $columns) !== $before) {
        throw new RuntimeException('Local data changed since backup verification.');
    }
    Artisan::call('migrate', ['--force' => true]);
    $after = $digest($pdo, $source, $columns);
    foreach ($before as $table => $snapshot) {
        if ($table !== 'migrations' && $snapshot !== $after[$table]) {
            throw new RuntimeException('Existing local column data changed: '.$table);
        }
    }
    $result = ['backup_sha256' => $proof['backup_sha256'], 'backup_directory' => $directory, 'restore_rehearsal' => 'PASS', 'local_migration' => 'PASS', 'pending_applied' => $pending, 'existing_tables_verified' => count($columns) - 1, 'existing_product_rows' => $after['products']['rows'], 'product_old_columns_sha256' => $after['products']['old_columns_sha256'], 'source_before' => $before, 'source_after' => $after, 'disposable_database_removed' => $clone];
    file_put_contents($directory.'/upgrade-proof.json', json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    echo json_encode(array_diff_key($result, ['source_before' => true, 'source_after' => true]), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
} finally {
    DB::purge('audit_upgrade');
    if ($created) {
        $pdo->exec('DROP DATABASE `'.$clone.'`');
    }
    if (is_file($options)) {
        unlink($options);
    }
}
