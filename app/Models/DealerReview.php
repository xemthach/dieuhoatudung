<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DealerReview extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $hidden = ['customer_email', 'customer_phone', 'admin_note', 'user_id'];

    protected function casts(): array
    {
        return ['rating' => 'integer', 'is_visible' => 'boolean', 'approved_at' => 'datetime', 'moderated_at' => 'datetime'];
    }

    public function dealer()
    {
        return $this->belongsTo(Dealer::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'approved')->where('is_visible', true)
            ->whereHas('dealer', fn (Builder $dealer) => $dealer->publiclyListed()->where('reviews_visible', true));
    }
}
