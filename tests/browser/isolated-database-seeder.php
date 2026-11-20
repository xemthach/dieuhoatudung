<?php

declare(strict_types=1);

use App\Models\ProductCategory;
use App\Models\Brand;
use App\Models\Product;
use App\Models\Promotion;
use App\Enums\DiscountType;
use App\Support\Catalog\SkyAirTechnicalSchema;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$database = (string) config('database.connections.sqlite.database');
if (config('database.default') !== 'sqlite' || basename($database) !== 'product-data-governance-browser-isolated.sqlite') {
    fwrite(STDERR, "Refusing to seed a non-isolated browser database: ".json_encode([
        'default' => config('database.default'),
        'database' => $database,
    ], JSON_UNESCAPED_SLASHES)."\n");
    exit(2);
}

foreach ([
    'RolePermissionSeeder',
    'AdminUserSeeder',
    'SiteSettingSeeder',
    'MailTemplateSeeder',
    'DemoDataSeeder',
] as $seeder) {
    Artisan::call('db:seed', ['--class' => $seeder, '--force' => true]);
    echo Artisan::output();
}

foreach (SkyAirTechnicalSchema::CATEGORIES as $definition) {
    ProductCategory::query()->firstOrCreate(
        ['name' => $definition['name']],
        ['slug' => Str::slug($definition['name']), 'type' => 'main', 'is_active' => true],
    );
}
foreach (['ProductCategoryTechnicalSchemaSeeder', 'WallMountedProductCategorySeeder', 'SkyAirProductCategorySchemaSeeder'] as $seeder) {
    Artisan::call('db:seed', ['--class' => $seeder, '--force' => true]);
    echo Artisan::output();
}

ProductCategory::query()->updateOrCreate(
    ['slug' => 'browser-navigation-category'],
    ['name' => 'Browser Navigation Category', 'type' => 'main', 'is_active' => true, 'is_indexable' => true],
);

