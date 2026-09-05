<?php

declare(strict_types=1);

use App\Models\Brand;
use App\Models\DataExportJob;
use App\Models\DataImportJob;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Services\DataTransfer\DataExportService;
use App\Services\DataTransfer\DataImportService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$statePath = storage_path('framework/testing/product-transfer-browser-fixture.json');
$mode = $argv[1] ?? 'setup';

if ($mode === 'snapshot') {
    $state = json_decode((string) file_get_contents($statePath), true, 512, JSON_THROW_ON_ERROR);
    $job = DataImportJob::find($state['import_job_id']);
    $product = Product::where('sku', $state['sku'])->first();
    echo json_encode([
        'status' => $job?->status,
        'created' => $job?->created_rows,
        'product_id' => $product?->id,
        'brand_id' => $product?->brand_id,
        'category_id' => $product?->product_category_id,
        'marketing' => $product?->marketing_capacity_btu,
        'technical' => $product?->technical_capacity_btu,
        'kw' => $product?->capacity_kw,
        'provenance' => $product?->technical_specs_source,
    ], JSON_THROW_ON_ERROR);
    exit;
}

if ($mode === 'cleanup') {
    $state = is_file($statePath) ? json_decode((string) file_get_contents($statePath), true) : [];
    Product::where('sku', $state['sku'] ?? '__none__')->forceDelete();
    DataImportJob::whereKey($state['import_job_id'] ?? 0)->delete();
    DataExportJob::whereKey($state['export_job_id'] ?? 0)->delete();
    Brand::withTrashed()->where('slug', $state['brand_slug'] ?? '__none__')->forceDelete();
    ProductCategory::withTrashed()->where('slug', $state['category_slug'] ?? '__none__')->forceDelete();
    User::whereKey($state['user_id'] ?? 0)->delete();
    foreach (['import_file', 'export_file'] as $key) if (! empty($state[$key])) Storage::disk('local')->delete($state[$key]);
    @unlink($statePath);
    echo json_encode(['cleaned' => true], JSON_THROW_ON_ERROR);
    exit;
}

$token = bin2hex(random_bytes(5));
$brandSlug = 'browser-transfer-brand-'.$token;
$categorySlug = 'browser-transfer-category-'.$token;
$brand = Brand::factory()->create(['slug' => $brandSlug]);
$category = ProductCategory::factory()->create(['slug' => $categorySlug, 'technical_schema_status' => 'missing']);
$sku = 'BROWSER-TRANSFER-'.strtoupper($token);
$source = Product::factory()->create([
    'brand_id' => $brand->id, 'product_category_id' => $category->id,
    'sku' => $sku, 'slug' => strtolower($sku),
    'marketing_capacity_btu' => 18000, 'technical_capacity_btu' => 17100, 'capacity_kw' => '5.00',
]);
$export = app(DataExportService::class)->export('product', 'xlsx', [], [], [$source->id], 'selected', null, 'product_transfer');
$path = storage_path('app/private/'.$export->file_path);
$source->forceDelete();
$brand->forceDelete();
$category->forceDelete();
$targetBrand = Brand::factory()->create(['slug' => $brandSlug]);
$targetCategory = ProductCategory::factory()->create(['slug' => $categorySlug, 'technical_schema_status' => 'missing']);

$role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
$password = bin2hex(random_bytes(18));
$user = User::create(['name' => 'Product Transfer Browser Certification', 'email' => 'transfer-'.$token.'@example.test', 'password' => Hash::make($password), 'is_active' => true]);
$user->assignRole($role);
$import = app(DataImportService::class)->uploadAndPreview('product', $path, 'product-transfer-browser.xlsx', 'xlsx', userId: $user->id);

$state = [
    'user_id' => $user->id, 'email' => $user->email, 'password' => $password,
    'sku' => $sku, 'brand_slug' => $brandSlug, 'category_slug' => $categorySlug,
    'target_brand_id' => $targetBrand->id, 'target_category_id' => $targetCategory->id,
    'export_job_id' => $export->id, 'export_file' => $export->file_path,
    'import_job_id' => $import->id, 'import_file' => $import->file_path,
    'contract' => data_get($import->format_context_json, 'contract'),
    'valid' => $import->success_rows, 'errors' => $import->failed_rows,
    'brand_remapped' => data_get($import->format_context_json, 'preview_summary.brand_mapping.remapped'),
    'category_remapped' => data_get($import->format_context_json, 'preview_summary.category_mapping.remapped'),
];
file_put_contents($statePath, json_encode($state, JSON_THROW_ON_ERROR));
echo json_encode($state, JSON_THROW_ON_ERROR);
