<?php

namespace App\Services\Product;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;

class ProductFilterService
{
    public const MAX_ARRAY_VALUES = 20;
    public const MAX_FILTER_STRING_LENGTH = 100;
    public const MAX_PRICE_FILTER = 1_000_000_000_000;

    /** @var list<string> */
    public const SORT_OPTIONS = ['latest', 'price_asc', 'price_desc', 'btu_asc', 'btu_desc'];

    /** @var array<string, string> */
    public const CAPACITY_FILTER_OPTIONS = [
        // The query is an inclusive SQL BETWEEN range. Keep copy explicit so a
        // 12,000 BTU product is not presented as an accidental boundary match.
        '9000-12000' => '9.000 - 12.000 BTU',
        '18000' => '18.000 BTU',
        '24000' => '24.000 BTU',
        '28000' => '28.000 BTU',
        '36000' => '36.000 BTU',
        '42000' => '42.000 BTU',
        '48000' => '48.000 BTU',
        '50000-100000' => 'Từ 50.000 BTU trở lên',
    ];

    public static function isAllowedMarketingTier(int $value): bool
    {
        return ($value >= 9000 && $value <= 12000)
            || in_array($value, [18000, 24000, 28000, 36000, 42000, 48000], true)
            || ($value >= 50000 && $value <= 100000);
    }

    public function __construct(
        private readonly ProductMarketingCapacityQueryAdapter $capacityQuery,
        private readonly ProductMarketingCapacityEligibility $capacityEligibility,
        private readonly ProductEffectivePriceQuery $effectivePriceQuery,
    ) {}

    /**
     * @var array
     */
    protected array $whitelist = [
        'brand' => 'array',
        'category' => 'string',
        'btu' => 'array',
        'inverter' => 'boolean',
        'cooling_type' => 'string',
        'voltage' => 'string',
        'refrigerant_gas' => 'string',
        'price_min' => 'numeric',
        'price_max' => 'numeric',
        'recommended_area' => 'string',
        'stock_status' => 'string',
        'is_sale' => 'boolean',
        'is_featured' => 'boolean',
        'sort' => 'string',
    ];

    /**
     * Apply filters to the query builder based on the request.
     *
     * @param Builder $query
     * @param Request $request
     * @return Builder
     */
    public function apply(Builder $query, Request $request): Builder
    {
        // 1. Sanitize and extract only valid query parameters
        $filters = $this->sanitize($request);

        // 2. Apply each filter to the query
        if (!empty($filters['brand'])) {
            $query->whereHas('brand', function ($q) use ($filters) {
                $q->whereIn('slug', $filters['brand']);
            });
        }

        if (! empty($filters['category'])) {
            $query->whereHas('category', fn (Builder $category) => $category->where('slug', $filters['category']));
        }

        if (!empty($filters['btu'])) {
            // The public BTU buckets use one stable physical-DX category-slug eligibility
            // contract. Merchant product_type strings and translated category
            // display names are not filter-governance inputs.
            $this->capacityEligibility->apply($query);

            $query->where(function ($q) use ($filters) {
                foreach ($filters['btu'] as $btu) {
                    if (str_contains($btu, '-')) {
                        [$min, $max] = explode('-', $btu, 2);
                        if (is_numeric($min) && is_numeric($max)) {
                            $q->orWhereBetween($this->capacityQuery->column(), [(int) $min, (int) $max]);
                        }
                    } else if (is_numeric($btu)) {
                        $q->orWhere($this->capacityQuery->column(), (int) $btu);
                    }
                }
            });
        }

        if (isset($filters['inverter'])) {
            $query->where('inverter', $filters['inverter']);
        }

        if (!empty($filters['cooling_type'])) {
            $query->where('cooling_type', $filters['cooling_type']);
        }

        if (!empty($filters['voltage'])) {
            $query->where('voltage', $filters['voltage']);
        }

        if (!empty($filters['refrigerant_gas'])) {
            $query->where('refrigerant_gas', $filters['refrigerant_gas']);
        }

        if (isset($filters['price_min'])) {
            $this->effectivePriceQuery->whereMinimum($query, $filters['price_min']);
        }

        if (isset($filters['price_max'])) {
            $this->effectivePriceQuery->whereMaximum($query, $filters['price_max']);
        }

        if (!empty($filters['recommended_area'])) {
            $query->where('recommended_area', $filters['recommended_area']);
        }

        if (!empty($filters['stock_status'])) {
            $query->where('stock_status', $filters['stock_status']);
        }

        if (isset($filters['is_sale']) && $filters['is_sale']) {
            $this->effectivePriceQuery->whereDiscounted($query);
        }

        if (isset($filters['is_featured']) && $filters['is_featured']) {
            $query->where('is_featured', true);
        }

        // 3. Apply sorting
        $this->applySort($query, $filters['sort'] ?? 'latest');

        return $query;
    }

