<?php

namespace Tests\Feature;

use App\Filament\Pages\AIQueueHealth;
use App\Models\QueueWorkerHeartbeat;
use App\Models\User;
use App\Services\AI\AIQueueMonitor;
use App\Services\AI\AIWorkerDesiredStateService;
use App\Services\AI\AIWorkerReadinessService;
use App\Services\Operations\SystemHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AIWorkerAdminControlTest extends TestCase
{
    use RefreshDatabase;

    private string $statePath;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ai.worker_desired_state_path', storage_path('framework/testing/ai-worker-desired-state-'.Str::uuid().'.json'));
        config()->set('ai.managed_state_directory', storage_path('framework/testing'));
        $this->statePath = app(AIWorkerDesiredStateService::class)->statePath();
        $this->writeDesiredState(AIWorkerDesiredStateService::DISABLED);
    }

    protected function tearDown(): void
    {
        File::delete($this->statePath);

        parent::tearDown();
    }

    public function test_authorized_operator_can_enable_and_gracefully_disable_worker_processing(): void
    {
        $this->actingAs($this->operator(['ai_worker.view', 'ai_worker.manage']));

        Livewire::test(AIQueueHealth::class)
            ->assertActionVisible('enable_worker')
            ->assertActionVisible('worker_self_test')
            ->callAction('enable_worker')
            ->assertHasNoActionErrors()
            ->assertActionVisible('disable_worker')
            ->callAction('disable_worker')
            ->assertHasNoActionErrors();

        $this->assertSame(AIWorkerDesiredStateService::DISABLED, app(AIWorkerDesiredStateService::class)->current()['desired_state']);
        $this->assertDatabaseHas('ai_technical_logs', ['module' => 'ai_worker_control', 'event' => 'WORKER_ENABLED']);
        $this->assertDatabaseHas('ai_technical_logs', ['module' => 'ai_worker_control', 'event' => 'WORKER_DISABLED']);
    }

    public function test_viewer_can_read_status_but_cannot_invoke_worker_controls(): void
    {
        $this->actingAs($this->operator(['ai_worker.view']));

        Livewire::test(AIQueueHealth::class)
            ->assertOk()
            ->assertActionHidden('enable_worker')
            ->assertActionHidden('disable_worker')
            ->assertActionVisible('reload');

        $source = File::get(app_path('Filament/Pages/AIQueueHealth.php'));
        $this->assertStringContainsString("can('ai_worker.manage')", $source);
        $this->assertStringContainsString('abort_unless', $source);
    }

    public function test_enabled_with_recent_running_heartbeat_is_ready(): void
    {
        $this->writeDesiredState(AIWorkerDesiredStateService::ENABLED);
        $this->heartbeat('running', now());

        $status = app(AIWorkerReadinessService::class)->snapshot();

        $this->assertSame('ONLINE', $status['actual']);
        $this->assertTrue($status['accepting_new_jobs']);
        $this->assertTrue($status['ready']);
    }

    public function test_enabled_with_stale_heartbeat_is_warning_and_not_ready(): void
    {
        $this->writeDesiredState(AIWorkerDesiredStateService::ENABLED);
        $this->heartbeat('running', now()->subMinutes(10));

        $status = app(AIWorkerReadinessService::class)->snapshot();

        $this->assertSame('STALE', $status['actual']);
        $this->assertFalse($status['accepting_new_jobs']);
        $this->assertFalse($status['ready']);
        $this->assertStringContainsString('chưa hoạt động', $status['message']);
    }

    public function test_disabled_with_paused_process_is_online_but_not_accepting_jobs(): void
    {
        $this->writeDesiredState(AIWorkerDesiredStateService::DISABLED);
        $this->heartbeat('paused', now());

        $health = app(AIQueueMonitor::class)->liveStatusHealth();

        $this->assertSame('ONLINE', data_get($health, 'worker_heartbeat.health_status'));
        $this->assertSame('PAUSED', data_get($health, 'worker_heartbeat.process_status'));
        $this->assertTrue((bool) data_get($health, 'worker_heartbeat.process_online'));
        $this->assertFalse((bool) data_get($health, 'worker_heartbeat.accepting_new_jobs'));

        $system = app(SystemHealthService::class)->snapshot();
        $this->assertSame('DISABLED', data_get($system, 'components.worker.state'));
        $this->assertSame('ONLINE', data_get($system, 'components.worker.actual'));
    }

    public function test_refresh_is_read_only(): void
    {
        $this->actingAs($this->operator(['ai_worker.view']));
        $beforeState = File::get($this->statePath);
        $beforeLogs = DB::table('ai_technical_logs')->count();
        $beforeJobs = DB::table('jobs')->count();

        Livewire::test(AIQueueHealth::class)->call('reload')->assertOk();

        $this->assertSame($beforeState, File::get($this->statePath));
        $this->assertSame($beforeLogs, DB::table('ai_technical_logs')->count());
        $this->assertSame($beforeJobs, DB::table('jobs')->count());
    }

    public function test_http_worker_actions_do_not_spawn_kill_purge_or_call_provider(): void
    {
        $source = File::get(app_path('Filament/Pages/AIQueueHealth.php'));

        foreach (['shell_exec(', 'exec(', 'Artisan::call(', 'queue:work', 'queue:clear', 'provider->'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $source);
        }

        $child = File::get(app_path('Console/Commands/AIManagedChildWorker.php'));
        $this->assertStringContainsString('acceptsNewJobs()', $child);
        $this->assertStringContainsString("heartbeat('queue-worker', \$queue, 'paused')", $child);
    }

    public function test_permission_registry_contains_strong_worker_management_permission(): void
    {
        $this->assertSame('Bật hoặc tắt xử lý AI', config('permissions.ai_worker.permissions.manage'));
        $command = File::get(app_path('Console/Commands/SyncPermissions.php'));
        $this->assertStringContainsString("'ai_worker.*'", $command);
    }

    private function operator(array $permissions): User
    {
        $user = User::factory()->create(['is_active' => true]);
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
        $user->givePermissionTo($permissions);

        return $user;
    }

    private function heartbeat(string $status, $lastSeenAt): void
    {
        QueueWorkerHeartbeat::create([
            'worker_name' => 'queue-worker',
            'queue' => config('ai.governed_queue', 'ai_governed'),
            'hostname' => 'worker-control-test',
            'last_seen_at' => $lastSeenAt,
            'status' => $status,
        ]);
    }

    private function writeDesiredState(string $state): void
    {
        File::ensureDirectoryExists(dirname($this->statePath));
        File::put($this->statePath, json_encode([
            'desired_state' => $state,
            'changed_at' => now()->toIso8601String(),
            'changed_by' => 'test-fixture',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
