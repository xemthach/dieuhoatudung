<?php

namespace App\Jobs;

use App\Models\AiProductJobItem;
use App\Models\AiProductDraft;
use App\Models\Product;
use App\Models\User;
use App\Services\AI\AIContentGovernance;
use App\Services\AI\AIManager;
use App\Services\AI\BulkRuntimeBatchService;
use App\Services\AI\BulkRuntimeLeaseService;
use App\Services\AI\BulkRuntimeSlotService;
use App\Services\AI\BulkRuntimeTokenService;
use App\Services\Product\AIProductContentSystem;
use App\Support\CanonicalJsonHasher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class AiProductFieldRetryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public int $fieldOperationId) {}

    public function handle(AIManager $manager, AIContentGovernance $governance, AIProductContentSystem $system): void
    {
        $operation = DB::table('ai_bulk_field_operations')->where('id', $this->fieldOperationId)->first();
        if (! $operation || in_array($operation->status, ['DONE', 'RUNNING'], true)) return;
        if ((int) $operation->attempts >= (int) $operation->max_attempts) return;
        $item = AiProductJobItem::findOrFail($operation->item_id);
        $job = $item->job;
        \App\Services\AI\PilotRuntimeGuard::assert(is_array($job->config_json) ? $job->config_json : []);
        $product = Product::with(['brand', 'category', 'tags', 'faqs', 'relatedProducts', 'posts'])->findOrFail($operation->product_id);
        if ($operation->actor_id) {
            $actor = User::find($operation->actor_id);
            if (! $actor || ! ($actor->can('bulk_ai_retry') || $actor->can('bulk_ai.retry'))) {
                DB::table('ai_bulk_field_operations')->where('id', $operation->id)->update(['status' => 'BLOCKED', 'last_error_code' => 'BULK_AI_RETRY_FORBIDDEN', 'last_error_message' => 'Retry permission was revoked before execution.', 'updated_at' => now()]);
                return;
            }
        }
        $contextHash = $system->technicalContextHash($product);
        if ((string) $item->technical_context_hash !== '' && ! hash_equals((string) $item->technical_context_hash, $contextHash)) {
            DB::table('ai_bulk_field_operations')->where('id', $operation->id)->update(['status' => 'BLOCKED', 'last_error_code' => 'STALE_TECHNICAL_CONTEXT', 'last_error_message' => 'Verified context changed before field retry.', 'updated_at' => now()]);
            return;
        }

        $worker = gethostname().':'.getmypid();
        $runtime = app(BulkRuntimeBatchService::class)->ensure($job);
        $lease = app(BulkRuntimeLeaseService::class)->claim($runtime, (int) $item->id, $worker);
        if (! $lease) return;
        $slot = app(BulkRuntimeSlotService::class)->acquire($runtime, (int) $item->id, $worker, 120, true);
        if (! $slot) { app(BulkRuntimeLeaseService::class)->release($runtime, (int) $item->id, $worker); $this->release(5); return; }
        $reserved = null;
        $reservationEstimate = null;
        try {
            // A field retry is an explicit, operator-directed remediation. It
            // may run while the parent batch remains PAUSED; queued sibling
            // items are still blocked by the normal gate.
            $field = (string) $operation->field;
            $config = is_array($job->config_json) ? $job->config_json : [];
            $config['action'] = 'retry_ai_product_field';
            $config['outputs'] = [$field === 'content_html' ? 'content' : $field => true];
            $promptVersion = AIContentGovernance::PROMPT_VERSION;
            $context = $governance->publicContext($governance->buildProductContext($product, ['action' => $config['action'], 'outputs' => $config['outputs']]));
            $fieldContract = match ($field) {
                'content', 'content_html' => ' CONTENT CONTRACT: minimum 800 Vietnamese words; target 1000-1300 words; include substantive H2 and H3 sections; no filler or repeated paragraphs. Preserve all non-target fields exactly.',
                'faq' => ' FAQ CONTRACT: use verified facts only; missing facts must be omitted or answered neutrally; never invent area, HP conversion, warranty, efficiency or installation distance.',
                default => ' Preserve all non-target fields exactly and use only the requested field.',
            };
            $capacityContract = ' CAPACITY CONTRACT: marketing_capacity_btu is commercial grouping only; technical_capacity_btu is for rated/technical cooling wording. Never use bare ambiguous "công suất X BTU" when the semantic role is not explicit.';
            $requestPayload = [
                'system' => 'You are a governed HVAC content editor. Return JSON only.',
                'prompt' => 'FIELD-SPECIFIC GOVERNED RETRY. Generate only: '.$field.'. Do not regenerate any other field.'.$fieldContract.$capacityContract,
                'input' => 'VERIFIED CONTEXT: '.json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ];
            $provider = \App\Models\AiProvider::where('status', 'active')->orderBy('id')->first();
            $requestPayload['max_tokens'] = (int) ($config['field_retry_max_output_tokens'] ?? 10000);
            $envelope = app(\App\Services\AI\BulkRuntimeTokenEnvelopeService::class)->forPayload($requestPayload, $provider);
            $reservationEstimate = (int) $envelope['reservation_envelope'];
            $reserved = app(BulkRuntimeTokenService::class)->reserveEnvelope($runtime, $envelope, true);
            if ($reserved === false) return;
            DB::table('ai_bulk_field_operations')->where('id', $operation->id)->update(['status' => 'RUNNING', 'updated_at' => now()]);
            $started = microtime(true);
            $result = $manager->generate($requestPayload, [
                'task_type' => 'product_content_field_retry', 'context_id' => 'field-retry-'.$operation->idempotency_key,
                'require_json' => true, 'max_attempts' => 1, 'fake_429_attempts' => (int) ($config['fake_429_attempts'] ?? 0),
                'fake_timeout_attempts' => (int) ($config['fake_timeout_attempts'] ?? 0), 'fake_5xx_attempts' => (int) ($config['fake_5xx_attempts'] ?? 0),
            ]);
            DB::table('ai_bulk_field_operations')->where('id', $operation->id)->update(['status' => 'VALIDATING', 'updated_at' => now()]);
            DB::table('ai_bulk_field_operations')->where('id', $operation->id)->update(['status' => 'FACT_CHECKING', 'updated_at' => now()]);
            $payload = $result['json'] ?? (json_decode($result['content'] ?? '', true) ?: []);
            $value = $payload[$field] ?? null;
            if ($value === null) throw new RuntimeException('FIELD_PAYLOAD_MISSING');
            $audit = ['warnings' => []];
            if (in_array($field, ['content', 'content_html'], true)) {
                $wordCount = preg_match_all('/\p{L}+[\p{L}\p{M}\d-]*/u', trim(strip_tags((string) $value))) ?: 0;
                if ($wordCount < 800) throw new RuntimeException("FIELD_CONTENT_TOO_SHORT:{$wordCount}/800");
                $audit = $governance->validateText((string) $value, $context);
                if (($audit['blocked_claims'] ?? []) !== []) throw new RuntimeException('FIELD_FACT_CHECK_BLOCKED:'.implode('|', $audit['blocked_claims']));
            }
            $tokens = (int) ($result['tokens_used'] ?? 0);
            $draft = $item->draft_id ? AiProductDraft::find($item->draft_id) : null;
            $draftPayload = $draft ? (array) ($draft->normalized_output_json ?? []) : [];
            $draftPayload[$field] = $value;
            if (! $draft) {
                $draft = AiProductDraft::create([
                    'job_id' => $job->id,
                    'product_id' => $product->id,
                    'module' => 'ai_product_field_retry',
                    'raw_output_json' => [$field => $value],
                    'normalized_output_json' => $draftPayload,
                    'field_status_json' => [$field => 'REVIEW_REQUIRED'],
                    'validation_errors_json' => [],
                    'warnings_json' => $audit['warnings'] ?? [],
                    'status' => 'needs_review',
                    'approval_status' => 'REVIEW_REQUIRED',
                    'token_usage_json' => ['provider' => $result['provider'] ?? null, 'model' => $result['model'] ?? null, 'tokens_total' => $tokens, 'field' => $field],
                ]);
            } else {
                $draft->update(['normalized_output_json' => $draftPayload, 'field_status_json' => array_merge((array) ($draft->field_status_json ?? []), [$field => 'REVIEW_REQUIRED']), 'status' => 'needs_review', 'approval_status' => 'REVIEW_REQUIRED']);
            }
            $item->update(['draft_id' => $draft->id, 'generated_payload_json' => array_merge((array) ($item->generated_payload_json ?? []), [$field => $value]), 'status' => 'needs_review', 'canonical_status' => 'REVIEW_REQUIRED', 'status_reason' => 'FIELD_RETRY_REVIEW_REQUIRED']);
            DB::table('ai_bulk_field_operations')->where('id', $operation->id)->update(['status' => 'DONE', 'tokens_consumed' => $tokens, 'input_tokens' => $tokens, 'output_tokens' => 0, 'provider' => $result['provider'] ?? null, 'model' => $result['model'] ?? null, 'latency_ms' => (int) ((microtime(true) - $started) * 1000), 'updated_at' => now()]);
            $actual = DB::table('ai_product_job_items')->where('id', $item->id)->value('tokens_used');
            app(BulkRuntimeTokenService::class)->finalize($runtime, $reservationEstimate, $tokens ?: (int) $actual);
        } catch (\Throwable $e) {
            DB::table('ai_bulk_field_operations')->where('id', $operation->id)->update(['status' => 'FAILED', 'last_error_code' => 'field_retry_failed', 'last_error_message' => $e->getMessage(), 'updated_at' => now()]);
            if ($reserved !== null) {
                $recovered = (int) (DB::table('ai_request_logs')
                    ->where('context_id', 'field-retry-'.$operation->idempotency_key)
                    ->where('status', 'success')
                    ->whereNotNull('tokens_total')
                    ->latest('id')
                    ->value('tokens_total') ?? 0);
                if ($recovered > 0) {
                    app(BulkRuntimeTokenService::class)->finalize($runtime, $reservationEstimate, $recovered);
                } else {
                    app(BulkRuntimeTokenService::class)->releaseOutstandingReservation($runtime, $reservationEstimate, 'field_retry_failed_before_provider_usage', (int) $item->id);
                }
            }
            throw $e;
        } finally {
            app(BulkRuntimeSlotService::class)->release($runtime, (int) $item->id, $worker);
            app(BulkRuntimeLeaseService::class)->release($runtime, (int) $item->id, $worker);
        }
    }
}
