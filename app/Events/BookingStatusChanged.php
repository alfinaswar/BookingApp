<?php

namespace App\Events;

use App\Models\Booking;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dipancarkan setiap kali status booking berubah.
 * Listener berjalan di queue, jadi request user tidak menunggu SMTP/WhatsApp.
 */
class BookingStatusChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Booking $booking,
        public readonly ?string $fromStatus,
        public readonly string $toStatus,
        public readonly ?string $note = null,
    ) {
    }
}