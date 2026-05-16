<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\AiProductJob;
use App\Models\Product;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AIProductEditDraftActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_blocked_ai_draft_cannot_be_applied_and_shows_fact_check_message(): void
    {
        $this->actingAsAiProductEditor();
        $product = Product::factory()->create(['ai_status' => 'needs_review']);
        $job = AiProductJob::create([
            'type' => 'generate_ai_content',
            'scope' => 'selected',
            'status' => 'completed',
            'total' => 1,
            'config_json' => [],
        ]);
        $job->items()->create([
            'product_id' => $product->id,
            'status' => 'needs_review',
            'generated_payload_json' => [
                'excerpt' => 'Draft cần duyệt.',
                'blocked_claims' => ['chinh_hang'],
            ],
        ]);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->callAction('ai_apply_latest_draft')
            ->assertNotified('AI draft bị fact-check chặn');

        $this->assertSame('needs_review', $product->refresh()->ai_status);
    }

    public function test_queued_ai_draft_cannot_be_applied(): void
    {
        $this->actingAsAiProductEditor();
        $product = Product::factory()->create(['ai_status' => 'queued']);
        $job = AiProductJob::create([
            'type' => 'generate_ai_content',
            'scope' => 'selected',
            'status' => 'processing',
            'total' => 1,
            'config_json' => [],
        ]);
        $job->items()->create([
            'product_id' => $product->id,
            'status' => 'queued',
            'generated_payload_json' => [
                'excerpt' => 'Draft chưa hoàn tất.',
                'blocked_claims' => [],
            ],
        ]);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->callAction('ai_apply_latest_draft')
            ->assertNotified('Chưa có AI draft để apply');

        $product->refresh();
        $this->assertSame('queued', $product->ai_status);
        $this->assertStringContainsString('chưa hoàn tất', $product->ai_error_message);
    }

    private function actingAsAiProductEditor(): void
    {
        $user = UserFactory::new()->create(['is_active' => true]);

        foreach (['product.view', 'product.edit', 'product.ai_generate'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $user->givePermissionTo(['product.view', 'product.edit', 'product.ai_generate']);
        $this->actingAs($user);
    }
}
