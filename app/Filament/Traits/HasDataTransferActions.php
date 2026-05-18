<?php

namespace App\Filament\Traits;

use App\Services\DataTransfer\DataExportService;
use App\Services\DataTransfer\DataImportService;
use App\Services\DataTransfer\ModuleRegistry;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
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
                        'selected' => 'Sản phẩm đã chọn',
                        'current_page' => 'Trang hiện tại',
                        'filter' => 'Theo filter hiện tại',
                    ])
                    ->default('selected')
                    ->live()
                    ->required(),

                Placeholder::make('export_scope_summary')
                    ->label('Tóm tắt phạm vi')
                    ->content(fn ($get): string => $this->exportScopeSummary((string) $get('export_scope'))),

                CheckboxList::make('field_groups')
                    ->label('Nhóm dữ liệu (bỏ trống = tất cả)')
                    ->options(function () use ($module) {
                        $groups = ModuleRegistry::fieldGroups($module);

                        return collect($groups)->mapWithKeys(fn ($g, $k) => [$k => $g['label']])->toArray();
                    })
                    ->columns(3),
            ])
            ->action(function (array $data) use ($module, $exportPerm) {
                abort_unless(auth()->user()?->isSuperAdmin() || auth()->user()?->can($exportPerm), 403);

                $service = app(DataExportService::class);

                try {
                    $filters = [];
                    $selectedIds = [];
                    $currentPageIds = [];
                    $scope = $data['export_scope'] ?? 'selected';

                    if ($scope === 'selected') {
                        $selectedIds = $this->resolveExportSelectedIds();

                        if ($selectedIds === []) {
                            Notification::make()
                                ->title('Chưa chọn sản phẩm để export')
                                ->body('Hãy tick ít nhất một sản phẩm, hoặc đổi phạm vi sang Trang hiện tại / Theo filter hiện tại.')
                                ->warning()
                                ->send();

                            return;
                        }
                    }

                    if ($scope === 'current_page') {
                        $currentPageIds = $this->resolveExportCurrentPageIds();
                        $selectedIds = $currentPageIds;
                    }

                    if ($scope === 'filter' && method_exists($this, 'getFilteredTableQuery')) {
                        $selectedIds = $this->getFilteredTableQuery()
                            ->pluck('id')
                            ->map(fn ($id) => (int) $id)
                            ->unique()
                            ->values()
                            ->all();
                    }

                    Log::info('Data export header payload', [
                        'module' => $module,
                        'scope' => $scope,
                        'selected_product_ids' => $scope === 'selected' ? $selectedIds : [],
                        'selected_product_ids_count' => $scope === 'selected' ? count($selectedIds) : 0,
                        'current_page_ids' => $currentPageIds,
                        'current_page_ids_count' => count($currentPageIds),
                        'filters' => $filters,
                        'fields' => $data['field_groups'] ?? [],
                        'format' => $data['file_type'] ?? null,
                        'resolved_total_items' => count($selectedIds),
                        'resolved_ids_sample' => array_slice($selectedIds, 0, 25),
                        'user_id' => auth()->id(),
                        'route' => request()?->route()?->getName(),
                    ]);

                    $job = $service->export(
                        module: $module,
                        fileType: $data['file_type'],
                        fieldGroups: $data['field_groups'] ?? [],
                        filters: $filters,
                        selectedIds: $selectedIds,
                        scope: $scope,
                    );

                    if ($job->status === 'completed') {
                        $downloadPath = $service->getDownloadPath($job);

                        Notification::make()
                            ->success()
                            ->title('Export thành công')
                            ->body($this->exportSuccessMessage($scope, $job->total_rows, $job->file_name))
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
                    ->visible(fn ($get) => in_array($get('mode'), ['update', 'upsert'], true)),

                FileUpload::make('import_file')
                    ->label('Chọn file')
                    ->required()
                    ->acceptedFileTypes(DataImportService::allowedMimeTypes())
                    ->maxSize(DataImportService::maxFileSizeKb())
                    ->disk('local')
                    ->directory('temp-imports')
                    ->visibility('private'),
            ])
            ->action(function (array $data) use ($module, $importPerm) {
                abort_unless(auth()->user()?->isSuperAdmin() || auth()->user()?->can($importPerm), 403);

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

    protected function resolveExportSelectedIds(): array
    {
        $selectedIds = $this->normalizeExportRecordIds($this->selectedTableRecords ?? []);

        if ($selectedIds === [] && ($this->isTrackingDeselectedTableRecords ?? false) && method_exists($this, 'getFilteredTableQuery')) {
            return $this->getFilteredTableQuery()
                ->whereKeyNot($this->deselectedTableRecords ?? [])
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();
        }

        return $selectedIds;
    }

    protected function resolveExportCurrentPageIds(): array
    {
        if (! method_exists($this, 'getTableRecords')) {
            return [];
        }

        $records = $this->getTableRecords();
        if ($records instanceof Paginator) {
            $records = collect($records->items());
        }

        return $records instanceof Collection
            ? $records->pluck('id')->map(fn ($id) => (int) $id)->unique()->values()->all()
            : [];
    }

    protected function normalizeExportRecordIds(array $state): array
    {
        $ids = [];

        foreach ($state as $key => $value) {
            if ($value === false || $value === null || $value === '') {
                continue;
            }

            if (is_numeric($value) && (int) $value > 0) {
                $ids[] = (int) $value;
                continue;
            }

            if (is_numeric($key) && (int) $key > 0) {
                $ids[] = (int) $key;
            }
        }

        return array_values(array_unique($ids));
    }

    protected function exportSuccessMessage(string $scope, int $totalRows, string $fileName): string
    {
        $scopeText = match ($scope) {
            'selected' => "{$totalRows} sản phẩm đã chọn",
            'current_page' => "{$totalRows} sản phẩm trên trang hiện tại",
            'filter' => "{$totalRows} sản phẩm theo filter",
            default => "{$totalRows} dòng",
        };

        return "Đã export {$scopeText}. File: {$fileName}";
    }

    protected function exportScopeSummary(string $scope): string
    {
        return match ($scope) {
            'selected' => 'Xuất các sản phẩm đang được tick trong bảng.',
            'current_page' => 'Xuất các sản phẩm đang hiển thị trên trang hiện tại, không dùng checkbox đã tick.',
            'filter' => 'Xuất toàn bộ sản phẩm theo filter hiện tại, không dùng checkbox đã tick.',
            default => 'Chọn phạm vi export.',
        };
    }
}
