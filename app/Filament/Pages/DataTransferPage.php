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
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class DataTransferPage extends Page
{
    protected string $view = 'filament.pages.data-transfer';

    public static function getNavigationIcon(): ?string { return 'heroicon-o-arrows-right-left'; }
    public static function getNavigationLabel(): string { return 'Nhập / Xuất dữ liệu'; }
    public static function getNavigationGroup(): ?string { return 'Hệ thống'; }
    protected static ?int $navigationSort = 1;
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
        return 'Nhập / Xuất dữ liệu';
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
                    ->options(fn ($get): array => $get('module') === 'product'
                        ? match ($get('export_intent') ?? 'normal') {
                            'business_edit' => ['xlsx' => 'Excel (XLSX)', 'csv' => 'CSV (UTF-8)'],
                            'product_transfer', 'system_backup' => ['xlsx' => 'Excel (XLSX) có manifest'],
                            default => ['xlsx' => 'Excel (XLSX)', 'csv' => 'CSV (UTF-8)', 'xml' => 'XML', 'json' => 'JSON'],
                        }
                        : ['xlsx' => 'Excel (XLSX)', 'csv' => 'CSV (UTF-8)', 'xml' => 'XML', 'json' => 'JSON'])
                    ->default('xlsx')
                    ->required(),

                Select::make('export_intent')
                    ->label('Mục đích xuất Product')
                    ->options([
                        'normal' => 'Normal Export — tham khảo dữ liệu',
                        'business_edit' => 'File nghiệp vụ — chỉnh sửa và nhập lại CSV/XLSX',
                        'product_transfer' => 'Product Transfer — chuyển Product giữa môi trường',
                        'system_backup' => 'System Backup — khôi phục toàn bộ có manifest',
                    ])
                    ->default('normal')
                    ->live()
                    ->afterStateUpdated(fn ($set) => $set('file_type', 'xlsx'))
                    ->visible(fn ($get) => $get('module') === 'product')
                    ->required(fn ($get) => $get('module') === 'product'),

                CheckboxList::make('field_groups')
                    ->label('Nhóm dữ liệu (bỏ trống = export tất cả)')
                    ->options(function ($get) {
                        $module = $get('module');
                        if (!$module) return [];
                        $groups = ModuleRegistry::fieldGroups($module);
                        return collect($groups)->mapWithKeys(fn ($g, $k) => [$k => $g['label']])->toArray();
                    })
                    ->columns(3)
                    ->visible(fn ($get): bool => ($get('export_intent') ?? 'normal') === 'normal'),
            ])
            ->action(function (array $data) {
                $this->authorizeTransfer($data['module'] ?? '', 'export');
                $intent = $data['export_intent'] ?? 'normal';
                if ($intent === 'product_transfer') $this->authorizeNamed('product_transfer.run');
                if ($intent === 'system_backup') $this->authorizeNamed('system_restore.run');

                $service = app(DataExportService::class);

                try {
                    $job = $service->export(
                        module: $data['module'],
                        fileType: $data['file_type'],
                        fieldGroups: $data['field_groups'] ?? [],
                        intent: $data['export_intent'] ?? 'normal',
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
            ->modalSubmitActionLabel('Xem trước dữ liệu')
            ->modalWidth('4xl')->stickyModalHeader()->stickyModalFooter()
            ->icon('heroicon-o-arrow-up-tray')
            ->color('primary')
            ->form([
                Select::make('module')
                    ->label('Module')
                    ->options($this->getImportableModules())
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn ($state, $set) => $set('matching_key', $state === 'product' ? 'sku' : 'id')),

                Select::make('file_type')
                    ->label('Định dạng file')
                    ->options(fn ($get): array => $get('module') === 'product'
                        ? (($get('import_intent') ?? 'business') === 'business'
                            ? ['xlsx' => 'Excel (XLSX)', 'csv' => 'CSV (UTF-8)']
                            : ['xlsx' => 'Excel (XLSX) có manifest'])
                        : ['xlsx' => 'Excel (XLSX)', 'csv' => 'CSV (UTF-8)', 'json' => 'JSON'])
                    ->default('xlsx')
                    ->required(),

                Select::make('import_intent')->label('Mục đích nhập Product')
                    ->options(['business'=>'File nghiệp vụ chỉnh sửa', 'product_transfer'=>'Product Transfer', 'system_restore'=>'System Restore'])
                    ->default('business')->required(fn ($get) => $get('module') === 'product')
                    ->live()->afterStateUpdated(fn ($set) => $set('file_type', 'xlsx'))
                    ->visible(fn ($get) => $get('module') === 'product'),
                \Filament\Forms\Components\KeyValue::make('column_mapping')->label('Mapping cột (tùy chọn)')
                    ->keyLabel('Tên cột trong file')->valueLabel('Tên trường hệ thống')
                    ->helperText('Ví dụ: Giá bán → regular_price; dùng _skip để bỏ cột. SKU/slug không được trùng. Brand/category dùng brand_slug hoặc category_slug.')
                    ->visible(fn ($get) => $get('module') === 'product' && ($get('import_intent') ?? 'business') === 'business'),

                Select::make('mode')
                    ->label('Chế độ import')
                    ->options([
                        'create' => 'Chỉ tạo mới (Create only)',
                        'update' => 'Chỉ cập nhật (Update existing)',
                        'upsert' => 'Tạo mới + Cập nhật (Upsert)',
                    ])
                    ->default('create')
                    ->required()
                    ->live()
                    ->visible(fn ($get): bool => $get('module') !== 'product' || ($get('import_intent') ?? 'business') === 'business'),

                Select::make('matching_key')
                    ->label('Trường khóa để match (cho Update/Upsert)')
                    ->options(function ($get) {
                        $module = $get('module');
                        if (!$module) return ['id' => 'ID'];
                        return $module === 'product' ? ['sku' => 'SKU', 'slug' => 'Slug'] : ModuleRegistry::matchingKeys($module);
                    })
                    ->default(fn ($get): string => $get('module') === 'product' ? 'sku' : 'id')
                    ->required()
                    ->visible(fn ($get): bool => in_array($get('mode'), ['update', 'upsert'], true)
                        && ($get('module') !== 'product' || ($get('import_intent') ?? 'business') === 'business')),

                FileUpload::make('import_file')
                    ->label('Chọn file')
                    ->required()
                    ->acceptedFileTypes(DataImportService::allowedMimeTypes())
                    ->maxSize(DataImportService::maxFileSizeKb())
                    ->storeFiles(false),
            ])
            ->action(function (array $data) {
                $this->authorizeTransfer($data['module'] ?? '', 'import');

                $service = app(DataImportService::class);
                $intent = $data['module'] === 'product' ? ($data['import_intent'] ?? 'business') : 'auto';
                if ($intent === 'product_transfer') $this->authorizeNamed('product_transfer.run');
                if ($intent === 'system_restore') $this->authorizeNamed('system_restore.run');

                $upload = $data['import_file'] ?? null;
                try {
                    if (! $upload instanceof TemporaryUploadedFile) throw new \InvalidArgumentException('Cần chọn file import mới.');

                    $job = $service->uploadAndPreview(
                        module: $data['module'],
                        filePath: $upload->getRealPath(),
                        originalName: $upload->getClientOriginalName(),
                        fileType: $data['file_type'],
                        mode: $data['mode'] ?? 'create',
                        matchingKey: $data['matching_key'] ?? ($data['module'] === 'product' ? 'sku' : 'id'),
                        intent: $intent,
                        columnMapping: $data['column_mapping'] ?? [],
                    );
                    if ($job->mode === 'product_transfer') $this->authorizeNamed('product_transfer.run');
                    if ($job->mode === 'system_restore') $this->authorizeNamed('system_restore.run');

                    $page = $job->status === 'previewing'
                        ? ImportPreviewPage::class
                        : ImportResultPage::class;
                    $this->redirect($page::getUrl(['job' => $job->id]));
                } catch (\Throwable $e) {
                    Notification::make()
                        ->danger()
                        ->title('Import thất bại')
                        ->body($e->getMessage())
                        ->send();
                } finally {
                    if ($upload instanceof TemporaryUploadedFile) $upload->delete();
                }
            });
    }

    // ─── Helpers ─────────────────────────────────────────────────────

    public function importJobsQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = DataImportJob::query();
        return auth()->user()?->isSuperAdmin() ? $query
            : $query->where('created_by', auth()->id())->whereIn('module', array_keys($this->getImportableModules()));
    }

    public function exportJobsQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = DataExportJob::query();
        return auth()->user()?->isSuperAdmin() ? $query
            : $query->where('created_by', auth()->id())->whereIn('module', array_keys($this->getExportableModules()));
    }

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

    protected function authorizeNamed(string $permission): void
    {
        abort_unless(auth()->user()?->isSuperAdmin() || auth()->user()?->can($permission), 403);
    }
}
