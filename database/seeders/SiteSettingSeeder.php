<?php

namespace Database\Seeders;

use App\Models\SiteSetting;
use Illuminate\Database\Seeder;

class SiteSettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // === GENERAL ===
            ['group' => 'general', 'key' => 'site_name',        'value' => 'Điều Hòa Không Khí',                       'type' => 'text'],
            ['group' => 'general', 'key' => 'site_slogan',      'value' => 'Giải pháp làm mát chuyên nghiệp',         'type' => 'text'],
            ['group' => 'general', 'key' => 'company_name',     'value' => 'Công Ty TNHH Điều Hòa Không Khí',          'type' => 'text'],
            ['group' => 'general', 'key' => 'company_address',  'value' => '123 Đường ABC, Quận XYZ, TP.HCM',         'type' => 'text'],
            ['group' => 'general', 'key' => 'company_phone',    'value' => '0909.123.456',                             'type' => 'text'],
            ['group' => 'general', 'key' => 'company_email',    'value' => env('MAIL_FROM_ADDRESS', ''),              'type' => 'text'],
            ['group' => 'general', 'key' => 'company_tax_code', 'value' => '',                                         'type' => 'text'],
            ['group' => 'general', 'key' => 'working_hours',    'value' => 'T2-T7: 08:00 - 18:00 | CN: 08:00 - 12:00', 'type' => 'text'],

            // === CONTACT ===
            ['group' => 'contact', 'key' => 'hotline',          'value' => '0909.123.456',                             'type' => 'text'],
            ['group' => 'contact', 'key' => 'zalo_phone',       'value' => '0909.123.456',                             'type' => 'text'],
            ['group' => 'contact', 'key' => 'zalo_link',        'value' => 'https://zalo.me/0909123456',               'type' => 'text'],
            ['group' => 'contact', 'key' => 'email',            'value' => env('MAIL_FROM_ADDRESS', ''),              'type' => 'text'],
            ['group' => 'contact', 'key' => 'contact_address',  'value' => '123 Đường ABC, Quận XYZ, TP.HCM',         'type' => 'text'],
            ['group' => 'contact', 'key' => 'google_map_embed', 'value' => '',                                         'type' => 'text'],
            ['group' => 'contact', 'key' => 'facebook_link',    'value' => '',                                         'type' => 'text'],
            ['group' => 'contact', 'key' => 'youtube_link',     'value' => '',                                         'type' => 'text'],
            ['group' => 'contact', 'key' => 'tiktok_link',      'value' => '',                                         'type' => 'text'],

            // === AI CONTENT GUARD DEFAULTS ===
            ['group' => 'ai_guard_policy', 'key' => 'CONTENT_TOO_SHORT', 'value' => 'WARN', 'type' => 'text'],
            ['group' => 'ai_guard_policy', 'key' => 'MISSING_H2_H3',      'value' => 'WARN', 'type' => 'text'],
            ['group' => 'ai_guard_policy', 'key' => 'MISSING_SEO',       'value' => 'WARN', 'type' => 'text'],
            ['group' => 'ai_guard_policy', 'key' => 'MISSING_MERCHANT', 'value' => 'WARN', 'type' => 'text'],
            ['group' => 'ai_guard_policy', 'key' => 'MISSING_FAQ',      'value' => 'WARN', 'type' => 'text'],

            // === UI SETTINGS STATE DEFAULTS ===
            ['group' => 'r2_storage', 'key' => 'r2_sync_enabled', 'value' => '0', 'type' => 'boolean'],
            ['group' => 'r2_storage', 'key' => 'r2_sync_replace_urls_enabled', 'value' => '0', 'type' => 'boolean'],
            ['group' => 'r2_storage', 'key' => 'r2_sync_delete_local_after_upload', 'value' => '0', 'type' => 'boolean'],
            ['group' => 'r2_storage', 'key' => 'r2_old_base_urls', 'value' => '', 'type' => 'text'],
            ['group' => 'cta', 'key' => 'mobile_bar_call_enabled', 'value' => '1', 'type' => 'boolean'],
            ['group' => 'cta', 'key' => 'mobile_bar_zalo_enabled', 'value' => '1', 'type' => 'boolean'],
            ['group' => 'cta', 'key' => 'mobile_bar_quote_enabled', 'value' => '1', 'type' => 'boolean'],

            // === SEO ===
            ['group' => 'seo', 'key' => 'default_seo_title',       'value' => 'Sản Phẩm Điều Hòa | Tư Vấn Theo Nhu Cầu', 'type' => 'text'],
            ['group' => 'seo', 'key' => 'default_meta_description', 'value' => 'Tham khảo sản phẩm điều hòa và thông số kỹ thuật theo nhu cầu công trình.', 'type' => 'text'],
            ['group' => 'seo', 'key' => 'default_robots',           'value' => 'index,follow',                    'type' => 'text'],
            ['group' => 'seo', 'key' => 'canonical_base_url',       'value' => env('APP_URL', ''),       'type' => 'text'],
            ['group' => 'seo', 'key' => 'enable_schema',            'value' => '1',                                'type' => 'boolean'],
            ['group' => 'seo', 'key' => 'enable_breadcrumb_schema', 'value' => '1',                                'type' => 'boolean'],
            ['group' => 'seo', 'key' => 'enable_faq_schema',        'value' => '1',                                'type' => 'boolean'],
            ['group' => 'seo', 'key' => 'enable_product_schema',    'value' => '1',                                'type' => 'boolean'],
            ['group' => 'seo', 'key' => 'enable_article_schema',    'value' => '1',                                'type' => 'boolean'],

            // === SCHEMA ORGANIZATION ===
            ['group' => 'schema_organization', 'key' => 'organization_name',    'value' => 'Điều Hòa Không Khí',           'type' => 'text'],
            ['group' => 'schema_organization', 'key' => 'organization_url',     'value' => env('APP_URL', ''),  'type' => 'text'],
            ['group' => 'schema_organization', 'key' => 'organization_logo',    'value' => '/images/logo.png',            'type' => 'text'],
            ['group' => 'schema_organization', 'key' => 'organization_phone',   'value' => '0909.123.456',                'type' => 'text'],
            ['group' => 'schema_organization', 'key' => 'organization_email',   'value' => env('MAIL_FROM_ADDRESS', ''), 'type' => 'text'],
            ['group' => 'schema_organization', 'key' => 'organization_address', 'value' => '123 Đường ABC, TP.HCM',       'type' => 'text'],
            ['group' => 'schema_organization', 'key' => 'organization_same_as', 'value' => '',                            'type' => 'text'],

            // === AI ===
            ['group' => 'ai', 'key' => 'ai_enabled',                   'value' => '0', 'type' => 'boolean'],
            ['group' => 'ai', 'key' => 'ai_provider',                  'value' => 'gemini', 'type' => 'text'],
            ['group' => 'ai', 'key' => 'gemini_api_key',               'value' => '', 'type' => 'text', 'is_encrypted' => true],
            ['group' => 'ai', 'key' => 'gemini_model',                 'value' => 'gemini-2.0-flash', 'type' => 'text'],
            ['group' => 'ai', 'key' => 'ai_default_language',          'value' => 'vi', 'type' => 'text'],
            ['group' => 'ai', 'key' => 'ai_auto_tag_enabled',          'value' => '1', 'type' => 'boolean'],
            ['group' => 'ai', 'key' => 'ai_auto_internal_link_enabled','value' => '1', 'type' => 'boolean'],
            ['group' => 'ai', 'key' => 'ai_auto_publish_enabled',      'value' => '0', 'type' => 'boolean'],
            ['group' => 'ai', 'key' => 'ai_max_tokens',                'value' => '8192', 'type' => 'integer'],
            ['group' => 'ai', 'key' => 'ai_temperature',               'value' => '0.7', 'type' => 'text'],

            // === R2 STORAGE ===
            ['group' => 'r2_storage', 'key' => 'r2_enabled',          'value' => '0', 'type' => 'boolean'],
            ['group' => 'r2_storage', 'key' => 'r2_access_key_id',    'value' => '', 'type' => 'text', 'is_encrypted' => true],
            ['group' => 'r2_storage', 'key' => 'r2_secret_access_key','value' => '', 'type' => 'text', 'is_encrypted' => true],
            ['group' => 'r2_storage', 'key' => 'r2_bucket',           'value' => '', 'type' => 'text'],
            ['group' => 'r2_storage', 'key' => 'r2_endpoint',         'value' => '', 'type' => 'text'],
            ['group' => 'r2_storage', 'key' => 'r2_public_url',       'value' => '', 'type' => 'text'],
            ['group' => 'r2_storage', 'key' => 'r2_default_folder',   'value' => 'uploads', 'type' => 'text'],

            // === UPLOAD LIMITS ===
            ['group' => 'upload', 'key' => 'image_max_size_kb',         'value' => '5120', 'type' => 'text'],
            ['group' => 'upload', 'key' => 'avatar_max_size_kb',        'value' => '2048', 'type' => 'text'],
            ['group' => 'upload', 'key' => 'product_image_max_size_kb', 'value' => '5120', 'type' => 'text'],
            ['group' => 'upload', 'key' => 'review_image_max_size_kb',  'value' => '3072', 'type' => 'text'],
            ['group' => 'upload', 'key' => 'brand_logo_max_size_kb',    'value' => '2048', 'type' => 'text'],
            ['group' => 'upload', 'key' => 'document_max_size_kb',      'value' => '51200', 'type' => 'text'],
            ['group' => 'upload', 'key' => 'file_max_size_kb',          'value' => '51200', 'type' => 'text'],
            ['group' => 'upload', 'key' => 'max_images_per_upload',     'value' => '10', 'type' => 'text'],
            ['group' => 'upload', 'key' => 'allowed_image_types',       'value' => 'image/jpeg,image/png,image/webp,image/gif', 'type' => 'text'],
            ['group' => 'upload', 'key' => 'allowed_file_types',        'value' => 'application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'type' => 'text'],

            // === MAIL SERVER ===
            ['group' => 'mail', 'key' => 'mail_enabled',            'value' => '0', 'type' => 'boolean'],
            ['group' => 'mail', 'key' => 'mail_provider',           'value' => 'smtp', 'type' => 'text'],
            ['group' => 'mail', 'key' => 'mail_from_name',          'value' => 'Điều Hòa Không Khí', 'type' => 'text'],
            ['group' => 'mail', 'key' => 'mail_from_address',       'value' => env('MAIL_FROM_ADDRESS', ''), 'type' => 'text'],
            ['group' => 'mail', 'key' => 'mail_test_recipient',     'value' => '', 'type' => 'text'],
            ['group' => 'mail', 'key' => 'smtp_host',               'value' => '', 'type' => 'text'],
            ['group' => 'mail', 'key' => 'smtp_port',               'value' => '587', 'type' => 'integer'],
            ['group' => 'mail', 'key' => 'smtp_encryption',         'value' => 'tls', 'type' => 'text'],

            // === LEAD ===
            ['group' => 'lead', 'key' => 'lead_notify_email',           'value' => env('MAIL_FROM_ADDRESS', ''), 'type' => 'text'],
            ['group' => 'lead', 'key' => 'lead_success_message',        'value' => 'Cảm ơn bạn! Chúng tôi sẽ liên hệ trong vòng 30 phút.', 'type' => 'text'],
            ['group' => 'lead', 'key' => 'lead_required_phone',         'value' => '1', 'type' => 'boolean'],
            ['group' => 'lead', 'key' => 'lead_required_email',         'value' => '0', 'type' => 'boolean'],
            ['group' => 'lead', 'key' => 'lead_default_status',         'value' => 'new', 'type' => 'text'],
            ['group' => 'lead', 'key' => 'lead_spam_protection_enabled','value' => '1', 'type' => 'boolean'],

            // === SITEMAP ===
            ['group' => 'sitemap', 'key' => 'sitemap_enabled',             'value' => '1', 'type' => 'boolean'],
            ['group' => 'sitemap', 'key' => 'sitemap_include_products',    'value' => '1', 'type' => 'boolean'],
            ['group' => 'sitemap', 'key' => 'sitemap_include_posts',       'value' => '1', 'type' => 'boolean'],
            ['group' => 'sitemap', 'key' => 'sitemap_include_categories',  'value' => '1', 'type' => 'boolean'],
            ['group' => 'sitemap', 'key' => 'sitemap_include_tags',        'value' => '0', 'type' => 'boolean'],
            ['group' => 'sitemap', 'key' => 'sitemap_include_case_studies','value' => '0', 'type' => 'boolean'],
            ['group' => 'sitemap', 'key' => 'sitemap_exclude_noindex',     'value' => '1', 'type' => 'boolean'],
            ['group' => 'sitemap', 'key' => 'sitemap_cache_minutes',       'value' => '60', 'type' => 'integer'],

            // === ROBOTS ===
            ['group' => 'robots', 'key' => 'robots_content',              'value' => '', 'type' => 'text'],
            ['group' => 'robots', 'key' => 'robots_disallow_admin',       'value' => '1', 'type' => 'boolean'],
            ['group' => 'robots', 'key' => 'robots_disallow_search',      'value' => '1', 'type' => 'boolean'],
            ['group' => 'robots', 'key' => 'robots_disallow_filter_urls', 'value' => '1', 'type' => 'boolean'],

            // === TRACKING ===
            ['group' => 'tracking', 'key' => 'google_analytics_id',    'value' => '', 'type' => 'text'],
            ['group' => 'tracking', 'key' => 'google_tag_manager_id',  'value' => '', 'type' => 'text'],
            ['group' => 'tracking', 'key' => 'facebook_pixel_id',      'value' => '', 'type' => 'text'],
            ['group' => 'tracking', 'key' => 'custom_head_scripts',    'value' => '', 'type' => 'text'],
            ['group' => 'tracking', 'key' => 'custom_body_scripts',    'value' => '', 'type' => 'text'],
            ['group' => 'tracking', 'key' => 'custom_footer_scripts',  'value' => '', 'type' => 'text'],

            // === DISPLAY ===
            ['group' => 'display', 'key' => 'products_per_page',             'value' => '12', 'type' => 'integer'],
            ['group' => 'display', 'key' => 'posts_per_page',                'value' => '10', 'type' => 'integer'],
            ['group' => 'display', 'key' => 'featured_products_limit',       'value' => '8',  'type' => 'integer'],
            ['group' => 'display', 'key' => 'related_products_limit',        'value' => '4',  'type' => 'integer'],
            ['group' => 'display', 'key' => 'related_posts_limit',           'value' => '4',  'type' => 'integer'],
            ['group' => 'display', 'key' => 'homepage_featured_limit',       'value' => '6',  'type' => 'integer'],
            ['group' => 'display', 'key' => 'homepage_dealers_enabled',      'value' => '0',  'type' => 'boolean'],
            ['group' => 'display', 'key' => 'homepage_dealers_title',        'value' => 'Đại lý gần bạn', 'type' => 'text'],
            ['group' => 'display', 'key' => 'homepage_dealers_subtitle',     'value' => 'Tìm đại lý và đơn vị dịch vụ gần khu vực của bạn.', 'type' => 'text'],
            ['group' => 'display', 'key' => 'homepage_dealers_mode',         'value' => 'auto', 'type' => 'text'],
            ['group' => 'display', 'key' => 'homepage_dealers_limit',        'value' => '4', 'type' => 'integer'],
            ['group' => 'display', 'key' => 'homepage_dealers_radius_km',    'value' => '20', 'type' => 'integer'],
            ['group' => 'display', 'key' => 'homepage_dealers_geolocation',  'value' => '1', 'type' => 'boolean'],
            ['group' => 'display', 'key' => 'homepage_dealers_manual',       'value' => '[]', 'type' => 'json'],
            ['group' => 'display', 'key' => 'homepage_dealers_default_province', 'value' => '', 'type' => 'text'],
            ['group' => 'display', 'key' => 'homepage_dealers_show_logo',   'value' => '1', 'type' => 'boolean'],
            ['group' => 'display', 'key' => 'homepage_dealers_show_verified', 'value' => '1', 'type' => 'boolean'],
            ['group' => 'display', 'key' => 'homepage_dealers_show_distance', 'value' => '1', 'type' => 'boolean'],
            ['group' => 'display', 'key' => 'homepage_dealers_show_rating', 'value' => '1', 'type' => 'boolean'],
            ['group' => 'display', 'key' => 'homepage_dealers_show_review_count', 'value' => '1', 'type' => 'boolean'],
            ['group' => 'display', 'key' => 'homepage_dealers_show_brands', 'value' => '1', 'type' => 'boolean'],
            ['group' => 'display', 'key' => 'homepage_dealers_show_categories', 'value' => '1', 'type' => 'boolean'],
            ['group' => 'display', 'key' => 'homepage_dealers_show_services', 'value' => '1', 'type' => 'boolean'],
            ['group' => 'display', 'key' => 'homepage_dealers_show_phone', 'value' => '1', 'type' => 'boolean'],
            ['group' => 'display', 'key' => 'dealer_card_style', 'value' => 'standard', 'type' => 'text'],
            ['group' => 'display', 'key' => 'dealer_brand_style', 'value' => 'logo_name', 'type' => 'text'],
            ['group' => 'display', 'key' => 'dealer_product_line_style', 'value' => 'icon_badge', 'type' => 'text'],
            ['group' => 'display', 'key' => 'dealer_service_style', 'value' => 'icon_badge', 'type' => 'text'],
            ['group' => 'display', 'key' => 'dealer_max_brands', 'value' => '4', 'type' => 'integer'],
            ['group' => 'display', 'key' => 'dealer_max_product_lines', 'value' => '4', 'type' => 'integer'],
            ['group' => 'display', 'key' => 'dealer_max_services', 'value' => '4', 'type' => 'integer'],
            ['group' => 'display', 'key' => 'homepage_section_order', 'value' => '[{"section":"hero","sort_order":10},{"section":"benefits","sort_order":20},{"section":"dealers","sort_order":30},{"section":"featured_products","sort_order":40},{"section":"brands","sort_order":50},{"section":"articles","sort_order":60},{"section":"faq","sort_order":70},{"section":"cta","sort_order":80}]', 'type' => 'json'],
            ['group' => 'display', 'key' => 'landing_featured_products_limit','value' => '6', 'type' => 'integer'],

            // === CTA ===
            // Site scope references a canonical category; empty copy means automatic defaults.
            ['group' => 'site', 'key' => 'product_scope', 'value' => 'all_products', 'type' => 'text'],
            ['group' => 'site', 'key' => 'primary_product_category_id', 'value' => '', 'type' => 'text'],
            ['group' => 'site', 'key' => 'product_label', 'value' => '', 'type' => 'text'],
            ['group' => 'site', 'key' => 'compare_title', 'value' => '', 'type' => 'text'],
            ['group' => 'site', 'key' => 'compare_description', 'value' => '', 'type' => 'text'],
            ['group' => 'site', 'key' => 'consultation_title', 'value' => '', 'type' => 'text'],
            ['group' => 'site', 'key' => 'consultation_description', 'value' => '', 'type' => 'text'],
            ['group' => 'site', 'key' => 'product_cta_label', 'value' => '', 'type' => 'text'],
            ['group' => 'site', 'key' => 'product_cta_type', 'value' => 'auto', 'type' => 'text'],
            ['group' => 'site', 'key' => 'consultation_cta_type', 'value' => 'auto', 'type' => 'text'],

            ['group' => 'cta', 'key' => 'global_cta_text',  'value' => 'Nhận báo giá',     'type' => 'text'],
            ['group' => 'cta', 'key' => 'global_cta_link',  'value' => '',          'type' => 'text'],
            ['group' => 'cta', 'key' => 'quote_cta_text',   'value' => 'Yêu cầu báo giá',  'type' => 'text'],
            ['group' => 'cta', 'key' => 'quote_cta_link',   'value' => '',          'type' => 'text'],
            ['group' => 'cta', 'key' => 'phone_cta_text',   'value' => 'Gọi ngay',          'type' => 'text'],
            ['group' => 'cta', 'key' => 'zalo_cta_text',    'value' => 'Chat Zalo',         'type' => 'text'],
        ];

        foreach ($settings as $setting) {
            SiteSetting::firstOrCreate(
                ['group' => $setting['group'], 'key' => $setting['key']],
                [
                    'value'        => $setting['value'],
                    'type'         => $setting['type'],
                    'is_encrypted' => $setting['is_encrypted'] ?? false,
                ]
            );
        }
    }
}
