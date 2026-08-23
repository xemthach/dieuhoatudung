<?php

namespace App\Http\Requests;

use App\Support\VietnamesePhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreQuickQuoteRequest extends FormRequest
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
        ]);
    }

    public function rules(): array
    {
        return [
            'submission_token' => ['required', 'uuid'],
            'full_name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'regex:/^0[0-9]{8,10}$/'],
            'email' => ['nullable', 'email', 'max:150'],
            'province_city' => ['nullable', 'string', 'max:100'],
            'message' => ['nullable', 'string', 'max:1000'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'product_name' => ['nullable', 'string', 'max:255'],
            'product_sku' => ['nullable', 'string', 'max:100'],
            'product_url' => ['nullable', 'url', 'max:500'],
            'product_brand' => ['nullable', 'string', 'max:100'],
            'product_category' => ['nullable', 'string', 'max:100'],
            'product_capacity_btu' => ['nullable', 'integer'],
            'source_page' => ['nullable', 'url', 'max:255'],
            'utm_source' => ['nullable', 'string', 'max:100'],
            'utm_medium' => ['nullable', 'string', 'max:100'],
            'utm_campaign' => ['nullable', 'string', 'max:100'],
            'gclid' => ['nullable', 'string', 'max:255'],
            'gbraid' => ['nullable', 'string', 'max:255'],
            'wbraid' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.required' => 'Vui lòng nhập họ tên.',
            'phone.required' => 'Vui lòng nhập số điện thoại.',
            'phone.regex' => 'Số điện thoại không hợp lệ. Có thể nhập dạng 09xx xxx xxx hoặc +84.',
        ];
    }
}
