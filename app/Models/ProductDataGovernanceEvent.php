<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class ProductDataGovernanceEvent extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Product data governance events are append-only.');
        });
        static::deleting(static function (): never {
            throw new LogicException('Product data governance events are append-only.');
        });
    }

    protected function casts(): array
    {
        return [
            'product_ids_json' => 'array',
            'descriptor_json' => 'array',
            'before_json' => 'array',
            'after_json' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }
}
