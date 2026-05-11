<?php

namespace App\Services\Product;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;

class ProductFilterService
{
    /**
     * @var array
     */
    protected array $whitelist = [
        'brand' => 'array',
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

        if (!empty($filters['btu'])) {
            $query->where(function ($q) use ($filters) {
                foreach ($filters['btu'] as $btu) {
                    if (str_contains($btu, '-')) {
                        [$min, $max] = explode('-', $btu, 2);
                        if (is_numeric($min) && is_numeric($max)) {
                            $q->orWhereBetween('btu', [(int)$min, (int)$max]);
                        }
                    } else if (is_numeric($btu)) {
                        $q->orWhere('btu', (int)$btu);
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
            $query->where(function($q) use ($filters) {
                $q->where('sale_price', '>=', $filters['price_min'])
                  ->orWhere(function($sub) use ($filters) {
                      $sub->whereNull('sale_price')->where('regular_price', '>=', $filters['price_min']);
                  });
            });
        }

        if (isset($filters['price_max'])) {
            $query->where(function($q) use ($filters) {
                $q->where('sale_price', '<=', $filters['price_max'])
                  ->orWhere(function($sub) use ($filters) {
                      $sub->whereNull('sale_price')->where('regular_price', '<=', $filters['price_max']);
                  });
            });
        }

        if (!empty($filters['recommended_area'])) {
            $query->where('recommended_area', $filters['recommended_area']);
        }

        if (!empty($filters['stock_status'])) {
            $query->where('stock_status', $filters['stock_status']);
        }

        if (isset($filters['is_sale']) && $filters['is_sale']) {
            $query->whereNotNull('sale_price')->whereColumn('sale_price', '<', 'regular_price');
        }

        if (isset($filters['is_featured']) && $filters['is_featured']) {
            $query->where('is_featured', true);
        }

        // 3. Apply sorting
        $sort = $filters['sort'] ?? 'latest';
        switch ($sort) {
            case 'price_asc':
                // Using coalescing to sort by sale_price if exists, else regular_price
                $query->orderByRaw('COALESCE(sale_price, regular_price) ASC');
                break;
            case 'price_desc':
                $query->orderByRaw('COALESCE(sale_price, regular_price) DESC');
                break;
            case 'btu_asc':
                $query->orderBy('btu', 'asc');
                break;
            case 'btu_desc':
                $query->orderBy('btu', 'desc');
                break;
            case 'latest':
            default:
                $query->latest();
                break;
        }

        return $query;
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
                        $cleanArray = array_filter(array_map('trim', array_map('strip_tags', $value)));
                        if (!empty($cleanArray)) {
                            $sanitized[$key] = $cleanArray;
                        }
                    } elseif (is_string($value) && !empty(trim($value))) {
                        // Support comma separated strings
                        $parts = array_filter(array_map('trim', explode(',', strip_tags($value))));
                        if (!empty($parts)) {
                            $sanitized[$key] = $parts;
                        }
                    }
                    break;
                case 'boolean':
                    // Skip empty/null values — they mean "no filter" (e.g., inverter="" = show all)
                    if ($value === '' || $value === null) {
                        break;
                    }
                    $sanitized[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                    break;
                case 'numeric':
                    if (is_numeric($value) && $value >= 0) {
                        $sanitized[$key] = (float) $value;
                    }
                    break;
                case 'string':
                    if (is_string($value)) {
                        $clean = trim(strip_tags($value));
                        if ($clean !== '') {
                            $sanitized[$key] = $clean;
                        }
                    }
                    break;
            }
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
