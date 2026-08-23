<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Filament\Resources\Posts\PostResource;
use App\Services\AI\PostAiWorkflowService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreatePost extends CreateRecord
{
    protected static string $resource = PostResource::class;

    private bool $generateWithAiAfterCreate = false;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save_and_generate_ai')
                ->label('Lưu bản nháp & tạo bằng AI')
                ->icon('heroicon-o-sparkles')
                ->color('info')
                ->visible(fn (): bool => (bool) auth()->user()?->can('ai_content_job.create'))
                ->action(function (): void {
                    $this->generateWithAiAfterCreate = true;
                    $this->create();
                }),
        ];
    }

    protected function afterCreate(): void
    {
        if (! $this->generateWithAiAfterCreate) {
            return;
        }

        $result = app(PostAiWorkflowService::class)->createForPost($this->record, $this->form->getRawState(), auth()->user(), newlyCreated: true);

        $notification = Notification::make()
            ->title('Đã nhận yêu cầu tạo nội dung AI')
            ->body(! $result['dispatched'] ? 'Chưa có AI Provider khả dụng.' : $result['worker_message'])
            ->status($result['dispatched'] && $result['worker_ready'] ? 'success' : 'warning');
        if (auth()->user()?->can('ai_worker.manage')) {
            $notification->actions([
                Action::make('manage_ai_worker')->label('Bật AI Worker')->url(\App\Filament\Pages\AIQueueHealth::getUrl()),
            ]);
        }
        $notification->send();
    }
}
