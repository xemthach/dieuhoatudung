<?php

namespace Tests\Feature;

use App\Models\SiteCampaign;
use App\Models\SiteCampaignEvent;
use App\Services\Marketing\SiteCampaignResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class SiteCampaignModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolver_filters_device_before_conflict_resolution(): void
    {
        $this->createCampaign([
            'title' => 'mobile high',
            'device' => 'mobile',
            'priority' => 10,
        ]);
        $this->createCampaign([
            'title' => 'desktop low',
            'device' => 'desktop',
            'priority' => 1,
        ]);

        $desktop = app(SiteCampaignResolver::class)->forRequest($this->requestFor('/', 'home', 'Mozilla/5.0 Windows NT'));
        $mobile = app(SiteCampaignResolver::class)->forRequest($this->requestFor('/', 'home', 'Mozilla/5.0 iPhone Mobile'));

        $this->assertSame(['desktop low'], $desktop->pluck('title')->all());
        $this->assertSame(['mobile high'], $mobile->pluck('title')->all());
    }

    public function test_resolver_does_not_drop_lower_priority_url_match_before_filtering(): void
    {
        foreach (range(1, 25) as $index) {
            $this->createCampaign([
                'title' => "nonmatch {$index}",
                'type' => 'top_bar',
                'priority' => 100 - $index,
                'targeting_json' => ['exact_urls' => "/nope-{$index}"],
            ]);
        }

        $this->createCampaign([
            'title' => 'actual match',
            'type' => 'top_bar',
            'priority' => 1,
            'targeting_json' => ['exact_urls' => '/target'],
        ]);

        $matches = app(SiteCampaignResolver::class)->forRequest($this->requestFor('/target', 'home'));

        $this->assertSame(['actual match'], $matches->pluck('title')->all());
    }

    public function test_tracking_rejects_expired_campaigns(): void
    {
        $campaign = $this->createCampaign([
            'title' => 'expired',
            'end_at' => now()->subMinute(),
        ]);

        $this->postJson(route('site-campaign-events.store'), [
            'campaign_id' => $campaign->id,
            'event_type' => 'impression',
            'page_url' => 'https://example.test/',
            'device' => 'desktop',
            'session_id' => 'test-session',
        ])->assertNotFound();

        $this->assertSame(0, SiteCampaignEvent::count());
    }

    public function test_component_renders_safe_video_embed_and_position_class(): void
    {
        $this->createCampaign([
            'type' => 'video_popup',
            'content_json' => [
                'title' => 'Video campaign',
                'video_url' => 'https://www.youtube.com/watch?v=abc123',
            ],
            'design_json' => [
                'position' => 'bottom_left',
                'background_color' => '#ffffff',
                'text_color' => '#0f172a',
            ],
        ]);

        app()->instance('request', $this->requestFor('/', 'home'));

        $html = Blade::render('<x-site-campaigns />');

        $this->assertStringContainsString('site-campaign--position-bottom_left', $html);
        $this->assertStringContainsString('https://www.youtube.com/embed/abc123', $html);
        $this->assertStringContainsString('<iframe', $html);
    }

    private function createCampaign(array $overrides = []): SiteCampaign
    {
        return SiteCampaign::create(array_merge([
            'title' => 'Campaign',
            'type' => 'modal',
            'status' => 'active',
            'placement' => 'all',
            'device' => 'both',
            'priority' => 0,
            'start_at' => now()->subHour(),
            'end_at' => now()->addDay(),
            'content_json' => ['title' => 'Campaign'],
            'targeting_json' => [],
            'frequency_json' => ['delay_seconds' => 0, 'frequency' => 'visit'],
            'design_json' => ['background_color' => '#ffffff', 'text_color' => '#0f172a', 'position' => 'center'],
        ], $overrides));
    }

    private function requestFor(string $path, string $routeName, string $userAgent = 'Mozilla/5.0 Windows NT'): Request
    {
        $request = Request::create($path, 'GET', [], [], [], [
            'HTTP_HOST' => 'example.test',
            'HTTP_USER_AGENT' => $userAgent,
        ]);

        $request->setRouteResolver(fn () => new class($routeName) {
            public function __construct(private string $name) {}

            public function getName(): string
            {
                return $this->name;
            }
        });

        return $request;
    }
}
