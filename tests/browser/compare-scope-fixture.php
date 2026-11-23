<?php

declare(strict_types=1);

use App\Models\ProductCategory;
use App\Services\Settings\SettingService;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (config('app.env') !== 'testing' || config('database.default') !== 'sqlite'
    || basename((string) config('database.connections.sqlite.database')) !== 'product-data-governance-browser-isolated.sqlite') {
    throw new RuntimeException('Refusing to modify a non-isolated database.');
}

$settings = app(SettingService::class);
if (($argv[1] ?? '') === 'enable') {
    $category = ProductCategory::query()->where('slug', 'browser-navigation-category')->firstOrFail();
    $settings->set('product_scope', 'primary_category', 'site');
    $settings->set('primary_product_category_id', (string) $category->id, 'site');
    echo json_encode(['name' => $category->name, 'url' => route('category.show', $category->slug)], JSON_THROW_ON_ERROR);
} else {
    $settings->set('product_scope', 'all_products', 'site');
    $settings->set('primary_product_category_id', '', 'site');
}
