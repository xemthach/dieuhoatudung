<?php

namespace Tests\Feature;

use App\Filament\Pages\MarketingIntegrations;
use App\Services\Marketing\BingWebmasterClient;
use App\Services\Marketing\GA4DataClient;
use App\Services\Marketing\GoogleSearchConsoleClient;
use App\Services\Marketing\IndexNowClient;
use App\Services\Marketing\MarketingIntegrationHealthService;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MarketingIntegrationsPhaseThreeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_health_service_reports_configured_and_missing_integrations(): void
    {
        config([
            'marketing_integrations.google_search_console.site_url' => 'https://example.com/',
            'marketing_integrations.google_search_console.access_token' => 'gsc-token',
            'marketing_integrations.ga4.property_id' => null,
            'marketing_integrations.ga4.access_token' => null,
            'marketing_integrations.bing_webmaster.api_key' => 'bing-key',
            'marketing_integrations.bing_webmaster.site_url' => 'https://example.com/',
        ]);

        $health = app(MarketingIntegrationHealthService::class)->summary();

        $this->assertTrue($health['integrations']['google_search_console']['configured']);
        $this->assertFalse($health['integrations']['ga4']['configured']);
        $this->assertSame(['property_id', 'access_token'], $health['integrations']['ga4']['missing']);
        $this->assertTrue($health['integrations']['bing_webmaster']['configured']);
        $this->assertContains('submit_quote', $health['recommended_events']);
    }

    public function test_search_console_client_builds_search_and_inspection_requests(): void
    {
        Http::fake([
            'searchconsole.googleapis.com/webmasters/v3/*' => Http::response(['rows' => []]),
            'searchconsole.googleapis.com/v1/urlInspection/index:inspect' => Http::response(['inspectionResult' => []]),
        ]);

        $client = new GoogleSearchConsoleClient('token');

        $client->searchAnalytics('https://example.com/', '2026-05-01', '2026-05-17');
        $client->inspectUrl('https://example.com/', 'https://example.com/product-a');

        Http::assertSent(fn ($request) => str_contains($request->url(), '/searchAnalytics/query')
            && $request->hasHeader('Authorization', 'Bearer token')
            && $request['startDate'] === '2026-05-01');

        Http::assertSent(fn ($request) => str_contains($request->url(), '/urlInspection/index:inspect')
            && $request['inspectionUrl'] === 'https://example.com/product-a');
    }

    public function test_ga4_client_builds_run_report_request(): void
    {
        Http::fake([
            'analyticsdata.googleapis.com/v1beta/properties/123:runReport' => Http::response(['rows' => []]),
        ]);

        $client = new GA4DataClient('ga-token');

        $client->runReport('123', ['sessions', 'eventCount'], ['pagePath']);

        Http::assertSent(fn ($request) => $request->url() === 'https://analyticsdata.googleapis.com/v1beta/properties/123:runReport'
            && $request->hasHeader('Authorization', 'Bearer ga-token')
            && $request['metrics'][0]['name'] === 'sessions'
            && $request['dimensions'][0]['name'] === 'pagePath');
    }

    public function test_bing_and_indexnow_clients_build_submission_requests(): void
    {
        config(['marketing_integrations.indexnow.key' => 'index-key']);

        Http::fake([
            'ssl.bing.com/webmaster/api.svc/json/SubmitUrl*' => Http::response(['d' => null]),
            'api.indexnow.org/indexnow*' => Http::response('', 200),
        ]);

        (new BingWebmasterClient('bing-key'))->submitUrl('https://example.com/', 'https://example.com/product-a');
        (new IndexNowClient())->submitUrl('https://example.com/product-a');

        Http::assertSent(fn ($request) => str_contains($request->url(), 'SubmitUrl')
            && str_contains($request->url(), 'apikey=bing-key')
            && $request['siteUrl'] === 'https://example.com/'
            && $request['url'] === 'https://example.com/product-a');

        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.indexnow.org/indexnow')
            && str_contains($request->url(), 'key=index-key'));
    }

    public function test_clients_throw_clear_missing_credential_errors(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('google_search_console_access_token_missing');

        (new GoogleSearchConsoleClient())->searchAnalytics('https://example.com/', '2026-05-01', '2026-05-17');
    }

    public function test_marketing_integrations_page_loads_for_seo_audit_user(): void
    {
        $user = UserFactory::new()->create(['is_active' => true]);
        Permission::firstOrCreate(['name' => 'seo_audit.view', 'guard_name' => 'web']);
        $user->givePermissionTo('seo_audit.view');

        $this->actingAs($user);

        Livewire::test(MarketingIntegrations::class)
            ->assertOk()
            ->assertSet('health.integrations.google_merchant_center.configured', true)
            ->call('refreshHealth')
            ->assertOk();
    }
}
