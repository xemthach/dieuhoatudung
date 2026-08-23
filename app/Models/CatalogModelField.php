<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogModelField extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'confidence_score' => 'decimal:2',
            'verified_at' => 'datetime',
        ];
    }

    public function catalogModel(): BelongsTo
    {
        return $this->belongsTo(CatalogModel::class);
    }
}
