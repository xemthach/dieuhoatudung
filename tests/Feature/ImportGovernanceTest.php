<?php

namespace Tests\Feature;

use App\Models\ImportGovernanceAudit;
use App\Models\User;
use App\Filament\Pages\ImportGovernancePage;
use App\Services\DataTransfer\ImportGovernanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Spatie\Permission\Models\Role;
use Livewire\Livewire;
use Tests\TestCase;

class ImportGovernanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_policy_change_requires_reason_and_writes_append_only_audit(): void
    {
        $role = Role::create(['name'=>'super_admin','guard_name'=>'web']);
        $actor = User::factory()->create(); $actor->assignRole($role);
        $service = app(ImportGovernanceService::class);
        $service->change(ImportGovernanceService::DETACH_CATALOG_LINEAGE, 'ON', 'Controlled transfer test', $actor);
        $this->assertTrue($service->catalogDetachEnabled());
        $this->assertDatabaseHas('import_governance_audits', ['policy_key'=>ImportGovernanceService::DETACH_CATALOG_LINEAGE,'old_mode'=>'OFF','new_mode'=>'ON','changed_by'=>$actor->id]);
        $this->assertSame('Controlled transfer test', ImportGovernanceAudit::firstOrFail()->reason);
    }

    public function test_system_integrity_policy_cannot_be_disabled(): void
    {
        $role = Role::create(['name'=>'super_admin','guard_name'=>'web']);
        $actor = User::factory()->create(); $actor->assignRole($role);
        $this->expectException(InvalidArgumentException::class);
        app(ImportGovernanceService::class)->change('integrity.manifest', 'OFF', 'must fail', $actor);
    }

    public function test_admin_page_manages_business_policy_and_displays_locked_rules_and_audit(): void
    {
        $role = Role::create(['name'=>'super_admin','guard_name'=>'web']);
        $actor = User::factory()->create();
        $actor->assignRole($role);
        $this->actingAs($actor);

        Livewire::test(ImportGovernancePage::class)
            ->assertOk()
            ->assertSee('product_transfer.detach_catalog_lineage')
            ->assertSee('LOCKED')
            ->set('modes', ['product_transfer__detach_catalog_lineage' => 'ON'])
            ->set('reasons', ['product_transfer__detach_catalog_lineage' => 'Browser governance certification'])
            ->call('savePolicy', ImportGovernanceService::DETACH_CATALOG_LINEAGE)
            ->assertHasNoErrors()
            ->assertSee('Browser governance certification');

        $this->assertDatabaseHas('import_governance_audits', [
            'policy_key' => ImportGovernanceService::DETACH_CATALOG_LINEAGE,
            'new_mode' => 'ON',
            'changed_by' => $actor->id,
        ]);
    }

    public function test_required_permission_inventory_is_configured(): void
    {
        foreach ([
            'import_governance.view', 'import_governance.change', 'product_transfer.run',
            'product_transfer.detach_catalog_lineage', 'product_import.run', 'catalog_import.run',
            'system_restore.run', 'bulk_import.run', 'bulk_update.run', 'bulk_retry.run',
        ] as $permission) {
            [$module, $action] = explode('.', $permission, 2);
            $this->assertArrayHasKey($module, config('permissions'));
            $this->assertArrayHasKey($action, config("permissions.{$module}.permissions"));
        }
    }
}
