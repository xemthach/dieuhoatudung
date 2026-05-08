<?php

namespace App\Filament\Traits;

use App\Services\DataTransfer\DataExportService;
use App\Services\DataTransfer\DataImportService;
use App\Services\DataTransfer\ModuleRegistry;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * Add export/import header actions to a Filament List page.
 *
 * Usage in ListXxx extends ListRecords:
 *   use HasDataTransferActions;
 *   protected string $transferModule = 'product';
 */
trait HasDataTransferActions
{
    /**
     * Must be defined in the using class.
     * protected string $transferModule = 'product';
     */

    protected function getExportHeaderAction(): Action
    {
        $module = $this->transferModule;
        $exportPerm = "{$module}.export";

        return Action::make('export_data')
            ->label('Export')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('success')
            ->visible(fn () => auth()->user()?->isSuperAdmin() || auth()->user()?->can($exportPerm))
            ->form([
                Select::make('file_type')
                    ->label('Định dạng')
                    ->options([
                        'xlsx' => 'Excel (XLSX)',
                        'csv'  => 'CSV (UTF-8)',
                        'xml'  => 'XML',
                        'json' => 'JSON',
                    ])
                    ->default('xlsx')
                    ->required(),

                Select::make('export_scope')
                    ->label('Phạm vi')
                    ->options([
                        'all'      => 'Tất cả dữ liệu',
                        'filtered' => 'Theo filter hiện tại',
                        'selected' => 'Dòng đang chọn',
                    ])
                    ->default('all')
                    ->required(),

                CheckboxList::make('field_groups')
                    ->label('Nhóm dữ liệu (bỏ trống = tất cả)')
                    ->options(function () use ($module) {
                        $groups = ModuleRegistry::fieldGroups($module);
                        return collect($groups)->mapWithKeys(fn ($g, $k) => [$k => $g['label']])->toArray();
                    })
                    ->columns(3),
            ])
            ->action(function (array $data) use ($module) {
                $service = app(DataExportService::class);

                try {
                    $filters = [];
                    $selectedIds = [];

                    // Note: filtered/selected scope requires JS integration which
                    // is complex in Filament v5. For now we export all.
                    // Filter support can be added via table state if needed.

                    $job = $service->export(
                        module: $module,
                        fileType: $data['file_type'],
                        fieldGroups: $data['field_groups'] ?? [],
                        filters: $filters,
                        selectedIds: $selectedIds,
                    );

                    if ($job->status === 'completed') {
                        $downloadPath = $service->getDownloadPath($job);

                        Notification::make()
                            ->success()
                            ->title('Export thành công!')
                            ->body("Đã export {$job->total_rows} dòng. File: {$job->file_name}")
                            ->send();

                        return response()->download(
                            $downloadPath,
                            $job->file_name,
                        );
                    }
                } catch (\Throwable $e) {
                    Notification::make()
                        ->danger()
                        ->title('Export thất bại')
                        ->body($e->getMessage())
                        ->send();
                }
            });
    }

    protected function getImportHeaderAction(): Action
    {
        $module = $this->transferModule;
        $importPerm = "{$module}.import";

        return Action::make('import_data')
            ->label('Import')
            ->icon('heroicon-o-arrow-up-tray')
            ->color('primary')
            ->visible(fn () => auth()->user()?->isSuperAdmin() || auth()->user()?->can($importPerm))
            ->form([
                Select::make('file_type')
                    ->label('Định dạng file')
                    ->options([
                        'xlsx' => 'Excel (XLSX)',
                        'csv'  => 'CSV (UTF-8)',
                        'xml'  => 'XML',
                        'json' => 'JSON',
                    ])
                    ->default('xlsx')
                    ->required(),

                Select::make('mode')
                    ->label('Chế độ import')
                    ->options([
                        'create' => 'Chỉ tạo mới',
                        'update' => 'Chỉ cập nhật',
                        'upsert' => 'Tạo mới + Cập nhật',
                    ])
                    ->default('create')
                    ->required()
                    ->live(),

                Select::make('matching_key')
                    ->label('Trường khóa match')
                    ->options(function () use ($module) {
                        return ModuleRegistry::matchingKeys($module);
                    })
                    ->default('id')
                    ->visible(fn ($get) => in_array($get('mode'), ['update', 'upsert'])),

                FileUpload::make('import_file')
                    ->label('Chọn file')
                    ->required()
                    ->acceptedFileTypes(DataImportService::allowedMimeTypes())
                    ->maxSize(DataImportService::maxFileSizeKb())
                    ->disk('local')
                    ->directory('temp-imports')
                    ->visibility('private'),
            ])
            ->action(function (array $data) use ($module) {
                $service = app(DataImportService::class);

                try {
                    $filePath = storage_path('app/private/' . $data['import_file']);
                    $originalName = basename($data['import_file']);

                    $job = $service->uploadAndPreview(
                        module: $module,
                        filePath: $filePath,
                        originalName: $originalName,
                        fileType: $data['file_type'],
                        mode: $data['mode'],
                        matchingKey: $data['matching_key'] ?? 'id',
                    );

                    Storage::disk('local')->delete($data['import_file']);

                    if ($job->status === 'failed') {
                        $errorMsg = collect($job->error_report_json ?? [])
                            ->pluck('errors')->flatten()->first() ?? 'Không thể xử lý file.';

                        Notification::make()
                            ->danger()
                            ->title('Import thất bại')
                            ->body($errorMsg)
                            ->send();
                        return;
                    }

                    $this->redirect(\App\Filament\Pages\ImportPreviewPage::getUrl(['job' => $job->id]));
                } catch (\Throwable $e) {
                    Notification::make()
                        ->danger()
                        ->title('Import thất bại')
                        ->body($e->getMessage())
                        ->send();
                }
            });
    }
}
