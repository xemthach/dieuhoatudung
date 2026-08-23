<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiBulkApplyBatch extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['manifest_json' => 'array'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(AiBulkApplyItem::class, 'batch_id');
    }
}
