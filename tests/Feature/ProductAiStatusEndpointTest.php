<?php

namespace Tests\Feature;

use App\Jobs\AiProductContentSingleJob;
use App\Models\AiProductJob;
use App\Models\AiProductJobItem;
use App\Models\Product;
use App\Models\QueueWorkerHeartbeat;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ProductAiStatusEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_ai_status_endpoint_returns_visible_rows_only_with_queue_health(): void
    {
        $this->actingAsAiProductUser();

        $visible = Product::factory()->create([
            'ai_status' => 'processing',
            'ai_score' => 72,
            'ai_warning_count' => 3,
            'ai_last_run_at' => now()->subMinute(),
        ]);
        $hidden = Product::factory()->create([
            'ai_status' => 'failed',
            'ai_score' => 0,
            'ai_warning_count' => 9,
        ]);
        $job = AiProductJob::create([
            'type' => 'generate_ai_content',
            'scope' => 'selected',
            'status' => 'processing',
            'total' => 10,
            'processed' => 4,
            'config_json' => ['outputs' => ['content' => true]],
        ]);
        AiProductJobItem::create([
            'ai_product_job_id' => $job->id,
            'product_id' => $visible->id,
            'status' => 'processing',
        ]);
        AiProductJobItem::create([
            'ai_product_job_id' => $job->id,
            'product_id' => $hidden->id,
            'status' => 'failed',
            'failed_reason' => 'missing_api_key',
        ]);
        QueueWorkerHeartbeat::create([
            'worker_name' => 'queue-worker',
            'queue' => 'ai',
            'hostname' => 'test',
            'last_seen_at' => now(),
            'status' => 'running',
        ]);

        $response = $this->getJson(route('admin.products.ai-status', ['ids' => (string) $visible->id]));

        $response
            ->assertOk()
            ->assertJsonPath('products.0.id', $visible->id)
            ->assertJsonPath('products.0.ai_status', 'processing')
            ->assertJsonPath('products.0.seo_score', 72)
            ->assertJsonPath('products.0.warnings_count', 3)
            ->assertJsonPath('products.0.progress_percent', 40)
            ->assertJsonPath('queue_health.worker_online', true)
            ->assertJsonPath('auto_refresh.should_continue', true);

        $this->assertCount(1, $response->json('products'));
    }

    public function test_ai_status_endpoint_exposes_failed_reason_and_retry_url(): void
    {
        $this->actingAsAiProductUser();

        $product = Product::factory()->create([
            'ai_status' => 'failed',
            'ai_score' => 0,
            'ai_warning_count' => 0,
            'ai_error_message' => 'Provider timeout.',
        ]);
        $job = AiProductJob::create([
            'type' => 'generate_ai_content',
            'scope' => 'selected',
            'status' => 'completed_with_errors',
            'total' => 1,
            'processed' => 1,
            'failed' => 1,
            'config_json' => ['outputs' => ['content' => true]],
        ]);
        AiProductJobItem::create([
            'ai_product_job_id' => $job->id,
            'product_id' => $product->id,
            'status' => 'failed',
            'failed_reason' => 'provider_timeout',
            'last_error_message' => 'Provider timeout.',
        ]);

        $response = $this->getJson(route('admin.products.ai-status', ['ids' => (string) $product->id]));

        $response
            ->assertOk()
            ->assertJsonPath('products.0.ai_status_label', 'Thất bại: provider_timeout')
            ->assertJsonPath('products.0.failed_reason', 'provider_timeout')
            ->assertJsonPath('products.0.last_error_message', 'Provider timeout.');

        $this->assertStringContainsString("/admin/products/{$product->id}/ai-retry", $response->json('products.0.retry_url'));
    }

    public function test_ai_retry_endpoint_requeues_failed_items_without_reloading_page(): void
    {
        Bus::fake();
        $this->actingAsAiProductUser();

        $product = Product::factory()->create([
            'ai_status' => 'failed',
            'ai_error_message' => 'Missing API key.',
        ]);
        $job = AiProductJob::create([
            'type' => 'generate_ai_content',
            'scope' => 'selected',
            'status' => 'completed_with_errors',
            'total' => 1,
            'processed' => 1,
            'failed' => 1,
            'config_json' => ['outputs' => ['content' => true]],
        ]);
        $item = AiProductJobItem::create([
            'ai_product_job_id' => $job->id,
            'product_id' => $product->id,
            'status' => 'failed',
            'failed_reason' => 'missing_api_key',
            'last_error_code' => 'missing_api_key',
            'last_error_message' => 'Missing API key.',
            'error_message' => 'Missing API key.',
        ]);

        $response = $this->postJson(route('admin.products.ai-retry', $product));

        $response
            ->assertOk()
            ->assertJsonPath('retried', 1)
            ->assertJsonPath('product_id', $product->id)
            ->assertJsonPath('status', 'queued');

        $this->assertSame('queued', $item->refresh()->status);
        $this->assertNull($item->failed_reason);
        $this->assertSame('queued', $product->refresh()->ai_status);
        Bus::assertDispatched(AiProductContentSingleJob::class);
    }

    public function test_product_list_renders_refresh_button_queue_widget_and_poller_script(): void
    {
        $this->actingAsAiProductUser();

        $response = $this->get('/admin/products');

        $response
            ->assertOk()
            ->assertSee('Refresh AI Status')
            ->assertSee('AI Queue: checking...')
            ->assertSee('ProductAiStatusPoller', false)
            ->assertSee('admin\\/products\\/ai-status', false);
    }

    private function actingAsAiProductUser(): void
    {
        $user = UserFactory::new()->create(['is_active' => true]);

        foreach (['product.view', 'product.ai_generate'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $user->givePermissionTo(['product.view', 'product.ai_generate']);
        $this->actingAs($user);
    }
}
