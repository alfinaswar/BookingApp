<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\BookingUnit;
use App\Models\BookingUnitNight;
use App\Models\RoomUnit;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Backfill data lama ke skema baru (Mode A).
 *
 * 1. rooms.qty  -> room_units (bila belum ada)
 * 2. bookings   -> booking_units + booking_unit_nights
 *
 * Konflik tidak di-resolve otomatis: dilaporkan ke storage/app/backfill-conflicts-*.log
 * agar diputuskan manual oleh admin.
 */
class BackfillInventory extends Command
{
    protected $signature = 'bookings:backfill-inventory
                            {--dry-run : Laporkan saja, tidak menulis apa pun}
                            {--units : Hanya generate room_units dari rooms.qty}
                            {--bookings : Hanya backfill booking lama}';

    protected $description = 'Migrasi booking lama ke booking_units & booking_unit_nights';

    private array $conflicts = [];

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $onlyUnits = (bool) $this->option('units');
        $onlyBookings = (bool) $this->option('bookings');

        if (! $onlyBookings) {
            $this->generateUnits($dry);
        }

        if (! $onlyUnits) {
            $this->backfillBookings($dry);
        }

        if (! empty($this->conflicts)) {
            $path = storage_path('app/backfill-conflicts-' . now()->format('Ymd-His') . '.log');
            file_put_contents($path, implode(PHP_EOL, $this->conflicts));
            $this->warn(count($this->conflicts) . ' konflik dilaporkan ke ' . $path);
        }

        return self::SUCCESS;
    }

    private function generateUnits(bool $dry): void
    {
        $rooms = \App\Models\Room::with('roomtypes')->get();
        $created = 0;

        foreach ($rooms as $room) {
            $existing = RoomUnit::where('room_id', $room->id)->count();
            $target = max((int) $room->qty, 1);

            if ($existing >= $target) {
                continue;
            }

            $prefix = strtoupper(preg_replace('/[^A-Z0-9]/', '', \Illuminate\Support\Str::slug($room->nama, ''))) ?: 'UNIT';
            $prefix = substr($prefix, 0, 8);

            for ($i = $existing + 1; $i <= $target; $i++) {
                if (! $dry) {
                    RoomUnit::firstOrCreate(
                        ['kode' => sprintf('%s-%02d', $prefix, $i)],
                        ['room_id' => $room->id, 'nama' => $room->nama . ' ' . $i, 'status' => RoomUnit::STATUS_AVAILABLE]
                    );
                }
                $created++;
            }
        }

        $this->info(($dry ? '[dry-run] ' : '') . "{$created} unit dibuat.");
    }

    private function backfillBookings(bool $dry): void
    {
        $bookings = Booking::withTrashed()
            ->whereDoesntHave('units')
            ->whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_CHECKED_IN, Booking::STATUS_CHECKED_OUT, Booking::STATUS_PENDING])
            ->get();

        $done = 0;

        foreach ($bookings as $booking) {
            $checkIn = $booking->check_in_date ?? ($booking->checkIn ? Carbon::parse($booking->checkIn) : null);
            $checkOut = $booking->check_out_date ?? ($booking->checkOut ? Carbon::parse($booking->checkOut) : null);

            if (! $checkIn || ! $checkOut || $checkOut->lessThanOrEqualTo($checkIn)) {
                $this->conflicts[] = "booking#{$booking->id}: rentang tanggal tidak valid ({$checkIn} -> {$checkOut})";

                continue;
            }

            $unit = RoomUnit::where('room_id', $booking->roomId)->orderBy('kode')->first();

            if (! $unit) {
                $this->conflicts[] = "booking#{$booking->id}: tidak ada unit untuk room_id {$booking->roomId}";

                continue;
            }

            $nights = CarbonPeriod::create($checkIn->copy()->startOfDay(), $checkOut->copy()->subDay()->startOfDay());
            $taken = BookingUnitNight::where('room_unit_id', $unit->id)
                ->whereIn('stay_date', collect($nights)->map(fn ($d) => $d->toDateString()))
                ->whereIn('status', [BookingUnitNight::STATUS_HELD, BookingUnitNight::STATUS_BOOKED])
                ->exists();

            if ($taken) {
                $this->conflicts[] = "booking#{$booking->id}: unit {$unit->kode} sudah terpakai pada rentang tersebut";

                continue;
            }

            $nightCount = $checkIn->startOfDay()->diffInDays($checkOut->startOfDay());
            $rate = $nightCount > 0 ? round((float) $booking->grand_total / $nightCount, 2) : 0;

            if (! $dry) {
                DB::transaction(function () use ($booking, $unit, $nights, $rate) {
                    $bookingUnit = BookingUnit::create([
                        'booking_id' => $booking->id,
                        'room_unit_id' => $unit->id,
                        'room_id' => $booking->roomId,
                        'check_in_date' => $booking->check_in_date,
                        'check_out_date' => $booking->check_out_date,
                        'urutan' => 1,
                        'subtotal' => $booking->grand_total,
                        'rate_snapshot' => [],
                    ]);

                    foreach ($nights as $date) {
                        BookingUnitNight::create([
                            'booking_id' => $booking->id,
                            'booking_unit_id' => $bookingUnit->id,
                            'room_unit_id' => $unit->id,
                            'stay_date' => $date->toDateString(),
                            'rate' => $rate,
                            'status' => $booking->status === Booking::STATUS_CHECKED_OUT
                                ? BookingUnitNight::STATUS_RELEASED
                                : BookingUnitNight::STATUS_BOOKED,
                        ]);
                    }
                });
            }

            $done++;
        }

        $this->info(($dry ? '[dry-run] ' : '') . "{$done} booking dimigrasi.");
    }
}