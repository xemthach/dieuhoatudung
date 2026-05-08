<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Filament\Resources\Posts\PostResource;
use App\Services\SEO\InternalLinkSuggestionService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditPost extends EditRecord
{
    protected static string $resource = PostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate_link_suggestions')
                ->label('Gợi ý Internal Links')
                ->icon('heroicon-o-link')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Tạo gợi ý liên kết nội bộ')
                ->modalDescription('Hệ thống sẽ phân tích bài viết này và gợi ý các trang liên quan. Các gợi ý cũ (pending) sẽ bị thay thế.')
                ->action(function () {
                    try {
                        $service = app(InternalLinkSuggestionService::class);
                        $suggestions = $service->generateForModel($this->record, force: true);

                        Notification::make()
                            ->title("Đã tạo {$suggestions->count()} gợi ý internal link.")
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Lỗi khi tạo gợi ý: ' . $e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
