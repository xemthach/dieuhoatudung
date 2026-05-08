<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductReview extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'images_json' => 'array',
            'is_verified_purchase' => 'boolean',
            'rating' => 'integer',
            'helpful_count' => 'integer',
            'replied_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Get image URLs from images_json field.
     */
    public function getImageUrlsAttribute(): array
    {
        if (empty($this->images_json)) {
            return [];
        }

        return collect($this->images_json)
            ->map(fn ($path) => media_url($path))
            ->filter()
            ->values()
            ->all();
    }
}
