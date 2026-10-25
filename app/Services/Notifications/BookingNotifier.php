<?php

namespace App\Services\Notifications;

use App\Mail\AdminPaymentProof;
use App\Mail\BookingStatusUpdate;
use App\Models\Booking;
use App\Models\Payment;
use App\Support\WhatsApp;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Semua notifikasi keluar terpusat di sini.
 *
 * Email dikirim via queue (listener ShouldQueue). WhatsApp memakai deep link
 * wa.me: tidak butuh provider, tapi butuh satu klik dari admin/tamu.
 * Bila nanti memakai WhatsApp Business API, cukup ganti method whatsappUrl().
 */
class BookingNotifier
{
    public function notifyStatus(Booking $booking, ?string $from, string $to): void
    {
        $booking->loadMissing(['units.unit', 'units.room.roomtypes', 'payments']);

        $this->sendMail($booking->Email, new BookingStatusUpdate($booking, $from, $to));

        Log::info('[notifikasi] status booking', [
            'kode' => $booking->kode_booking,
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function notifyProofReceived(Booking $booking, Payment $payment): void
    {
        $adminEmail = config('basecamp.admin_email');

        if ($adminEmail) {
            $this->sendMail($adminEmail, new AdminPaymentProof($booking, $payment));
        }

        Log::info('[notifikasi] bukti bayar masuk', [
            'kode' => $booking->kode_booking,
            'payment' => $payment->kode_pembayaran,
        ]);
    }

    /**
     * Link WhatsApp untuk tamu, berisi ringkasan booking.
     */
    public function guestWhatsAppUrl(Booking $booking, string $context = 'konfirmasi'): ?string
    {
        $message = sprintf(
            "Halo Admin Basecamp Military Lifestyle, saya ingin %s booking %s (%s s/d %s).",
            $context,
            $booking->kode_booking,
            $booking->check_in_date?->format('d/m/Y') ?? '-',
            $booking->check_out_date?->format('d/m/Y') ?? '-',
        );

        return WhatsApp::link($booking->hp, $message);
    }

    /**
     * Link WhatsApp untuk admin, berisi instruksi follow-up.
     */
    public function adminWhatsAppUrl(Booking $booking, string $context = 'konfirmasi pembayaran'): ?string
    {
        $message = sprintf(
            "Follow up %s untuk booking %s - %s (sisa Rp %s).",
            $context,
            $booking->kode_booking,
            $booking->NamaBooking,
            number_format((float) $booking->balance_due, 0, ',', '.'),
        );

        return WhatsApp::adminLink($message);
    }

    private function sendMail(?string $to, $mailable): void
    {
        if (! $to || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            Log::warning('[notifikasi] email tidak valid, dilewati', ['to' => $to]);

            return;
        }

        try {
            Mail::to($to)->send($mailable);
        } catch (\Throwable $e) {
            // Notifikasi gagal tidak boleh menggagalkan transaksi bisnis.
            Log::error('[notifikasi] gagal kirim email: ' . $e->getMessage(), ['to' => $to]);
        }
    }
}