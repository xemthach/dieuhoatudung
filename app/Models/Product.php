<?php

namespace App\Models;

use App\Enums\StockStatus;
use App\Services\Product\ProductBadgeService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::saving(function (Product $product) {
            if (\App\Services\AI\DraftOnlyWriteGuard::isActive()) {
                \App\Services\AI\DraftOnlyWriteGuard::block($product);

                return false;
            }

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
            'marketing_capacity_btu' => 'integer',
            'technical_capacity_btu' => 'integer',
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
            'price_includes_vat' => 'boolean',
            'promotion_start_at' => 'datetime',
            'promotion_end_at' => 'datetime',
            'technical_specs_overridden_at' => 'datetime',
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

    public function catalogSource(): BelongsTo
    {
        return $this->belongsTo(CatalogSource::class);
    }

    public function catalogModel(): BelongsTo
    {
        return $this->belongsTo(CatalogModel::class);
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

    public function promotions(): BelongsToMany
    {
        return $this->belongsToMany(Promotion::class)->withTimestamps();
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

    public function latestAiProductJobItem(): HasOne
    {
        return $this->hasOne(AiProductJobItem::class)->latestOfMany();
    }

    public function aiProductDrafts(): HasMany
    {
        return $this->hasMany(AiProductDraft::class);
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
    /**
     * Main display URL — used for product detail, OG, schema.
     */
    public function getMainImageUrlAttribute(): string
    {
        return app(\App\Services\Media\ProductMediaResolver::class)->mainUrl($this);
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
        return app(\App\Services\Media\ProductMediaResolver::class)->gallery($this);
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
