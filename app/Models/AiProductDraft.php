<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiProductDraft extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'raw_output_json' => 'array',
            'normalized_output_json' => 'array',
            'field_status_json' => 'array',
            'validation_errors_json' => 'array',
            'warnings_json' => 'array',
            'used_verified_facts_json' => 'array',
            'token_usage_json' => 'array',
            'approved_identity_json' => 'array',
            'approved_fields_json' => 'array',
            'approved_at' => 'datetime',
            'applied_at' => 'datetime',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(AiProductJob::class, 'job_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
