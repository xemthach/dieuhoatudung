<?php

namespace App\Livewire;

use App\Filament\Resources\AiProductJobs\AiProductJobResource;
use App\Models\AiProductJob;
use App\Models\Product;
use App\Services\AI\AiProductLiveStatusService;
use App\Services\AI\BulkRuntimeAuthorizationService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class AiProductLiveStatus extends Component
{
    public int $productId;

    /** @var array<string, mixed> */
    public array $status = [];

    public function mount(int $productId): void
    {
        abort_unless(auth()->user()?->can('product.view'), 403);
        abort_unless(Product::query()->whereKey($productId)->exists(), 404);

        $this->productId = $productId;
        $this->refreshStatus();
    }

    public function refreshStatus(): void
    {
        abort_unless(auth()->user()?->can('product.view'), 403);
        $this->status = app(AiProductLiveStatusService::class)->forProduct($this->productId);
    }

    public function jobUrl(): ?string
    {
        $actor = auth()->user();
        $jobId = (int) ($this->status['job_id'] ?? 0);

        if (! $actor?->can('bulk_ai_view') || $jobId < 1) {
            return null;
        }

        $job = AiProductJob::query()->find($jobId);

        if (! $job || ! app(BulkRuntimeAuthorizationService::class)->canViewJob($actor, $job)) {
            return null;
        }

        return AiProductJobResource::getUrl('edit', ['record' => $job]);
    }

    public function render(): View
    {
        return view('livewire.ai-product-live-status', [
            'jobUrl' => $this->jobUrl(),
        ]);
    }
}