    /** Apply the canonical public Product ordering contract. */
    public function applySort(Builder $query, ?string $sort): Builder
    {
        $sort = $this->normalizeSort($sort);
        switch ($sort) {
            case 'price_asc':
                $this->effectivePriceQuery->orderBy($query, 'asc');
                $query->orderBy('products.id', 'asc');
                break;
            case 'price_desc':
                $this->effectivePriceQuery->orderBy($query, 'desc');
                $query->orderBy('products.id', 'desc');
                break;
            case 'btu_asc':
                $this->capacityEligibility->applyGovernedOrder($query, 'asc')
                    ->orderBy('products.id', 'asc');
                break;
            case 'btu_desc':
                $this->capacityEligibility->applyGovernedOrder($query, 'desc')
                    ->orderBy('products.id', 'desc');
                break;
            case 'latest':
            default:
                $query->latest()->orderBy('products.id', 'desc');
                break;
        }

        return $query;
    }

    public function normalizeSort(?string $sort): string
    {
        return in_array($sort, self::SORT_OPTIONS, true) ? $sort : 'latest';
    }

    /**
     * Sanitize inputs against the whitelist to prevent injection.
     */
    protected function sanitize(Request $request): array
    {
        $sanitized = [];

        foreach ($this->whitelist as $key => $type) {
            if (!$request->has($key)) {
                continue;
            }

            $value = $request->input($key);

            switch ($type) {
                case 'array':
                    if (is_array($value)) {
                        // Filter out empty strings and sanitize
                        $cleanArray = array_values(array_filter(array_map(
                            static fn ($item): string => is_scalar($item) ? mb_substr(trim(strip_tags((string) $item)), 0, self::MAX_FILTER_STRING_LENGTH) : '',
                            $value,
                        )));
                        if (!empty($cleanArray)) {
                            $sanitized[$key] = array_slice(array_values(array_unique($cleanArray)), 0, self::MAX_ARRAY_VALUES);
                        }
                    } elseif (is_string($value) && !empty(trim($value))) {
                        // Support comma separated strings
                        $parts = array_filter(array_map(
                            static fn (string $item): string => mb_substr(trim($item), 0, self::MAX_FILTER_STRING_LENGTH),
                            explode(',', strip_tags($value)),
                        ));
                        if (!empty($parts)) {
                            $sanitized[$key] = array_slice(array_values(array_unique($parts)), 0, self::MAX_ARRAY_VALUES);
                        }
                    }
                    break;
                case 'boolean':
                    // Skip empty/null values — they mean "no filter" (e.g., inverter="" = show all)
                    if ($value === '' || $value === null) {
                        break;
                    }
                    $normalized = is_string($value) ? strtolower(trim($value)) : $value;
                    if (in_array($normalized, [true, 1, '1', 'true'], true)) {
                        $sanitized[$key] = true;
                    } elseif (in_array($normalized, [false, 0, '0', 'false'], true)) {
                        $sanitized[$key] = false;
                    }
                    break;
                case 'numeric':
                    $numeric = is_numeric($value) ? (float) $value : null;
                    if ($numeric !== null && is_finite($numeric) && $numeric >= 0 && $numeric <= self::MAX_PRICE_FILTER) {
                        $sanitized[$key] = $numeric;
                    }
                    break;
                case 'string':
                    if (is_string($value)) {
                        $clean = mb_substr(trim(strip_tags($value)), 0, self::MAX_FILTER_STRING_LENGTH);
                        if ($clean !== '') {
                            $sanitized[$key] = $clean;
                        }
                    }
                    break;
            }
        }

        if (isset($sanitized['btu'])) {
            $sanitized['btu'] = array_values(array_intersect(
                $sanitized['btu'],
                array_keys(self::CAPACITY_FILTER_OPTIONS),
            ));
            if ($sanitized['btu'] === []) {
                unset($sanitized['btu']);
            }
        }

        if (isset($sanitized['cooling_type']) && ! in_array($sanitized['cooling_type'], ['1_chieu', '2_chieu'], true)) {
            unset($sanitized['cooling_type']);
        }

        if (isset($sanitized['sort']) && ! in_array($sanitized['sort'], self::SORT_OPTIONS, true)) {
            unset($sanitized['sort']);
        }

        return $sanitized;
    }

    /**
     * Check if request has active filters that should trigger SEO rules (noindex)
     */
    public function hasActiveFilters(Request $request): bool
    {
        $filters = $this->sanitize($request);
        // Exclude pagination 'page' from active filters check if it was in whitelist, but it's not.
        return !empty($filters);
    }
}
