<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'      => ['required', 'string', 'max:255'],
            'phone'     => ['required', 'string', 'max:20', 'regex:/^(0|\+84)[0-9]{8,10}$/'],
            'email'     => ['nullable', 'email', 'max:255'],
            'room_area' => ['nullable', 'string', 'max:50'],
            'note'        => ['nullable', 'string', 'max:2000'],
            'source_page' => ['nullable', 'url', 'max:500'],
            'website_url' => ['nullable', 'max:0'], // honeypot
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'  => 'Vui lòng nhập họ tên.',
            'phone.required' => 'Vui lòng nhập số điện thoại.',
            'phone.regex'    => 'Số điện thoại không đúng định dạng (VD: 0901234567).',
            'email.email'    => 'Email không đúng định dạng.',
        ];
    }
}
