<?php

namespace App\Filament\Widgets;

use App\Services\Operations\SystemHealthService;
use Filament\Widgets\Widget;

class SystemHealthWidget extends Widget
{
    protected string $view = 'filament.widgets.system-health';
    protected int|string|array $columnSpan = 'full';
    protected static ?int $sort = 10;

    public static function canView(): bool
    {
        return auth()->user()?->can('dashboard.view') ?? false;
    }

    protected function getViewData(): array
    {
        return ['health' => app(SystemHealthService::class)->snapshot()];
    }
}
