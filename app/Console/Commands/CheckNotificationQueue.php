<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Cek kesehatan antrean notifikasi.
 *
 * Notifikasi berjalan di queue, jadi kegagalan tidak terlihat di UI.
 * Command ini dipakai untuk monitoring (cron alert / health check).
 *
 * Exit code 1 bila ada job gagal, agar mudah dipantau.
 */
class CheckNotificationQueue extends Command
{
    protected $signature = 'notifications:health {--prune : Hapus entri failed_jobs yang lebih tua dari 7 hari}';

    protected $description = 'Laporkan status antrean notifikasi dan job yang gagal';

    public function handle(): int
    {
        if ($this->option('prune')) {
            $deleted = DB::table('failed_jobs')->where('failed_at', '<', now()->subDays(7))->delete();
            $this->info("{$deleted} entri failed_jobs lama dihapus.");
        }

        $pending = $this->countJobs();
        $failed = DB::table('failed_jobs')->count();
        $channel = config('queue.default');

        $this->newLine();
        $this->table(['Metrik', 'Nilai'], [
            ['Queue driver', $channel],
            ['Job menunggu', $pending],
            ['Job gagal', $failed],
        ]);

        if ($channel === 'sync') {
            $this->warn('QUEUE_CONNECTION=sync: notifikasi dikirim langsung di request cycle.');
            $this->line('Untuk produksi, set QUEUE_CONNECTION=database dan jalankan: php artisan queue:work');
        }

        if ($pending > 0 && $channel !== 'sync') {
            $this->line('Pastikan worker jalan: php artisan queue:work --tries=3');
        }

        if ($failed > 0) {
            $this->newLine();
            $this->error("Ada {$failed} job notifikasi gagal.");
            $this->line('Periksa dengan: php artisan queue:failed');

            $recent = DB::table('failed_jobs')->latest('failed_at')->limit(5)->get(['uuid', 'failed_at', 'exception']);

            foreach ($recent as $row) {
                $this->line('· ' . substr((string) $row->failed_at, 0, 19) . ' — ' . Str::limit($row->exception, 90));
            }

            return self::FAILURE;
        }

        $this->info('Antrean notifikasi sehat.');

        return self::SUCCESS;
    }

    private function countJobs(): int
    {
        try {
            return DB::table('jobs')->count();
        } catch (\Throwable) {
            return 0;
        }
    }
}