<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class GoogleAdsConversionImport extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'conversion_date_time' => 'datetime',
            'conversion_value' => 'decimal:2',
            'user_identifiers_json' => 'array',
            'payload_json' => 'array',
            'response_json' => 'array',
            'uploaded_at' => 'datetime',
            'last_attempted_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    public function source(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'source_type', 'source_id');
    }
}
