<?php

namespace App\Models;

use App\Enums\CaseStudyStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class CaseStudy extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'product_ids' => 'array',
            'gallery_json' => 'array',
            'status' => CaseStudyStatus::class,
            'published_at' => 'datetime',
            'completion_date' => 'date',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    public function faqs(): MorphToMany
    {
        return $this->morphToMany(Faq::class, 'faqable')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function activeFaqs(): MorphToMany
    {
        return $this->faqs()->where('faqs.is_active', true);
    }

    public function testimonials(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Testimonial::class);
    }

    public function activeTestimonials(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->testimonials()->where('is_active', true)->orderBy('sort_order');
    }

    public function getCoverImageUrlAttribute(): ?string
    {
        return $this->cover_image ? media_url($this->cover_image) : null;
    }

    public function getOgImageUrlAttribute(): ?string
    {
        return $this->og_image ? media_url($this->og_image) : ($this->cover_image_url ?? null);
    }

    public function getGalleryImageUrlsAttribute(): array
    {
        $urls = [];
        if (is_array($this->gallery_json)) {
            foreach ($this->gallery_json as $img) {
                if (!empty($img)) {
                    $urls[] = media_url($img);
                }
            }
        }
        return array_unique($urls);
    }
}
