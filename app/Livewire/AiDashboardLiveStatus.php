<?php

namespace App\Livewire;

use App\Services\Dashboard\DashboardStatsService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class AiDashboardLiveStatus extends Component
{
    /** @var array<string, mixed> */
    public array $status = [];

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('dashboard.view'), 403);
        $this->refreshStatus();
    }

    public function refreshStatus(): void
    {
        abort_unless(auth()->user()?->can('dashboard.view'), 403);
        $this->status = app(DashboardStatsService::class)->getAIStatus();
    }

    public function render(): View
    {
        return view('livewire.ai-dashboard-live-status');
    }
}
