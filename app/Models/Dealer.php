<?php

namespace App\Models;

use App\Services\Dealer\DealerValidation;
use App\Services\SlugGeneratorService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Dealer extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7', 'longitude' => 'decimal:7', 'is_active' => 'boolean',
            'is_featured' => 'boolean', 'is_verified' => 'boolean', 'review_enabled' => 'boolean',
            'reviews_visible' => 'boolean', 'is_indexable' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Dealer $dealer) {
            if (blank($dealer->code)) {
                $dealer->code = (string) Str::ulid();
            }
            if (blank($dealer->slug) || $dealer->isDirty('slug')) {
                $dealer->slug = app(SlugGeneratorService::class)->unique(self::class,
                    $dealer->slug ?: $dealer->name, $dealer->id, fallback: 'dai-ly');
            }
            app(DealerValidation::class)->validate($dealer->getAttributes());
        });
    }

    public function scopePubliclyListed(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function province()
    {
        return $this->belongsTo(AdministrativeUnit::class, 'province_id');
    }

    public function ward()
    {
        return $this->belongsTo(AdministrativeUnit::class, 'ward_id');
    }

    public function legacyProvince()
    {
        return $this->belongsTo(AdministrativeUnit::class, 'legacy_province_id');
    }

    public function legacyDistrict()
    {
        return $this->belongsTo(AdministrativeUnit::class, 'legacy_district_id');
    }

    public function legacyWard()
    {
        return $this->belongsTo(AdministrativeUnit::class, 'legacy_ward_id');
    }

    public function brands()
    {
        return $this->belongsToMany(Brand::class, 'dealer_brand');
    }

    public function categories()
    {
        return $this->belongsToMany(ProductCategory::class, 'dealer_product_category');
    }

    public function services()
    {
        return $this->belongsToMany(DealerService::class, 'dealer_dealer_service');
    }

    public function reviews()
    {
        return $this->hasMany(DealerReview::class);
    }

    public function publicReviews()
    {
        return $this->reviews()->published();
    }

    public function getFullAddressAttribute(): string
    {
        return implode(', ', array_filter([$this->address_line, $this->ward?->full_name, $this->province?->full_name]));
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo ? media_url($this->logo) : null;
    }

    public function getMapLinksAttribute(): array
    {
        if ($this->latitude === null || $this->longitude === null) {
            return [];
        }
        $pair = $this->latitude.','.$this->longitude;

        return [
            'google' => 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($pair),
            'directions' => 'https://www.google.com/maps/dir/?api=1&destination='.rawurlencode($pair),
            'osm' => 'https://www.openstreetmap.org/?mlat='.$this->latitude.'&mlon='.$this->longitude.'#map=16/'.$this->latitude.'/'.$this->longitude,
        ];
    }
}
