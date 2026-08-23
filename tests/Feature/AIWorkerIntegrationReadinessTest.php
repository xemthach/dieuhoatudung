<?php

namespace Tests\Feature;

use App\Jobs\AIManagedWorkerHealthCheckJob;
use App\Models\Product;
use App\Models\QueueWorkerHeartbeat;
use App\Services\AI\AIQueueMonitor;
use App\Services\AI\AITechnicalLogger;
use App\Services\AI\AIWorkerDesiredStateService;
use App\Services\AI\AIWorkerRuntimeIdentityService;
use App\Services\AI\AIWorkerSelfTestService;
use App\Services\Operations\SystemHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class AIWorkerIntegrationReadinessTest extends TestCase
{
    use RefreshDatabase;

    private string $desiredPath;

    protected function setUp(): void
    {
        parent::setUp();

        $scope = (string) Str::uuid();
        $this->desiredPath = storage_path("framework/testing/{$scope}-desired.json");
        config()->set('ai.worker_desired_state_path', $this->desiredPath);
        config()->set('ai.managed_state_directory', storage_path("framework/testing/{$scope}"));
        File::ensureDirectoryExists(dirname($this->desiredPath));
        File::put($this->desiredPath, json_encode(['desired_state' => 'DISABLED']));
    }

    protected function tearDown(): void
    {
        File::delete($this->desiredPath);
        File::deleteDirectory((string) config('ai.managed_state_directory'));
        parent::tearDown();
    }

    public function test_runtime_identity_matches_current_queue_and_database_binding(): void
    {
        $runtime = app(AIWorkerRuntimeIdentityService::class)->current();

        $this->assertSame(trim(File::get(base_path('VERSION'))), $runtime['app_version']);
        $this->assertSame(config('queue.default'), $runtime['queue_connection']);
        $this->assertSame('ai_governed', $runtime['queue']);
        $this->assertSame(config('database.default'), $runtime['database_connection']);
        $this->assertSame(config('database.connections.'.config('database.default').'.database'), $runtime['database_name']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $runtime['worker_code_hash']);
        $this->assertGreaterThan(900, config('queue.connections.database.retry_after'));
    }

    public function test_self_test_dispatch_is_governed_non_provider_and_product_safe(): void
    {
        Queue::fake();
        $products = Product::query()->count();

        $result = app(AIWorkerSelfTestService::class)->dispatch('test-operator', 'probe-safe-dispatch');

        $this->assertTrue($result['created']);
        $this->assertSame('QUEUED', $result['status']);
        Queue::assertPushedOn('ai_governed', AIManagedWorkerHealthCheckJob::class);
        $this->assertSame($products, Product::query()->count());
        $this->assertDatabaseHas('ai_technical_logs', [
            'module' => 'ai_worker_self_test',
            'event' => 'WORKER_SELF_TEST_QUEUED',
        ]);
        $context = \App\Models\AiTechnicalLog::query()->latest('id')->firstOrFail()->context_json;
        $this->assertFalse($context['provider_call']);
        $this->assertFalse($context['product_mutation']);
    }

    public function test_same_process_completion_is_not_misreported_as_cross_process_proof(): void
    {
        Queue::fake();
        app(AIWorkerSelfTestService::class)->dispatch('test-operator', 'probe-same-process');
        $job = new AIManagedWorkerHealthCheckJob('probe-same-process', getmypid() ?: null);
        $job->handle(
            app(AIQueueMonitor::class),
            app(AITechnicalLogger::class),
            app(AIWorkerRuntimeIdentityService::class),
        );

        $latest = app(AIWorkerSelfTestService::class)->latest();
        $this->assertSame('COMPLETED', $latest['status']);
        $this->assertFalse($latest['cross_process']);
        $this->assertSame('ai_governed', $latest['queue']);
        $this->assertFalse($latest['provider_call']);
        $this->assertFalse($latest['product_mutation']);
    }

    public function test_version_mismatch_is_visible_and_blocks_enabled_health(): void
    {
        File::put($this->desiredPath, json_encode(['desired_state' => 'ENABLED']));
        QueueWorkerHeartbeat::create([
            'worker_name' => 'queue-worker',
            'queue' => 'ai_governed',
            'hostname' => 'integration-test',
            'pid' => 1234,
            'last_seen_at' => now(),
            'status' => 'running',
        ]);
        $path = app(AIWorkerDesiredStateService::class)->managedStatePath('ai_governed');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode([
            'supervisor_pid' => 1234,
            'child_pid' => 1235,
            'runtime' => array_merge(app(AIWorkerRuntimeIdentityService::class)->current(), ['app_version' => '0.0.0-stale']),
        ]));

        $health = app(AIQueueMonitor::class)->health();
        $this->assertSame('VERSION_MISMATCH', $health['worker_deployment_status']);
        $this->assertSame('CRITICAL', data_get(app(SystemHealthService::class)->snapshot(), 'components.worker.state'));
    }

    public function test_http_control_and_self_test_never_manage_os_process_directly(): void
    {
        $source = File::get(app_path('Filament/Pages/AIQueueHealth.php'));
        foreach (['exec(', 'shell_exec(', 'proc_open(', 'Start-Process', 'queue:work'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $source);
        }

        $worker = File::get(app_path('Console/Commands/AIManagedWorker.php'));
        $child = File::get(app_path('Console/Commands/AIManagedChildWorker.php'));
        $this->assertStringContainsString("'--connection='.\$connection", $worker);
        $this->assertStringContainsString("'--queue' => \$queue", $child);
        $this->assertStringContainsString('acceptsNewJobs()', $child);
    }
}
