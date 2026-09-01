<?php

namespace App\Filament\Resources\Products\Tables;

use App\Enums\StockStatus;
use App\Jobs\AiProductContentSingleJob;
use App\Jobs\AiProductContentBatchJob;
use App\Services\AI\ProductBulkGenerationManifest;
use App\Services\AI\ProductBulkTargetResolver;
use App\Services\AI\SingleOperatorControlledRolloutPolicy;
use App\Services\AI\AiContentStatusPresenter;
use App\Services\AI\AiProductContentStateResolver;
use App\Services\AI\AIWorkerReadinessService;
use App\Services\AI\ProductAiBulkWorkflowService;
use App\Services\AI\ProductAiGenerationReadiness;
use App\Services\Media\MediaDiskService;
use App\Models\AiProductJob;
use App\Models\AiProductJobItem;
use App\Models\AiTechnicalLog;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\Product\AIProductContentSystem;
use App\Services\DataTransfer\DataExportService;
use App\Services\DataTransfer\ModuleRegistry;
use App\Support\SchemaColumns;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'aiProductJobItems.draft',
                'aiProductDrafts',
            ]))
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('ai_content_status')
                    ->label('Nội dung AI')
                    ->state(fn (Product $record): string => self::aiStatusView($record)['label'])
                    ->badge()
                    ->extraCellAttributes(fn (Product $record): array => [
                        'data-ai-product-id' => (string) $record->id,
                        'data-ai-field' => 'ai_status',
                    ])
                    ->color(fn (Product $record): string => self::aiStatusView($record)['color'])
                    ->tooltip(fn (Product $record): ?string => self::aiStatusTooltip($record))
                    ->toggleable(),
                TextColumn::make('ai_score')
                    ->label('SEO')
                    ->badge()
                    ->extraCellAttributes(fn (Product $record): array => [
                        'data-ai-product-id' => (string) $record->id,
                        'data-ai-field' => 'seo_score',
                    ])
                    ->color(fn (?int $state): string => match (true) {
                        (int) $state >= 85 => 'success',
                        (int) $state >= 70 => 'info',
                        (int) $state > 0 => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('ai_content_updated_at')
                    ->label('Cập nhật AI')
                    ->state(fn (Product $record) => self::resolvedAiState($record)['latest_history']['item']?->state_changed_at
                        ?: self::resolvedAiState($record)['latest_history']['item']?->updated_at
                        ?: $record->ai_last_run_at)
                    ->extraCellAttributes(fn (Product $record): array => [
                        'data-ai-product-id' => (string) $record->id,
                        'data-ai-field' => 'last_ai_run',
                    ])
                    ->since()
                    ->placeholder('-'),
                TextColumn::make('ai_warning_count')
                    ->label('Warn')
                    ->badge()
                    ->extraCellAttributes(fn (Product $record): array => [
                        'data-ai-product-id' => (string) $record->id,
                        'data-ai-field' => 'warnings_count',
                    ])
                    ->color(fn (?int $state): string => ((int) $state) > 0 ? 'warning' : 'success')
                    ->sortable(),
                TextColumn::make('slug')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable(),
                TextColumn::make('model_code')
                    ->searchable(),
                TextColumn::make('brand.name')
                    ->searchable(),
                TextColumn::make('product_category_id')
                    ->label('Category ID')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('series')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('btu')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('inverter')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('cooling_type')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('voltage')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('refrigerant_gas')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('power_consumption')
                    ->searchable(),
                TextColumn::make('airflow')
                    ->searchable(),
                TextColumn::make('noise_level')
                    ->searchable(),
                TextColumn::make('indoor_dimensions')
                    ->searchable(),
                TextColumn::make('outdoor_dimensions')
                    ->searchable(),
                TextColumn::make('weight')
                    ->searchable(),
                TextColumn::make('recommended_area')
                    ->searchable(),
                TextColumn::make('regular_price')
                    ->money()
                    ->sortable(),
                TextColumn::make('sale_price')
                    ->money()
                    ->sortable(),
                IconColumn::make('price_includes_vat')
                    ->label('VAT')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('discount_percent')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('promotion_start_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('promotion_end_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('stock_status')
                    ->badge()
                    ->searchable(),
                ImageColumn::make('main_image')
                    ->label('Hình ảnh')
                    ->disk(fn (): string => app(MediaDiskService::class)->getUploadDisk()),
                TextColumn::make('video_url')
                    ->searchable(),
                IconColumn::make('is_featured')
                    ->boolean(),
                IconColumn::make('is_bestseller')
                    ->boolean(),
                IconColumn::make('is_new')
                    ->boolean(),
                IconColumn::make('is_active')
                    ->boolean(),
                TextColumn::make('sort_order')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('seo_title')
                    ->searchable(),
                TextColumn::make('seo_description')
                    ->searchable(),
                TextColumn::make('canonical_url')
                    ->searchable(),
                TextColumn::make('robots')
                    ->searchable(),
                TextColumn::make('og_title')
                    ->searchable(),
                TextColumn::make('og_description')
                    ->searchable(),
                ImageColumn::make('og_image'),
                IconColumn::make('schema_enabled')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('brand_id')
                    ->label('Brand')
                    ->options(fn () => Brand::query()->orderBy('name')->pluck('name', 'id')->all()),
                SelectFilter::make('product_category_id')
                    ->label('Category')
                    ->options(fn () => ProductCategory::query()->orderBy('name')->pluck('name', 'id')->all()),
                SelectFilter::make('ai_content_state')
                    ->label('Trạng thái AI')
                    ->options([
                        'available' => 'Sẵn sàng tạo nội dung',
                        'queued' => 'Đang chờ',
                        'processing' => 'Đang xử lý',
                        'review_required' => 'Chờ duyệt',
                        'approved' => 'Đã duyệt',
                        'history_applied' => 'Lịch sử: đã áp dụng',
                        'current_blocked' => 'Hiện tại: bị chặn',
                        'history_failed' => 'Lịch sử: thất bại',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => self::applyAiStateFilter(
                        $query,
                        $data['value'] ?? null,
                    )),
                Filter::make('seo_score_lt_70')
                    ->label('SEO score < 70')
                    ->query(fn (Builder $query): Builder => $query->where('ai_score', '<', 70)),
                Filter::make('seo_score_70_84')
                    ->label('SEO score 70-84')
                    ->query(fn (Builder $query): Builder => $query->whereBetween('ai_score', [70, 84])),
                Filter::make('seo_score_gte_85')
                    ->label('SEO score >= 85')
                    ->query(fn (Builder $query): Builder => $query->where('ai_score', '>=', 85)),
                Filter::make('missing_content')
                    ->label('Missing content')
                    ->query(fn (Builder $query): Builder => $query->where(function (Builder $query) {
                        $query->whereNull('short_description')
                            ->orWhereNull('long_description')
                            ->orWhere('short_description', '')
                            ->orWhere('long_description', '');
                    })),
                Filter::make('missing_seo')
                    ->label('Missing SEO')
                    ->query(fn (Builder $query): Builder => $query->where(function (Builder $query) {
                        $query->whereNull('seo_title')
                            ->orWhereNull('seo_description')
                            ->orWhere('seo_title', '')
                            ->orWhere('seo_description', '');
                    })),
                Filter::make('missing_merchant')
                    ->label('Missing Merchant')
                    ->query(fn (Builder $query): Builder => $query->where(function (Builder $query) {
                        $query->whereNull('merchant_title')
                            ->orWhereNull('merchant_description')
                            ->orWhereNull('google_product_category')
                            ->orWhereNull('product_type');
                    })),
                Filter::make('missing_faq')
                    ->label('Missing FAQ')
                    ->query(fn (Builder $query): Builder => $query->whereDoesntHave('faqs')),
                Filter::make('has_technical_specs')
                    ->label('Has technical specs')
                    ->query(fn (Builder $query): Builder => $query->where(function (Builder $query) {
                        $query->whereNotNull('specs_json')
                            ->orWhereNotNull('btu')
                            ->orWhereNotNull('capacity_kw')
                            ->orWhereNotNull('model_code');
                    })),
                Filter::make('no_technical_specs')
                    ->label('No technical specs')
                    ->query(fn (Builder $query): Builder => $query->whereNull('specs_json')
                        ->whereNull('btu')
                        ->whereNull('capacity_kw')
                        ->whereNull('model_code')),
                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('ai_status_detail')
                    ->label('AI details')
                    ->icon('heroicon-o-information-circle')
                    ->color('gray')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Đóng')
                    ->modalHeading(fn (Product $record): string => 'AI status: '.$record->name)
                    ->modalContent(fn (Product $record) => new HtmlString(self::aiStatusDetailHtml($record))),
                Action::make('ai_logs')
                    ->label('AI logs')
                    ->icon('heroicon-o-bug-ant')
                    ->color('gray')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Đóng')
                    ->modalHeading(fn (Product $record): string => 'AI logs: '.$record->name)
                    ->modalContent(fn (Product $record) => new HtmlString('<pre style="white-space:pre-wrap;max-height:520px;overflow:auto">'
                        .e(self::aiTechnicalLogsText($record)).'</pre>')),
                Action::make('ai_retry_failed')
                    ->label('Retry AI')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->iconButton()
                    ->tooltip('Retry AI')
                    ->visible(fn (Product $record): bool => $record->aiProductJobItems()->whereIn('status', ['failed', 'stuck', 'cancelled'])->exists())
                    ->requiresConfirmation()
                    ->action(function (Product $record): void {
                        $items = $record->aiProductJobItems()
                            ->whereIn('status', ['failed', 'stuck', 'cancelled'])
                            ->latest('id')
                            ->get();
                        $count = self::retryAiProductItems($items);
                        Notification::make()
                            ->title($count > 0 ? "Đã retry {$count} AI item" : 'Không có AI item lỗi để retry')
                            ->status($count > 0 ? 'success' : 'warning')
                            ->send();
                    }),
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkActionGroup::make([
                        self::aiBulkReviewAction(),
                        self::aiBulkApproveAction(),
                        self::aiBulkRejectAction(),
                        self::aiBulkDiscardAction(),
                        self::aiBulkRegenerateAction(),
                        self::aiBulkApplyAction(),
                        self::aiBulkAction('ai_generate_content', 'Generate AI Content', 'heroicon-o-sparkles', 'generate_ai_content', [
                            'content', 'seo', 'merchant', 'tags', 'faq', 'internal_links', 'og',
                        ]),
                        self::aiBulkAction('ai_rewrite_weak', 'Rewrite Weak Content', 'heroicon-o-pencil-square', 'rewrite_weak_content', [
                            'content', 'seo', 'tags', 'faq', 'og',
                        ], 'rewrite_weak'),
                        self::aiBulkAction('ai_audit_seo', 'Audit SEO', 'heroicon-o-chart-bar-square', 'audit_seo', []),
                        self::aiBulkAction('ai_generate_merchant', 'Generate Merchant', 'heroicon-o-shopping-cart', 'generate_merchant', [
                            'merchant',
                        ]),
                        self::aiBulkAction('ai_generate_faq', 'Generate FAQ', 'heroicon-o-question-mark-circle', 'generate_faq', [
                            'faq',
                        ]),
                        self::aiBulkAction('ai_generate_tags', 'Generate Tags', 'heroicon-o-hashtag', 'generate_tags', [
                            'tags',
                        ]),
                    ])->label('AI Product System')->icon('heroicon-o-cpu-chip'),

                    BulkActionGroup::make([
                        BulkAction::make('export_selected_products')
                            ->label('Export selected products')
                            ->icon('heroicon-o-arrow-down-tray')
                            ->color('success')
                            ->visible(fn () => auth()->user()?->isSuperAdmin() || (auth()->user()?->can('product.export') ?? false))
                            ->requiresConfirmation()
                            ->modalHeading('Export sản phẩm đã chọn')
                            ->modalDescription('Scope: sản phẩm đã chọn. Export này chỉ xuất các sản phẩm đang được tick trong bảng.')
                            ->modalSubmitActionLabel('Export selected')
                            ->form([
                                Select::make('file_type')
                                    ->label('Định dạng')
                                    ->options([
                                        'xlsx' => 'Excel (XLSX)',
                                        'csv' => 'CSV (UTF-8)',
                                        'xml' => 'XML',
                                        'json' => 'JSON',
                                    ])
                                    ->default('xlsx')
                                    ->required(),
                                CheckboxList::make('field_groups')
                                    ->label('Nhóm dữ liệu')
                                    ->options(fn (): array => collect(ModuleRegistry::fieldGroups('product'))
                                        ->mapWithKeys(fn ($group, $key) => [$key => $group['label']])
                                        ->all())
                                    ->columns(3),
                            ])
                            ->action(function (Collection $records, array $data): void {
                                abort_unless(auth()->user()?->isSuperAdmin() || auth()->user()?->can('product.export'), 403);

                                $selectedIds = $records->pluck('id')->map(fn ($id) => (int) $id)->unique()->values()->all();

                                if ($selectedIds === []) {
                                    Notification::make()
                                        ->title('Chưa chọn sản phẩm để export')
                                        ->warning()
                                        ->send();

                                    return;
                                }

                                $job = app(DataExportService::class)->export(
                                    module: 'product',
                                    fileType: $data['file_type'],
                                    fieldGroups: $data['field_groups'] ?? [],
                                    selectedIds: $selectedIds,
                                    scope: 'selected',
                                );

                                Log::info('Product selected export payload', [
                                    'source' => 'products_table_bulk_action',
                                    'user_id' => auth()->id(),
                                    'scope' => 'selected',
                                    'selected_count' => count($selectedIds),
                                    'selected_ids_sample' => array_slice($selectedIds, 0, 25),
                                    'total_rows' => $job->total_rows,
                                    'selected_product_ids' => $selectedIds,
                                    'selected_product_ids_count' => count($selectedIds),
                                    'current_page_ids' => [],
                                    'current_page_ids_count' => 0,
                                    'filters' => [],
                                    'fields' => $data['field_groups'] ?? [],
                                    'format' => $data['file_type'] ?? null,
                                    'resolved_total_items' => $job->total_rows,
                                    'route' => request()?->route()?->getName(),
                                    'timestamp' => now()->toIso8601String(),
                                ]);

                                Notification::make()
                                    ->title('Export selected thành công')
                                    ->body("Đã export {$job->total_rows} sản phẩm đã chọn.")
                                    ->success()
                                    ->send();
                            }),
                    ])->label('Data')->icon('heroicon-o-arrow-down-tray'),

                    // Phân loại
                    BulkActionGroup::make([
                        BulkAction::make('bulk_update_category')
                            ->label('Cập nhật danh mục')
                            ->icon('heroicon-o-folder')
                            ->form([
                                Select::make('product_category_id')
                                    ->label('Danh mục')
                                    ->relationship('category', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                            ])
                            ->action(function (Collection $records, array $data) {
                                abort_unless(auth()->user()?->can('product.edit'), 403);

                                DB::transaction(function () use ($records, $data) {
                                    $records->each->update(['product_category_id' => $data['product_category_id']]);
                                });
                            })
                            ->deselectRecordsAfterCompletion(),

                        BulkAction::make('bulk_update_brand')
                            ->label('Cập nhật thương hiệu')
                            ->icon('heroicon-o-tag')
                            ->form([
                                Select::make('brand_id')
                                    ->label('Thương hiệu')
                                    ->relationship('brand', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                            ])
                            ->action(function (Collection $records, array $data) {
                                abort_unless(auth()->user()?->can('product.edit'), 403);

                                DB::transaction(function () use ($records, $data) {
                                    $records->each->update(['brand_id' => $data['brand_id']]);
                                });
                            })
                            ->deselectRecordsAfterCompletion(),
                    ])->label('Phân loại')->icon('heroicon-o-folder-open'),

                    // Hiển thị & Tồn kho
                    BulkActionGroup::make([
                        BulkAction::make('bulk_activate')
                            ->label('Hiển thị sản phẩm')
                            ->icon('heroicon-o-eye')
                            ->requiresConfirmation()
                            ->action(function (Collection $records) {
                                abort_unless(auth()->user()?->can('product.edit'), 403);

                                DB::transaction(fn () => $records->each->update(['is_active' => true]));
                            })
                            ->deselectRecordsAfterCompletion(),

                        BulkAction::make('bulk_deactivate')
                            ->label('Ẩn sản phẩm')
                            ->icon('heroicon-o-eye-slash')
                            ->requiresConfirmation()
                            ->action(function (Collection $records) {
                                abort_unless(auth()->user()?->can('product.edit'), 403);

                                DB::transaction(fn () => $records->each->update(['is_active' => false]));
                            })
                            ->deselectRecordsAfterCompletion(),

                        BulkAction::make('bulk_stock_status')
                            ->label('Cập nhật tình trạng hàng')
                            ->icon('heroicon-o-cube')
                            ->form([
                                Select::make('stock_status')
                                    ->label('Trạng thái')
                                    ->options(StockStatus::class)
                                    ->required(),
                            ])
                            ->action(function (Collection $records, array $data) {
                                abort_unless(auth()->user()?->can('product.edit'), 403);

                                DB::transaction(fn () => $records->each->update(['stock_status' => $data['stock_status']]));
                            })
                            ->deselectRecordsAfterCompletion(),
                    ])->label('Hiển thị')->icon('heroicon-o-eye'),

                    // Giá & Khuyến mãi
                    BulkAction::make('bulk_pricing')
                        ->label('Cập nhật giá/khuyến mãi')
                        ->icon('heroicon-o-currency-dollar')
                        ->form([
                            TextInput::make('regular_price')->label('Giá gốc')->numeric(),
                            TextInput::make('sale_price')->label('Giá khuyến mãi')->numeric(),
                            TextInput::make('discount_percent')->label('Phần trăm giảm (%)')->numeric(),
                            DateTimePicker::make('promotion_start_at')->label('Bắt đầu KM'),
                            DateTimePicker::make('promotion_end_at')->label('Kết thúc KM'),
                            Checkbox::make('clear_sale_price')->label('Xóa giá khuyến mãi'),
                            Checkbox::make('clear_discount')->label('Xóa % giảm giá'),
                            Checkbox::make('clear_promotion_dates')->label('Xóa ngày KM'),
                        ])
                        ->action(function (Collection $records, array $data) {
                            abort_unless(auth()->user()?->can('product.edit'), 403);

                            DB::transaction(function () use ($records, $data) {
                                foreach ($records as $record) {
                                    $updates = [];
                                    if (! empty($data['regular_price'])) {
                                        $updates['regular_price'] = $data['regular_price'];
                                    }

                                    if (! empty($data['sale_price'])) {
                                        $updates['sale_price'] = $data['sale_price'];
                                    } elseif (! empty($data['clear_sale_price'])) {
                                        $updates['sale_price'] = null;
                                    }

                                    if (! empty($data['discount_percent'])) {
                                        $updates['discount_percent'] = $data['discount_percent'];
                                    } elseif (! empty($data['clear_discount'])) {
                                        $updates['discount_percent'] = null;
                                    }

                                    if (! empty($data['promotion_start_at'])) {
                                        $updates['promotion_start_at'] = $data['promotion_start_at'];
                                    } elseif (! empty($data['clear_promotion_dates'])) {
                                        $updates['promotion_start_at'] = null;
                                    }

                                    if (! empty($data['promotion_end_at'])) {
                                        $updates['promotion_end_at'] = $data['promotion_end_at'];
                                    } elseif (! empty($data['clear_promotion_dates'])) {
                                        $updates['promotion_end_at'] = null;
                                    }

                                    if (! empty($updates)) {
                                        $record->update($updates);
                                    }
                                }
                            });
                        })
                        ->deselectRecordsAfterCompletion(),

                    // SEO
                    BulkAction::make('bulk_seo_robots')
                        ->label('Cập nhật Robots SEO')
                        ->icon('heroicon-o-magnifying-glass')
                        ->form([
                            Select::make('robots')
                                ->label('Robots')
                                ->options([
                                    'index,follow' => 'Index, Follow',
                                    'noindex,follow' => 'Noindex, Follow',
                                    'index,nofollow' => 'Index, Nofollow',
                                    'noindex,nofollow' => 'Noindex, Nofollow',
                                ])
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data) {
                            abort_unless(auth()->user()?->can('product.edit'), 403);

                            DB::transaction(fn () => $records->each->update(['robots' => $data['robots']]));
                        })
                        ->deselectRecordsAfterCompletion(),

                    // Badge
                    BulkAction::make('bulk_badges')
                        ->label('Cập nhật Badge')
                        ->icon('heroicon-o-star')
                        ->form([
                            Select::make('is_featured')->label('Nổi bật')->options(['no_change' => 'Không đổi', '1' => 'Có', '0' => 'Không'])->default('no_change'),
                            Select::make('is_bestseller')->label('Bán chạy')->options(['no_change' => 'Không đổi', '1' => 'Có', '0' => 'Không'])->default('no_change'),
                            Select::make('is_new')->label('Mới')->options(['no_change' => 'Không đổi', '1' => 'Có', '0' => 'Không'])->default('no_change'),
                        ])
                        ->action(function (Collection $records, array $data) {
                            abort_unless(auth()->user()?->can('product.edit'), 403);

                            DB::transaction(function () use ($records, $data) {
                                foreach ($records as $record) {
                                    $updates = [];
                                    if ($data['is_featured'] !== 'no_change') {
                                        $updates['is_featured'] = (bool) $data['is_featured'];
                                    }
                                    if ($data['is_bestseller'] !== 'no_change') {
                                        $updates['is_bestseller'] = (bool) $data['is_bestseller'];
                                    }
                                    if ($data['is_new'] !== 'no_change') {
                                        $updates['is_new'] = (bool) $data['is_new'];
                                    }
                                    if (! empty($updates)) {
                                        $record->update($updates);
                                    }
                                }
                            });
                        })
                        ->deselectRecordsAfterCompletion(),

                    // Thuộc tính kỹ thuật
                    BulkAction::make('bulk_tech_attributes')
                        ->label('Cập nhật thông số cơ bản')
                        ->icon('heroicon-o-cog')
                        ->form([
                            Select::make('inverter')->label('Inverter')->options(['no_change' => 'Không đổi', '1' => 'Có', '0' => 'Không'])->default('no_change'),
                            Select::make('cooling_type')->label('Kiểu làm lạnh')->options(['no_change' => 'Không đổi', '1 chiều' => '1 chiều', '2 chiều' => '2 chiều'])->default('no_change'),
                            TextInput::make('voltage')->label('Điện áp'),
                            TextInput::make('refrigerant_gas')->label('Loại Gas'),
                        ])
                        ->action(function (Collection $records, array $data) {
                            abort_unless(auth()->user()?->can('product.edit'), 403);

                            DB::transaction(function () use ($records, $data) {
                                foreach ($records as $record) {
                                    $updates = [];
                                    if ($data['inverter'] !== 'no_change') {
                                        $updates['inverter'] = (bool) $data['inverter'];
                                    }
                                    if ($data['cooling_type'] !== 'no_change') {
                                        $updates['cooling_type'] = $data['cooling_type'];
                                    }
                                    if (! empty($data['voltage'])) {
                                        $updates['voltage'] = $data['voltage'];
                                    }
                                    if (! empty($data['refrigerant_gas'])) {
                                        $updates['refrigerant_gas'] = $data['refrigerant_gas'];
                                    }
                                    if (! empty($updates)) {
                                        $record->update($updates);
                                    }
                                }
                            });
                        })
                        ->deselectRecordsAfterCompletion(),

                    // Tag
                    BulkActionGroup::make([
                        BulkAction::make('bulk_attach_tags')
                            ->label('Gắn Tag')
                            ->icon('heroicon-o-hashtag')
                            ->form([
                                Select::make('tags')
                                    ->label('Tags')
                                    ->multiple()
                                    ->relationship('tags', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                            ])
                            ->action(function (Collection $records, array $data) {
                                abort_unless(auth()->user()?->can('product.edit'), 403);

                                DB::transaction(function () use ($records, $data) {
                                    foreach ($records as $record) {
                                        $record->tags()->syncWithoutDetaching($data['tags']);
                                    }
                                });
                            })
                            ->deselectRecordsAfterCompletion(),

                        BulkAction::make('bulk_detach_tags')
                            ->label('Xóa Tag')
                            ->icon('heroicon-o-trash')
                            ->form([
                                Select::make('tags')
                                    ->label('Tags')
                                    ->multiple()
                                    ->relationship('tags', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                            ])
                            ->action(function (Collection $records, array $data) {
                                abort_unless(auth()->user()?->can('product.edit'), 403);

                                DB::transaction(function () use ($records, $data) {
                                    foreach ($records as $record) {
                                        $record->tags()->detach($data['tags']);
                                    }
                                });
                            })
                            ->deselectRecordsAfterCompletion(),
                    ])->label('Tag')->icon('heroicon-o-hashtag'),

                    // Default Bulk Delete
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    private static function aiBulkAction(string $name, string $label, string $icon, string $action, array $defaultOutputs, string $defaultMode = 'missing_only'): BulkAction
    {
        return BulkAction::make($name)
            ->label($label)
            ->icon($icon)
            ->color('info')
            ->visible(fn () => auth()->user()?->can('product.ai_generate') ?? false)
            ->requiresConfirmation()
            ->modalHeading('Xác nhận tạo nội dung AI')
            ->modalDescription('Scope: sản phẩm đã chọn. Action này chỉ chạy các sản phẩm đang được tick trong bảng.')
            ->modalSubmitActionLabel('Bắt đầu chạy AI')
            ->form(self::aiConfigForm($defaultOutputs, $defaultMode, 'selected', [
                'selected' => 'Sản phẩm đã chọn',
            ]))
            ->action(function (Collection $records, array $data, $livewire = null) use ($action) {
                abort_unless(auth()->user()?->can('product.ai_generate'), 403);

                try {
                    $productIds = app(ProductBulkTargetResolver::class)->resolve(
                        ProductBulkTargetResolver::SELECTED,
                        $records->pluck('id')->all(),
                    );
                } catch (\RuntimeException $exception) {
                    Log::warning('bulk_scope_mismatch_detected', [
                        'module' => 'ai_product',
                        'action' => $action,
                        'errors' => [$exception->getMessage()],
                        'record_count' => $records->count(),
                        'resolved_total_count' => 0,
                    ]);

                    Notification::make()
                        ->title('Phạm vi AI không hợp lệ')
                        ->body($exception->getMessage())
                        ->warning()
                        ->send();

                    return;
                }

                Log::info('AI product bulk action payload', [
                    'source' => 'products_table_bulk_action',
                    'user_id' => auth()->id(),
                    'action' => $action,
                    'scope' => $data['scope'] ?? 'selected',
                    'record_count' => $records->count(),
                    'resolved_count' => count($productIds),
                    'resolved_ids_sample' => array_slice($productIds, 0, 25),
                    'route' => request()?->route()?->getName(),
                    'timestamp' => now()->toIso8601String(),
                ]);

                if ($productIds === []) {
                    Log::warning('bulk_selection_empty', [
                        'source' => 'products_table_bulk_action',
                        'user_id' => auth()->id(),
                        'action' => $action,
                        'scope' => $data['scope'] ?? 'selected',
                        'record_count' => $records->count(),
                        'route' => request()?->route()?->getName(),
                        'timestamp' => now()->toIso8601String(),
                    ]);

                    Notification::make()
                        ->title('Chưa có sản phẩm để xử lý')
                        ->warning()
                        ->send();

                    return;
                }

                $preflight = app(ProductAiGenerationReadiness::class)->resolveMany(
                    $productIds,
                    [\App\Services\Product\ProductContentEligibilityPolicy::LONG_DESCRIPTION],
                );
                if ($preflight['blocked'] > 0) {
                    app(\App\Services\AI\AITechnicalLogger::class)->event('ai_product_preflight', 'generation_preflight_blocked', 'Selected generation excluded Products with mandatory blockers.', [
                        'selected' => $preflight['selected'],
                        'ready' => $preflight['ready'],
                        'blocked' => $preflight['blocked'],
                        'blocked_products' => collect($preflight['rows'])->filter(fn (array $row): bool => ! $row['can_generate'])->map(fn (array $row): array => [
                            'product_id' => $row['product_id'],
                            'guard_codes' => array_column($row['mandatory_blockers'], 'code'),
                        ])->values()->all(),
                        'actor_id' => auth()->id(),
                    ], null, 'warning');
                }
                $productIds = $preflight['ready_ids'];
                if ($productIds === []) {
                    Notification::make()->title('Không có sản phẩm sẵn sàng tạo AI')
                        ->body('Không tạo job vì tất cả mục đã bị chặn ở preflight.')->warning()->persistent()->send();
                    return;
                }

                $config = self::normalizeAiActionData($data, $action);
                $config['guard_policy_version'] = app(\App\Services\AI\AiGuardPolicy::class)->version();
                $config['guard_policy_snapshot'] = app(\App\Services\AI\AiGuardPolicy::class)->snapshot();
                $job = AiProductJob::create(array_merge([
                    'type' => $action,
                    'scope' => $data['scope'] ?? 'selected',
                    'status' => 'queued',
                    'total' => count($productIds),
                    'config_json' => $config,
                    'created_by' => auth()->id(),
                ], SchemaColumns::existing('ai_product_jobs', [
                    'module' => 'ai_product_bulk',
                    'queue_name' => config('ai.governed_queue', 'ai_governed'),
                    'selected_product_ids_json' => $productIds,
                ])));

                app(ProductBulkGenerationManifest::class)->freeze(
                    $job,
                    ProductBulkTargetResolver::SELECTED,
                    $productIds,
                    (int) auth()->id(),
                    [],
                    ['operation' => $action, 'requested_fields' => $config['outputs'] ?? ['content_html']]
                    , auth()->user()
                );
                AiProductContentBatchJob::dispatch($job->id)->onQueue(config('ai.governed_queue', 'ai_governed'));

                $worker = app(AIWorkerReadinessService::class)->snapshot();
                $notification = Notification::make()
                    ->title('Đã đưa AI Product Job vào queue')
                    ->body("Job #{$job->id} sẽ xử lý ".count($productIds)." sản phẩm; preflight loại {$preflight['blocked']}. {$worker['message']}")
                    ->status($worker['ready'] ? 'success' : 'warning')
                    ->persistent();
                if (! $worker['ready'] && auth()->user()?->can('ai_worker.manage')) {
                    $notification->actions([
                        Action::make('manage_ai_worker')->label('Bật AI Worker')->url(\App\Filament\Pages\AIQueueHealth::getUrl()),
                    ]);
                }
                $notification->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    private static function aiBulkReviewAction(): BulkAction
    {
        return BulkAction::make('ai_bulk_review')
            ->label("Duy\u{1EC7}t n\u{1ED9}i dung AI")
            ->icon('heroicon-o-clipboard-document-check')
            ->color('gray')
            ->visible(fn (): bool => (bool) (auth()->user()?->can('bulk_ai_view')
                || auth()->user()?->can('bulk_ai_approve')
                || auth()->user()?->can('bulk_ai_apply')))
            ->modalHeading("Ki\u{1EC3}m tra tr\u{01B0}\u{1EDB}c n\u{1ED9}i dung AI \u{0111}\u{00E3} ch\u{1ECD}n")
            ->modalWidth('7xl')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel("\u{0110}\u{00F3}ng")
            ->modalContent(fn (Collection $records) => view('filament.product-ai-bulk-preflight', [
                'preflight' => app(ProductAiBulkWorkflowService::class)->preflight($records->pluck('id')->all()),
                'mode' => 'review',
            ]))
            ->action(static fn (): null => null);
    }

    private static function aiBulkApproveAction(): BulkAction
    {
        return BulkAction::make('ai_bulk_approve')
            ->label("Duy\u{1EC7}t c\u{00E1}c b\u{1EA3}n nh\u{00E1}p")
            ->icon('heroicon-o-check-badge')
            ->color('success')
            ->visible(fn (): bool => auth()->user()?->can('bulk_ai_approve') ?? false)
            ->disabled(fn (): bool => ! self::canRunBulkAiMutation('APPROVE'))
            ->tooltip(fn (): ?string => self::bulkAiMutationBlockMessage('APPROVE'))
            ->modalHeading("Duy\u{1EC7}t b\u{1EA3}n nh\u{00E1}p AI \u{0111}\u{00E3} ch\u{1ECD}n")
            ->modalWidth('7xl')
            ->modalSubmitActionLabel("Duy\u{1EC7}t c\u{00E1}c b\u{1EA3}n nh\u{00E1}p \u{0111}\u{1EE7} \u{0111}i\u{1EC1}u ki\u{1EC7}n")
            ->modalContent(fn (Collection $records) => view('filament.product-ai-bulk-preflight', [
                'preflight' => app(ProductAiBulkWorkflowService::class)->preflight($records->pluck('id')->all()),
                'mode' => 'approve',
            ]))
            ->form(fn (Collection $records): array => [
                self::bulkProductSelection($records, "Ch\u{1ECD}n b\u{1EA3}n nh\u{00E1}p s\u{1EBD} duy\u{1EC7}t", 'ready_to_approve'),
                Checkbox::make('warning_override')
                    ->label("T\u{00F4}i \u{0111}\u{00E3} xem c\u{1EA3}nh b\u{00E1}o ch\u{1EA5}t l\u{01B0}\u{1EE3}ng v\u{00E0} v\u{1EAB}n mu\u{1ED1}n duy\u{1EC7}t c\u{00E1}c b\u{1EA3}n nh\u{00E1}p c\u{00F3} soft warning."),
                Textarea::make('reason')->label("Ghi ch\u{00FA} duy\u{1EC7}t")->maxLength(1000),
            ])
            ->action(function (Collection $records, array $data, mixed $livewire): void {
                self::runAiBulkWorkflow(ProductAiBulkWorkflowService::ACTION_APPROVE, $records, $data, $livewire);
            });
    }

    private static function aiBulkRejectAction(): BulkAction
    {
        return BulkAction::make('ai_bulk_reject')
            ->label("T\u{1EEB} ch\u{1ED1}i b\u{1EA3}n nh\u{00E1}p")
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (): bool => auth()->user()?->can('bulk_ai_approve') ?? false)
            ->disabled(fn (): bool => ! self::canRunBulkAiMutation('APPROVE'))
            ->tooltip(fn (): ?string => self::bulkAiMutationBlockMessage('APPROVE'))
            ->modalHeading("T\u{1EEB} ch\u{1ED1}i b\u{1EA3}n nh\u{00E1}p AI")
            ->modalWidth('5xl')
            ->modalSubmitActionLabel("T\u{1EEB} ch\u{1ED1}i c\u{00E1}c b\u{1EA3}n nh\u{00E1}p \u{0111}\u{1EE7} \u{0111}i\u{1EC1}u ki\u{1EC7}n")
            ->form(fn (Collection $records): array => [
                self::bulkProductSelection($records, "Ch\u{1ECD}n b\u{1EA3}n nh\u{00E1}p s\u{1EBD} t\u{1EEB} ch\u{1ED1}i", 'ready_to_review'),
                Textarea::make('reason')->label("L\u{00FD} do t\u{1EEB} ch\u{1ED1}i")->required()->minLength(3)->maxLength(1000),
            ])
            ->requiresConfirmation()
            ->action(function (Collection $records, array $data, mixed $livewire): void {
                self::runAiBulkWorkflow(ProductAiBulkWorkflowService::ACTION_REJECT, $records, $data, $livewire);
            });
    }

    private static function aiBulkDiscardAction(): BulkAction
    {
        return BulkAction::make('ai_bulk_discard')
            ->label("Lo\u{1EA1}i b\u{1ECF} b\u{1EA3}n nh\u{00E1}p")
            ->icon('heroicon-o-archive-box-x-mark')
            ->color('danger')
            ->visible(fn (): bool => auth()->user()?->can('bulk_ai_approve') ?? false)
            ->disabled(fn (): bool => ! self::canRunBulkAiMutation('APPROVE'))
            ->tooltip(fn (): ?string => self::bulkAiMutationBlockMessage('APPROVE'))
            ->modalHeading("Lo\u{1EA1}i b\u{1ECF} logic b\u{1EA3}n nh\u{00E1}p AI")
            ->modalDescription("Draft, job, token v\u{00E0} provider evidence v\u{1EAB}n \u{0111}\u{01B0}\u{1EE3}c gi\u{1EEF} nguy\u{00EA}n.")
            ->modalWidth('5xl')
            ->form(fn (Collection $records): array => [
                self::bulkProductSelection($records, "Ch\u{1ECD}n b\u{1EA3}n nh\u{00E1}p s\u{1EBD} lo\u{1EA1}i b\u{1ECF}", 'ready_to_review'),
                Textarea::make('reason')->label("L\u{00FD} do lo\u{1EA1}i b\u{1ECF}")->required()->minLength(3)->maxLength(1000),
            ])
            ->requiresConfirmation()
            ->action(function (Collection $records, array $data, mixed $livewire): void {
                self::runAiBulkWorkflow(ProductAiBulkWorkflowService::ACTION_DISCARD, $records, $data, $livewire);
            });
    }

    private static function aiBulkRegenerateAction(): BulkAction
    {
        return BulkAction::make('ai_bulk_regenerate')
            ->label("T\u{1EA1}o l\u{1EA1}i n\u{1ED9}i dung AI")
            ->icon('heroicon-o-arrow-path')
            ->color('warning')
            ->visible(fn (): bool => auth()->user()?->can('product.ai_generate') ?? false)
            ->disabled(fn (): bool => ! self::canRunBulkAiMutation('GENERATE'))
            ->tooltip(fn (): ?string => self::bulkAiMutationBlockMessage('GENERATE'))
            ->modalHeading("T\u{1EA1}o l\u{1EA1}i n\u{1ED9}i dung AI")
            ->modalWidth('7xl')
            ->modalSubmitActionLabel("G\u{1EED}i c\u{00E1}c y\u{00EA}u c\u{1EA7}u \u{0111}\u{1EE7} \u{0111}i\u{1EC1}u ki\u{1EC7}n")
            ->modalContent(fn (Collection $records) => view('filament.product-ai-bulk-preflight', [
                'preflight' => app(ProductAiBulkWorkflowService::class)->preflight($records->pluck('id')->all()),
                'mode' => 'regenerate',
            ]))
            ->form(fn (Collection $records): array => [
                self::bulkProductSelection($records, "Ch\u{1ECD}n s\u{1EA3}n ph\u{1EA9}m s\u{1EBD} t\u{1EA1}o l\u{1EA1}i", 'regenerate_available'),
                CheckboxList::make('outputs')
                    ->label("Tr\u{01B0}\u{1EDD}ng c\u{1EA7}n t\u{1EA1}o")
                    ->options([
                        'content' => "N\u{1ED9}i dung", 'seo' => 'SEO', 'merchant' => 'Merchant',
                        'tags' => 'Tags', 'faq' => 'FAQ', 'internal_links' => "Li\u{00EA}n k\u{1EBF}t n\u{1ED9}i b\u{1ED9}", 'og' => 'Open Graph',
                    ])
                    ->default(['content', 'seo', 'merchant', 'tags', 'faq', 'internal_links', 'og'])
                    ->columns(2),
            ])
            ->requiresConfirmation()
            ->action(function (Collection $records, array $data, mixed $livewire): void {
                self::runAiBulkWorkflow(ProductAiBulkWorkflowService::ACTION_REGENERATE, $records, $data, $livewire);
            });
    }

    private static function aiBulkApplyAction(): BulkAction
    {
        return BulkAction::make('ai_bulk_apply')
            ->label("\u{00C1}p d\u{1EE5}ng n\u{1ED9}i dung \u{0111}\u{00E3} duy\u{1EC7}t")
            ->icon('heroicon-o-cloud-arrow-up')
            ->color('primary')
            ->visible(fn (): bool => auth()->user()?->can('bulk_ai_apply') ?? false)
            ->disabled(fn (): bool => ! self::canRunBulkAiMutation('APPLY'))
            ->tooltip(fn (): ?string => self::bulkAiMutationBlockMessage('APPLY'))
            ->modalHeading("\u{00C1}p d\u{1EE5}ng n\u{1ED9}i dung AI \u{0111}\u{00E3} duy\u{1EC7}t")
            ->modalWidth('7xl')
            ->modalSubmitActionLabel("X\u{00E1}c nh\u{1EAD}n \u{00E1}p d\u{1EE5}ng")
            ->modalContent(fn (Collection $records) => view('filament.product-ai-bulk-preflight', [
                'preflight' => app(ProductAiBulkWorkflowService::class)->preflight($records->pluck('id')->all()),
                'mode' => 'apply',
            ]))
            ->form(fn (Collection $records): array => self::bulkApplyForm($records))
            ->action(function (Collection $records, array $data, mixed $livewire): void {
                self::runAiBulkWorkflow(ProductAiBulkWorkflowService::ACTION_APPLY, $records, $data, $livewire);
            });
    }

    private static function canRunBulkAiMutation(string $action): bool
    {
        $actor = auth()->user();
        if (! $actor) {
            return false;
        }

        $policy = app(SingleOperatorControlledRolloutPolicy::class);
        if (! $policy->active()) {
            return true;
        }

        try {
            $policy->assertAction($actor, $action);

            return true;
        } catch (\RuntimeException) {
            return false;
        }
    }

    private static function bulkAiMutationBlockMessage(string $action): ?string
    {
        if (self::canRunBulkAiMutation($action)) {
            return null;
        }

        $policy = app(SingleOperatorControlledRolloutPolicy::class);
        if ($policy->active()) {
            return "Ch\u{1EC9} operator \u{0111}\u{01B0}\u{1EE3}c ch\u{1EC9} \u{0111}\u{1ECB}nh m\u{1EDB}i c\u{00F3} th\u{1EC3} th\u{1EF1}c hi\u{1EC7}n thao t\u{00E1}c AI trong \u{0111}\u{1EE3}t rollout n\u{00E0}y.";
        }

        return "B\u{1EA1}n kh\u{00F4}ng c\u{00F3} quy\u{1EC1}n th\u{1EF1}c hi\u{1EC7}n thao t\u{00E1}c n\u{00E0}y.";
    }

    private static function bulkProductSelection(Collection $records, string $label, ?string $eligibleFlag = null): CheckboxList
    {
        $preflight = app(ProductAiBulkWorkflowService::class)->preflight($records->pluck('id')->all());
        $options = collect($preflight['rows'])->mapWithKeys(fn (array $row): array => [
            (string) $row['product_id'] => $row['product_name'].' · '.$row['state']
                .($row['soft_warning_count'] ? " · {$row['soft_warning_count']} cảnh báo" : '')
                .($row['hard_blocker_count'] ? " · {$row['hard_blocker_count']} blocker" : ''),
        ])->all();

        $default = $eligibleFlag
            ? collect($preflight['rows'])->filter(fn (array $row): bool => (bool) ($row[$eligibleFlag] ?? false))->pluck('product_id')->map(fn ($id): string => (string) $id)->all()
            : array_keys($options);

        return CheckboxList::make('product_ids')
            ->label($label)
            ->options($options)
            ->default($default)
            ->columns(1)
            ->required();
    }

    private static function bulkApplyForm(Collection $records): array
    {
        $preflight = app(ProductAiBulkWorkflowService::class)->preflight($records->pluck('id')->all());
        $ready = collect($preflight['rows'])->where('ready_to_apply', true);
        $expected = app(ProductAiBulkWorkflowService::class)->expectedApplyConfirmation($ready->count());
        $selection = self::bulkProductSelection($records, "Ch\u{1ECD}n s\u{1EA3}n ph\u{1EA9}m s\u{1EBD} \u{00E1}p d\u{1EE5}ng");
        $selection->default($ready->pluck('product_id')->map(fn ($id): string => (string) $id)->all());

        return [
            $selection,
            TextInput::make('confirmation')
                ->label("M\u{00E3} x\u{00E1}c nh\u{1EAD}n \u{00E1}p d\u{1EE5}ng")
                ->helperText("Nh\u{1EAD}p ch\u{00ED}nh x\u{00E1}c: {$expected}. Kh\u{00F4}ng thay \u{0111}\u{1ED5}i SKU, gi\u{00E1}, danh m\u{1EE5}c ho\u{1EB7}c th\u{00F4}ng s\u{1ED1} k\u{1EF9} thu\u{1EAD}t.")
                ->required(),
        ];
    }

    private static function runAiBulkWorkflow(string $action, Collection $records, array $data, mixed $livewire): void
    {
        $recordIds = $records->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $requestedIds = array_values(array_intersect(
            $recordIds,
            array_map('intval', (array) ($data['product_ids'] ?? $recordIds)),
        ));
        $selectionMode = (bool) ($livewire->isTrackingDeselectedTableRecords ?? false)
            ? ProductBulkTargetResolver::ALL_MATCHING
            : ProductBulkTargetResolver::SELECTED;
        $result = app(ProductAiBulkWorkflowService::class)->execute(
            $action,
            $requestedIds,
            auth()->user(),
            $data,
            $selectionMode,
            [
                'table_filters' => $livewire->tableFilters ?? [],
                'table_search' => $livewire->tableSearch ?? null,
            ],
        );
        $summary = $result['summary'];
        Notification::make()
            ->title("{$action}: {$summary['success']}/{$summary['selected']} th\u{00E0}nh c\u{00F4}ng")
            ->body("B\u{1ECF} qua {$summary['skipped']} \u{00B7} B\u{1ECB} ch\u{1EB7}n {$summary['blocked']} \u{00B7} Th\u{1EA5}t b\u{1EA1}i {$summary['failed']} \u{00B7} Operation {$result['operation']->operation_uuid}")
            ->status(($summary['failed'] + $summary['blocked']) > 0 ? 'warning' : 'success')
            ->persistent()
            ->send();
    }

    public static function aiConfigForm(
        array $defaultOutputs,
        string $defaultMode,
        string $defaultScope = 'selected',
        ?array $scopeOptions = null
    ): array
    {
        $scopeOptions ??= [
            'selected' => 'Sản phẩm đã chọn',
            'current_page' => 'Trang hiện tại',
            'filter' => 'Theo bộ lọc hiện tại',
        ];

        return [
            Select::make('scope')
                ->label('Scope')
                ->options($scopeOptions)
                ->default($defaultScope)
                ->live()
                ->required(),
            CheckboxList::make('outputs')
                ->label('Output cần tạo')
                ->options([
                    'content' => 'Nội dung',
                    'seo' => 'SEO',
                    'merchant' => 'Google Merchant',
                    'tags' => 'Tags',
                    'faq' => 'FAQ kỹ thuật',
                    'internal_links' => 'Internal links',
                    'og' => 'OG metadata',
                ])
                ->columns(2)
                ->default($defaultOutputs),
            Select::make('mode')
                ->label('Mode')
                ->options([
                    'missing_only' => 'Generate only missing fields',
                    'rewrite_all' => 'Rewrite all',
                    'rewrite_weak' => 'Rewrite only weak content',
                    'force_overwrite' => 'Force overwrite',
                ])
                ->default($defaultMode)
                ->required(),
            Select::make('depth')
                ->label('Depth')
                ->options([
                    'basic' => 'Basic',
                    'seo' => 'SEO chuẩn',
                    'deep_hvac' => 'Chuyên sâu HVAC',
                ])
                ->default('seo')
                ->required(),
            Select::make('tone')
                ->label('Tone')
                ->options([
                    'hvac_expert' => 'Chuyên gia HVAC',
                    'technical_consulting' => 'Tư vấn kỹ thuật',
                    'soft_sales' => 'Bán hàng nhẹ',
                    'b2b_project' => 'B2B công trình',
                ])
                ->default('hvac_expert')
                ->required(),
            Select::make('apply_mode')
                ->label('Apply mode')
                ->options([
                    'draft_only' => 'Draft only',
                    'auto_apply_safe_fields' => 'Auto apply safe fields',
                    'full_auto_if_passed' => 'Full auto if passed',
                    'needs_review' => 'Generate draft only / needs review',
                    'auto_apply' => 'Auto apply',
                ])
                ->default('draft_only')
                ->required(),
            Select::make('batch_size')
                ->label('Batch size')
                ->options([
                    10 => '10',
                    20 => '20',
                    50 => '50',
                ])
                ->default(10)
                ->required(),
        ];
    }

    public static function normalizeAiActionData(array $data, string $action): array
    {
        $selectedOutputs = array_fill_keys($data['outputs'] ?? [], true);

        return [
            'action' => $action,
            'mode' => $data['mode'] ?? 'missing_only',
            'depth' => $data['depth'] ?? 'seo',
            'tone' => $data['tone'] ?? 'hvac_expert',
            'apply_mode' => $data['apply_mode'] ?? 'needs_review',
            'batch_size' => (int) ($data['batch_size'] ?? 10),
            'outputs' => [
                'content' => ! empty($selectedOutputs['content']),
                'seo' => ! empty($selectedOutputs['seo']),
                'merchant' => ! empty($selectedOutputs['merchant']),
                'tags' => ! empty($selectedOutputs['tags']),
                'faq' => ! empty($selectedOutputs['faq']),
                'internal_links' => ! empty($selectedOutputs['internal_links']),
                'og' => ! empty($selectedOutputs['og']),
            ],
        ];
    }

    private static function resolveProductIds(Collection $records, array $data, mixed $livewire): array
    {
        if (in_array($data['scope'] ?? 'selected', ['filter', 'all_filtered'], true) && $livewire && method_exists($livewire, 'getFilteredTableQuery')) {
            return $livewire->getFilteredTableQuery()
                ->pluck('products.id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        return $records->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public static function retryAiProductItems(iterable $items): int
    {
        $count = 0;
        $actor = auth()->user() ?? throw new \RuntimeException('AUTHENTICATED_RETRY_ACTOR_REQUIRED');

        foreach ($items as $item) {
            if (! $item instanceof AiProductJobItem) {
                $item = AiProductJobItem::find($item->id ?? null);
            }

            if (! $item) {
                continue;
            }

            [$newJob, $newItem] = app(\App\Services\AI\AiProductLifecycleService::class)
                ->retryAsNewOperation($item, $actor);
            AiProductContentSingleJob::dispatch(
                $newItem->product_id, $newJob->id, $newItem->id, $newItem->dispatch_uuid,
            )->onQueue(config('ai.governed_queue', 'ai_governed'));
            $count++;
        }

        return $count;
    }

    private static function aiStatusView(Product $record): array
    {
        $resolved = self::resolvedAiState($record);

        return app(AiContentStatusPresenter::class)->present(
            $resolved['status'],
        );
    }

    private static function applyAiStateFilter(Builder $query, ?string $state): Builder
    {
        if (blank($state)) {
            return $query;
        }

        $activeItem = fn (Builder $item): Builder => $item
            ->whereIn('canonical_status', \App\Services\AI\AiProductStateCompatibility::ACTIVE)
            ->whereNotIn('status', ['needs_review', 'completed', 'completed_verified', 'failed', 'blocked', 'cancelled', 'completed_with_errors']);
        $actionableDraft = fn (Builder $draft): Builder => $draft
            ->whereNull('applied_at')
            ->where(fn (Builder $state): Builder => $state
                ->where(fn (Builder $review): Builder => $review
                    ->where('approval_status', 'REVIEW_REQUIRED')
                    ->whereIn('status', ['needs_review', 'REVIEW_REQUIRED']))
                ->orWhere('approval_status', 'APPROVED_FOR_APPLY')
                ->orWhere('status', 'applying'));

        if (in_array($state, ['available', 'not_generated'], true)) {
            return $query
                ->whereDoesntHave('aiProductJobItems', $activeItem)
                ->whereDoesntHave('aiProductDrafts', $actionableDraft)
                ->whereDoesntHave('aiProductJobItems', fn (Builder $item): Builder => $item
                    ->where(fn (Builder $status): Builder => $status
                        ->where('canonical_status', 'REVIEW_REQUIRED')
                        ->orWhere('status', 'needs_review'))
                    ->whereDoesntHave('draft'));
        }

        if (in_array($state, ['history_applied', 'applied'], true)) {
            return $query->whereHas('latestAiProductJobItem.draft', fn (Builder $draft): Builder => $draft
                ->whereNotNull('applied_at'));
        }

        if ($state === 'approved') {
            return $query->whereDoesntHave('aiProductJobItems', $activeItem)
                ->whereHas('aiProductDrafts', fn (Builder $draft): Builder => $draft
                ->where('approval_status', 'APPROVED_FOR_APPLY')
                ->whereNull('applied_at'))
                ->whereHas('aiProductDrafts', $actionableDraft, '=', 1);
        }

        if ($state === 'review_required') {
            return $query->whereDoesntHave('aiProductJobItems', $activeItem)
                ->whereHas('aiProductDrafts', fn (Builder $draft): Builder => $draft
                    ->whereIn('status', ['needs_review', 'REVIEW_REQUIRED'])
                    ->whereNull('applied_at')
                    ->where('approval_status', 'REVIEW_REQUIRED'))
                ->whereHas('aiProductDrafts', $actionableDraft, '=', 1);
        }

        if (in_array($state, ['current_blocked', 'blocked'], true)) {
            return $query->where(function (Builder $blocked) use ($activeItem, $actionableDraft): Builder {
                return $blocked
                    ->whereHas('aiProductJobItems', $activeItem, '>=', 2)
                    ->orWhereHas('aiProductDrafts', $actionableDraft, '>=', 2)
                    ->orWhereHas('aiProductJobItems', fn (Builder $item): Builder => $item
                        ->where(fn (Builder $status): Builder => $status
                            ->where('canonical_status', 'REVIEW_REQUIRED')
                            ->orWhere('status', 'needs_review'))
                        ->whereDoesntHave('draft'));
            });
        }

        if (in_array($state, ['history_failed', 'failed'], true)) {
            return $query->whereHas('latestAiProductJobItem', fn (Builder $item): Builder => $item
                ->where(fn (Builder $status): Builder => $status
                    ->where('canonical_status', 'FAILED')
                    ->orWhereIn('status', ['failed', 'stuck'])));
        }

        $statuses = match ($state) {
            'queued' => [['QUEUED'], ['queued']],
            'processing' => [['RUNNING', 'VALIDATING', 'FACT_CHECKING'], ['processing', 'validating']],
            default => [[], []],
        };

        if ($statuses[0] === [] && $statuses[1] === []) {
            return $query;
        }

        return $query->whereHas('aiProductJobItems', fn (Builder $item): Builder => $item
            ->where(function (Builder $status) use ($statuses): Builder {
                return $status->whereIn('canonical_status', $statuses[0])
                    ->orWhereIn('status', $statuses[1]);
            })
            ->whereNotIn('status', ['needs_review', 'completed', 'completed_verified', 'failed', 'blocked', 'cancelled', 'completed_with_errors']));
    }

    private static function aiStatusTooltip(Product $record): ?string
    {
        $resolved = self::resolvedAiState($record);
        $item = $resolved['item'];
        $historyItem = $resolved['latest_history']['item'];
        $view = self::aiStatusView($record);
        $parts = array_filter([
            $view['warning'],
            $resolved['state_issue'] === 'REVIEWABLE_DRAFT_MISSING'
                ? 'Trạng thái cũ không còn bản nháp có thể duyệt.'
                : null,
            app(AiContentStatusPresenter::class)->safeReason($item?->failed_reason ?: $item?->last_error_code),
            $resolved['latest_history']['status']
                ? 'Lịch sử gần nhất: '.$resolved['latest_history']['status']
                : null,
            $resolved['latest_history']['reason']
                ? app(AiContentStatusPresenter::class)->safeReason($resolved['latest_history']['reason'])
                : null,
            $historyItem?->updated_at ? 'Cập nhật '.$historyItem->updated_at->diffForHumans() : null,
        ]);

        return $parts === [] ? null : implode("\n", $parts);
    }

    private static function aiStatusDetailHtml(Product $record): string
    {
        $resolved = self::resolvedAiState($record);
        $item = $resolved['item'] ?: $resolved['latest_history']['item'];
        $factCheck = $item?->generated_payload_json['fact_check'] ?? [];
        $blocked = $item?->generated_payload_json['blocked_claims'] ?? [];
        $blockedFields = $item?->generated_payload_json['blocked_product_data_fields'] ?? [];

        $rows = [
            'Trạng thái hiện tại' => self::aiStatusView($record)['label'],
            'Lịch sử gần nhất' => $resolved['latest_history']['status'],
            'SEO score' => (string) ($record->ai_score ?? 0),
            'Lý do lịch sử' => app(AiContentStatusPresenter::class)->safeReason($resolved['latest_history']['reason']),
            'Cập nhật' => $item?->updated_at?->diffForHumans(),
        ];

        $html = '<div class="max-h-[70vh] space-y-3 overflow-auto pr-2 text-sm">';
        foreach ($rows as $label => $value) {
            if (blank($value) && $value !== '0') {
                continue;
            }

            $html .= '<div class="space-y-1">';
            $html .= '<div class="font-semibold text-slate-700">'.e($label).'</div>';
            $html .= '<div class="break-words rounded bg-slate-50 px-3 py-2 text-slate-900">'.e((string) $value).'</div>';
            $html .= '</div>';
        }

        if (is_array($factCheck) && $factCheck !== []) {
            $html .= '<div class="space-y-1">';
            $html .= '<div class="font-semibold text-slate-700">fact_check</div>';
            $html .= '<pre class="max-h-64 overflow-auto rounded bg-slate-50 px-3 py-2 text-xs leading-5 whitespace-pre-wrap break-words">'.e(json_encode($factCheck, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)).'</pre>';
            $html .= '</div>';
        }

        if (is_array($blocked) && $blocked !== []) {
            $html .= '<div class="space-y-1">';
            $html .= '<div class="font-semibold text-slate-700">blocked_claims</div>';
            $html .= '<div class="rounded bg-slate-50 px-3 py-2 text-slate-900">'.e(implode(', ', $blocked)).'</div>';
            $html .= '</div>';
        }

        if (is_array($blockedFields) && $blockedFields !== []) {
            $html .= '<div class="space-y-1">';
            $html .= '<div class="font-semibold text-slate-700">blocked_fields</div>';
            $html .= '<div class="rounded bg-slate-50 px-3 py-2 text-slate-900">'.e(implode(', ', $blockedFields)).'</div>';
            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /** @return array<string,mixed> */
    private static function resolvedAiState(Product $record): array
    {
        return app(AiProductContentStateResolver::class)->resolve($record);
    }

    private static function aiTechnicalLogsText(Product $record): string
    {
        $itemIds = $record->aiProductJobItems()->pluck('id');

        return AiTechnicalLog::query()
            ->where('ai_job_type', 'AiProductJobItem')
            ->whereIn('ai_job_id', $itemIds)
            ->latest('id')
            ->limit(40)
            ->get()
            ->map(fn ($log) => '['.$log->created_at?->format('Y-m-d H:i:s')."] {$log->level} {$log->event}: {$log->message}\n"
                .json_encode($log->context_json ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
            ->implode("\n\n") ?: 'No technical logs.';
    }
}
