<?php

namespace Tests\Feature;

use App\Services\AI\AIQueueMonitor;
use App\Services\Operations\SystemHealthService;
use Tests\TestCase;

class SystemHealthConfigurationTest extends TestCase
{
    public function test_production_debug_is_reported_as_a_configuration_warning(): void
    {
        config()->set('app.env', 'production');
        config()->set('app.debug', true);

        $this->mock(AIQueueMonitor::class, function ($mock): void {
            $mock->shouldReceive('health')->andReturn([
                'pending_jobs_count' => 0,
                'failed_jobs_count' => 0,
                'ai_jobs_stuck_count' => 0,
                'queue_connection' => 'sync',
                'legacy_ai_processing_count' => 0,
                'scheduler_is_running' => false,
                'worker_desired_state' => 'DISABLED',
                'worker_heartbeat' => ['health_status' => 'OFFLINE', 'accepting_new_jobs' => false],
                'worker_deployment_status' => 'UNKNOWN',
            ]);
        });

        $configuration = app(SystemHealthService::class)->snapshot()['components']['configuration'];

        $this->assertSame('WARNING', $configuration['state']);
        $this->assertTrue($configuration['debug_enabled']);
        $this->assertSame('production_debug_enabled', $configuration['reason']);
    }

    public function test_non_production_debug_is_not_reported_as_a_production_configuration_warning(): void
    {
        config()->set('app.env', 'testing');
        config()->set('app.debug', true);

        $this->mock(AIQueueMonitor::class, function ($mock): void {
            $mock->shouldReceive('health')->andReturn([
                'pending_jobs_count' => 0,
                'failed_jobs_count' => 0,
                'ai_jobs_stuck_count' => 0,
                'queue_connection' => 'sync',
                'legacy_ai_processing_count' => 0,
                'scheduler_is_running' => false,
                'worker_desired_state' => 'DISABLED',
                'worker_heartbeat' => ['health_status' => 'OFFLINE', 'accepting_new_jobs' => false],
                'worker_deployment_status' => 'UNKNOWN',
            ]);
        });

        $configuration = app(SystemHealthService::class)->snapshot()['components']['configuration'];

        $this->assertSame('HEALTHY', $configuration['state']);
        $this->assertTrue($configuration['debug_enabled']);
        $this->assertArrayNotHasKey('reason', $configuration);
    }
}
