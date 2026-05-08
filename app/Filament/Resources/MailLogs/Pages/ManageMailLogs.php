<?php

namespace App\Filament\Resources\MailLogs\Pages;

use App\Filament\Resources\MailLogs\MailLogResource;
use App\Models\MailLog;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageMailLogs extends ManageRecords
{
    protected static string $resource = MailLogResource::class;

    protected function getHeaderActions(): array
    {
        $stats = MailLog::stats(7);

        return [
            // ── Stats summary chip ──────────────────────────────────
            Action::make('stats_info')
                ->label("7 ngày: {$stats['total']} tổng | {$stats['sent']} gửi | {$stats['failed']} lỗi | {$stats['skipped']} bỏ qua")
                ->icon('heroicon-o-chart-bar')
                ->color('gray')
                ->disabled(),

            // ── Purge old logs ───────────────────────────────────────
            Action::make('purge_old')
                ->label('Xóa log cũ (>30 ngày)')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription('Xóa tất cả mail log đã gửi hoặc bỏ qua cũ hơn 30 ngày. Log lỗi sẽ được giữ lại.')
                ->action(function () {
                    $deleted = MailLog::whereIn('status', ['sent', 'skipped'])
                        ->where('created_at', '<', now()->subDays(30))
                        ->delete();

                    Notification::make()
                        ->title("Đã xóa {$deleted} bản ghi cũ")
                        ->success()->send();
                }),
        ];
    }
}
