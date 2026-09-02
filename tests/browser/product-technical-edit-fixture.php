<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$statePath = storage_path('framework/testing/product-technical-edit-browser-fixture.json');
$mode = $argv[1] ?? 'setup';

if ($mode === 'cleanup') {
    $state = is_file($statePath) ? json_decode((string) file_get_contents($statePath), true) : [];
    Product::query()->whereKey($state['product_id'] ?? 0)->forceDelete();
    User::query()->whereKey($state['user_id'] ?? 0)->delete();
    @unlink($statePath);
    echo json_encode(['cleaned' => true], JSON_THROW_ON_ERROR);
    exit;
}

if ($mode === 'snapshot') {
    $state = json_decode((string) file_get_contents($statePath), true, 512, JSON_THROW_ON_ERROR);
    $product = Product::query()->findOrFail($state['product_id']);
    echo json_encode([
        'technical_capacity_btu' => $product->technical_capacity_btu,
        'capacity_kw' => $product->capacity_kw,
        'hp' => $product->hp,
        'voltage' => $product->voltage,
        'source' => $product->technical_specs_source,
        'reason' => $product->technical_specs_override_reason,
        'overridden_at' => $product->technical_specs_overridden_at?->toIso8601String(),
        'spec_capacity_kw' => collect($product->specs_json)->firstWhere('key', 'capacity_kw')['value'] ?? null,
    ], JSON_THROW_ON_ERROR);
    exit;
}

$role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
$password = bin2hex(random_bytes(18));
$user = User::create([
    'name' => 'Product Technical Browser Certification',
    'email' => 'product-technical-browser-'.bin2hex(random_bytes(6)).'@example.test',
    'password' => Hash::make($password),
    'is_active' => true,
]);
$user->assignRole($role);
$product = Product::factory()->create([
    'name' => 'Technical edit browser fixture',
    'btu' => null,
    'marketing_capacity_btu' => 12000,
    'technical_capacity_btu' => 12300,
    'technical_capacity_status' => 'verified_candidate',
    'capacity_kw' => 3.6,
    'hp' => 1.5,
    'voltage' => '230V',
    'technical_specs_source' => 'catalog_verified_specs',
    'specs_json' => [[
        'key' => 'capacity_kw', 'value' => '3.6', 'source_pdf' => 'catalog.pdf',
        'source_sha256' => str_repeat('a', 64), 'source_page' => '42',
        'source_section' => 'TECHNICAL_APPENDIX', 'verification_status' => 'verified_candidate',
    ]],
]);

$state = ['user_id' => $user->id, 'email' => $user->email, 'password' => $password, 'product_id' => $product->id];
file_put_contents($statePath, json_encode($state, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode($state, JSON_THROW_ON_ERROR);
