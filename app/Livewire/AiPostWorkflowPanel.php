<?php

namespace App\Livewire;

use App\Filament\Resources\AiContentJobs\AiContentJobResource;
use App\Models\AiContentJob;
use App\Models\Post;
use App\Services\AI\AiPostLiveStatusService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class AiPostWorkflowPanel extends Component
{
    public int $postId;

    /** @var array<string, mixed> */
    public array $status = [];

    public function mount(int $postId): void
    {
        $this->postId = $postId;
        $this->refreshStatus();
    }

    public function refreshStatus(): void
    {
        abort_unless(auth()->user()?->can('post.view'), 403);
        abort_unless(Post::query()->whereKey($this->postId)->exists(), 404);
        $this->status = app(AiPostLiveStatusService::class)->forPost($this->postId);
    }

    public function render(): View
    {
        $jobId = (int) ($this->status['job_id'] ?? 0);
        $jobUrl = null;
        if ($jobId && auth()->user()?->can('ai_content_job.view') && AiContentJob::query()->whereKey($jobId)->exists()) {
            $jobUrl = AiContentJobResource::getUrl('edit', ['record' => $jobId]);
        }

        return view('livewire.ai-post-workflow-panel', compact('jobUrl'));
    }
}
