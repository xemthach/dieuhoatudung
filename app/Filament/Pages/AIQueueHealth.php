<?php

namespace App\Filament\Pages;

use App\Services\AI\AIQueueMonitor;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class AIQueueHealth extends Page
{
    protected string $view = 'filament.pages.ai-queue-health';

    protected static ?string $title = 'Trạng thái vận hành AI';

    protected static ?string $navigationLabel = 'Trạng thái AI';

    protected static ?int $navigationSort = 1;

    public array $health = [];

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-signal';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Vận hành';
    }

    public static function canAccess(): bool
    {
        return (auth()->user()?->can('ai_content_job.view') || auth()->user()?->can('product.ai_generate')) ?? false;
    }

    public function mount(AIQueueMonitor $monitor): void
    {
        $this->health = $monitor->health();
    }

    public function reload(): void
    {
        $this->health = app(AIQueueMonitor::class)->health();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reload')
                ->label('Làm mới trạng thái')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(fn () => $this->reload()),
            Action::make('recover_stuck')
                ->label('Khôi phục job bị kẹt')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->visible(fn (): bool => (bool) auth()->user()?->can('product.ai_generate'))
                ->disabled(fn (): bool => (int) data_get($this->health, 'ai_jobs_stuck_count', 0) === 0)
                ->requiresConfirmation()
                ->modalHeading('Khôi phục job AI bị kẹt?')
                ->modalDescription('Chỉ các job vượt ngưỡng stale hiện hành mới được kiểm tra. Cơ chế quyền, idempotency và retry vẫn được áp dụng.')
                ->action(function (): void {
                    abort_unless(auth()->user()?->can('product.ai_generate'), 403);
                    $result = app(AIQueueMonitor::class)->recoverStuck();
                    $this->reload();
                    Notification::make()
                        ->title('Recover stuck completed')
                        ->body('Redispatched: '.$result['redispatched'].', failed: '.$result['failed'])
                        ->success()
                        ->send();
                }),
        ];
    }
}
