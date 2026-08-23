<?php

namespace App\Services\AI;

use App\Models\AiBulkRuntimeBatch;
use Illuminate\Support\Facades\DB;

class BulkRuntimeSlotService
{
    public function acquire(AiBulkRuntimeBatch $batch, int $itemId, string $workerId, int $leaseSeconds = 120, bool $allowExplicitTargetedOperation = false): ?array
    {
        return DB::transaction(function () use ($batch, $itemId, $workerId, $leaseSeconds, $allowExplicitTargetedOperation): ?array {
            $locked = AiBulkRuntimeBatch::whereKey($batch->id)->lockForUpdate()->first();
            if (! $locked || in_array($locked->status, ['CANCELLED', 'FAILED', 'COMPLETED'], true) || ($locked->status === 'PAUSED' && ! $allowExplicitTargetedOperation)) return null;
            DB::table('ai_bulk_runtime_slots')->where('runtime_batch_id', $locked->id)->where('status', 'LEASED')->where('expires_at', '<', now())->update(['status' => 'FREE', 'owner_worker' => null, 'item_id' => null, 'updated_at' => now()]);
            $slot = DB::table('ai_bulk_runtime_slots')->where('runtime_batch_id', $locked->id)->where('status', 'FREE')->lockForUpdate()->first();
            if (! $slot) return null;
            $expires = now()->addSeconds($leaseSeconds);
            DB::table('ai_bulk_runtime_slots')->where('id', $slot->id)->update(['status' => 'LEASED', 'owner_worker' => $workerId, 'item_id' => $itemId, 'acquired_at' => now(), 'expires_at' => $expires, 'heartbeat_at' => now(), 'updated_at' => now()]);
            DB::table('ai_bulk_runtime_slot_events')->insert(['runtime_batch_id' => $locked->id, 'slot_id' => $slot->id, 'item_id' => $itemId, 'worker_id' => $workerId, 'event' => 'ACQUIRED', 'occurred_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            return ['slot_id' => $slot->id, 'expires_at' => $expires->toIso8601String()];
        });
    }

    public function release(AiBulkRuntimeBatch $batch, int $itemId, string $workerId): void
    {
        $slot = DB::table('ai_bulk_runtime_slots')->where('runtime_batch_id', $batch->id)->where('item_id', $itemId)->where('owner_worker', $workerId)->first();
        if (! $slot) return;
        DB::table('ai_bulk_runtime_slot_events')->insert(['runtime_batch_id' => $batch->id, 'slot_id' => $slot->id, 'item_id' => $itemId, 'worker_id' => $workerId, 'event' => 'RELEASED', 'occurred_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('ai_bulk_runtime_slots')->where('id', $slot->id)->update(['status' => 'FREE', 'owner_worker' => null, 'item_id' => null, 'expires_at' => null, 'heartbeat_at' => null, 'updated_at' => now()]);
    }

    public function recoverExpired(AiBulkRuntimeBatch $batch): int
    {
        return DB::transaction(function () use ($batch): int {
            $slots = DB::table('ai_bulk_runtime_slots')
                ->where('runtime_batch_id', $batch->id)
                ->where('status', 'LEASED')
                ->where('expires_at', '<', now())
                ->lockForUpdate()
                ->get();
            foreach ($slots as $slot) {
                DB::table('ai_bulk_runtime_slot_events')->insert([
                    'runtime_batch_id' => $batch->id,
                    'slot_id' => $slot->id,
                    'item_id' => $slot->item_id,
                    'worker_id' => $slot->owner_worker,
                    'event' => 'EXPIRED_RECOVERED',
                    'occurred_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            if ($slots->isEmpty()) return 0;
            return DB::table('ai_bulk_runtime_slots')
                ->whereIn('id', $slots->pluck('id')->all())
                ->update(['status' => 'FREE', 'owner_worker' => null, 'item_id' => null, 'expires_at' => null, 'heartbeat_at' => null, 'updated_at' => now()]);
        });
    }
}
