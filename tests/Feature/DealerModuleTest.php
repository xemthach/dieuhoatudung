<?php

namespace Tests\Feature;

use App\Filament\Resources\Dealers\Pages\CreateDealer;
use App\Filament\Resources\Dealers\Pages\EditDealer;
use App\Filament\Pages\ManageSiteSettings;
use App\Models\AdministrativeUnit;
use App\Models\Brand;
use App\Models\Dealer;
use App\Models\DealerReview;
use App\Models\DealerService;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Services\Dealer\DealerDirectoryService;
use App\Services\Dealer\DealerLocationQuery;
use App\Services\Dealer\DealerReviewService;
use App\Services\Dealer\MapCoordinateParser;
use App\Services\Settings\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class DealerModuleTest extends TestCase
{
    use RefreshDatabase;

    private function unit(string $scheme, string $kind, string $code, ?AdministrativeUnit $parent = null): AdministrativeUnit
    {
        return AdministrativeUnit::create(['scheme' => $scheme, 'dataset_version' => config("dealers.{$scheme}_version"),
            'kind' => $kind, 'code' => $code, 'name' => $code, 'full_name' => $kind.' '.$code, 'slug' => $scheme.'-'.$kind.'-'.$code, 'parent_id' => $parent?->id]);
    }

    private function address(): array
    {
        $p = $this->unit('current', 'province', '01');

        return ['province_id' => $p->id, 'ward_id' => $this->unit('current', 'ward', '00001', $p)->id];
    }

    private function dealer(array $values = []): Dealer
    {
        $address = AdministrativeUnit::where('scheme', 'current')->where('kind', 'ward')->first();

        return Dealer::create($values + ($address ? ['province_id' => $address->parent_id, 'ward_id' => $address->id] : $this->address()) +
            ['name' => 'Dealer '.uniqid(), 'address_line' => '123 Street', 'is_active' => true, 'review_enabled' => true, 'reviews_visible' => true]);
    }

    private function admin(): User
    {
        $u = User::factory()->create(['is_active' => true]);
        $u->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));

        return $u;
    }

    private function directory(array $filters = [])
    {
        $s = app(DealerDirectoryService::class);

        return $s->query($s->filters($filters));
    }

    public function test_homepage_dealer_section_honors_settings_and_region_selection(): void
    {
        $provinceA = $this->unit('current', 'province', 'home-01');
        $wardA = $this->unit('current', 'ward', 'home-0001', $provinceA);
        $provinceB = $this->unit('current', 'province', 'home-02');
        $wardB = $this->unit('current', 'ward', 'home-0002', $provinceB);
        $featured = $this->dealer(['name' => 'Featured Home Dealer', 'slug' => 'featured-home-dealer',
            'province_id' => $provinceA->id, 'ward_id' => $wardA->id, 'is_featured' => true]);
        $other = $this->dealer(['name' => 'Regional Home Dealer', 'slug' => 'regional-home-dealer',
            'province_id' => $provinceB->id, 'ward_id' => $wardB->id, 'is_featured' => false]);
        $settings = app(SettingService::class);
        $settings->set('homepage_dealers_enabled', false, 'display', type: 'boolean');

        $this->get('/')->assertOk()->assertDontSee('data-homepage-dealers', false);

        $settings->set('homepage_dealers_enabled', true, 'display', type: 'boolean');
        $settings->set('homepage_dealers_mode', 'auto', 'display');
        $this->assertSame([$other->id], app(\App\Services\Dealer\DealerHomepageService::class)
            ->resolve(['scheme' => 'current', 'province' => $provinceB->id], app(DealerDirectoryService::class))['dealers']->pluck('id')->all());
        $this->get('/')->assertOk()->assertSee('data-homepage-dealers', false)
            ->assertSee('Featured Home Dealer')->assertDontSee('Regional Home Dealer');

        $regionalResponse = $this->get('/?scheme=current&province='.$provinceB->id);
        $this->assertSame([$other->id], $regionalResponse->viewData('homepageDealers')->pluck('id')->all());
        $regionalResponse->assertOk()->assertViewHas('homepageDealerFilters', fn (array $f) => (int) ($f['province'] ?? 0) === $provinceB->id)
            ->assertViewHas('homepageDealers', fn ($rows) => $rows->pluck('id')->all() === [$other->id]);
    }

    public function test_homepage_manual_and_featured_modes_respect_selection_and_visibility(): void
    {
        $first = $this->dealer(['name' => 'First Dealer', 'is_featured' => true, 'sort_order' => 10]);
        $second = $this->dealer(['name' => 'Second Dealer', 'is_featured' => true, 'sort_order' => 20]);
        $ordinary = $this->dealer(['name' => 'Ordinary Dealer', 'is_featured' => false]);
        $inactive = $this->dealer(['name' => 'Inactive Dealer', 'is_featured' => true, 'is_active' => false]);
        $settings = app(SettingService::class);
        $settings->set('homepage_dealers_enabled', true, 'display', type: 'boolean');
        $settings->set('homepage_dealers_limit', 12, 'display', type: 'integer');
        $homepage = app(\App\Services\Dealer\DealerHomepageService::class);
        $directory = app(DealerDirectoryService::class);

        $settings->set('homepage_dealers_mode', 'manual', 'display');
        $settings->set('homepage_dealers_manual', [$second->id, $inactive->id, $first->id], 'display', type: 'json');
        $this->assertSame([$second->id, $first->id], $homepage->resolve([], $directory)['dealers']->pluck('id')->all());

        $settings->set('homepage_dealers_mode', 'featured', 'display');
        $this->assertSame([$first->id, $second->id], $homepage->resolve([], $directory)['dealers']->pluck('id')->all());
        $this->assertNotContains($ordinary->id, $homepage->resolve([], $directory)['dealers']->pluck('id')->all());
    }

    public function test_dealer_public_pages_render_seo_metadata_without_raw_template_attributes(): void
    {
        $dealer = $this->dealer([
            'slug' => 'seo-template-regression',
            'name' => 'SEO Template Dealer',
            'seo_title' => 'SEO Dealer Title',
            'seo_description' => 'SEO dealer description for metadata regression.',
            'is_indexable' => true,
        ]);

        $detail = $this->get(route('dealers.show', $dealer->slug))->assertOk();
        $detail->assertSee('<title>SEO Dealer Title |', false)
            ->assertSee('<meta name="description" content="SEO dealer description for metadata regression.">', false)
            ->assertSee('<meta name="robots" content="index,follow">', false)
            ->assertSee('<link rel="canonical" href="'.route('dealers.show', $dealer->slug).'">', false)
            ->assertDontSee('seo_title ?')
            ->assertDontSee('seo-description=')
            ->assertDontSee('canonical="route(')
            ->assertDontSee('robots="')
            ->assertDontSee('$dealer->');

        $this->get(route('dealers.index'))->assertOk()
            ->assertDontSee('seo_title ?')
            ->assertDontSee('seo-description=')
            ->assertDontSee('canonical="route(')
            ->assertDontSee('robots="');
    }

    public function test_homepage_section_order_is_configurable_and_renders_each_section_once(): void
    {
        $settings = app(SettingService::class);
        $settings->set('homepage_dealers_enabled', true, 'display', type: 'boolean');
        $settings->set('homepage_section_order', [
            ['section' => 'hero', 'sort_order' => 10],
            ['section' => 'benefits', 'sort_order' => 20],
            ['section' => 'featured_products', 'sort_order' => 30],
            ['section' => 'dealers', 'sort_order' => 40],
            ['section' => 'brands', 'sort_order' => 50],
        ], 'display', type: 'json');

        $response = $this->get('/')->assertOk();
        $html = $response->getContent();
        $featuredPosition = strpos($html, 'data-homepage-section="featured_products"');
        $dealerPosition = strpos($html, 'data-homepage-section="dealers"');
        $brandsPosition = strpos($html, 'data-homepage-section="brands"');

        $this->assertNotFalse($featuredPosition);
        $this->assertNotFalse($dealerPosition);
        $this->assertNotFalse($brandsPosition);
        $this->assertLessThan($dealerPosition, $featuredPosition);
        $this->assertLessThan($brandsPosition, $dealerPosition);
        $this->assertSame(1, substr_count($html, 'data-homepage-section="dealers"'));
    }

    public function test_public_dealer_display_modes_render_fallbacks_without_broken_logos(): void
    {
        $dealer = $this->dealer(['slug' => 'display-modes-dealer', 'name' => 'Display Modes Dealer']);
        $brand = Brand::create(['name' => 'Display Brand', 'is_active' => true]);
        $category = ProductCategory::create(['name' => 'Display Product Line', 'is_active' => true]);
        $service = DealerService::create(['name' => 'Display Service', 'slug' => 'display-service', 'is_active' => true]);
        $dealer->brands()->attach($brand);
        $dealer->categories()->attach($category);
        $dealer->services()->attach($service);
        $settings = app(SettingService::class);

        foreach (['logo', 'badge', 'logo_name', 'text'] as $brandStyle) {
            $settings->set('dealer_brand_style', $brandStyle, 'display');
            $html = $this->get(route('dealers.show', $dealer->slug))->assertOk()->getContent();
            $this->assertStringContainsString('data-brand-style="'.$brandStyle.'"', $html);
            $this->assertStringContainsString('Display Brand', $html);
            $this->assertStringNotContainsString('src=""', $html);
        }

        foreach (['icon_badge', 'badge', 'icon_list', 'grid'] as $style) {
            $settings->set('dealer_product_line_style', $style, 'display');
            $settings->set('dealer_service_style', $style === 'icon_list' ? 'compact' : $style, 'display');
            $html = $this->get(route('dealers.show', $dealer->slug))->assertOk()->getContent();
            $this->assertStringContainsString('data-product-line-style="'.$style.'"', $html);
            $this->assertStringContainsString('Display Product Line', $html);
            $this->assertStringContainsString('Display Service', $html);
        }
    }

    public function test_settings_save_persists_navigation_category_semantics(): void
    {
        $category = ProductCategory::create(['name' => 'Navigation Save Category', 'is_active' => true]);
        $this->actingAs($this->admin());
        $settings = app(SettingService::class);
        foreach (['CONTENT_TOO_SHORT', 'MISSING_H2_H3', 'MISSING_SEO', 'MISSING_MERCHANT', 'MISSING_FAQ'] as $key) {
            $settings->set($key, 'WARN', 'ai_guard_policy');
        }
        foreach ([
            'homepage_dealers_mode' => 'auto', 'homepage_dealers_limit' => 4,
            'homepage_dealers_radius_km' => 20, 'dealer_card_style' => 'standard',
            'dealer_brand_style' => 'logo_name', 'dealer_product_line_style' => 'icon_badge',
            'dealer_service_style' => 'icon_badge', 'mail_provider' => 'smtp',
        ] as $key => $value) {
            $settings->set($key, $value, in_array($key, ['mail_provider']) ? 'mail' : 'display');
        }
        foreach (['facebook_link', 'youtube_link', 'tiktok_link'] as $key) {
            $settings->set($key, '', 'contact');
        }
        $navigation = [[
            'label' => 'Category navigation', 'link_type' => 'product_category', 'target' => '',
            'category_target' => (string) $category->id, 'custom_target' => '',
            'sort_order' => 10, 'is_active' => true, 'open_new_tab' => false,
        ]];

        $component = Livewire::test(ManageSiteSettings::class)
            ->set('data.navigation__header_primary', $navigation)
            ->set('data.site__product_scope', 'primary_category')
            ->set('data.site__primary_product_category_id', (string) $category->id)
            ->set('data.site__product_cta_type', 'product_category')
            ->set('data.site__product_cta_product_category', (string) $category->id)
            ->call('saveSettings')
            ->assertHasNoErrors();

        $saved = app(SettingService::class)->get('navigation.header_primary');
        $this->assertSame('primary_category', $settings->get('site.product_scope'));
        $this->assertSame((string) $category->id, $settings->get('site.primary_product_category_id'));
        $this->assertSame('product_category', $settings->get('site.product_cta_type'));
        $this->assertSame((string) $category->id, $settings->get('site.product_cta_product_category'));
        $this->assertSame('product_category', $saved[0]['type']);
        $this->assertSame((string) $category->id, (string) $saved[0]['category_target']);

        // Mirror the browser's nested toggle update in a later Livewire
        // request. Explicit false must survive schema dehydration and reload.
        $component->set('data.navigation__header_primary.0.is_active', false)
            ->call('saveSettings')
            ->assertHasNoErrors();
        $this->assertFalse($settings->get('navigation.header_primary')[0]['is_active']);
        Livewire::test(ManageSiteSettings::class)
            ->assertSet('data.navigation__header_primary.0.is_active', false);
    }

    public function test_pinned_master_dry_run_then_idempotent_import_preserves_codes_and_custom_seo(): void
    {
        $this->artisan('dealer:import-locations')->assertSuccessful();
        $this->assertSame(0, AdministrativeUnit::count());
        $this->artisan('dealer:import-locations', ['--apply' => true])->assertSuccessful();
        $this->assertSame(14149, AdministrativeUnit::count());
        $province = AdministrativeUnit::where('scheme', 'current')->where('code', '01')->sole();
        $province->update(['introduction' => 'Local introduction', 'is_indexable' => true]);
        $this->assertSame('01', $province->code);
        $this->artisan('dealer:import-locations', ['--apply' => true])->assertSuccessful();
        $this->assertSame(14149, AdministrativeUnit::count());
        $this->assertSame('Local introduction', $province->fresh()->introduction);
        $this->assertSame(0, Dealer::count());
        $this->artisan('dealer:seed-services')->assertSuccessful();
        $this->assertSame(0, DealerService::count());
        $this->artisan('dealer:seed-services', ['--apply' => true])->assertSuccessful();
        $this->artisan('dealer:seed-services', ['--apply' => true])->assertSuccessful();
        $this->assertSame(11, DealerService::count());
    }

    public function test_wrong_master_version_is_rejected_before_writes(): void
    {
        config(['dealers.current_version' => 'invalid']);
        $this->expectException(\RuntimeException::class);
        try {
            $this->artisan('dealer:import-locations', ['--apply' => true]);
        } finally {
            $this->assertSame(0, AdministrativeUnit::count());
        }
    }

    public function test_addresses_are_independent_and_invalid_hierarchy_is_rejected(): void
    {
        $d = $this->dealer();
        $lp = $this->unit('legacy', 'province', '01');
        $ld = $this->unit('legacy', 'district', '001', $lp);
        $lw = $this->unit('legacy', 'ward', '00001', $ld);
        $d->update(['legacy_province_id' => $lp->id, 'legacy_district_id' => $ld->id, 'legacy_ward_id' => $lw->id]);
        $this->assertSame($d->id, $this->directory(['scheme' => 'legacy', 'province' => $lp->id, 'district' => $ld->id, 'ward' => $lw->id])->sole()->id);
        $p = $this->unit('current', 'province', '02');
        $w = $this->unit('current', 'ward', '00002', $p);
        $this->expectException(ValidationException::class);
        $d->update(['ward_id' => $w->id]);
    }

    public function test_invalid_coordinate_pair_and_non_https_map_url_are_rejected(): void
    {
        $d = $this->dealer();
        foreach ([['latitude' => 10], ['latitude' => 91, 'longitude' => 20], ['latitude' => 0, 'longitude' => 181],
            ['google_maps_url' => 'https://localhost/maps'], ['website' => 'javascript:alert(1)']] as $input) {
            try {
                $d->fill($input)->save();
                $this->fail('Invalid dealer accepted');
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors());
                $d->refresh();
            }
        }
        $d->update(['latitude' => 0, 'longitude' => 0]);
        $this->assertSame('0.0000000', $d->fresh()->latitude);
    }

    public function test_directory_combines_region_brand_category_service_and_excludes_inactive_deleted(): void
    {
        $d = $this->dealer(['name' => 'Matching dealer']);
        $b = Brand::factory()->create(['is_active' => true]);
        $c = ProductCategory::factory()->create(['is_active' => true]);
        $s = DealerService::create(['name' => 'Install', 'slug' => 'install', 'is_active' => true]);
        $d->brands()->attach($b);
        $d->categories()->attach($c);
        $d->services()->attach($s);
        $this->dealer(['is_active' => false]);
        $this->dealer()->delete();
        $this->dealer(['name' => 'Other dealer']);
        $f = ['province' => $d->province_id, 'ward' => $d->ward_id, 'brand' => $b->id, 'category' => $c->id, 'service' => $s->id, 'q' => 'Matching'];
        $this->assertSame([$d->id], $this->directory($f)->pluck('dealers.id')->all());
        $this->assertSame(2, app(DealerDirectoryService::class)->regions(['scheme' => 'current'])->sole()->dealer_count);
        $this->assertSame(2, $this->directory()->count());
        $this->get('/dai-ly?'.http_build_query($f))->assertOk()->assertSee('Matching dealer')->assertDontSee('Other dealer');
    }

    public function test_literal_search_and_invalid_parent_input_cannot_broaden_results(): void
    {
        $this->dealer(['name' => '100% dealer']);
        $this->dealer(['name' => 'Other']);
        $this->assertSame(1, $this->directory(['q' => '%'])->count());
        $this->assertSame(0, $this->directory(['q' => "' OR 1=1 --"])->count());
        $this->getJson('/dai-ly?ward=99999')->assertUnprocessable();
        $ward = AdministrativeUnit::where('kind', 'ward')->sole();
        $this->getJson('/dai-ly?ward='.$ward->id)->assertUnprocessable();
    }

    public function test_paginated_sorts_are_stable_and_query_count_stays_bounded(): void
    {
        foreach (range(1, 25) as $i) {
            $this->dealer(['name' => 'Same name']);
        }
        DB::enableQueryLog();
        $rows = $this->directory(['sort' => 'name'])->paginate(12);
        foreach ($rows as $d) {
            $d->full_address;
            $d->brands->count();
            $d->services->count();
            $d->review_count;
        }
        $this->assertLessThanOrEqual(12, count(DB::getQueryLog()));
        DB::disableQueryLog();
        $this->assertSame(range(1, 12), $rows->modelKeys());
        $this->assertSame(3, $rows->lastPage());
        $this->get('/dai-ly?sort=name')->assertOk()->assertSee('Pagination Navigation', false);
    }

    public function test_haversine_distance_radius_ties_missing_coordinates_and_antimeridian(): void
    {
        $a = $this->dealer(['latitude' => 0, 'longitude' => 0]);
        $b = $this->dealer(['latitude' => 0, 'longitude' => 0]);
        $c = $this->dealer(['latitude' => 0, 'longitude' => 0.05]);
        $this->dealer();
        $geo = app(DealerLocationQuery::class);
        $rows = $geo->apply($this->directory(), 0, 0, 10)->get();
        $this->assertSame([$a->id, $b->id, $c->id], $rows->modelKeys());
        $this->assertEqualsWithDelta(5.56, $rows[2]->distance_km, 0.02);
        $this->assertSame(2, $geo->apply($this->directory(), 0, 0, 5)->count());
        $across = $this->dealer(['latitude' => 0, 'longitude' => -179.99]);
        $this->assertSame([$across->id], $geo->apply($this->directory(), 0, 179.99, 5)->get()->modelKeys());
        $pole = $this->dealer(['latitude' => 89.99, 'longitude' => -179]);
        $this->assertLessThan(0.05, $geo->apply($this->directory()->whereKey($pole->id), 89.99, 179, 5)->sole()->distance_km);
        $this->assertEqualsWithDelta(20015.09, $geo->apply($this->directory()->whereKey($a->id), 0, 180, null)->sole()->distance_km, 0.02);
    }

    public function test_nearby_post_keeps_position_out_of_response_and_uses_filters(): void
    {
        $d = $this->dealer(['latitude' => 10, 'longitude' => 106]);
        $this->dealer(['latitude' => 10.2, 'longitude' => 106]);
        $this->dealer();
        $response = $this->postJson('/dai-ly/gan-toi', ['latitude' => 10.1234567, 'longitude' => 106.0123456, 'radius' => 20, 'q' => $d->name]);
        $response->assertOk()->assertJsonPath('count', 1)->assertJsonPath('markers.0.id', $d->id)
            ->assertHeader('Cache-Control', 'no-store, private')->assertDontSee('10.1234567', false)->assertDontSee('106.0123456', false);
        $this->postJson('/dai-ly/gan-toi', ['latitude' => 91, 'longitude' => 106])->assertUnprocessable();
        $this->postJson('/dai-ly/gan-toi', ['latitude' => 10, 'longitude' => 106, 'radius' => '20 OR 1=1'])->assertUnprocessable();
        $this->get('/dai-ly')->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(self)');
    }

    public function test_guest_review_cannot_forge_moderation_or_user_and_publication_is_explicit(): void
    {
        $d = $this->dealer();
        $this->post(route('dealers.review.store', $d->slug), [
            'customer_name' => 'Guest', 'rating' => 5, 'content' => 'Very good installation service', 'status' => 'approved',
            'is_visible' => false, 'approved_by' => 999, 'user_id' => 999,
        ])->assertRedirect();
        $r = DealerReview::sole();
        $this->assertSame('pending', $r->status);
        $this->assertNull($r->approved_by);
        $this->assertNull($r->user_id);
        $this->assertSame(0, $this->directory()->sole()->review_count);
        $admin = $this->admin();
        $service = app(DealerReviewService::class);
        $service->moderate($r, 'approve', $admin);
        $d = $this->directory()->sole();
        $this->assertSame(1, $d->review_count);
        $this->assertEquals(5, $d->average_rating);
        $this->assertSame($admin->id, $r->fresh()->approved_by);
        $this->get(route('dealers.show', $d->slug))->assertOk()->assertSee('Very good installation service')->assertSee('aggregateRating', false);
        $service->moderate($r, 'hide', $admin);
        $this->assertSame(0, $this->directory()->sole()->review_count);
        $service->moderate($r, 'show', $admin);
        $this->assertSame(1, $this->directory()->sole()->review_count);
        $service->moderate($r, 'reject', $admin);
        $this->assertSame(0, $this->directory()->sole()->review_count);
        $this->assertSame($admin->id, $r->fresh()->moderated_by);
    }

    public function test_review_toggles_soft_delete_aggregates_and_guest_rate_limit(): void
    {
        $d = $this->dealer();
        $service = app(DealerReviewService::class);
        $r = $service->submit($d, ['customer_name' => 'Guest', 'rating' => 4, 'content' => 'A helpful and friendly dealer'], null, '192.0.2.30');
        $service->moderate($r, 'approve', $this->admin());
        $d->update(['reviews_visible' => false]);
        $this->assertSame(0, $this->directory(['sort' => 'rating'])->sole()->review_count);
        $this->get(route('dealers.show', $d->slug))->assertDontSee('A helpful and friendly dealer')->assertDontSee('aggregateRating', false)->assertSee('Gửi đánh giá');
        $d->update(['reviews_visible' => true, 'review_enabled' => false]);
        $this->get(route('dealers.show', $d->slug))->assertSee('A helpful and friendly dealer')->assertDontSee('Gửi đánh giá');
        $this->post(route('dealers.review.store', $d->slug), ['rating' => 5])->assertNotFound();
        $r->delete();
        $this->assertSame(0, $this->directory()->sole()->review_count);
        $d->update(['review_enabled' => true]);
        $payload = ['customer_name' => 'Guest', 'rating' => 4, 'content' => 'A helpful and friendly dealer'];
        foreach (range(1, 5) as $i) {
            $this->post(route('dealers.review.store', $d->slug), $payload)->assertRedirect();
        }
        $this->post(route('dealers.review.store', $d->slug), $payload)->assertStatus(429);
    }

    public function test_honeypot_empty_html_and_xss_payloads_do_not_publish_unsanitized_content(): void
    {
        $d = $this->dealer();
        $url = route('dealers.review.store', $d->slug);
        $this->postJson($url, ['customer_name' => 'Guest', 'rating' => 5, 'content' => '<script></script>'])->assertUnprocessable();
        $this->postJson($url, ['customer_name' => 'Guest', 'rating' => 5, 'content' => 'Good dealer with nice staff', 'honeypot' => 'robot'])->assertUnprocessable();
        $this->post($url, ['customer_name' => '<b>Guest</b>', 'rating' => 5, 'content' => '<img src=x onerror=alert(1)>A helpful and friendly dealer'])->assertRedirect();
        $r = DealerReview::sole();
        $this->assertSame('Guest', $r->customer_name);
        $this->assertStringNotContainsString('<img', $r->content);
    }

    public function test_server_permissions_block_resource_and_review_action_even_for_forged_calls(): void
    {
        $d = $this->dealer();
        $viewer = User::factory()->create(['is_active' => true]);
        $this->actingAs($viewer)->get('/admin/dealers/create')->assertForbidden();
        $this->get('/admin/dealers/'.$d->id.'/edit')->assertForbidden();
        $r = DealerReview::create(['dealer_id' => $d->id, 'customer_name' => 'Guest', 'rating' => 4, 'content' => 'Service review', 'status' => 'pending']);
        try {
            app(DealerReviewService::class)->moderate($r, 'approve', $viewer);
            $this->fail('Permission bypass');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $this->assertSame('pending', $r->fresh()->status);
    }

    public function test_super_admin_can_create_edit_relations_and_dependent_parent_resets(): void
    {
        $address = $this->address();
        $second = $this->unit('current', 'province', '02');
        $b = Brand::factory()->create(['is_active' => true]);
        $c = ProductCategory::factory()->create(['is_active' => true]);
        $s = DealerService::create(['name' => 'Install', 'slug' => 'install']);
        $this->actingAs($this->admin());
        Livewire::test(CreateDealer::class)->fillForm([
            'name' => 'Created in Admin', 'address_line' => '123 Street', ...$address, 'brands' => [$b->id], 'categories' => [$c->id],
            'services' => [$s->id], 'is_active' => true, 'latitude' => 10, 'longitude' => 106,
        ])->call('create')->assertHasNoFormErrors();
        $d = Dealer::sole();
        $this->assertSame([$b->id], $d->brands->modelKeys());
        $this->assertSame([$c->id], $d->categories->modelKeys());
        $this->assertSame([$s->id], $d->services->modelKeys());
        Livewire::test(EditDealer::class, ['record' => $d->id])
            ->assertFormSet(['name' => 'Created in Admin', 'province_id' => $address['province_id'], 'ward_id' => $address['ward_id']])
            ->fillForm(['name' => 'Edited in Admin'])->call('save')->assertHasNoFormErrors();
        $this->assertSame('Edited in Admin', $d->fresh()->name);
        Livewire::test(EditDealer::class, ['record' => $d->id])
            ->fillForm(['province_id' => $second->id])->assertFormSet(['ward_id' => null]);
    }

    public function test_region_seo_and_sitemap_only_publish_useful_opted_in_pages(): void
    {
        $d = $this->dealer(['latitude' => 10, 'longitude' => 106]);
        $province = $d->province;
        $url = route('dealers.region', $province->slug);
        $this->get($url)->assertOk()->assertSee('noindex,follow', false);
        $province->update(['introduction' => 'Dealers serving the province', 'is_indexable' => true]);
        $this->get($url)->assertOk()->assertSee('content="index,follow"', false);
        $this->get($url.'?brand=')->assertOk();
        $this->get($url.'?q=Dealer')->assertOk()->assertSee('noindex,follow', false);
        $this->get('/sitemap-dealers.xml')->assertOk()->assertSee($province->slug)->assertSee($d->slug);
        $d->update(['is_active' => false]);
        $this->get('/sitemap-dealers.xml')->assertDontSee($d->slug)->assertDontSee($province->slug);
        $this->get(route('dealers.show', $d->slug))->assertNotFound();
    }

    public function test_parser_supports_google_osm_and_rejects_untrusted_urls(): void
    {
        $parser = app(MapCoordinateParser::class);
        foreach (['https://www.google.com/maps/@10.25,106.5,15z', 'https://maps.google.com/?q=10.25,106.5',
            'https://www.openstreetmap.org/#map=15/10.25/106.5'] as $url) {
            $this->assertSame(['latitude' => 10.25, 'longitude' => 106.5], $parser->parse($url));
        }
        $this->assertNull($parser->parse('https://www.google.com/maps/@91,106,15z'));
        foreach (['http://www.google.com/maps', 'https://localhost/maps', 'https://127.0.0.1', 'https://[::1]',
            'https://www.google.com.evil.test/maps', 'https://user:pass@maps.google.com/maps', 'https://www.google.com:444/maps'] as $url) {
            try {
                $parser->parse($url);
                $this->fail('Untrusted URL accepted');
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors());
            }
        }
    }

    public function test_invalid_nearby_form_does_not_flash_coordinates_and_query_errors_are_redacted(): void
    {
        $d = $this->dealer(['latitude' => 10, 'longitude' => 106]);
        $this->post('/dai-ly/gan-toi', ['latitude' => 10.1234567, 'longitude' => 200])
            ->assertUnprocessable()->assertSessionMissing('_old_input')
            ->assertHeader('Cache-Control', 'no-store, private');
        Log::spy();
        DB::connection()->beforeExecuting(function ($sql) {
            if (str_contains($sql, '12742')) {
                throw new \RuntimeException('Sensitive bindings: 10.1234567');
            }
        });
        $this->postJson('/dai-ly/gan-toi', ['latitude' => 10.1234567, 'longitude' => 106])
            ->assertStatus(503)->assertDontSee('10.1234567', false);
        Log::shouldHaveReceived('warning')->once()->with('dealer_nearby_failed', ['exception_type' => \RuntimeException::class]);
    }

    public function test_additive_upgrade_and_reverse_migration_preserve_existing_product_data(): void
    {
        $product = Product::factory()->create();
        $this->dealer();
        $migration = require database_path('migrations/2026_11_19_000001_create_dealer_directory.php');
        $migration->down();
        $this->assertFalse(Schema::hasTable('dealers'));
        $this->assertTrue($product->fresh()->exists);
        $migration->up();
        $this->assertTrue(Schema::hasTable('dealer_reviews'));
        $this->assertTrue($product->fresh()->exists);
    }

    public function test_master_rejects_bad_checksum_duplicate_codes_and_wrong_parent_without_partial_import(): void
    {
        $original = resource_path();
        $temporary = storage_path('framework/testing/dealer-master-'.bin2hex(random_bytes(6)));
        mkdir($temporary.'/geography', 0775, true);
        try {
            foreach (['current.json', 'legacy.json', 'manifest.json'] as $file) {
                copy($original.'/geography/'.$file, $temporary.'/geography/'.$file);
            }
            config(['dealers.master_path'=>$temporary.'/geography']);
            $originalRows = json_decode(file_get_contents($temporary.'/geography/current.json'), true);
            foreach (['checksum', 'duplicate', 'parent'] as $mode) {
                $rows = $originalRows;
                if ($mode === 'duplicate') {
                    $rows[] = $rows[0];
                }
                if ($mode === 'parent') {
                    $rows[1]['parent_code'] = 'not-found';
                }
                $bytes = json_encode($rows, JSON_UNESCAPED_UNICODE);
                file_put_contents($temporary.'/geography/current.json', $bytes);
                $manifest = json_decode(file_get_contents($original.'/geography/manifest.json'), true);
                $manifest['current']['sha256'] = $mode === 'checksum' ? str_repeat('0',64) : hash('sha256', $bytes);
                file_put_contents($temporary.'/geography/manifest.json',json_encode($manifest));
                try {
                    $this->artisan('dealer:import-locations',['--apply' => true]);
                    $this->fail('Invalid master accepted');
                } catch (\RuntimeException $e) {
                    $this->assertSame(0,AdministrativeUnit::count());
                }
            }
        } finally {
            config(['dealers.master_path'=>$original.'/geography']);
            foreach (['current.json', 'legacy.json', 'manifest.json'] as $file) {
                unlink($temporary.'/geography/'.$file);
            }
            rmdir($temporary.'/geography');
            rmdir($temporary);
        }
    }
}
