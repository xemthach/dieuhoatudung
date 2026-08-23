<?php

namespace Tests\Unit;

use App\Models\AiBulkRuntimeBatch;
use App\Services\AI\BulkRuntimeTokenEnvelopeService;
use App\Services\AI\BulkRuntimeTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase2F6HardBudgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_envelope_is_deterministic_and_has_provider_output_cap(): void
    {
        $envelope = app(BulkRuntimeTokenEnvelopeService::class)->forPayload([
            'system' => 'system', 'prompt' => 'prompt', 'input' => 'input', 'max_tokens' => 10000,
        ]);

        $this->assertSame(10000, $envelope['effective_max_output_tokens']);
        $this->assertSame($envelope['estimated_input_tokens'] + 10000, $envelope['reservation_envelope']);
        $this->assertSame('max_tokens', $envelope['provider_parameter']);
    }

    public function test_envelope_over_remaining_budget_pauses_before_provider(): void
    {
        $batch = AiBulkRuntimeBatch::create([
            'batch_uuid' => 'phase2f6-over-budget', 'status' => 'RUNNING',
            'token_budget_total' => 50000, 'token_consumed' => 34207, 'token_reserved' => 0,
        ]);

        $allowed = app(BulkRuntimeTokenService::class)->reserveEnvelope($batch, [
            'estimated_input_tokens' => 5000, 'effective_max_output_tokens' => 12000,
            'reservation_envelope' => 17000,
        ]);

        $this->assertFalse($allowed);
        $this->assertSame('PAUSED', $batch->refresh()->status);
        $this->assertSame('TOKEN_BUDGET_INSUFFICIENT_FOR_NEXT_REQUEST', $batch->status_reason);
    }

    public function test_exact_boundary_is_allowed_and_actual_usage_releases_unused_reservation(): void
    {
        $batch = AiBulkRuntimeBatch::create([
            'batch_uuid' => 'phase2f6-boundary', 'status' => 'RUNNING',
            'token_budget_total' => 50000, 'token_consumed' => 40000, 'token_reserved' => 0,
        ]);
        $service = app(BulkRuntimeTokenService::class);
        $this->assertTrue($service->reserveEnvelope($batch, ['reservation_envelope' => 10000]));
        $service->finalize($batch->refresh(), 10000, 8437);

        $batch->refresh();
        $this->assertSame(48437, (int) $batch->token_consumed);
        $this->assertSame(0, (int) $batch->token_reserved);
    }

    public function test_two_reservations_are_serialized_against_one_budget(): void
    {
        $batch = AiBulkRuntimeBatch::create([
            'batch_uuid' => 'phase2f6-concurrent', 'status' => 'RUNNING',
            'token_budget_total' => 100, 'token_consumed' => 0, 'token_reserved' => 0,
        ]);
        $service = app(BulkRuntimeTokenService::class);
        $this->assertTrue($service->reserveEnvelope($batch, ['reservation_envelope' => 60]));
        $this->assertFalse($service->reserveEnvelope($batch->refresh(), ['reservation_envelope' => 41]));
        $this->assertSame(60, (int) $batch->refresh()->token_reserved);
        $this->assertSame('PAUSED', $batch->status);
    }

    public function test_usage_greater_than_reserved_pauses_and_preserves_violation(): void
    {
        $batch = AiBulkRuntimeBatch::create([
            'batch_uuid' => 'phase2f6-violation', 'status' => 'RUNNING',
            'token_budget_total' => 50000, 'token_consumed' => 0, 'token_reserved' => 100,
        ]);
        app(BulkRuntimeTokenService::class)->finalize($batch, 100, 101);

        $batch->refresh();
        $this->assertSame('PAUSED', $batch->status);
        $this->assertSame('CRITICAL_BUDGET_CONTRACT_VIOLATION', $batch->status_reason);
        $this->assertSame(101, (int) $batch->token_consumed);
        $this->assertSame(0, (int) $batch->token_reserved);
    }

    public function test_unknown_usage_conservatively_consumes_full_envelope(): void
    {
        $batch = AiBulkRuntimeBatch::create([
            'batch_uuid' => 'phase2f6-unknown', 'status' => 'RUNNING',
            'token_budget_total' => 50000, 'token_consumed' => 0, 'token_reserved' => 10000,
        ]);
        app(BulkRuntimeTokenService::class)->finalize($batch, 10000, null);

        $this->assertSame(10000, (int) $batch->refresh()->token_consumed);
        $this->assertSame(0, (int) $batch->token_reserved);
    }
}
