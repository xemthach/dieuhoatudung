<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class SlugGeneratorService
{
    public function normalize(?string $value, string $fallback = 'item', int $maxLength = 200): string
    {
        $slug = Str::of((string) ($value ?: $fallback))
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '-')
            ->replaceMatches('/-+/', '-')
            ->trim('-')
            ->toString();

        if ($slug === '') {
            $slug = $fallback;
        }

        return Str::limit($slug, $maxLength, '');
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    public function unique(
        string $modelClass,
        ?string $source,
        ?int $ignoreId = null,
        string $column = 'slug',
        string $fallback = 'item',
        int $firstSuffix = 2,
        int $maxLength = 200,
    ): string {
        $base = $this->normalize($source, $fallback, $maxLength);
        $slug = $base;
        $counter = $firstSuffix;

        while ($this->exists($modelClass, $column, $slug, $ignoreId)) {
            $suffix = '-'.$counter++;
            $slug = Str::limit($base, $maxLength - strlen($suffix), '').$suffix;
        }

        return $slug;
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function exists(string $modelClass, string $column, string $slug, ?int $ignoreId): bool
    {
        $query = in_array(SoftDeletes::class, class_uses_recursive($modelClass), true)
            ? $modelClass::withTrashed()
            : $modelClass::query();

        return $query
            ->where($column, $slug)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();
    }
}
