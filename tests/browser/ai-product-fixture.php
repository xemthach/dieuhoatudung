<?php

declare(strict_types=1);

use App\Models\AiProductJobItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$mode = $argv[1] ?? 'setup';
$productId = (int) ($argv[2] ?? 1319);
$statePath = storage_path('framework/testing/ai-product-browser-fixture.json');

if ($mode === 'cleanup') {
    $state = is_file($statePath) ? json_decode((string) file_get_contents($statePath), true) : [];
    User::query()->whereKey((int) ($state['user_id'] ?? 0))->delete();
    @unlink($statePath);
    echo json_encode(['cleaned' => true], JSON_THROW_ON_ERROR);
    exit;
}

$product = Product::findOrFail($productId);
$contentHash = static fn (Product $row): string => hash('sha256', json_encode([
    $row->short_description,
    $row->long_description,
    $row->seo_title,
    $row->seo_description,
    $row->og_title,
    $row->og_description,
    $row->merchant_title,
    $row->merchant_description,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

if ($mode === 'snapshot') {
    $item = AiProductJobItem::query()->with(['job', 'draft'])->where('product_id', $productId)->latest('id')->first();
    echo json_encode([
        'product_id' => $productId,
        'product_count' => Product::count(),
        'product_content_hash' => $contentHash($product->refresh()),
        'job_id' => $item?->ai_product_job_id,
        'item_id' => $item?->id,
        'item_status' => $item?->status,
        'canonical_status' => $item?->canonical_status,
        'provider' => $item?->provider,
        'model' => $item?->model,
        'tokens' => $item?->tokens_used,
        'warnings' => $item?->warnings_json,
        'field_status' => $item?->field_status_json,
        'draft_id' => $item?->draft_id,
        'draft_status' => $item?->draft?->status,
        'draft_content_length' => mb_strlen(strip_tags((string) data_get($item?->draft?->normalized_output_json, 'content_html', ''))),
    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

$role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
$password = bin2hex(random_bytes(18));
$user = User::create([
    'name' => 'AI Product Browser Certification',
    'email' => 'ai-product-browser-'.bin2hex(random_bytes(6)).'@example.test',
    'password' => Hash::make($password),
    'is_active' => true,
]);
$user->assignRole($role);
$state = [
    'user_id' => $user->id,
    'email' => $user->email,
    'password' => $password,
    'product_id' => $productId,
    'product_count_before' => Product::count(),
    'product_content_hash_before' => $contentHash($product),
    'latest_item_before' => AiProductJobItem::where('product_id', $productId)->max('id'),
];
file_put_contents($statePath, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
echo json_encode($state, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
