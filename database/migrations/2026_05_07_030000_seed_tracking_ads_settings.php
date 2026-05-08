<?php

use App\Models\SiteSetting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $settings = [
            // Google Ads conversion
            ['group' => 'tracking', 'key' => 'google_ads_conversion_id',        'value' => '', 'type' => 'text'],
            ['group' => 'tracking', 'key' => 'google_ads_lead_label',           'value' => '', 'type' => 'text'],
            ['group' => 'tracking', 'key' => 'google_ads_quote_label',          'value' => '', 'type' => 'text'],
            ['group' => 'tracking', 'key' => 'google_ads_phone_label',          'value' => '', 'type' => 'text'],

            // Consent & Enhanced Conversions
            ['group' => 'tracking', 'key' => 'enable_consent_mode',             'value' => '0', 'type' => 'boolean'],
            ['group' => 'tracking', 'key' => 'enable_enhanced_conversions',     'value' => '0', 'type' => 'boolean'],

            // SEO verification
            ['group' => 'seo', 'key' => 'google_site_verification',  'value' => '', 'type' => 'text'],
            ['group' => 'seo', 'key' => 'bing_site_verification',    'value' => '', 'type' => 'text'],
            ['group' => 'seo', 'key' => 'default_og_image',          'value' => '', 'type' => 'text'],
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
        $keys = [
            'google_ads_conversion_id', 'google_ads_lead_label',
            'google_ads_quote_label', 'google_ads_phone_label',
            'enable_consent_mode', 'enable_enhanced_conversions',
        ];
        SiteSetting::where('group', 'tracking')->whereIn('key', $keys)->delete();
        SiteSetting::where('group', 'seo')->whereIn('key', [
            'google_site_verification', 'bing_site_verification', 'default_og_image'
        ])->delete();
    }
};
