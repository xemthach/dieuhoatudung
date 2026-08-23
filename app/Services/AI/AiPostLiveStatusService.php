<?php

namespace App\Services\AI;

use App\Enums\AIContentJobStatus;
use App\Models\AiRequestLog;
use App\Models\Post;

final class AiPostLiveStatusService
{
    public function __construct(
        private PostAiWorkflowService $workflow,
        private AiContentStatusPresenter $presenter,
        private AIQueueMonitor $queueMonitor,
        private AiProviderReadinessService $providers,
    ) {}

    /** @return array<string, mixed> */
    public function forPost(Post|int $post): array
    {
        $post = $post instanceof Post ? $post : Post::query()->findOrFail($post);
        $job = $this->workflow->latestForPost($post);
        $health = $this->queueMonitor->liveStatusHealth();
        $worker = [
            'desired_state' => data_get($health, 'worker_desired_state', 'DISABLED'),
            'health' => data_get($health, 'worker_heartbeat.health_status', 'UNKNOWN'),
        ];
        $provider = $this->providers->summary();

        if (! $job) {
            return [
                'view' => $this->presenter->present('not_generated', $worker),
                'job_id' => null,
                'provider' => $provider['preferred'],
                'provider_ready' => $provider['ready'],
                'worker' => $worker,
                'fields' => [],
                'provider_request' => ['state' => 'NOT_STARTED', 'label' => 'Chưa gửi'],
                'updated_human' => null,
            ];
        }

        $payload = (array) $job->input_payload;
        $applied = filled($payload['applied_at'] ?? null);
        $displayState = $job->status;
        if (! $applied && in_array($job->status, [
            AIContentJobStatus::Completed,
            AIContentJobStatus::CompletedVerified,
            AIContentJobStatus::CompletedWithWarnings,
        ], true)) {
            $displayState = 'needs_review';
        }

        $view = $this->presenter->present($displayState, $worker, $applied);
        $request = AiRequestLog::query()
            ->where('context_id', (string) ($payload['context_id'] ?? 'hvac_blog_job_'.$job->id))
            ->latest('id')
            ->first();
        $requested = array_values((array) ($payload['requested_fields'] ?? array_keys(PostAiWorkflowService::OUTPUTS)));

        return [
            'view' => $view,
            'job_id' => (int) $job->id,
            'warning' => $view['warning'],
            'safe_reason' => $this->presenter->safeReason($job->failed_reason ?: $job->last_error_code),
            'provider' => $provider['preferred'],
            'provider_ready' => $provider['ready'],
            'worker' => $worker,
            'step' => $this->step($view['key']),
            'fields' => collect($requested)->map(fn (string $field): array => [
                'field' => $field,
                'label' => PostAiWorkflowService::OUTPUTS[$field] ?? $this->presenter->fieldLabel($field),
                'status' => $this->fieldStatus($field, $job, $view),
            ])->all(),
            'provider_request' => $this->providerRequest($request?->status),
            'provider_attempts' => $request ? AiRequestLog::query()->where('context_id', $request->context_id)->count() : 0,
            'provider_request_id' => $request?->id,
            'started_at' => $job->started_at?->toIso8601String(),
            'completed_at' => $job->finished_at?->toIso8601String(),
            'applied_at' => $payload['applied_at'] ?? null,
            'updated_human' => $job->updated_at?->diffForHumans(),
            'review_required' => $view['review_required'],
            'approved' => $job->status === AIContentJobStatus::Reviewed,
            'applied' => $applied,
        ];
    }

    /** @return array{current:int,total:int,label:string} */
    private function step(string $key): array
    {
        return match ($key) {
            'QUEUED' => ['current' => 1, 'total' => 4, 'label' => 'Đang chờ'],
            'PROCESSING' => ['current' => 2, 'total' => 4, 'label' => 'AI đang viết'],
            'VALIDATING' => ['current' => 3, 'total' => 4, 'label' => 'Đang kiểm tra'],
            'REVIEW_REQUIRED', 'APPROVED', 'APPLIED', 'COMPLETED', 'COMPLETED_WITH_ERRORS' => ['current' => 4, 'total' => 4, 'label' => 'Sẵn sàng duyệt'],
            default => ['current' => 0, 'total' => 4, 'label' => 'Chưa bắt đầu'],
        };
    }

    /** @return array<string, mixed> */
    private function fieldStatus(string $field, $job, array $view): array
    {
        $hasOutput = match ($field) {
            'content' => filled($job->output_draft),
            'seo' => filled(data_get($job->output_meta, 'seo_title')) || filled(data_get($job->output_meta, 'meta_description')),
            'faq' => ! empty($job->output_faq),
            'tags' => ! empty($job->output_tags),
            default => false,
        };

        return $hasOutput ? $this->presenter->present('completed') : $this->presenter->present($view['internal_status']);
    }

    /** @return array{state:string,label:string} */
    private function providerRequest(?string $status): array
    {
        return match ($status) {
            'success' => ['state' => 'COMPLETED', 'label' => 'Đã hoàn tất'],
            'failed', 'rate_limited' => ['state' => 'FAILED', 'label' => 'Thất bại'],
            'fallback' => ['state' => 'RETRYING', 'label' => 'Đang thử lại'],
            default => ['state' => 'NOT_STARTED', 'label' => 'Chưa gửi'],
        };
    }
}
