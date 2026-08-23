<?php

namespace App\Filament\Pages;

use App\Services\Marketing\MarketingIntegrationHealthService;
use App\Services\Marketing\GoogleAdsOfflineConversionService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class MarketingIntegrations extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-globe-alt';

    protected static string|\UnitEnum|null $navigationGroup = 'SEO & Marketing';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.marketing-integrations';

    public array $health = [];

    public ?array $lastUploadResult = null;

    public static function getNavigationLabel(): string
    {
        return 'Tích hợp Marketing';
    }

    public function getTitle(): string
    {
        return 'Tích hợp Marketing';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('seo_audit.view') ?? false;
    }

    public function mount(): void
    {
        $this->health = app(MarketingIntegrationHealthService::class)->summary();
    }

    public function refreshHealth(): void
    {
        $this->health = app(MarketingIntegrationHealthService::class)->summary();
    }

    public function uploadOfflineConversions(): void
    {
        try {
            $this->lastUploadResult = app(GoogleAdsOfflineConversionService::class)->uploadPending(limit: 50);
            $this->refreshHealth();

            Notification::make()
                ->title('Google Ads offline conversions processed')
                ->body('Uploaded: '.($this->lastUploadResult['uploaded'] ?? 0).', failed: '.($this->lastUploadResult['failed'] ?? 0).', skipped: '.($this->lastUploadResult['skipped'] ?? 0))
                ->success()
                ->send();
        } catch (\Throwable $exception) {
            Notification::make()
                ->title('Google Ads upload failed')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Làm mới trạng thái')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action('refreshHealth'),
            Action::make('merchant_feed')
                ->label('Mở Merchant feed')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('info')
                ->url(route('merchant.feed'))
                ->openUrlInNewTab(),
            Action::make('upload_offline_conversions')
                ->label('Gửi chuyển đổi offline')
                ->icon('heroicon-o-cloud-arrow-up')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Gửi chuyển đổi offline lên Google Ads?')
                ->modalDescription('Chỉ các chuyển đổi đang chờ và đủ điều kiện mới được xử lý. Kết quả sẽ được ghi nhận theo từng bản ghi.')
                ->action('uploadOfflineConversions'),
        ];
    }
}
