<?php

declare(strict_types=1);

use App\Models\AiProductJobItem;
use App\Models\AiRequestLog;
use App\Models\Product;
use App\Models\User;
use App\Services\Product\AIProductDraftApplyService;
use App\Services\Product\ProductTechnicalFactResolver;
use App\Services\AI\Governance\VerifiedFactRegistry;
use App\Services\AI\AIContentGovernance;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$mode = $argv[1] ?? 'setup';
$statePath = storage_path('framework/testing/ai-guard-real-provider.json');
$state = is_file($statePath) ? json_decode((string) file_get_contents($statePath), true) : [];

if ($mode === 'revalidate') {
    $product = Product::findOrFail((int) $state['product_id']);
    $item = AiProductJobItem::with('draft')->where('product_id', $product->id)->latest('id')->firstOrFail();
    $content = (string) data_get($item->draft?->normalized_output_json, 'content_html', '');
    $governance = app(AIContentGovernance::class);
    $result = $governance->validateText($content, $governance->buildProductContext($product));
    echo json_encode([
        'product_id' => $product->id,
        'item_id' => $item->id,
        'draft_id' => $item->draft_id,
        'content_length' => mb_strlen(trim(strip_tags($content))),
        'status' => $result['status'],
        'blocked_claims' => $result['blocked_claims'],
        'warnings' => $result['warnings'],
        'used_facts' => $result['used_facts'],
    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

if ($mode === 'snapshot') {
    $product = Product::findOrFail((int) $state['product_id']);
    $item = AiProductJobItem::with(['job', 'draft'])->where('product_id', $product->id)->latest('id')->first();
    $request = $item?->job ? AiRequestLog::where('context_id', "ai-product-{$product->id}-{$item->job->id}")->latest('id')->first() : null;
    echo json_encode([
        'product_id' => $product->id,
        'product_count' => Product::count(),
        'content_hash' => app(AIProductDraftApplyService::class)->contentHash($product),
        'job_id' => $item?->ai_product_job_id,
        'item_id' => $item?->id,
        'status' => $item?->status,
        'canonical_status' => $item?->canonical_status,
        'reason' => $item?->status_reason ?: $item?->last_error_code,
        'draft_id' => $item?->draft_id,
        'draft_status' => $item?->draft?->status,
        'warnings' => $item?->warnings_json ?? [],
        'validation_errors' => $item?->validation_errors ?? [],
        'field_status' => $item?->field_status_json ?? [],
        'provider' => $item?->provider,
        'model' => $item?->model,
        'tokens' => (int) ($item?->tokens_used ?? 0),
        'policy_version' => data_get($item?->token_usage_json, 'guard_policy_version'),
        'request_log_id' => $request?->id,
        'request_status' => $request?->status,
        'fact_status' => data_get($item?->draft?->normalized_output_json, 'governance_context.fact_status'),
        'capacity_semantics' => data_get($item?->draft?->normalized_output_json, 'governance_context.capacity_semantics'),
        'schema_ai_keys' => collect($product->category?->technicalSchemaFieldsFor('ai') ?? [])->pluck('key')->values()->all(),
        'verified_fact_keys' => array_keys(app(ProductTechnicalFactResolver::class)->allVerified($product)),
        'registry_fact_keys' => collect(app(VerifiedFactRegistry::class)->buildForProduct($product))->pluck('fact_key')->values()->all(),
    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

if ($mode === 'cleanup') {
    if ($state !== []) {
        Product::whereKey((int) $state['product_id'])->update([
            'is_active' => false,
            'name' => '[AI GUARD AUDIT EVIDENCE] '.Str::after((string) Product::find((int) $state['product_id'])?->name, '] '),
        ]);
    }
    echo json_encode(['preserved_as_inactive_evidence' => true, 'product_id' => $state['product_id'] ?? null], JSON_THROW_ON_ERROR);
    exit;
}

$operator = User::findOrFail(1);
$password = (string) env('ADMIN_PASSWORD', 'ChangeMe!2024');
if (! $operator->is_active || ! Hash::check($password, (string) $operator->password)) {
    throw new RuntimeException('CONFIGURED_OPERATOR_LOGIN_UNAVAILABLE');
}

$source = Product::whereKey(1320)->first() ?: Product::whereNotNull('brand_id')->whereNotNull('model_code')->firstOrFail();
$suffix = now()->format('YmdHis').'-'.Str::lower(Str::random(5));
$product = $source->replicate();
$product->forceFill([
    'name' => '[AI GUARD REAL '.$suffix.'] '.$source->name,
    'slug' => 'ai-guard-real-'.$suffix,
    'sku' => 'AI-GUARD-'.$suffix,
    'short_description' => null,
    'long_description' => null,
    'seo_title' => null,
    'seo_description' => null,
    'og_title' => null,
    'og_description' => null,
    'merchant_title' => null,
    'merchant_description' => null,
    'ai_status' => 'not_generated',
    'ai_error_message' => null,
    'ai_score' => 0,
    'ai_warning_count' => 0,
    'ai_generated_at' => null,
    'ai_last_run_at' => null,
    'is_active' => false,
])->save();

$state = [
    'operator' => ['id' => $operator->id, 'email' => $operator->email, 'password' => $password],
    'product_id' => $product->id,
    'product_count_before' => Product::count(),
    'content_hash_before' => app(AIProductDraftApplyService::class)->contentHash($product),
    'job_count_before' => \App\Models\AiProductJob::count(),
    'request_count_before' => AiRequestLog::count(),
];
file_put_contents($statePath, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
echo json_encode($state, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
