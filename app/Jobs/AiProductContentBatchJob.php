<?php

namespace App\Jobs;

use App\Models\AiProductJob;
use App\Models\Product;
use App\Services\AI\AITechnicalLogger;
use App\Services\AI\AIProductIdempotencyService;
use App\Services\AI\ProductBulkGenerationManifest;
use App\Support\SchemaColumns;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Database\QueryException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AiProductContentBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;
    public array $productIds;

    public function __construct(public int $aiProductJobId)
    {
        $manifest = AiProductJob::find($this->aiProductJobId)?->target_manifest_json;
        $this->productIds = is_array($manifest) ? array_map('intval', $manifest['resolved_product_ids'] ?? []) : [];
    }

    public function handle(?AITechnicalLogger $technicalLogger = null, ?AIProductIdempotencyService $idempotency = null): void
    {
        $technicalLogger ??= app(AITechnicalLogger::class);
        $idempotency ??= app(AIProductIdempotencyService::class);
        $job = AiProductJob::findOrFail($this->aiProductJobId);
        $config = is_array($job->config_json) ? $job->config_json : [];
        \App\Services\AI\PilotRuntimeGuard::assert($config);
        $strictDraftOnly = (bool) ($config['draft_only_strict'] ?? false);
        if ($job->target_manifest_hash) {
            $manifest = app(ProductBulkGenerationManifest::class)->loadVerified($job);
            $this->productIds = array_map('intval', $manifest['resolved_product_ids']);
        } else {
            throw new \RuntimeException('GENERATION_MANIFEST_REQUIRED');
        }
        $runtimeBatch = Schema::hasTable('ai_bulk_runtime_batches')
            ? app(\App\Services\AI\BulkRuntimeBatchService::class)->ensure($job)
            : null;
        if ($runtimeBatch && $runtimeBatch->status === 'QUEUED') {
            $runtimeBatch->update(['status' => 'RUNNING']);
        }
        $batchSize = max(1, min((int) ($config['batch_size'] ?? 10), 50));
        $productIds = array_values(array_unique(array_map('intval', $this->productIds ?? [])));
        $existingIds = Product::query()->whereKey($productIds)->pluck('id')->map(fn ($id) => (int) $id)->all();
        if (count($existingIds) !== count($productIds)) {
            throw new \RuntimeException('BLOCKED_TARGET_MISSING');
        }

        $job->update(SchemaColumns::existing('ai_product_jobs', [
            'status' => 'processing',
            'canonical_status' => 'RUNNING',
            'module' => 'ai_product_bulk',
            'queue_name' => $this->queue ?: config('ai.governed_queue', 'ai_governed'),
            'attempts' => $this->attempts(),
            'total' => count($productIds),
            'started_at' => $job->started_at ?? now(),
            'failed_reason' => null,
            'last_error_code' => null,
            'last_error_message' => null,
        ]));
        $technicalLogger->event('ai_product_bulk', 'job_started', 'AI product batch job started.', [
            'queue' => $this->queue ?: config('ai.governed_queue', 'ai_governed'),
            'total' => count($productIds),
            'batch_size' => $batchSize,
        ], $job);

        if (! $strictDraftOnly) {
            Product::whereKey($productIds)->update([
                'ai_status' => 'queued',
                'ai_error_message' => null,
                'ai_last_run_at' => now(),
            ]);
        }

        foreach (array_chunk($productIds, $batchSize) as $chunkIndex => $chunk) {
            foreach ($chunk as $productId) {
                $item = $job->items()->firstOrCreate(
                    ['product_id' => $productId],
                    SchemaColumns::existing('ai_product_job_items', [
                        'status' => 'queued', 'canonical_status' => 'QUEUED',
                        'error_message' => null, 'dispatch_uuid' => (string) Str::uuid(),
                    ])
                );
                if (! $item->wasRecentlyCreated) {
                    $effective = app(\App\Services\AI\AiProductStateCompatibility::class)->item($item)['status'];
                    if (! app(\App\Services\AI\AiProductStateCompatibility::class)->isActive($effective)) continue;
                    if (! $item->dispatch_uuid) $item->update(['dispatch_uuid' => (string) Str::uuid()]);
                }

                $product = Product::find($productId);
                $key = $product ? $idempotency->key($product, $config) : null;
                if ($key && ($existing = $idempotency->existing($key, $item->id))) {
                    $isDone = in_array($existing->status, ['completed', 'completed_verified', 'completed_with_warnings'], true)
                        || $existing->canonical_status === 'DONE';
                    $item->update(SchemaColumns::existing('ai_product_job_items', [
                        'technical_context_hash' => $product ? $idempotency->contextHash($product) : null,
                        'prompt_version' => \App\Services\AI\AIContentGovernance::PROMPT_VERSION,
                        'status' => $isDone ? 'completed_verified' : 'blocked',
                        'canonical_status' => $isDone ? 'DONE' : 'BLOCKED',
                        'status_reason' => $isDone ? 'REUSED_EXISTING_RESULT' : 'DUPLICATE_IN_PROGRESS',
                        'generated_payload_json' => $isDone ? $existing->generated_payload_json : null,
                        'draft_id' => $isDone ? $existing->draft_id : null,
                    ]));

                    continue;
                }

                try {
                    $item->update(SchemaColumns::existing('ai_product_job_items', [
                        'idempotency_key' => $key,
                        'technical_context_hash' => $product ? $idempotency->contextHash($product) : null,
                        'prompt_version' => \App\Services\AI\AIContentGovernance::PROMPT_VERSION,
                    ]));
                } catch (QueryException $exception) {
                    if (! $idempotency->isDuplicateKeyException($exception)) {
                        throw $exception;
                    }

                    $existing = $key ? $idempotency->existing($key, $item->id) : null;
                    if (! $existing) {
                        throw $exception;
                    }

                    $item->update(SchemaColumns::existing('ai_product_job_items', [
                        'canonical_status' => 'BLOCKED',
                        'status' => 'blocked',
                        'status_reason' => 'DUPLICATE_IN_PROGRESS',
                    ]));

                    continue;
                }

                if ($runtimeBatch && Schema::hasTable('ai_bulk_field_operations')) {
                    foreach ((array) ($config['outputs'] ?? ['content' => true]) as $field => $enabled) {
                        if (! $enabled) continue;
                        $field = $field === 'content' ? 'content_html' : (string) $field;
                        DB::table('ai_bulk_field_operations')->insertOrIgnore([
                            'runtime_batch_id' => $runtimeBatch->id, 'item_id' => $item->id, 'product_id' => $productId,
                            'field' => $field, 'status' => 'QUEUED', 'max_attempts' => $runtimeBatch->max_attempts,
                            'created_at' => now(), 'updated_at' => now(),
                        ]);
                    }
                }

                AiProductContentSingleJob::dispatch($productId, $job->id, $item->id, $item->dispatch_uuid)
                    ->onQueue(config('ai.governed_queue', 'ai_governed'))
                    ->delay(now()->addSeconds($chunkIndex * 5));
            }
        }

        if ($productIds === []) {
            app(\App\Services\AI\AiProductLifecycleService::class)->reconcile($job);
        }

        app(\App\Services\AI\AiProductLifecycleService::class)->reconcile($job);

        $technicalLogger->event('ai_product_bulk', 'job_dispatched', 'AI product item jobs dispatched.', [
            'total' => count($productIds),
            'queue' => config('ai.governed_queue', 'ai_governed'),
        ], $job);
    }
}
