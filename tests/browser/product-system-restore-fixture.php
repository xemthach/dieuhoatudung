<?php

declare(strict_types=1);

use App\Models\DataExportJob;
use App\Models\DataImportJob;
use App\Models\User;
use App\Services\DataTransfer\DataExportService;
use App\Services\DataTransfer\DataImportService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$statePath = storage_path('framework/testing/product-system-restore-browser-fixture.json');
$mode = $argv[1] ?? 'setup';

if ($mode === 'cleanup') {
    $state = is_file($statePath) ? json_decode((string) file_get_contents($statePath), true) : [];
    foreach (['import_file', 'export_file'] as $pathKey) {
        if (! empty($state[$pathKey])) {
            Storage::disk('local')->delete($state[$pathKey]);
        }
    }
    DataImportJob::query()->whereKey($state['import_job_id'] ?? 0)->delete();
    DataExportJob::query()->whereKey($state['export_job_id'] ?? 0)->delete();
    User::query()->whereKey($state['user_id'] ?? 0)->delete();
    @unlink($statePath);
    echo json_encode(['cleaned' => true], JSON_THROW_ON_ERROR);
    exit;
}

$role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
$password = bin2hex(random_bytes(18));
$user = User::create([
    'name' => 'System Restore Browser Certification',
    'email' => 'system-restore-browser-'.bin2hex(random_bytes(6)).'@example.test',
    'password' => Hash::make($password),
    'is_active' => true,
]);
$user->assignRole($role);

$export = app(DataExportService::class)->export('product', 'xlsx', [], [], [], 'all', $user->id);
$import = app(DataImportService::class)->uploadAndPreview(
    'product',
    storage_path('app/private/'.$export->file_path),
    $export->file_name,
    'xlsx',
    userId: $user->id,
);

$state = [
    'user_id' => $user->id,
    'email' => $user->email,
    'password' => $password,
    'export_job_id' => $export->id,
    'export_file' => $export->file_path,
    'import_job_id' => $import->id,
    'import_file' => $import->file_path,
    'total_rows' => $import->total_rows,
    'failed_rows' => $import->failed_rows,
    'mode' => $import->mode,
    'contract' => data_get($import->format_context_json, 'contract'),
];
file_put_contents($statePath, json_encode($state, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode($state, JSON_THROW_ON_ERROR);
