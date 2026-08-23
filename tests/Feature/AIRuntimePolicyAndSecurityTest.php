<?php

namespace Tests\Feature;

use App\Filament\Pages\AIRuntimeSecurityProbe;
use App\Models\User;
use App\Services\AI\AIRuntimePolicyService;
use App\Services\Product\AIProductDraftApplyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AIRuntimePolicyAndSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthorized_probe_actions_are_denied_before_any_fixture_mutation(): void
    {
        $generator = User::factory()->create();
        Permission::findOrCreate('bulk_ai_view', 'web');
        Permission::findOrCreate('bulk_ai_generate', 'web');
        $generator->givePermissionTo(['bulk_ai_view', 'bulk_ai_generate']);

        Livewire::actingAs($generator)
            ->test(AIRuntimeSecurityProbe::class)
            ->call('probeApprove')
            ->call('probeApply')
            ->call('probeRollback')
            ->assertSet('results.approve.result', 'DENIED')
            ->assertSet('results.apply.result', 'DENIED')
            ->assertSet('results.rollback.result', 'DENIED');
    }

    public function test_policy_widget_requires_view_permission_and_uses_runtime_source(): void
    {
        $user = User::factory()->create();
        Permission::findOrCreate('bulk_ai_view', 'web');
        $user->givePermissionTo('bulk_ai_view');
        config()->set('ai.production.model', 'test-pinned-model');

        $this->actingAs($user);
        $this->assertTrue(AIRuntimeSecurityProbe::canAccess());
        $snapshot = app(AIRuntimePolicyService::class)->snapshot();
        $this->assertSame('test-pinned-model', $snapshot['model']);
        $this->assertArrayNotHasKey('api_key', $snapshot);
        $this->assertArrayNotHasKey('authorization', $snapshot);
    }
}
