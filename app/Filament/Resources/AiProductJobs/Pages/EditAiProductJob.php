<?php

namespace App\Filament\Resources\AiProductJobs\Pages;

use App\Filament\Resources\AiProductJobs\AiProductJobResource;
use App\Jobs\AiProductContentSingleJob;
use App\Models\AiTechnicalLog;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\HtmlString;

class EditAiProductJob extends EditRecord
{
    protected static string $resource = AiProductJobResource::class;

    protected function getFormActions(): array
    {
        // Runtime records are projections and audit evidence, not editable CMS data.
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reload_status')
                ->label('Reload status')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(fn () => $this->refreshFormData([
                    'status',
                    'processed',
                    'success',
                    'failed',
                    'needs_review',
                    'failed_reason',
                    'last_error_message',
                ])),

            Action::make('retry_failed')
                ->label('Retry failed/stuck')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->requiresConfirmation()
                ->authorize(fn () => auth()->user()?->can('bulk_ai_retry') ?? false)
                ->action(function () {
                    app(\App\Services\AI\BulkRuntimeAuthorizationService::class)->requireRetry(auth()->user());
                    $items = $this->record->items()
                        ->whereIn('status', ['failed', 'stuck', 'cancelled'])
                        ->get();

                    foreach ($items as $item) {
                        [$newJob, $newItem] = app(\App\Services\AI\AiProductLifecycleService::class)
                            ->retryAsNewOperation($item, auth()->user());
                        AiProductContentSingleJob::dispatch(
                            $newItem->product_id, $newJob->id, $newItem->id, $newItem->dispatch_uuid,
                        )->onQueue(config('ai.governed_queue', 'ai_governed'));
                    }
                    Notification::make()->title('Đã retry '.$items->count().' item')->success()->send();
                }),

            Action::make('cancel_job')
                ->label('Cancel job')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn () => in_array($this->record->status, ['queued', 'processing', 'stuck'], true))
                ->action(function () {
                    app(\App\Services\AI\AiProductLifecycleService::class)->requestCancel(
                        $this->record, auth()->id(), 'Cancelled by operator from AI Product Job detail.',
                    );
                    Notification::make()->title('Job đã hủy')->warning()->send();
                    $this->refreshFormData(['status']);
                }),

            Action::make('view_technical_log')
                ->label('View technical log')
                ->icon('heroicon-o-bug-ant')
                ->color('gray')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Đóng')
                ->modalContent(fn () => new HtmlString('<pre style="white-space:pre-wrap;max-height:520px;overflow:auto">'
                    .e($this->technicalLogText()).'</pre>')),
        ];
    }

    private function technicalLogText(): string
    {
        return AiTechnicalLog::query()
            ->where(function ($query): void {
                $query->where(function ($query): void {
                    $query->where('ai_job_type', class_basename($this->record))
                        ->where('ai_job_id', $this->record->id);
                })->orWhere(function ($query): void {
                    $query->where('ai_job_type', 'AiProductJobItem')
                        ->whereIn('ai_job_id', $this->record->items()->pluck('id'));
                });
            })
            ->latest('id')
            ->limit(40)
            ->get()
            ->map(fn ($log) => '['.$log->created_at?->format('Y-m-d H:i:s')."] {$log->level} {$log->event}: {$log->message}\n".json_encode($log->context_json ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
            ->implode("\n\n") ?: 'No technical logs.';
    }
}
