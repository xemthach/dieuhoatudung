<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Redirect extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active'   => 'boolean',
            'hit_count'   => 'integer',
            'status_code' => 'integer',
            'last_hit_at' => 'datetime',
        ];
    }

    /**
     * Normalize a URL to its path only (strip domain) for comparison.
     */
    public static function normalizePath(string $url): string
    {
        $parsed = parse_url($url);
        $path = $parsed['path'] ?? $url;
        return '/' . ltrim($path, '/');
    }

    /**
     * Find an active redirect for the given path.
     */
    public static function findActiveForPath(string $path): ?self
    {
        $normalized = '/' . ltrim($path, '/');

        return static::where('is_active', true)
            ->where(function ($query) use ($normalized) {
                $query->where('source_url', $normalized)
                      ->orWhere('source_url', 'like', '%' . parse_url($normalized, PHP_URL_PATH));
            })
            ->first();
    }

    /**
     * Record a hit on this redirect.
     */
    public function recordHit(): void
    {
        $this->increment('hit_count');
        $this->update(['last_hit_at' => now()]);
    }

    /**
     * Check if a redirect would create a loop.
     */
    public static function wouldCreateLoop(string $source, string $target): bool
    {
        $sourceNorm = self::normalizePath($source);
        $targetNorm = self::normalizePath($target);

        if ($sourceNorm === $targetNorm) {
            return true;
        }

        // Check A → B and B → A pattern
        return static::where('source_url', $targetNorm)
            ->where('target_url', $sourceNorm)
            ->exists();
    }
}
