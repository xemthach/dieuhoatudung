<?php

namespace App\Services\Product;

use App\Models\AiProductContentVersion;
use App\Models\AiProductDraft;
use App\Models\AiProductJob;
use App\Models\AiProductJobItem;
use App\Models\AiProvider;
use App\Models\Faq;
use App\Models\Product;
use App\Models\Tag;
use App\Models\User;
use App\Services\AI\AIContentGovernance;
use App\Services\AI\AIContentPatchService;
use App\Services\AI\AIJobStateMachine;
use App\Services\AI\Governance\ForbiddenClaimEngine;
use App\Services\AI\AIManager;
use App\Services\AI\AITechnicalLogger;
use App\Support\SchemaColumns;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class AIProductContentSystem
{
    private const CONTENT_LAYER_FIELDS = [
        'excerpt',
        'content_html',
        'seo_title',
        'meta_description',
        'og_title',
        'og_description',
        'merchant_title',
        'merchant_description',
        'tags',
        'faq',
        'internal_links',
        'media_metadata',
        'warnings',
        'blocked_claims',
        'used_facts',
        'used_verified_facts',
        'product_id',
    ];

    private const BLOCKED_PRODUCT_DATA_FIELDS = [
        'basic_info',
        'technical_specs',
        'technical_specs_json',
        'name',
        'slug',
        'sku',
        'model',
        'model_code',
        'brand',
        'brand_id',
        'category',
        'product_category_id',
        'series',
        'capacity_btu',
        'btu',
        'marketing_capacity_btu',
        'technical_capacity_btu',
        'technical_capacity_status',
        'catalog_source_id',
        'catalog_model_id',
        'catalog_provenance',
        'source_catalogue',
        'source_page',
        'capacity_kw',
        'hp',
        'cooling_type',
        'inverter',
        'phase',
        'voltage',
        'refrigerant',
        'refrigerant_gas',
        'power_consumption',
        'power_input_kw',
        'airflow',
        'noise_level',
        'recommended_area',
        'indoor_dimensions',
        'outdoor_dimensions',
        'weight',
        'pipe_liquid',
        'pipe_gas',
        'pipe_max_length',
        'pipe_max_height',
        'regular_price',
        'sale_price',
        'discount_percent',
        'stock_status',
        'specs_json',
    ];

    public const AI_STATUSES = [
        'not_generated' => 'Chưa tạo',
        'queued' => 'Đang chờ',
        'processing' => 'Đang xử lý',
        'completed' => 'Hoàn thành',
        'completed_verified' => 'Hoàn thành đã xác minh',
        'completed_with_warnings' => 'Hoàn thành có cảnh báo',
        'failed' => 'Thất bại',
        'needs_review' => 'Cần duyệt',
        'blocked' => 'Bị chặn',
        'cancelled' => 'Đã hủy',
        'stuck' => 'Bị kẹt',
    ];

    private AIContentGovernance $governance;

    private AITechnicalLogger $technicalLogger;
    private ProductTechnicalFactResolver $technicalFacts;
    private AIContentStructureValidator $structureValidator;

    public function __construct(
        private readonly AIManager $aiManager,
        private readonly AIProductSeoScorer $scorer,
        private readonly AIProductContentSanitizer $sanitizer,
        ?AIContentGovernance $governance = null,
        ?AITechnicalLogger $technicalLogger = null,
        ?ProductTechnicalFactResolver $technicalFacts = null,
        ?AIContentStructureValidator $structureValidator = null,
    ) {
        $this->governance = $governance ?? app(AIContentGovernance::class);
        $this->technicalLogger = $technicalLogger ?? app(AITechnicalLogger::class);
        $this->technicalFacts = $technicalFacts ?? app(ProductTechnicalFactResolver::class);
        $this->structureValidator = $structureValidator ?? app(AIContentStructureValidator::class);
    }

    public function normalizeConfig(array $config): array
    {
        $outputs = $config['outputs'] ?? [];
        if (! is_array($outputs)) {
            $outputs = [];
        }

        $applyMode = match ($config['apply_mode'] ?? 'draft_only') {
            'needs_review' => 'draft_only',
            'auto_apply' => 'auto_apply_safe_fields',
            'draft_only', 'auto_apply_safe_fields', 'full_auto_if_passed' => $config['apply_mode'],
            default => 'draft_only',
        };

        return [
            'mode' => $config['mode'] ?? 'missing_only',
            'depth' => $config['depth'] ?? 'seo',
            'tone' => $config['tone'] ?? 'hvac_expert',
            'batch_size' => max(1, min((int) ($config['batch_size'] ?? 10), 50)),
            'apply_mode' => $applyMode,
            'outputs' => array_merge([
                'content' => false,
                'seo' => false,
                'merchant' => false,
                'tags' => false,
                'faq' => false,
                'internal_links' => false,
                'og' => false,
            ], $outputs),
            'action' => $config['action'] ?? 'generate_ai_content',
            'fake_429_attempts' => (int) ($config['fake_429_attempts'] ?? 0),
            'fake_timeout_attempts' => (int) ($config['fake_timeout_attempts'] ?? 0),
            'fake_5xx_attempts' => (int) ($config['fake_5xx_attempts'] ?? 0),
            'fake_governance_failure' => (bool) ($config['fake_governance_failure'] ?? false),
            'fake_output_case' => $config['fake_output_case'] ?? null,
            'retry_short_content' => filter_var($config['retry_short_content'] ?? true, FILTER_VALIDATE_BOOL),
            'draft_only_strict' => (bool) ($config['draft_only_strict'] ?? false),
            'content_eligibility_scope' => $config['content_eligibility_scope'] ?? null,
            'current_job_item_id' => isset($config['current_job_item_id']) ? (int) $config['current_job_item_id'] : null,
        ];
    }

    /** Build the governed request shape without contacting a provider. */
    public function providerRequestEnvelope(Product $product, array $config, ?AiProductJob $job = null): array
    {
        $config = $this->normalizeConfig($config);
        $product->loadMissing(['brand', 'category', 'tags', 'faqs', 'relatedProducts', 'posts']);
        $contentEligibility = $this->governance->contentEligibility($product, [
            'outputs' => $config['outputs'],
            'type' => $config['action'],
            'scope' => $config['content_eligibility_scope'],
            'current_job_item_id' => $config['current_job_item_id'],
        ]);
        if (! $contentEligibility['eligible']) {
            throw new RuntimeException('CONTENT_ELIGIBILITY_BLOCKED:'.implode('|', $contentEligibility['reasons']));
        }
        $input = $this->buildInput($product);
        $context = $this->governance->buildProductContext($product, [
            'action' => $config['action'], 'outputs' => $config['outputs'], 'mode' => $config['mode'],
            'depth' => $config['depth'], 'tone' => $config['tone'],
        ]);
        $payload = [
            'system' => $this->systemPrompt(),
            'prompt' => $this->buildPrompt($input, $config, $context),
            'temperature' => $config['depth'] === 'deep_hvac' ? 0.45 : 0.55,
            'max_tokens' => $config['depth'] === 'deep_hvac' ? 14000 : 10000,
        ];
        $provider = AiProvider::where('status', 'active')->orderBy('id')->first();
        return app(\App\Services\AI\BulkRuntimeTokenEnvelopeService::class)->forPayload($payload, $provider);
    }

    public function audit(Product $product): array
    {
        $product->loadMissing(['brand', 'category', 'tags', 'faqs', 'relatedProducts', 'posts']);
        $score = $this->scorer->score($product);
        $status = $score['score'] < 70 ? 'needs_review' : ($product->ai_status ?: 'not_generated');

        $product->update([
            'ai_score' => $score['score'],
            'ai_warning_count' => count($score['warnings']),
            'ai_status' => $status,
            'ai_last_run_at' => now(),
            'ai_error_message' => null,
        ]);

        return $score;
    }

    public function generate(Product $product, array $config, ?AiProductJob $job = null, ?AiProductJobItem $item = null, ?int $userId = null): array
    {
        $config = $this->normalizeConfig($config);
        $product->loadMissing(['brand', 'category', 'tags', 'faqs', 'relatedProducts', 'posts']);
        $contentEligibility = $this->governance->contentEligibility($product, [
            'outputs' => $config['outputs'],
            'type' => $config['action'],
            'scope' => $config['content_eligibility_scope'],
            'current_job_item_id' => $item?->id ?? $config['current_job_item_id'],
        ]);
        if (! $contentEligibility['eligible']) {
            throw new RuntimeException('CONTENT_ELIGIBILITY_BLOCKED:'.implode('|', $contentEligibility['reasons']));
        }
        $before = $this->scorer->score($product);

        if ($config['action'] === 'audit_seo') {
            return $this->completeAuditOnly($product, $before, $item);
        }

        if ($config['mode'] === 'rewrite_weak' && $before['score'] >= 70) {
            return $this->completeSkippedStrongContent($product, $before, $item);
        }

        $strictDraftOnly = (bool) ($config['draft_only_strict'] ?? false);
        if ($strictDraftOnly) {
            $config['apply_mode'] = 'draft_only';
        }
        if (! $strictDraftOnly) {
            $product->update([
                'ai_status' => 'processing',
                'ai_last_run_at' => now(),
                'ai_error_message' => null,
            ]);
        }

        $input = $this->buildInput($product);
        $guardContext = $this->governance->buildProductContext($product, [
            'action' => $config['action'],
            'outputs' => $config['outputs'],
            'mode' => $config['mode'],
            'depth' => $config['depth'],
            'tone' => $config['tone'],
        ]);
        $contextId = 'ai-product-'.$product->id.'-'.($job?->id ?? Str::uuid());
        $result = $this->aiManager->generate([
            'system' => $this->systemPrompt(),
            'prompt' => $this->buildPrompt($input, $config, $guardContext),
            'temperature' => $config['depth'] === 'deep_hvac' ? 0.45 : 0.55,
        ], [
            'task_type' => 'product_content',
            'context_id' => $contextId,
            'require_json' => true,
            'max_tokens' => $config['depth'] === 'deep_hvac' ? 14000 : 10000,
            'max_attempts' => 3,
            'fake_429_attempts' => (int) ($config['fake_429_attempts'] ?? 0),
            'fake_timeout_attempts' => (int) ($config['fake_timeout_attempts'] ?? 0),
            'fake_5xx_attempts' => (int) ($config['fake_5xx_attempts'] ?? 0),
            'fake_governance_failure' => (bool) ($config['fake_governance_failure'] ?? false),
            'fake_output_case' => $config['fake_output_case'] ?? null,
        ]);

        $payload = $result['json'] ?? [];
        if ($payload === [] && ! empty($result['content'])) {
            $payload = json_decode($result['content'], true) ?: [];
        }

        $rawPayload = $payload;
        if ($item && is_string($result['content'] ?? null)) {
            $usage = is_array($item->token_usage_json) ? $item->token_usage_json : [];
            $usage['response_fingerprint'] = hash('sha256', $result['content']);
            $usage['response_shape'] = array_keys($result['json'] ?? []);
            $usage['schema_version'] = config('ai_product_allowed_fields.schema_version', 'content-layer-runtime-contract-v1');
            $usage['finish_reason'] = $result['finish_reason'] ?? null;
            $usage['raw_response_length'] = mb_strlen($result['content'], '8bit');
            $usage['provider_request_id'] = $result['provider_request_id'] ?? null;
            $item->update(['token_usage_json' => $usage]);
        }
        if ($item) {
            AIJobStateMachine::transition($item, AIJobStateMachine::VALIDATING, 'provider_output_received');
        }
        try {
            $payload = $this->normalizePayload($payload, $product, $config);
        } catch (\Throwable $exception) {
            $recovery = $this->isPersistableValidationFailure($exception)
                ? $this->attemptShortContentRecovery(
                    $product,
                    $config,
                    $input,
                    $guardContext,
                    $rawPayload,
                    $result,
                )
                : null;
            if ($recovery !== null) {
                $payload = $recovery['payload'];
                $rawPayload = $recovery['raw_payload'];
                $result = $recovery['result'];
            } else {
            // Preserve safe provider output when a downstream content-quality
            // check rejects it. Without this evidence draft persistence never
            // runs, while the job still records provider token usage; the UI
            // then misleadingly reports every field as missing.
            if ($item && $this->isPersistableValidationFailure($exception)) {
                try {
                    $evidencePayload = $this->normalizePayload($rawPayload, $product, $config, validate: false);
                    $evidencePayload['warnings'] = $this->normalizeIssueList(
                        $evidencePayload['warnings'] ?? [],
                        $this->validationWarningFromException($exception),
                    );
                    $validationErrors = $this->structuredValidationErrors($evidencePayload, $config);
                    $fieldStatus = $this->fieldStatus(
                        $evidencePayload,
                        $evidencePayload['warnings'],
                        $evidencePayload['blocked_claims'] ?? [],
                        $config,
                    );
                    $tokenUsage = [
                        'generate_tokens' => (int) ($result['tokens_used'] ?? 0),
                        'patch_tokens' => 0,
                        'saved_tokens_estimate' => 0,
                        'provider' => $result['provider'] ?? null,
                        'model' => $result['model'] ?? null,
                        'product_id' => $product->id,
                        'job_id' => $job?->id,
                        'prompt_version' => $guardContext['prompt_version'] ?? null,
                        'technical_context_hash' => $this->technicalContextHash($product),
                        'validation_failure_evidence' => true,
                    ];
                    $this->persistDraft(
                        $product,
                        $job,
                        $item,
                        $rawPayload,
                        $evidencePayload,
                        $fieldStatus,
                        $validationErrors,
                        $evidencePayload['warnings'],
                        $tokenUsage,
                        'failed',
                    );
                    $this->updateItem($item, [
                        'generated_payload_json' => $evidencePayload,
                        'validation_errors' => $validationErrors,
                        'warnings_json' => $evidencePayload['warnings'],
                        'tokens_used' => (int) ($result['tokens_used'] ?? 0),
                        'latency_ms' => (int) ($result['latency_ms'] ?? 0),
                        'provider' => $result['provider'] ?? null,
                        'model' => $result['model'] ?? null,
                    ]);
                } catch (\Throwable) {
                    // Keep the original validation exception authoritative if
                    // evidence normalization itself cannot be completed.
                }
            }

            throw $exception;
            }
        }
        if ($item) {
            AIJobStateMachine::transition($item, AIJobStateMachine::FACT_CHECKING, 'payload_normalized');
        }
        $factCheck = $this->governance->validatePayload($payload, $guardContext, [
            'excerpt',
            'content_html',
            'seo_title',
            'meta_description',
            'og_title',
            'og_description',
            'merchant_title',
            'merchant_description',
        ]);
        $warnings = $this->normalizeIssueList(
            $this->filterAiDeclaredMissingWarnings($payload['warnings'], $guardContext),
            $factCheck['warnings'],
            $this->detectDuplicateWarnings($product, $payload['content_html']),
            $this->scorer->auditWarningsForPayload($product, $payload)
        );
        $payload['warnings'] = $warnings;
        $payload['blocked_claims'] = $this->normalizeIssueList($payload['blocked_claims'] ?? [], $factCheck['blocked_claims']);
        $payload['used_facts'] = $factCheck['used_facts'];
        $payload['fact_check'] = $factCheck;
        $payload['governance_context'] = $this->governance->publicContext($guardContext);
        $validationErrors = $this->structuredValidationErrors($payload, $config);
        $fieldStatus = $this->fieldStatus($payload, $warnings, $payload['blocked_claims'], $config);
        $tokenUsage = [
            'generate_tokens' => (int) ($result['tokens_used'] ?? 0),
            'patch_tokens' => 0,
            'saved_tokens_estimate' => 0,
            'provider' => $result['provider'] ?? null,
            'model' => $result['model'] ?? null,
            'product_id' => $product->id,
            'job_id' => $job?->id,
            'prompt_version' => $guardContext['prompt_version'] ?? null,
            'technical_context_hash' => $this->technicalContextHash($product),
        ];
        $draft = $this->persistDraft($product, $job, $item, $rawPayload, $payload, $fieldStatus, $validationErrors, $warnings, $tokenUsage, 'draft');
        foreach ($fieldStatus as $fieldName => $statusForField) {
            Log::info('ai_token_usage', [
                'generate_tokens' => $tokenUsage['generate_tokens'],
                'patch_tokens' => 0,
                'saved_tokens_estimate' => 0,
                'provider' => $tokenUsage['provider'],
                'model' => $tokenUsage['model'],
                'field_name' => $fieldName,
                'field_status' => $statusForField,
                'product_id' => $product->id,
                'job_id' => $job?->id,
                'mode' => 'generate',
            ]);
        }

        if ($payload['blocked_claims'] !== []) {
            // Only truly block if there are critical safety issues
            // For warning-level issues (unverified claims), auto-rewrite and continue
            $hasCriticalBlock = $this->hasCriticalBlock($payload['blocked_claims']);

            if ($hasCriticalBlock) {
                $status = 'blocked';
                $message = 'AI output bi chan fact-check: '.implode(', ', $payload['blocked_claims']);
            } else {
                // Auto-rewrite unverified claims and continue with warnings
                $payload = $this->autoRewriteUnverifiedClaims($payload, $product);
                $status = 'completed_with_warnings';
                $message = null;
            }

            if ($hasCriticalBlock) {
                if (! $strictDraftOnly) {
                    $product->update([
                        'ai_status' => $status,
                        'ai_score' => $before['score'],
                        'ai_warning_count' => count($warnings),
                        'ai_error_message' => $message,
                        'ai_last_run_at' => now(),
                    ]);
                }

                $this->updateItem($item, [
                    'status' => $status,
                    'failed_reason' => 'fact_check_failed',
                    'last_error_code' => 'fact_check_failed',
                    'last_error_message' => $message,
                    'seo_score_before' => $before['score'],
                    'seo_score_after' => $before['score'],
                    'warnings_json' => $warnings,
                    'error_message' => $message,
                    'generated_payload_json' => $payload,
                    'tokens_used' => (int) ($result['tokens_used'] ?? 0),
                    'latency_ms' => (int) ($result['latency_ms'] ?? 0),
                    'provider' => $result['provider'] ?? null,
                    'model' => $result['model'] ?? null,
                    'finished_at' => now(),
                    'duration_ms' => (int) $item?->started_at?->diffInMilliseconds(now()),
                ]);
                if ($item) {
                    AIJobStateMachine::transition($item->refresh(), AIJobStateMachine::BLOCKED, 'fact_check_failed');
                }
                $this->technicalLogger->event('ai_product_content', 'fact_check_failed', $message, [
                    'content_layer_only' => true,
                    'warnings' => $warnings,
                    'blocked_claims' => $payload['blocked_claims'],
                    'blocked_product_data_fields' => $payload['blocked_product_data_fields'] ?? [],
                    'verified_facts_used' => $payload['used_facts'] ?? [],
                    'unverified_claims_removed' => $this->warningsWithPrefix($warnings, 'unverified_claim_removed:'),
                    'fact_check_status' => $factCheck['status'] ?? null,
                    'provider' => $result['provider'] ?? null,
                    'model' => $result['model'] ?? null,
                ], $item, 'warning');

                Log::warning('AI product content blocked by critical safety check', [
                    'ai_product_job_id' => $job?->id,
                    'product_id' => $product->id,
                    'status' => $status,
                    'prompt_version' => $guardContext['prompt_version'],
                    'blocked_claims' => $payload['blocked_claims'],
                ]);
                $draft?->update(['status' => 'blocked']);

                return [
                    'payload' => $payload,
                    'score_before' => $before,
                    'score_after' => $before,
                    'status' => $status,
                ];
            }

            // Non-critical: rewritten claims, continue to apply
            $warnings = $payload['warnings'];
        }

        $applied = false;
        $canApply = match ($config['apply_mode']) {
            'full_auto_if_passed' => $warnings === [] && ($payload['blocked_claims'] ?? []) === [],
            'auto_apply_safe_fields' => ! $this->hasCriticalBlock($payload['blocked_claims'] ?? []),
            default => false,
        };

        if ($canApply && ! $strictDraftOnly) {
            $this->applyPayload($product, $payload, $config, $before['score'], $userId);
            $applied = true;
            $product->refresh()->loadMissing(['brand', 'category', 'tags', 'faqs', 'relatedProducts', 'posts']);
        }

        $after = $applied
            ? $this->scorer->score($product, $warnings)
            : $this->scorer->scorePayload($product, $payload, $warnings);
        $status = $applied
            ? ($after['score'] < 70 ? 'needs_review' : ($warnings === [] ? 'completed_verified' : 'completed_with_warnings'))
            : 'needs_review';

        if ($item) {
            AIJobStateMachine::transition(
                $item,
                $status === 'needs_review' ? AIJobStateMachine::REVIEW_REQUIRED : AIJobStateMachine::DONE,
                $status
            );
        }

        if (! $strictDraftOnly) {
            $product->update([
                'ai_status' => $status,
                'ai_score' => $after['score'],
                'ai_warning_count' => count($warnings),
                'ai_error_message' => $warnings !== [] ? 'AI draft contains fact-check warnings: ' . implode(', ', $warnings) : null,
                'ai_last_run_at' => now(),
                'ai_generated_at' => now(),
            ]);
        }

        $this->updateItem($item, [
            'status' => $status,
            'seo_score_before' => $before['score'],
            'seo_score_after' => $after['score'],
            'warnings_json' => $warnings,
            'generated_payload_json' => $payload,
            'tokens_used' => (int) ($result['tokens_used'] ?? 0),
            'latency_ms' => (int) ($result['latency_ms'] ?? 0),
            'provider' => $result['provider'] ?? null,
            'model' => $result['model'] ?? null,
            'finished_at' => now(),
            'duration_ms' => (int) $item?->started_at?->diffInMilliseconds(now()),
        ]);
        $draft?->update(['status' => $status]);
        $this->technicalLogger->event('ai_product_content', 'job_completed', 'AI product content generated.', [
            'content_layer_only' => true,
            'status' => $status,
            'warnings' => $warnings,
            'blocked_product_data_fields' => $payload['blocked_product_data_fields'] ?? [],
            'verified_facts_used' => $payload['used_facts'] ?? [],
            'unverified_claims_removed' => $this->warningsWithPrefix($warnings, 'unverified_claim_removed:'),
            'fact_check_status' => $factCheck['status'] ?? null,
            'provider' => $result['provider'] ?? null,
            'model' => $result['model'] ?? null,
            'tokens_used' => $result['tokens_used'] ?? null,
        ], $item);

        Log::info('AI product content generated', [
            'ai_product_job_id' => $job?->id,
            'product_id' => $product->id,
            'score_before' => $before['score'],
            'score_after' => $after['score'],
            'warnings' => $warnings,
            'prompt_version' => $guardContext['prompt_version'],
            'allowed_facts' => $guardContext['allowed_facts'],
            'missing_facts' => $guardContext['missing_facts'],
            'fact_check' => $factCheck,
            'blocked_claims' => $payload['blocked_claims'],
            'provider' => $result['provider'] ?? null,
            'model' => $result['model'] ?? null,
            'tokens_used' => $result['tokens_used'] ?? null,
        ]);

        return [
            'payload' => $payload,
            'score_before' => $before,
            'score_after' => $after,
            'status' => $status,
        ];
    }

    public function applyLatestDraft(Product $product, ?int $userId = null): ?AiProductJobItem
    {
        $item = $product->aiProductJobItems()
            ->whereNotNull('generated_payload_json')
            ->latest('id')
            ->first();

        if (! $item || ! is_array($item->generated_payload_json)) {
            return null;
        }

        $blockedDataFields = $this->blockedProductDataFields($item->generated_payload_json);
        if ($blockedDataFields !== []) {
            throw new RuntimeException('AI draft rejected: forbidden Product Data field(s): '.implode(', ', $blockedDataFields));
        }

        if (($item->status === 'blocked') || ! empty($item->generated_payload_json['blocked_claims'] ?? [])) {
            $product->update([
                'ai_status' => 'blocked',
                'ai_error_message' => 'Không thể áp dụng bản nháp AI vì nội dung chưa vượt qua bước kiểm tra.',
                'ai_last_run_at' => now(),
            ]);

            return null;
        }

        if (! in_array($item->status, ['needs_review', 'completed', 'completed_verified', 'completed_with_warnings'], true)) {
            $product->update([
                'ai_status' => $item->status ?: 'needs_review',
                'ai_error_message' => 'Không thể áp dụng bản nháp AI vì job chưa hoàn tất hợp lệ.',
                'ai_last_run_at' => now(),
            ]);

            return null;
        }

        $storedContextHash = data_get($item->token_usage_json, 'technical_context_hash');
        if (! is_string($storedContextHash) || $storedContextHash === '' || ! hash_equals($storedContextHash, $this->technicalContextHash($product))) {
            $message = 'STALE_TECHNICAL_CONTEXT: Product technical facts changed or legacy draft has no context snapshot.';
            $item->update(SchemaColumns::existing('ai_product_job_items', [
                'status' => 'blocked',
                'failed_reason' => 'stale_technical_context',
                'last_error_code' => 'stale_technical_context',
                'last_error_message' => $message,
                'error_message' => $message,
            ]));
            $product->update([
                'ai_status' => 'blocked',
                'ai_error_message' => $message,
                'ai_last_run_at' => now(),
            ]);

            throw new RuntimeException($message);
        }

        $config = $this->normalizeConfig($item->job?->config_json ?? []);
        $config['apply_mode'] = 'auto_apply';
        $before = $this->scorer->score($product->loadMissing(['brand', 'category', 'tags', 'faqs', 'relatedProducts', 'posts']));

        $this->applyPayload($product, $item->generated_payload_json, $config, $before['score'], $userId);
        $product->refresh()->loadMissing(['brand', 'category', 'tags', 'faqs', 'relatedProducts', 'posts']);
        $after = $this->scorer->score($product, $item->warnings_json ?? []);

        $product->update([
            'ai_status' => $after['score'] < 70 ? 'needs_review' : (($item->warnings_json ?? []) === [] ? 'completed_verified' : 'completed_with_warnings'),
            'ai_score' => $after['score'],
            'ai_warning_count' => count($after['warnings']),
            'ai_generated_at' => now(),
            'ai_last_run_at' => now(),
        ]);

        $item->update([
            'status' => $product->ai_status,
            'seo_score_after' => $after['score'],
            'warnings_json' => $after['warnings'],
        ]);

        return $item;
    }

    public function retryDraftPatch(Product $product, AiProductJobItem $item, ?AiProductJob $job = null): ?array
    {
        $draft = $item->draft ?: AiProductDraft::query()
            ->where('product_id', $product->id)
            ->latest('id')
            ->first();
        $payload = $draft?->normalized_output_json ?: $item->generated_payload_json;

        if (! is_array($payload) || $payload === []) {
            return null;
        }

        $config = $this->normalizeConfig($job?->config_json ?? $item->job?->config_json ?? []);
        $validationErrors = $draft?->validation_errors_json ?: $this->structuredValidationErrors($payload, $config);

        if ($this->hasCriticalBlock($payload['blocked_claims'] ?? [])) {
            $item->update(SchemaColumns::existing('ai_product_job_items', [
                'status' => 'blocked',
                'error_message' => 'Critical draft issue requires admin review.',
                'validation_errors' => $validationErrors,
                'finished_at' => now(),
            ]));

            return ['status' => 'blocked', 'payload' => $payload];
        }

        $patcher = app(AIContentPatchService::class);
        $patchedFields = [];
        foreach (array_keys($this->fieldStatus($payload, $payload['warnings'] ?? [], $payload['blocked_claims'] ?? [], $config)) as $field) {
            $fieldErrors = array_values(array_filter($validationErrors, fn (array $error): bool => ($error['field'] ?? null) === $field));
            if ($fieldErrors === []) {
                continue;
            }

            $patched = $patcher->patchField(
                $payload[$field] ?? null,
                $fieldErrors,
                $payload['used_facts'] ?? [],
                [],
                $field,
            );
            $payload[$field] = $patched['patched_field_content'];
            $patchedFields[$field] = $patched['patch_notes'];
        }

        $payload['blocked_claims'] = [];
        $payload['warnings'] = $this->normalizeIssueList($payload['warnings'] ?? [], 'patched_invalid_fragments');
        $fieldStatus = $this->fieldStatus($payload, $payload['warnings'], [], $config);
        $tokenUsage = [
            'generate_tokens' => (int) ($item->tokens_used ?? 0),
            'patch_tokens' => 0,
            'saved_tokens_estimate' => max(0, (int) ($item->tokens_used ?? 0)),
            'provider' => $item->provider,
            'model' => $item->model,
            'product_id' => $product->id,
            'job_id' => $job?->id ?? $item->ai_product_job_id,
            'patched_fields' => array_keys($patchedFields),
        ];
        $newDraft = $this->persistDraft($product, $job ?? $item->job, $item, $payload, $payload, $fieldStatus, [], $payload['warnings'], $tokenUsage, 'needs_review');

        $product->update([
            'ai_status' => 'needs_review',
            'ai_error_message' => 'Draft patched partially; review before applying.',
            'ai_last_run_at' => now(),
        ]);
        $item->update(SchemaColumns::existing('ai_product_job_items', [
            'status' => 'needs_review',
            'generated_payload_json' => $payload,
            'warnings_json' => $payload['warnings'],
            'field_status_json' => $fieldStatus,
            'token_usage_json' => $tokenUsage,
            'draft_id' => $newDraft?->id,
            'finished_at' => now(),
        ]));
        $this->technicalLogger->event('ai_product_content', 'draft_patch_completed', 'Retried failed AI item by patching invalid fields only.', [
            'patched_fields' => array_keys($patchedFields),
            'saved_tokens_estimate' => $tokenUsage['saved_tokens_estimate'],
        ], $item);
        foreach (array_keys($patchedFields) as $fieldName) {
            Log::info('ai_token_usage', [
                'generate_tokens' => $tokenUsage['generate_tokens'],
                'patch_tokens' => $tokenUsage['patch_tokens'],
                'saved_tokens_estimate' => $tokenUsage['saved_tokens_estimate'],
                'provider' => $tokenUsage['provider'],
                'model' => $tokenUsage['model'],
                'field_name' => $fieldName,
                'product_id' => $product->id,
                'job_id' => $job?->id ?? $item->ai_product_job_id,
                'mode' => 'patch',
            ]);
        }

        return ['status' => 'needs_review', 'payload' => $payload];
    }

    public function rollback(Product $product, User $actor, ?int $versionId = null): ?AiProductContentVersion
    {
        app(\App\Services\AI\BulkRuntimeAuthorizationService::class)->requireRollback($actor);
        $version = $versionId
            ? $product->aiContentVersions()->whereKey($versionId)->first()
            : $product->aiContentVersions()->latest('id')->first();

        if (! $version) {
            return null;
        }

        DB::transaction(function () use ($product, $version) {
            $seo = $version->old_seo_json ?? [];
            $merchant = $version->old_merchant_json ?? [];

            $product->update([
                'short_description' => $version->old_excerpt,
                'long_description' => $version->old_content,
                'seo_title' => $seo['seo_title'] ?? null,
                'seo_description' => $seo['seo_description'] ?? null,
                'og_title' => $seo['og_title'] ?? null,
                'og_description' => $seo['og_description'] ?? null,
                'merchant_title' => $merchant['merchant_title'] ?? null,
                'merchant_description' => $merchant['merchant_description'] ?? null,
                'google_product_category' => $merchant['google_product_category'] ?? null,
                'product_type' => $merchant['product_type'] ?? null,
            ]);

            $tagIds = collect($version->old_tags_json ?? [])->pluck('id')->filter()->all();
            $product->tags()->sync($tagIds);

            $product->faqs()->detach();
            foreach ($version->old_faq_json ?? [] as $index => $faqData) {
                if (empty($faqData['question']) || empty($faqData['answer'])) {
                    continue;
                }

                $faq = Faq::create([
                    'question' => $faqData['question'],
                    'answer' => $faqData['answer'],
                    'group' => 'product',
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ]);
                $product->faqs()->attach($faq->id, ['sort_order' => $index + 1]);
            }
        });

        $this->audit($product->refresh());

        return $version;
    }

    public function contentSnapshot(Product $product): array
    {
        $product->loadMissing(['tags', 'faqs']);
        return [
            'short_description' => $product->short_description,
            'long_description' => $product->long_description,
            'seo_title' => $product->seo_title, 'seo_description' => $product->seo_description,
            'og_title' => $product->og_title, 'og_description' => $product->og_description,
            'merchant_title' => $product->merchant_title, 'merchant_description' => $product->merchant_description,
            'tags' => $product->tags->map->only(['id', 'name', 'slug'])->values()->all(),
            'faq' => $product->faqs->map->only(['question', 'answer'])->values()->all(),
        ];
    }

    public function restoreContentSnapshot(Product $product, array $snapshot): void
    {
        DB::transaction(function () use ($product, $snapshot): void {
            $product->update(array_intersect_key($snapshot, array_flip([
                'short_description', 'long_description', 'seo_title', 'seo_description',
                'og_title', 'og_description', 'merchant_title', 'merchant_description',
            ])));
            if (array_key_exists('tags', $snapshot)) $product->tags()->sync(collect($snapshot['tags'])->pluck('id')->filter()->all());
            if (array_key_exists('faq', $snapshot)) {
                $product->faqs()->detach();
                foreach ((array) $snapshot['faq'] as $index => $data) {
                    if (empty($data['question']) || empty($data['answer'])) continue;
                    $faq = Faq::create(['question' => $data['question'], 'answer' => $data['answer'], 'group' => 'product', 'sort_order' => $index + 1, 'is_active' => true]);
                    $product->faqs()->attach($faq->id, ['sort_order' => $index + 1]);
                }
            }
        });
    }

    private function completeAuditOnly(Product $product, array $score, ?AiProductJobItem $item): array
    {
        $status = $score['score'] < 70 ? 'needs_review' : ($score['warnings'] === [] ? 'completed_verified' : 'completed_with_warnings');
        $product->update([
            'ai_status' => $status,
            'ai_score' => $score['score'],
            'ai_warning_count' => count($score['warnings']),
            'ai_last_run_at' => now(),
            'ai_error_message' => null,
        ]);
        $this->updateItem($item, [
            'status' => $status,
            'seo_score_before' => $score['score'],
            'seo_score_after' => $score['score'],
            'warnings_json' => $score['warnings'],
            'finished_at' => now(),
        ]);

        return ['score_before' => $score, 'score_after' => $score, 'status' => $status, 'payload' => []];
    }

    private function completeSkippedStrongContent(Product $product, array $score, ?AiProductJobItem $item): array
    {
        $warnings = array_values(array_unique(array_merge($score['warnings'], ['skipped_strong_content'])));
        $product->update([
            'ai_status' => 'completed_with_warnings',
            'ai_score' => $score['score'],
            'ai_warning_count' => count($warnings),
            'ai_last_run_at' => now(),
        ]);
        $this->updateItem($item, [
            'status' => 'completed_with_warnings',
            'seo_score_before' => $score['score'],
            'seo_score_after' => $score['score'],
            'warnings_json' => $warnings,
            'finished_at' => now(),
        ]);

        return ['score_before' => $score, 'score_after' => $score, 'status' => 'completed_with_warnings', 'payload' => []];
    }

    private function normalizeIssueList(mixed ...$lists): array
    {
        $items = [];

        foreach ($lists as $list) {
            foreach ($this->flattenIssueList($list) as $item) {
                $item = trim($item);
                if ($item !== '') {
                    $items[] = $item;
                }
            }
        }

        return array_values(array_unique($items));
    }

    private function warningsWithPrefix(array $warnings, string $prefix): array
    {
        return array_values(array_filter(
            $warnings,
            fn (string $warning): bool => str_starts_with($warning, $prefix)
        ));
    }

    private function updateItem(?AiProductJobItem $item, array $attributes): void
    {
        $item?->update(SchemaColumns::existing('ai_product_job_items', $attributes));
    }

    private function persistDraft(
        Product $product,
        ?AiProductJob $job,
        ?AiProductJobItem $item,
        array $rawPayload,
        array $payload,
        array $fieldStatus,
        array $validationErrors,
        array $warnings,
        array $tokenUsage,
        string $status,
    ): ?AiProductDraft {
        $draft = AiProductDraft::create([
            'job_id' => $job?->id,
            'product_id' => $product->id,
            'module' => 'ai_product',
            'raw_output_json' => $rawPayload,
            'normalized_output_json' => $payload,
            'field_status_json' => $fieldStatus,
            'validation_errors_json' => $validationErrors,
            'warnings_json' => $warnings,
            'used_verified_facts_json' => $payload['used_facts'] ?? [],
            'token_usage_json' => $tokenUsage,
            'status' => $status,
        ]);

        $this->updateItem($item, [
            'draft_id' => $draft->id,
            'field_status_json' => $fieldStatus,
            'token_usage_json' => $tokenUsage,
            'error_count' => count($validationErrors),
            'warning_count' => count($warnings),
        ]);

        return $draft;
    }

    private function fieldStatus(array $payload, array $warnings, array $blockedClaims, array $config): array
    {
        $fields = [
            'excerpt', 'content_html', 'seo_title', 'meta_description', 'og_title', 'og_description',
            'merchant_title', 'merchant_description', 'tags', 'faq', 'internal_links',
        ];
        $status = [];

        foreach ($fields as $field) {
            $value = $payload[$field] ?? null;
            $status[$field] = blank($value) || $value === [] ? 'skipped' : 'valid';
        }

        foreach ($warnings as $warning) {
            $field = $this->fieldFromIssue((string) $warning, $config);
            $status[$field] = $status[$field] === 'skipped' ? 'warning' : 'warning';
        }

        foreach ($blockedClaims as $claim) {
            $field = $this->fieldFromIssue((string) $claim, $config);
            $status[$field] = $this->hasCriticalBlock([(string) $claim]) ? 'failed' : 'needs_patch';
        }

        return $status;
    }

    private function fieldFromIssue(string $issue, array $config): string
    {
        foreach (['merchant_description', 'merchant_title', 'faq', 'tags', 'seo_title', 'meta_description', 'og_title', 'og_description', 'content_html', 'excerpt'] as $field) {
            if (str_contains($issue, $field)) {
                return $field;
            }
        }

        if (($config['outputs']['merchant'] ?? false) && str_contains($issue, 'merchant')) {
            return 'merchant_description';
        }

        if (($config['outputs']['faq'] ?? false) && str_contains($issue, 'faq')) {
            return 'faq';
        }

        return 'content_html';
    }

    private function structuredValidationErrors(array $payload, array $config): array
    {
        $errors = [];

        foreach ($payload['blocked_claims'] ?? [] as $claim) {
            $claim = (string) $claim;
            $errors[] = [
                'field' => $this->fieldFromIssue($claim, $config),
                'severity' => $this->hasCriticalBlock([$claim]) ? 'critical' : 'warning',
                'claim' => $claim,
                'reason' => 'fact_check',
                'suggested_action' => $this->hasCriticalBlock([$claim]) ? 'block' : 'patch',
                'replacement' => '',
            ];
        }

        foreach ($payload['warnings'] ?? [] as $warning) {
            $warning = (string) $warning;
            $errors[] = [
                'field' => $this->fieldFromIssue($warning, $config),
                'severity' => 'warning',
                'claim' => $warning,
                'reason' => 'validation_warning',
                'suggested_action' => 'rewrite',
                'replacement' => '',
            ];
        }

        return $errors;
    }

    private function filterAiDeclaredMissingWarnings(array $warnings, array $guardContext): array
    {
        $actualMissingFacts = array_flip((array) ($guardContext['missing_facts'] ?? []));

        return array_values(array_filter($warnings, function (string $warning) use ($actualMissingFacts): bool {
            $code = trim(explode(':', $warning, 2)[0]);

            if (! str_starts_with($code, 'missing_') || $code === 'missing_technical_data') {
                return true;
            }

            return isset($actualMissingFacts[$code]);
        }));
    }

    private function flattenIssueList(mixed $value): array
    {
        if (is_scalar($value)) {
            return [(string) $value];
        }

        if (! is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            if (is_scalar($item)) {
                $items[] = (string) $item;

                continue;
            }

            if (is_array($item)) {
                foreach (['code', 'warning', 'claim', 'message', 'value', 'name', 'label'] as $key) {
                    if (isset($item[$key]) && is_scalar($item[$key])) {
                        $items[] = (string) $item[$key];

                        continue 2;
                    }
                }

                $items[] = json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
            }
        }

        return $items;
    }

    private function buildInput(Product $product): array
    {
        $verifiedFacts = $this->technicalFacts->allVerified($product);
        unset($verifiedFacts['marketing_capacity_btu'], $verifiedFacts['technical_capacity_btu']);
        $verifiedFacts = array_merge([
            'rated_cooling_capacity_btu' => $this->technicalFacts->value($product, 'technical_capacity_btu'),
        ], $verifiedFacts);

        return [
            'product_id' => $product->id,
            'product_identity' => [
                'name' => $product->name,
                'slug' => $product->slug,
                'brand' => $product->brand?->only(['id', 'name', 'slug']),
                'category' => $product->category?->only(['id', 'name', 'slug']),
                'model_code' => $product->model_code,
                'sku' => $product->sku,
            ],
            'marketing_identity_facts' => [
                'capacity_group_btu' => $this->technicalFacts->value($product, 'marketing_capacity_btu'),
            ],
            'verified_technical_facts' => $verifiedFacts,
            'capacity_semantics' => [
                'marketing_capacity_btu' => [
                    'value' => $this->technicalFacts->value($product, 'marketing_capacity_btu'),
                    'meaning' => 'COMMERCIAL_GROUPING_ONLY',
                ],
                'technical_capacity_btu' => [
                    'value' => $this->technicalFacts->value($product, 'technical_capacity_btu'),
                    'meaning' => 'AUTHORITATIVE_TECHNICAL_RATED_CAPACITY',
                ],
            ],
            'existing_excerpt' => $product->short_description,
            'existing_content' => Str::limit(strip_tags((string) $product->long_description), 1200),
            'existing_seo' => [
                'seo_title' => $product->seo_title,
                'seo_description' => $product->seo_description,
                'og_title' => $product->og_title,
                'og_description' => $product->og_description,
            ],
            'existing_merchant' => [
                'merchant_title' => $product->merchant_title,
                'merchant_description' => $product->merchant_description,
                'google_product_category' => $product->google_product_category,
                'product_type' => $product->product_type,
            ],
            'related_products' => $product->relatedProducts->take(5)->map(function (Product $related): array {
                return [
                    'id' => $related->id,
                    'name' => $related->name,
                    'slug' => $related->slug,
                    'model_code' => $related->model_code,
                    'marketing_capacity_btu' => $this->technicalFacts->value($related, 'marketing_capacity_btu'),
                ];
            })->values()->all(),
            'related_posts' => $product->posts->take(5)->map->only(['id', 'title', 'slug'])->values()->all(),
        ];
    }

    /**
     * Stable snapshot of the Product technical context used by an AI draft.
     * This intentionally excludes legacy display-only fields such as products.btu.
     */
    public function technicalContextHash(Product $product): string
    {
        $facts = $this->technicalFacts->allVerified($product);

        return hash('sha256', json_encode([
            'product_id' => $product->id,
            'model' => $product->model_code,
            'brand_id' => $product->brand_id,
            'marketing_capacity_btu' => $this->technicalFacts->value($product, 'marketing_capacity_btu'),
            'verified_facts' => $facts,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION));
    }

    private function normalizePayload(array $payload, Product $product, array $config, bool $validate = true): array
    {
        $blockedDataFields = $this->blockedProductDataFields($payload);

        if ($blockedDataFields !== []) {
            throw new RuntimeException('AI payload rejected: forbidden Product Data field(s): '.implode(', ', $blockedDataFields));
        }

        if (is_array($payload['content_layer'] ?? null)) {
            $contentLayer = $payload['content_layer'];
            $payload = array_merge($contentLayer, [
                'product_id' => $payload['product_id'] ?? $contentLayer['product_id'] ?? $product->id,
                'warnings' => $payload['warnings'] ?? $contentLayer['warnings'] ?? [],
                'used_facts' => $payload['used_verified_facts'] ?? $payload['used_facts'] ?? $contentLayer['used_verified_facts'] ?? $contentLayer['used_facts'] ?? [],
                'blocked_claims' => $payload['blocked_claims'] ?? $contentLayer['blocked_claims'] ?? [],
                'internal_links' => $payload['internal_links'] ?? $contentLayer['internal_links'] ?? [],
                'media_metadata' => $payload['media_metadata'] ?? $contentLayer['media_metadata'] ?? [],
            ]);
        }

        if (is_array($payload['content'] ?? null)) {
            $payload = array_merge($payload['content'], [
                'product_id' => $payload['product_id'] ?? $payload['content']['product_id'] ?? null,
                'warnings' => $payload['warnings'] ?? $payload['content']['warnings'] ?? [],
                'used_facts' => $payload['used_verified_facts'] ?? $payload['used_facts'] ?? $payload['content']['used_verified_facts'] ?? $payload['content']['used_facts'] ?? [],
                'blocked_claims' => $payload['blocked_claims'] ?? $payload['content']['blocked_claims'] ?? [],
                'internal_links' => $payload['internal_links'] ?? $payload['content']['internal_links'] ?? [],
            ]);
        }

        $payload = Arr::only($payload, config('ai_product_allowed_fields.content_layer_fields', self::CONTENT_LAYER_FIELDS));
        $payload['product_id'] = (int) ($payload['product_id'] ?? $product->id);
        foreach (['excerpt', 'content_html', 'seo_title', 'meta_description', 'og_title', 'og_description', 'merchant_title', 'merchant_description'] as $key) {
            $payload[$key] = (string) ($payload[$key] ?? '');
        }
        $payload['tags'] = is_array($payload['tags'] ?? null) ? $payload['tags'] : [];
        $payload['faq'] = is_array($payload['faq'] ?? null) ? $payload['faq'] : [];
        $payload['internal_links'] = is_array($payload['internal_links'] ?? null) ? $payload['internal_links'] : [];
        $payload['media_metadata'] = is_array($payload['media_metadata'] ?? null) ? $payload['media_metadata'] : [];
        $payload['warnings'] = $this->normalizeIssueList($payload['warnings'] ?? []);
        $payload['used_facts'] = is_array($payload['used_facts'] ?? null) ? $payload['used_facts'] : [];
        $payload['blocked_claims'] = $this->normalizeIssueList($payload['blocked_claims'] ?? []);
        $payload = $this->neutralizeUnverifiedMarketingClaims($payload, $product);

        $payload = $this->sanitizer->sanitizePayload($payload);
        if ($validate) {
            $this->validatePayload($payload, $product, $config);
        }

        return $payload;
    }

    private function isPersistableValidationFailure(\Throwable $exception): bool
    {
        return str_contains(Str::lower($exception->getMessage()), 'content_too_short');
    }

    private function attemptShortContentRecovery(
        Product $product,
        array $config,
        array $input,
        array $guardContext,
        array $rawPayload,
        array $result,
    ): ?array {
        if (! ($config['retry_short_content'] ?? true) || ($config['action'] ?? '') === 'retry_ai_product_field') {
            return null;
        }

        try {
            $retryConfig = $config;
            $retryConfig['action'] = 'retry_ai_product_field';
            $retryConfig['outputs'] = [
                'content' => true,
                'seo' => false,
                'merchant' => false,
                'tags' => false,
                'faq' => false,
                'internal_links' => false,
                'og' => false,
            ];
            $retryResult = $this->aiManager->generate([
                'system' => $this->systemPrompt(),
                'prompt' => $this->buildPrompt($input, $retryConfig, $guardContext),
                'temperature' => 0.45,
            ], [
                'task_type' => 'product_content',
                'context_id' => 'ai-product-short-content-retry-'.($product->id),
                'require_json' => true,
                'max_tokens' => 12000,
                'max_attempts' => 1,
            ]);
            $retryPayload = $retryResult['json'] ?? [];
            if ($retryPayload === [] && ! empty($retryResult['content'])) {
                $retryPayload = json_decode($retryResult['content'], true) ?: [];
            }
            $retryPayload = $this->normalizePayload($retryPayload, $product, $retryConfig);
            if (blank($retryPayload['content_html'] ?? null)) {
                return null;
            }

            $recoveredPayload = $rawPayload;
            $recoveredPayload['content_html'] = $retryPayload['content_html'];
            $recoveredPayload['warnings'] = $this->normalizeIssueList(
                $rawPayload['warnings'] ?? [],
                $retryPayload['warnings'] ?? [],
                ['short_content_recovered'],
            );
            $recoveredPayload = $this->normalizePayload($recoveredPayload, $product, $config);

            return [
                'payload' => $recoveredPayload,
                'raw_payload' => $recoveredPayload,
                'result' => array_merge($result, [
                    'tokens_used' => (int) ($result['tokens_used'] ?? 0) + (int) ($retryResult['tokens_used'] ?? 0),
                    'latency_ms' => (int) ($result['latency_ms'] ?? 0) + (int) ($retryResult['latency_ms'] ?? 0),
                ]),
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    private function validationWarningFromException(\Throwable $exception): array
    {
        if (preg_match('/content_too_short:\s*(\d+)\/(\d+)/i', $exception->getMessage(), $matches) === 1) {
            return ["content_too_short:{$matches[1]}/{$matches[2]}"];
        }

        return ['content_validation_failed'];
    }

    private function blockedProductDataFields(array $payload): array
    {
        $blocked = [];
        $this->collectBlockedProductDataFields($payload, $blocked);

        return array_values(array_unique($blocked));
    }

    private function collectBlockedProductDataFields(mixed $value, array &$blocked, ?string $parentKey = null): void
    {
        if (! is_array($value)) {
            return;
        }

        foreach ($value as $key => $child) {
            $key = is_string($key) ? $key : '';
            $normalized = Str::snake($key);

            if ($normalized !== ''
                && in_array($normalized, config('ai_product_allowed_fields.blocked_product_data_fields', self::BLOCKED_PRODUCT_DATA_FIELDS), true)) {
                $blocked[] = $normalized;
            }

            $this->collectBlockedProductDataFields($child, $blocked, $normalized);
        }
    }

    private function neutralizeUnverifiedMarketingClaims(array $payload, Product $product): array
    {
        $claimEngine = app(ForbiddenClaimEngine::class);
        $claimContext = $this->governance->buildProductContext($product);
        $rules = [];

        $removed = [];
        $textKeys = ['excerpt', 'content_html', 'seo_title', 'meta_description', 'og_title', 'og_description', 'merchant_title', 'merchant_description'];

        foreach ($textKeys as $key) {
            $result = $claimEngine->rewriteText((string) ($payload[$key] ?? ''), $claimContext);
            if ($result['text'] !== ($payload[$key] ?? '')) {
                $payload[$key] = $result['text'];
                $removed = array_merge($removed, $result['removed_claims']);
            }
        }

        foreach ((array) ($payload['faq'] ?? []) as $index => $faq) {
            if (! is_array($faq)) {
                continue;
            }

            foreach (['question', 'answer'] as $key) {
                $result = $claimEngine->rewriteText((string) ($faq[$key] ?? ''), $claimContext);
                if ($result['text'] !== ($payload['faq'][$index][$key] ?? '')) {
                    $payload['faq'][$index][$key] = $result['text'];
                    $removed = array_merge($removed, $result['removed_claims']);
                }
            }
        }

        foreach ($textKeys as $key) {
            foreach ($rules as $code => $rule) {
                if ($rule['allowed']) {
                    continue;
                }

                foreach ($rule['patterns'] as $pattern => $replacement) {
                    $newValue = preg_replace($pattern, $replacement, (string) ($payload[$key] ?? '')) ?? (string) ($payload[$key] ?? '');
                    if ($newValue !== ($payload[$key] ?? '')) {
                        $payload[$key] = $newValue;
                        $removed[] = $code;
                    }
                }
            }
        }

        foreach ((array) ($payload['faq'] ?? []) as $index => $faq) {
            if (! is_array($faq)) {
                continue;
            }

            foreach (['question', 'answer'] as $key) {
                foreach ($rules as $code => $rule) {
                    if ($rule['allowed']) {
                        continue;
                    }

                    foreach ($rule['patterns'] as $pattern => $replacement) {
                        $newValue = preg_replace($pattern, $replacement, (string) ($faq[$key] ?? '')) ?? (string) ($faq[$key] ?? '');
                        if ($newValue !== ($payload['faq'][$index][$key] ?? '')) {
                            $payload['faq'][$index][$key] = $newValue;
                            $removed[] = $code;
                        }
                    }
                }
            }
        }

        foreach ($textKeys as $key) {
            $value = (string) ($payload[$key] ?? '');
            $ascii = Str::ascii(Str::lower($value));
            $fallbackRules = [
                'vat' => ['vat', '/VAT/iu', 'chinh sach gia'],
                'vuot_troi' => ['vuot troi', '/v\S*\s+tr\S*i/iu', 'on dinh'],
                'mien_phi' => ['mien phi', '/mi\S*n\s+ph\S*/iu', 'lien he de duoc tu van'],
                'chinh_hang' => ['chinh hang', '/ch\S*nh\s+h\S*ng/iu', 'theo thong tin san pham da luu'],
                'gia_tot_nhat' => ['gia tot nhat', '/gi\S*\s+t\S*t\s+nh\S*t/iu', 'muc gia can xac nhan'],
            ];

            foreach ($fallbackRules as $code => [$needle, $pattern, $replacement]) {
                if ($this->claimAllowedByContext($code, $claimContext)) {
                    continue;
                }

                if (! str_contains($ascii, $needle)) {
                    continue;
                }

                $newValue = preg_replace($pattern, $replacement, $value) ?? $value;
                if ($newValue !== $value) {
                    $payload[$key] = $newValue;
                    $value = $newValue;
                    $ascii = Str::ascii(Str::lower($value));
                    $removed[] = $code;
                }
            }
        }

        if ($removed !== []) {
            $payload['warnings'] = $this->normalizeIssueList(
                $payload['warnings'],
                array_map(fn (string $code): string => 'unverified_claim_removed:'.$code, array_values(array_unique($removed)))
            );
        }

        return $payload;
    }

    private function claimAllowedByContext(string $code, array $context): bool
    {
        $rule = (array) config('ai_claim_rules.claims.'.$code, []);

        foreach ((array) ($rule['allow_if'] ?? []) as $key) {
            if ($this->sourceValueAllowsClaim(Arr::get($context, 'allowed_facts.'.$key.'.value'))) {
                return true;
            }

            foreach ((array) Arr::get($context, 'verified_fact_registry', []) as $fact) {
                if (($fact['fact_key'] ?? null) === $key && $this->sourceValueAllowsClaim($fact['original_value'] ?? null)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function sourceValueAllowsClaim(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return filled($value);
    }

    private function validatePayload(array &$payload, Product $product, array $config): void
    {
        if ((int) ($payload['product_id'] ?? 0) !== (int) $product->id) {
            throw new RuntimeException('AI output không khớp sản phẩm đang xử lý.');
        }

        if (($config['outputs']['content'] ?? false) && blank($payload['content_html'])) {
            throw new RuntimeException('AI output thiếu content_html.');
        }

        if (($config['outputs']['content'] ?? false) && ! in_array('missing_technical_data', $payload['warnings'], true)) {
            $minimumWords = $this->isCommercialProduct($product) ? 1200 : 800;
            $words = $this->scorer->wordCount($payload['content_html']);
            if ($words < (int) floor($minimumWords * 0.75)) {
                throw new RuntimeException("CONTENT_TOO_SHORT: {$words}/{$minimumWords}");
            }
            $minimumWords = $this->isCommercialProduct($product) ? 1200 : 800;
            $words = $this->scorer->wordCount($payload['content_html']);
            if ($words < $minimumWords && $words >= (int) floor($minimumWords * 0.75)) {
                $payload['warnings'] = $this->normalizeIssueList($payload['warnings'], ["content_too_short:{$words}/{$minimumWords}"]);
            } elseif ($words < $minimumWords) {
                throw new RuntimeException("AI output content quá ngắn ({$words}/{$minimumWords} từ).");
            }
        }

        if (($config['outputs']['content'] ?? false)) {
            $this->structureValidator->assert($payload['content_html']);
        }

        if (false && ($config['outputs']['content'] ?? false) && (! str_contains(Str::lower($payload['content_html']), '<h2') || ! str_contains(Str::lower($payload['content_html']), '<h3'))) {
            throw new RuntimeException('AI output thiếu H2/H3.');
        }

        if (($config['outputs']['faq'] ?? false) && count($payload['faq']) < 3) {
            throw new RuntimeException('AI output FAQ phải có ít nhất 3 câu.');
        }
    }

    private function applyPayload(Product $product, array $payload, array $config, int $scoreBefore, ?int $userId): void
    {
        DB::transaction(function () use ($product, $payload, $config, $scoreBefore, $userId) {
            $this->backupProduct($product, $userId);
            $mode = $config['mode'];
            $updates = [];

            if (($config['outputs']['content'] ?? false)) {
                if ($this->shouldUpdate($product->short_description, $mode, $scoreBefore) && filled($payload['excerpt'])) {
                    $updates['short_description'] = $payload['excerpt'];
                }
                if ($this->shouldUpdate($product->long_description, $mode, $scoreBefore) && filled($payload['content_html'])) {
                    $updates['long_description'] = $payload['content_html'];
                }
            }

            if (($config['outputs']['seo'] ?? false)) {
                if ($this->shouldUpdate($product->seo_title, $mode, $scoreBefore) && filled($payload['seo_title'])) {
                    $updates['seo_title'] = $payload['seo_title'];
                }
                if ($this->shouldUpdate($product->seo_description, $mode, $scoreBefore) && filled($payload['meta_description'])) {
                    $updates['seo_description'] = $payload['meta_description'];
                }
            }

            if (($config['outputs']['og'] ?? false)) {
                if ($this->shouldUpdate($product->og_title, $mode, $scoreBefore) && filled($payload['og_title'])) {
                    $updates['og_title'] = $payload['og_title'];
                }
                if ($this->shouldUpdate($product->og_description, $mode, $scoreBefore) && filled($payload['og_description'])) {
                    $updates['og_description'] = $payload['og_description'];
                }
            }

            if (($config['outputs']['merchant'] ?? false)) {
                if ($this->shouldUpdate($product->merchant_title, $mode, $scoreBefore) && filled($payload['merchant_title'])) {
                    $updates['merchant_title'] = $payload['merchant_title'];
                }
                if ($this->shouldUpdate($product->merchant_description, $mode, $scoreBefore) && filled($payload['merchant_description'])) {
                    $updates['merchant_description'] = $payload['merchant_description'];
                }
                if ($this->shouldUpdate($product->google_product_category, $mode, $scoreBefore) && filled($product->google_product_category)) {
                    $updates['google_product_category'] = $product->google_product_category;
                }
                if ($this->shouldUpdate($product->product_type, $mode, $scoreBefore) && filled($product->category?->name)) {
                    $updates['product_type'] = $product->product_type ?: $this->productType($product);
                }
            }

            if ($updates !== []) {
                $product->update($updates);
            }

            if (($config['outputs']['tags'] ?? false) && ($mode !== 'missing_only' || ! $product->tags()->exists())) {
                $this->syncTags($product, $payload['tags']);
            }

            if (($config['outputs']['faq'] ?? false) && ($mode !== 'missing_only' || ! $product->faqs()->exists())) {
                $this->syncFaq($product, $payload['faq']);
            }
        });
    }

    private function backupProduct(Product $product, ?int $userId): void
    {
        $product->loadMissing(['tags', 'faqs']);
        AiProductContentVersion::create([
            'product_id' => $product->id,
            'old_excerpt' => $product->short_description,
            'old_content' => $product->long_description,
            'old_seo_json' => [
                'seo_title' => $product->seo_title,
                'seo_description' => $product->seo_description,
                'og_title' => $product->og_title,
                'og_description' => $product->og_description,
            ],
            'old_merchant_json' => [
                'merchant_title' => $product->merchant_title,
                'merchant_description' => $product->merchant_description,
                'google_product_category' => $product->google_product_category,
                'product_type' => $product->product_type,
            ],
            'old_tags_json' => $product->tags->map->only(['id', 'name', 'slug'])->values()->all(),
            'old_faq_json' => $product->faqs->map->only(['question', 'answer'])->values()->all(),
            'created_by' => $userId,
        ]);
    }

    private function shouldUpdate(mixed $currentValue, string $mode, int $scoreBefore): bool
    {
        return match ($mode) {
            'missing_only' => blank($currentValue),
            'rewrite_weak' => $scoreBefore < 70,
            'rewrite_all', 'force_overwrite' => true,
            default => false,
        };
    }

    private function syncTags(Product $product, array $tags): void
    {
        $tagIds = [];
        foreach ($tags as $name) {
            if (blank($name)) {
                continue;
            }
            $tag = Tag::firstOrCreate(['name' => trim((string) $name)], ['slug' => Str::slug((string) $name)]);
            $tagIds[] = $tag->id;
        }
        if ($tagIds !== []) {
            $product->tags()->sync($tagIds);
        }
    }

    private function syncFaq(Product $product, array $faq): void
    {
        $product->faqs()->detach();
        foreach ($faq as $index => $item) {
            $faqModel = Faq::create([
                'question' => $item['question'],
                'answer' => $item['answer'],
                'group' => 'product',
                'sort_order' => $index + 1,
                'is_active' => true,
            ]);
            $product->faqs()->attach($faqModel->id, ['sort_order' => $index + 1]);
        }
    }

    private function detectDuplicateWarnings(Product $product, string $content): array
    {
        $plain = Str::limit(strip_tags($content), 4000, '');
        if ($plain === '') {
            return [];
        }

        $candidates = Product::query()
            ->whereKeyNot($product->id)
            ->when($product->product_category_id, fn ($query) => $query->where('product_category_id', $product->product_category_id))
            ->whereNotNull('long_description')
            ->latest('updated_at')
            ->limit(20)
            ->pluck('long_description');

        foreach ($candidates as $candidate) {
            similar_text(Str::ascii(Str::lower($plain)), Str::ascii(Str::lower(Str::limit(strip_tags((string) $candidate), 4000, ''))), $percent);
            if ($percent >= 85) {
                return ['duplicate_content_risk'];
            }
        }

        return [];
    }

    private function isCommercialProduct(Product $product): bool
    {
        $category = Str::lower($product->category?->name ?? '');

        return (int) ($this->technicalFacts->value($product, 'marketing_capacity_btu') ?? 0) >= 48000
            || Str::contains($category, ['vrf', 'gmv', 'rooftop', 'commercial', 'lac', 'ống gió', 'duct']);
    }

    /**
     * Check if blocked_claims contain critical severity issues that must hard-block.
     *
     * Critical: code leaks, XSS, mojibake, fake SKU/model/brand.
     * NOT critical: unverified_numeric_claim, unverified_technical_claim,
     *              business_claim_needs_rewrite, missing_airflow, etc.
     */
    private function hasCriticalBlock(array $blockedClaims): bool
    {
        $criticalPrefixes = [
            'code_leak:',
            'unsafe_html:',
            'broken_utf8',
            'mojibake_detected',
            'content_safety:code_leak',
            'content_safety:unsafe_html',
            'content_safety:broken_utf8',
            'content_safety:mojibake',
            'internal_language_detected:namespace',
            'internal_language_detected:method_signature',
            'internal_language_detected:raw_variable',
            'contradicted_technical_capacity:',
            'ambiguous_capacity_claim:',
            'FACT_CHECK_BLOCKED',
        ];

        foreach ($blockedClaims as $claim) {
            foreach ($criticalPrefixes as $prefix) {
                if (str_starts_with($claim, $prefix) || $claim === $prefix) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Auto-rewrite unverified claims in AI payload before saving.
     *
     * Removes sentences containing unverified technical claims and
     * replaces them with safe neutral statements. Re-runs validation
     * to ensure the cleaned content passes.
     */
    private function autoRewriteUnverifiedClaims(array $payload, Product $product): array
    {
        $textKeys = ['excerpt', 'content_html', 'seo_title', 'meta_description', 'og_title', 'og_description', 'merchant_title', 'merchant_description'];
        $rewrittenClaims = [];

        // Extract the actual claim texts from blocked_claims
        $claimTexts = [];
        foreach ($payload['blocked_claims'] as $blocked) {
            if (str_starts_with($blocked, 'unverified_numeric_claim:') || str_starts_with($blocked, 'unverified_technical_claim:')) {
                $claimTexts[] = explode(':', $blocked, 2)[1] ?? '';
            }
            if (str_starts_with($blocked, 'unverified_formula_claim:')) {
                $claimTexts[] = explode(':', $blocked, 2)[1] ?? '';
            }
        }

        if ($claimTexts === []) {
            return $payload;
        }

        foreach ($textKeys as $key) {
            $text = (string) ($payload[$key] ?? '');
            if ($text === '') {
                continue;
            }

            foreach ($claimTexts as $claimText) {
                if ($claimText === '' || ! str_contains($text, $claimText)) {
                    continue;
                }

                // Find and rewrite the sentence containing the claim
                $sentences = preg_split('/(?<=[.!?。！？])\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [$text];
                foreach ($sentences as &$sentence) {
                    if (str_contains($sentence, $claimText)) {
                        // Replace sentence with neutral HVAC statement
                        $sentence = 'Thông số kỹ thuật chi tiết vui lòng tham khảo tab thông số hoặc liên hệ tư vấn.';
                        $rewrittenClaims[] = $claimText;
                        break;
                    }
                }
                $text = implode(' ', $sentences);
            }

            $payload[$key] = $text;
        }

        // Update warnings to reflect rewrites
        if ($rewrittenClaims !== []) {
            $payload['warnings'] = $this->normalizeIssueList(
                $payload['warnings'],
                array_map(fn (string $claim): string => 'unverified_claim_rewritten:'.$claim, array_unique($rewrittenClaims))
            );
            // Remove the rewritten claims from blocked list
            $payload['blocked_claims'] = array_values(array_filter(
                $payload['blocked_claims'],
                function (string $blocked) use ($rewrittenClaims): bool {
                    foreach ($rewrittenClaims as $claim) {
                        if (str_ends_with($blocked, ':'.$claim)) {
                            return false;
                        }
                    }

                    return true;
                }
            ));
        }

        return $payload;
    }

    private function productType(Product $product): string
    {
        return collect(['Điều hòa', $product->category?->name, $product->brand?->name])
            ->filter()
            ->implode(' > ');
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
Bạn là AI Product Content System cho sản phẩm HVAC. Luôn trả về JSON hợp lệ, tiếng Việt UTF-8 có dấu, không markdown ngoài JSON.
Không bịa thông số, không sai model, không sai BTU, không sai brand/category, không fake giá, không ghi bảo hành nếu input không có dữ liệu. Không nhồi keyword, không duplicate giữa các sản phẩm, có chiều sâu HVAC và CTA nhẹ.
Chỉ được dùng dữ liệu đã xác minh trong ngữ cảnh đầu vào. Không tự tính BTU, không tự tạo công thức, không tự đoán diện tích phù hợp. Nếu thiếu dữ liệu, ghi warnings và viết trung lập.
Toàn bộ nội dung hiển thị phải là tiếng Việt có dấu, mã hóa UTF-8 sạch. Không trả về tiếng Việt không dấu, ký tự lỗi, text vỡ dấu hoặc tên kỹ thuật nội bộ.
PROMPT;
    }

    private function buildPrompt(array $input, array $config, array $guardContext): string
    {
        $guardJson = json_encode($this->governance->publicContext($guardContext), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $inputJson = json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $outputJson = json_encode($config['outputs'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $categoryLogic = $this->categoryLogic((string) data_get($input, 'product_identity.category.name', ''));
        $contentOnlyRetry = ($config['action'] ?? '') === 'retry_ai_product_field'
            && ($config['outputs']['content'] ?? false)
            && count(array_filter($config['outputs'], fn ($enabled): bool => (bool) $enabled)) === 1;
        $retryInstruction = $contentOnlyRetry
            ? "FIELD-LEVEL RETRY: chỉ tạo content_html. Không tạo lại SEO, OG, Merchant, FAQ, tags hoặc excerpt. Content phải có tối thiểu 1000 từ tiếng Việt (mục tiêu 1000-1200), gồm ít nhất 2 thẻ HTML <h2> và 3 thẻ <h3> cùng các đoạn thực tế; tự kiểm đếm và kiểm tra thẻ trước khi trả JSON."
            : '';

        return <<<PROMPT
NGỮ CẢNH DỮ LIỆU ĐÃ XÁC MINH (bắt buộc tuân thủ):
{$guardJson}

INPUT PRODUCT DATA:
{$inputJson}

OUTPUT FLAGS:
{$outputJson}

CONFIG:
- mode: {$config['mode']}
- depth: {$config['depth']}
- tone: {$config['tone']}

CONTENT SCOPE CONTRACT:
- AI is a content and SEO writer, not a Product Data or catalog authority.
- Use only Product facts supplied in this request/context for Product-specific claims.
- If a fact is absent, omitted, conflicted, or not supplied, omit the claim.
- Never infer room area, HP, BTU conversions, capacity, electrical values, refrigerant, warranty, price, availability, or regional equivalence.
- Never correct Product data and never mention internal provenance, data quality, source gates, or internal implementation in customer-facing copy.
- General writing, benefits, structure, CTA, and SEO phrasing may be creative only when they do not create new Product-specific facts.

JSON output bắt buộc:
{
  "content_layer": {
    "excerpt": "",
    "content_html": "",
    "seo_title": "",
    "meta_description": "",
    "og_title": "",
    "og_description": "",
    "merchant_title": "",
    "merchant_description": "",
    "tags": [],
    "faq": [{"question": "", "answer": ""}],
    "internal_links": [],
    "media_metadata": []
  },
  "used_verified_facts": ["fact_id trong verified_technical_facts, ví dụ fact_1"],
  "warnings": ["encoding_checked", "vietnamese_verified"],
  "blocked_claims": []
}

AI governance rule:
- Chỉ tạo AI Content Layer: excerpt, content_html, SEO, OG, Google Merchant text, tags, FAQ, internal links, media metadata.
- Không trả về và không đề xuất cập nhật Product Data Layer: thông tin cơ bản, model, SKU, brand, category, slug, giá, trạng thái, thông số kỹ thuật, technical_specs_json.
- verified_technical_facts chỉ dùng để tham chiếu trong nội dung; không được sửa dữ liệu gốc.
- MARKETING_IDENTITY_FACTS và VERIFIED_TECHNICAL_FACTS là hai nhóm khác nhau. capacity_group_btu chỉ dùng cho nhóm/phân khúc/dòng máy thương mại.
- rated_cooling_capacity_btu là công suất kỹ thuật/định mức/danh định/công suất lạnh. Không được dùng capacity_group_btu cho các cách gọi kỹ thuật này.
- Không viết câu mơ hồ "máy có công suất X BTU" khi hai giá trị khác nhau; phải nói rõ "thuộc nhóm công suất thương mại" hoặc "công suất kỹ thuật".
- Chỉ được dùng giá trị trong dữ liệu đã xác minh để viết thông số kỹ thuật, BTU, kW, HP, diện tích, độ ồn, kích thước, trọng lượng, gas, bảo hành, giá, VAT, CO/CQ.
- Khi dùng một dữ liệu trong verified_technical_facts, ghi đúng id công khai của dữ liệu đó vào used_verified_facts; không ghi giá trị rời như "GREE", "18000" hoặc tên biến nội bộ.
- Không tự suy diễn công thức BTU, hệ số BTU/m2, diện tích phù hợp hoặc tải lạnh. Nếu chưa có kết quả tính toán đã xác minh thì không đưa số BTU cụ thể và thêm warning "missing_btu_inputs".
- Nếu thiếu lưu lượng gió, độ ồn, bảo hành, nguồn catalogue, giá hoặc xuất xứ thì bỏ qua claim tương ứng hoặc thêm warning phù hợp.
- Chỉ thêm warning missing_* nếu mã đó xuất hiện trong missing_facts của ngữ cảnh; không tự khai thiếu thông số đã có trong allowed_facts.
- Tuyệt đối không đưa tên service, class, function, API, biến nội bộ, CamelCase hoặc cú pháp code vào các trường nội dung hiển thị.
- Nếu phát hiện nội dung có thể vượt nguồn dữ liệu, đưa mã vào blocked_claims thay vì viết thành khẳng định.

Content rule:
- content_html is an HTML fragment, not Markdown and not a full HTML document.
- content_html MUST contain at least one non-empty <h2>...</h2> element and at least one non-empty <h3>...</h3> element.
- Use this structural skeleton inside content_html: <h2>...</h2><p>...</p><h3>...</h3><p>...</p>.
- Do not write "## heading" or escaped literal "&lt;h2&gt;" text as a substitute for HTML headings.
- Sản phẩm thường: content_html tối thiểu 800 từ; hãy viết thực tế khoảng 900-1200 từ để không rơi dưới ngưỡng.
- Sản phẩm LAC/commercial/VRF/GMV/Rooftop/ống gió hoặc >= 48.000 BTU: 1200-1800 từ.
- Có H2/H3, giới thiệu sản phẩm, điểm nổi bật kỹ thuật, ứng dụng thực tế, "Khi nào nên dùng", lưu ý lắp đặt/vận hành, CTA nhẹ.
- Nếu thiếu thông số kỹ thuật, viết ngắn hơn nhưng không fake và thêm warning "missing_technical_data".
- Toàn bộ excerpt, content_html, SEO, OG, Google Merchant, tag và FAQ phải là tiếng Việt có dấu hoặc tag slug hợp lệ như "cassette inverter", "24000btu", "gree".
- Không dùng text bị lỗi dấu, ký tự lạ, text vỡ mã hóa hoặc tiếng Việt không dấu.
{$retryInstruction}

HVAC category logic:
{$categoryLogic}

Safety:
- HTML chỉ dùng h2, h3, p, ul, ol, li, strong, em, table, thead, tbody, tr, th, td, a.
- Không có script, inline style, placeholder, undefined, N/A, lorem, raw variable.
- FAQ 3-5 câu hỏi kỹ thuật thực tế nếu output faq bật.
- Internal links chỉ dùng URL nội bộ bắt đầu bằng "/".
PROMPT;
    }

    private function categoryLogic(string $category): string
    {
        $category = Str::lower($category);

        return match (true) {
            Str::contains($category, 'cassette') => '- Cassette: nói về âm trần, phân phối gió, không gian thương mại, trần giả.',
            Str::contains($category, ['duct', 'ống gió']) => '- Duct/ống gió: nói về giấu trần, ống gió, thẩm mỹ, phân phối gió.',
            Str::contains($category, 'tủ đứng') => '- Tủ đứng: nói về công suất lớn, lắp đặt nhanh, không gian rộng.',
            Str::contains($category, ['vrf', 'gmv']) => '- VRF/GMV: nói về hệ thống trung tâm, nhiều dàn lạnh, công trình lớn.',
            Str::contains($category, 'rooftop') => '- Rooftop: nói về packaged unit, lắp mái, nhà xưởng/tòa nhà lớn.',
            default => '- Dựa trên brand/category/model/BTU có trong input, không suy đoán ngoài dữ liệu.',
        };
    }
}
