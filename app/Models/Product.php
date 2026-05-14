<?php

namespace App\Models;

use App\Enums\StockStatus;
use App\Services\Product\ProductBadgeService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::saving(function (Product $product): void {
            if (blank($product->slug)) {
                $product->slug = static::generateUniqueSlug($product->name, $product->getKey());

                return;
            }

            if ($product->isDirty('slug')) {
                $product->slug = static::generateUniqueSlug($product->slug, $product->getKey());
            }
        });
    }

    protected function casts(): array
    {
        return [
            'stock_status' => StockStatus::class,
            'specs_json' => 'array',
            'gallery_json' => 'array',
            'documents_json' => 'array',
            'btu' => 'integer',
            'capacity_kw' => 'decimal:2',
            'hp' => 'decimal:1',
            'inverter' => 'boolean',
            'is_featured' => 'boolean',
            'is_bestseller' => 'boolean',
            'is_new' => 'boolean',
            'is_active' => 'boolean',
            'schema_enabled' => 'boolean',
            'ai_score' => 'integer',
            'ai_warning_count' => 'integer',
            'ai_last_run_at' => 'datetime',
            'ai_generated_at' => 'datetime',
            'identifier_exists' => 'boolean',
            'regular_price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'promotion_start_at' => 'datetime',
            'promotion_end_at' => 'datetime',
        ];
    }

    public static function generateUniqueSlug(?string $source, ?int $ignoreId = null): string
    {
        $base = Str::slug($source ?: 'product');

        if ($base === '') {
            $base = 'product';
        }

        $base = Str::limit($base, 200, '');
        $slug = $base;
        $counter = 1;

        while (static::withTrashed()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()
        ) {
            $suffix = '-'.$counter++;
            $slug = Str::limit($base, 200 - strlen($suffix), '').$suffix;
        }

        return $slug;
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
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

    public function documents(): HasMany
    {
        return $this->hasMany(ProductDocument::class)->orderBy('sort_order');
    }

    public function publicDocuments(): HasMany
    {
        return $this->documents()->where('is_public', true);
    }

    public function testimonials(): HasMany
    {
        return $this->hasMany(Testimonial::class);
    }

    public function activeTestimonials(): HasMany
    {
        return $this->testimonials()->where('is_active', true)->orderBy('sort_order');
    }

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class);
    }

    public function relatedProducts(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_related', 'product_id', 'related_product_id');
    }

    public function relatedToProducts(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_related', 'related_product_id', 'product_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    public function approvedReviews(): HasMany
    {
        return $this->reviews()->where('status', 'approved')->latest();
    }

    public function questions(): HasMany
    {
        return $this->hasMany(ProductQuestion::class);
    }

    public function aiProductJobItems(): HasMany
    {
        return $this->hasMany(AiProductJobItem::class);
    }

    public function aiContentVersions(): HasMany
    {
        return $this->hasMany(AiProductContentVersion::class);
    }

    public function publicQuestions(): HasMany
    {
        return $this->questions()
            ->where('is_public', true)
            ->whereIn('status', ['approved', 'answered'])
            ->latest();
    }

    public function getAverageRatingAttribute(): ?float
    {
        $avg = $this->approvedReviews()->avg('rating');

        return $avg ? round($avg, 1) : null;
    }

    public function getReviewCountAttribute(): int
    {
        return $this->approvedReviews()->count();
    }

    public function getBadgesAttribute(): array
    {
        return app(ProductBadgeService::class)->getBadges($this);
    }

    /**
     * Canonical fallback URL for a product image.
     * Priority: main_image → gallery_json[0] → site setting → public asset
     */
    private function productImageFallback(): string
    {
        $settingPath = setting('product_detail.default_product_image');
        if (! empty($settingPath)) {
            return media_url($settingPath);
        }

        return asset('images/placeholders/product-default.jpg');
    }

    /**
     * Main display URL — used for product detail, OG, schema.
     */
    public function getMainImageUrlAttribute(): string
    {
        if (! empty($this->main_image)) {
            return media_url($this->main_image);
        }
        if (is_array($this->gallery_json)) {
            foreach ($this->gallery_json as $img) {
                if (! empty($img)) {
                    return media_url($img);
                }
            }
        }

        return $this->productImageFallback();
    }

    /**
     * Card thumbnail URL — identical logic, kept separate for semantic clarity.
     */
    public function getCardImageUrlAttribute(): string
    {
        return $this->main_image_url;
    }

    public function getGalleryImagesAttribute(): array
    {
        $images = [];

        if (! empty($this->main_image)) {
            $images[] = [
                'url' => media_url($this->main_image),
                'path' => $this->main_image,
                'alt' => $this->name,
            ];
        }

        if (is_array($this->gallery_json)) {
            foreach ($this->gallery_json as $img) {
                if (! empty($img)) {
                    $images[] = [
                        'url' => media_url($img),
                        'path' => $img,
                        'alt' => $this->name,
                    ];
                }
            }
        }

        // If no real images exist, inject the default image so gallery/lightbox is never empty
        if (empty($images)) {
            $fallback = $this->productImageFallback();
            $images[] = [
                'url' => $fallback,
                'path' => '',
                'alt' => $this->name,
            ];
        }

        return collect($images)->unique('url')->values()->all();
    }

    public function getGalleryImageUrlsAttribute(): array
    {
        return collect($this->gallery_images)->pluck('url')->all();
    }

    public function getCompareImageUrlAttribute(): string
    {
        return $this->main_image_url;
    }
}
