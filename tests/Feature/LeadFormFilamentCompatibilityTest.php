<?php

namespace Tests\Feature;

use App\Filament\Resources\Leads\Pages\EditLead;
use App\Models\Lead;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class LeadFormFilamentCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_authorized_operator_can_render_lead_edit_form_with_suffix_actions(): void
    {
        $user = UserFactory::new()->create(['is_active' => true]);

        foreach (['lead.view', 'lead.edit'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $user->givePermissionTo(['lead.view', 'lead.edit']);

        $lead = Lead::query()->create([
            'full_name' => 'Lead compatibility fixture',
            'phone' => '0900000000',
            'lead_type' => 'consultation',
            'status' => 'new',
        ]);

        $this->actingAs($user);

        Livewire::test(EditLead::class, ['record' => $lead->getRouteKey()])
            ->assertOk()
            ->assertSee('Lead compatibility fixture');
    }
}
