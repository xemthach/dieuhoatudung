<?php

declare(strict_types=1);

use App\Models\ImportGovernanceAudit;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\DataTransfer\ImportGovernanceService;
use App\Services\Settings\SettingService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$statePath = storage_path('framework/testing/import-governance-browser-fixture.json');
$mode = $argv[1] ?? 'setup';
$policy = ImportGovernanceService::DETACH_CATALOG_LINEAGE;

if ($mode === 'snapshot') {
    $state = json_decode((string) file_get_contents($statePath), true, 512, JSON_THROW_ON_ERROR);
    echo json_encode([
        'mode' => app(ImportGovernanceService::class)->mode($policy),
        'audit_count' => ImportGovernanceAudit::where('changed_by', $state['user_id'])->count(),
    ], JSON_THROW_ON_ERROR);
    exit;
}

if ($mode === 'cleanup') {
    $state = is_file($statePath) ? json_decode((string) file_get_contents($statePath), true) : [];
    ImportGovernanceAudit::where('changed_by', $state['user_id'] ?? 0)->delete();
    if (($state['setting'] ?? null) === null) {
        SiteSetting::where('group', 'product_transfer')->where('key', 'detach_catalog_lineage')->delete();
    } else {
        SiteSetting::updateOrCreate(
            ['group' => 'product_transfer', 'key' => 'detach_catalog_lineage'],
            $state['setting'],
        );
    }
    User::whereKey($state['user_id'] ?? 0)->delete();
    app(SettingService::class)->forgetCache($policy);
    @unlink($statePath);
    echo json_encode(['cleaned' => true], JSON_THROW_ON_ERROR);
    exit;
}

$existing = SiteSetting::where('group', 'product_transfer')->where('key', 'detach_catalog_lineage')->first();
$role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
$password = bin2hex(random_bytes(18));
$user = User::create([
    'name' => 'Import Governance Browser Certification',
    'email' => 'import-governance-'.bin2hex(random_bytes(6)).'@example.test',
    'password' => Hash::make($password),
    'is_active' => true,
]);
$user->assignRole($role);
$originalMode = app(ImportGovernanceService::class)->mode($policy);
$state = [
    'user_id' => $user->id,
    'email' => $user->email,
    'password' => $password,
    'original_mode' => $originalMode,
    'changed_mode' => $originalMode === 'ON' ? 'OFF' : 'ON',
    'setting' => $existing?->only(['value', 'type', 'is_encrypted', 'is_public']),
];
file_put_contents($statePath, json_encode($state, JSON_THROW_ON_ERROR));
echo json_encode($state, JSON_THROW_ON_ERROR);
