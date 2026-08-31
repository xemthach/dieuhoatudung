<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Models\User;
use App\Filament\Pages\ManageSiteSettings;
use App\Services\AI\AiGuardPolicy;
use App\Services\Settings\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AiGuardPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_editorial_guards_default_to_warning_and_accept_admin_modes(): void
    {
        $policy = app(AiGuardPolicy::class);
        $this->assertSame('WARN', $policy->evaluate('content_too_short:459/800')['effect']);

        app(SettingService::class)->set('CONTENT_TOO_SHORT', 'IGNORE', 'ai_guard_policy');
        $this->assertSame('IGNORE', $policy->evaluate('content_too_short:459/800')['effect']);

        app(SettingService::class)->set('CONTENT_TOO_SHORT', 'BLOCK', 'ai_guard_policy');
        $this->assertSame('BLOCK', $policy->evaluate('content_too_short:459/800')['effect']);
    }

    public function test_locked_safety_guard_cannot_be_weakened_by_persisted_setting(): void
    {
        SiteSetting::create(['group' => 'ai_guard_policy', 'key' => 'AMBIGUOUS_CAPACITY_CLAIM', 'value' => 'IGNORE', 'type' => 'text']);
        $result = app(AiGuardPolicy::class)->evaluate('ambiguous_capacity_claim:9,900 BTU');

        $this->assertSame('BLOCK', $result['effect']);
        $this->assertFalse($result['overrideable']);
        $this->assertSame('SYSTEM', $result['source']);
    }

    public function test_corrupt_mode_falls_back_safely(): void
    {
        app(SettingService::class)->set('MISSING_FAQ', 'INVALID', 'ai_guard_policy');
        $this->assertSame('WARN', app(AiGuardPolicy::class)->evaluate('missing_faq')['effect']);
    }

    public function test_policy_snapshot_is_stable_and_changes_version_when_admin_policy_changes(): void
    {
        $policy = app(AiGuardPolicy::class);
        $before = $policy->version();
        $this->assertSame('WARN', $policy->snapshot()['CONTENT_TOO_SHORT']);

        app(SettingService::class)->set('CONTENT_TOO_SHORT', 'IGNORE', 'ai_guard_policy');

        $this->assertSame('IGNORE', $policy->snapshot()['CONTENT_TOO_SHORT']);
        $this->assertNotSame($before, $policy->version());
    }

    public function test_policy_save_is_denied_server_side_without_settings_edit_permission(): void
    {
        Permission::firstOrCreate(['name' => 'settings.view', 'guard_name' => 'web']);
        $viewer = User::factory()->create(['is_active' => true]);
        $viewer->givePermissionTo('settings.view');
        $this->actingAs($viewer);

        Livewire::test(ManageSiteSettings::class)
            ->set('data.ai_guard_policy__CONTENT_TOO_SHORT', 'IGNORE')
            ->call('saveSettings')
            ->assertForbidden();

        $this->assertDatabaseMissing('site_settings', [
            'group' => 'ai_guard_policy',
            'key' => 'CONTENT_TOO_SHORT',
            'value' => 'IGNORE',
        ]);
    }
}
