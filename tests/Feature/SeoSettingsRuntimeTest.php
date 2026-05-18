<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Services\Settings\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoSettingsRuntimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_robots_uses_disallow_toggles(): void
    {
        SiteSetting::insert([
            ['group' => 'robots', 'key' => 'robots_content', 'value' => '', 'type' => 'text'],
            ['group' => 'robots', 'key' => 'robots_disallow_admin', 'value' => '0', 'type' => 'boolean'],
            ['group' => 'robots', 'key' => 'robots_disallow_search', 'value' => '1', 'type' => 'boolean'],
            ['group' => 'robots', 'key' => 'robots_disallow_filter_urls', 'value' => '0', 'type' => 'boolean'],
            ['group' => 'seo', 'key' => 'canonical_base_url', 'value' => 'https://example.test', 'type' => 'text'],
        ]);

        app(SettingService::class)->clearAllCache();

        $response = $this->get('/robots.txt')->assertOk();

        $response->assertSee('Disallow: /search', false);
        $response->assertSee('Disallow: /tim-kiem', false);
        $response->assertDontSee('Disallow: /admin', false);
        $response->assertDontSee('Disallow: /*?filter=', false);
        $response->assertSee('Sitemap: https://example.test/sitemap.xml', false);
    }

    public function test_sitemap_cache_header_uses_setting_minutes(): void
    {
        SiteSetting::create([
            'group' => 'sitemap',
            'key' => 'sitemap_cache_minutes',
            'value' => '5',
            'type' => 'integer',
        ]);

        app(SettingService::class)->clearAllCache();

        $response = $this->get('/sitemap.xml')->assertOk();
        $cacheControl = $response->headers->get('Cache-Control');

        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=300', $cacheControl);
    }
}
