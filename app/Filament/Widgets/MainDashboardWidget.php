<?php

namespace App\Filament\Widgets;

use App\Services\Dashboard\DashboardStatsService;
use Filament\Widgets\Widget;

class MainDashboardWidget extends Widget
{
    protected string $view = 'filament.widgets.main-dashboard';

    protected int | string | array $columnSpan = 'full';
    
    protected static ?int $sort = -1;

    public static function canView(): bool
    {
        return auth()->user()?->can('dashboard.view') ?? false;
    }

    protected function getViewData(): array
    {
        $stats = app(DashboardStatsService::class);

        return [
            'leads'        => $stats->getLeadStats(),
            'products'     => $stats->getProductStats(),
            'posts'        => $stats->getPostStats(),
            'seoHealth'    => $stats->getSeoStats(),
            'r2Status'     => $stats->getR2Status(),
            'aiStatus'     => $stats->getAIStatus(),
            'mailStatus'   => $stats->getMailStatus(),
            'alerts'       => $stats->getAlerts(),
            'quickActions' => $stats->getQuickActions(),
        ];
    }
}
