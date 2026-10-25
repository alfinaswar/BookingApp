<?php

namespace App\Contracts;

use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Http\Request;

interface PaymentGateway
{
    /**
     * Gateway siap dipakai (kredensial lengkap)?
     */
    public function isConfigured(): bool;

    public function name(): string;

    /**
     * Minta halaman/token pembayaran dari gateway.
     *
     * @return array{redirect_url?:string, token?:string, instructions?:string}
     */
    public function charge(Booking $booking, Payment $payment): array;

    /**
     * Normalisasi notifikasi gateway menjadi event internal.
     *
     * @return array{
     *   kode_pembayaran:string, status:string, amount:float,
     *   gateway_ref:?string, paid_at:?\Carbon\Carbon, raw:array<string,mixed>
     * }
     */
    public function parseWebhook(Request $request): array;

    public function verifySignature(Request $request): bool;
}