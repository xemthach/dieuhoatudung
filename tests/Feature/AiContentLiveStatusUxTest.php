<?php

namespace Tests\Feature;

use App\Livewire\AiProductLiveStatus;
use App\Livewire\AiProductJobLiveStatus;
use App\Livewire\AiDashboardLiveStatus;
use App\Models\AiProductJob;
use App\Models\AiProductJobItem;
use App\Models\Product;
use App\Models\QueueWorkerHeartbeat;
use App\Services\AI\AiContentStatusPresenter;
use App\Services\AI\AiProductLiveStatusService;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AiContentLiveStatusUxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_presenter_maps_persisted_states_without_inventing_progress(): void
    {
        $presenter = app(AiContentStatusPresenter::class);
        $cases = [
            'QUEUED' => ['Đang chờ', 'gray'],
            'PROCESSING' => ['AI đang tạo nội dung', 'info'],
            'VALIDATING' => ['Đang kiểm tra nội dung', 'info'],
            'REVIEW_REQUIRED' => ['Chờ duyệt', 'warning'],
            'COMPLETED' => ['Hoàn tất', 'success'],
            'BLOCKED' => ['Bị chặn', 'warning'],
            'FAILED_TERMINAL' => ['Thất bại', 'danger'],
            'PAUSED' => ['Tạm dừng', 'gray'],
            'CANCELLED' => ['Đã hủy', 'gray'],
        ];

        foreach ($cases as $internal => [$label, $color]) {
            $view = $presenter->present($internal, ['desired_state' => 'ENABLED', 'health' => 'ONLINE']);
            $this->assertSame($label, $view['label'], $internal);
            $this->assertSame($color, $view['color'], $internal);
        }

        $disabled = $presenter->present('QUEUED', ['desired_state' => 'DISABLED', 'health' => 'OFFLINE']);
        $this->assertSame('Đã tạo yêu cầu nhưng AI worker đang tắt.', $disabled['warning']);

        $stale = $presenter->present('PROCESSING', ['desired_state' => 'ENABLED', 'health' => 'STALE']);
        $this->assertSame('INTERRUPTED', $stale['key']);
        $this->assertSame('Có thể bị gián đoạn', $stale['label']);
    }

    public function test_live_status_uses_latest_runtime_item_and_real_bulk_field_progress(): void
    {
        $product = Product::factory()->create(['ai_status' => 'completed']);
        $job = AiProductJob::create([
            'type' => 'generate_ai_content',
            'scope' => 'selected',
            'status' => 'processing',
            'total' => 20,
            'processed' => 12,
            'success' => 8,
            'failed' => 1,
            'needs_review' => 3,
            'config_json' => ['outputs' => ['content_html', 'seo_title', 'faq']],
        ]);
        AiProductJobItem::create([
            'ai_product_job_id' => $job->id,
            'product_id' => $product->id,
            'status' => 'processing',
            'canonical_status' => 'RUNNING',
            'state_changed_at' => now(),
            'field_status_json' => [
                'content_html' => 'DONE',
                'seo_title' => 'RUNNING',
                'faq' => 'BLOCKED',
            ],
        ]);

        $status = app(AiProductLiveStatusService::class)->forProductIds([$product->id], [
            'worker_desired_state' => 'ENABLED',
            'worker_heartbeat' => ['health_status' => 'ONLINE'],
        ])->get($product->id);

        $this->assertSame('PROCESSING', $status['status']['key']);
        $this->assertSame(60, $status['progress']['percent']);
        $this->assertSame(12, $status['progress']['processed']);
        $this->assertSame(
            ['Hoàn tất', 'AI đang tạo nội dung', 'Bị chặn'],
            collect($status['fields'])->pluck('status.label')->all(),
        );
    }

    public function test_single_product_has_step_status_but_no_fake_percentage(): void
    {
        $product = Product::factory()->create();
        $job = AiProductJob::create([
            'type' => 'generate_ai_content',
            'scope' => 'selected',
            'status' => 'processing',
            'total' => 1,
            'processed' => 0,
            'config_json' => ['outputs' => ['content_html' => true]],
        ]);
        AiProductJobItem::create([
            'ai_product_job_id' => $job->id,
            'product_id' => $product->id,
            'status' => 'validating',
            'canonical_status' => 'VALIDATING',
            'state_changed_at' => now(),
        ]);

        $status = app(AiProductLiveStatusService::class)->forProductIds([$product->id], [
            'worker_desired_state' => 'ENABLED',
            'worker_heartbeat' => ['health_status' => 'ONLINE'],
        ])->get($product->id);

        $this->assertSame('Đang kiểm tra nội dung', $status['ai_status_label']);
        $this->assertNull($status['progress']);
    }

    public function test_livewire_poll_refreshes_from_persisted_state_without_page_reload(): void
    {
        $user = $this->actingAsProductViewer();
        $product = Product::factory()->create();
        $job = AiProductJob::create([
            'type' => 'generate_ai_content',
            'scope' => 'selected',
            'status' => 'queued',
            'total' => 1,
            'processed' => 0,
            'config_json' => ['outputs' => ['content_html' => true]],
        ]);
        $item = AiProductJobItem::create([
            'ai_product_job_id' => $job->id,
            'product_id' => $product->id,
            'status' => 'queued',
            'canonical_status' => 'QUEUED',
            'state_changed_at' => now(),
        ]);
        QueueWorkerHeartbeat::create([
            'worker_name' => 'queue-worker',
            'queue' => config('ai.governed_queue', 'ai_governed'),
            'hostname' => 'test',
            'last_seen_at' => now(),
            'status' => 'running',
        ]);

        $component = Livewire::actingAs($user)
            ->test(AiProductLiveStatus::class, ['productId' => $product->id])
            ->assertSee('Đang chờ')
            ->assertSeeHtml('wire:poll.10s');

        $item->update([
            'status' => 'processing',
            'canonical_status' => 'RUNNING',
            'state_changed_at' => now()->addSecond(),
        ]);

        $component->call('refreshStatus')->assertSee('AI đang tạo nội dung');
    }

    public function test_live_status_component_requires_product_view_permission(): void
    {
        $product = Product::factory()->create();
        $user = UserFactory::new()->create(['is_active' => true]);

        Livewire::actingAs($user)
            ->test(AiProductLiveStatus::class, ['productId' => $product->id])
            ->assertForbidden();
    }

    public function test_job_detail_status_polls_real_aggregate_counts(): void
    {
        $user = UserFactory::new()->create(['is_active' => true]);
        foreach (['bulk_ai_view', 'bulk_ai_view_all'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
        $user->givePermissionTo(['bulk_ai_view', 'bulk_ai_view_all']);
        $job = AiProductJob::create([
            'type' => 'generate_ai_content',
            'scope' => 'selected',
            'status' => 'processing',
            'canonical_status' => 'RUNNING',
            'total' => 3,
            'processed' => 2,
            'success' => 1,
            'failed' => 0,
            'needs_review' => 1,
            'config_json' => [],
        ]);
        foreach ([['RUNNING', 'processing'], ['REVIEW_REQUIRED', 'needs_review'], ['BLOCKED', 'blocked']] as [$canonical, $legacy]) {
            AiProductJobItem::create([
                'ai_product_job_id' => $job->id,
                'product_id' => Product::factory()->create()->id,
                'canonical_status' => $canonical,
                'status' => $legacy,
            ]);
        }

        Livewire::actingAs($user)
            ->test(AiProductJobLiveStatus::class, ['jobId' => $job->id])
            ->assertSeeHtml('wire:poll.10s')
            ->assertSee('2 / 3')
            ->assertSee('Chờ duyệt')
            ->assertSee('Bị chặn');
    }

    public function test_product_status_endpoint_query_cost_is_bounded_for_multiple_rows(): void
    {
        $this->actingAsProductViewer();
        $products = Product::factory()->count(20)->create();
        $job = AiProductJob::create([
            'type' => 'generate_ai_content',
            'scope' => 'selected',
            'status' => 'queued',
            'total' => 20,
            'processed' => 0,
            'config_json' => ['outputs' => ['content_html' => true]],
        ]);
        foreach ($products as $product) {
            AiProductJobItem::create([
                'ai_product_job_id' => $job->id,
                'product_id' => $product->id,
                'status' => 'queued',
                'canonical_status' => 'QUEUED',
            ]);
        }

        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $response = $this->getJson(route('admin.products.ai-status', [
            'ids' => $products->pluck('id')->implode(','),
        ]));

        $response->assertOk();
        $this->assertCount(20, $response->json('products'));
        $this->assertLessThanOrEqual(15, $queries, "Status polling executed {$queries} queries for 20 Products.");
    }

    public function test_dashboard_live_card_separates_actionable_states_from_worker_disabled(): void
    {
        $user = UserFactory::new()->create(['is_active' => true]);
        foreach (['dashboard.view', 'product.ai_generate'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
        $user->givePermissionTo(['dashboard.view', 'product.ai_generate']);
        $job = AiProductJob::create([
            'type' => 'generate_ai_content',
            'scope' => 'selected',
            'status' => 'completed',
            'total' => 3,
            'processed' => 3,
            'config_json' => [],
        ]);
        foreach ([['RUNNING', 'processing'], ['REVIEW_REQUIRED', 'needs_review'], ['BLOCKED', 'blocked']] as [$canonical, $legacy]) {
            AiProductJobItem::create([
                'ai_product_job_id' => $job->id,
                'product_id' => Product::factory()->create()->id,
                'canonical_status' => $canonical,
                'status' => $legacy,
            ]);
        }

        Livewire::actingAs($user)
            ->test(AiDashboardLiveStatus::class)
            ->assertSeeHtml('wire:poll.10s')
            ->assertSee('Worker')
            ->assertSee('Đang tắt')
            ->assertSee('Đang chạy')
            ->assertSee('Chờ duyệt')
            ->assertSee('Bị chặn');
    }

    private function actingAsProductViewer(): object
    {
        $user = UserFactory::new()->create(['is_active' => true]);
        Permission::firstOrCreate(['name' => 'product.view', 'guard_name' => 'web']);
        $user->givePermissionTo('product.view');
        $this->actingAs($user);

        return $user;
    }
}
