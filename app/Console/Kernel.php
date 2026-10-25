<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Lepas hold DP yang kedaluwarsa supaya unit kembali tersedia.
        $schedule->command('bookings:expire-holds')->everyMinute()->withoutOverlapping();

        // Bersihkan jejak job gagal lama, dan pastikan antrean tidak menumpuk.
        $schedule->command('notifications:health --prune')
            ->hourly()
            ->emailOutputOnFailure(config('basecamp.admin_email'));
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
