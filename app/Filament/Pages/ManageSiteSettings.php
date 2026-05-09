<?php

namespace App\Filament\Pages;

use App\Models\SiteSetting;
use App\Services\Settings\SettingService;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Http;

class ManageSiteSettings extends Page
{
    use InteractsWithSchemas;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('settings.view') ?? false;
    }

    public static function getNavigationIcon(): ?string { return 'heroicon-o-cog-6-tooth'; }
    public static function getNavigationLabel(): string { return 'Site Settings'; }
    public static function getNavigationGroup(): ?string { return 'System'; }
    protected static ?string $slug = 'manage-settings';
    protected string $view = 'filament.pages.manage-site-settings';

    public ?array $data = [];

    /** Keys ÃâÃÂ°Ã¡Â»Â£c mÃÂ£ hÃÂ³a Ã¢â¬â nÃ¡ÂºÂ¿u trÃ¡Â»âng khi save thÃÂ¬ giÃ¡Â»Â¯ nguyÃÂªn giÃÂ¡ trÃ¡Â»â¹ cÃÂ© */
    protected const ENCRYPTED_KEYS = [
        'r2_storage__r2_access_key_id',
        'r2_storage__r2_secret_access_key',
        'mail__mail_password',
        'mail__brevo_api_key',
        'mail__testmail_api_key',
        'mail__mailgun_api_key',
        'mail__sendgrid_api_key',
    ];

    /** FileUpload fields — must be stored as arrays for Livewire synthesizer compatibility */
    protected const FILE_UPLOAD_KEYS = [
        'branding__logo_image',
        'branding__logo_dark_image',
        'branding__logo_mobile_image',
        'branding__favicon',
        'branding__apple_touch_icon',
        'product_detail__default_product_image',
    ];

    public function mount(): void
    {
        $allSettings = SiteSetting::all();
        $formatted = [];

        foreach ($allSettings as $s) {
            $formKey = $s->group . '__' . $s->key;

            // Encrypted fields: show empty to avoid revealing ciphertext
            if ($s->is_encrypted) {
                $formatted[$formKey] = '';
                continue;
            }

            // Boolean type: cast '0'/'1' string to actual PHP bool
            // so that Filament Toggle displays the correct ON/OFF state.
            // Without this, string '0' is treated as truthy by Toggle.
            if ($s->type === 'boolean') {
                $formatted[$formKey] = filter_var($s->value, FILTER_VALIDATE_BOOLEAN);
                continue;
            }

            $formatted[$formKey] = $s->value;
        }

        // FileUpload fields require array state for Livewire's deep-set path
        // (e.g. data.branding__logo_image.{uuid}). A plain string causes
        // "No synthesizer found for key" because Livewire treats the string
        // as a synthesizer key during recursive property hydration.
        foreach (self::FILE_UPLOAD_KEYS as $fileKey) {
            $current = $formatted[$fileKey] ?? null;

            // Sanitize corrupted values: '{}', '[]', empty arrays/objects
            // that were saved when the save method bypassed schema dehydration
            if (in_array($current, ['{}', '[]', null, ''], true)) {
                $formatted[$fileKey] = [];
                continue;
            }

            // Valid file path string → wrap in array for Filament
            $formatted[$fileKey] = is_string($current) ? [$current] : [];
        }

        $this->data = $formatted;
    }


    public function settingsSchema(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Tabs::make('Settings')->tabs([

                    /* ── Tab 0: Branding / Logo ── */
                    Tabs\Tab::make('Branding')->icon('heroicon-o-paint-brush')->schema([
                        \Filament\Schemas\Components\Section::make('Site Identity')
                            ->description('Tên, tên viết tắt, slogan — nguồn cấu hình duy nhất cho toàn site.')
                            ->schema([
                                TextInput::make('general__site_name')->label('Tên website')
                                    ->helperText('Hiển thị header, title, schema, email, OG'),
                                TextInput::make('general__site_short_name')->label('Tên viết tắt')
                                    ->helperText('Dùng cho mobile, PWA manifest'),
                                TextInput::make('general__site_slogan')->label('Slogan')
                                    ->helperText('Hiển thị footer, meta description fallback'),
                                TextInput::make('general__company_name')->label('Tên công ty')
                                    ->helperText('Schema Organization, footer copyright'),
                            ])->columns(2),

                        \Filament\Schemas\Components\Section::make('Logo')
                            ->description('Upload logo và chọn cách hiển thị.')
                            ->schema([
                                Select::make('branding__logo_display_mode')->label('Chế độ hiển thị logo')
                                    ->options([
                                        'logo_text' => 'Logo + Text',
                                        'logo_only' => 'Chỉ Logo',
                                        'text_only' => 'Chỉ Text',
                                    ])
                                    ->default('logo_text')
                                    ->helperText('Áp dụng toàn site. Header/Footer có thể override riêng.'),
                                FileUpload::make('branding__logo_image')->label('Logo chính')
                                    ->disk(fn () => app(\App\Services\Media\MediaDiskService::class)->getUploadDisk())
                                    ->visibility('public')
                                    ->directory('branding')
                                    ->image()
                                    ->maxSize(2048)
                                    ->acceptedFileTypes(['image/png', 'image/svg+xml', 'image/webp']),
                                FileUpload::make('branding__logo_dark_image')->label('Logo dark/footer')
                                    ->disk(fn () => app(\App\Services\Media\MediaDiskService::class)->getUploadDisk())
                                    ->visibility('public')
                                    ->directory('branding')
                                    ->image()
                                    ->maxSize(2048)
                                    ->helperText('Dùng trên nền tối (footer, dark mode)'),
                                FileUpload::make('branding__logo_mobile_image')->label('Logo mobile')
                                    ->disk(fn () => app(\App\Services\Media\MediaDiskService::class)->getUploadDisk())
                                    ->visibility('public')
                                    ->directory('branding')
                                    ->image()
                                    ->maxSize(1024)
                                    ->helperText('Tùy chọn — nếu trống dùng logo chính'),
                                TextInput::make('branding__logo_alt_text')->label('Alt text logo')
                                    ->helperText('Nếu trống dùng tên website'),
                                TextInput::make('branding__logo_text')->label('Text logo (tùy chỉnh)')
                                    ->helperText('Nếu trống dùng tên website'),
                            ])->columns(2),

                        \Filament\Schemas\Components\Section::make('Favicon & Icons')
                            ->schema([
                                FileUpload::make('branding__favicon')->label('Favicon')
                                    ->disk(fn () => app(\App\Services\Media\MediaDiskService::class)->getUploadDisk())
                                    ->visibility('public')
                                    ->directory('branding')
                                    ->image()
                                    ->maxSize(512)
                                    ->helperText('PNG/ICO, 32x32 hoặc 48x48'),
                                FileUpload::make('branding__apple_touch_icon')->label('Apple Touch Icon')
                                    ->disk(fn () => app(\App\Services\Media\MediaDiskService::class)->getUploadDisk())
                                    ->visibility('public')
                                    ->directory('branding')
                                    ->image()
                                    ->maxSize(512)
                                    ->helperText('PNG, 180x180'),
                            ])->columns(2),

                        \Filament\Schemas\Components\Section::make('Kích thước & Màu sắc')
                            ->schema([
                                \Filament\Schemas\Components\Grid::make(['default' => 1, 'md' => 3])->schema([
                                    TextInput::make('branding__logo_width_desktop')->label('Chiều rộng desktop (px)')->numeric()->default(160),
                                    TextInput::make('branding__logo_width_mobile')->label('Chiều rộng mobile (px)')->numeric()->default(120),
                                    TextInput::make('branding__logo_height_max')->label('Chiều cao tối đa (px)')->numeric()->default(48),
                                ]),
                                \Filament\Schemas\Components\Grid::make(['default' => 1, 'md' => 3])->schema([
                                    TextInput::make('branding__brand_primary_color')->label('Primary Color')->placeholder('#1e40af'),
                                    TextInput::make('branding__brand_secondary_color')->label('Secondary Color')->placeholder('#0f766e'),
                                    TextInput::make('branding__brand_accent_color')->label('Accent Color')->placeholder('#f59e0b'),
                                ]),
                                TextInput::make('branding__logo_text_color')->label('Màu text logo')->placeholder('#1e293b'),
                            ])->collapsible()->collapsed(),

                        \Filament\Schemas\Components\Section::make('Header / Footer Override')
                            ->description('Override chế độ hiển thị logo cho từng khu vực.')
                            ->schema([
                                Select::make('branding__header_logo_mode')->label('Header logo mode')
                                    ->options([
                                        'auto' => 'Auto (theo global)',
                                        'logo_text' => 'Logo + Text',
                                        'logo_only' => 'Chỉ Logo',
                                        'text_only' => 'Chỉ Text',
                                    ])->default('auto'),
                                Select::make('branding__footer_logo_mode')->label('Footer logo mode')
                                    ->options([
                                        'auto' => 'Auto (theo global)',
                                        'logo_text' => 'Logo + Text',
                                        'logo_only' => 'Chỉ Logo',
                                        'text_only' => 'Chỉ Text',
                                    ])->default('auto'),
                                Toggle::make('branding__footer_show_slogan')->label('Hiện slogan ở footer')->default(true),
                                Toggle::make('branding__footer_show_company_name')->label('Hiện tên công ty ở footer')->default(true),
                                Toggle::make('branding__footer_show_contact')->label('Hiện thông tin liên hệ ở footer')->default(true),
                            ])->columns(2)->collapsible()->collapsed(),
                    ]),

                    /* ── Tab 1: General ── */
                    Tabs\Tab::make('General')->icon('heroicon-o-building-office')->schema([
                        Textarea::make('general__company_address')->label('Địa chỉ công ty')->rows(2),
                        TextInput::make('general__company_tax_code')->label('Mã số thuế'),
                        TextInput::make('general__working_hours')->label('Giờ làm việc')
                            ->helperText('Hiển thị topbar header'),
                        \Filament\Schemas\Components\Section::make()
                            ->description('Tên website, slogan, logo → cấu hình tại tab Branding. Hotline, email, social links → tab Contact.')
                            ->schema([]),
                    ]),

                    /* ── Tab 2: Contact ── */
                    Tabs\Tab::make('Contact')->icon('heroicon-o-phone')->schema([
                        TextInput::make('contact__hotline')->label('Hotline'),
                        TextInput::make('contact__zalo_phone')->label('Số Zalo'),
                        TextInput::make('contact__zalo_link')->label('Link Zalo')->url(),
                        TextInput::make('contact__email')->label('Email liên hệ')->email(),
                        Textarea::make('contact__contact_address')->label('Địa chỉ liên hệ')->rows(2),
                        Textarea::make('contact__google_map_embed')->label('Google Map Embed HTML')->rows(3),
                        TextInput::make('contact__facebook_link')->label('Facebook')->url(),
                        TextInput::make('contact__youtube_link')->label('YouTube')->url(),
                        TextInput::make('contact__tiktok_link')->label('TikTok')->url(),
                    ]),

                    /* ── Tab 3: SEO ── */
                    Tabs\Tab::make('SEO')->icon('heroicon-o-magnifying-glass')->schema([
                        TextInput::make('seo__default_seo_title')->label('SEO Title mặc định'),
                        Textarea::make('seo__default_meta_description')->label('Meta Description mặc định')->rows(3),
                        Select::make('seo__default_robots')->label('Robots mặc định')->options([
                            'index,follow' => 'index,follow',
                            'noindex,follow' => 'noindex,follow',
                            'index,nofollow' => 'index,nofollow',
                            'noindex,nofollow'=> 'noindex,nofollow',
                        ]),
                        TextInput::make('seo__canonical_base_url')->label('Canonical Base URL'),
                        Toggle::make('seo__enable_schema')->label('Bật Schema JSON-LD'),
                        Toggle::make('seo__enable_breadcrumb_schema')->label('Bật Breadcrumb Schema'),
                        Toggle::make('seo__enable_faq_schema')->label('Bật FAQ Schema'),
                        Toggle::make('seo__enable_product_schema')->label('Bật Product Schema'),
                        Toggle::make('seo__enable_article_schema')->label('Bật Article Schema'),
                    ]),

                    /* ── Tab 4: Schema Organization ── */
                    Tabs\Tab::make('Schema Org')->icon('heroicon-o-rectangle-group')->schema([
                        TextInput::make('schema_organization__organization_name')->label('Tên tổ chức'),
                        TextInput::make('schema_organization__organization_url')->label('Website URL'),
                        TextInput::make('schema_organization__organization_phone')->label('Điện thoại'),
                        TextInput::make('schema_organization__organization_email')->label('Email')->email(),
                        Textarea::make('schema_organization__organization_address')->label('Địa chỉ')->rows(2),
                        Textarea::make('schema_organization__organization_same_as')->label('Social URLs (mỗi dòng 1 URL)')->rows(4)
                            ->helperText('Mỗi dòng một URL mạng xã hội'),
                    ]),

                    /* ── Tab 5: AI Content ── */
                    Tabs\Tab::make('AI Content')->icon('heroicon-o-cpu-chip')->schema([
                        \Filament\Schemas\Components\Section::make('Cấu hình AI Provider')
                            ->description('API Key, Model, Provider được quản lý tập trung tại trang AI Providers.')
                            ->schema([
                                \Filament\Schemas\Components\Actions::make([
                                    \Filament\Actions\Action::make('go_to_ai_providers')
                                        ->label('Quản lý AI Providers')
                                        ->color('primary')
                                        ->url('/admin/ai-providers'),
                                ]),
                            ]),
                        \Filament\Schemas\Components\Section::make('Tính năng AI cho Blog')
                            ->schema([
                                Select::make('ai__ai_default_language')->label('Ngôn ngữ mặc định')->options([
                                    'vi' => 'Tiếng Việt', 'en' => 'English',
                                ]),
                                Toggle::make('ai__ai_auto_tag_enabled')->label('Tự động gợi ý Tag'),
                                Toggle::make('ai__ai_auto_internal_link_enabled')->label('Tự động Internal Link'),
                                Toggle::make('ai__ai_auto_publish_enabled')
                                    ->label('Auto Publish (Nên TẮT)')
                                    ->helperText('NGUY HIỂM: Tự động publish bài AI chưa qua duyệt'),
                            ]),
                    ]),

                    /* ── Tab 6: Cloudflare R2 ── */
                    Tabs\Tab::make('R2 Storage')->icon('heroicon-o-cloud')->schema([
                        \Filament\Schemas\Components\Section::make('Trạng thái R2')
                            ->description('Bật/tắt Cloudflare R2 Storage cho toàn hệ thống. Khi TẮT, hệ thống dùng local disk.')
                            ->schema([
                                Toggle::make('r2_storage__r2_enabled')
                                    ->label('Bật Cloudflare R2 Storage')
                                    ->helperText('Khi BẬT: file upload sẽ lên R2, media_url() ưu tiên CDN. Khi TẮT: mọi thứ dùng local storage.')
                                    ->live(),
                            ]),

                        \Filament\Schemas\Components\Section::make('Thông tin kết nối R2')
                            ->description('Lấy từ Cloudflare Dashboard > R2 > API Tokens. Access Key và Secret sẽ được mã hóa khi lưu.')
                            ->schema([
                                TextInput::make('r2_storage__r2_access_key_id')
                                    ->label('R2 Access Key ID')->password()->revealable()
                                    ->placeholder('Để trống = giữ nguyên giá trị đã lưu'),
                                TextInput::make('r2_storage__r2_secret_access_key')
                                    ->label('R2 Secret Access Key')->password()->revealable()
                                    ->placeholder('Để trống = giữ nguyên giá trị đã lưu'),
                                TextInput::make('r2_storage__r2_bucket')
                                    ->label('Bucket Name')
                                    ->helperText('Tên bucket trên Cloudflare R2'),
                                TextInput::make('r2_storage__r2_endpoint')
                                    ->label('Endpoint URL')
                                    ->helperText('Dạng: https://<account_id>.r2.cloudflarestorage.com'),
                                TextInput::make('r2_storage__r2_public_url')
                                    ->label('Public URL (CDN)')
                                    ->helperText('URL công khai để truy cập file. Vd: https://cloud-data.yourdomain.com'),
                                TextInput::make('r2_storage__r2_default_folder')
                                    ->label('Default Folder')
                                    ->helperText('Thư mục gốc trên R2. Để trống nếu lưu ở root bucket.'),
                            ])->columns(2),

                        \Filament\Schemas\Components\Section::make('Cấu hình đồng bộ (Sync)')
                            ->description('Điều khiển quá trình upload file local lên R2 và thay thế URL trong database.')
                            ->icon('heroicon-o-arrow-path')
                            ->schema([
                                Toggle::make('r2_storage__r2_sync_enabled')
                                    ->label('Bật tính năng Sync')
                                    ->helperText('Cho phép chạy Sync Upload từ R2 Sync Manager.'),
                                Toggle::make('r2_storage__r2_sync_replace_urls_enabled')
                                    ->label('Bật tính năng thay thế URL')
                                    ->helperText('Cho phép Replace URLs từ R2 Sync Manager. Chỉ thay thế file đã xác nhận sync.'),
                                Toggle::make('r2_storage__r2_sync_delete_local_after_upload')
                                    ->label('Xóa local file sau khi upload')
                                    ->helperText('⚠️ NGUY HIỂM: File trên server sẽ bị xóa vĩnh viễn sau khi upload lên R2. Chỉ bật khi đã xác nhận R2 hoạt động ổn định.')
                                    ->default(false),
                                TextInput::make('r2_storage__r2_sync_batch_size')
                                    ->label('Batch Size')
                                    ->numeric()
                                    ->default(50)
                                    ->helperText('Số file xử lý mỗi batch. Giảm nếu server yếu.'),
                            ])->columns(2),

                        \Filament\Schemas\Components\Section::make('URL Replace Config')
                            ->description('Cấu hình cho chức năng thay thế URL cũ thành CDN URL mới trong database.')
                            ->icon('heroicon-o-link')
                            ->schema([
                                Textarea::make('r2_storage__r2_old_base_urls')
                                    ->label('Các URL cũ cần thay thế (Old Base URLs)')
                                    ->helperText('Mỗi dòng 1 URL. Vd: http://localhost/storage hoặc https://old-domain.com/storage')
                                    ->rows(3),
                                TextInput::make('r2_storage__r2_new_cdn_base_url')
                                    ->label('New CDN Base URL (override)')
                                    ->helperText('Nếu trống, hệ thống tự dùng Public URL ở trên.'),
                            ]),

                        \Filament\Schemas\Components\Actions::make([
                            \Filament\Actions\Action::make('open_sync_manager')
                                ->label('Mở R2 Sync Manager')
                                ->icon('heroicon-o-arrow-top-right-on-square')
                                ->color('primary')
                                ->url('/admin/r2-sync-manager'),
                        ]),
                    ]),

                    /* ── Tab 7: Lead Form ── */
                    Tabs\Tab::make('Lead Form')->icon('heroicon-o-clipboard-document-list')->schema([
                        TextInput::make('lead__lead_notify_email')->label('Email nhận thông báo')->email(),
                        Textarea::make('lead__lead_success_message')->label('Thông báo thành công')->rows(2),
                        Toggle::make('lead__lead_required_phone')->label('Bắt buộc nhập SĐT'),
                        Toggle::make('lead__lead_required_email')->label('Bắt buộc nhập Email'),
                        Select::make('lead__lead_default_status')->label('Trạng thái mặc định')->options([
                            'new' => 'Mới',
                            'contacted' => 'Đã liên hệ',
                            'qualified' => 'Tiềm năng',
                            'rejected' => 'Từ chối',
                        ]),
                        Toggle::make('lead__lead_spam_protection_enabled')->label('Bật chống spam'),
                    ]),

                    /* ── Tab 8: Sitemap ── */
                    Tabs\Tab::make('Sitemap')->icon('heroicon-o-map')->schema([
                        Toggle::make('sitemap__sitemap_enabled')->label('Bật Sitemap'),
                        Toggle::make('sitemap__sitemap_include_products')->label('Include Sản phẩm'),
                        Toggle::make('sitemap__sitemap_include_posts')->label('Include Bài viết'),
                        Toggle::make('sitemap__sitemap_include_categories')->label('Include Danh mục'),
                        Toggle::make('sitemap__sitemap_include_tags')->label('Include Tags'),
                        Toggle::make('sitemap__sitemap_include_case_studies')->label('Include Case Studies'),
                        Toggle::make('sitemap__sitemap_exclude_noindex')->label('Loại trừ noindex'),
                        TextInput::make('sitemap__sitemap_cache_minutes')->label('Cache (phút)')->numeric(),
                    ]),

                    /* ── Tab 9: Robots ── */
                    Tabs\Tab::make('Robots')->icon('heroicon-o-document-text')->schema([
                        Textarea::make('robots__robots_content')
                            ->label('Nội dung robots.txt (ghi đè tự động)')
                            ->rows(8)
                            ->placeholder("User-agent: *\nAllow: /\nDisallow: /admin\n\nSitemap: https://example.com/sitemap.xml"),
                        Toggle::make('robots__robots_disallow_admin')->label('Disallow /admin'),
                        Toggle::make('robots__robots_disallow_search')->label('Disallow /search'),
                        Toggle::make('robots__robots_disallow_filter_urls')->label('Disallow Filter URLs'),
                    ]),

                    /* ── Tab 10: Tracking ── */
                    Tabs\Tab::make('Tracking')->icon('heroicon-o-chart-bar')->schema([
                        TextInput::make('tracking__google_analytics_id')->label('Google Analytics ID'),
                        TextInput::make('tracking__google_tag_manager_id')->label('Google Tag Manager ID'),
                        TextInput::make('tracking__facebook_pixel_id')->label('Facebook Pixel ID'),
                        Textarea::make('tracking__custom_head_scripts')->label('Custom <head> Scripts')->rows(4),
                        Textarea::make('tracking__custom_body_scripts')->label('Custom <body> Scripts')->rows(4),
                        Textarea::make('tracking__custom_footer_scripts')->label('Custom Footer Scripts')->rows(4),
                    ]),

                    /* ── Tab 11: Display ── */
                    Tabs\Tab::make('Display')->icon('heroicon-o-computer-desktop')->schema([
                        TextInput::make('display__products_per_page')->label('Sản phẩm/trang')->numeric(),
                        TextInput::make('display__posts_per_page')->label('Bài viết/trang')->numeric(),
                        TextInput::make('display__featured_products_limit')->label('Sản phẩm nổi bật')->numeric(),
                        TextInput::make('display__related_products_limit')->label('Sản phẩm liên quan')->numeric(),
                        TextInput::make('display__related_posts_limit')->label('Bài viết liên quan')->numeric(),
                        TextInput::make('display__homepage_featured_limit')->label('Nổi bật trang chủ')->numeric(),
                        TextInput::make('display__landing_featured_products_limit')->label('Sản phẩm landing page')->numeric(),
                        \Filament\Schemas\Components\Section::make('Ảnh mặc định sản phẩm')
                            ->description('Hiển thị khi sản phẩm chưa có ảnh. Ưu tiên: ảnh này → ảnh public/images/placeholders/product-default.jpg')
                            ->schema([
                                FileUpload::make('product_detail__default_product_image')
                                    ->label('Ảnh mặc định sản phẩm')
                                    ->disk(fn () => app(\App\Services\Media\MediaDiskService::class)->getUploadDisk())
                                    ->directory('placeholders')
                                    ->image()
                                    ->maxSize(fn () => app(\App\Services\Settings\UploadSettingService::class)->imageMaxSizeKb())
                                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                                    ->helperText(fn () => 'Tối đa ' . app(\App\Services\Settings\UploadSettingService::class)->formatMb(app(\App\Services\Settings\UploadSettingService::class)->imageMaxSizeKb()) . '. Định dạng JPG, PNG, WebP.'),
                            ]),

                        \Filament\Schemas\Components\Section::make('Giới hạn Upload File / Hình ảnh')
                            ->icon('heroicon-o-arrow-up-tray')
                            ->description('Quản lý tập trung dung lượng và loại file upload cho toàn bộ hệ thống. Đơn vị: KB (1 MB = 1024 KB).')
                            ->schema([
                                \Filament\Schemas\Components\Grid::make(['default' => 1, 'md' => 3])->schema([
                                    TextInput::make('upload__image_max_size_kb')
                                        ->label('Ảnh chung (KB)')
                                        ->numeric()
                                        ->suffix('KB')
                                        ->helperText('Mặc định: 5120 (5 MB)'),
                                    TextInput::make('upload__avatar_max_size_kb')
                                        ->label('Avatar user (KB)')
                                        ->numeric()
                                        ->suffix('KB')
                                        ->helperText('Mặc định: 2048 (2 MB)'),
                                    TextInput::make('upload__product_image_max_size_kb')
                                        ->label('Ảnh sản phẩm (KB)')
                                        ->numeric()
                                        ->suffix('KB')
                                        ->helperText('Mặc định: 5120 (5 MB)'),
                                ]),
                                \Filament\Schemas\Components\Grid::make(['default' => 1, 'md' => 3])->schema([
                                    TextInput::make('upload__review_image_max_size_kb')
                                        ->label('Ảnh đánh giá (KB)')
                                        ->numeric()
                                        ->suffix('KB')
                                        ->helperText('Mặc định: 3072 (3 MB)'),
                                    TextInput::make('upload__brand_logo_max_size_kb')
                                        ->label('Logo thương hiệu (KB)')
                                        ->numeric()
                                        ->suffix('KB')
                                        ->helperText('Mặc định: 2048 (2 MB)'),
                                    TextInput::make('upload__document_max_size_kb')
                                        ->label('Tài liệu (KB)')
                                        ->numeric()
                                        ->suffix('KB')
                                        ->helperText('Mặc định: 10240 (10 MB)'),
                                ]),
                                \Filament\Schemas\Components\Grid::make(['default' => 1, 'md' => 3])->schema([
                                    TextInput::make('upload__file_max_size_kb')
                                        ->label('File chung (KB)')
                                        ->numeric()
                                        ->suffix('KB')
                                        ->helperText('Mặc định: 10240 (10 MB)'),
                                    TextInput::make('upload__max_images_per_upload')
                                        ->label('Số ảnh tối đa / lần upload')
                                        ->numeric()
                                        ->helperText('Mặc định: 10'),
                                ]),
                                Textarea::make('upload__allowed_image_types')
                                    ->label('MIME types ảnh cho phép')
                                    ->helperText('Phân cách bởi dấu phẩy. Ví dụ: image/jpeg,image/png,image/webp,image/gif')
                                    ->rows(2),
                                Textarea::make('upload__allowed_file_types')
                                    ->label('MIME types file cho phép')
                                    ->helperText('Phân cách bởi dấu phẩy. Ví dụ: application/pdf,application/msword')
                                    ->rows(2),
                            ])
                            ->collapsible()
                            ->collapsed(false),
                    ]),

                    /* ── Tab 12: Product Detail ── */
                    Tabs\Tab::make('Product Detail')->icon('heroicon-o-shopping-bag')->schema([
                        \Filament\Schemas\Components\Section::make('Mô tả rút gọn (Collapsible Description)')
                            ->description('Cấu hình hiển thị mô tả sản phẩm kiểu Thegioididong')
                            ->schema([
                                Toggle::make('product_detail__enable_collapsible_description')
                                    ->label('Bật tính năng rút gọn mô tả')
                                    ->default(true),
                                TextInput::make('product_detail__description_collapsed_height')
                                    ->label('Chiều cao rút gọn (px)')
                                    ->numeric()
                                    ->default(420)
                                    ->helperText('Chiều cao tối đa khi rút gọn (đơn vị: pixel)'),
                                Toggle::make('product_detail__show_read_more_button')
                                    ->label('Hiện nút Xem thêm')
                                    ->default(true),
                            ]),
                        \Filament\Schemas\Components\Section::make('Đánh giá sản phẩm (Reviews)')
                            ->description('Cấu hình module đánh giá / review sản phẩm')
                            ->schema([
                                Toggle::make('product_detail__enable_reviews')
                                    ->label('Bật tính năng đánh giá')
                                    ->default(true),
                                Toggle::make('product_detail__review_auto_approve')
                                    ->label('Tự động duyệt đánh giá')
                                    ->helperText('Nếu bật, đánh giá sẽ hiển thị ngay khi gửi. Không khuyến nghị.')
                                    ->default(false),
                                Toggle::make('product_detail__review_require_phone')
                                    ->label('Bắt buộc nhập SĐT khi đánh giá')
                                    ->default(false),
                                Toggle::make('product_detail__review_allow_images')
                                    ->label('Cho phép gửi ảnh khi đánh giá')
                                    ->default(true),
                                TextInput::make('product_detail__review_max_images')
                                    ->label('Số ảnh tối đa / đánh giá')
                                    ->numeric()
                                    ->default(3),
                                TextInput::make('product_detail__review_max_image_size_mb')
                                    ->label('Dung lượng ảnh tối đa (MB)')
                                    ->numeric()
                                    ->default(3)
                                    ->helperText('Giới hạn dung lượng mỗi ảnh (MB)'),
                                TextInput::make('product_detail__review_allowed_image_types')
                                    ->label('Định dạng ảnh cho phép')
                                    ->default('jpg,jpeg,png,webp')
                                    ->helperText('Phân cách bằng dấu phẩy, ví dụ: jpg,jpeg,png,webp'),
                                Toggle::make('product_detail__review_show_verified_badge')
                                    ->label('Hiện badge "Đã mua hàng"')
                                    ->default(true),
                            ]),
                        \Filament\Schemas\Components\Section::make('Hỏi đáp sản phẩm (Q&A)')
                            ->description('Cấu hình module hỏi đáp sản phẩm')
                            ->schema([
                                Toggle::make('product_detail__enable_questions')
                                    ->label('Bật tính năng hỏi đáp')
                                    ->default(true),
                                Toggle::make('product_detail__question_auto_approve')
                                    ->label('Tự động duyệt câu hỏi')
                                    ->default(false),
                                Toggle::make('product_detail__question_require_phone')
                                    ->label('Bắt buộc nhập SĐT khi hỏi')
                                    ->default(false),
                                Toggle::make('product_detail__question_show_only_answered')
                                    ->label('Chỉ hiện câu hỏi đã trả lời')
                                    ->default(false),
                            ]),
                    ]),

                    /* ── Tab 13: CTA ── */
                    Tabs\Tab::make('CTA')->icon('heroicon-o-cursor-arrow-rays')->schema([
                        TextInput::make('cta__global_cta_text')->label('CTA Text toàn site'),
                        TextInput::make('cta__global_cta_link')->label('CTA Link'),
                        TextInput::make('cta__quote_cta_text')->label('Quote CTA Text'),
                        TextInput::make('cta__quote_cta_link')->label('Quote CTA Link'),
                        TextInput::make('cta__phone_cta_text')->label('Phone CTA Text'),
                        TextInput::make('cta__zalo_cta_text')->label('Zalo CTA Text'),
                    ]),


                    /* ── Tab 7: Mail Server ── */
                    Tabs\Tab::make('Mail Server')->icon('heroicon-o-envelope')->schema([

                        \Filament\Schemas\Components\Section::make('Cấu hình chung')
                            ->schema([
                                Toggle::make('mail__mail_enabled')
                                    ->label('Bật/tắt gửi mail')
                                    ->helperText('Tắt = toàn bộ mail bị block, mọi send đều bị log là skipped.'),
                                Select::make('mail__mail_provider')
                                    ->label('Provider hiện tại')
                                    ->options([
                                        'smtp' => 'SMTP',
                                        'brevo' => 'Brevo (Sendinblue) API',
                                        'mailgun' => 'Mailgun API',
                                        'sendgrid' => 'SendGrid API',
                                        'testmail' => 'Testmail.app (preview only)',
                                    ])
                                    ->required()
                                    ->live(),
                                TextInput::make('mail__mail_from_name')->label('From Name'),
                                TextInput::make('mail__mail_from_address')->label('From Address')->email(),
                                TextInput::make('mail__mail_test_recipient')
                                    ->label('Email nhận test')
                                    ->email()
                                    ->helperText('Dùng nút "Gửi test" trong Mail Templates.'),
                            ]),

                        \Filament\Schemas\Components\Section::make('SMTP')
                            ->schema([
                                TextInput::make('mail__mail_host')->label('Host'),
                                TextInput::make('mail__mail_port')->label('Port')->numeric(),
                                Select::make('mail__mail_encryption')->label('Encryption')
                                    ->options(['tls' => 'TLS', 'ssl' => 'SSL', '' => 'None']),
                                TextInput::make('mail__mail_username')->label('Username'),
                                TextInput::make('mail__mail_password')->label('Password')->password()->revealable()
                                    ->placeholder('Để trống = giữ nguyên'),
                            ])->visible(fn ($get) => $get('mail__mail_provider') === 'smtp'),

                        \Filament\Schemas\Components\Section::make('Brevo (Sendinblue) API')
                            ->schema([
                                TextInput::make('mail__brevo_api_key')->label('API Key')->password()->revealable()
                                    ->placeholder('Để trống = giữ nguyên'),
                                TextInput::make('mail__brevo_sender_name')->label('Sender Name'),
                                TextInput::make('mail__brevo_sender_email')->label('Sender Email')->email(),
                            ])->visible(fn ($get) => $get('mail__mail_provider') === 'brevo'),

                        \Filament\Schemas\Components\Section::make('Testmail.app')
                            ->schema([
                                TextInput::make('mail__testmail_api_key')->label('API Key')->password()->revealable()
                                    ->placeholder('Để trống = giữ nguyên'),
                                TextInput::make('mail__testmail_namespace')->label('Namespace'),
                                TextInput::make('mail__testmail_tag')->label('Tag'),
                            ])->visible(fn ($get) => $get('mail__mail_provider') === 'testmail'),

                        \Filament\Schemas\Components\Section::make('Mailgun API')
                            ->schema([
                                TextInput::make('mail__mailgun_api_key')->label('API Key')->password()->revealable()
                                    ->placeholder('Để trống = giữ nguyên'),
                                TextInput::make('mail__mailgun_domain')->label('Domain'),
                                TextInput::make('mail__mailgun_endpoint')->label('Endpoint')->default('api.mailgun.net'),
                                TextInput::make('mail__mailgun_from_address')->label('From Address (Override)')->email(),
                            ])->visible(fn ($get) => $get('mail__mail_provider') === 'mailgun'),

                        \Filament\Schemas\Components\Section::make('SendGrid API')
                            ->schema([
                                TextInput::make('mail__sendgrid_api_key')->label('API Key')->password()->revealable()
                                    ->placeholder('Để trống = giữ nguyên'),
                                TextInput::make('mail__sendgrid_from_name')->label('From Name (Override)'),
                                TextInput::make('mail__sendgrid_from_address')->label('From Address (Override)')->email(),
                            ])->visible(fn ($get) => $get('mail__mail_provider') === 'sendgrid'),

                        \Filament\Schemas\Components\Section::make('Thông báo Email (Mail Notify)')
                            ->description('Bật/tắt từng loại email thông báo. Email override để trống thì dùng "Email nhận lead" làm fallback.')
                            ->schema([
                                \Filament\Schemas\Components\Section::make('Lead / Liên hệ')
                                    ->schema([
                                        Toggle::make('mail_notify__lead_admin')
                                            ->label('Thông báo admin khi có lead mới')
                                            ->helperText('Gửi email khi khách điền form liên hệ, BTU calculator.')
                                            ->default(true),
                                        Toggle::make('mail_notify__lead_customer')
                                            ->label('Gửi xác nhận cho khách sau khi lead')
                                            ->helperText('Cần khách có email. Template: lead_customer_confirmation.')
                                            ->default(false),
                                        TextInput::make('lead__lead_notify_email')
                                            ->label('Email nhận thông báo lead (fallback chung)')
                                            ->email()
                                            ->columnSpanFull(),
                                    ])->columns(2),

                                \Filament\Schemas\Components\Section::make('Báo giá')
                                    ->schema([
                                        Toggle::make('mail_notify__quote_admin')
                                            ->label('Thông báo admin khi có báo giá mới')
                                            ->default(true),
                                        Toggle::make('mail_notify__quote_customer')
                                            ->label('Gửi xác nhận cho khách sau khi báo giá')
                                            ->helperText('Template: quote_customer_confirmation.')
                                            ->default(false),
                                        TextInput::make('mail_notify__quote_notify_email')
                                            ->label('Email nhận báo giá (override)')
                                            ->email()
                                            ->helperText('Để trống = dùng email nhận lead.')
                                            ->columnSpanFull(),
                                    ])->columns(2),

                                \Filament\Schemas\Components\Section::make('Đánh giá sản phẩm')
                                    ->schema([
                                        Toggle::make('mail_notify__review_admin')
                                            ->label('Thông báo admin khi có đánh giá mới')
                                            ->default(true),
                                        Toggle::make('mail_notify__review_customer')
                                            ->label('Thông báo khách khi đánh giá được duyệt')
                                            ->helperText('Template: review_approved_customer.')
                                            ->default(false),
                                        TextInput::make('mail_notify__review_notify_email')
                                            ->label('Email nhận đánh giá (override)')
                                            ->email()
                                            ->helperText('Để trống = dùng email nhận lead.')
                                            ->columnSpanFull(),
                                    ])->columns(2),

                                \Filament\Schemas\Components\Section::make('Hỏi đáp sản phẩm')
                                    ->schema([
                                        Toggle::make('mail_notify__question_admin')
                                            ->label('Thông báo admin khi có câu hỏi mới')
                                            ->default(true),
                                        Toggle::make('mail_notify__question_customer')
                                            ->label('Thông báo khách khi câu hỏi được trả lời')
                                            ->helperText('Template: question_answered_customer.')
                                            ->default(false),
                                        TextInput::make('mail_notify__question_notify_email')
                                            ->label('Email nhận câu hỏi (override)')
                                            ->email()
                                            ->helperText('Để trống = dùng email nhận lead.')
                                            ->columnSpanFull(),
                                    ])->columns(2),

                                \Filament\Schemas\Components\Section::make('Cảnh báo hệ thống')
                                    ->schema([
                                        Toggle::make('mail_notify__system_admin')
                                            ->label('Gửi email cảnh báo khi có lỗi hệ thống')
                                            ->helperText('R2 fail, AI job fail. Template: system_alert.')
                                            ->default(false),
                                    ]),
                            ]),
                    ]),

                ])->columnSpanFull(),
            ]);
    }

    public function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Lưu cấu hình')
                ->icon('heroicon-o-document-check')
                ->color('primary')
                ->action('saveSettings'),

            Action::make('clear_cache')
                ->label('Xóa Cache')
                ->icon('heroicon-o-trash')
                ->color('warning')
                ->requiresConfirmation()
                ->action(function (SettingService $svc) {
                    $svc->clearAllCache();
                    Notification::make()->title('Đã xóa cache settings')->success()->send();
                }),

            Action::make('test_ai_providers')
                ->label('Test AI Providers')
                ->icon('heroicon-o-cpu-chip')
                ->color('info')
                ->action(function () {
                    $activeCount = \App\Models\AiProvider::where('status', 'active')->count();
                    if ($activeCount === 0) {
                        Notification::make()->title('Chưa có AI Provider nào đang hoạt động')->danger()->send();
                        return;
                    }
                    Notification::make()
                        ->title('Có ' . $activeCount . ' AI Provider(s) đang hoạt động')
                        ->body('Truy cập AI Providers để test từng provider.')
                        ->success()
                        ->send();
                }),

            Action::make('test_r2')
                ->label('Test R2')
                ->icon('heroicon-o-cloud')
                ->color('info')
                ->action(function (SettingService $svc) {
                    $url = $svc->get('r2_storage.r2_public_url');
                    if (empty($url)) {
                        Notification::make()->title('Chưa cấu hình Public URL cho R2')->danger()->send();
                        return;
                    }
                    try {
                        $resp = Http::get($url);
                        if ($resp->successful() || $resp->status() === 403 || $resp->status() === 404) {
                            Notification::make()->title('R2 Endpoint hợp lệ!')->success()->send();
                        } else {
                            Notification::make()->title('Lỗi R2: HTTP ' . $resp->status())->danger()->send();
                        }
                    } catch (\Exception $e) {
                        Notification::make()->title('Không thể kết nối R2: ' . $e->getMessage())->danger()->send();
                    }
                }),

            Action::make('test_mail')
                ->label('Test Mail')
                ->icon('heroicon-o-envelope')
                ->color('info')
                ->action(function () {
                    try {
                        $mailService = app(\App\Services\Mail\MailProviderService::class);
                        $recipient   = auth()->user()->email ?? 'test@example.com';

                        $result = $mailService->send(
                            payload: [
                                'to'      => $recipient,
                                'subject' => '[Test] Kiểm tra kết nối mail — ' . setting('general.site_name', config('app.name')),
                                'html'    => '<div style="font-family:sans-serif;max-width:500px;margin:0 auto;padding:24px">
                                    <h2 style="color:#1a56db">Kết nối mail thành công!</h2>
                                    <p>Email này xác nhận hệ thống mail đang hoạt động bình thường.</p>
                                    <p style="color:#9ca3af;font-size:12px">' . setting('general.site_name', '') . ' | ' . now()->format('d/m/Y H:i:s') . '</p>
                                </div>',
                                'text'    => 'Kết nối mail thành công! — ' . now()->format('d/m/Y H:i:s'),
                            ],
                            eventKey:    'system_test',
                            templateKey: ''
                        );

                        if ($result['success']) {
                            Notification::make()->title('Đã gửi mail test thành công!')->body("Tới: {$recipient}")->success()->send();
                        } else {
                            Notification::make()->title('Gửi test thất bại')->body($result['message'])->danger()->send();
                        }
                    } catch (\Exception $e) {
                        Notification::make()->title('Lỗi Mail: ' . $e->getMessage())->danger()->send();
                    }
                }),
        ];
    }

    public function saveSettings(SettingService $svc): void
    {
        // CRITICAL: Use getState() to trigger Filament's beforeStateDehydrated
        // hooks on all schema components. This is what moves TemporaryUploadedFile
        // objects from livewire-tmp/ to their permanent disk location (branding/)
        // and converts them to path strings. Reading $this->data directly would
        // bypass this, leaving files in livewire-tmp and saving {} to the database.
        $state = $this->settingsSchema->getState();

        foreach ($state as $formKey => $value) {
            if (! str_contains($formKey, '__')) continue;

            [$group, $settingKey] = explode('__', $formKey, 2);

            $isEncrypted = in_array($formKey, self::ENCRYPTED_KEYS);

            if ($isEncrypted && ($value === null || $value === '')) continue;

            // FileUpload fields return arrays after dehydration — extract the
            // first filename string (or empty string) before persisting.
            if (in_array($formKey, self::FILE_UPLOAD_KEYS)) {
                $value = is_array($value) ? (collect($value)->first() ?? '') : ($value ?? '');
            }

            $type = is_bool($value) ? 'boolean' : 'text';
            $svc->set(
                $settingKey,
                is_bool($value) ? ($value ? '1' : '0') : (string) $value,
                $group,
                $isEncrypted,
                $type
            );
        }

        $svc->clearAllCache();

        Notification::make()->title('Đã lưu cấu hình. Cache đã được xóa.')->success()->send();
    }
}