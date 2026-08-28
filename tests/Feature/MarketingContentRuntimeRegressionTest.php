<?php

namespace Tests\Feature;

use App\Filament\Resources\Posts\Pages\EditPost;
use App\Filament\Resources\Promotions\Pages\EditPromotion;
use App\Filament\Resources\SiteCampaigns\Pages\EditSiteCampaign;
use App\Models\Promotion;
use App\Models\Post;
use App\Models\Product;
use App\Models\SiteCampaign;
use App\Models\SiteCampaignEvent;
use App\Services\AI\AISeoContentGenerator;
use App\Services\Content\RichHtmlSanitizer;
use App\Services\Marketing\PromotionDisplayResolver;
use App\Services\Marketing\SiteCampaignReadinessService;
use App\Services\Product\PromotionPriceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Database\Factories\UserFactory;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MarketingContentRuntimeRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_advertised_campaign_type_has_a_runtime_renderer_and_readiness_contract(): void
    {
        foreach (array_keys(SiteCampaign::typeOptions()) as $type) {
            $campaign = $this->campaign(['type' => $type]);
            $this->assertSame('READY', app(SiteCampaignReadinessService::class)->present($campaign)['code'], $type);
        }
    }

    public function test_campaign_preview_uses_production_component_without_tracking_side_effects(): void
    {
        $campaign = $this->campaign(['status' => 'draft']);
        $before = SiteCampaignEvent::count();

        $html = Blade::render('<x-site-campaigns :campaigns="$campaigns" :preview="true" />', [
            'campaigns' => collect([$campaign]),
        ]);

        $this->assertStringContainsString('data-campaign-preview="1"', $html);
        $this->assertStringContainsString('site-campaign--modal', $html);
        $this->assertStringNotContainsString('site-campaign-events', $html);
        $this->assertStringNotContainsString('localStorage', $html);
        $this->assertSame($before, SiteCampaignEvent::count());
    }

    public function test_campaign_readiness_distinguishes_schedule_expiry_and_bad_configuration(): void
    {
        $service = app(SiteCampaignReadinessService::class);

        $this->assertSame('SCHEDULED', $service->present($this->campaign(['start_at' => now()->addHour()]))['code']);
        $this->assertSame('EXPIRED', $service->present($this->campaign(['end_at' => now()->subSecond()]))['code']);
        $this->assertSame('MISCONFIGURED', $service->present($this->campaign(['content_json' => []]))['code']);
    }

    public function test_homepage_contains_only_currently_eligible_campaign_markup(): void
    {
        $this->campaign(['title' => 'Eligible runtime campaign', 'placement' => 'home', 'content_json' => ['title' => 'Eligible runtime campaign']]);
        $this->campaign(['title' => 'Disabled runtime campaign', 'placement' => 'home', 'status' => 'paused', 'content_json' => ['title' => 'Disabled runtime campaign']]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Eligible runtime campaign')
            ->assertDontSee('Disabled runtime campaign');
    }

    public function test_all_promotion_placements_have_deterministic_route_consumers(): void
    {
        $placements = [
            'landing' => 'landing',
            'banner' => 'home',
            'popup' => 'blog.index',
            'announcement_bar' => 'blog.index',
        ];

        foreach ($placements as $placement => $routeName) {
            Promotion::factory()->create(['placement' => $placement, 'title' => 'Promotion '.$placement]);
            $resolved = app(PromotionDisplayResolver::class)->forRequest($this->requestFor($routeName));
            $this->assertTrue($resolved->contains('placement', $placement), $placement);
        }
    }

    public function test_homepage_renders_banner_promotion_but_not_landing_only_promotion(): void
    {
        Promotion::factory()->create(['placement' => 'banner', 'title' => 'Homepage promotion']);
        Promotion::factory()->create(['placement' => 'landing', 'title' => 'Landing only promotion']);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Homepage promotion')
            ->assertDontSee('Landing only promotion');
    }

    public function test_promotion_component_renders_each_supported_surface_and_sanitizes_rich_html(): void
    {
        $promotions = collect(array_map(fn (string $placement) => Promotion::factory()->make([
            'id' => crc32($placement),
            'placement' => $placement,
            'content' => '<h2>Chi tiết</h2><script>alert(1)</script><p onclick="bad()">An toàn</p>',
        ]), array_keys(PromotionDisplayResolver::PLACEMENTS)));

        $html = Blade::render('<x-promotions :promotions="$promotions" />', compact('promotions'));

        foreach (array_keys(PromotionDisplayResolver::PLACEMENTS) as $placement) {
            $this->assertStringContainsString('data-promotion-placement="'.$placement.'"', $html);
        }
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('onclick=', $html);
    }

    public function test_promotion_ai_can_generate_description_and_detailed_content_without_provider(): void
    {
        $generated = app(AISeoContentGenerator::class)->generate('promotion', [
            'title' => 'Ưu đãi kiểm thử',
            'placement' => 'landing',
            'scope' => 'global',
        ], ['promotion_description', 'detailed_content']);

        $this->assertNotEmpty($generated['promotion_description']);
        $this->assertStringContainsString('<h2>', $generated['detailed_content']);
    }

    public function test_product_cards_reuse_one_active_promotion_snapshot_per_request(): void
    {
        Promotion::factory()->create(['scope' => 'global']);
        $products = Product::factory()->count(12)->create(['regular_price' => 10000000, 'sale_price' => null]);
        DB::flushQueryLog();
        DB::enableQueryLog();

        $resolver = app(PromotionPriceResolver::class);
        $products->each(fn (Product $product) => $resolver->resolve($product));

        $promotionSelects = collect(DB::getQueryLog())
            ->filter(fn (array $query): bool => str_starts_with($query['query'], 'select * from "promotions"'))
            ->count();
        $this->assertLessThanOrEqual(1, $promotionSelects);
    }

    public function test_shared_rich_html_sanitizer_removes_editor_blocking_attributes(): void
    {
        $html = app(RichHtmlSanitizer::class)->sanitize(
            '<html><body><p contenteditable="false" style="pointer-events:none" onclick="bad()">Nội dung</p><script>bad()</script></body></html>'
        );

        $this->assertSame('<p>Nội dung</p>', $html);
    }

    public function test_ai_shaped_html_round_trips_through_filament_post_editor_and_updates_same_post(): void
    {
        $user = UserFactory::new()->create(['is_active' => true]);
        foreach (['post.view', 'post.edit', 'ai_content_job.view'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
        $user->givePermissionTo(['post.view', 'post.edit', 'ai_content_job.view']);
        $post = Post::factory()->create(['content' => '<h2>Nội dung AI</h2><p>Đoạn gốc.</p>']);
        $postCount = Post::count();

        $this->actingAs($user);
        Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])
            ->assertOk()
            ->set('data.content', '<h2>Nội dung AI</h2><p>Đã sửa trong editor.</p>')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertStringContainsString('Đã sửa trong editor.', (string) $post->refresh()->content);
        $this->assertSame($postCount, Post::count());
    }

    public function test_authorized_admin_forms_render_campaign_preview_and_promotion_ai_fields(): void
    {
        $user = UserFactory::new()->create(['is_active' => true]);
        $permissions = ['site_campaign.view', 'site_campaign.edit', 'promotion.view', 'promotion.edit'];
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
        $user->givePermissionTo($permissions);
        $campaign = $this->campaign();
        $promotion = Promotion::factory()->create();

        $this->actingAs($user);
        Livewire::test(EditSiteCampaign::class, ['record' => $campaign->getRouteKey()])
            ->assertOk()
            ->assertActionVisible('preview_campaign');
        Livewire::test(EditPromotion::class, ['record' => $promotion->getRouteKey()])
            ->assertOk()
            ->assertSee('Nội dung chi tiết');
    }

    private function campaign(array $overrides = []): SiteCampaign
    {
        $type = (string) ($overrides['type'] ?? 'modal');
        $content = match ($type) {
            'image_popup' => ['title' => 'Campaign test', 'image' => 'campaigns/fixture.jpg'],
            'video_popup' => ['title' => 'Campaign test', 'video_url' => 'https://youtu.be/fixture'],
            default => ['title' => 'Campaign test'],
        };

        return SiteCampaign::create(array_merge([
            'title' => 'Campaign test',
            'type' => 'modal',
            'status' => 'active',
            'placement' => 'all',
            'device' => 'both',
            'content_json' => $content,
            'priority' => 1,
            'start_at' => now()->subMinute(),
            'end_at' => now()->addHour(),
        ], $overrides));
    }

    private function requestFor(string $routeName): Request
    {
        $request = Request::create('/', 'GET');
        $request->setRouteResolver(fn () => new class($routeName) {
            public function __construct(private string $name) {}
            public function getName(): string { return $this->name; }
            public function parameter(string $key): mixed { return null; }
        });

        return $request;
    }
}
