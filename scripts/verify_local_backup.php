<?php

// Local-only backup drill. No credentials or business payloads are printed.
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! app()->environment('local') || config('database.default') !== 'mysql') {
    throw new RuntimeException('This backup drill only supports the local MySQL environment.');
}
$connection = config('database.connections.mysql');
$source = $connection['database'];
$clone = 'audit_restore_'.date('Ymd_His').'_'.bin2hex(random_bytes(3));
if (! preg_match('/^audit_restore_[0-9]{8}_[0-9]{6}_[a-f0-9]{6}$/', $clone) || $clone === $source) {
    throw new RuntimeException('Invalid isolated restore target.');
}
$directory = storage_path('app/private/comprehensive-audit');
if (! is_dir($directory)) mkdir($directory, 0700, true);
$options = $directory.'/mysql-options-'.bin2hex(random_bytes(6)).'.cnf';
$quote = static fn ($value) => '"'.addcslashes((string) $value, "\\\"\n\r").'"';
file_put_contents($options, "[client]\nhost=".$quote($connection['host'])."\nport=".(int) $connection['port']."\nuser=".$quote($connection['username'])."\npassword=".$quote($connection['password'])."\n");
chmod($options, 0600);
$pdo = Illuminate\Support\Facades\DB::connection()->getPdo();
$sql = $directory.'/database-'.date('Ymd_His').'.sql';
$created = false;
try {
    $dump = new Symfony\Component\Process\Process(['mysqldump', '--defaults-extra-file='.$options, '--single-transaction', '--no-tablespaces', '--skip-lock-tables', '--set-gtid-purged=OFF', '--result-file='.$sql, $source]);
    $dump->setTimeout(300)->mustRun();
    $charset = $pdo->query('SELECT DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='.$pdo->quote($source))->fetch(PDO::FETCH_ASSOC);
    $pdo->exec('CREATE DATABASE `'.$clone.'` CHARACTER SET '.$charset['DEFAULT_CHARACTER_SET_NAME'].' COLLATE '.$charset['DEFAULT_COLLATION_NAME']);
    $created = true;
    $restore = new Symfony\Component\Process\Process(['mysql', '--defaults-extra-file='.$options, $clone]);
    $restore->setInput(fopen($sql, 'rb'))->setTimeout(300)->mustRun();
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $proof = [];
    foreach ($tables as $table) {
        $identifier = str_replace('`', '``', $table);
        $before = $pdo->query('SELECT COUNT(*) FROM `'.$source.'`.`'.$identifier.'`')->fetchColumn();
        $after = $pdo->query('SELECT COUNT(*) FROM `'.$clone.'`.`'.$identifier.'`')->fetchColumn();
        $checksums = $pdo->query('CHECKSUM TABLE `'.$source.'`.`'.$identifier.'`, `'.$clone.'`.`'.$identifier.'` EXTENDED')->fetchAll(PDO::FETCH_ASSOC);
        if ($before !== $after || $checksums[0]['Checksum'] !== $checksums[1]['Checksum']) {
            throw new RuntimeException('Restore mismatch for table '.$table);
        }
        $proof[$table] = ['rows' => (int) $before, 'checksum' => $checksums[0]['Checksum']];
    }
    config(['database.connections.backup_verify'=>array_replace($connection, ['database'=>$clone])]);
    Illuminate\Support\Facades\Artisan::call('migrate', ['--database'=>'backup_verify', '--force'=>true]);
    foreach ($tables as $table) {
        if ($table === 'migrations') continue;
        $identifier = str_replace('`', '``', $table);
        $checksums = $pdo->query('CHECKSUM TABLE `'.$source.'`.`'.$identifier.'`, `'.$clone.'`.`'.$identifier.'` EXTENDED')->fetchAll(PDO::FETCH_ASSOC);
        if ($checksums[0]['Checksum'] !== $checksums[1]['Checksum']) throw new RuntimeException('Migration changed existing data: '.$table);
    }
    Illuminate\Support\Facades\DB::purge('backup_verify');
    $media = new ZipArchive();
    $media->open($directory.'/public-media.zip', ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $mediaRoot = storage_path('app/public');
    $mediaCount = 0;
    if (is_dir($mediaRoot)) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($mediaRoot, FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->isFile() && ! $file->isLink()) {
                $media->addFile($file->getPathname(), substr($file->getPathname(), strlen($mediaRoot) + 1));
                $mediaCount++;
            }
        }
    }
    $media->close();
    $verifyMedia = new ZipArchive();
    if ($verifyMedia->open($directory.'/public-media.zip', ZipArchive::CHECKCONS) !== true) throw new RuntimeException('Media backup verification failed.');
    for ($index = 0; $index < $verifyMedia->numFiles; $index++) {
        $name = $verifyMedia->getNameIndex($index);
        if (! hash_equals(hash_file('sha256', $mediaRoot.DIRECTORY_SEPARATOR.$name), hash('sha256', $verifyMedia->getFromIndex($index)))) throw new RuntimeException('Media restore hash mismatch.');
    }
    $verifyMedia->close();
    file_put_contents($directory.'/backup-proof.json', json_encode(['verified_at' => date(DATE_ATOM), 'database_sha256' => hash_file('sha256', $sql), 'migration_upgrade'=>'PASS', 'tables' => $proof, 'media_files' => $mediaCount, 'media_restore'=>'PASS', 'media_sha256' => hash_file('sha256', $directory.'/public-media.zip')], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    echo json_encode(['backup_restore' => 'PASS', 'migration_upgrade'=>'PASS', 'tables' => count($tables), 'media_files' => $mediaCount, 'media_restore'=>'PASS']).PHP_EOL;
} finally {
    if ($created) $pdo->exec('DROP DATABASE `'.$clone.'`');
    if (is_file($options)) unlink($options);
}
