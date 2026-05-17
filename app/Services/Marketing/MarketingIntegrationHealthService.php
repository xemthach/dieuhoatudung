<?php

namespace App\Services\Marketing;

use App\Models\GoogleAdsConversionImport;
use App\Services\Merchant\MerchantFeedService;
use Illuminate\Support\Arr;

class MarketingIntegrationHealthService
{
    public function __construct(private MerchantFeedService $merchantFeedService) {}

    public function summary(): array
    {
        $integrations = [
            'google_search_console' => $this->googleSearchConsole(),
            'ga4' => $this->ga4(),
            'google_ads' => $this->googleAds(),
            'google_merchant_center' => $this->merchantCenter(),
            'bing_webmaster' => $this->bingWebmaster(),
            'tag_manager' => $this->tagManager(),
        ];

        $configured = collect($integrations)->where('configured', true)->count();

        return [
            'integrations' => $integrations,
            'recommended_events' => [
                'view_product',
                'click_hotline',
                'click_zalo',
                'submit_quote',
                'add_compare',
                'search_product',
                'download_catalog',
            ],
            'summary' => [
                'configured_count' => $configured,
                'total_count' => count($integrations),
                'critical_missing' => collect($integrations)
                    ->filter(fn (array $integration) => ! $integration['configured'] && ($integration['severity'] ?? null) === 'critical')
                    ->pluck('label')
                    ->values()
                    ->all(),
            ],
        ];
    }

    protected function googleSearchConsole(): array
    {
        $siteUrl = $this->value('google_search_console.site_url', 'integrations.google_search_console_site_url');
        $accessToken = $this->value('google_search_console.access_token', 'integrations.google_search_console_access_token');

        return $this->integration(
            'Google Search Console',
            [
                'site_url' => $siteUrl,
                'access_token' => $this->masked($accessToken),
            ],
            [
                'search analytics',
                'URL inspection',
                'sitemap submit',
            ],
            [
                'site_url' => $siteUrl,
                'access_token' => $accessToken,
            ],
            'high',
            [
                'https://www.googleapis.com/auth/webmasters.readonly',
                'https://www.googleapis.com/auth/webmasters',
            ]
        );
    }

    protected function ga4(): array
    {
        $propertyId = $this->value('ga4.property_id', 'integrations.ga4_property_id');
        $accessToken = $this->value('ga4.access_token', 'integrations.ga4_access_token');

        return $this->integration(
            'Google Analytics 4',
            [
                'property_id' => $propertyId,
                'access_token' => $this->masked($accessToken),
            ],
            [
                'sessions',
                'page performance',
                'lead events',
                'traffic sources',
            ],
            [
                'property_id' => $propertyId,
                'access_token' => $accessToken,
            ],
            'high',
            ['https://www.googleapis.com/auth/analytics.readonly']
        );
    }

    protected function googleAds(): array
    {
        $developerToken = $this->value('google_ads.developer_token', 'integrations.google_ads_developer_token');
        $customerId = $this->value('google_ads.customer_id', 'integrations.google_ads_customer_id');
        $accessToken = $this->value('google_ads.access_token', 'integrations.google_ads_access_token');
        $conversionAction = $this->value('google_ads.conversion_action_resource_name', 'integrations.google_ads_conversion_action_resource_name')
            ?: $this->value('google_ads.conversion_action_id', 'integrations.google_ads_conversion_action_id');
        $conversionId = setting('tracking.google_ads_conversion_id');

        $integration = $this->integration(
            'Google Ads',
            [
                'developer_token' => $this->masked($developerToken),
                'customer_id' => $customerId,
                'access_token' => $this->masked($accessToken),
                'conversion_action' => $conversionAction,
                'conversion_id' => $conversionId,
                'offline_conversions' => $this->offlineConversionStats(),
            ],
            [
                'conversion tracking readiness',
                'lead conversion mapping',
                'remarketing readiness',
                'offline click conversion upload',
            ],
            [
                'developer_token' => $developerToken,
                'customer_id' => $customerId,
                'access_token' => $accessToken,
                'conversion_action' => $conversionAction,
            ],
            'medium'
        );

        $integration['browser_tracking_configured'] = filled($conversionId);

        return $integration;
    }

