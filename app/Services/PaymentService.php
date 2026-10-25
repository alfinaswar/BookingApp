<?php

namespace App\Services;

use App\Exceptions\BookingException;
use App\Events\PaymentProofSubmitted;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PaymentService
{
    public function __construct(
        private readonly BookingService $bookings,
        private readonly PricingService $pricing,
    ) {
    }

    /**
     * Buat tagihan DP untuk booking baru.
     */
    public function createDpInvoice(Booking $booking, string $metode = Payment::METODE_TRANSFER): Payment
    {
        return $this->createInvoice($booking, Payment::TIPE_DP, (float) $booking->dp_amount, $metode);
    }

    /**
     * Buat tagihan pelunasan (sisa saldo).
     */
    public function createSettlementInvoice(Booking $booking, string $metode = Payment::METODE_TRANSFER): Payment
    {
        $balance = (float) $booking->balance_due;

        if ($balance <= 0) {
            throw new BookingException('Tidak ada sisa pembayaran.', 422);
        }

        return $this->createInvoice($booking, Payment::TIPE_PELUNASAN, $balance, $metode);
    }

    public function createInvoice(Booking $booking, string $tipe, float $amount, string $metode): Payment
    {
        if ($amount <= 0) {
            throw new BookingException('Nominal pembayaran harus lebih dari nol.', 422);
        }

        return Payment::create([
            'booking_id' => $booking->id,
            'kode_pembayaran' => $this->generateKode($booking, $tipe),
            'metode' => $metode,
            'tipe' => $tipe,
            'amount' => round($amount, 2),
            'status' => $metode === Payment::METODE_MIDTRANS
                ? Payment::STATUS_PENDING
                : Payment::STATUS_PENDING,
        ]);
    }

    /**
     * Tamu upload bukti transfer manual.
     */
    public function submitManualProof(Payment $payment, UploadedFile $proof): Payment
    {
        if ($payment->isVerified()) {
            throw new BookingException('Pembayaran ini sudah diverifikasi.', 422);
        }

        $path = $proof->store('bukti-bayar/' . $payment->booking_id, 'local');

        $payment->update([
            'proof_path' => $path,
            'status' => Payment::STATUS_WAITING,
        ]);

        $booking = $payment->booking()->first();

        if ($booking) {
            PaymentProofSubmitted::dispatch($payment->fresh(), $booking);
        }

        return $payment->fresh();
    }

    /**
     * Pembayaran tunai di front desk: langsung verified.
     */
    public function recordCash(Booking $booking, float $amount, string $tipe, ?User $actor = null): Payment
    {
        return DB::transaction(function () use ($booking, $amount, $tipe, $actor) {
            $payment = $this->createInvoice($booking, $tipe, $amount, Payment::METODE_CASH);

            $payment->update([
                'status' => Payment::STATUS_VERIFIED,
                'paid_at' => now(),
                'verified_by' => $actor?->id,
                'verified_at' => now(),
            ]);

            return $this->syncBooking($booking->fresh(), $actor);
        });
    }

    /**
     * Admin menyetujui bukti transfer / pembayaran gateway.
     */
    public function verify(Payment $payment, ?User $actor = null): Payment
    {
        if ($payment->tipe === Payment::TIPE_REFUND) {
            return $this->completeRefund($payment, $actor);
        }

        if ($payment->isVerified()) {
            return $payment;
        }

        return DB::transaction(function () use ($payment, $actor) {
            $payment->update([
                'status' => Payment::STATUS_VERIFIED,
                'paid_at' => $payment->paid_at ?? now(),
                'verified_by' => $actor?->id,
                'verified_at' => now(),
                'rejected_reason' => null,
            ]);

            return $this->syncBooking($payment->booking()->first(), $actor);
        });
    }

    public function reject(Payment $payment, ?User $actor = null, string $reason = ''): Payment
    {
        if ($payment->isVerified()) {
            throw new BookingException('Pembayaran yang sudah verified tidak bisa ditolak.', 422);
        }

        $payment->update([
            'status' => Payment::STATUS_REJECTED,
            'rejected_reason' => $reason ?: 'Bukti pembayaran tidak valid',
            'verified_by' => $actor?->id,
            'verified_at' => now(),
        ]);

        return $payment->fresh();
    }

    /**
     * Catat tagihan refund. Kas belum berkurang sampai admin menyelesaikannya.
     */
    public function createRefundInvoice(Booking $booking, float $amount, string $reason = ''): Payment
    {
        if ($amount <= 0) {
            throw new BookingException('Nominal refund harus lebih dari nol.', 422);
        }

        $available = (float) $booking->paid_total - (float) $booking->refunded_total;

        if ($amount > $available + 0.01) {
            throw new BookingException(
                'Refund melebihi dana yang diterima (Rp ' . number_format($available, 0, ',', '.') . ').',
                422
            );
        }

        $sumber = $booking->payments()
            ->whereIn('tipe', [Payment::TIPE_DP, Payment::TIPE_PELUNASAN, Payment::TIPE_FULL])
            ->latest('id')
            ->first();

        return Payment::create([
            'booking_id' => $booking->id,
            'kode_pembayaran' => $this->generateKode($booking, Payment::TIPE_REFUND),
            'metode' => $sumber->metode ?? Payment::METODE_TRANSFER,
            'tipe' => Payment::TIPE_REFUND,
            'amount' => round($amount, 2),
            // Refund manual menunggu admin transfer; gateway bisa otomatis.
            'status' => Payment::STATUS_PENDING,
            'rejected_reason' => $reason ?: null,
        ]);
    }

    /**
     * Admin menandai refund sudah ditransfer ke tamu.
     */
    public function completeRefund(Payment $payment, ?User $actor = null): Payment
    {
        if ($payment->tipe !== Payment::TIPE_REFUND) {
            throw new BookingException('Pembayaran ini bukan refund.', 422);
        }

        if ($payment->isVerified()) {
            return $payment;
        }

        return DB::transaction(function () use ($payment, $actor) {
            $payment->update([
                'status' => Payment::STATUS_VERIFIED,
                'paid_at' => now(),
                'verified_by' => $actor?->id,
                'verified_at' => now(),
            ]);

            $this->syncBooking($payment->booking()->first(), $actor);

            return $payment->fresh();
        });
    }

    /**
     * Refund dibatalkan (uang tetap di perusahaan).
     */
    public function voidRefund(Payment $payment, ?User $actor = null, string $reason = ''): Payment
    {
        if ($payment->tipe !== Payment::TIPE_REFUND) {
            throw new BookingException('Pembayaran ini bukan refund.', 422);
        }

        if ($payment->isVerified()) {
            throw new BookingException('Refund yang sudah selesai tidak bisa dibatalkan.', 422);
        }

        $payment->update([
            'status' => Payment::STATUS_REJECTED,
            'rejected_reason' => $reason ?: 'Refund dibatalkan',
            'verified_by' => $actor?->id,
            'verified_at' => now(),
        ]);

        return $payment->fresh();
    }

    /**
     * Sinkronkan paid_total / balance_due / payment_status, lalu auto-confirm bila DP terpenuhi.
     */
    public function syncBooking(?Booking $booking, ?User $actor = null): Payment
    {
        if (! $booking) {
            return new Payment();
        }

        $totals = $this->pricing->recalculateTotals($booking);
        $booking->update($totals);

        if ($booking->status === Booking::STATUS_PENDING
            && (float) $booking->paid_total >= (float) $booking->dp_amount
            && (float) $booking->dp_amount > 0) {
            $this->bookings->confirm($booking->fresh(), $actor);
        }

        return $booking->payments()->latest('id')->first();
    }

    public function proofUrl(Payment $payment): ?string
    {
        if (! $payment->proof_path) {
            return null;
        }

        return Storage::disk('local')->exists($payment->proof_path)
            ? route('payment.proof', $payment->id)
            : null;
    }

    private function generateKode(Booking $booking, string $tipe): string
    {
        return $booking->kode_booking . '-' . strtoupper($tipe) . '-' . strtoupper(substr(uniqid(), -5));
    }
}
