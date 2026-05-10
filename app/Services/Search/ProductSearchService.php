<?php

namespace App\Services\Search;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Product search service with weighted scoring, normalization, and caching.
 *
 * Scoring:
 *   Exact SKU              = 100
 *   Exact model_code       = 95
 *   Exact indoor/outdoor   = 90
 *   Prefix model           = 80
 *   Brand + BTU            = 70
 *   Name contains          = 60
 *   Category contains      = 40
 */
class ProductSearchService
{
    /** Maximum suggestions in autocomplete */
    public const SUGGEST_LIMIT = 8;

    /** Cache TTL for suggest queries (seconds) */
    public const SUGGEST_CACHE_TTL = 600; // 10 minutes

    /** Maximum query length */
    public const MAX_QUERY_LENGTH = 80;

    /** Minimum query length */
    public const MIN_QUERY_LENGTH = 2;

    /**
     * Vietnamese diacritics mapping for accent-insensitive search.
     */
    private const VIET_MAP = [
        'à' => 'a', 'á' => 'a', 'ả' => 'a', 'ã' => 'a', 'ạ' => 'a',
        'ă' => 'a', 'ằ' => 'a', 'ắ' => 'a', 'ẳ' => 'a', 'ẵ' => 'a', 'ặ' => 'a',
        'â' => 'a', 'ầ' => 'a', 'ấ' => 'a', 'ẩ' => 'a', 'ẫ' => 'a', 'ậ' => 'a',
        'đ' => 'd',
        'è' => 'e', 'é' => 'e', 'ẻ' => 'e', 'ẽ' => 'e', 'ẹ' => 'e',
        'ê' => 'e', 'ề' => 'e', 'ế' => 'e', 'ể' => 'e', 'ễ' => 'e', 'ệ' => 'e',
        'ì' => 'i', 'í' => 'i', 'ỉ' => 'i', 'ĩ' => 'i', 'ị' => 'i',
        'ò' => 'o', 'ó' => 'o', 'ỏ' => 'o', 'õ' => 'o', 'ọ' => 'o',
        'ô' => 'o', 'ồ' => 'o', 'ố' => 'o', 'ổ' => 'o', 'ỗ' => 'o', 'ộ' => 'o',
        'ơ' => 'o', 'ờ' => 'o', 'ớ' => 'o', 'ở' => 'o', 'ỡ' => 'o', 'ợ' => 'o',
        'ù' => 'u', 'ú' => 'u', 'ủ' => 'u', 'ũ' => 'u', 'ụ' => 'u',
        'ư' => 'u', 'ừ' => 'u', 'ứ' => 'u', 'ử' => 'u', 'ữ' => 'u', 'ự' => 'u',
        'ỳ' => 'y', 'ý' => 'y', 'ỷ' => 'y', 'ỹ' => 'y', 'ỵ' => 'y',
    ];

    /**
     * Normalize a search query for safe and flexible matching.
     */
    public static function normalizeQuery(string $q): string
    {
        // Trim whitespace
        $q = trim($q);

        // Strip HTML tags
        $q = strip_tags($q);

        // Remove control characters (keep printable + Unicode)
        $q = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $q);

        // Normalize multiple spaces
        $q = preg_replace('/\s+/u', ' ', $q);

        // Normalize slashes (backslash → forward slash)
        $q = str_replace('\\', '/', $q);

        // Limit length
        $q = mb_substr($q, 0, self::MAX_QUERY_LENGTH);

