<?php

namespace App\Listeners;

use App\Events\PaymentProofSubmitted;
use App\Services\Notifications\BookingNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendPaymentProofNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(private readonly BookingNotifier $notifier)
    {
    }

    public function handle(PaymentProofSubmitted $event): void
    {
        $this->notifier->notifyProofReceived($event->booking, $event->payment);
    }
}