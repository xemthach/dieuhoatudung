<?php

namespace App\Filament\Widgets;

use App\Services\AI\AIRuntimePolicyService;
use Filament\Widgets\Widget;

class AIRuntimePolicyWidget extends Widget
{
    protected static bool $isDiscovered = false;

    protected string $view = 'filament.widgets.ai-runtime-policy';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->can('bulk_ai_view') ?? false;
    }

    public function getPolicy(): array
    {
        return app(AIRuntimePolicyService::class)->snapshot();
    }
}
