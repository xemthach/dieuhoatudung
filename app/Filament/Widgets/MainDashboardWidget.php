<?php

namespace App\Filament\Widgets;

use App\Services\Dashboard\DashboardStatsService;
use Filament\Widgets\Widget;

class MainDashboardWidget extends Widget
{
    protected string $view = 'filament.widgets.main-dashboard';

    protected int | string | array $columnSpan = 'full';
    
    protected static ?int $sort = -10;

    public static function canView(): bool
    {
        return auth()->user()?->can('dashboard.view') ?? false;
    }

    protected function getViewData(): array
    {
        $stats = app(DashboardStatsService::class);
        $leads = $stats->getLeadStats();
        $products = $stats->getProductStats();
        $posts = $stats->getPostStats();
        $seoHealth = $stats->getSeoStats();
        $r2Status = $stats->getR2Status();
        $aiStatus = $stats->getAIStatus();
        $mailStatus = $stats->getMailStatus();

        return [
            'leads'        => $leads,
            'products'     => $products,
            'posts'        => $posts,
            'seoHealth'    => $seoHealth,
            'r2Status'     => $r2Status,
            'aiStatus'     => $aiStatus,
            'mailStatus'   => $mailStatus,
            'alerts'       => $stats->getAlerts($products, $posts, $r2Status, $mailStatus, $aiStatus),
            'quickActions' => $stats->getQuickActions(),
        ];
    }
}
