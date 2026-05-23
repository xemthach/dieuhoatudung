<?php

namespace App\Models;

use App\Services\SlugGeneratorService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Brand extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::saving(function (Brand $brand): void {
            $slugger = app(SlugGeneratorService::class);

            if (blank($brand->slug)) {
                $brand->slug = $slugger->unique(static::class, $brand->name, $brand->getKey(), fallback: 'brand');

                return;
            }

            if ($brand->isDirty('slug')) {
                $brand->slug = $slugger->unique(static::class, $brand->slug, $brand->getKey(), fallback: 'brand');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function promotions(): BelongsToMany
    {
        return $this->belongsToMany(Promotion::class)->withTimestamps();
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo ? media_url($this->logo) : null;
    }
}
