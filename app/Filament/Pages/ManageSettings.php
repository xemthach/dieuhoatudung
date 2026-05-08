<?php

namespace App\Filament\Pages;

use App\Services\Settings\SettingService;
use Filament\Schemas\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Artisan;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Http;

class ManageSettings extends Page
{
    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-cog-6-tooth';
    }

    public static function getNavigationLabel(): string
    {
        return 'Cấu hình hệ thống (cũ)';
    }

    public static function getNavigationGroup(): ?string
    {
        return null; // Ẩn khỏi navigation
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false; // Đã thay bằng ManageSiteSettings
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('settings.manage') ?? false;
    }

    protected string $view = 'filament.pages.manage-settings';
    
    public ?array $data = [];

    public function mount(SettingService $settings): void
    {
        // Load all settings
        $allSettings = \App\Models\SiteSetting::all();
        $formatted = [];
        foreach ($allSettings as $s) {
            $key = $s->group . '.' . $s->key;
            // Dùng service để decrypt nếu cần
            $formatted[$s->group . '_' . $s->key] = $settings->get($key);
        }
        $this->form->fill($formatted);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Tabs::make('Settings')
                    ->tabs([
                        Tabs\Tab::make('General')
                            ->schema([
                                TextInput::make('general_site_name')->label('Tên website'),
                                TextInput::make('general_site_slogan')->label('Slogan'),
                                TextInput::make('general_company_name')->label('Tên công ty'),
                                TextInput::make('general_company_address')->label('Địa chỉ'),
                                TextInput::make('general_company_phone')->label('Điện thoại'),
                                TextInput::make('general_company_email')->label('Email công ty'),
                            ]),
                        Tabs\Tab::make('Contact')
                            ->schema([
                                TextInput::make('contact_hotline')->label('Hotline'),
                                TextInput::make('contact_zalo_phone')->label('Số Zalo'),
                                TextInput::make('contact_email')->label('Email liên hệ'),
                                TextInput::make('contact_facebook_link')->label('Link Facebook'),
                            ]),
                        Tabs\Tab::make('SEO')
                            ->schema([
                                TextInput::make('seo_default_seo_title')->label('SEO Title mặc định'),
                                Textarea::make('seo_default_meta_description')->label('Meta Description mặc định'),
                                TextInput::make('seo_canonical_base_url')->label('Canonical Base URL (APP_URL)'),
                                Toggle::make('seo_enable_schema')->label('Bật Schema JSON-LD'),
                            ]),

                        Tabs\Tab::make('Cloudflare R2')
                            ->schema([
                                Toggle::make('r2_storage_r2_enabled')->label('Bật R2 Storage'),
                                TextInput::make('r2_storage_r2_access_key_id')->password()->revealable(),
                                TextInput::make('r2_storage_r2_secret_access_key')->password()->revealable(),
                                TextInput::make('r2_storage_r2_bucket'),
                                TextInput::make('r2_storage_r2_endpoint'),
                                TextInput::make('r2_storage_r2_public_url'),
                            ]),
                        Tabs\Tab::make('Sitemap')
                            ->schema([
                                Toggle::make('sitemap_sitemap_enabled')->label('Bật Sitemap'),
                                Toggle::make('sitemap_sitemap_include_products')->label('Include Products'),
                                Toggle::make('sitemap_sitemap_include_posts')->label('Include Posts'),
                            ]),
                        Tabs\Tab::make('Display')
                            ->schema([
                                TextInput::make('display_products_per_page')->numeric(),
                                TextInput::make('display_posts_per_page')->numeric(),
                            ]),
                    ])
                    ->columnSpanFull()
            ])
            ->statePath('data');
    }

    public function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Lưu cấu hình')
                ->action('saveSettings'),
                
            Action::make('clear_cache')
                ->label('Xóa Cache')
                ->color('warning')
                ->action(function (SettingService $settings) {
                    $settings->clearAllCache();
                    Notification::make()->title('Đã xóa cache settings')->success()->send();
                }),
                

        ];
    }

    public function saveSettings(SettingService $settings)
    {
        $state = $this->form->getState();
        
        foreach ($state as $key => $value) {
            $parts = explode('_', $key, 2);
            if (count($parts) === 2) {
                $group = $parts[0];
                $settingKey = $parts[1];
                
                // Mã hóa các trường nhạy cảm
                $isEncrypted = in_array($key, ['r2_storage_r2_access_key_id', 'r2_storage_r2_secret_access_key']);
                
                // Bỏ qua nếu là password field bị trống (không đổi)
                if ($isEncrypted && empty($value)) {
                    continue;
                }
                
                $type = is_bool($value) ? 'boolean' : 'text';
                
                $settings->set($settingKey, $value, $group, $isEncrypted, $type);
            }
        }
        
        Notification::make()->title('Cấu hình đã được lưu')->success()->send();
    }
}
