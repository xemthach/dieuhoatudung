<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Filament\Resources\Posts\PostResource;
use App\Services\AI\PostAiWorkflowService;
use App\Services\Seo\InternalLinkSuggestionService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\HtmlString;

class EditPost extends EditRecord
{
    protected static string $resource = PostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate_ai_content')
                ->label(fn (): string => filled($this->record->content) ? 'Tạo lại bằng AI' : 'Tạo nội dung bằng AI')
                ->icon('heroicon-o-sparkles')
                ->color('info')
                ->visible(fn (): bool => (bool) auth()->user()?->can('ai_content_job.create'))
                ->requiresConfirmation()
                ->modalDescription('AI sẽ tạo một bản nháp riêng. Nội dung bài viết hiện tại không bị ghi đè.')
                ->action(function (): void {
                    $result = app(PostAiWorkflowService::class)->createForPost($this->record, $this->form->getRawState(), auth()->user());
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
                }),

            Action::make('compare_ai_draft')
                ->label('So sánh bản nháp AI')
                ->icon('heroicon-o-arrows-right-left')
                ->color('gray')
                ->visible(fn (): bool => filled(app(PostAiWorkflowService::class)->latestForPost($this->record)?->output_draft)
                    && (bool) auth()->user()?->can('ai_content_job.view'))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Đóng')
                ->modalContent(function (): HtmlString {
                    $draft = app(PostAiWorkflowService::class)->latestForPost($this->record)?->output_draft;

                    return new HtmlString('<div class="grid gap-4 md:grid-cols-2"><section><h3 class="font-semibold">Nội dung hiện tại</h3><pre class="mt-2 max-h-96 overflow-auto whitespace-pre-wrap rounded bg-gray-50 p-3">'.e(strip_tags((string) $this->record->content)).'</pre></section><section><h3 class="font-semibold">Bản nháp AI</h3><pre class="mt-2 max-h-96 overflow-auto whitespace-pre-wrap rounded bg-gray-50 p-3">'.e(strip_tags((string) $draft)).'</pre></section></div>');
                }),

            Action::make('approve_ai_draft')
                ->label('Duyệt bản nháp AI')
                ->icon('heroicon-o-check-circle')
                ->color('warning')
                ->visible(fn (): bool => $this->latestAiJobReadyForReview())
                ->requiresConfirmation()
                ->action(function (): void {
                    $workflow = app(PostAiWorkflowService::class);
                    $workflow->approve($this->record, $workflow->latestForPost($this->record), auth()->user());
                    Notification::make()->title('Bản nháp AI đã được duyệt')->success()->send();
                }),

            Action::make('reject_ai_draft')
                ->label('Từ chối bản nháp AI')
                ->icon('heroicon-o-x-circle')
                ->color('gray')
                ->visible(fn (): bool => $this->latestAiJobReadyForReview())
                ->requiresConfirmation()
                ->action(function (): void {
                    $workflow = app(PostAiWorkflowService::class);
                    $workflow->reject($this->record, $workflow->latestForPost($this->record), auth()->user());
                    Notification::make()->title('Bản nháp AI đã bị từ chối')->warning()->send();
                }),

            Action::make('apply_ai_draft')
                ->label('Chèn nội dung AI')
                ->icon('heroicon-o-document-check')
                ->color('success')
                ->visible(fn (): bool => app(PostAiWorkflowService::class)->latestForPost($this->record)?->status?->value === 'reviewed')
                ->requiresConfirmation()
                ->modalDescription('Chỉ các trường nội dung đã yêu cầu mới được áp dụng vào bài viết.')
                ->action(function (): void {
                    $workflow = app(PostAiWorkflowService::class);
                    $result = $workflow->apply($this->record, $workflow->latestForPost($this->record), auth()->user());
                    $this->record->refresh();
                    $this->fillForm();
                    Notification::make()->title($result['result'] === 'APPLIED' ? 'Đã áp dụng nội dung AI' : 'Nội dung đã được áp dụng trước đó')->success()->send();
                }),

            Action::make('generate_link_suggestions')
                ->label('Gợi ý Internal Links')
                ->icon('heroicon-o-link')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Tạo gợi ý liên kết nội bộ')
                ->modalDescription('Hệ thống sẽ phân tích bài viết này và gợi ý các trang liên quan. Các gợi ý cũ (pending) sẽ bị thay thế.')
                ->action(function () {
                    try {
                        $service = app(InternalLinkSuggestionService::class);
                        $suggestions = $service->generateForModel($this->record, force: true);

                        Notification::make()
                            ->title("Đã tạo {$suggestions->count()} gợi ý internal link.")
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Lỗi khi tạo gợi ý: ' . $e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    private function latestAiJobReadyForReview(): bool
    {
        $status = app(PostAiWorkflowService::class)->latestForPost($this->record)?->status?->value;

        return in_array($status, ['completed', 'completed_verified', 'completed_with_warnings'], true)
            && (bool) auth()->user()?->can('ai_content_job.view');
    }
}
