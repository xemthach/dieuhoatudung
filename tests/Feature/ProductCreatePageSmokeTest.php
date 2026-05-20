<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ProductCreatePageSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_create_product_page_renders_without_helpertext_crash(): void
    {
        $this->actingAsProductManager();

        $response = $this->get('/admin/products/create');

        $response->assertSuccessful();
    }

    private function actingAsProductManager(): User
    {
        $user = UserFactory::new()->create(['is_active' => true]);

        foreach (['product.view', 'product.create'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $user->givePermissionTo(['product.view', 'product.create']);
        $this->actingAs($user);

        return $user;
    }
}
