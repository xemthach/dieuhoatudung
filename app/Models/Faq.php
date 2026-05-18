<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Str;

class Faq extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active'   => 'boolean',
            'sort_order'  => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Faq $faq): void {
            $faq->normalized_search_text = static::normalizeSearchText(
                trim(implode(' ', array_filter([
                    $faq->group,
                    $faq->question,
                    strip_tags((string) $faq->answer),
                ])))
            );
        });
    }

    public static function normalizeSearchText(string $text): string
    {
        return Str::ascii(Str::lower($text));
    }

    // ─── Inverse polymorphic relations ─────────────────────────────────────

    public function products(): MorphToMany
    {
        return $this->morphedByMany(Product::class, 'faqable')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function posts(): MorphToMany
    {
        return $this->morphedByMany(Post::class, 'faqable')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function productCategories(): MorphToMany
    {
        return $this->morphedByMany(ProductCategory::class, 'faqable')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function postCategories(): MorphToMany
    {
        return $this->morphedByMany(PostCategory::class, 'faqable')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function caseStudies(): MorphToMany
    {
        return $this->morphedByMany(CaseStudy::class, 'faqable')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }
}
