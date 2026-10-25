<?php

namespace App\Listeners;

use App\Events\BookingStatusChanged;
use App\Models\Booking;
use App\Services\Notifications\BookingNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendBookingStatusNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(private readonly BookingNotifier $notifier)
    {
    }

    public function handle(BookingStatusChanged $event): void
    {
        // Booking pending tidak perlu notifikasi (tamu baru saja di halaman itu),
        // kecuali expired yang perlu diberi tahu.
        if ($event->toStatus === Booking::STATUS_PENDING) {
            return;
        }

        $this->notifier->notifyStatus($event->booking, $event->fromStatus, $event->toStatus);
    }
}