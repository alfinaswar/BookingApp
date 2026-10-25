<?php

namespace App\Services\Payment;

use App\Contracts\PaymentGateway;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Http\Request;

/**
 * Pengganti default saat Midtrans belum dikonfigurasi.
 * Instruksi transfer ditampilkan, verifikasi dilakukan admin.
 */
class ManualTransferGateway implements PaymentGateway
{
    public function isConfigured(): bool
    {
        return true;
    }

    public function name(): string
    {
        return 'transfer_manual';
    }

    public function charge(Booking $booking, Payment $payment): array
    {
        return [
            'instructions' => 'Transfer ke rekening resmi Basecamp Military Lifestyle, lalu unggah bukti pembayaran.',
        ];
    }

    public function parseWebhook(Request $request): array
    {
        return [];
    }

    public function verifySignature(Request $request): bool
    {
        return false;
    }
}