<?php

namespace App\Services\AI;

use App\Models\AiBulkRuntimeBatch;
use Illuminate\Support\Facades\DB;

class BulkRuntimeLeaseService
{
    public function claim(AiBulkRuntimeBatch $batch, int $itemId, string $workerId, int $seconds = 300): bool
    {
        return DB::transaction(function () use ($batch, $itemId, $workerId, $seconds): bool {
            $existing = DB::table('ai_bulk_runtime_leases')->where('runtime_batch_id', $batch->id)->where('item_id', $itemId)->lockForUpdate()->first();
            if ($existing && $existing->status === 'CLAIMED' && $existing->expires_at > now()) return false;
            $values = ['worker_id' => $workerId, 'status' => 'CLAIMED', 'claimed_at' => now(), 'expires_at' => now()->addSeconds($seconds), 'heartbeat_at' => now(), 'updated_at' => now()];
            if ($existing) DB::table('ai_bulk_runtime_leases')->where('id', $existing->id)->update($values);
            else DB::table('ai_bulk_runtime_leases')->insert(array_merge($values, ['runtime_batch_id' => $batch->id, 'item_id' => $itemId, 'created_at' => now()]));
            return true;
        });
    }

    public function release(AiBulkRuntimeBatch $batch, int $itemId, string $workerId): void
    {
        DB::table('ai_bulk_runtime_leases')->where('runtime_batch_id', $batch->id)->where('item_id', $itemId)->where('worker_id', $workerId)->update(['status' => 'RELEASED', 'updated_at' => now()]);
    }

    public function recoverExpired(): int
    {
        return DB::table('ai_bulk_runtime_leases')->where('status', 'CLAIMED')->where('expires_at', '<', now())->update(['status' => 'EXPIRED', 'updated_at' => now()]);
    }
}
