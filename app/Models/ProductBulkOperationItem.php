<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductBulkOperationItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['metadata_json' => 'array'];
    }

    public function operation(): BelongsTo
    {
        return $this->belongsTo(ProductBulkOperation::class, 'operation_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function draft(): BelongsTo
    {
        return $this->belongsTo(AiProductDraft::class);
    }
}
