<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SiteCampaign extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title',
        'type',
        'status',
        'placement',
        'device',
        'content_json',
        'design_json',
        'targeting_json',
        'schedule_json',
        'frequency_json',
        'tracking_json',
        'priority',
        'start_at',
        'end_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'content_json' => 'array',
        'design_json' => 'array',
        'targeting_json' => 'array',
        'schedule_json' => 'array',
        'frequency_json' => 'array',
        'tracking_json' => 'array',
        'priority' => 'integer',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
    ];

    public function events(): HasMany
    {
        return $this->hasMany(SiteCampaignEvent::class);
    }

    public function scopeActive($query)
    {
        $now = now();

        return $query
            ->where('status', 'active')
            ->where(function ($query) use ($now) {
                $query->whereNull('start_at')->orWhere('start_at', '<=', $now);
            })
            ->where(function ($query) use ($now) {
                $query->whereNull('end_at')->orWhere('end_at', '>=', $now);
            })
            ->orderByDesc('priority')
            ->orderByDesc('id');
    }

    public static function typeOptions(): array
    {
        return [
            'modal' => 'Popup modal',
            'slide_in' => 'Slide-in popup',
            'top_bar' => 'Top announcement bar',
            'bottom_bar' => 'Bottom sticky bar',
            'floating_cta' => 'Floating CTA',
            'image_popup' => 'Image/banner popup',
            'video_popup' => 'Video popup',
            'product_promo' => 'Product promo popup',
        ];
    }

    public static function placementOptions(): array
    {
        return [
            'all' => 'All pages',
            'home' => 'Home page',
            'product' => 'Product detail',
            'product_category' => 'Product category',
            'brand' => 'Brand page',
            'blog_list' => 'Blog list',
            'blog_post' => 'Blog post detail',
            'policy' => 'Policy page',
            'quote' => 'Quote page',
            'search' => 'Search page',
            'compare' => 'Compare page',
        ];
    }

    public static function deviceOptions(): array
    {
        return [
            'both' => 'Desktop & mobile',
            'desktop' => 'Desktop only',
            'mobile' => 'Mobile only',
        ];
    }

    public function getImpressionsCountAttribute(): int
    {
        return (int) $this->events()->where('event_type', 'impression')->count();
    }

    public function getClicksCountAttribute(): int
    {
        return (int) $this->events()
            ->whereIn('event_type', ['click_primary', 'click_secondary'])
            ->count();
    }

    public function getCtrAttribute(): float
    {
        $impressions = $this->impressions_count;

        return $impressions > 0 ? round(($this->clicks_count / $impressions) * 100, 2) : 0.0;
    }
}
