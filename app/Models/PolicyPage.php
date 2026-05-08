<?php

namespace App\Models;

use App\Enums\PolicyType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class PolicyPage extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type'              => PolicyType::class,
            'is_active'         => 'boolean',
            'display_locations' => 'array',
        ];
    }

    // ─── Scopes ────────────────────────────────────────────
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeDisplayedIn(Builder $query, string $location): Builder
    {
        return $query->whereJsonContains('display_locations', $location);
    }

    // ─── Accessors ─────────────────────────────────────────
    public function getPublicUrlAttribute(): string
    {
        return route('policy-pages.show', $this->slug);
    }

    public function getSeoTitleComputedAttribute(): string
    {
        return $this->seo_title ?: $this->title;
    }

    public function getSeoDescriptionComputedAttribute(): string
    {
        return $this->seo_description ?: \Illuminate\Support\Str::limit(strip_tags($this->content), 160);
    }
}
