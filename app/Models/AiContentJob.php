<?php

namespace App\Models;

use App\Enums\AIContentJobStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiContentJob extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'input_payload' => 'array',
            'output_faq' => 'array',
            'output_tags' => 'array',
            'output_meta' => 'array',
            'output_internal_links' => 'array',
            'status' => AIContentJobStatus::class,
        ];
    }

    public function postCategory(): BelongsTo
    {
        return $this->belongsTo(PostCategory::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
