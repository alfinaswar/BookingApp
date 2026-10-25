<?php

namespace App\Mail;

use App\Models\Booking;
use App\Models\Payment;
use App\Services\Notifications\BookingNotifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AdminPaymentProof extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly Booking $booking,
        public readonly Payment $payment,
    ) {
    }

    public function build()
    {
        return $this->subject('Bukti bayar baru: ' . $this->booking->kode_booking)
            ->view('Email.admin-payment-proof')
            ->with([
                'booking' => $this->booking,
                'payment' => $this->payment,
                'detailUrl' => route('booking.status', $this->booking->kode_booking),
                'whatsappUrl' => app(BookingNotifier::class)->adminWhatsAppUrl($this->booking, 'verifikasi pembayaran'),
            ]);
    }
}