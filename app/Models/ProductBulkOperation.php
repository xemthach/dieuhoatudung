<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductBulkOperation extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'product_ids_json' => 'array',
            'filters_json' => 'array',
            'summary_json' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProductBulkOperationItem::class, 'operation_id');
    }
}
