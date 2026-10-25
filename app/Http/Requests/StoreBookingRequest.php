<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'room_unit_ids' => ['required', 'array', 'min:1', 'max:10'],
            'room_unit_ids.*' => ['required', 'integer', 'exists:room_units,id'],

            'check_in' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'check_out' => ['required', 'date_format:Y-m-d', 'after:check_in'],

            'dewasa' => ['nullable', 'integer', 'min:1', 'max:50'],
            'anak' => ['nullable', 'integer', 'min:0', 'max:50'],

            'nama' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:100'],
            'hp' => ['required', 'string', 'regex:/^[0-9+\-\s]{8,20}$/'],
            'identitas' => ['nullable', 'string', 'max:30'],
            'no_identitas' => ['nullable', 'string', 'max:30'],
            'gender' => ['nullable', 'in:L,P'],
            'catatan' => ['nullable', 'string', 'max:500'],

            'addons' => ['nullable', 'array'],
            'addons.*.id' => ['required_with:addons', 'integer', 'exists:addons,id'],
            'addons.*.qty' => ['nullable', 'integer', 'min:1', 'max:50'],

            'promo_kode' => ['nullable', 'string', 'max:50'],
            'source' => ['nullable', 'in:online,walkin,phone,ota'],
            'metode' => ['nullable', 'in:transfer_manual,cash,midtrans'],
        ];
    }

    public function messages(): array
    {
        return [
            'room_unit_ids.required' => 'Pilih minimal satu unit.',
            'room_unit_ids.*.exists' => 'Unit yang dipilih tidak valid.',
            'check_in.after_or_equal' => 'Tanggal check-in tidak boleh di masa lalu.',
            'check_out.after' => 'Tanggal check-out harus setelah check-in.',
            'hp.regex' => 'Nomor HP tidak valid.',
        ];
    }

    /**
     * Data siap pakai untuk BookingService.
     */
    public function payload(): array
    {
        $validated = $this->validated();

        return [
            'room_unit_ids' => array_map('intval', $validated['room_unit_ids']),
            'check_in' => $validated['check_in'],
            'check_out' => $validated['check_out'],
            'dewasa' => (int) ($validated['dewasa'] ?? 1),
            'anak' => (int) ($validated['anak'] ?? 0),
            'nama' => $validated['nama'],
            'email' => $validated['email'],
            'hp' => $validated['hp'],
            'identitas' => $validated['identitas'] ?? 'KTP',
            'no_identitas' => $validated['no_identitas'] ?? '-',
            'gender' => $validated['gender'] ?? 'L',
            'addons' => $validated['addons'] ?? [],
            'promo_kode' => $validated['promo_kode'] ?? null,
            'source' => $validated['source'] ?? \App\Models\Booking::SOURCE_ONLINE,
        ];
    }
}