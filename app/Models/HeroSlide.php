<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HeroSlide extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title', 'highlight_text', 'subtitle', 'description',
        'text_color', 'text_align', 'content_position',
        'background_type', 'background_color', 'gradient_from', 'gradient_to',
        'background_image', 'background_video', 'embed_url',
        'overlay_enabled', 'overlay_color', 'overlay_opacity',
        'cta_primary_text', 'cta_primary_url', 'cta_primary_style',
        'cta_secondary_text', 'cta_secondary_url', 'cta_secondary_style',
        'open_in_new_tab',
        'animation_type', 'duration_ms',
        'sort_order', 'is_active',
    ];

    protected $casts = [
        'overlay_enabled' => 'boolean',
        'overlay_opacity' => 'integer',
        'open_in_new_tab' => 'boolean',
        'duration_ms'     => 'integer',
        'sort_order'      => 'integer',
        'is_active'       => 'boolean',
    ];

    /* ---- Scopes ---- */

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    /* ---- Accessors ---- */

    public function getBackgroundImageUrlAttribute(): ?string
    {
        return $this->background_image ? media_url($this->background_image) : null;
    }

    public function getBackgroundVideoUrlAttribute(): ?string
    {
        return $this->background_video ? media_url($this->background_video) : null;
    }

    /**
     * Inline CSS for the slide background.
     */
    public function getBackgroundCssAttribute(): string
    {
        return match ($this->background_type) {
            'color'    => "background-color: {$this->background_color};",
            'gradient' => "background: linear-gradient(135deg, {$this->gradient_from}, {$this->gradient_to});",
            'image'    => "background-image: url('{$this->background_image_url}'); background-size: cover; background-position: center;",
            default    => '',
        };
    }
}
