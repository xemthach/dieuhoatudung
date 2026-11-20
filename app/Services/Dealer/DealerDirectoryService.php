<?php

namespace App\Services\Dealer;

use App\Models\AdministrativeUnit;
use App\Models\Dealer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class DealerDirectoryService
{
    public function filters(array $data): array
    {
        $filters = Validator::make($data, [
            'q' => 'nullable|string|max:100', 'scheme' => ['nullable', Rule::in(['current', 'legacy'])],
            'province' => 'nullable|integer|exists:administrative_units,id',
            'district' => 'nullable|integer|exists:administrative_units,id',
            'ward' => 'nullable|integer|exists:administrative_units,id',
            'brand' => 'nullable|integer|exists:brands,id',
            'category' => 'nullable|integer|exists:product_categories,id',
            'service' => 'nullable|integer|exists:dealer_services,id',
            'sort' => ['nullable', Rule::in(['featured', 'name', 'rating'])],
        ])->validate();
        $filters = array_filter($filters, static fn ($v) => $v !== null && $v !== '');
        $filters['scheme'] ??= 'current';
        $parent = null;
        foreach (['province', 'district', 'ward'] as $kind) {
            if (empty($filters[$kind])) {
                continue;
            }
            if ($kind === 'district' && $filters['scheme'] === 'current') {
                throw ValidationException::withMessages(['district' => 'Hệ địa giới hiện hành dùng tỉnh và xã/phường.']);
            }
            $unit = AdministrativeUnit::find($filters[$kind]);
            if ($unit->scheme !== $filters['scheme'] || $unit->kind !== $kind
                || $unit->dataset_version !== config('dealers.'.$filters['scheme'].'_version')
                || ($kind !== 'province' && (! $parent || $unit->parent_id !== $parent))) {
                throw ValidationException::withMessages([$kind => 'Địa giới không thuộc khu vực đã chọn.']);
            }
            $parent = $unit->id;
        }

        return $filters;
    }

    public function query(array $filters): Builder
    {
        $query = Dealer::publiclyListed()->select('dealers.*')
            ->with(['province', 'ward', 'legacyProvince', 'legacyDistrict', 'legacyWard',
                'brands' => fn ($q) => $q->where('is_active', true),
                'categories' => fn ($q) => $q->where('is_active', true),
                'services' => fn ($q) => $q->where('is_active', true)])
            ->withCount('publicReviews as review_count')->withAvg('publicReviews as average_rating', 'rating');
        if (! empty($filters['q'])) {
            $text = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($filters['q'])).'%';
            $query->where(fn ($q) => $q->whereRaw("lower(dealers.name) like ? escape '!'", [$text])
                ->orWhereRaw("lower(address_line) like ? escape '!'", [$text]));
        }
        foreach (['province', 'district', 'ward'] as $field) {
            if (empty($filters[$field])) {
                continue;
            }
            $column = ($filters['scheme'] === 'legacy' ? 'legacy_' : '').$field.'_id';
            $query->where($column, $filters[$field]);
        }
        foreach (['brand' => 'brands', 'category' => 'categories', 'service' => 'services'] as $key => $relation) {
            if (! empty($filters[$key])) {
                $query->whereHas($relation, fn ($q) => $q->whereKey($filters[$key])->where('is_active', true));
            }
        }

        return match ($filters['sort'] ?? 'featured') {
            'name' => $query->orderBy('name')->orderBy('dealers.id'),
            'rating' => $query->orderByRaw('case when average_rating is null then 1 else 0 end')
                ->orderByDesc('average_rating')->orderByDesc('review_count')->orderBy('dealers.id'),
            default => $query->orderByDesc('is_featured')->orderBy('sort_order')->orderBy('dealers.id'),
        };
    }

    public function regions(array $filters)
    {
        $scheme = $filters['scheme'];
        $query = AdministrativeUnit::where('scheme', $scheme)
            ->where('dataset_version', config('dealers.'.$scheme.'_version'));
        if (! empty($filters['district'])) {
            $query->where('kind', 'ward')->where('parent_id', $filters['district']);
            $column = 'legacy_ward_id';
        } elseif (! empty($filters['province'])) {
            $query->where('parent_id', $filters['province'])->where('kind', $scheme === 'legacy' ? 'district' : 'ward');
            $column = $scheme === 'legacy' ? 'legacy_district_id' : 'ward_id';
        } else {
            $query->where('kind', 'province');
            $column = $scheme === 'legacy' ? 'legacy_province_id' : 'province_id';
        }

        return $query->with('parent')->select('administrative_units.*')->selectSub(
            Dealer::publiclyListed()->selectRaw('count(*)')->whereColumn($column, 'administrative_units.id'), 'dealer_count'
        )->whereExists(Dealer::publiclyListed()->selectRaw('1')->whereColumn($column, 'administrative_units.id'))
            ->orderBy('name')->get();
    }
}
