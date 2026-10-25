<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\Payment\MidtransGateway;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller
{
    public function __construct(
        private readonly MidtransGateway $midtrans,
        private readonly PaymentService $payments,
    ) {
    }

    /**
     * Notifikasi Midtrans. Idempoten: status akhir yang sama tidak diproses dua kali.
     */
    public function midtrans(Request $request)
    {
        if (! $this->midtrans->isConfigured()) {
            return response()->json(['message' => 'Gateway belum dikonfigurasi.'], 503);
        }

        if (! $this->midtrans->verifySignature($request)) {
            Log::warning('[midtrans] signature tidak valid', ['order_id' => $request->input('order_id')]);

            return response()->json(['message' => 'Signature tidak valid.'], 403);
        }

        $event = $this->midtrans->parseWebhook($request);

        $payment = Payment::where('kode_pembayaran', $event['kode_pembayaran'])->first();

        if (! $payment) {
            return response()->json(['message' => 'Pembayaran tidak ditemukan.'], 404);
        }

        // Nominal tidak cocok = tolak, jangan dikonfirmasi.
        if (abs((float) $payment->amount - (float) $event['amount']) > 0.01) {
            Log::warning('[midtrans] nominal tidak cocok', [
                'payment_id' => $payment->id,
                'expected' => $payment->amount,
                'received' => $event['amount'],
            ]);

            return response()->json(['message' => 'Nominal tidak cocok.'], 422);
        }

        // Idempoten: transaksi yang sama tidak diverifikasi dua kali.
        if ($payment->gateway_ref === $event['gateway_ref'] && $payment->isVerified()) {
            return response()->json(['message' => 'OK (duplikat diabaikan).']);
        }

        $payment->update([
            'gateway_ref' => $event['gateway_ref'],
            'raw_payload' => $event['raw'],
        ]);

        if ($event['status'] === Payment::STATUS_VERIFIED) {
            $this->payments->verify($payment->fresh());
        } elseif (in_array($event['status'], [Payment::STATUS_REJECTED, Payment::STATUS_EXPIRED], true)) {
            $payment->update(['status' => $event['status']]);
        }

        return response()->json(['message' => 'OK']);
    }
}