<?php

namespace App\Events;

use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Bukti transfer diunggah tamu, menunggu verifikasi admin.
 */
class PaymentProofSubmitted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Payment $payment,
        public readonly Booking $booking,
    ) {
    }
}