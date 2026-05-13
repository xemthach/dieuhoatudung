<?php

namespace App\Filament\Resources\AiProviders\Tables;

use App\Models\AiProvider;
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
                    ->searchable()
                    ->description(fn (AiProvider $record) => $record->api_key ? 'sk-...'.substr($record->api_key, -4) : 'No key'),
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
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('test')
                    ->label('Test')
                    ->icon('heroicon-o-bolt')
                    ->color('warning')
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
