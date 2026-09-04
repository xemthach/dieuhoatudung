<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Support\Catalog\SkyAirTechnicalSchema;
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
    Product::query()->whereKey($state['three_phase_product_id'] ?? 0)->forceDelete();
    Product::query()->whereKey($state['wall_product_id'] ?? 0)->forceDelete();
    ProductCategory::query()->whereKey($state['category_id'] ?? 0)->forceDelete();
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
        'phase' => $product->technical_phase,
        'frequency' => $product->technical_frequency,
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
$category = ProductCategory::factory()->create([
    'name' => 'SkyAir browser technical fixture '.bin2hex(random_bytes(4)),
    'technical_schema_status' => 'active',
    'technical_schema_version' => SkyAirTechnicalSchema::version('cassette'),
    'technical_schema_json' => SkyAirTechnicalSchema::schema('cassette'),
]);
$product = Product::factory()->create([
    'name' => 'Technical edit browser fixture',
    'btu' => null,
    'marketing_capacity_btu' => 12000,
    'technical_capacity_btu' => 12300,
    'technical_capacity_status' => 'verified_candidate',
    'capacity_kw' => 3.6,
    'hp' => 1.5,
    'voltage' => '230V',
    'product_category_id' => $category->id,
    'model_code' => 'FCTF50AVM/RZF50DVM',
    'technical_specs_source' => 'catalog_verified_specs',
    'specs_json' => [
        ['key' => 'capacity_kw', 'value' => '3.6', 'source_pdf' => 'catalog.pdf',
            'source_sha256' => str_repeat('a', 64), 'source_page' => '42',
            'source_section' => 'TECHNICAL_APPENDIX', 'verification_status' => 'verified_candidate'],
        ['key' => 'phase', 'value' => '1', 'source_pdf' => 'skyair.pdf',
            'source_sha256' => str_repeat('b', 64), 'source_page' => '55',
            'source_section' => 'TECHNICAL_APPENDIX', 'verification_status' => 'verified_candidate'],
        ['key' => 'frequency', 'value' => '50 / 60Hz', 'source_pdf' => 'skyair.pdf',
            'source_sha256' => str_repeat('b', 64), 'source_page' => '55',
            'source_section' => 'TECHNICAL_APPENDIX', 'verification_status' => 'verified_candidate'],
        ['key' => 'remote_model', 'value' => 'BRC1H63W', 'source_pdf' => 'skyair.pdf',
            'source_sha256' => str_repeat('b', 64), 'source_page' => '55',
            'source_section' => 'TECHNICAL_APPENDIX', 'verification_status' => 'verified_candidate'],
        ['key' => 'panel_model', 'value' => 'BYCQ125EAF8', 'source_pdf' => 'skyair.pdf',
            'source_sha256' => str_repeat('b', 64), 'source_page' => '55',
            'source_section' => 'TECHNICAL_APPENDIX', 'verification_status' => 'verified_candidate'],
    ],
]);
$threePhaseProduct = Product::factory()->create([
    'name' => 'SkyAir 3P browser fixture',
    'is_active' => true,
    'product_category_id' => $category->id,
    'model_code' => 'FCFG140AV1V/RZFC140AY19',
    'technical_capacity_btu' => 48000,
    'capacity_kw' => 14.07,
    'specs_json' => [
        ['key' => 'phase', 'value' => '3', 'source_pdf' => 'skyair.pdf',
            'source_sha256' => str_repeat('c', 64), 'source_page' => '61',
            'source_section' => 'TECHNICAL_APPENDIX', 'verification_status' => 'verified_candidate'],
        ['key' => 'frequency', 'value' => '50Hz', 'source_pdf' => 'skyair.pdf',
            'source_sha256' => str_repeat('c', 64), 'source_page' => '61',
            'source_section' => 'TECHNICAL_APPENDIX', 'verification_status' => 'verified_candidate'],
    ],
    'voltage' => '380-415V',
]);
$wallProduct = Product::factory()->create([
    'name' => 'Wall mounted RAC browser fixture',
    'is_active' => true,
    'model_code' => 'FTKB35XVMV/RKB35XVMV',
    'specs_json' => [
        ['key' => 'remote_model', 'value' => 'ARC486A33', 'source_pdf' => 'rac.pdf',
            'source_sha256' => str_repeat('d', 64), 'source_page' => '1',
            'source_section' => 'TECHNICAL_APPENDIX', 'verification_status' => 'verified_candidate'],
    ],
]);

$state = [
    'user_id' => $user->id, 'email' => $user->email, 'password' => $password,
    'product_id' => $product->id, 'slug' => $product->slug,
    'three_phase_product_id' => $threePhaseProduct->id, 'three_phase_slug' => $threePhaseProduct->slug,
    'wall_product_id' => $wallProduct->id, 'wall_slug' => $wallProduct->slug,
    'category_id' => $category->id,
];
file_put_contents($statePath, json_encode($state, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode($state, JSON_THROW_ON_ERROR);
