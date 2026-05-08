<?php

namespace App\Filament\Pages;

use App\Models\DataImportJob;
use App\Services\DataTransfer\DataImportService;
use App\Services\DataTransfer\ModuleRegistry;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ImportPreviewPage extends Page
{
    protected string $view = 'filament.pages.import-preview';
    protected static bool $shouldRegisterNavigation = false;

    public ?int $jobId = null;
    public ?DataImportJob $job = null;

    public function mount(): void
    {
        $this->jobId = request()->query('job');

        if (!$this->jobId) {
            $this->redirect(DataTransferPage::getUrl());
            return;
        }

        $this->job = DataImportJob::find($this->jobId);

        if (!$this->job || $this->job->status !== 'previewing') {
            Notification::make()
                ->warning()
                ->title('Import job không hợp lệ hoặc đã được xử lý.')
                ->send();
            $this->redirect(DataTransferPage::getUrl());
        }
    }

    public function getTitle(): string
    {
        $moduleName = ModuleRegistry::modules()[$this->job?->module ?? ''] ?? '';
        return "Preview Import: {$moduleName}";
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('confirm_import')
                ->label('✅ Xác nhận Import')
                ->color('success')
                ->icon('heroicon-o-check-circle')
                ->requiresConfirmation()
                ->modalHeading('Xác nhận Import')
                ->modalDescription(fn () => $this->getConfirmDescription())
                ->action(function () {
                    $service = app(DataImportService::class);
                    $result = $service->confirmImport($this->job);

                    if ($result->status === 'completed') {
                        Notification::make()
                            ->success()
                            ->title('Import hoàn tất!')
                            ->body("Tạo mới: {$result->created_rows} | Cập nhật: {$result->updated_rows} | Lỗi: {$result->failed_rows}")
                            ->duration(10000)
                            ->send();
                    } else {
                        Notification::make()
                            ->danger()
                            ->title('Import thất bại')
                            ->body("Lỗi: {$result->failed_rows} dòng")
                            ->send();
                    }

                    $this->redirect(ImportResultPage::getUrl(['job' => $result->id]));
                })
                ->visible(fn () => $this->job?->status === 'previewing'),

            Action::make('cancel')
                ->label('Hủy bỏ')
                ->color('gray')
                ->icon('heroicon-o-x-mark')
                ->action(function () {
                    $this->job?->update(['status' => 'failed', 'finished_at' => now()]);
                    Notification::make()
                        ->info()
                        ->title('Import đã hủy.')
                        ->send();
                    $this->redirect(DataTransferPage::getUrl());
                }),
        ];
    }

    protected function getConfirmDescription(): string
    {
        if (!$this->job) return '';

        $lines = [];
        $lines[] = "Module: " . (ModuleRegistry::modules()[$this->job->module] ?? $this->job->module);
        $lines[] = "Tổng dòng: {$this->job->total_rows}";
        $lines[] = "Hợp lệ: {$this->job->success_rows}";
        $lines[] = "Lỗi: {$this->job->failed_rows}";
        $lines[] = "Sẽ tạo mới: {$this->job->created_rows}";
        $lines[] = "Sẽ cập nhật: {$this->job->updated_rows}";
        $lines[] = "";
        $lines[] = "Chế độ: " . strtoupper($this->job->mode);

        if ($this->job->failed_rows > 0) {
            $lines[] = "";
            $lines[] = "⚠️ Có {$this->job->failed_rows} dòng lỗi sẽ bị bỏ qua.";
        }

        return implode("\n", $lines);
    }
}
