<?php

namespace App\Filament\Pages;

use App\Models\DataExportJob;
use App\Models\DataImportJob;
use App\Services\DataTransfer\DataExportService;
use App\Services\DataTransfer\DataImportService;
use App\Services\DataTransfer\ModuleRegistry;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class DataTransferPage extends Page
{
    protected string $view = 'filament.pages.data-transfer';

    public static function getNavigationIcon(): ?string { return 'heroicon-o-arrows-right-left'; }
    public static function getNavigationLabel(): string { return 'Import / Export'; }
    public static function getNavigationGroup(): ?string { return 'System'; }
    public static function getModelLabel(): string { return 'Import / Export Dữ liệu'; }

    public static function canAccess(): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        if ($user->isSuperAdmin()) return true;

        // Check if user has any import/export permission
        $permissions = [
            'product.import', 'product.export',
            'lead.import', 'lead.export',
            'quote_request.import', 'quote_request.export',
            'btu_calculation.import', 'btu_calculation.export',
        ];

        foreach ($permissions as $perm) {
            if ($user->can($perm)) return true;
        }

        return false;
    }

    public function getTitle(): string
    {
        return 'Import / Export Dữ liệu';
    }

    // ─── Header Actions ──────────────────────────────────────────────

    protected function getHeaderActions(): array
    {
        return [
            $this->getExportAction(),
            $this->getImportAction(),
        ];
    }

    // ─── Export Action ───────────────────────────────────────────────

    protected function getExportAction(): Action
    {
        return Action::make('export')
            ->label('Export Dữ liệu')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('success')
            ->form([
                Select::make('module')
                    ->label('Module')
                    ->options($this->getExportableModules())
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn ($set) => $set('field_groups', [])),

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

                CheckboxList::make('field_groups')
                    ->label('Nhóm dữ liệu (bỏ trống = export tất cả)')
                    ->options(function ($get) {
                        $module = $get('module');
                        if (!$module) return [];
                        $groups = ModuleRegistry::fieldGroups($module);
                        return collect($groups)->mapWithKeys(fn ($g, $k) => [$k => $g['label']])->toArray();
                    })
                    ->columns(3),
            ])
            ->action(function (array $data) {
                $this->authorizeTransfer($data['module'] ?? '', 'export');

                $service = app(DataExportService::class);

                try {
                    $job = $service->export(
                        module: $data['module'],
                        fileType: $data['file_type'],
                        fieldGroups: $data['field_groups'] ?? [],
                    );

                    if ($job->status === 'completed') {
                        $downloadPath = $service->getDownloadPath($job);

                        Notification::make()
                            ->success()
                            ->title('Export thành công!')
                            ->body("Đã export {$job->total_rows} dòng.")
                            ->send();

                        return response()->download(
                            $downloadPath,
                            $job->file_name,
                            ['Content-Type' => $this->getMimeType($data['file_type'])]
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

    // ─── Import Action ───────────────────────────────────────────────

    protected function getImportAction(): Action
    {
        return Action::make('import')
            ->label('Import Dữ liệu')
            ->icon('heroicon-o-arrow-up-tray')
            ->color('primary')
            ->form([
                Select::make('module')
                    ->label('Module')
                    ->options($this->getImportableModules())
                    ->required()
                    ->live(),

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
                        'create' => 'Chỉ tạo mới (Create only)',
                        'update' => 'Chỉ cập nhật (Update existing)',
                        'upsert' => 'Tạo mới + Cập nhật (Upsert)',
                    ])
                    ->default('create')
                    ->required()
                    ->live(),

                Select::make('matching_key')
                    ->label('Trường khóa để match (cho Update/Upsert)')
                    ->options(function ($get) {
                        $module = $get('module');
                        if (!$module) return ['id' => 'ID'];
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
            ->action(function (array $data) {
                $this->authorizeTransfer($data['module'] ?? '', 'import');

                $service = app(DataImportService::class);

                try {
                    // Get the uploaded file path
                    $filePath = storage_path('app/private/' . $data['import_file']);
                    $originalName = basename($data['import_file']);
                    $fileType = $data['file_type'];

                    $job = $service->uploadAndPreview(
                        module: $data['module'],
                        filePath: $filePath,
                        originalName: $originalName,
                        fileType: $fileType,
                        mode: $data['mode'],
                        matchingKey: $data['matching_key'] ?? 'id',
                    );

                    // Cleanup temp file
                    Storage::disk('local')->delete($data['import_file']);

                    if ($job->status === 'failed') {
                        $errors = $job->error_report_json;
                        $errorMsg = is_array($errors) && !empty($errors)
                            ? collect($errors)->pluck('errors')->flatten()->first()
                            : 'Không thể xử lý file.';

                        Notification::make()
                            ->danger()
                            ->title('Import thất bại')
                            ->body($errorMsg)
                            ->send();
                        return;
                    }

                    // Redirect to import preview page
                    $this->redirect(ImportPreviewPage::getUrl(['job' => $job->id]));
                } catch (\Throwable $e) {
                    Notification::make()
                        ->danger()
                        ->title('Import thất bại')
                        ->body($e->getMessage())
                        ->send();
                }
            });
    }

    // ─── Helpers ─────────────────────────────────────────────────────

    protected function getExportableModules(): array
    {
        $user = auth()->user();
        $modules = [];

        foreach (ModuleRegistry::modules() as $key => $label) {
            $perm = "{$key}.export";
            if ($user->isSuperAdmin() || $user->can($perm)) {
                $modules[$key] = $label;
            }
        }

        return $modules;
    }

    protected function getImportableModules(): array
    {
        $user = auth()->user();
        $modules = [];

        foreach (ModuleRegistry::modules() as $key => $label) {
            $perm = "{$key}.import";
            if ($user->isSuperAdmin() || $user->can($perm)) {
                $modules[$key] = $label;
            }
        }

        return $modules;
    }

    protected function getMimeType(string $fileType): string
    {
        return match ($fileType) {
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'csv'  => 'text/csv; charset=UTF-8',
            'xml'  => 'application/xml',
            'json' => 'application/json',
            default => 'application/octet-stream',
        };
    }

    protected function authorizeTransfer(string $module, string $action): void
    {
        $permission = "{$module}.{$action}";

        abort_unless(
            auth()->user()?->isSuperAdmin() || auth()->user()?->can($permission),
            403
        );
    }
}
