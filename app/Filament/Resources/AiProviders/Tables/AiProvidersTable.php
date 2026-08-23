<?php

namespace App\Filament\Resources\AiProviders\Tables;

use App\Models\AiProvider;
use App\Services\AI\AiProviderReadinessService;
use App\Services\AI\Adapters\ClaudeAdapter;
use App\Services\AI\Adapters\GeminiAdapter;
use App\Services\AI\Adapters\OpenAIAdapter;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class AiProvidersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('provider')
                    ->label('Provider')
                    ->badge()
                    ->colors([
                        'primary' => 'gemini',
                        'success' => 'openai',
                        'warning' => 'claude',
                        'info' => 'groq',
                        'gray' => 'ollama',
                    ]),
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable(),
                TextColumn::make('model')
                    ->label('Model')
                    ->searchable(),
                TextColumn::make('credential_status')
                    ->label('Cấu hình')
                    ->state(fn (AiProvider $record): string => app(AiProviderReadinessService::class)->present($record)['configured'] ? 'Đã cấu hình' : 'Thiếu cấu hình')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Đã cấu hình' ? 'success' : 'warning'),
                TextColumn::make('connection_status')
                    ->label('Kết nối')
                    ->state(fn (AiProvider $record): string => app(AiProviderReadinessService::class)->present($record)['connection_label'])
                    ->badge()
                    ->color(fn (AiProvider $record): string => match (app(AiProviderReadinessService::class)->present($record)['connection']) {
                        'CONNECTED' => 'success',
                        'FAILED' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('priority')
                    ->label('Priority')
                    ->badge()
                    ->colors([
                        'success' => 'primary',
                        'warning' => 'fallback',
                    ]),
                TextColumn::make('weight')
                    ->label('Weight')
                    ->numeric()
                    ->sortable()
                    ->alignCenter(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->colors([
                        'success' => 'active',
                        'gray' => 'inactive',
                        'warning' => 'rate_limited',
                        'danger' => 'failed',
                    ]),
                TextColumn::make('request_count')
                    ->label('Reqs')
                    ->numeric()
                    ->sortable()
                    ->alignRight(),
                TextColumn::make('success_count')
                    ->label('Success')
                    ->numeric()
                    ->sortable()
                    ->alignRight()
                    ->color('success'),
                TextColumn::make('error_count')
                    ->label('Errors')
                    ->numeric()
                    ->sortable()
                    ->alignRight()
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'gray'),
                TextColumn::make('tokens_used')
                    ->label('Tokens')
                    ->numeric()
                    ->sortable()
                    ->alignRight(),
                TextColumn::make('last_used_at')
                    ->label('Last Used')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('quota_status')
                    ->label('Credit / Quota')
                    ->state(fn (AiProvider $record): string => app(AiProviderReadinessService::class)->present($record)['quota_label'])
                    ->toggleable(),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('test')
                    ->label('Kiểm tra kết nối')
                    ->icon('heroicon-o-bolt')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('Provider không có health endpoint riêng; thao tác này gửi một yêu cầu mô hình tối thiểu. Không được gọi tự động khi polling.')
                    ->action(function (AiProvider $record) {
                        try {
                            $adapter = match ($record->provider) {
                                'gemini' => new GeminiAdapter,
                                'claude' => new ClaudeAdapter,
                                default => new OpenAIAdapter,
                            };

                            $result = $adapter->testConnection($record);

                            if (! empty($result['success'])) {
                                $record->update([
                                    'status' => 'active',
                                    'error_count' => 0,
                                    'last_success_at' => now(),
                                    'rate_limited_until' => null,
                                ]);
                                Notification::make()
                                    ->title('Kết nối thành công')
                                    ->success()
                                    ->send();
                            } else {
                                $isRateLimited = ! empty($result['rate_limited']);
                                $record->update([
                                    'status' => $isRateLimited ? 'rate_limited' : 'failed',
                                    'last_error_at' => now(),
                                    'last_error_message' => $result['message'],
                                    'error_count' => $record->error_count + 1,
                                ]);
                                Notification::make()
                                    ->title('Kết nối thất bại')
                                    ->body($result['message'])
                                    ->danger()
                                    ->send();
                            }
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Lỗi hệ thống')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('priority', 'asc')
            ->persistSortInSession();
    }
}