    protected function merchantCenter(): array
    {
        $diagnostics = $this->merchantFeedService->getDiagnostics();

        return [
            'label' => 'Google Merchant Center',
            'configured' => true,
            'severity' => 'high',
            'missing' => [],
            'capabilities' => [
                'XML product feed',
                'feed diagnostics',
                'sale price effective date',
            ],
            'values' => [
                'feed_url' => route('merchant.feed'),
                'eligible_for_feed' => $diagnostics['eligible_for_feed'] ?? 0,
                'total_active' => $diagnostics['total_active'] ?? 0,
                'excluded_no_price' => $diagnostics['excluded_no_price'] ?? 0,
                'excluded_no_image' => $diagnostics['excluded_no_image'] ?? 0,
                'missing_brand' => $diagnostics['missing_brand'] ?? 0,
                'missing_google_product_category' => $diagnostics['missing_google_product_category'] ?? 0,
            ],
        ];
    }

    protected function bingWebmaster(): array
    {
        $siteUrl = $this->value('bing_webmaster.site_url', 'integrations.bing_webmaster_site_url');
        $apiKey = $this->value('bing_webmaster.api_key', 'integrations.bing_webmaster_api_key');
        $indexNowKey = $this->value('indexnow.key', 'integrations.indexnow_key');

        $integration = $this->integration(
            'Bing Webmaster / IndexNow',
            [
                'site_url' => $siteUrl,
                'api_key' => $this->masked($apiKey),
                'indexnow_key' => $this->masked($indexNowKey),
            ],
            [
                'sitemap submit',
                'URL submit',
                'IndexNow URL notifications',
            ],
            [
                'site_url' => $siteUrl,
                'api_key_or_indexnow_key' => filled($apiKey) || filled($indexNowKey),
            ],
            'medium'
        );

        return $integration;
    }

    protected function tagManager(): array
    {
        $gtm = setting('tracking.google_tag_manager_id');
        $ga = setting('tracking.google_analytics_id');
        $facebook = setting('tracking.facebook_pixel_id');

        return [
            'label' => 'Tags & Pixels',
            'configured' => filled($gtm) || filled($ga) || filled($facebook),
            'severity' => 'medium',
            'missing' => filled($gtm) || filled($ga) || filled($facebook) ? [] : ['tracking_tag'],
            'capabilities' => [
                'GTM',
                'GA4 browser tracking',
                'Facebook Pixel',
                'custom scripts',
            ],
            'values' => [
                'google_tag_manager_id' => $gtm,
                'google_analytics_id' => $ga,
                'facebook_pixel_id' => $facebook,
            ],
        ];
    }

    protected function offlineConversionStats(): array
    {
        if (! class_exists(GoogleAdsConversionImport::class) || ! \Illuminate\Support\Facades\Schema::hasTable('google_ads_conversion_imports')) {
            return [
                'pending' => 0,
                'synced' => 0,
                'failed' => 0,
            ];
        }

        return GoogleAdsConversionImport::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->only(['pending', 'validated', 'synced', 'failed'])
            ->all();
    }

    protected function integration(string $label, array $values, array $capabilities, array $requirements, string $severity, array $scopes = []): array
    {
        $missing = collect($requirements)
            ->filter(fn ($value) => ! filled($value))
            ->keys()
            ->values()
            ->all();

        return [
            'label' => $label,
            'configured' => $missing === [],
            'severity' => $severity,
            'missing' => $missing,
            'capabilities' => $capabilities,
            'required_scopes' => $scopes,
            'values' => $values,
        ];
    }

    protected function value(string $configPath, string $settingKey): mixed
    {
        return setting($settingKey, Arr::get(config('marketing_integrations'), $configPath));
    }

    protected function masked(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return str($value)->limit(6, '').'...'.str($value)->substr(-4);
    }
}
