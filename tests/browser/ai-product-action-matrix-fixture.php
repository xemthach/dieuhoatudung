<?php

declare(strict_types=1);

use App\Models\AiProductDraft;
use App\Models\AiProductJob;
use App\Models\AiProductJobItem;
use App\Models\Product;
use App\Models\User;
use App\Services\Product\AIProductContentSystem;
use App\Services\Product\AIProductDraftApplyService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$mode = $argv[1] ?? 'setup';
$key = $argv[2] ?? null;
$statePath = storage_path('framework/testing/ai-product-action-matrix.json');
$state = is_file($statePath) ? json_decode((string) file_get_contents($statePath), true) : [];

$contentHash = static fn (Product $product): string => app(AIProductDraftApplyService::class)->contentHash($product);

if ($mode === 'cleanup') {
    $itemIds = array_values(array_filter(array_map('intval', (array) ($state['item_ids'] ?? []))));
    $draftIds = array_values(array_filter(array_map('intval', (array) ($state['draft_ids'] ?? []))));
    $jobIds = array_values(array_filter(array_map('intval', (array) ($state['job_ids'] ?? []))));
    $productIds = array_values(array_filter(array_map('intval', (array) ($state['product_ids'] ?? []))));
    $userIds = array_values(array_filter(array_map('intval', (array) ($state['user_ids'] ?? []))));
    $jobIds = array_values(array_unique(array_merge(
        $jobIds,
        AiProductDraft::whereIn('product_id', $productIds)->pluck('job_id')->filter()->map(fn ($id) => (int) $id)->all(),
        AiProductJobItem::whereIn('product_id', $productIds)->pluck('ai_product_job_id')->filter()->map(fn ($id) => (int) $id)->all(),
    )));
    $orphanQueueIds = DB::table('jobs')->where('queue', config('ai.governed_queue', 'ai_governed'))->get()
        ->filter(function ($queued) use ($jobIds, $productIds): bool {
            $payload = json_decode((string) $queued->payload, true);
            $command = @unserialize((string) data_get($payload, 'data.command'));

            return $command instanceof \App\Jobs\AiProductContentSingleJob
                && (in_array((int) $command->aiProductJobId, $jobIds, true)
                    || in_array((int) $command->productId, $productIds, true));
        })
        ->pluck('id')
        ->all();
    if ($orphanQueueIds !== []) {
        DB::table('jobs')->whereIn('id', $orphanQueueIds)->delete();
    }
    $draftIds = array_values(array_unique(array_merge(
        $draftIds,
        AiProductDraft::whereIn('product_id', $productIds)->pluck('id')->map(fn ($id) => (int) $id)->all(),
    )));
    $itemIds = array_values(array_unique(array_merge(
        $itemIds,
        AiProductJobItem::whereIn('product_id', $productIds)->pluck('id')->map(fn ($id) => (int) $id)->all(),
    )));
    AiProductJobItem::whereIn('id', $itemIds)->update(['draft_id' => null]);
    AiProductDraft::whereIn('id', $draftIds)->delete();
    AiProductJobItem::whereIn('id', $itemIds)->delete();
    AiProductJob::whereIn('id', $jobIds)->delete();
    Product::whereIn('id', $productIds)->forceDelete();
    User::whereIn('id', $userIds)->delete();
    @unlink($statePath);
    echo json_encode(['cleaned' => true], JSON_THROW_ON_ERROR);
    exit;
}

