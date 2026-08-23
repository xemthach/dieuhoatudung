<?php

namespace App\Http\Requests;

use App\Support\VietnamesePhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreLeadRequest extends FormRequest
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
            'name'      => ['required', 'string', 'max:100'],
            'phone'     => ['required', 'string', 'max:20', 'regex:/^0[0-9]{8,10}$/'],
            'email'     => ['nullable', 'email', 'max:150'],
            'room_area' => ['nullable', 'numeric', 'min:1', 'max:50000'],
            'note'        => ['nullable', 'string', 'max:2000'],
            'source_page' => ['nullable', 'url', 'max:255'],
            'submission_token' => ['required', 'uuid'],
            'website_url' => ['nullable', 'max:0'], // honeypot
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'  => 'Vui lòng nhập họ tên.',
            'phone.required' => 'Vui lòng nhập số điện thoại.',
            'phone.regex'    => 'Số điện thoại không hợp lệ. Có thể nhập 09xx xxx xxx hoặc +84.',
            'email.email'    => 'Email không đúng định dạng.',
        ];
    }
}
