<?php

namespace App\Services\Payment;

use App\Contracts\PaymentGateway;
use App\Models\Booking;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Midtrans\Config as MidtransConfig;
use Midtrans\Snap;

/**
 * Adapter Midtrans Snap.
 *
 * Kredensial dibaca dari config/midtrans.php (env). Selama kosong,
 * isConfigured() = false dan factory memakai ManualTransferGateway.
 */
class MidtransGateway implements PaymentGateway
{
    public function isConfigured(): bool
    {
        return (bool) config('midtrans.enabled')
            && filled(config('midtrans.server_key'))
            && filled(config('midtrans.client_key'));
    }

    public function name(): string
    {
        return 'midtrans';
    }

    public function charge(Booking $booking, Payment $payment): array
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('Midtrans belum dikonfigurasi.');
        }

        $this->bootstrap();

        $payload = [
            'transaction_details' => [
                'order_id' => $payment->kode_pembayaran,
                'gross_amount' => (int) round($payment->amount),
            ],
            'item_details' => $this->itemDetails($booking, $payment),
            'customer_details' => [
                'first_name' => $booking->NamaBooking,
                'email' => $booking->Email,
                'phone' => (string) $booking->hp,
            ],
            'callbacks' => [
                'finish' => url(config('midtrans.finish_redirect')),
            ],
        ];

        try {
            $response = Snap::createTransaction($payload);

            return [
                'redirect_url' => $response->redirect_url ?? null,
                'token' => $response->token ?? null,
            ];
        } catch (\Throwable $e) {
            Log::error('[midtrans] gagal membuat transaksi: ' . $e->getMessage(), [
                'payment_id' => $payment->id,
            ]);
            throw $e;
        }
    }

    public function parseWebhook(Request $request): array
    {
        $payload = $request->all();

        $status = match ($payload['transaction_status'] ?? '') {
            'settlement', 'capture' => Payment::STATUS_VERIFIED,
            'pending' => Payment::STATUS_PENDING,
            'deny', 'cancel', 'failure' => Payment::STATUS_REJECTED,
            'expire' => Payment::STATUS_EXPIRED,
            'refund', 'partial_refund' => Payment::STATUS_REFUNDED,
            default => Payment::STATUS_PENDING,
        };

        // Fraud challenge: capture tapi belum lolos review, jangan diterima.
        if (($payload['fraud_status'] ?? null) === 'challenge') {
            $status = Payment::STATUS_PENDING;
        }

        return [
            'kode_pembayaran' => (string) ($payload['order_id'] ?? ''),
            'status' => $status,
            'amount' => (float) ($payload['gross_amount'] ?? 0),
            'gateway_ref' => $payload['transaction_id'] ?? null,
            'paid_at' => isset($payload['settlement_time'])
                ? Carbon::parse($payload['settlement_time'])
                : now(),
            'raw' => $payload,
        ];
    }

    /**
     * Signature key = sha512(order_id + status_code + gross_amount + server_key).
     * Bila signature_key tidak dikirim (mis. test dari dashboard), verifikasi
     * memakai status_code + server_key lewat endpoint status Midtrans.
     */
    public function verifySignature(Request $request): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        $orderId = (string) $request->input('order_id', '');
        $statusCode = (string) $request->input('status_code', '');
        $grossAmount = (string) $request->input('gross_amount', '');
        $signature = (string) $request->input('signature_key', '');

        if ($orderId === '' || $grossAmount === '') {
            return false;
        }

        if ($signature !== '') {
            $expected = hash('sha512', $orderId . $statusCode . $grossAmount . config('midtrans.server_key'));

            return hash_equals($expected, $signature);
        }

        // Tanpa signature_key: konfirmasi ulang status ke API Midtrans.
        return $this->confirmStatusFromApi($orderId);
    }

    private function confirmStatusFromApi(string $orderId): bool
    {
        try {
            [$code, $body] = $this->getStatus($orderId);

            return $code === 200 && (($body['transaction_status'] ?? '') !== '');
        } catch (\Throwable $e) {
            Log::warning('[midtrans] verifikasi status gagal: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Ambil status transaksi dari API Midtrans.
     *
     * @return array{0:int,1:array<string,mixed>}
     */
    public function getStatus(string $orderId): array
    {
        $base = config('midtrans.is_production')
            ? 'https://api.midtrans.com/v2/'
            : 'https://api.sandbox.midtrans.com/v2/';

        $response = Http::withBasicAuth(config('midtrans.server_key'), '')
            ->acceptJson()
            ->get($base . $orderId . '/status');

        return [$response->status(), (array) $response->json()];
    }

    private function bootstrap(): void
    {
        MidtransConfig::$serverKey = config('midtrans.server_key');
        MidtransConfig::$clientKey = config('midtrans.client_key');
        MidtransConfig::$isProduction = (bool) config('midtrans.is_production');
        MidtransConfig::$isSanitized = true;
        MidtransConfig::$is3ds = true;
    }

    private function itemDetails(Booking $booking, Payment $payment): array
    {
        $items = [];

        if ($payment->tipe === Payment::TIPE_DP || $payment->tipe === Payment::TIPE_FULL) {
            $items[] = [
                'id' => 'AKOMODASI',
                'price' => (int) round($payment->amount),
                'quantity' => 1,
                'name' => 'Akomodasi ' . $booking->kode_booking,
            ];
        } else {
            $items[] = [
                'id' => 'PELUNASAN',
                'price' => (int) round($payment->amount),
                'quantity' => 1,
                'name' => 'Pelunasan ' . $booking->kode_booking,
            ];
        }

        return $items;
    }
}