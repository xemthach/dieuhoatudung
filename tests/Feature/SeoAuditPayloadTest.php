<?php

namespace Tests\Feature;

use App\Filament\Pages\SeoAudit;
use App\Models\Product;
use App\Services\Seo\SeoAuditService;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SeoAuditPayloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        app(SeoAuditService::class)->clearCache();
    }

    public function test_seo_audit_limits_public_livewire_payload_rows(): void
    {
        $this->actingAsSeoAuditUser();
        Product::factory()->count(80)->create([
            'seo_title' => null,
            'seo_description' => null,
            'main_image' => null,
            'short_description' => null,
            'long_description' => null,
            'specs_json' => null,
        ]);

        $component = Livewire::test(SeoAudit::class);

        $this->assertGreaterThan(50, $component->get('totalFilteredGroups'));
        $this->assertSame(50, $component->get('displayedGroups'));
        $this->assertCount(50, $component->get('groupedIssues'));
        $serializedVisibleState = json_encode([
            'groupedIssues' => $component->get('groupedIssues'),
            'totalFilteredGroups' => $component->get('totalFilteredGroups'),
            'displayedGroups' => $component->get('displayedGroups'),
            'totalIssues' => $component->get('totalIssues'),
        ], JSON_UNESCAPED_UNICODE);

        $this->assertLessThan(1024 * 1024, strlen((string) $serializedVisibleState));
    }

    private function actingAsSeoAuditUser(): void
    {
        $user = UserFactory::new()->create(['is_active' => true]);
        Permission::firstOrCreate(['name' => 'seo_audit.view', 'guard_name' => 'web']);
        $user->givePermissionTo('seo_audit.view');

        $this->actingAs($user);
    }
}