        return $q;
    }

    /**
     * Remove Vietnamese diacritics from a string.
     */
    public static function removeDiacritics(string $str): string
    {
        return strtr(mb_strtolower($str), self::VIET_MAP);
    }

    /**
     * Extract numeric BTU value from query (e.g. "24000", "24.000 BTU", "24000BTU").
     */
    private static function extractBtu(string $q): ?int
    {
        // Match plain number (24000, 48000) or formatted (24.000, 24,000), optionally followed by BTU
        if (preg_match('/(\d[\d.,]*)\s*(?:btu)?(?:\s|$)/i', $q, $m)) {
            $num = (int)str_replace(['.', ','], '', $m[1]);
            // Only valid BTU ranges for HVAC (9000-200000)
            if ($num >= 9000 && $num <= 200000) {
                return $num;
            }
        }
        return null;
    }

    /**
     * Get autocomplete suggestions (cached).
     *
     * @return array<int, array{id: int, name: string, model: string, sku: string, brand: string, btu: ?int, image: string, url: string, score: int}>
     */
    public function suggest(string $rawQuery): array
    {
        $q = self::normalizeQuery($rawQuery);

        if (mb_strlen($q) < self::MIN_QUERY_LENGTH) {
            return [];
        }

        $cacheKey = 'search:suggest:' . md5(mb_strtolower($q));

        return Cache::remember($cacheKey, self::SUGGEST_CACHE_TTL, function () use ($q) {
            return $this->executeSearch($q, self::SUGGEST_LIMIT);
        });
    }

    /**
     * Full search with pagination.
     */
    public function search(string $rawQuery, int $perPage = 12): LengthAwarePaginator
    {
        $q = self::normalizeQuery($rawQuery);

        if (mb_strlen($q) < self::MIN_QUERY_LENGTH) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);
        }

        // Get all scored results then paginate
        $results = $this->executeSearch($q, 200);
        $ids = collect($results)->pluck('id')->toArray();

        if (empty($ids)) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);
        }

        // Fetch paginated from DB preserving score order
        $page = request()->input('page', 1);
        $offset = ($page - 1) * $perPage;
        $pageIds = array_slice($ids, $offset, $perPage);

        if (empty($pageIds)) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage, $page);
        }

        $products = Product::with(['brand', 'category'])
            ->whereIn('id', $pageIds)
            ->get()
            ->sortBy(function ($product) use ($pageIds) {
                return array_search($product->id, $pageIds);
            })
            ->values();

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $products,
            count($ids),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    /**
     * Execute the scored search query.
     *
     * @return array<int, array>
     */
    private function executeSearch(string $q, int $limit): array
    {
        $qLower = mb_strtolower($q);
        $qNoAccent = self::removeDiacritics($q);
        $btu = self::extractBtu($q);

        // Split query into tokens for multi-model search (e.g. "GU71T/A1-K GU71W/A1-K")
        $tokens = preg_split('/\s+/', $q);
        $likeQ = '%' . str_replace(['%', '_'], ['\%', '\_'], $qLower) . '%';
        $likeNoAccent = '%' . str_replace(['%', '_'], ['\%', '\_'], $qNoAccent) . '%';

        // Base query: only active products
        $products = Product::with(['brand', 'category'])
            ->where('is_active', true)
            ->where(function ($query) use ($qLower, $qNoAccent, $likeQ, $likeNoAccent, $btu, $tokens) {
                // Exact match fields
                $query->where(DB::raw('LOWER(model_code)'), $qLower)
                    ->orWhere(DB::raw('LOWER(sku)'), $qLower)
                    // Partial match fields
                    ->orWhere(DB::raw('LOWER(name)'), 'LIKE', $likeQ)
                    ->orWhere(DB::raw('LOWER(slug)'), 'LIKE', $likeQ)
                    ->orWhere(DB::raw('LOWER(model_code)'), 'LIKE', $likeQ)
                    ->orWhere(DB::raw('LOWER(sku)'), 'LIKE', $likeQ);

                // BTU match
                if ($btu) {
                    $query->orWhere('btu', $btu);
                }

                // Search in specs_json for indoor/outdoor model
                $query->orWhereRaw('LOWER(JSON_EXTRACT(specs_json, \'$[*].value\')) LIKE ?', [$likeQ]);

                // Multi-token: each token as separate OR
                if (count($tokens) > 1) {
                    foreach ($tokens as $token) {
                        $token = mb_strtolower(trim($token));
                        if (mb_strlen($token) >= 2) {
                            $tokenLike = '%' . str_replace(['%', '_'], ['\%', '\_'], $token) . '%';
                            $query->orWhere(DB::raw('LOWER(model_code)'), 'LIKE', $tokenLike)
                                ->orWhere(DB::raw('LOWER(sku)'), 'LIKE', $tokenLike)
                                ->orWhere(DB::raw('LOWER(name)'), 'LIKE', $tokenLike);
                        }
                    }
                }

                // Accent-insensitive name search (for Vietnamese)
                if ($qNoAccent !== $qLower) {
                    $query->orWhere(DB::raw('LOWER(slug)'), 'LIKE', $likeNoAccent);
                }
            })
            ->limit(200)
            ->get();

        // Score and sort
        $scored = [];
        foreach ($products as $product) {
            $score = $this->calculateScore($product, $qLower, $qNoAccent, $btu);
            if ($score <= 0) {
                continue;
            }

            // Boost featured/bestseller
            if ($product->is_featured) $score += 5;
            if ($product->is_bestseller) $score += 3;

            $scored[] = [
                'id'       => $product->id,
                'name'     => $product->name,
                'model'    => $product->model_code,
                'sku'      => $product->sku,
                'brand'    => $product->brand?->name ?? '',
                'btu'      => $product->btu,
                'category' => $product->category?->name ?? '',
                'image'    => $product->main_image_url,
                'url'      => route('product.show', $product->slug),
                'price'    => $product->sale_price ?? $product->regular_price,
                'score'    => $score,
            ];
        }

        // Sort by score desc, then sort_order
        usort($scored, function ($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        return array_slice($scored, 0, $limit);
    }

    /**
     * Calculate relevance score for a product.
     */
    private function calculateScore(Product $product, string $qLower, string $qNoAccent, ?int $btu): int
    {
        $score = 0;

        $sku = mb_strtolower($product->sku ?? '');
        $model = mb_strtolower($product->model_code ?? '');
        $name = mb_strtolower($product->name ?? '');
        $slug = mb_strtolower($product->slug ?? '');
        $brand = mb_strtolower($product->brand?->name ?? '');
        $category = mb_strtolower($product->category?->name ?? '');

        // Extract indoor/outdoor model from specs_json
        $indoorModel = '';
        $outdoorModel = '';
        if (is_array($product->specs_json)) {
            foreach ($product->specs_json as $spec) {
                if (is_array($spec)) {
                    $key = $spec['key'] ?? '';
                    $val = mb_strtolower($spec['value'] ?? '');
                    if ($key === 'indoor_model') $indoorModel = $val;
                    if ($key === 'outdoor_model') $outdoorModel = $val;
                }
            }
        }

        // === EXACT MATCHES ===

        // Exact SKU
        if ($sku && $sku === $qLower) {
            $score = max($score, 100);
        }

        // Exact model_code
        if ($model && $model === $qLower) {
            $score = max($score, 95);
        }

        // Exact indoor/outdoor model
        if ($indoorModel && $indoorModel === $qLower) {
            $score = max($score, 90);
        }
        if ($outdoorModel && $outdoorModel === $qLower) {
            $score = max($score, 90);
        }

        // === PREFIX MATCHES ===

        // Model starts with query
        if ($model && str_starts_with($model, $qLower)) {
            $score = max($score, 80);
        }
        // SKU starts with query
        if ($sku && str_starts_with($sku, $qLower)) {
            $score = max($score, 80);
        }

        // === PARTIAL MODEL/SKU MATCH ===
        if ($model && str_contains($model, $qLower)) {
            $score = max($score, 75);
        }
        if ($sku && str_contains($sku, $qLower)) {
            $score = max($score, 75);
        }

        // Indoor/outdoor partial
        if ($indoorModel && str_contains($indoorModel, $qLower)) {
            $score = max($score, 72);
        }
        if ($outdoorModel && str_contains($outdoorModel, $qLower)) {
            $score = max($score, 72);
        }

        // === BRAND + BTU ===
        if ($btu && $product->btu === $btu) {
            $score = max($score, 70);
            // Boost if brand also matches
            if ($brand && str_contains($qLower, $brand)) {
                $score = max($score, 85);
            }
        }

        // === NAME CONTAINS ===
        if (str_contains($name, $qLower)) {
            $score = max($score, 60);
        }

        // Accent-insensitive name match
        if ($qNoAccent !== $qLower && str_contains($slug, $qNoAccent)) {
            $score = max($score, 58);
        }

        // === CATEGORY CONTAINS ===
        if ($category && str_contains($category, $qLower)) {
            $score = max($score, 40);
        }

        // === MULTI-TOKEN MATCH ===
        // If query has multiple words, check each
        $tokens = preg_split('/\s+/', $qLower);
        if (count($tokens) > 1) {
            $matchedTokens = 0;
            foreach ($tokens as $token) {
                if (mb_strlen($token) < 2) continue;
                if (str_contains($name, $token) || str_contains($model, $token)
                    || str_contains($sku, $token) || str_contains($brand, $token)
                    || str_contains($slug, $token) || str_contains($category, $token)) {
                    $matchedTokens++;
                }
            }
            if ($matchedTokens > 0) {
                $tokenScore = 30 + ($matchedTokens * 10);
                $score = max($score, min($tokenScore, 65));
            }
        }

        return $score;
    }

    /**
     * Log a search query for analytics.
     */
    public function logSearch(string $rawQuery, string $normalizedQuery, int $resultCount, ?string $ip = null, ?string $userAgent = null): void
    {
        try {
            DB::table('search_logs')->insert([
                'query'            => mb_substr($rawQuery, 0, 200),
                'normalized_query' => mb_substr($normalizedQuery, 0, 200),
                'result_count'     => $resultCount,
                'ip_hash'          => $ip ? hash('sha256', $ip . config('app.key')) : null,
                'user_agent_hash'  => $userAgent ? hash('sha256', mb_substr($userAgent, 0, 500)) : null,
                'created_at'       => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to log search query', ['error' => $e->getMessage()]);
        }
    }
}
