<?php

use App\Models\AdministrativeUnit;
use App\Models\Brand;
use App\Models\Dealer;
use App\Models\DealerReview;
use App\Models\DealerService;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (config('database.default') !== 'sqlite' || basename((string) config('database.connections.sqlite.database')) !== 'product-data-governance-browser-isolated.sqlite') {
    throw new RuntimeException('Requires isolated browser database');
}
$path = storage_path('framework/testing/dealer-fixture.json');
$mode = $argv[1] ?? 'setup';
if ($mode === 'snapshot') {
    echo json_encode(['dealers' => Dealer::with(['brands', 'categories', 'services'])->get(),
        'reviews' => DealerReview::get(['id', 'status', 'is_visible', 'approved_by', 'moderated_by'])]);
    exit;
}
if ($mode === 'cleanup') {
    if (is_file($path)) {
        unlink($path);
    }echo '{}';
    exit;
}
$unit = function ($scheme, $kind, $code, $parent = null) {
    return AdministrativeUnit::firstOrCreate(['scheme' => $scheme, 'dataset_version' => config('dealers.'.$scheme.'_version'), 'code' => $code],
        ['kind' => $kind, 'parent_id' => $parent?->id, 'name' => $code, 'full_name' => ($kind === 'province' ? 'Tỉnh ' : ($kind === 'district' ? 'Huyện ' : 'Xã ')).$code, 'slug' => $scheme.'-'.$code]);
};
$p = $unit('current', 'province', '01');
$w = $unit('current', 'ward', '00001', $p);
$p2 = $unit('current', 'province', '02');
$w2 = $unit('current', 'ward', '00002', $p2);
$lp = $unit('legacy', 'province', '01');
$ld = $unit('legacy', 'district', '001', $lp);
$lw = $unit('legacy', 'ward', '00001', $ld);
$b = Brand::firstOrCreate(['slug' => 'dealer-test-brand'], ['name' => 'Dealer Test Brand', 'is_active' => true]);
$c = ProductCategory::where('slug', 'dieu-hoa-treo-tuong')->firstOrFail();
$s = DealerService::firstOrCreate(['slug' => 'dealer-install'], ['name' => 'Lắp đặt Dealer', 'is_active' => true]);
if ($mode === 'homepage') {
    Dealer::firstOrCreate(['slug' => 'dealer-featured-homepage'], [
        'name' => 'Dealer Featured Homepage', 'address_line' => '1 Homepage Street', 'province_id' => $p->id,
        'ward_id' => $w->id, 'latitude' => 20.002, 'longitude' => 106.001, 'is_active' => true, 'is_featured' => true,
    ]);
    app(\App\Services\Settings\SettingService::class)->set('homepage_dealers_enabled', true, 'display', type: 'boolean');
    app(\App\Services\Settings\SettingService::class)->set('homepage_dealers_mode', 'auto', 'display');
    app(\App\Services\Settings\SettingService::class)->set('homepage_dealers_limit', 4, 'display', type: 'integer');
    app(\App\Services\Settings\SettingService::class)->set('homepage_dealers_radius_km', 20, 'display', type: 'integer');
    app(\App\Services\Settings\SettingService::class)->set('homepage_section_order', [
        ['section' => 'hero', 'sort_order' => 10], ['section' => 'benefits', 'sort_order' => 20],
        ['section' => 'featured_products', 'sort_order' => 30], ['section' => 'dealers', 'sort_order' => 40],
        ['section' => 'brands', 'sort_order' => 50], ['section' => 'articles', 'sort_order' => 60],
        ['section' => 'faq', 'sort_order' => 70], ['section' => 'cta', 'sort_order' => 80],
    ], 'display', type: 'json');
}
if ($mode === 'homepage') {
    Dealer::firstOrCreate(['slug' => 'dealer-regional-homepage'], [
        'name' => 'Dealer Regional Homepage', 'address_line' => '2 Regional Street', 'province_id' => $p2->id,
        'ward_id' => $w2->id, 'is_active' => true,
    ]);
}
$password = bin2hex(random_bytes(16));
$u = User::updateOrCreate(['email' => 'dealer-module@example.test'], ['name' => 'Dealer Admin', 'password' => Hash::make($password), 'is_active' => true]);
$u->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
foreach ([['name' => 'Dealer Nearby', 'latitude' => 10.01, 'longitude' => 106], ['name' => 'Dealer Far', 'latitude' => 11, 'longitude' => 106], ['name' => 'Dealer No Coordinates']] as $row) {
    Dealer::firstOrCreate(['name' => $row['name']], $row + ['address_line' => '123 Dealer Street', 'province_id' => $p->id, 'ward_id' => $w->id, 'is_active' => true]);
}
$state = ['email' => $u->email, 'password' => $password, 'province' => $p->id, 'ward' => $w->id, 'province2' => $p2->id, 'ward2' => $w2->id,
    'legacy_province' => $lp->id, 'legacy_district' => $ld->id, 'legacy_ward' => $lw->id, 'brand' => $b->id, 'category' => $c->id, 'service' => $s->id, 'user_id' => $u->id];
file_put_contents($path,json_encode($state));
echo json_encode($state);
