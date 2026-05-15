<?php

namespace App\Filament\Pages;

use App\Services\AI\AIQueueMonitor;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class AIQueueHealth extends Page
{
    protected string $view = 'filament.pages.ai-queue-health';

    protected static ?string $title = 'AI Queue Health';

    protected static ?string $navigationLabel = 'AI Queue Health';

    protected static ?int $navigationSort = 26;

    public array $health = [];

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-signal';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'SEO & AI';
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
                ->label('Reload status')
                ->icon('heroicon-o-arrow-path')
                ->action(fn () => $this->reload()),
            Action::make('recover_stuck')
                ->label('Retry stuck')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->requiresConfirmation()
                ->action(function (): void {
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
