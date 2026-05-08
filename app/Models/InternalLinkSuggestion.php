<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class InternalLinkSuggestion extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'score'  => 'integer',
            'status' => 'string',
        ];
    }

    /**
     * The entity that needs an internal link (e.g. a Post being edited).
     */
    public function source(): MorphTo
    {
        return $this->morphTo('source');
    }

    /**
     * The entity being recommended as a link target.
     */
    public function target(): MorphTo
    {
        return $this->morphTo('target');
    }

    // ─── Scopes ───────────────────────────────────────────

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeForSource($query, string $type, int $id)
    {
        return $query->where('source_type', $type)->where('source_id', $id);
    }

    // ─── Helpers ──────────────────────────────────────────

    public function getTargetUrlAttribute(): ?string
    {
        $target = $this->target;
        if (! $target) return null;

        return match (true) {
            $target instanceof \App\Models\Post            => route('blog.show', $target->slug),
            $target instanceof \App\Models\Product         => route('product.show', $target->slug),
            $target instanceof \App\Models\ProductCategory => route('category.show', $target->slug),
            $target instanceof \App\Models\CaseStudy       => route('case-studies.show', $target->slug),
            default => null,
        };
    }

    public function getTargetTitleAttribute(): string
    {
        $target = $this->target;
        if (! $target) return 'N/A';

        return match (true) {
            $target instanceof \App\Models\Post            => $target->title,
            $target instanceof \App\Models\Product         => $target->name,
            $target instanceof \App\Models\ProductCategory => $target->name,
            $target instanceof \App\Models\CaseStudy       => $target->title,
            default => 'Unknown',
        };
    }
}
