<?php

namespace App\Filament\Resources\Products\Tables;

use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('slug')
                    ->searchable(),
                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable(),
                TextColumn::make('model_code')
                    ->searchable(),
                TextColumn::make('brand.name')
                    ->searchable(),
                TextColumn::make('product_category_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('series')
                    ->searchable(),
                TextColumn::make('btu')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('inverter')
                    ->boolean(),
                TextColumn::make('cooling_type')
                    ->searchable(),
                TextColumn::make('voltage')
                    ->searchable(),
                TextColumn::make('refrigerant_gas')
                    ->searchable(),
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
                ImageColumn::make('main_image'),
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
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    // Phân loại
                    \Filament\Actions\BulkActionGroup::make([
                        \Filament\Actions\BulkAction::make('bulk_update_category')
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
                                DB::transaction(function () use ($records, $data) {
                                    $records->each->update(['product_category_id' => $data['product_category_id']]);
                                });
                            })
                            ->deselectRecordsAfterCompletion(),

                        \Filament\Actions\BulkAction::make('bulk_update_brand')
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
                                DB::transaction(function () use ($records, $data) {
                                    $records->each->update(['brand_id' => $data['brand_id']]);
                                });
                            })
                            ->deselectRecordsAfterCompletion(),
                    ])->label('Phân loại')->icon('heroicon-o-folder-open'),

                    // Hiển thị & Tồn kho
                    \Filament\Actions\BulkActionGroup::make([
                        \Filament\Actions\BulkAction::make('bulk_activate')
                            ->label('Hiển thị sản phẩm')
                            ->icon('heroicon-o-eye')
                            ->requiresConfirmation()
                            ->action(function (Collection $records) {
                                DB::transaction(fn () => $records->each->update(['is_active' => true]));
                            })
                            ->deselectRecordsAfterCompletion(),
                        
                        \Filament\Actions\BulkAction::make('bulk_deactivate')
                            ->label('Ẩn sản phẩm')
                            ->icon('heroicon-o-eye-slash')
                            ->requiresConfirmation()
                            ->action(function (Collection $records) {
                                DB::transaction(fn () => $records->each->update(['is_active' => false]));
                            })
                            ->deselectRecordsAfterCompletion(),

                        \Filament\Actions\BulkAction::make('bulk_stock_status')
                            ->label('Cập nhật tình trạng hàng')
                            ->icon('heroicon-o-cube')
                            ->form([
                                Select::make('stock_status')
                                    ->label('Trạng thái')
                                    ->options(\App\Enums\StockStatus::class)
                                    ->required(),
                            ])
                            ->action(function (Collection $records, array $data) {
                                DB::transaction(fn () => $records->each->update(['stock_status' => $data['stock_status']]));
                            })
                            ->deselectRecordsAfterCompletion(),
                    ])->label('Hiển thị')->icon('heroicon-o-eye'),

                    // Giá & Khuyến mãi
                    \Filament\Actions\BulkAction::make('bulk_pricing')
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
                            DB::transaction(function () use ($records, $data) {
                                foreach ($records as $record) {
                                    $updates = [];
                                    if (!empty($data['regular_price'])) $updates['regular_price'] = $data['regular_price'];
                                    
                                    if (!empty($data['sale_price'])) $updates['sale_price'] = $data['sale_price'];
                                    elseif (!empty($data['clear_sale_price'])) $updates['sale_price'] = null;

                                    if (!empty($data['discount_percent'])) $updates['discount_percent'] = $data['discount_percent'];
                                    elseif (!empty($data['clear_discount'])) $updates['discount_percent'] = null;

                                    if (!empty($data['promotion_start_at'])) $updates['promotion_start_at'] = $data['promotion_start_at'];
                                    elseif (!empty($data['clear_promotion_dates'])) $updates['promotion_start_at'] = null;

                                    if (!empty($data['promotion_end_at'])) $updates['promotion_end_at'] = $data['promotion_end_at'];
                                    elseif (!empty($data['clear_promotion_dates'])) $updates['promotion_end_at'] = null;

                                    if (!empty($updates)) {
                                        $record->update($updates);
                                    }
                                }
                            });
                        })
                        ->deselectRecordsAfterCompletion(),

                    // SEO
                    \Filament\Actions\BulkAction::make('bulk_seo_robots')
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
                            DB::transaction(fn () => $records->each->update(['robots' => $data['robots']]));
                        })
                        ->deselectRecordsAfterCompletion(),

                    // Badge
                    \Filament\Actions\BulkAction::make('bulk_badges')
                        ->label('Cập nhật Badge')
                        ->icon('heroicon-o-star')
                        ->form([
                            Select::make('is_featured')->label('Nổi bật')->options(['no_change' => 'Không đổi', '1' => 'Có', '0' => 'Không'])->default('no_change'),
                            Select::make('is_bestseller')->label('Bán chạy')->options(['no_change' => 'Không đổi', '1' => 'Có', '0' => 'Không'])->default('no_change'),
                            Select::make('is_new')->label('Mới')->options(['no_change' => 'Không đổi', '1' => 'Có', '0' => 'Không'])->default('no_change'),
                        ])
                        ->action(function (Collection $records, array $data) {
                            DB::transaction(function () use ($records, $data) {
                                foreach ($records as $record) {
                                    $updates = [];
                                    if ($data['is_featured'] !== 'no_change') $updates['is_featured'] = (bool)$data['is_featured'];
                                    if ($data['is_bestseller'] !== 'no_change') $updates['is_bestseller'] = (bool)$data['is_bestseller'];
                                    if ($data['is_new'] !== 'no_change') $updates['is_new'] = (bool)$data['is_new'];
                                    if (!empty($updates)) $record->update($updates);
                                }
                            });
                        })
                        ->deselectRecordsAfterCompletion(),

                    // Thuộc tính kỹ thuật
                    \Filament\Actions\BulkAction::make('bulk_tech_attributes')
                        ->label('Cập nhật thông số cơ bản')
                        ->icon('heroicon-o-cog')
                        ->form([
                            Select::make('inverter')->label('Inverter')->options(['no_change' => 'Không đổi', '1' => 'Có', '0' => 'Không'])->default('no_change'),
                            Select::make('cooling_type')->label('Kiểu làm lạnh')->options(['no_change' => 'Không đổi', '1 chiều' => '1 chiều', '2 chiều' => '2 chiều'])->default('no_change'),
                            TextInput::make('voltage')->label('Điện áp'),
                            TextInput::make('refrigerant_gas')->label('Loại Gas'),
                        ])
                        ->action(function (Collection $records, array $data) {
                            DB::transaction(function () use ($records, $data) {
                                foreach ($records as $record) {
                                    $updates = [];
                                    if ($data['inverter'] !== 'no_change') $updates['inverter'] = (bool)$data['inverter'];
                                    if ($data['cooling_type'] !== 'no_change') $updates['cooling_type'] = $data['cooling_type'];
                                    if (!empty($data['voltage'])) $updates['voltage'] = $data['voltage'];
                                    if (!empty($data['refrigerant_gas'])) $updates['refrigerant_gas'] = $data['refrigerant_gas'];
                                    if (!empty($updates)) $record->update($updates);
                                }
                            });
                        })
                        ->deselectRecordsAfterCompletion(),

                    // Tag
                    \Filament\Actions\BulkActionGroup::make([
                        \Filament\Actions\BulkAction::make('bulk_attach_tags')
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
                                DB::transaction(function () use ($records, $data) {
                                    foreach ($records as $record) {
                                        $record->tags()->syncWithoutDetaching($data['tags']);
                                    }
                                });
                            })
                            ->deselectRecordsAfterCompletion(),
                        
                        \Filament\Actions\BulkAction::make('bulk_detach_tags')
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
                                DB::transaction(function () use ($records, $data) {
                                    foreach ($records as $record) {
                                        $record->tags()->detach($data['tags']);
                                    }
                                });
                            })
                            ->deselectRecordsAfterCompletion(),
                    ])->label('Tag')->icon('heroicon-o-hashtag'),

                    // Default Bulk Delete
                    \Filament\Actions\DeleteBulkAction::make(),
                    \Filament\Actions\ForceDeleteBulkAction::make(),
                    \Filament\Actions\RestoreBulkAction::make(),
                ]),
            ]);
    }
}
