<?php

use App\Models\SiteSetting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $settings = [
            // === BRANDING / LOGO ===
            ['group' => 'branding', 'key' => 'logo_display_mode',       'value' => 'logo_text', 'type' => 'text'],
            ['group' => 'branding', 'key' => 'logo_image',              'value' => '', 'type' => 'text'],
            ['group' => 'branding', 'key' => 'logo_dark_image',         'value' => '', 'type' => 'text'],
            ['group' => 'branding', 'key' => 'logo_footer_image',       'value' => '', 'type' => 'text'],
            ['group' => 'branding', 'key' => 'logo_mobile_image',       'value' => '', 'type' => 'text'],
            ['group' => 'branding', 'key' => 'favicon',                 'value' => '', 'type' => 'text'],
            ['group' => 'branding', 'key' => 'apple_touch_icon',        'value' => '', 'type' => 'text'],
            ['group' => 'branding', 'key' => 'logo_alt_text',           'value' => '', 'type' => 'text'],
            ['group' => 'branding', 'key' => 'logo_width_desktop',      'value' => '160', 'type' => 'integer'],
            ['group' => 'branding', 'key' => 'logo_width_mobile',       'value' => '120', 'type' => 'integer'],
            ['group' => 'branding', 'key' => 'logo_height_max',         'value' => '48', 'type' => 'integer'],
            ['group' => 'branding', 'key' => 'logo_text',               'value' => '', 'type' => 'text'],
            ['group' => 'branding', 'key' => 'logo_text_color',         'value' => '', 'type' => 'text'],
            ['group' => 'branding', 'key' => 'brand_primary_color',     'value' => '#1e40af', 'type' => 'text'],
            ['group' => 'branding', 'key' => 'brand_secondary_color',   'value' => '#0f766e', 'type' => 'text'],
            ['group' => 'branding', 'key' => 'brand_accent_color',      'value' => '#f59e0b', 'type' => 'text'],
            ['group' => 'branding', 'key' => 'header_logo_mode',        'value' => 'auto', 'type' => 'text'],
            ['group' => 'branding', 'key' => 'footer_logo_mode',        'value' => 'auto', 'type' => 'text'],
            ['group' => 'branding', 'key' => 'footer_show_slogan',      'value' => '1', 'type' => 'boolean'],
            ['group' => 'branding', 'key' => 'footer_show_company_name','value' => '1', 'type' => 'boolean'],
            ['group' => 'branding', 'key' => 'footer_show_contact',     'value' => '1', 'type' => 'boolean'],

            // === GENERAL – new field ===
            ['group' => 'general', 'key' => 'site_short_name', 'value' => '', 'type' => 'text'],
        ];

        foreach ($settings as $s) {
            SiteSetting::firstOrCreate(
                ['group' => $s['group'], 'key' => $s['key']],
                ['value' => $s['value'], 'type' => $s['type'], 'is_encrypted' => false]
            );
        }
    }

    public function down(): void
    {
        SiteSetting::where('group', 'branding')->delete();
        SiteSetting::where('group', 'general')->where('key', 'site_short_name')->delete();
    }
};
