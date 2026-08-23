<?php

namespace Tests\Unit;

use App\Models\AiBulkRuntimeBatch;
use App\Services\AI\BulkRuntimeTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Phase2F3TokenAccountingTest extends TestCase
{
    use RefreshDatabase;

    public function test_provider_usage_reconciliation_is_audited_and_idempotent(): void
    {
        $batch = AiBulkRuntimeBatch::create([
            'batch_uuid' => 'phase2f3-token-test',
            'status' => 'PAUSED',
            'token_budget_total' => 50000,
            'token_reserved' => 100,
            'token_consumed' => 20192,
        ]);

        $service = app(BulkRuntimeTokenService::class);
        $this->assertTrue($service->reconcileActualUsage($batch, 8282, 232, 'validator_failure_after_provider_success'));
        $this->assertFalse($service->reconcileActualUsage($batch->refresh(), 8282, 232, 'duplicate_reconciliation'));
        $this->assertTrue($service->releaseOutstandingReservation($batch->refresh(), 100, 'interrupted_before_provider', 174));

        $batch->refresh();
        $this->assertSame(28474, (int) $batch->token_consumed);
        $this->assertSame(0, (int) $batch->token_reserved);
        $this->assertSame(1, DB::table('ai_technical_logs')->where('event', 'token_reconciled')->count());
        $this->assertSame(1, DB::table('ai_technical_logs')->where('event', 'token_reservation_released')->count());
    }
}
