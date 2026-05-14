<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiProductContentVersion extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'old_seo_json' => 'array',
            'old_merchant_json' => 'array',
            'old_tags_json' => 'array',
            'old_faq_json' => 'array',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
