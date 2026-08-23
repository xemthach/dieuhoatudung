<?php

namespace Tests\Feature;

use App\Filament\Pages\ImportPreviewPage;
use App\Models\DataImportJob;
use App\Models\Product;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class Phase8SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_headers_are_present_on_web_response(): void
    {
        $response = $this->get('/robots.txt');

        $response->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    }

    public function test_unauthenticated_admin_requests_redirect_to_filament_login(): void
    {
        $this->get(route('admin.products.ai-status'))
            ->assertRedirect(route('filament.admin.auth.login'));
    }

    public function test_import_preview_requires_import_permission(): void
    {
        $user = UserFactory::new()->create(['is_active' => true]);
        $this->actingAs($user);

        $this->assertFalse(ImportPreviewPage::canAccess());
    }

    public function test_import_preview_cannot_be_used_to_access_another_users_job(): void
    {
        $owner = UserFactory::new()->create(['is_active' => true]);
        $viewer = UserFactory::new()->create(['is_active' => true]);
        Permission::firstOrCreate(['name' => 'product.import', 'guard_name' => 'web']);
        $viewer->givePermissionTo('product.import');

        $job = DataImportJob::create([
            'module' => 'product',
            'file_name' => 'private.csv',
            'file_path' => 'temp-imports/private.csv',
            'file_type' => 'csv',
            'status' => 'previewing',
            'created_by' => $owner->id,
        ]);

        $this->actingAs($viewer)
            ->get(ImportPreviewPage::getUrl(['job' => $job->id]))
            ->assertRedirect();
    }

    public function test_ai_retry_requires_server_side_permission(): void
    {
        $user = UserFactory::new()->create(['is_active' => true]);
        $this->actingAs($user);

        $product = Product::factory()->create();

        $this->post(route('admin.products.ai-retry', ['product' => $product->id]))
            ->assertForbidden();
    }
}
