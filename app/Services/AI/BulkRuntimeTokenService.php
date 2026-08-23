<?php

namespace App\Services\AI;

use App\Models\AiBulkRuntimeBatch;
use Illuminate\Support\Facades\DB;

class BulkRuntimeTokenService
{
    public function reserve(AiBulkRuntimeBatch $batch, int $estimate, bool $allowExplicitTargetedOperation = false): bool
    {
        $allowed = DB::transaction(function () use ($batch, $estimate, $allowExplicitTargetedOperation): bool {
            $row = AiBulkRuntimeBatch::whereKey($batch->id)->lockForUpdate()->first();
            if (! $row || in_array($row->status, ['CANCELLED', 'FAILED', 'COMPLETED'], true) || ($row->status === 'PAUSED' && ! $allowExplicitTargetedOperation)) return false;
            if ($row->token_budget_total !== null && ((int) $row->token_reserved + (int) $row->token_consumed + $estimate) > (int) $row->token_budget_total) {
                $row->update(['status' => 'PAUSED', 'status_reason' => 'TOKEN_BUDGET_INSUFFICIENT_FOR_NEXT_REQUEST', 'pause_requested_at' => now()]);
                return false;
            }
            $row->increment('token_reserved', $estimate);
            return true;
        });
        if (! $allowed && $batch->refresh()->status_reason === 'TOKEN_BUDGET_INSUFFICIENT_FOR_NEXT_REQUEST') {
            app(AIRuntimeAlertService::class)->emit('TOKEN_BUDGET_INSUFFICIENT', AiBulkRuntimeBatch::class, ['resource_id' => $batch->id, 'reason' => $batch->status_reason]);
        }
        return $allowed;
    }

    public function reserveEnvelope(AiBulkRuntimeBatch $batch, array $envelope, bool $allowExplicitTargetedOperation = false): bool
    {
        $reservation = (int) ($envelope['reservation_envelope'] ?? 0);
        if ($reservation < 1) {
            throw new \RuntimeException('INVALID_TOKEN_RESERVATION_ENVELOPE');
        }
        return $this->reserve($batch, $reservation, $allowExplicitTargetedOperation);
    }

    public function finalize(AiBulkRuntimeBatch $batch, int $reserved, ?int $actual): void
    {
        $violation = DB::transaction(function () use ($batch, $reserved, $actual): bool {
            $row = AiBulkRuntimeBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
            $used = $actual ?? $reserved;
            if ($used > $reserved) {
                // Preserve the authoritative usage and durably stop further
                // work. The status is committed so the violation cannot be
                // hidden by an exception rollback.
                $row->update([
                    'token_reserved' => 0,
                    'token_consumed' => (int) $row->token_consumed + $used,
                    'status' => 'PAUSED',
                    'status_reason' => 'CRITICAL_BUDGET_CONTRACT_VIOLATION',
                    'pause_requested_at' => now(),
                ]);
                return true;
            }
            $row->update([
                'token_reserved' => max(0, (int) $row->token_reserved - $reserved),
                'token_consumed' => (int) $row->token_consumed + $used,
            ]);
            return false;
        });
        if ($violation) app(AIRuntimeAlertService::class)->emit('BUDGET_CONTRACT_VIOLATION', AiBulkRuntimeBatch::class, ['resource_id' => $batch->id, 'reason' => 'actual_usage_exceeded_reservation']);
    }

    /**
     * Apply an explicit, idempotent audit correction when a provider log is
     * authoritative but an earlier runtime path failed to persist its usage.
     */
    public function reconcileActualUsage(AiBulkRuntimeBatch $batch, int $delta, int $sourceRequestId, string $reason): bool
    {
        if ($delta === 0) return false;

        return DB::transaction(function () use ($batch, $delta, $sourceRequestId, $reason): bool {
            $marker = 'token_reconciliation:'.$batch->id.':'.$sourceRequestId.':'.$delta;
            if (DB::table('ai_technical_logs')->where('event', 'token_reconciled')->where('message', $marker)->exists()) {
                return false;
            }

            $row = AiBulkRuntimeBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
            $newConsumed = (int) $row->token_consumed + $delta;
            if ($newConsumed < 0) throw new \RuntimeException('TOKEN_RECONCILIATION_NEGATIVE_CONSUMED');
            $row->update(['token_consumed' => $newConsumed]);

            DB::table('ai_technical_logs')->insert([
                'module' => 'ai_bulk_runtime',
                'ai_job_type' => AiBulkRuntimeBatch::class,
                'ai_job_id' => $row->id,
                'level' => 'warning',
                'event' => 'token_reconciled',
                'message' => $marker,
                'context_json' => json_encode([
                    'batch_uuid' => $row->batch_uuid,
                    'runtime_batch_id' => $row->id,
                    'source_provider_request_id' => $sourceRequestId,
                    'delta' => $delta,
                    'old_consumed' => $newConsumed - $delta,
                    'new_consumed' => $newConsumed,
                    'reason' => $reason,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return true;
        });
    }

    public function releaseOutstandingReservation(AiBulkRuntimeBatch $batch, int $reserved, string $reason, ?int $itemId = null): bool
    {
        if ($reserved <= 0) return false;

        return DB::transaction(function () use ($batch, $reserved, $reason, $itemId): bool {
            $row = AiBulkRuntimeBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
            $released = min($reserved, (int) $row->token_reserved);
            if ($released <= 0) return false;
            $row->update(['token_reserved' => (int) $row->token_reserved - $released]);
            DB::table('ai_technical_logs')->insert([
                'module' => 'ai_bulk_runtime',
                'ai_job_type' => AiBulkRuntimeBatch::class,
                'ai_job_id' => $row->id,
                'level' => 'warning',
                'event' => 'token_reservation_released',
                'message' => 'token_reservation_released:'.$row->id.':'.($itemId ?? 'unknown').':'.$released,
                'context_json' => json_encode([
                    'batch_uuid' => $row->batch_uuid,
                    'runtime_batch_id' => $row->id,
                    'item_id' => $itemId,
                    'released' => $released,
                    'reason' => $reason,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            return true;
        });
    }
}
