<?php

// Livewire's PHPUnit upload trait normally supplies this disk. Browser HTTP
// requests need a persistent disk shared across their separate PHP requests.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! app()->environment('testing') || config('database.default') !== 'sqlite'
    || basename(config('database.connections.sqlite.database')) !== 'product-data-governance-browser-isolated.sqlite'
    || basename($app->getCachedConfigPath()) !== 'browser-config.php') {
    throw new RuntimeException('Refusing to configure a non-isolated browser runtime.');
}
$configuration = config()->all();
$configuration['ai']['worker_desired_state_path'] = storage_path('framework/testing/browser-worker-desired-state.json');
$configuration['filesystems']['disks']['tmp-for-tests'] = [
    'driver'=>'local', 'root'=>storage_path('framework/testing/browser-upload-tmp'),
    'throw'=>true, 'visibility'=>'private',
];
file_put_contents($app->getCachedConfigPath(), '<?php return '.var_export($configuration, true).';');
echo "Isolated browser upload runtime configured.\n";
