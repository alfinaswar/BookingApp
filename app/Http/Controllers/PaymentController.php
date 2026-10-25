<?php

namespace App\Http\Controllers;

use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\Payment\MidtransGateway;
use App\Services\Payment\PaymentGatewayFactory;
use App\Services\PaymentService;
use App\Services\PricingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $payments,
        private readonly PaymentGatewayFactory $gateways,
        private readonly PricingService $pricing,
        private readonly MidtransGateway $midtrans,
    ) {
    }

    /**
     * Instruksi pembayaran + form unggah bukti transfer (publik, berbasis kode booking).
     */
    public function pay(string $kode, ?int $payment = null)
    {
        $booking = Booking::with(['payments', 'units.unit'])->where('kode_booking', $kode)->firstOrFail();

        $invoice = $payment
            ? $booking->payments->firstWhere('id', $payment)
            : $booking->payments->whereIn('status', [Payment::STATUS_PENDING, Payment::STATUS_WAITING, Payment::STATUS_REJECTED])->sortByDesc('id')->first();

        return view('Booking.pay', [
            'booking' => $booking,
            'invoice' => $invoice,
            'onlineAvailable' => $this->gateways->onlineAvailable(),
            'bankInfo' => [
                'bank' => config('basecamp.bank_name', 'Bank Central Asia (BCA)'),
                'norek' => config('basecamp.bank_account', '0000000000'),
                'atasnama' => config('basecamp.bank_holder', 'Basecamp Military Lifestyle'),
            ],
        ]);
    }

    /**
     * Unggah bukti transfer manual.
     */
    public function uploadProof(Request $request, string $kode)
    {
        $request->validate([
            'payment_id' => ['required', 'integer', 'exists:payments,id'],
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
        ]);

        $booking = Booking::where('kode_booking', $kode)->firstOrFail();
        $payment = $booking->payments()->findOrFail($request->integer('payment_id'));

        try {
            $this->payments->submitManualProof($payment, $request->file('proof'));
        } catch (BookingException $e) {
            return back()->withErrors(['proof' => $e->getMessage()]);
        }

        return redirect()->route('booking.status', $kode)
            ->with('success', 'Bukti pembayaran terkirim. Menunggu verifikasi admin.');
    }

    /**
     * Tampilkan bukti bayar (admin atau pemilik booking).
     */
    public function proof(int $paymentId)
    {
        $payment = Payment::with('booking')->findOrFail($paymentId);

        abort_if(! $payment->proof_path, 404);

        $user = auth()->user();
        $isOwner = $user && ($payment->booking?->userId === $user->id || $payment->booking?->created_by === $user->id);

        abort_unless($user && ($isOwner || $user->can('verifikasi-pembayaran') || in_array($user->role ?? '', ['admin', 'superadmin'], true)), 403);

        abort_unless(Storage::disk('local')->exists($payment->proof_path), 404);

        return Storage::disk('local')->response($payment->proof_path);
    }

    /**
     * Admin: setujui bukti bayar.
     */
    public function verify(Payment $payment)
    {
        $this->payments->verify($payment, auth()->user());

        return response()->json([
            'message' => 'Pembayaran diverifikasi.',
            'booking' => $payment->booking()->first(),
        ]);
    }

    public function reject(Request $request, Payment $payment)
    {
        $request->validate(['reason' => ['required', 'string', 'max:255']]);

        if ($payment->tipe === Payment::TIPE_REFUND) {
            $this->payments->voidRefund($payment, auth()->user(), $request->get('reason'));

            return response()->json(['message' => 'Refund dibatalkan. Dana tetap tercatat sebagai pendapatan.']);
        }

        $this->payments->reject($payment, auth()->user(), $request->get('reason'));

        return response()->json(['message' => 'Pembayaran ditolak. Customer dapat mengunggah ulang bukti.']);
    }

    /**
     * Admin/front desk: catat pelunasan tunai.
     */
    public function cash(Request $request, Booking $booking)
    {
        $request->validate([
            'tipe' => ['required', 'in:dp,pelunasan'],
            'amount' => ['required', 'numeric', 'min:1'],
        ]);

        try {
            $payment = $this->payments->recordCash($booking, (float) $request->get('amount'), $request->get('tipe'), auth()->user());
        } catch (BookingException $e) {
            return response()->json(['message' => $e->getMessage()], $e->statusCode);
        }

        return response()->json(['message' => 'Pembayaran tunai dicatat.', 'payment' => $payment]);
    }

    /**
     * Buat transaksi Snap. 503 selama Midtrans belum dikonfigurasi.
     */
    public function snapToken(Request $request)
    {
        $request->validate([
            'payment_id' => ['required', 'integer', 'exists:payments,id'],
        ]);

        $gateway = $this->gateways->online();

        if (! $gateway) {
            return response()->json([
                'message' => 'Pembayaran online belum dikonfigurasi. Silakan gunakan transfer manual.',
            ], 503);
        }

        $payment = Payment::with('booking')->findOrFail($request->integer('payment_id'));

        if ($payment->isVerified()) {
            return response()->json(['message' => 'Pembayaran ini sudah lunas.'], 422);
        }

        try {
            $result = $gateway->charge($payment->booking, $payment);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Gagal menghubungi gateway: ' . $e->getMessage()], 502);
        }

        $payment->update([
            'metode' => Payment::METODE_MIDTRANS,
            'status' => Payment::STATUS_PENDING,
        ]);

        return response()->json($result + [
            'client_key' => config('midtrans.client_key'),
            'snap_url' => config('midtrans.snap_url'),
        ]);
    }

    /**
     * Kembali dari halaman gateway.
     */
    public function returnFromGateway(Request $request)
    {
        $kode = (string) $request->get('order_id', '');

        if ($kode !== '' && $this->gateways->onlineAvailable()) {
            [$code, $body] = $this->midtrans->getStatus($kode);

            if ($code === 200 && in_array($body['transaction_status'] ?? '', ['settlement', 'capture'], true)) {
                $payment = Payment::where('kode_pembayaran', $kode)->first();
                if ($payment && ! $payment->isVerified()) {
                    $this->payments->verify($payment);
                }
            }
        }

        $booking = Booking::whereHas('payments', fn ($q) => $q->where('kode_pembayaran', $kode))->first();

        return $booking
            ? redirect()->route('booking.status', $booking->kode_booking)
            : redirect()->route('booking.index');
    }
}