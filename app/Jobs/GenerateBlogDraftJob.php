<?php

namespace App\Jobs;

use App\Enums\AIContentJobStatus;
use App\Models\AiContentJob;
use App\Services\AI\AIManager;
use App\Services\AI\AIProviderPool;
use App\Services\AI\AITechnicalLogger;
use App\Services\AI\HVACSeoContentEngine;
use App\Support\EncodingGuard;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GenerateBlogDraftJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 300;

    public function backoff(): array
    {
        return [60, 180];
    }

    public function __construct(
        public readonly int $aiContentJobId
    ) {}

    public function handle(AIManager $aiManager, HVACSeoContentEngine $contentEngine, ?AITechnicalLogger $technicalLogger = null): void
    {
        $technicalLogger ??= app(AITechnicalLogger::class);
        $job = AiContentJob::findOrFail($this->aiContentJobId);

        if (in_array($job->status, [
            AIContentJobStatus::Completed,
            AIContentJobStatus::CompletedVerified,
            AIContentJobStatus::CompletedWithWarnings,
            AIContentJobStatus::Reviewed,
        ], true)) {
            return;
        }

        if (! app(AIProviderPool::class)->hasAvailableProviders()) {
            $job->update([
                'status' => AIContentJobStatus::Failed,
                'error_message' => 'Không có AI Provider nào đang hoạt động.',
                'failed_reason' => 'missing_api_key',
                'last_error_code' => 'missing_api_key',
                'last_error_message' => 'No active AI provider.',
                'queue_name' => $this->queue ?: 'ai',
                'attempts' => $this->attempts(),
            ]);
            $technicalLogger->event('ai_blog', 'job_failed', 'No active AI provider.', ['failed_reason' => 'missing_api_key'], $job, 'error');

            return;
        }

        $startedAt = now();
        $job->update([
            'status' => AIContentJobStatus::Processing,
            'module' => 'ai_blog',
            'queue_name' => $this->queue ?: 'ai',
            'attempts' => $this->attempts(),
            'started_at' => $job->started_at ?? $startedAt,
            'finished_at' => null,
            'failed_reason' => null,
            'last_error_code' => null,
            'last_error_message' => null,
        ]);
        $technicalLogger->event('ai_blog', 'job_started', 'AI blog job started.', [
            'queue' => $this->queue ?: 'ai',
            'attempts' => $this->attempts(),
            'topic' => $job->topic,
        ], $job);

        $contextId = 'hvac_blog_'.$job->id.'_'.Str::random(8);

        try {
            Log::info('GenerateBlogDraftJob: Bắt đầu tạo nội dung HVAC SEO', [
                'job_id' => $job->id,
                'topic' => $job->topic,
            ]);

            $output = $contentEngine->generate($aiManager, $job, $contextId);
            $finishedAt = now();

            $job->update([
                'topic' => $output['title'],
                'primary_keyword' => $job->primary_keyword ?: ($output['tags'][0]['name'] ?? null),
                'output_outline' => EncodingGuard::jsonEncode([
                    'title' => $output['title'],
                    'slug' => $output['slug'],
                    'excerpt' => $output['excerpt'],
                ]),
                'output_draft' => $output['content'],
                'output_tags' => $output['tags'],
                'output_meta' => [
                    'title' => $output['title'],
                    'slug' => $output['slug'],
                    'excerpt' => $output['excerpt'],
                    'seo_title' => $output['seo_title'],
                    'meta_description' => $output['meta_description'],
                    'og_title' => $output['og_title'],
                    'og_description' => $output['og_description'],
                    'ai_governance' => [
                        'prompt_version' => $output['governance_context']['prompt_version'] ?? null,
                        'status' => empty($output['warnings'] ?? []) ? 'completed_verified' : 'completed_with_warnings',
                        'data_completeness' => $output['governance_context']['data_completeness'] ?? null,
                        'missing_facts' => $output['governance_context']['missing_facts'] ?? [],
                        'calculation_rules' => $output['governance_context']['calculation_rules'] ?? [],
                        'used_facts' => $output['used_facts'] ?? [],
                        'warnings' => $output['warnings'] ?? [],
                        'blocked_claims' => $output['blocked_claims'] ?? [],
                        'fact_check' => $output['fact_check'] ?? null,
                    ],
                ],
                'output_faq' => $output['faq'],
                'output_internal_links' => $output['internal_links'],
                'status' => empty($output['warnings'] ?? []) ? AIContentJobStatus::CompletedVerified : AIContentJobStatus::CompletedWithWarnings,
                'error_message' => null,
                'provider' => $output['provider'] ?? null,
                'model' => $output['model'] ?? null,
                'finished_at' => $finishedAt,
                'duration_ms' => (int) $job->started_at?->diffInMilliseconds($finishedAt),
                'failed_reason' => null,
                'last_error_code' => null,
                'last_error_message' => null,
            ]);

            Log::info('GenerateBlogDraftJob: Hoàn thành', ['job_id' => $job->id]);
            $technicalLogger->event('ai_blog', 'job_completed', 'AI blog job completed.', [
                'status' => empty($output['warnings'] ?? []) ? 'completed_verified' : 'completed_with_warnings',
                'warnings' => $output['warnings'] ?? [],
                'provider' => $output['provider'] ?? null,
                'model' => $output['model'] ?? null,
            ], $job);
        } catch (\Throwable $e) {
            if ($this->isRateLimit($e) && $this->attempts() < $this->tries) {
                $job->update([
                    'status' => AIContentJobStatus::Queued,
                    'error_message' => 'Rate limited, retrying later.',
                    'failed_reason' => 'provider_rate_limit',
                    'last_error_code' => 'provider_rate_limit',
                    'last_error_message' => 'Rate limited, retrying later.',
                    'retry_count' => (int) ($job->retry_count ?? 0) + 1,
                ]);
                $technicalLogger->event('ai_blog', 'job_retried', 'Provider rate limit; job released for retry.', [
                    'failed_reason' => 'provider_rate_limit',
                    'attempts' => $this->attempts(),
                    'queue' => $this->queue ?: 'ai',
                ], $job, 'warning');
                $this->release(60 * $this->attempts());

                return;
            }

            $technical = $technicalLogger->exception('ai_blog', $e, $job, [
                'queue' => $this->queue ?: 'ai',
                'attempts' => $this->attempts(),
            ]);
            Log::error('GenerateBlogDraftJob: Thất bại', [
                'job_id' => $job->id,
                'error' => $e->getMessage(),
            ]);

            $isBlocked = str_contains($e->getMessage(), 'fact-check');

            $job->update([
                'status' => $isBlocked ? AIContentJobStatus::Blocked : AIContentJobStatus::Failed,
                'error_message' => $e->getMessage(),
                'finished_at' => now(),
                'duration_ms' => (int) $job->started_at?->diffInMilliseconds(now()),
                ...$technical,
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        $technical = app(AITechnicalLogger::class)->exception('ai_blog', $exception, AiContentJob::find($this->aiContentJobId));

        AiContentJob::where('id', $this->aiContentJobId)
            ->where('status', AIContentJobStatus::Processing)
            ->update([
                'status' => AIContentJobStatus::Failed,
                'error_message' => $exception->getMessage(),
                'finished_at' => now(),
                ...$technical,
            ]);
    }

    private function isRateLimit(\Throwable $e): bool
    {
        $message = $e->getMessage();
        $decoded = json_decode($message, true);

        return (is_array($decoded) && ! empty($decoded['is_rate_limit']))
            || str_contains($message, '429')
            || stripos($message, 'rate limit') !== false;
    }
}
