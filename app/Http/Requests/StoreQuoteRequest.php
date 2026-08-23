<?php

namespace App\Http\Requests;

use App\Support\VietnamesePhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => VietnamesePhone::normalize($this->input('phone')),
            'submission_token' => $this->input('submission_token') ?: (string) Str::uuid(),
            'entry_context' => $this->input('entry_context') ?: 'direct',
        ]);

        if ($this->input('entry_context') !== 'calculator') {
            return;
        }

        $context = $this->session()->get('quote_calculator_context');

        if (! is_array($context)) {
            return;
        }

        $spaceMap = [
            'nha_o' => 'nha_o',
            'phong_ngu' => 'nha_o',
            'phong_khach' => 'nha_o',
            'can_ho' => 'can_ho',
            'van_phong' => 'van_phong',
            'van_phong_noi_that' => 'van_phong',
            'cua_hang' => 'cua_hang',
            'showroom' => 'showroom',
            'nha_hang' => 'nha_hang',
            'cafe' => 'nha_hang',
            'hoi_truong' => 'hoi_truong',
            'nha_xuong' => 'nha_xuong',
            'phong_hoc' => 'truong_hoc',
            'khach_san' => 'khach_san',
        ];

        $this->merge([
            'area_m2' => $context['area_m2'] ?? $this->input('area_m2'),
            'ceiling_height' => $context['ceiling_height'] ?? $this->input('ceiling_height'),
            'number_of_people' => $context['people_count'] ?? $this->input('number_of_people'),
            'sun_exposure' => ! empty($context['direct_sunlight'])
                ? 'nang_nhieu'
                : $this->input('sun_exposure'),
            'project_type' => $spaceMap[$context['space_type'] ?? ''] ?? $this->input('project_type'),
        ]);
    }

    public function rules(): array
    {
        return [
            'submission_token' => ['required', 'uuid'],
            'entry_context' => ['required', 'in:direct,product,calculator,category,campaign'],
            'lead_type' => ['nullable', 'in:product,general,consultation'],
            'project_type' => ['nullable', 'in:nha_o,can_ho,van_phong,cua_hang,showroom,nha_hang,hoi_truong,nha_xuong,truong_hoc,khach_san,chua_ro,khac'],
            'usage_description' => ['nullable', 'string', 'max:500'],
            'number_of_rooms' => ['nullable', 'integer', 'min:1', 'max:500'],
            'area_m2' => ['nullable', 'numeric', 'min:1', 'max:50000'],
            'ceiling_height' => ['nullable', 'numeric', 'min:1', 'max:20'],
            'number_of_people' => ['nullable', 'integer', 'min:0', 'max:5000'],
            'sun_exposure' => ['nullable', 'in:it_nang,nang_vua,nang_nhieu'],
            'insulation_quality' => ['nullable', 'in:tot,trung_binh,kem,chua_ro'],
            'glass_area' => ['nullable', 'in:it_kinh,nhieu_kinh,vach_kinh'],
            'open_space' => ['nullable', 'boolean'],
            'current_aircon_status' => ['nullable', 'in:chua_co,co_nhung_yeu,thay_cu,can_them,chua_ro'],
            'preferred_btu' => ['nullable', 'integer', 'min:9000'],
            'preferred_brands' => ['nullable', 'array', 'max:10'],
            'preferred_brands.*' => ['string', 'max:50'],
            'preferred_brand' => ['nullable', 'string', 'max:255'],
            'need_inverter' => ['nullable', 'boolean'],
            'need_three_phase' => ['nullable', 'boolean'],
            'power_supply' => ['nullable', 'in:1_pha,3_pha,chua_ro'],
            'installation_type' => ['nullable', 'in:lap_moi,thay_cu,di_doi,bao_tri'],
            'pipe_distance_m' => ['nullable', 'numeric', 'min:0', 'max:200'],
            'outdoor_unit_location' => ['nullable', 'in:ban_cong,mai_nha,tuong_ngoai,san_thuong,chua_ro'],
            'drainage_available' => ['nullable', 'in:co,khong,chua_ro'],
            'has_existing_piping' => ['nullable', 'in:co,khong,chua_ro'],
            'budget_range' => ['nullable', 'in:duoi_20_trieu,20_40_trieu,40_70_trieu,tren_70_trieu,chua_ro'],
            'installation_time' => ['nullable', 'in:ngay,3_ngay,1_tuan,1_thang,chua_ro'],
            'need_installation_service' => ['nullable', 'in:tron_goi,chi_may,chua_ro'],
            'need_invoice' => ['nullable', 'boolean'],
            'need_site_survey' => ['nullable', 'boolean'],
            'full_name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'regex:/^0[0-9]{8,10}$/'],
            'email' => ['nullable', 'email', 'max:150'],
            'province_city' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'preferred_contact_method' => ['nullable', 'in:phone,zalo,email'],
            'preferred_contact_time' => ['nullable', 'in:ngay,hanh_chinh,buoi_toi,khac'],
            'message' => ['nullable', 'string', 'max:2000'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'source_page' => ['nullable', 'string', 'max:255'],
            'landing_page' => ['nullable', 'string', 'max:500'],
            'referrer' => ['nullable', 'string', 'max:500'],
            'utm_source' => ['nullable', 'string', 'max:100'],
            'utm_medium' => ['nullable', 'string', 'max:100'],
            'utm_campaign' => ['nullable', 'string', 'max:100'],
            'utm_term' => ['nullable', 'string', 'max:100'],
            'utm_content' => ['nullable', 'string', 'max:100'],
            'gclid' => ['nullable', 'string', 'max:255'],
            'gbraid' => ['nullable', 'string', 'max:255'],
            'wbraid' => ['nullable', 'string', 'max:255'],
            'website_url' => ['nullable', 'max:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.required' => 'Vui lòng cho biết tên để nhân viên tiện xưng hô.',
            'phone.required' => 'Vui lòng nhập số điện thoại để chúng tôi liên hệ.',
            'phone.regex' => 'Số điện thoại không hợp lệ. Có thể nhập dạng 09xx xxx xxx hoặc +84.',
            'email.email' => 'Email không đúng định dạng.',
        ];
    }

    public function calculatorContext(): ?array
    {
        if ($this->validated('entry_context') !== 'calculator') {
            return null;
        }

        $context = $this->session()->get('quote_calculator_context');

        return is_array($context) ? $context : null;
    }
}