if ($mode === 'snapshot') {
    $row = (array) data_get($state, "products.{$key}", []);
    $product = Product::withTrashed()->findOrFail((int) $row['product_id']);
    $draft = isset($row['draft_id']) ? AiProductDraft::find((int) $row['draft_id']) : null;
    $item = isset($row['item_id']) ? AiProductJobItem::find((int) $row['item_id']) : null;
    $latestItem = AiProductJobItem::where('product_id', $product->id)->latest('id')->first();
    echo json_encode([
        'product_id' => $product->id,
        'product_count' => Product::count(),
        'content_hash' => $contentHash($product),
        'long_description' => $product->long_description,
        'seo_title' => $product->seo_title,
        'seo_description' => $product->seo_description,
        'merchant_title' => $product->merchant_title,
        'merchant_description' => $product->merchant_description,
        'faq_count' => $product->faqs()->count(),
        'draft_id' => $draft?->id,
        'draft_status' => $draft?->status,
        'approval_status' => $draft?->approval_status,
        'approved_by' => $draft?->approved_by,
        'approved_at' => $draft?->approved_at?->toIso8601String(),
        'warning_override' => $draft?->warning_override,
        'warnings_at_approval' => $draft?->warnings_at_approval,
        'rejected_by' => $draft?->rejected_by,
        'rejected_at' => $draft?->rejected_at?->toIso8601String(),
        'discarded_by' => $draft?->discarded_by,
        'discarded_at' => $draft?->discarded_at?->toIso8601String(),
        'applied_by' => $draft?->applied_by,
        'applied_at' => $draft?->applied_at?->toIso8601String(),
        'review_note' => $draft?->review_note,
        'item_status' => $item?->status,
        'job_count' => AiProductJob::whereIn('id', (array) ($state['job_ids'] ?? []))->count(),
        'draft_count' => AiProductDraft::whereIn('id', (array) ($state['draft_ids'] ?? []))->count(),
        'latest_job_id' => $latestItem?->ai_product_job_id,
        'latest_item_id' => $latestItem?->id,
        'latest_item_status' => $latestItem?->status,
    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

if ($mode !== 'setup') throw new RuntimeException('Unknown mode');

// Recover only leftovers created by this fixture if a prior setup was interrupted.
$leftoverProductIds = Product::withTrashed()->where('name', 'like', '[AI MATRIX] %')->pluck('id')->map(fn ($id) => (int) $id)->all();
if ($leftoverProductIds !== []) {
    $leftoverJobIds = array_values(array_unique(array_merge(
        AiProductDraft::whereIn('product_id', $leftoverProductIds)->pluck('job_id')->filter()->map(fn ($id) => (int) $id)->all(),
        AiProductJobItem::whereIn('product_id', $leftoverProductIds)->pluck('ai_product_job_id')->filter()->map(fn ($id) => (int) $id)->all(),
    )));
    AiProductJobItem::whereIn('product_id', $leftoverProductIds)->update(['draft_id' => null]);
    AiProductDraft::whereIn('product_id', $leftoverProductIds)->delete();
    AiProductJobItem::whereIn('product_id', $leftoverProductIds)->delete();
    AiProductJob::whereIn('id', $leftoverJobIds)->delete();
    Product::withTrashed()->whereIn('id', $leftoverProductIds)->forceDelete();
}
User::where('email', 'like', 'ai-matrix-%@example.test')->delete();

$source = Product::query()->whereNotNull('brand_id')->firstOrFail();
$operator = User::findOrFail(1);
$operatorPassword = (string) env('ADMIN_PASSWORD', 'ChangeMe!2024');
if (! $operator->is_active || ! Hash::check($operatorPassword, (string) $operator->password)) {
    throw new RuntimeException('CONFIGURED_OPERATOR_LOGIN_UNAVAILABLE');
}
$password = 'BrowserMatrix!'.Str::random(12);
$permissions = ['product.view', 'product.edit', 'product.ai_generate', 'bulk_ai_approve', 'bulk_ai_apply', 'bulk_ai_view', 'bulk_ai_view_all'];
foreach ($permissions as $permission) Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);

$actors = [
    'full' => $permissions,
    'full_panel' => $permissions,
    'generate' => ['product.view', 'product.edit', 'product.ai_generate'],
    'approve' => ['product.view', 'product.edit', 'bulk_ai_approve'],
    'apply' => ['product.view', 'product.edit', 'bulk_ai_apply'],
    'none' => ['product.view', 'product.edit'],
];
$users = [];
foreach ($actors as $actor => $grants) {
    $user = User::create([
        'name' => 'AI Matrix '.ucfirst($actor),
        'email' => 'ai-matrix-'.$actor.'-'.Str::lower(Str::random(8)).'@example.test',
        'password' => Hash::make($password),
        'is_active' => true,
    ]);
    $user->givePermissionTo($grants);
    $users[$actor] = ['id' => $user->id, 'email' => $user->email, 'password' => $password];
}

$productKeys = ['preview', 'approve', 'warning', 'warning_responsive', 'reject', 'discard', 'apply', 'apply_rbac', 'stale', 'duplicate', 'regenerate', 'blocked', 'no_draft', 'processing', 'failed', 'applied'];
$products = [];
$jobIds = [];
$itemIds = [];
$draftIds = [];

$makeProduct = static function (string $label) use ($source): Product {
    $product = $source->replicate();
    $token = Str::lower(Str::random(10));
    $product->name = "[AI MATRIX] {$label} {$token}";
    $product->slug = "ai-matrix-{$label}-{$token}";
    $product->sku = 'AIM-'.Str::upper(Str::random(12));
    $product->model_code = 'AIM-'.Str::upper(Str::random(10));
    $product->is_active = false;
    $product->ai_status = 'not_generated';
    $product->short_description = 'Nội dung cũ '.$label;
    $product->long_description = '<p>Nội dung Product trước khi Apply.</p>';
    $product->save();
    if (method_exists($source, 'categories')) {
        $product->categories()->sync($source->categories()->pluck('product_categories.id')->all());
    }
    return $product->refresh();
};

$makeDraft = static function (Product $product, array $warnings = [], bool $blocked = false) use (&$jobIds, &$itemIds, &$draftIds): array {
    $payload = [
        'excerpt' => 'Bản nháp AI cô lập để chứng nhận thao tác quản trị.',
        'content_html' => '<h2>Nội dung được tạo</h2><h3>Thông tin vận hành</h3><p>Bản nháp chỉ dùng trong kiểm thử browser và không tự ghi vào Product.</p>',
        'seo_title' => 'AI Matrix SEO title',
        'meta_description' => 'AI Matrix meta description.',
        'merchant_title' => 'AI Matrix Merchant title',
        'merchant_description' => 'AI Matrix Merchant description.',
        'og_title' => 'AI Matrix OG title',
        'og_description' => 'AI Matrix OG description.',
        'faq' => [['question' => 'Đây là dữ liệu gì?', 'answer' => 'Dữ liệu fixture kiểm thử cô lập.']],
        'blocked_claims' => $blocked ? ['CONTRADICTED'] : [],
        'fact_check' => ['status' => $blocked ? 'blocked' : 'passed', 'blocked_claims' => $blocked ? ['CONTRADICTED'] : []],
    ];
    $job = AiProductJob::create([
        'type' => 'single_product_preview', 'scope' => 'selected', 'status' => 'completed', 'total' => 1,
        'processed' => 1, 'needs_review' => $blocked ? 0 : 1, 'failed' => $blocked ? 1 : 0,
        'config_json' => ['operation_generation' => (string) Str::uuid()],
    ]);
    $draft = AiProductDraft::create([
        'job_id' => $job->id, 'product_id' => $product->id, 'module' => 'ai_product',
        'raw_output_json' => $payload, 'normalized_output_json' => $payload,
        'field_status_json' => array_fill_keys(['content_html', 'seo_title', 'meta_description', 'merchant_title', 'merchant_description', 'faq', 'og_title', 'og_description'], 'GENERATED'),
        'validation_errors_json' => $blocked ? ['FACT_CHECK_BLOCKED'] : [],
        'warnings_json' => $warnings,
        'token_usage_json' => ['technical_context_hash' => app(AIProductContentSystem::class)->technicalContextHash($product), 'total_tokens' => 321],
        'status' => $blocked ? 'blocked' : 'needs_review',
        'approval_status' => 'REVIEW_REQUIRED',
    ]);
    $item = AiProductJobItem::create([
        'ai_product_job_id' => $job->id, 'product_id' => $product->id,
        'status' => $blocked ? 'blocked' : 'needs_review',
        'canonical_status' => $blocked ? 'BLOCKED' : 'REVIEW_REQUIRED',
        'status_reason' => $blocked ? 'FACT_CHECK_BLOCKED' : 'REVIEW_REQUIRED',
        'failed_reason' => $blocked ? 'fact_check_blocked' : null,
        'last_error_code' => $blocked ? 'fact_check_blocked' : null,
        'last_error_message' => $blocked ? 'FACT_CHECK_BLOCKED' : null,
        'error_message' => $blocked ? 'FACT_CHECK_BLOCKED' : null,
        'generated_payload_json' => $payload, 'field_status_json' => $draft->field_status_json,
        'warnings_json' => $warnings, 'draft_id' => $draft->id, 'tokens_used' => 321,
    ]);
    $product->forceFill(['ai_status' => $blocked ? 'blocked' : 'needs_review'])->save();
    $jobIds[] = $job->id; $itemIds[] = $item->id; $draftIds[] = $draft->id;
    return [$draft->refresh(), $item->refresh(), $job->refresh()];
};

foreach ($productKeys as $productKey) {
    $product = $makeProduct($productKey);
    $products[$productKey] = [
        'product_id' => $product->id,
        'name' => $product->name,
        'model_code' => $product->model_code,
        'apply_confirmation' => 'APPLY '.($product->model_code ?: 'UNKNOWN').'#'.$product->id,
        'content_hash_before' => $contentHash($product),
    ];
}

foreach (['preview', 'approve', 'reject', 'discard', 'apply', 'apply_rbac', 'stale', 'duplicate', 'regenerate'] as $productKey) {
    [$draft, $item, $job] = $makeDraft(Product::findOrFail($products[$productKey]['product_id']));
    $products[$productKey] += ['draft_id' => $draft->id, 'item_id' => $item->id, 'job_id' => $job->id];
}
[$warningDraft, $warningItem, $warningJob] = $makeDraft(Product::findOrFail($products['warning']['product_id']), ['content_too_short:459/800', 'missing_h2_h3']);
$products['warning'] += ['draft_id' => $warningDraft->id, 'item_id' => $warningItem->id, 'job_id' => $warningJob->id];
[$responsiveDraft, $responsiveItem, $responsiveJob] = $makeDraft(Product::findOrFail($products['warning_responsive']['product_id']), ['content_too_short:459/800']);
$products['warning_responsive'] += ['draft_id' => $responsiveDraft->id, 'item_id' => $responsiveItem->id, 'job_id' => $responsiveJob->id];
[$blockedDraft, $blockedItem, $blockedJob] = $makeDraft(Product::findOrFail($products['blocked']['product_id']), [], true);
$products['blocked'] += ['draft_id' => $blockedDraft->id, 'item_id' => $blockedItem->id, 'job_id' => $blockedJob->id];

foreach (['apply', 'apply_rbac', 'stale'] as $productKey) {
    $draft = AiProductDraft::findOrFail($products[$productKey]['draft_id']);
    app(AIProductDraftApplyService::class)->approve($draft, $operator->id, $operator);
}
Product::findOrFail($products['stale']['product_id'])->forceFill(['long_description' => '<p>Biên tập thủ công sau khi draft được duyệt.</p>'])->save();

[$processingDraft, $processingItem, $processingJob] = $makeDraft(Product::findOrFail($products['processing']['product_id']));
$processingDraft->delete();
$processingItem->update(['draft_id' => null, 'status' => 'processing', 'canonical_status' => 'RUNNING']);
$processingJob->update(['status' => 'processing']);
$products['processing'] += ['item_id' => $processingItem->id, 'job_id' => $processingJob->id];
$draftIds = array_values(array_diff($draftIds, [$processingDraft->id]));

[$failedDraft, $failedItem, $failedJob] = $makeDraft(Product::findOrFail($products['failed']['product_id']));
$failedDraft->update(['status' => 'failed', 'approval_status' => 'REVIEW_REQUIRED']);
$failedItem->update(['status' => 'failed', 'canonical_status' => 'FAILED', 'status_reason' => 'CONTENT_TOO_SHORT']);
$products['failed'] += ['draft_id' => $failedDraft->id, 'item_id' => $failedItem->id, 'job_id' => $failedJob->id];

[$appliedDraft, $appliedItem, $appliedJob] = $makeDraft(Product::findOrFail($products['applied']['product_id']));
app(AIProductDraftApplyService::class)->approve($appliedDraft, $operator->id, $operator);
$appliedProduct = Product::findOrFail($products['applied']['product_id']);
$appliedConfirmation = app(\App\Services\AI\SingleOperatorControlledRolloutPolicy::class)
    ->expectedApplyConfirmation(($appliedProduct->model_code ?: 'UNKNOWN').'#'.$appliedProduct->id);
app(AIProductDraftApplyService::class)->apply($appliedDraft->refresh(), $operator->id, false, $appliedConfirmation);
$products['applied'] += ['draft_id' => $appliedDraft->id, 'item_id' => $appliedItem->id, 'job_id' => $appliedJob->id];

$state = [
    'operator' => ['id' => $operator->id, 'email' => $operator->email, 'password' => $operatorPassword],
    'users' => $users,
    'products' => $products,
    'user_ids' => array_column($users, 'id'),
    'product_ids' => array_column($products, 'product_id'),
    'job_ids' => array_values(array_unique($jobIds)),
    'item_ids' => array_values(array_unique($itemIds)),
    'draft_ids' => array_values(array_unique($draftIds)),
    'product_count_before' => Product::count() - count($products),
];
file_put_contents($statePath, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
echo json_encode($state, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
