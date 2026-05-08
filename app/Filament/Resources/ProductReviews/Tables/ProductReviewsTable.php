<?php

namespace App\Filament\Resources\ProductReviews\Tables;

use App\Services\Mail\MailDispatchService;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class ProductReviewsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product.name')
                    ->label('Sản phẩm')
                    ->searchable()
                    ->limit(30)
                    ->sortable(),
                TextColumn::make('customer_name')
                    ->label('Khách hàng')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('rating')
                    ->label('Sao')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => str_repeat('', $state ?? 0) . ' (' . $state . ')'),
                TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'pending' => 'warning',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
                IconColumn::make('is_verified_purchase')
                    ->label('Xác nhận')
                    ->boolean()
                    ->sortable(),
                // Image thumbnail — first image in images_json
                TextColumn::make('images_json')
                    ->label('Ảnh')
                    ->formatStateUsing(function ($state) {
                        if (empty($state)) {
                            return '—';
                        }
                        $paths = is_array($state) ? $state : json_decode($state, true);
                        $first = $paths[0] ?? null;
                        if (!$first) {
                            return '—';
                        }
                        $url = media_url($first);
                        return '<img src="' . e($url) . '" class="h-10 w-10 rounded object-cover" loading="lazy">';
                    })
                    ->html(),
                TextColumn::make('created_at')
                    ->label('Ngày gửi')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                TrashedFilter::make(),
                SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options([
                        'pending' => 'Chờ duyệt',
                        'approved' => 'Đã duyệt',
                        'rejected' => 'Từ chối',
                    ]),
                SelectFilter::make('rating')
                    ->label('Số sao')
                    ->options([
                        5 => '5 Sao',
                        4 => '4 Sao',
                        3 => '3 Sao',
                        2 => '2 Sao',
                        1 => '1 Sao',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('approve')
                        ->label('Duyệt')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (Collection $records) {
                            $records->each(function ($record) {
                                $record->update([
                                    'status' => 'approved',
                                    'approved_at' => now(),
                                ]);

                                // Gửi mail cho khách khi review được duyệt
                                if (!empty($record->customer_email)) {
                                    try {
                                        app(MailDispatchService::class)->sendCustomerEvent(
                                            event:         'review_customer',
                                            customerEmail: $record->customer_email,
                                            vars: [
                                                'customer_name' => $record->customer_name,
                                                'product_name'  => $record->product?->name ?? '—',
                                            ],
                                            relatedType: 'ProductReview',
                                            relatedId:   $record->id
                                        );
                                    } catch (\Throwable $e) {
                                        Log::error('Review approved customer mail failed: ' . $e->getMessage());
                                    }
                                }
                            });
                            Notification::make()
                                ->title('Đã duyệt ' . $records->count() . ' đánh giá')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('reject')
                        ->label('Từ chối')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function (Collection $records) {
                            $records->each(function ($record) {
                                $record->update(['status' => 'rejected']);
                            });
                            Notification::make()
                                ->title('Đã từ chối ' . $records->count() . ' đánh giá')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
