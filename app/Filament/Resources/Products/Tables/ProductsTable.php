<?php

namespace App\Filament\Resources\Products\Tables;

use App\Enums\StockStatus;
use App\Jobs\AiProductContentSingleJob;
use App\Jobs\AiProductContentBatchJob;
use App\Services\AI\ProductBulkGenerationManifest;
use App\Services\AI\ProductBulkTargetResolver;
use App\Services\AI\AiContentStatusPresenter;
use App\Services\AI\AiProductContentStateResolver;
use App\Services\AI\AIWorkerReadinessService;
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
                'latestAiProductJobItem.draft:id,status,field_status_json,approval_status,approved_at,approved_by,applied_at',
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
                    ->state(fn (Product $record) => $record->latestAiProductJobItem?->state_changed_at
                        ?: $record->latestAiProductJobItem?->updated_at
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
                        'not_generated' => 'Chưa tạo',
                        'queued' => 'Đang chờ',
                        'processing' => 'Đang xử lý',
                        'review_required' => 'Chờ duyệt',
                        'approved' => 'Đã duyệt',
                        'applied' => 'Đã áp dụng',
                        'blocked' => 'Bị chặn',
                        'failed' => 'Thất bại',
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
                        BulkAction::make('ai_retry_selected_failed')
                            ->label('Retry selected failed')
                            ->icon('heroicon-o-arrow-path')
                            ->color('warning')
                            ->visible(fn () => auth()->user()?->can('product.ai_generate') ?? false)
                            ->requiresConfirmation()
                            ->action(function (Collection $records) {
                                abort_unless(auth()->user()?->can('product.ai_generate'), 403);

                                $items = AiProductJobItem::query()
                                    ->whereIn('product_id', $records->pluck('id')->all())
                                    ->whereIn('status', ['failed', 'stuck', 'cancelled'])
                                    ->latest('id')
                                    ->get();
                                $count = self::retryAiProductItems($items);

                                Log::info('AI product retry selected payload', [
                                    'source' => 'products_bulk_action',
                                    'selected_product_count' => $records->count(),
                                    'selected_product_ids_sample' => $records->pluck('id')->take(25)->values()->all(),
                                    'item_count' => $count,
                                ]);

                                Notification::make()
                                    ->title($count > 0 ? "Đã retry {$count} AI item" : 'Không có AI item lỗi để retry')
                                    ->status($count > 0 ? 'success' : 'warning')
                                    ->send();
                            }),
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

                $config = self::normalizeAiActionData($data, $action);
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
                    ->body("Job #{$job->id} sẽ xử lý ".count($productIds)." sản phẩm. {$worker['message']}")
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

        foreach ($items as $item) {
            if (! $item instanceof AiProductJobItem) {
                $item = AiProductJobItem::find($item->id ?? null);
            }

            if (! $item) {
                continue;
            }

            $item->update(SchemaColumns::existing('ai_product_job_items', [
                'status' => 'queued',
                'canonical_status' => 'QUEUED',
                'status_reason' => 'AUTHORIZED_RETRY',
                'state_changed_at' => now(),
                'retry_count' => (int) ($item->retry_count ?? 0) + 1,
                'error_message' => null,
                'failed_reason' => null,
                'last_error_code' => null,
                'last_error_message' => null,
                'exception_class' => null,
                'exception_file' => null,
                'exception_line' => null,
                'stack_trace' => null,
                'finished_at' => null,
            ]));

            if ($item->job) {
                $item->job->update(SchemaColumns::existing('ai_product_jobs', [
                    'status' => 'processing',
                    'canonical_status' => 'RUNNING',
                    'status_reason' => 'AUTHORIZED_FIELD_RETRY',
                    'state_changed_at' => now(),
                    'finished_at' => null,
                    'failed_reason' => null,
                    'last_error_code' => null,
                    'last_error_message' => null,
                ]));
            }

            AiProductContentSingleJob::dispatch($item->product_id, $item->ai_product_job_id, $item->id)->onQueue(config('ai.governed_queue', 'ai_governed'));
            $count++;
        }

        return $count;
    }

    private static function aiStatusView(Product $record): array
    {
        $resolved = app(AiProductContentStateResolver::class)->resolve(
            $record,
            $record->latestAiProductJobItem,
        );

        return app(AiContentStatusPresenter::class)->present(
            $resolved['status'],
        );
    }

    private static function applyAiStateFilter(Builder $query, ?string $state): Builder
    {
        if (blank($state)) {
            return $query;
        }

        if ($state === 'not_generated') {
            return $query->whereDoesntHave('latestAiProductJobItem');
        }

        if ($state === 'applied') {
            return $query->whereHas('latestAiProductJobItem.draft', fn (Builder $draft): Builder => $draft
                ->whereNotNull('applied_at'));
        }

        if ($state === 'approved') {
            return $query->whereHas('latestAiProductJobItem.draft', fn (Builder $draft): Builder => $draft
                ->where('approval_status', 'APPROVED_FOR_APPLY')
                ->whereNull('applied_at'));
        }

        if ($state === 'review_required') {
            return $query->whereHas('latestAiProductJobItem', fn (Builder $item): Builder => $item
                ->where(function (Builder $status): Builder {
                    return $status->where('canonical_status', 'REVIEW_REQUIRED')
                        ->orWhere('status', 'needs_review');
                })
                ->whereHas('draft', fn (Builder $draft): Builder => $draft
                    ->whereIn('status', ['needs_review', 'REVIEW_REQUIRED'])
                    ->whereNull('applied_at')
                    ->whereNotIn('approval_status', ['APPROVED_FOR_APPLY', 'APPLIED', 'REJECTED'])));
        }

        $statuses = match ($state) {
            'queued' => [['QUEUED'], ['queued']],
            'processing' => [['RUNNING', 'VALIDATING', 'FACT_CHECKING'], ['processing', 'validating']],
            'blocked' => [['BLOCKED'], ['blocked']],
            'failed' => [['FAILED'], ['failed', 'stuck']],
            default => [[], []],
        };

        if ($statuses[0] === [] && $statuses[1] === []) {
            return $query;
        }

        return $query->whereHas('latestAiProductJobItem', fn (Builder $item): Builder => $item
            ->where(function (Builder $status) use ($statuses): Builder {
                return $status->whereIn('canonical_status', $statuses[0])
                    ->orWhereIn('status', $statuses[1]);
            }));
    }

    private static function aiStatusTooltip(Product $record): ?string
    {
        $item = $record->latestAiProductJobItem;
        $view = self::aiStatusView($record);
        $resolved = app(AiProductContentStateResolver::class)->resolve($record, $item);
        $parts = array_filter([
            $view['warning'],
            $resolved['state_issue'] === 'REVIEWABLE_DRAFT_MISSING'
                ? 'Trạng thái cũ không còn bản nháp có thể duyệt.'
                : null,
            app(AiContentStatusPresenter::class)->safeReason($item?->failed_reason ?: $item?->last_error_code),
            $item?->updated_at ? 'Cập nhật '.$item->updated_at->diffForHumans() : null,
        ]);

        return $parts === [] ? null : implode("\n", $parts);
    }

    private static function aiStatusDetailHtml(Product $record): string
    {
        $item = $record->latestAiProductJobItem;
        $factCheck = $item?->generated_payload_json['fact_check'] ?? [];
        $blocked = $item?->generated_payload_json['blocked_claims'] ?? [];
        $blockedFields = $item?->generated_payload_json['blocked_product_data_fields'] ?? [];

        $rows = [
            'Trạng thái nội dung AI' => self::aiStatusView($record)['label'],
            'SEO score' => (string) ($record->ai_score ?? 0),
            'Lý do' => app(AiContentStatusPresenter::class)->safeReason($item?->failed_reason ?: $item?->last_error_code),
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
