<?php

namespace App\Mail;

use App\Models\Booking;
use App\Services\Notifications\BookingNotifier;
use App\Support\WhatsApp;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BookingStatusUpdate extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly Booking $booking,
        public readonly ?string $fromStatus,
        public readonly string $toStatus,
    ) {
    }

    public function build()
    {
        $label = $this->booking->labelStatus();

        return $this->subject('[' . $this->booking->kode_booking . '] ' . $label)
            ->view('Email.booking-status')
            ->with([
                'booking' => $this->booking,
                'statusLabel' => $label,
                'status' => $this->toStatus,
                'whatsappUrl' => app(BookingNotifier::class)->guestWhatsAppUrl($this->booking),
                'statusUrl' => route('booking.status', $this->booking->kode_booking),
                'payUrl' => route('payment.pay', $this->booking->kode_booking),
            ]);
    }
}