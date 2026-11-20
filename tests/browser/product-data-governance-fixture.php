<?php

declare(strict_types=1);

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductDataGovernanceEvent;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$database = (string) config('database.connections.sqlite.database');
if (config('database.default') !== 'sqlite' || ! str_contains(basename($database), 'product-data-governance-browser-')) {
    fwrite(STDERR, "Refusing to mutate a non-isolated browser database.\n");
    exit(2);
}

$statePath = storage_path('framework/testing/product-data-governance-browser-fixture.json');
$mode = $argv[1] ?? 'setup';

if ($mode === 'snapshot') {
    $state = json_decode((string) file_get_contents($statePath), true, 512, JSON_THROW_ON_ERROR);
    echo json_encode([
        'events' => ProductDataGovernanceEvent::where('operator_id', $state['user_id'])->count(),
        'taxonomy_approvals' => ProductDataGovernanceEvent::where('operator_id', $state['user_id'])->where('domain', 'TAXONOMY')->count(),
        'marketing_approvals' => ProductDataGovernanceEvent::where('operator_id', $state['user_id'])->where('domain', 'MARKETING_CAPACITY')->count(),
    ], JSON_THROW_ON_ERROR);
    exit;
}

if ($mode === 'cleanup') {
    @unlink($statePath);
    echo json_encode(['cleaned' => true], JSON_THROW_ON_ERROR);
    exit;
}

$role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
$password = bin2hex(random_bytes(18));
$user = User::factory()->create([
    'name' => 'Product Data Governance Browser Certification',
    'email' => 'product-data-governance-browser@example.test',
    'password' => Hash::make($password),
    'is_active' => true,
]);
$user->assignRole($role);

$brand = Brand::query()->firstOrCreate(['slug' => 'daikin'], ['name' => 'Daikin', 'is_active' => true]);
$wall = ProductCategory::query()->firstOrCreate(['slug' => 'dieu-hoa-treo-tuong'], ['name' => 'Wall Browser', 'is_active' => true, 'is_indexable' => true]);
$cassette = ProductCategory::query()->firstOrCreate(['slug' => 'dieu-hoa-am-tran-cassette'], ['name' => 'Cassette Browser', 'is_active' => true, 'is_indexable' => true]);
$vrf = ProductCategory::query()->firstOrCreate(['slug' => 'dieu-hoa-trung-tam-vrf-gmv'], ['name' => 'VRF Browser', 'is_active' => true, 'is_indexable' => true]);

$base = [
    'brand_id' => $brand->id,
    'is_active' => true,
    'stock_status' => 'in_stock',
    'regular_price' => 10000000,
];
$verified = Product::factory()->create($base + [
    'id' => 900001, 'name' => 'Browser Verified Wall 18000', 'slug' => 'browser-verified-wall-18000',
    'sku' => 'BROWSER-VERIFIED-WALL-18000', 'model_code' => 'BROWSER-VERIFIED-WALL-18000',
    'product_category_id' => $wall->id, 'marketing_capacity_btu' => 18000, 'technical_capacity_btu' => 18000,
    'taxonomy_status' => 'VERIFIED', 'marketing_capacity_status' => 'VERIFIED',
]);
$unverified = Product::factory()->create($base + [
    'id' => 900002, 'name' => 'Browser Unverified Capacity 18000', 'slug' => 'browser-unverified-capacity-18000',
    'sku' => 'BROWSER-UNVERIFIED-18000', 'model_code' => 'BROWSER-UNVERIFIED-18000',
    'product_category_id' => $wall->id, 'marketing_capacity_btu' => 18000, 'technical_capacity_btu' => 18000,
    'taxonomy_status' => 'VERIFIED', 'marketing_capacity_status' => 'UNVERIFIED',
]);
$unresolved = Product::factory()->create($base + [
    'id' => 900003, 'name' => 'Browser Unresolved Taxonomy 18000', 'slug' => 'browser-unresolved-taxonomy-18000',
    'sku' => 'BROWSER-UNRESOLVED-18000', 'model_code' => 'BROWSER-UNRESOLVED-18000',
    'product_category_id' => $wall->id, 'marketing_capacity_btu' => 18000, 'technical_capacity_btu' => 18000,
    'taxonomy_status' => 'UNRESOLVED', 'marketing_capacity_status' => 'VERIFIED',
]);
$vrfProduct = Product::factory()->create($base + [
    'id' => 900004, 'name' => 'Browser VRF Must Not Leak 18000', 'slug' => 'browser-vrf-no-leak-18000',
    'sku' => 'BROWSER-VRF-18000', 'model_code' => 'BROWSER-VRF-18000',
    'product_category_id' => $vrf->id, 'marketing_capacity_btu' => 18000, 'technical_capacity_btu' => 18000,
    'taxonomy_status' => 'VERIFIED', 'marketing_capacity_status' => 'VERIFIED',
]);
$cassetteProduct = Product::factory()->create($base + [
    'id' => 900005, 'name' => 'Browser Verified SkyAir Cassette 24000', 'slug' => 'browser-skyair-cassette-24000',
    'sku' => 'FCTF50AVM-RZF50DVM', 'model_code' => 'FCTF50AVM/RZF50DVM',
    'product_category_id' => $cassette->id, 'marketing_capacity_btu' => 24000,
    'technical_capacity_btu' => 24000, 'capacity_kw' => 5.0,
    'taxonomy_status' => 'VERIFIED', 'marketing_capacity_status' => 'VERIFIED',
]);

$state = [
    'user_id' => $user->id,
    'email' => $user->email,
    'password' => $password,
    'verified_id' => $verified->id,
    'unverified_id' => $unverified->id,
    'unresolved_id' => $unresolved->id,
    'vrf_id' => $vrfProduct->id,
    'cassette_id' => $cassetteProduct->id,
];
file_put_contents($statePath, json_encode($state, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode($state, JSON_THROW_ON_ERROR);
