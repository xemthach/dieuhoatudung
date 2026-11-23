<?php

namespace Tests\Feature;

use App\Models\AdministrativeUnit;
use App\Models\Dealer;
use App\Models\DealerReview;
use App\Services\Dealer\DealerHomepageService;
use App\Services\Settings\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComprehensiveUxTest extends TestCase
{
    use RefreshDatabase;

    public function test_review_json_is_pending_private_and_validation_preserves_data(): void
    {
        $province = AdministrativeUnit::create(['scheme'=>'current', 'dataset_version'=>config('dealers.current_version'), 'kind'=>'province', 'code'=>'01', 'name'=>'Province', 'full_name'=>'Province', 'slug'=>'province']);
        $ward = AdministrativeUnit::create(['scheme'=>'current', 'dataset_version'=>config('dealers.current_version'), 'kind'=>'ward', 'code'=>'00001', 'name'=>'Ward', 'full_name'=>'Ward', 'slug'=>'ward', 'parent_id'=>$province->id]);
        $dealer = Dealer::create(['name'=>'Test dealer', 'address_line'=>'1 Street', 'province_id'=>$province->id, 'ward_id'=>$ward->id, 'is_active'=>true, 'review_enabled'=>true, 'reviews_visible'=>true]);
        $url = route('dealers.review.store', $dealer->slug);
        $this->postJson($url, ['customer_name'=>'Customer', 'rating'=>0, 'content'=>'Good installation service'])->assertUnprocessable();
        $this->assertSame(0, DealerReview::count());
        $this->postJson($url, ['customer_name'=>'Customer', 'rating'=>5, 'content'=>'Good installation service', 'customer_email'=>'private@example.test', 'customer_phone'=>'0123456789', 'status'=>'approved'])
            ->assertCreated()->assertJsonPath('status', 'pending')->assertDontSee('private@example.test')->assertDontSee('0123456789');
        $this->assertSame('pending', DealerReview::sole()->status);
        $this->get(route('dealers.show', $dealer->slug))->assertOk()->assertDontSee('private@example.test')->assertSee('data-dealer-review-form', false);
    }

    public function test_disabled_sections_stay_disabled_and_legacy_order_is_preserved(): void
    {
        app(SettingService::class)->set('homepage_section_order', [
            ['section'=>'brands', 'sort_order'=>10, 'enabled'=>false],
            ['section'=>'cta', 'sort_order'=>20],
            ['section'=>'dealers', 'sort_order'=>30, 'enabled'=>false],
        ], 'display');
        $order = app(DealerHomepageService::class)->sectionOrder();
        $this->assertSame('cta', $order[0]);
        $this->assertNotContains('brands', $order);
        $this->assertNotContains('dealers', $order);
        $this->get(route('home'))->assertOk()->assertDontSee('data-homepage-section="brands"', false);
    }

    public function test_pdg_health_reports_missing_evidence_without_writes(): void
    {
        config(['catalog_evidence.coverage_path'=>storage_path('framework/testing/does-not-exist.json')]);
        $this->artisan('products:governance-health', ['--json'=>true])->expectsOutputToContain('EVIDENCE_MISSING')->assertExitCode(1);
        $actor = \App\Models\User::factory()->create(['is_active'=>true]);
        $actor->assignRole(\Spatie\Permission\Models\Role::firstOrCreate(['name'=>'super_admin', 'guard_name'=>'web']));
        $this->actingAs($actor)->get('/admin/product-data-governance')->assertOk()->assertSee('EVIDENCE_MISSING');
    }

    public function test_settings_save_persists_disabled_sections_and_drag_order(): void
    {
        $this->seed(\Database\Seeders\SiteSettingSeeder::class);
        $actor = \App\Models\User::factory()->create(['is_active'=>true]);
        $actor->assignRole(\Spatie\Permission\Models\Role::firstOrCreate(['name'=>'super_admin', 'guard_name'=>'web']));
        $this->actingAs($actor);
        $rows = app(\App\Services\Navigation\HomepageLayout::class)->rows();
        $rows[0]['enabled'] = false;
        $rows = array_reverse($rows);
        \Livewire\Livewire::test(\App\Filament\Pages\ManageSiteSettings::class)
            ->set('data.display__homepage_section_order', $rows)->call('saveSettings')->assertHasNoErrors();
        $saved = app(SettingService::class)->get('display.homepage_section_order');
        $this->assertSame('cta', $saved[0]['section']);
        $this->assertFalse(collect($saved)->firstWhere('section', 'hero')['enabled']);
    }
}
