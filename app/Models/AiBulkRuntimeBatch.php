<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiBulkRuntimeBatch extends Model
{
    protected $table = 'ai_bulk_runtime_batches';
    protected $guarded = [];
    protected function casts(): array
    {
        return ['pause_requested_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }
}
