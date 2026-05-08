<?php

namespace App\Filament\Resources\ProductQuestions\Tables;

use App\Services\Mail\MailDispatchService;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class ProductQuestionsTable
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
                TextColumn::make('question')
                    ->label('Câu hỏi')
                    ->limit(50)
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'answered' => 'info',
                        'pending' => 'warning',
                        'rejected' => 'danger',
                    }),
                IconColumn::make('is_public')
                    ->label('Công khai')
                    ->boolean()
                    ->sortable(),
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
                        'answered' => 'Đã trả lời',
                        'approved' => 'Đã duyệt',
                        'rejected' => 'Từ chối',
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
                                $record->update(['status' => 'approved']);

                                // Gửi mail cho khách nếu câu hỏi có answer và khách có email
                                if (!empty($record->customer_email) && !empty($record->answer)) {
                                    try {
                                        app(MailDispatchService::class)->sendCustomerEvent(
                                            event:         'question_customer',
                                            customerEmail: $record->customer_email,
                                            vars: [
                                                'customer_name' => $record->customer_name,
                                                'question'      => $record->question,
                                                'answer'        => $record->answer,
                                                'product_name'  => $record->product?->name ?? '—',
                                            ],
                                            relatedType: 'ProductQuestion',
                                            relatedId:   $record->id
                                        );
                                    } catch (\Throwable $e) {
                                        Log::error('Question answered customer mail failed: ' . $e->getMessage());
                                    }
                                }
                            });
                            Notification::make()->title('Đã duyệt ' . $records->count() . ' câu hỏi')->success()->send();
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
                            Notification::make()->title('Đã từ chối ' . $records->count() . ' câu hỏi')->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
