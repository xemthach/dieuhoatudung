<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HomeBenefitItem extends Model
{
    protected $fillable = [
        'title', 'subtitle',
        'icon_type', 'icon_name', 'icon_image', 'icon_svg',
        'icon_color', 'bg_color',
        'display_device', 'sort_order', 'is_active',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    /* ---- Scopes ---- */

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    public static function displayDeviceOptions(): array
    {
        return [
            'both' => 'Cả desktop & mobile',
            'desktop' => 'Chỉ desktop',
            'mobile' => 'Chỉ mobile',
        ];
    }

    /* ---- Accessors ---- */

    public function getIconImageUrlAttribute(): ?string
    {
        return $this->icon_image ? media_url($this->icon_image) : null;
    }

    /**
     * Sanitize SVG to remove dangerous elements.
     */
    public function getSanitizedSvgAttribute(): ?string
    {
        if (!$this->icon_svg) {
            return null;
        }

        $svg = $this->icon_svg;

        // Remove script tags
        $svg = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $svg);

        // Remove on* event handlers
        $svg = preg_replace('/\bon\w+\s*=\s*(["\']).*?\1/i', '', $svg);
        $svg = preg_replace('/\bon\w+\s*=\s*[^\s>]+/i', '', $svg);

        // Remove javascript: URLs
        $svg = preg_replace('/href\s*=\s*(["\'])javascript:.*?\1/i', '', $svg);
        $svg = preg_replace('/xlink:href\s*=\s*(["\'])javascript:.*?\1/i', '', $svg);

        return $svg;
    }

    /**
     * Map of allowed icon names → SVG paths (Heroicon outline style).
     */
    public static function iconSvgMap(): array
    {
        return [
            'shield-check'      => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
            'zap'               => 'M13 10V3L4 14h7v7l9-11h-7z',
            'clock'             => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
            'badge-dollar-sign' => 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z',
            'truck'             => 'M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0',
            'wrench'            => 'M14.7 6.3a1 1 0 000 1.4l1.6 1.6a1 1 0 001.4 0l3.77-3.77a6 6 0 01-7.94 7.94l-6.91 6.91a2.12 2.12 0 01-3-3l6.91-6.91a6 6 0 017.94-7.94l-3.76 3.76z',
            'check-circle'     => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
        ];
    }
}
