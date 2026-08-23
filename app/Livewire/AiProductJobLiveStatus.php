<?php

namespace App\Livewire;

use App\Models\AiProductJob;
use App\Services\AI\AIQueueMonitor;
use App\Services\AI\AiContentStatusPresenter;
use App\Services\AI\BulkRuntimeAuthorizationService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class AiProductJobLiveStatus extends Component
{
    public int $jobId;

    /** @var array<string, mixed> */
    public array $status = [];

    public function mount(int $jobId): void
    {
        $this->jobId = $jobId;
        $this->authorizeJob();
        $this->refreshStatus();
    }

    public function refreshStatus(): void
    {
        $job = $this->authorizeJob();
        $job->load('runtimeBatch');
        $itemCounts = $job->items()
            ->selectRaw('COUNT(*) as total_items')
            ->selectRaw("SUM(CASE WHEN canonical_status IN ('RUNNING', 'VALIDATING', 'FACT_CHECKING') THEN 1 ELSE 0 END) as running_items")
            ->selectRaw("SUM(CASE WHEN canonical_status = 'BLOCKED' THEN 1 ELSE 0 END) as blocked_items")
            ->first();
        $health = app(AIQueueMonitor::class)->liveStatusHealth();
        $view = app(AiContentStatusPresenter::class)->present($job->status, [
            'desired_state' => data_get($health, 'worker_desired_state', 'DISABLED'),
            'health' => data_get($health, 'worker_heartbeat.health_status', 'UNKNOWN'),
        ]);
        $total = (int) $job->total;

        $this->status = [
            'view' => $view,
            'warning' => $view['warning'],
            'safe_reason' => app(AiContentStatusPresenter::class)->safeReason($job->failed_reason ?: $job->last_error_code),
            'processed' => (int) $job->processed,
            'total' => $total,
            'percent' => $total > 1 ? (int) min(100, round(((int) $job->processed / $total) * 100)) : null,
            'success' => (int) $job->success,
            'review' => (int) $job->needs_review,
            'failed' => (int) $job->failed,
            'blocked' => (int) ($itemCounts?->blocked_items ?? 0),
            'running' => (int) ($itemCounts?->running_items ?? 0),
            'updated_human' => ($job->state_changed_at ?: $job->updated_at)?->diffForHumans(),
            'token_used' => $job->runtimeBatch?->token_consumed,
            'token_reserved' => $job->runtimeBatch?->token_reserved,
            'token_budget' => $job->runtimeBatch?->token_budget_total,
        ];
    }

    public function render(): View
    {
        return view('livewire.ai-product-job-live-status');
    }

    private function authorizeJob(): AiProductJob
    {
        $actor = auth()->user();
        abort_unless($actor?->can('bulk_ai_view'), 403);
        $job = AiProductJob::query()->findOrFail($this->jobId);
        abort_unless(app(BulkRuntimeAuthorizationService::class)->canViewJob($actor, $job), 403);

        return $job;
    }
}
