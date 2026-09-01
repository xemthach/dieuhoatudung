<?php

namespace App\Jobs;

use App\Models\AiProductJob;
use App\Models\AiProductJobItem;
use App\Models\Product;
use App\Models\User;
use App\Services\AI\AITechnicalLogger;
use App\Services\AI\AIJobStateMachine;
use App\Services\AI\AIProductIdempotencyService;
use App\Services\AI\AiProductLifecycleService;
use App\Services\AI\AiProductStateCompatibility;
use App\Services\Product\AIProductContentSystem;
use App\Services\Product\AIProductSeoScorer;
use App\Support\SchemaColumns;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

class AiProductContentSingleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 900;

    public function __construct(
        public int $productId,
        public ?int $aiProductJobId = null,
        public ?int $aiProductJobItemId = null,
        public ?string $dispatchUuid = null,
    ) {}

    public function backoff(): array
    {
        return [60, 180, 300];
    }

    public function handle(AIProductContentSystem $system, ?AITechnicalLogger $technicalLogger = null, ?AIProductIdempotencyService $idempotency = null, ?AiProductLifecycleService $lifecycle = null): void
    {
        $technicalLogger ??= app(AITechnicalLogger::class);
        $idempotency ??= app(AIProductIdempotencyService::class);
        $lifecycle ??= app(AiProductLifecycleService::class);
        $product = Product::with(['brand', 'category', 'tags', 'faqs', 'relatedProducts', 'posts'])->findOrFail($this->productId);
        $job = $this->aiProductJobId ? AiProductJob::find($this->aiProductJobId) : null;
        $item = $this->resolveItem($job, $product);
        if ($item && $this->dispatchUuid && $item->dispatch_uuid && ! hash_equals($item->dispatch_uuid, $this->dispatchUuid)) return;
        if ($item && ($item->status_reason === 'BLOCKED_FINAL'
            || in_array((string) $item->canonical_status, [AIJobStateMachine::DONE, AIJobStateMachine::FAILED, AIJobStateMachine::BLOCKED, AIJobStateMachine::CANCELLED], true))) {
            if ($job) $lifecycle->reconcile($job);
            return;
        }
        if ($item && $lifecycle->checkpointCancellation($item, 'WORKER_ENTRY')) return;
        $resumeAllowlist = (array) data_get($job?->config_json, 'controlled_resume_allowlist', []);
        if ($resumeAllowlist !== [] && ! in_array((int) $product->id, array_map('intval', $resumeAllowlist), true)) return;
        if ($job?->created_by) {
            $actor = User::find($job->created_by);
            $manifest = is_array($job->target_manifest_json) ? $job->target_manifest_json : [];
            $permitted = (array) data_get($manifest, 'permission_snapshot.permitted_product_ids', []);
            $allowed = $actor && ($actor->can('bulk_ai_generate') || $actor->can('product.ai_generate'));
            if (! $allowed || ($permitted !== [] && ! in_array((int) $product->id, array_map('intval', $permitted), true))) {
                $this->updateItem($item, ['status' => 'blocked', 'canonical_status' => AIJobStateMachine::BLOCKED, 'status_reason' => 'BLOCKED_PERMISSION_REVOKED', 'last_error_code' => 'BLOCKED_PERMISSION_REVOKED', 'finished_at' => now()]);
                $this->logTerminalOutcome($technicalLogger, $item?->refresh(), $job, $product);
                if ($job) $lifecycle->reconcile($job->refresh());
                return;
            }
        }

        // Jobs created by the canonical lifecycle already carry this identity.
        // Preserve compatibility for an older queued job without rotating any
        // identity that was already frozen at submission time.
        $config = $job
            ? $lifecycle->ensureJobGenerationIdentity($job)
            : [];
        if ($item) {
            $config['current_job_item_id'] = $item->id;
        }
        \App\Services\AI\PilotRuntimeGuard::assert(is_array($config) ? $config : []);
        $strictDraftOnly = (bool) ($config['draft_only_strict'] ?? false);
        $key = $idempotency->key($product, $config);
        $existing = $idempotency->existing($key, $item?->id);
        if ($existing) {
            $isDone = in_array($existing->status, ['completed', 'completed_verified', 'completed_with_warnings'], true)
                || $existing->canonical_status === 'DONE';
            $this->updateItem($item, [
                'technical_context_hash' => $idempotency->contextHash($product),
                'prompt_version' => \App\Services\AI\AIContentGovernance::PROMPT_VERSION,
                'status' => $isDone ? 'completed_verified' : 'blocked',
                'canonical_status' => $isDone ? 'DONE' : 'BLOCKED',
                'status_reason' => $isDone ? 'REUSED_EXISTING_RESULT' : 'DUPLICATE_IN_PROGRESS',
                'generated_payload_json' => $isDone ? $existing->generated_payload_json : null,
                'draft_id' => $isDone ? $existing->draft_id : null,
            ]);
            if ($job) $lifecycle->reconcile($job->refresh());
            $this->logTerminalOutcome($technicalLogger, $item?->refresh(), $job, $product);

            return;
        }
        try {
            $this->updateItem($item, [
                'idempotency_key' => $key,
                'technical_context_hash' => $item->technical_context_hash ?: $idempotency->contextHash($product),
                'prompt_version' => \App\Services\AI\AIContentGovernance::PROMPT_VERSION,
            ]);
        } catch (QueryException $exception) {
            if (! $idempotency->isDuplicateKeyException($exception)) {
                throw $exception;
            }

            $existing = $idempotency->existing($key, $item?->id);
            if (! $existing) {
                throw $exception;
            }

            $this->updateItem($item, [
                'status' => 'blocked',
                'canonical_status' => AIJobStateMachine::BLOCKED,
                'status_reason' => 'DUPLICATE_IN_PROGRESS',
            ]);
            if ($job) $lifecycle->reconcile($job->refresh());
            $this->logTerminalOutcome($technicalLogger, $item?->refresh(), $job, $product);

            return;
        }

        if ($strictDraftOnly) {
            \App\Services\AI\DraftOnlyWriteGuard::begin('queued_draft_only_strict');
        }
        $this->updateItem($item, [
            'status' => 'processing',
            'module' => 'ai_product_content',
            'queue_name' => $this->queue ?: config('ai.governed_queue', 'ai_governed'),
            'attempts' => $this->attempts(),
            'started_at' => $item->started_at ?? now(),
            'finished_at' => null,
            'error_message' => null,
            'failed_reason' => null,
            'last_error_code' => null,
            'last_error_message' => null,
        ]);
        $technicalLogger->event('ai_product_content', 'job_started', 'AI product item job started.', [
            'queue' => $this->queue ?: config('ai.governed_queue', 'ai_governed'),
            'attempts' => $this->attempts(),
            'product_id' => $product->id,
        ], $item);
        AIJobStateMachine::transition($item, AIJobStateMachine::RUNNING, 'worker_started');

        try {
            $runtime = $this->acquireRuntimeGate($job, $item, $product, $system, $config);
            if ($runtime === false) {
                $this->release(5);
                return;
            }
            if (is_array($runtime) && ($runtime['terminal'] ?? false)) {
                $runtime = null;
                return;
            }
            if ($item && $lifecycle->checkpointCancellation($item, 'BEFORE_PROVIDER')) return;
            if ($item && $this->shouldPatchExistingDraft($item)) {
                $patched = $system->retryDraftPatch($product, $item, $job);
                if ($patched !== null) {
                    return;
                }
            }

            $system->generate($product, $config, $job, $item, $job?->created_by);
            if ($item && $lifecycle->checkpointCancellation($item, 'AFTER_PROVIDER')) return;
            if (is_array($runtime ?? null) && Schema::hasTable('ai_bulk_field_operations')) {
                \Illuminate\Support\Facades\DB::table('ai_bulk_field_operations')->where('runtime_batch_id', $runtime['batch']->id)->where('item_id', $item->id)->where('field', 'content_html')->update(['status' => 'DONE', 'tokens_consumed' => (int) ($item->refresh()->tokens_used ?? 0), 'updated_at' => now()]);
            }
        } catch (\Throwable $e) {
            $providerAttemptsExhausted = str_contains($e->getMessage(), 'AI Generation failed after');
            if ($this->isRateLimit($e) && ! $providerAttemptsExhausted && $this->attempts() < $this->tries) {
                $this->updateItem($item, [
                    'status' => 'queued',
                    'retry_count' => (int) ($item->retry_count ?? 0) + 1,
                    'failed_reason' => 'provider_rate_limit',
                    'last_error_code' => 'provider_rate_limit',
                    'last_error_message' => 'Rate limited, retrying later.',
                    'error_message' => 'Rate limited, retrying later.',
                ]);
                AIJobStateMachine::transition($item->refresh(), AIJobStateMachine::QUEUED, 'provider_rate_limit_retry');
                $product->update(['ai_status' => 'queued', 'ai_error_message' => 'Rate limited, retrying later.']);
                $technicalLogger->event('ai_product_content', 'job_retried', 'Provider rate limit; job released for retry.', [
                    'failed_reason' => 'provider_rate_limit',
                    'attempts' => $this->attempts(),
                    'product_id' => $product->id,
                ], $item, 'warning');
                $this->release(60 * $this->attempts());

                return;
            }

            $technical = $technicalLogger->exception('ai_product_content', $e, $item, [
                'queue' => $this->queue ?: 'ai',
                'attempts' => $this->attempts(),
                'product_id' => $product->id,
            ]);
            $responseUsage = is_array($item?->token_usage_json) ? $item->token_usage_json : [];
            $technical['validation_errors'] = [
                'code' => $technical['last_error_code'] ?? 'job_failed',
                'schema_version' => $responseUsage['schema_version'] ?? config('ai_product_allowed_fields.schema_version', 'content-layer-runtime-contract-v1'),
                'response_shape' => $responseUsage['response_shape'] ?? null,
                'finish_reason' => $responseUsage['finish_reason'] ?? null,
                'raw_response_length' => $responseUsage['raw_response_length'] ?? null,
                'response_fingerprint' => $responseUsage['response_fingerprint'] ?? null,
            ];
            $technical['raw_response_summary'] = json_encode([
                'response_fingerprint' => $responseUsage['response_fingerprint'] ?? null,
                'raw_response_length' => $responseUsage['raw_response_length'] ?? null,
                'finish_reason' => $responseUsage['finish_reason'] ?? null,
                'response_shape' => $responseUsage['response_shape'] ?? null,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $score = app(AIProductSeoScorer::class)->score($product->loadMissing(['brand', 'category', 'tags', 'faqs', 'relatedProducts', 'posts']));
            $message = $e->getMessage();
            $this->updateItem($item, [
                'status' => 'failed',
                'error_message' => $message,
                'seo_score_before' => $item?->seo_score_before ?? $score['score'],
                'seo_score_after' => $score['score'],
                'warnings_json' => $score['warnings'],
                'finished_at' => now(),
                'duration_ms' => (int) $item?->started_at?->diffInMilliseconds(now()),
                ...$technical,
            ]);
            if (is_array($runtime ?? null) && Schema::hasTable('ai_bulk_field_operations')) {
                \Illuminate\Support\Facades\DB::table('ai_bulk_field_operations')->where('runtime_batch_id', $runtime['batch']->id)->where('item_id', $item->id)->where('field', 'content_html')->update(['status' => 'FAILED', 'last_error_code' => $technical['last_error_code'] ?? 'job_failed', 'last_error_message' => $message, 'updated_at' => now()]);
            }
            AIJobStateMachine::transition($item, AIJobStateMachine::FAILED, $technical['last_error_code'] ?? 'job_failed');
            if (! $strictDraftOnly) {
                $product->update([
                    'ai_status' => 'failed',
                    'ai_score' => $score['score'],
                    'ai_warning_count' => count($score['warnings']),
                    'ai_error_message' => $message,
                    'ai_last_run_at' => now(),
                ]);
            }
            Log::error('AI product content job failed', [
                'ai_product_job_id' => $job?->id,
                'product_id' => $product->id,
                'error' => $message,
            ]);
        } finally {
            $this->logTerminalOutcome($technicalLogger, $item?->refresh(), $job, $product);
            if (isset($runtime) && is_array($runtime)) {
                $runtimeBatch = $runtime['batch'];
                $item?->refresh();
                $actual = (int) ($item?->tokens_used ?? 0);

                // The provider request is committed before downstream validation and
                // fact-checking. Recover that authoritative usage when a later
                // validator throws before AIProductContentSystem can persist it.
                if ($actual <= 0 && $job && $item) {
                    $contextId = "ai-product-{$product->id}-{$job->id}";
                    $providerLog = \Illuminate\Support\Facades\DB::table('ai_request_logs')
                        ->where('context_id', $contextId)
                        ->where('status', 'success')
                        ->whereNotNull('tokens_total')
                        ->when($item->started_at, fn ($query) => $query->where('created_at', '>=', $item->started_at))
                        ->latest('id')
                        ->first();

                    if ($providerLog && (int) $providerLog->tokens_total > 0) {
                        $actual = (int) $providerLog->tokens_total;
                        $usage = is_array($item->token_usage_json) ? $item->token_usage_json : [];
                        $usage['provider_usage_recovered'] = [
                            'request_log_id' => (int) $providerLog->id,
                            'tokens_total' => $actual,
                            'reason' => 'provider_success_before_downstream_validation',
                        ];
                        $item->update([
                            'tokens_used' => $actual,
                            'token_usage_json' => $usage,
                        ]);
                        $technicalLogger->event('ai_bulk_runtime', 'token_usage_recovered', 'Provider usage recovered after downstream validation failure.', [
                            'batch_uuid' => $runtimeBatch->batch_uuid,
                            'item_id' => $item->id,
                            'product_id' => $product->id,
                            'provider_request_log_id' => (int) $providerLog->id,
                            'tokens_total' => $actual,
                            'reason' => 'provider_success_before_downstream_validation',
                        ], $item);
                    }
                }
                if ($actual > 0) {
                    app(\App\Services\AI\BulkRuntimeTokenService::class)->finalize($runtimeBatch, $runtime['reserved'], $actual);
                } else {
                    app(\App\Services\AI\BulkRuntimeTokenService::class)->releaseOutstandingReservation($runtimeBatch, $runtime['reserved'], 'provider_usage_unavailable_or_interrupted_before_response', (int) $item->id);
                }
                app(\App\Services\AI\BulkRuntimeSlotService::class)->release($runtimeBatch, (int) $item->id, $runtime['worker']);
                app(\App\Services\AI\BulkRuntimeLeaseService::class)->release($runtimeBatch, (int) $item->id, $runtime['worker']);
            }
            if ($job) $lifecycle->reconcile($job->refresh());
            if ($strictDraftOnly) {
                \App\Services\AI\DraftOnlyWriteGuard::end();
            }
        }
    }

    public function failed(\Throwable $exception): void
    {
        $item = $this->aiProductJobItemId ? AiProductJobItem::find($this->aiProductJobItemId) : null;
        $job = $this->aiProductJobId ? AiProductJob::find($this->aiProductJobId) : null;
        $product = Product::with(['brand', 'category', 'tags', 'faqs', 'relatedProducts', 'posts'])->find($this->productId);
        $score = $product ? app(AIProductSeoScorer::class)->score($product) : ['score' => 0, 'warnings' => []];
        $technical = app(AITechnicalLogger::class)->exception('ai_product_content', $exception, $item, [
            'product_id' => $this->productId,
        ]);

        $this->updateItem($item, [
            'status' => 'failed',
            'error_message' => $exception->getMessage(),
            'seo_score_before' => $item?->seo_score_before ?? $score['score'],
            'seo_score_after' => $score['score'],
            'warnings_json' => $score['warnings'],
            'finished_at' => now(),
            ...$technical,
        ]);
        if ($item) {
            $item->refresh();
            if (! in_array($item->canonical_status, [AIJobStateMachine::FAILED, AIJobStateMachine::CANCELLED], true)) {
                AIJobStateMachine::transition($item, AIJobStateMachine::FAILED, $technical['last_error_code'] ?? 'queue_job_failed');
            }
        }

        $strictDraftOnly = (bool) ($job?->config_json['draft_only_strict'] ?? false);
        if (! $strictDraftOnly) {
            Product::whereKey($this->productId)->update([
                'ai_status' => 'failed',
                'ai_score' => $score['score'],
                'ai_warning_count' => count($score['warnings']),
                'ai_error_message' => $exception->getMessage(),
                'ai_last_run_at' => now(),
            ]);
        }

        if ($this->aiProductJobId && ($job = AiProductJob::find($this->aiProductJobId))) {
            app(AiProductLifecycleService::class)->reconcile($job);
        }
    }

    private function resolveItem(?AiProductJob $job, Product $product): ?AiProductJobItem
    {
        if ($this->aiProductJobItemId) {
            return AiProductJobItem::find($this->aiProductJobItemId);
        }

        return $job?->items()->updateOrCreate(
            ['product_id' => $product->id],
            ['status' => 'queued']
        );
    }

    private function shouldPatchExistingDraft(?AiProductJobItem $item): bool
    {
        if (! $item) {
            return false;
        }

        return (int) ($item->retry_count ?? 0) > 0
            && (is_array($item->generated_payload_json) || $item->draft_id)
            && in_array($item->status, ['processing', 'queued', 'failed', 'blocked', 'needs_review'], true);
    }

    private function updateItem(?AiProductJobItem $item, array $attributes): void
    {
        $item?->update(SchemaColumns::existing('ai_product_job_items', $attributes));
    }

    private function acquireRuntimeGate(?AiProductJob $job, ?AiProductJobItem $item, Product $product, ?AIProductContentSystem $system = null, array $config = []): array|false|null
    {
        if (! $job || ! $item || ! Schema::hasTable('ai_bulk_runtime_batches')) return null;
        $runtimeBatch = app(\App\Models\AiBulkRuntimeBatch::class)->where('batch_uuid', $job->batch_uuid)->first();
        if (! $runtimeBatch) return null;
        if ($runtimeBatch->status === 'CANCELLED') {
            $this->updateItem($item, ['status' => 'cancelled', 'canonical_status' => 'CANCELLED', 'status_reason' => 'CANCELLED_BY_OPERATOR']);
            return ['terminal' => true];
        }
        $expected = $item->technical_context_hash;
        $current = app(\App\Services\AI\AIProductIdempotencyService::class)->contextHash($product);
        if ($expected && ! hash_equals((string) $expected, (string) $current)) {
            $this->updateItem($item, ['status' => 'blocked', 'canonical_status' => 'BLOCKED', 'status_reason' => 'STALE_TECHNICAL_CONTEXT', 'last_error_code' => 'STALE_TECHNICAL_CONTEXT']);
            return ['terminal' => true];
        }
        $worker = gethostname().':'.getmypid();
        if (! app(\App\Services\AI\BulkRuntimeLeaseService::class)->claim($runtimeBatch, (int) $item->id, $worker)) return false;
        $slot = app(\App\Services\AI\BulkRuntimeSlotService::class)->acquire($runtimeBatch, (int) $item->id, $worker);
        if (! $slot) {
            app(\App\Services\AI\BulkRuntimeLeaseService::class)->release($runtimeBatch, (int) $item->id, $worker);
            return false;
        }
        $envelope = $system?->providerRequestEnvelope($product, $config ?: ($job->config_json ?? []), $job)
            ?? throw new \RuntimeException('HARD_TOKEN_BUDGET_ENVELOPE_REQUIRED');
        $estimate = (int) $envelope['reservation_envelope'];
        if (! app(\App\Services\AI\BulkRuntimeTokenService::class)->reserveEnvelope($runtimeBatch, $envelope)) {
            app(\App\Services\AI\BulkRuntimeSlotService::class)->release($runtimeBatch, (int) $item->id, $worker);
            app(\App\Services\AI\BulkRuntimeLeaseService::class)->release($runtimeBatch, (int) $item->id, $worker);
            return false;
        }
        return ['batch' => $runtimeBatch, 'worker' => $worker, 'reserved' => $estimate];
    }

    private function refreshJobStats(AiProductJob $job): void
    {
        $job = app(AiProductLifecycleService::class)->reconcile($job);
        $terminal = ! in_array($job->canonical_status, AiProductStateCompatibility::ACTIVE, true);
        if ($terminal && \Illuminate\Support\Facades\Schema::hasTable('ai_bulk_runtime_batches') && $job->batch_uuid) {
            $runtime = \App\Models\AiBulkRuntimeBatch::where('batch_uuid', $job->batch_uuid)->first();
            if ($runtime && ! in_array($runtime->status, ['PAUSED', 'CANCELLED'], true)) {
                $hasErrors = in_array($job->canonical_status, [AIJobStateMachine::FAILED, AIJobStateMachine::BLOCKED], true);
                $runtime->update(['status' => $hasErrors ? 'COMPLETED_WITH_ERRORS' : 'COMPLETED', 'status_reason' => $hasErrors ? 'ITEM_FAILURE' : null]);
            }
        }
    }

    private function isRateLimit(\Throwable $e): bool
    {
        $message = $e->getMessage();
        $decoded = json_decode($message, true);

        return (is_array($decoded) && ! empty($decoded['is_rate_limit']))
            || str_contains($message, '429')
            || stripos($message, 'rate limit') !== false;
    }

    private function logTerminalOutcome(
        AITechnicalLogger $logger,
        ?AiProductJobItem $item,
        ?AiProductJob $job,
        Product $product,
    ): void {
        if (! $item) return;

        $canonical = strtoupper((string) ($item->canonical_status ?: AIJobStateMachine::fromLegacy((string) $item->status)));
        $event = match ($canonical) {
            AIJobStateMachine::BLOCKED => 'item_blocked',
            AIJobStateMachine::FAILED => 'item_failed',
            AIJobStateMachine::REVIEW_REQUIRED => 'item_review_required',
            AIJobStateMachine::DONE => 'item_completed',
            AIJobStateMachine::CANCELLED => 'item_cancelled',
            default => null,
        };
        if (! $event) return;

        $logger->event('ai_product_content', $event, 'AI product item reached a terminal or actionable state.', [
            'job_id' => $job?->id,
            'item_id' => $item->id,
            'product_id' => $product->id,
            'reason_code' => $item->status_reason ?: $item->last_error_code ?: $item->failed_reason,
            'guard_code' => $item->last_error_code ?: $item->status_reason,
            'stage' => $canonical,
            'provider_called' => (bool) data_get($item->token_usage_json, 'provider_called', ((int) $item->tokens_used) > 0),
            'draft_id' => $item->draft_id,
        ], $item, in_array($canonical, [AIJobStateMachine::BLOCKED, AIJobStateMachine::FAILED], true) ? 'warning' : 'info');
    }
}
