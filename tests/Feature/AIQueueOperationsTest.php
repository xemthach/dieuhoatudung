<?php

namespace Tests\Feature;

use App\Enums\AIContentJobStatus;
use App\Jobs\AiProductContentSingleJob;
use App\Jobs\GenerateBlogDraftJob;
use App\Models\AiContentJob;
use App\Models\AiProductJob;
use App\Models\Product;
use App\Services\AI\AIQueueMonitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AIQueueOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_recover_stuck_blog_job_redispatches_when_retry_available(): void
    {
        Bus::fake();
        $job = AiContentJob::create([
            'topic' => 'stuck blog',
            'status' => AIContentJobStatus::Processing,
            'retry_count' => 0,
            'updated_at' => now()->subMinutes(20),
        ]);

        $this->artisan('ai:jobs-recover-stuck')->assertSuccessful();

        $job->refresh();
        $this->assertSame(AIContentJobStatus::Queued, $job->status);
        $this->assertSame('queue_job_stuck_timeout', $job->failed_reason);
        Bus::assertDispatched(GenerateBlogDraftJob::class);
    }

    public function test_recover_stuck_product_item_redispatches_when_retry_available(): void
    {
        Bus::fake();
        $product = Product::factory()->create();
        $job = AiProductJob::create([
            'type' => 'generate_ai_content',
            'scope' => 'selected',
            'status' => 'processing',
            'total' => 1,
            'config_json' => [],
        ]);
        $item = $job->items()->create([
            'product_id' => $product->id,
            'status' => 'processing',
            'retry_count' => 0,
            'updated_at' => now()->subMinutes(20),
        ]);

        $this->artisan('ai:jobs-recover-stuck')->assertSuccessful();

        $this->assertSame('queued', $item->refresh()->status);
        $this->assertSame('queue_job_stuck_timeout', $item->failed_reason);
        Bus::assertDispatched(AiProductContentSingleJob::class);
    }

    public function test_cancel_current_jobs_marks_ai_records_cancelled_and_flushes_queue_rows(): void
    {
        $product = Product::factory()->create(['ai_status' => 'processing']);
        AiContentJob::create([
            'topic' => 'queued blog',
            'status' => AIContentJobStatus::Queued,
        ]);
        $job = AiProductJob::create([
            'type' => 'generate_ai_content',
            'scope' => 'selected',
            'status' => 'processing',
            'total' => 1,
            'config_json' => [],
        ]);
        $job->items()->create([
            'product_id' => $product->id,
            'status' => 'processing',
        ]);
        DB::table('jobs')->insert([
            'queue' => 'ai',
            'payload' => '{}',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ]);

        $this->artisan('ai:jobs-cancel-current --flush-queue')->assertSuccessful();

        $this->assertSame(AIContentJobStatus::Cancelled, AiContentJob::first()->status);
        $this->assertSame('cancelled', $job->refresh()->status);
        $this->assertSame('cancelled', $job->items()->first()->status);
        $this->assertSame('cancelled', $product->refresh()->ai_status);
        $this->assertSame(0, DB::table('jobs')->where('queue', 'ai')->count());
    }

    public function test_queue_health_command_outputs_json(): void
    {
        $this->artisan('ai:queue-health --json')
            ->expectsOutputToContain('queue_connection')
            ->assertSuccessful();

        $health = app(AIQueueMonitor::class)->health();
        $this->assertArrayHasKey('worker_command', $health);
        $this->assertArrayHasKey('scheduler_is_running', $health);
    }
}
