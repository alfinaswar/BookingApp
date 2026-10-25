<?php

namespace App\Console\Commands;

use App\Services\BookingService;
use Illuminate\Console\Command;

class ExpireBookingHolds extends Command
{
    protected $signature = 'bookings:expire-holds';

    protected $description = 'Lepas hold booking yang tidak dibayar melewati batas waktu';

    public function handle(BookingService $bookings): int
    {
        $count = $bookings->expireHolds();

        $this->info("{$count} booking kedaluwarsa diproses.");

        return self::SUCCESS;
    }
}