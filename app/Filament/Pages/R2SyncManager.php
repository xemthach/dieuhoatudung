<?php

namespace App\Filament\Pages;

use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class R2SyncManager extends Page implements HasForms, HasTable
{
    use InteractsWithTable;
    protected string $view = 'filament.pages.r2-sync-manager';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('r2.view') ?? false;
    }

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-cloud-arrow-up';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'System';
    }

    public static function getNavigationLabel(): string
    {
        return 'R2/CDN Sync';
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return 'R2/CDN Sync Manager';
    }

    public function getTableQuery()
    {
        return \App\Models\R2SyncJob::query()->latest();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                TextColumn::make('id')->label('ID')->sortable(),
                TextColumn::make('name')->label('Job Name')->limit(25),
                TextColumn::make('mode')
                    ->badge()
                    ->label('Mode')
                    ->colors([
                        'info' => 'scan_only',
                        'primary' => 'upload_only',
                        'warning' => 'replace_urls_only',
                    ]),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'scanning', 'syncing', 'replacing' => 'warning',
                        'pending' => 'gray',
                        'completed' => 'success',
                        'completed_with_errors' => 'warning',
                        'failed', 'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->description(function ($record) {
                        // Stale detection: pending/in-progress for 5+ minutes
                        if (in_array($record->status, ['pending', 'scanning', 'syncing', 'replacing'])) {
                            if ($record->updated_at && $record->updated_at->diffInMinutes(now()) >= 5) {
                                return 'Stale — có thể bị kẹt (> 5 phút)';
                            }
                        }
                        return null;
                    }),
                \Filament\Tables\Columns\ViewColumn::make('progress')
                    ->label('Tiến độ')
                    ->view('filament.tables.columns.r2-progress'),
                TextColumn::make('duration')
                    ->label('Thời gian')
                    ->getStateUsing(function ($record) {
                        if (!$record->started_at) return '-';
                        $end = $record->finished_at ?? now();
                        return $record->started_at->diffForHumans($end, true);
                    }),
            ])
            ->actions([
                // Run Now - for pending/stale jobs
                \Filament\Actions\Action::make('run_now')
                    ->label('Run Now')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->visible(fn ($record) => in_array($record->status, ['pending']))
                    ->action(function ($record) {
                        try {
                            if ($record->mode === 'scan_only') {
                                $record->update(['status' => 'scanning', 'started_at' => now()]);
                                app(\App\Services\Media\R2SyncService::class)->scanLocalMedia($record);
                                Notification::make()->title('Scan hoàn thành!')->success()->send();
                            } elseif ($record->mode === 'upload_only') {
                                \App\Jobs\SyncR2MediaJob::dispatchSync($record->id);
                                Notification::make()->title('Upload hoàn thành!')->success()->send();
                            } elseif ($record->mode === 'replace_urls_only') {
                                \App\Jobs\ReplaceMediaUrlsJob::dispatchSync($record->id);
                                Notification::make()->title('Thay thế URL hoàn thành!')->success()->send();
                            }
                        } catch (\Throwable $e) {
                            $record->update([
                                'status' => 'failed',
                                'error_message' => $e->getMessage(),
                                'finished_at' => now(),
                            ]);
                            Notification::make()->title('Lỗi: ' . $e->getMessage())->danger()->send();
                        }
                    }),
                // Retry - for failed jobs
                \Filament\Actions\Action::make('retry')
                    ->label('Retry')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn ($record) => $record->status === 'failed')
                    ->action(function ($record) {
                        try {
                            $record->update(['status' => 'pending', 'error_message' => null, 'finished_at' => null]);

                            if ($record->mode === 'scan_only') {
                                app(\App\Services\Media\R2SyncService::class)->scanLocalMedia($record);
                            } elseif ($record->mode === 'upload_only') {
                                \App\Jobs\SyncR2MediaJob::dispatchSync($record->id);
                            } else {
                                \App\Jobs\ReplaceMediaUrlsJob::dispatchSync($record->id);
                            }
                            Notification::make()->title('Thử lại thành công!')->success()->send();
                        } catch (\Throwable $e) {
                            $record->update([
                                'status' => 'failed',
                                'error_message' => $e->getMessage(),
                                'finished_at' => now(),
                            ]);
                            Notification::make()->title('Thử lại thất bại: ' . $e->getMessage())->danger()->send();
                        }
                    }),
                // Cancel
                \Filament\Actions\Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => in_array($record->status, ['pending', 'scanning', 'syncing', 'replacing']))
                    ->action(function ($record) {
                        $record->update(['status' => 'cancelled', 'finished_at' => now()]);
                        Notification::make()->title('Đã hủy job')->success()->send();
                    }),
                // Logs
                \Filament\Actions\Action::make('view_items')
                    ->label('Logs')
                    ->icon('heroicon-o-document-text')
                    ->url(fn ($record) => '#'),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('test_r2')
                ->label('Test R2 Connection')
                ->icon('heroicon-o-signal')
                ->color('info')
                ->action(function (\App\Services\Media\R2ConnectionService $svc) {
                    $res = $svc->testConnection();
                    if ($res['success']) {
                        Notification::make()->title($res['message'])->success()->send();
                    } else {
                        Notification::make()->title($res['message'])->danger()->send();
                    }
                }),

            // ── Scan Local Media ──
            Action::make('scan_local')
                ->label('Scan Local Media')
                ->icon('heroicon-o-magnifying-glass')
                ->action(function (\App\Services\Media\R2SyncService $svc) {
                    $job = \App\Models\R2SyncJob::create([
                        'name' => 'Scan Local Storage',
                        'mode' => 'scan_only',
                        'status' => 'scanning',
                        'dry_run' => false,
                        'started_at' => now(),
                    ]);

                    try {
                        $svc->scanLocalMedia($job);
                        $job->refresh();
                        Notification::make()
                            ->title('Scan hoàn thành! Tổng: ' . $job->total_files . ' files')
                            ->success()->send();
                    } catch (\Throwable $e) {
                        $job->update([
                            'status' => 'failed',
                            'error_message' => $e->getMessage(),
                            'finished_at' => now(),
                        ]);
                        Notification::make()->title('Scan thất bại')->body($e->getMessage())->danger()->send();
                    }
                }),

            // ── Sync Upload to R2 ──
            Action::make('sync_upload')
                ->label('Sync Upload to R2')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('primary')
                ->requiresConfirmation()
                ->action(function (\App\Services\Media\R2SyncService $svc) {
                    // Check latest completed scan
                    $latestScan = \App\Models\R2SyncJob::where('mode', 'scan_only')
                        ->where('status', 'completed')
                        ->latest()
                        ->first();

                    if (!$latestScan || $latestScan->total_files === 0) {
                        Notification::make()->title('Vui lòng chạy Scan Local Media trước')->danger()->send();
                        return;
                    }

                    $job = \App\Models\R2SyncJob::create([
                        'name' => 'Sync Upload to R2',
                        'mode' => 'upload_only',
                        'status' => 'syncing',
                        'dry_run' => false,
                        'started_at' => now(),
                    ]);

                    try {
                        $svc->prepareUploadItems($job);
                        \App\Jobs\SyncR2MediaJob::dispatchSync($job->id);
                        $job->refresh();

                        $statusMsg = $job->failed_files > 0
                            ? "Upload xong với {$job->failed_files} lỗi"
                            : "Upload thành công {$job->synced_files} files!";
                        Notification::make()->title($statusMsg)->success()->send();
                    } catch (\Throwable $e) {
                        $job->update([
                            'status' => 'failed',
                            'error_message' => $e->getMessage(),
                            'finished_at' => now(),
                        ]);
                        Notification::make()->title('Upload thất bại')->body($e->getMessage())->danger()->send();
                    }
                }),

            // ── Dry Run Replace URLs ──
            Action::make('dry_run_replace')
                ->label('Dry Run Replace URLs')
                ->icon('heroicon-o-document-text')
                ->color('warning')
                ->requiresConfirmation()
                ->action(function (\App\Services\Settings\SettingService $settingService) {
                    $oldUrls = $settingService->get('r2_storage.r2_old_base_urls', '/storage');
                    $oldUrlsArr = array_filter(array_map('trim', explode("\n", $oldUrls)));
                    if (empty($oldUrlsArr)) {
                        $oldUrlsArr = ['/storage'];
                    }
                    $newBaseUrl = $settingService->get('r2_storage.r2_new_cdn_base_url') ?: $settingService->get('r2_storage.r2_public_url');
                    
                    if (empty($newBaseUrl)) {
                        Notification::make()->title('Lỗi: Thiếu Public URL. Vui lòng cấu hình trong Site Settings!')->danger()->send();
                        return;
                    }
                    
                    $defaultFolder = $settingService->get('r2_storage.r2_default_folder');
                    if (!empty($defaultFolder)) {
                        $newBaseUrl = rtrim($newBaseUrl, '/') . '/' . trim($defaultFolder, '/');
                    }
                    
                    $job = \App\Models\R2SyncJob::create([
                        'name' => 'Dry Run Replace URLs',
                        'mode' => 'replace_urls_only',
                        'status' => 'replacing',
                        'dry_run' => true,
                        'old_base_urls' => $oldUrlsArr,
                        'new_base_url' => $newBaseUrl,
                        'started_at' => now(),
                    ]);

                    try {
                        \App\Jobs\ReplaceMediaUrlsJob::dispatchSync($job->id);
                        $job->refresh();
                        Notification::make()->title('Dry Run hoàn thành!')->success()->send();
                    } catch (\Throwable $e) {
                        $job->update([
                            'status' => 'failed',
                            'error_message' => $e->getMessage(),
                            'finished_at' => now(),
                        ]);
                        Notification::make()->title('Dry Run thất bại')->body($e->getMessage())->danger()->send();
                    }
                }),

            // ── Real Replace URLs ──
            Action::make('run_replace')
                ->label('Replace URLs (Real)')
                ->icon('heroicon-o-check-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->action(function (\App\Services\Settings\SettingService $settingService) {
                    $lastDryRun = \App\Models\R2SyncJob::where('mode', 'replace_urls_only')->where('dry_run', true)->exists();
                    if (!$lastDryRun) {
                        Notification::make()->title('Vui lòng chạy Dry Run trước để an toàn!')->danger()->send();
                        return;
                    }

                    $oldUrls = $settingService->get('r2_storage.r2_old_base_urls', '/storage');
                    $oldUrlsArr = array_filter(array_map('trim', explode("\n", $oldUrls)));
                    if (empty($oldUrlsArr)) {
                        $oldUrlsArr = ['/storage'];
                    }
                    $newBaseUrl = $settingService->get('r2_storage.r2_new_cdn_base_url') ?: $settingService->get('r2_storage.r2_public_url');
                    
                    if (empty($newBaseUrl)) {
                        Notification::make()->title('Lỗi: Thiếu Public URL. Vui lòng cấu hình trong Site Settings!')->danger()->send();
                        return;
                    }
                    
                    $defaultFolder = $settingService->get('r2_storage.r2_default_folder');
                    if (!empty($defaultFolder)) {
                        $newBaseUrl = rtrim($newBaseUrl, '/') . '/' . trim($defaultFolder, '/');
                    }
                    
                    $job = \App\Models\R2SyncJob::create([
                        'name' => 'Real Replace URLs',
                        'mode' => 'replace_urls_only',
                        'status' => 'replacing',
                        'dry_run' => false,
                        'old_base_urls' => $oldUrlsArr,
                        'new_base_url' => $newBaseUrl,
                        'started_at' => now(),
                    ]);

                    try {
                        \App\Jobs\ReplaceMediaUrlsJob::dispatchSync($job->id);
                        $job->refresh();
                        Notification::make()->title('Thay thế URL hoàn thành!')->success()->send();
                    } catch (\Throwable $e) {
                        $job->update([
                            'status' => 'failed',
                            'error_message' => $e->getMessage(),
                            'finished_at' => now(),
                        ]);
                        Notification::make()->title('Thay thế URL thất bại')->body($e->getMessage())->danger()->send();
                    }
                }),
        ];
    }
}
