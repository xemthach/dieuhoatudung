<?php

namespace App\Filament\Widgets;

use App\Models\MailLog;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MailStatsWidget extends BaseWidget
{
    protected static ?int $sort = 15;

    public static function canView(): bool
    {
        return auth()->user()?->can('mail_log.view') ?? false;
    }

    protected function getStats(): array
    {
        $stats7  = MailLog::stats(7);
        $stats30 = MailLog::stats(30);

        $rate = $stats7['total'] > 0
            ? round(($stats7['sent'] / $stats7['total']) * 100)
            : 0;

        return [
            Stat::make('Tổng mail (7 ngày)', $stats7['total'])
                ->description('30 ngày: ' . $stats30['total'])
                ->descriptionIcon('heroicon-m-envelope')
                ->color('info'),

            Stat::make('Đã gửi (7 ngày)', $stats7['sent'])
                ->description("Tỷ lệ thành công: {$rate}%")
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('Thất bại (7 ngày)', $stats7['failed'])
                ->description('30 ngày: ' . $stats30['failed'])
                ->descriptionIcon('heroicon-m-x-circle')
                ->color($stats7['failed'] > 0 ? 'danger' : 'success'),

            Stat::make('Bỏ qua / Tắt (7 ngày)', $stats7['skipped'])
                ->description('30 ngày: ' . $stats30['skipped'])
                ->descriptionIcon('heroicon-m-minus-circle')
                ->color($stats7['skipped'] > 0 ? 'warning' : 'success'),
        ];
    }
}
