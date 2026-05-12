<?php

namespace App\Models;

use App\Enums\TagStatus;
use App\Enums\TagType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Tag extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::saving(function (Tag $tag): void {
            if (blank($tag->slug)) {
                $tag->slug = static::generateUniqueSlug($tag->name, $tag->getKey());
                return;
            }

            if ($tag->isDirty('slug')) {
                $tag->slug = static::generateUniqueSlug($tag->slug, $tag->getKey());
            }
        });
    }

    protected function casts(): array
    {
        return [
            'type' => TagType::class,
            'status' => TagStatus::class,
            'is_indexable' => 'boolean',
        ];
    }

    public static function generateUniqueSlug(?string $source, ?int $ignoreId = null): string
    {
        $base = Str::slug($source ?: 'tag');

        if ($base === '') {
            $base = 'tag';
        }

        $base = Str::limit($base, 200, '');
        $slug = $base;
        $counter = 1;

        while (static::withTrashed()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()
        ) {
            $suffix = '-' . $counter++;
            $slug = Str::limit($base, 200 - strlen($suffix), '') . $suffix;
        }

        return $slug;
    }

    public function products(): MorphToMany
    {
        return $this->morphedByMany(Product::class, 'taggable');
    }

    public function posts(): MorphToMany
    {
        return $this->morphedByMany(Post::class, 'taggable');
    }

    public function caseStudies(): MorphToMany
    {
        return $this->morphedByMany(CaseStudy::class, 'taggable');
    }
}
