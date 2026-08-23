<?php

namespace App\Filament\Pages;

use App\Services\AI\AIQueueMonitor;
use App\Services\AI\AIWorkerDesiredStateService;
use App\Services\AI\AIWorkerSelfTestService;
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
        return (auth()->user()?->can('ai_worker.view')
            || auth()->user()?->can('ai_worker.manage')
            || auth()->user()?->can('ai_content_job.view')
            || auth()->user()?->can('product.ai_generate')) ?? false;
    }

    public function mount(AIQueueMonitor $monitor): void
    {
        $this->health = $monitor->health();
    }

    public function reload(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->health = app(AIQueueMonitor::class)->health();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('enable_worker')
                ->label('Bật AI Worker')
                ->icon('heroicon-o-play')
                ->color('success')
                ->visible(fn (): bool => (bool) auth()->user()?->can('ai_worker.manage')
                    && data_get($this->health, 'worker_desired_state', 'DISABLED') === 'DISABLED')
                ->requiresConfirmation()
                ->modalDescription('Chỉ đổi desired state sang ENABLED. OS process manager/watchdog chịu trách nhiệm bảo đảm process hoạt động; HTTP không spawn worker.')
                ->action(function (AIWorkerDesiredStateService $desiredState): void {
                    abort_unless(auth()->user()?->can('ai_worker.manage'), 403);
                    $desiredState->set(AIWorkerDesiredStateService::ENABLED, auth()->user());
                    $this->reload();
                    $actual = data_get($this->health, 'worker_heartbeat.health_status', 'OFFLINE');
                    Notification::make()
                        ->title('Đã bật xử lý AI')
                        ->body($actual === 'ONLINE'
                            ? 'Worker đang online và có thể nhận job mới.'
                            : 'Desired state đã bật. Worker chưa online; process manager/watchdog cần khởi động process.')
                        ->status($actual === 'ONLINE' ? 'success' : 'warning')
                        ->send();
                }),
            Action::make('disable_worker')
                ->label('Tắt AI Worker')
                ->icon('heroicon-o-pause')
                ->color('warning')
                ->visible(fn (): bool => (bool) auth()->user()?->can('ai_worker.manage')
                    && data_get($this->health, 'worker_desired_state', 'DISABLED') === 'ENABLED')
                ->requiresConfirmation()
                ->modalHeading('Tắt AI Worker theo chế độ graceful?')
                ->modalDescription(function (): string {
                    $processing = (int) data_get($this->health, 'ai_content_processing_count', 0)
                        + (int) data_get($this->health, 'ai_product_processing_count', 0);

                    return $processing > 0
                        ? "Có {$processing} job đang xử lý. Job hiện tại được phép hoàn tất; worker sẽ không claim job mới."
                        : 'Worker sẽ ngừng claim job mới. Process có thể tiếp tục online ở trạng thái paused.';
                })
                ->action(function (AIWorkerDesiredStateService $desiredState): void {
                    abort_unless(auth()->user()?->can('ai_worker.manage'), 403);
                    $desiredState->set(AIWorkerDesiredStateService::DISABLED, auth()->user());
                    $this->reload();
                    Notification::make()
                        ->title('Đã yêu cầu tắt xử lý AI')
                        ->body('Graceful disable đã được ghi nhận. Không purge queue và không force-kill process.')
                        ->warning()
                        ->send();
                }),
            Action::make('reload')
                ->label('Làm mới trạng thái')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(fn () => $this->reload()),
            Action::make('worker_self_test')
                ->label('Kiểm tra Worker')
                ->icon('heroicon-o-beaker')
                ->color('info')
                ->visible(fn (): bool => (bool) auth()->user()?->can('ai_worker.manage'))
                ->requiresConfirmation()
                ->modalDescription('Tạo một diagnostic job nhẹ trên queue ai_governed. Job không gọi AI Provider và không đọc hoặc sửa Product.')
                ->action(function (AIWorkerSelfTestService $selfTest): void {
                    abort_unless(auth()->user()?->can('ai_worker.manage'), 403);
                    $result = $selfTest->dispatch(auth()->user());
                    $this->reload();
                    Notification::make()
                        ->title($result['created'] ? 'Đã gửi kiểm tra Worker' : 'Đã có kiểm tra Worker đang chờ')
                        ->body("Probe {$result['probe_id']} — {$result['status']}. Theo dõi trạng thái live trên trang này.")
                        ->status(data_get($this->health, 'worker_desired_state') === 'ENABLED' ? 'info' : 'warning')
                        ->send();
                }),
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