$filterBrand = Brand::query()->updateOrCreate(['slug' => 'filter-test-brand'], ['name' => 'Filter Test Brand', 'is_active' => true]);
$otherBrand = Brand::query()->updateOrCreate(['slug' => 'filter-other-brand'], ['name' => 'Filter Other Brand', 'is_active' => true]);
$eligibleCategory = ProductCategory::query()->updateOrCreate(['slug' => 'dieu-hoa-treo-tuong'], ['name' => 'Điều hòa treo tường', 'type' => 'main', 'is_active' => true]);
$vrfCategory = ProductCategory::query()->updateOrCreate(['slug' => 'dieu-hoa-trung-tam-vrf-gmv'], ['name' => 'Điều hòa trung tâm VRF GMV', 'type' => 'main', 'is_active' => true]);
$base = ['brand_id' => $filterBrand->id, 'product_category_id' => $eligibleCategory->id, 'is_active' => true, 'taxonomy_status' => 'VERIFIED', 'marketing_capacity_status' => 'VERIFIED', 'regular_price' => 20_000_000, 'created_at' => now()->subDay(), 'updated_at' => now()->subDay()];
$fixtureRows = [
    9001 => ['name' => 'Filter Alpha', 'slug' => 'filter-alpha', 'sku' => 'FILTER-ALPHA', 'model_code' => 'FILTER-ALPHA', 'technical_capacity_btu' => 18000, 'inverter' => true, 'sale_price' => 15_000_000, 'promotion_start_at' => now()->subDay(), 'promotion_end_at' => now()->addDay()],
    9002 => ['name' => 'Filter Beta', 'slug' => 'filter-beta', 'sku' => 'FILTER-BETA', 'model_code' => 'FILTER-BETA', 'technical_capacity_btu' => 24000, 'inverter' => false, 'regular_price' => 10_000_000],
    9003 => ['name' => 'Filter Gamma', 'slug' => 'filter-gamma', 'sku' => 'FILTER-GAMMA', 'model_code' => 'FILTER-GAMMA', 'technical_capacity_btu' => 48000, 'inverter' => true, 'regular_price' => 30_000_000],
    9004 => ['name' => 'Filter VRF', 'slug' => 'filter-vrf', 'sku' => 'FILTER-VRF', 'model_code' => 'FILTER-VRF', 'technical_capacity_btu' => 18000, 'product_category_id' => $vrfCategory->id],
    9005 => ['name' => 'Filter Unresolved', 'slug' => 'filter-unresolved', 'sku' => 'FILTER-UNRESOLVED', 'model_code' => 'FILTER-UNRESOLVED', 'technical_capacity_btu' => 18000, 'taxonomy_status' => 'UNRESOLVED'],
    9006 => ['name' => 'Filter Unverified', 'slug' => 'filter-unverified', 'sku' => 'FILTER-UNVERIFIED', 'model_code' => 'FILTER-UNVERIFIED', 'technical_capacity_btu' => 18000, 'marketing_capacity_status' => 'UNVERIFIED'],
    9007 => ['name' => 'Filter Expired Sale', 'slug' => 'filter-expired-sale', 'sku' => 'FILTER-EXPIRED', 'model_code' => 'FILTER-EXPIRED', 'regular_price' => 30_000_000, 'sale_price' => 5_000_000, 'promotion_end_at' => now()->subDay(), 'technical_capacity_btu' => 36000],
];
foreach ($fixtureRows as $id => $attributes) Product::query()->updateOrCreate(['id' => $id], array_merge($base, $attributes));
foreach (range(9010, 9034) as $id) Product::query()->updateOrCreate(['id' => $id], array_merge($base, ['name' => 'Filter Tie '.$id, 'slug' => 'filter-tie-'.$id, 'sku' => 'FILTER-TIE-'.$id, 'model_code' => 'FILTER-TIE-'.$id, 'brand_id' => $otherBrand->id, 'technical_capacity_btu' => 36000, 'regular_price' => 25_000_000]));
foreach ([9050 => [], 9051 => ['technical_capacity_btu' => null], 9052 => ['product_category_id' => $vrfCategory->id],
    9053 => ['is_active' => false]] as $id => $override) {
    Product::query()->updateOrCreate(['id' => $id], array_merge($base, [
        'name' => 'Boundary '.$id, 'slug' => 'boundary-'.$id, 'sku' => 'BOUNDARY-'.$id,
        'brand_id' => $otherBrand->id, 'technical_capacity_btu' => 12000, 'inverter' => true,
        'cooling_type' => '2_chieu',
    ], $override));
}
$searchCandidates = [];
foreach (range(9200, 9404) as $id) {
    $searchCandidates[] = array_merge($base, [
        'id' => $id,
        'brand_id' => $otherBrand->id,
        'product_category_id' => $vrfCategory->id,
        'name' => 'Browser exact needle candidate '.$id,
        'slug' => 'browser-exact-needle-candidate-'.$id,
        'sku' => 'BROWSER-CANDIDATE-'.$id,
        'model_code' => 'BROWSER-CANDIDATE-'.$id,
    ]);
}
Product::query()->upsert($searchCandidates, ['id']);
Product::query()->updateOrCreate(['id' => 9405], array_merge($base, [
    'brand_id' => $otherBrand->id,
    'product_category_id' => $vrfCategory->id,
    'name' => 'Browser exact identity target',
    'slug' => 'browser-exact-identity-target',
    'sku' => 'BROWSER-EXACT-NEEDLE',
    'model_code' => 'MODEL-BEYOND-200',
]));
$promotion = Promotion::query()->updateOrCreate(['slug' => 'filter-scoped-promotion'], ['title' => 'Filter scoped promotion', 'scope' => 'product', 'discount_type' => DiscountType::Percent, 'discount_value' => 20, 'start_at' => now()->subDay(), 'end_at' => now()->addDay(), 'is_active' => true]);
$promotion->products()->syncWithoutDetaching([9002]);
