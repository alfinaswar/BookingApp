<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class QuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'room_unit_ids' => ['required', 'array', 'min:1', 'max:10'],
            'room_unit_ids.*' => ['integer', 'exists:room_units,id'],
            'check_in' => ['required', 'date_format:Y-m-d'],
            'check_out' => ['required', 'date_format:Y-m-d', 'after:check_in'],
            'dewasa' => ['nullable', 'integer', 'min:1', 'max:50'],
            'anak' => ['nullable', 'integer', 'min:0', 'max:50'],
            'addons' => ['nullable', 'array'],
            'addons.*.id' => ['integer', 'exists:addons,id'],
            'addons.*.qty' => ['nullable', 'integer', 'min:1', 'max:50'],
            'promo_kode' => ['nullable', 'string', 'max:50'],
        ];
    }
}