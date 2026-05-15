<?php

namespace App\Services\Product;

use App\Models\AiProductContentVersion;
use App\Models\AiProductJob;
use App\Models\AiProductJobItem;
use App\Models\Faq;
use App\Models\Product;
use App\Models\Tag;
use App\Services\AI\AIContentGovernance;
use App\Services\AI\AIManager;
use App\Services\AI\AITechnicalLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class AIProductContentSystem
{
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

    public function __construct(
        private readonly AIManager $aiManager,
        private readonly AIProductSeoScorer $scorer,
        private readonly AIProductContentSanitizer $sanitizer,
        ?AIContentGovernance $governance = null,
        ?AITechnicalLogger $technicalLogger = null,
    ) {
        $this->governance = $governance ?? app(AIContentGovernance::class);
        $this->technicalLogger = $technicalLogger ?? app(AITechnicalLogger::class);
    }

    public function normalizeConfig(array $config): array
    {
        $outputs = $config['outputs'] ?? [];
        if (! is_array($outputs)) {
            $outputs = [];
        }

        return [
            'mode' => $config['mode'] ?? 'missing_only',
            'depth' => $config['depth'] ?? 'seo',
            'tone' => $config['tone'] ?? 'hvac_expert',
            'batch_size' => max(1, min((int) ($config['batch_size'] ?? 10), 50)),
            'apply_mode' => $config['apply_mode'] ?? 'needs_review',
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
        ];
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
        $before = $this->scorer->score($product);

        if ($config['action'] === 'audit_seo') {
            return $this->completeAuditOnly($product, $before, $item);
        }

        if ($config['mode'] === 'rewrite_weak' && $before['score'] >= 70) {
            return $this->completeSkippedStrongContent($product, $before, $item);
        }

        $product->update([
            'ai_status' => 'processing',
            'ai_last_run_at' => now(),
            'ai_error_message' => null,
        ]);

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
        ]);

        $payload = $result['json'] ?? [];
        if ($payload === [] && ! empty($result['content'])) {
            $payload = json_decode($result['content'], true) ?: [];
        }

        $payload = $this->normalizePayload($payload, $product, $config);
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
            $payload['warnings'],
            $factCheck['warnings'],
            $this->detectDuplicateWarnings($product, $payload['content_html']),
            $this->scorer->auditWarnings($product)
        );
        $payload['warnings'] = $warnings;
        $payload['blocked_claims'] = $this->normalizeIssueList($payload['blocked_claims'] ?? [], $factCheck['blocked_claims']);
        $payload['used_facts'] = $factCheck['used_facts'];
        $payload['fact_check'] = $factCheck;
        $payload['governance_context'] = $this->governance->publicContext($guardContext);

        if ($payload['blocked_claims'] !== []) {
            $status = $config['apply_mode'] === 'auto_apply' ? 'blocked' : 'needs_review';
            $message = ($status === 'blocked' ? 'AI output bi chan fact-check: ' : 'AI output can duyet fact-check: ')
                .implode(', ', $payload['blocked_claims']);

            $product->update([
                'ai_status' => $status,
                'ai_score' => $before['score'],
                'ai_warning_count' => count($warnings),
                'ai_error_message' => $message,
                'ai_last_run_at' => now(),
            ]);

            $item?->update([
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
            $this->technicalLogger->event('ai_product_content', $status === 'blocked' ? 'fact_check_failed' : 'fact_check_needs_review', $message, [
                'warnings' => $warnings,
                'blocked_claims' => $payload['blocked_claims'],
                'provider' => $result['provider'] ?? null,
                'model' => $result['model'] ?? null,
            ], $item, 'warning');

            Log::warning('AI product content requires governance review', [
                'ai_product_job_id' => $job?->id,
                'product_id' => $product->id,
                'status' => $status,
                'prompt_version' => $guardContext['prompt_version'],
                'allowed_facts' => $guardContext['allowed_facts'],
                'missing_facts' => $guardContext['missing_facts'],
                'warnings' => $warnings,
                'blocked_claims' => $payload['blocked_claims'],
                'fact_check' => $factCheck,
                'provider' => $result['provider'] ?? null,
                'model' => $result['model'] ?? null,
            ]);

            return [
                'payload' => $payload,
                'score_before' => $before,
                'score_after' => $before,
                'status' => $status,
            ];
        }

        $applied = false;
        if ($config['apply_mode'] === 'auto_apply') {
            $this->applyPayload($product, $payload, $config, $before['score'], $userId);
            $applied = true;
            $product->refresh()->loadMissing(['brand', 'category', 'tags', 'faqs', 'relatedProducts', 'posts']);
        }

        $after = $this->scorer->score($product, $warnings);
        $status = $applied
            ? ($after['score'] < 70 ? 'needs_review' : ($warnings === [] ? 'completed_verified' : 'completed_with_warnings'))
            : 'needs_review';

        $product->update([
            'ai_status' => $status,
            'ai_score' => $after['score'],
            'ai_warning_count' => count($warnings),
            'ai_error_message' => null,
            'ai_last_run_at' => now(),
            'ai_generated_at' => now(),
        ]);

        $item?->update([
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
        $this->technicalLogger->event('ai_product_content', 'job_completed', 'AI product content generated.', [
            'status' => $status,
            'warnings' => $warnings,
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

        if (($item->status === 'blocked') || ! empty($item->generated_payload_json['blocked_claims'] ?? [])) {
            $product->update([
                'ai_status' => 'blocked',
                'ai_error_message' => 'Không thể áp dụng bản nháp AI vì nội dung chưa vượt qua bước kiểm tra.',
                'ai_last_run_at' => now(),
            ]);

            return null;
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

    public function rollback(Product $product, ?int $versionId = null): ?AiProductContentVersion
    {
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
        $item?->update([
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
        $item?->update([
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
        return [
            'product_id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'brand' => $product->brand?->only(['id', 'name', 'slug']),
            'category' => $product->category?->only(['id', 'name', 'slug']),
            'model_code' => $product->model_code,
            'sku' => $product->sku,
            'capacity_btu' => $product->btu,
            'capacity_kw' => $product->capacity_kw,
            'cooling_type' => $product->cooling_type,
            'inverter' => $product->inverter,
            'phase' => $product->voltage,
            'refrigerant' => $product->refrigerant_gas,
            'technical_specs' => Arr::only($product->toArray(), [
                'power_consumption', 'airflow', 'noise_level', 'indoor_dimensions',
                'outdoor_dimensions', 'weight', 'recommended_area', 'hp',
            ]),
            'technical_specs_json' => $product->specs_json ?? [],
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
            'related_products' => $product->relatedProducts->take(5)->map->only(['id', 'name', 'slug', 'model_code', 'btu'])->values()->all(),
            'related_posts' => $product->posts->take(5)->map->only(['id', 'title', 'slug'])->values()->all(),
        ];
    }

    private function normalizePayload(array $payload, Product $product, array $config): array
    {
        if (is_array($payload['content'] ?? null)) {
            $payload = array_merge($payload['content'], [
                'product_id' => $payload['product_id'] ?? $payload['content']['product_id'] ?? null,
                'warnings' => $payload['warnings'] ?? $payload['content']['warnings'] ?? [],
                'used_facts' => $payload['used_facts'] ?? $payload['content']['used_facts'] ?? [],
                'blocked_claims' => $payload['blocked_claims'] ?? $payload['content']['blocked_claims'] ?? [],
                'internal_links' => $payload['internal_links'] ?? $payload['content']['internal_links'] ?? [],
            ]);
        }

        $payload['product_id'] = (int) ($payload['product_id'] ?? $product->id);
        foreach (['excerpt', 'content_html', 'seo_title', 'meta_description', 'og_title', 'og_description', 'merchant_title', 'merchant_description'] as $key) {
            $payload[$key] = (string) ($payload[$key] ?? '');
        }
        $payload['tags'] = is_array($payload['tags'] ?? null) ? $payload['tags'] : [];
        $payload['faq'] = is_array($payload['faq'] ?? null) ? $payload['faq'] : [];
        $payload['internal_links'] = is_array($payload['internal_links'] ?? null) ? $payload['internal_links'] : [];
        $payload['warnings'] = $this->normalizeIssueList($payload['warnings'] ?? []);
        $payload['used_facts'] = is_array($payload['used_facts'] ?? null) ? $payload['used_facts'] : [];
        $payload['blocked_claims'] = $this->normalizeIssueList($payload['blocked_claims'] ?? []);

        $payload = $this->sanitizer->sanitizePayload($payload);
        $this->validatePayload($payload, $product, $config);

        return $payload;
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
            if ($words < $minimumWords && $words >= (int) floor($minimumWords * 0.75)) {
                $payload['warnings'] = $this->normalizeIssueList($payload['warnings'], ["content_too_short:{$words}/{$minimumWords}"]);
            } elseif ($words < $minimumWords) {
                throw new RuntimeException("AI output content quá ngắn ({$words}/{$minimumWords} từ).");
            }
        }

        if (($config['outputs']['content'] ?? false) && (! str_contains(Str::lower($payload['content_html']), '<h2') || ! str_contains(Str::lower($payload['content_html']), '<h3'))) {
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
                if ($this->shouldUpdate($product->google_product_category, $mode, $scoreBefore)) {
                    $updates['google_product_category'] = $product->google_product_category ?: '604';
                }
                if ($this->shouldUpdate($product->product_type, $mode, $scoreBefore)) {
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

        return $product->btu >= 48000
            || Str::contains($category, ['vrf', 'gmv', 'rooftop', 'commercial', 'lac', 'ống gió', 'duct']);
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
        $categoryLogic = $this->categoryLogic((string) data_get($input, 'category.name', ''));

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

JSON output bắt buộc:
{
  "product_id": {$input['product_id']},
  "content": {
    "excerpt": "",
    "content_html": "",
    "seo_title": "",
    "meta_description": "",
    "og_title": "",
    "og_description": "",
    "merchant_title": "",
    "merchant_description": "",
    "tags": [],
    "faq": [{"question": "", "answer": ""}]
  },
  "used_facts": [],
  "warnings": ["encoding_checked", "vietnamese_verified"],
  "blocked_claims": []
}

AI governance rule:
- Chỉ được dùng giá trị trong dữ liệu đã xác minh để viết thông số kỹ thuật, BTU, kW, HP, diện tích, độ ồn, kích thước, trọng lượng, gas, bảo hành, giá, VAT, CO/CQ.
- Không tự suy diễn công thức BTU, hệ số BTU/m2, diện tích phù hợp hoặc tải lạnh. Nếu chưa có kết quả tính toán đã xác minh thì không đưa số BTU cụ thể và thêm warning "missing_btu_inputs".
- Nếu thiếu lưu lượng gió, độ ồn, bảo hành, nguồn catalogue, giá hoặc xuất xứ thì bỏ qua claim tương ứng hoặc thêm warning phù hợp.
- Mọi dữ liệu đã dùng phải ghi bằng tên dễ hiểu cho người quản trị, không dùng tên biến nội bộ.
- Tuyệt đối không đưa tên service, class, function, API, biến nội bộ, CamelCase hoặc cú pháp code vào các trường nội dung hiển thị.
- Nếu phát hiện nội dung có thể vượt nguồn dữ liệu, đưa mã vào blocked_claims thay vì viết thành khẳng định.

Content rule:
- Sản phẩm thường: content_html 800-1200 từ.
- Sản phẩm LAC/commercial/VRF/GMV/Rooftop/ống gió hoặc >= 48.000 BTU: 1200-1800 từ.
- Có H2/H3, giới thiệu sản phẩm, điểm nổi bật kỹ thuật, ứng dụng thực tế, "Khi nào nên dùng", lưu ý lắp đặt/vận hành, CTA nhẹ.
- Nếu thiếu thông số kỹ thuật, viết ngắn hơn nhưng không fake và thêm warning "missing_technical_data".
- Toàn bộ excerpt, content_html, SEO, OG, Google Merchant, tag và FAQ phải là tiếng Việt có dấu hoặc tag slug hợp lệ như "cassette inverter", "24000btu", "gree".
- Không dùng text bị lỗi dấu, ký tự lạ, text vỡ mã hóa hoặc tiếng Việt không dấu.

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
