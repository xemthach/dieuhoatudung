<?php

namespace Tests\Feature;

use App\Filament\Pages\DataTransferPage;
use App\Filament\Resources\AiProductJobs\AiProductJobResource;
use App\Filament\Resources\Products\ProductResource;
use App\Services\Dashboard\DashboardStatsService;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PermissionGovernanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_user_without_products_view_cannot_access_product_resource(): void
    {
        $this->actingAs(UserFactory::new()->create(['is_active' => true]));

        $this->assertFalse(ProductResource::canViewAny());
    }

    public function test_product_ai_jobs_require_ai_generate_permission(): void
    {
        $user = UserFactory::new()->create(['is_active' => true]);
        $this->actingAs($user);

        $this->assertFalse(AiProductJobResource::canViewAny());

        Permission::firstOrCreate(['name' => 'product.ai_generate', 'guard_name' => 'web']);
        $user->givePermissionTo('product.ai_generate');

        $this->assertTrue(AiProductJobResource::canViewAny());
    }

    public function test_data_transfer_page_needs_import_or_export_permission(): void
    {
        $user = UserFactory::new()->create(['is_active' => true]);
        $this->actingAs($user);

        $this->assertFalse(DataTransferPage::canAccess());

        Permission::firstOrCreate(['name' => 'product.export', 'guard_name' => 'web']);
        $user->givePermissionTo('product.export');

        $this->assertTrue(DataTransferPage::canAccess());
    }

    public function test_dashboard_stats_do_not_query_product_data_without_permission(): void
    {
        $this->actingAs(UserFactory::new()->create(['is_active' => true]));

        DB::enableQueryLog();

        $stats = app(DashboardStatsService::class)->getProductStats();

        $this->assertSame(['total' => 0, 'missing_seo' => 0, 'missing_image' => 0, 'on_sale' => 0], $stats);
        $this->assertFalse(collect(DB::getQueryLog())->contains(
            fn (array $query) => str_contains(strtolower($query['query'] ?? ''), 'from "products"')
                || str_contains(strtolower($query['query'] ?? ''), 'from `products`')
        ));
    }

    public function test_super_admin_bypasses_resource_permissions(): void
    {
        $user = UserFactory::new()->create(['is_active' => true]);
        $role = Role::create(['name' => 'super_admin', 'guard_name' => 'web']);
        $user->assignRole($role);

        $this->actingAs($user);

        $this->assertTrue(ProductResource::canViewAny());
        $this->assertTrue(AiProductJobResource::canViewAny());
    }
}
