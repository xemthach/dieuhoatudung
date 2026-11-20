<?php

namespace App\Services\Dealer;

use App\Models\AdministrativeUnit;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class DealerValidation
{
    public function validate(array $data): void
    {
        Validator::make($data, [
            'name' => 'required|string|max:255', 'address_line' => 'required|string|max:255',
            'province_id' => 'required|integer', 'ward_id' => 'required|integer',
            'latitude' => 'nullable|numeric|between:-90,90|required_with:longitude',
            'longitude' => 'nullable|numeric|between:-180,180|required_with:latitude',
            'email' => 'nullable|email|max:255', 'website' => 'nullable|url:http,https|max:1000',
            'phone' => 'nullable|string|max:40', 'hotline' => 'nullable|string|max:40',
            'google_maps_url' => 'nullable|string|max:1000', 'openstreetmap_url' => 'nullable|string|max:1000',
        ])->validate();
        $this->unit($data['province_id'], 'current', 'province');
        $this->unit($data['ward_id'], 'current', 'ward', $data['province_id']);
        if (! empty($data['legacy_province_id'])) {
            $this->unit($data['legacy_province_id'], 'legacy', 'province');
            if (empty($data['legacy_district_id'])) {
                throw ValidationException::withMessages(['legacy_district_id' => 'Chọn quận/huyện thuộc tỉnh cũ.']);
            }
            $this->unit($data['legacy_district_id'], 'legacy', 'district', $data['legacy_province_id']);
            if (! empty($data['legacy_ward_id'])) {
                $this->unit($data['legacy_ward_id'], 'legacy', 'ward', $data['legacy_district_id']);
            }
        } elseif (! empty($data['legacy_district_id']) || ! empty($data['legacy_ward_id'])) {
            throw ValidationException::withMessages(['legacy_province_id' => 'Chọn tỉnh/thành cũ trước.']);
        }
        foreach (['google_maps_url', 'openstreetmap_url'] as $field) {
            if (! empty($data[$field])) {
                app(MapCoordinateParser::class)->validateUrl($data[$field], $field);
            }
        }
    }

    private function unit(int|string $id, string $scheme, string $kind, int|string|null $parent = null): void
    {
        $query = AdministrativeUnit::whereKey($id)->where('scheme', $scheme)->where('kind', $kind)
            ->where('dataset_version', config('dealers.'.$scheme.'_version'));
        if ($parent !== null) {
            $query->where('parent_id', $parent);
        }
        if (! $query->exists()) {
            throw ValidationException::withMessages(['province_id' => 'Địa giới không đúng hệ, phiên bản hoặc quan hệ tỉnh/huyện/xã.']);
        }
    }
}
