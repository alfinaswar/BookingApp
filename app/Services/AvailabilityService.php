<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingUnitNight;
use App\Models\InventoryBlock;
use App\Models\Room;
use App\Models\RoomUnit;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

/**
 * Sumber tunggal logika ketersediaan unit.
 *
 * Model malam: [check_in, check_out) — malam terakhir tidak dikonsumsi,
 * sehingga tanggal check-out bisa dipakai tamu berikutnya.
 */
class AvailabilityService
{
    /**
     * Unit yang bentrok pada rentang tanggal.
     *
     * @param  array<int,int>  $unitIds
     * @return array<int,int>
     */
    public function conflictingUnits(array $unitIds, string $checkIn, string $checkOut, ?int $ignoreBookingId = null): array
    {
        $unitIds = array_values(array_unique(array_map('intval', $unitIds)));

        if (empty($unitIds) || ! $this->validRange($checkIn, $checkOut)) {
            return $unitIds;
        }

        $booked = BookingUnitNight::query()
            ->whereIn('room_unit_id', $unitIds)
            ->whereDate('stay_date', '>=', $checkIn)
            ->whereDate('stay_date', '<', $checkOut)
            ->whereIn('status', [BookingUnitNight::STATUS_HELD, BookingUnitNight::STATUS_BOOKED])
            ->when($ignoreBookingId, fn ($q) => $q->where('booking_id', '<>', $ignoreBookingId))
            ->pluck('room_unit_id')
            ->all();

        $blocked = InventoryBlock::query()
            ->whereIn('room_unit_id', $unitIds)
            ->whereDate('start_date', '<', $checkOut)
            ->whereDate('end_date', '>', $checkIn)
            ->pluck('room_unit_id')
            ->all();

        $inactive = RoomUnit::query()
            ->whereIn('id', $unitIds)
            ->where('status', '<>', RoomUnit::STATUS_AVAILABLE)
            ->pluck('id')
            ->all();

        return array_values(array_unique(array_merge($booked, $blocked, $inactive)));
    }

    public function isUnitAvailable(int $unitId, string $checkIn, string $checkOut, ?int $ignoreBookingId = null): bool
    {
        if (! $this->validRange($checkIn, $checkOut)) {
            return false;
        }

        return empty($this->conflictingUnits([$unitId], $checkIn, $checkOut, $ignoreBookingId));
    }

    /**
     * Daftar unit tersedia, dikelompokkan per tipe.
     */
    public function availableUnits(string $checkIn, string $checkOut, ?int $roomId = null): Collection
    {
        $units = RoomUnit::active()
            ->with('room.roomtypes')
            ->when($roomId, fn ($q) => $q->where('room_id', $roomId))
            ->orderBy('room_id')
            ->orderBy('kode')
            ->get();

        $conflicts = $this->conflictingUnits($units->pluck('id')->all(), $checkIn, $checkOut);

        return $units->reject(fn (RoomUnit $u) => in_array($u->id, $conflicts, true))->values();
    }

    /**
     * Matriks ketersediaan untuk ditampilkan di UI.
     *
     * @return array{from:string,to:string,dates:array<int,string>,types:array<int,array<string,mixed>>}
     */
    public function calendar(string $from, string $to, ?int $roomId = null): array
    {
        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->startOfDay();

        if ($end->lessThanOrEqualTo($start)) {
            $end = $start->copy()->addDay();
        }

        $maxDays = 90;
        if ($start->diffInDays($end) > $maxDays) {
            $end = $start->copy()->addDays($maxDays);
        }

        $dates = collect(CarbonPeriod::create($start, $end->copy()->subDay()))
            ->map(fn (Carbon $d) => $d->toDateString())
            ->values()
            ->all();

        $units = RoomUnit::active()
            ->with('room.roomtypes')
            ->when($roomId, fn ($q) => $q->where('room_id', $roomId))
            ->orderBy('room_id')
            ->orderBy('kode')
            ->get();

        $nights = BookingUnitNight::query()
            ->whereIn('room_unit_id', $units->pluck('id'))
            ->whereDate('stay_date', '>=', $start->toDateString())
            ->whereDate('stay_date', '<', $end->toDateString())
            ->whereIn('status', [BookingUnitNight::STATUS_HELD, BookingUnitNight::STATUS_BOOKED])
            ->get()
            ->keyBy(fn (BookingUnitNight $n) => $n->room_unit_id . '|' . $n->stay_date->toDateString());

        $blocks = InventoryBlock::query()
            ->whereIn('room_unit_id', $units->pluck('id'))
            ->whereDate('start_date', '<', $end->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString())
            ->get();

        $types = [];

        foreach ($units as $unit) {
            $room = $unit->room;
            if (! $room) {
                continue;
            }

            $typeKey = $room->id;

            if (! isset($types[$typeKey])) {
                $types[$typeKey] = [
                    'room_id' => $room->id,
                    'tipe' => $room->namaTipe(),
                    'nama' => $room->nama,
                    'kapasitas_max' => (int) $room->kapasitas_max,
                    'base_rate_weekday' => $room->baseRate(false),
                    'base_rate_weekend' => $room->baseRate(true),
                    'units' => [],
                ];
            }

            $cells = [];

            foreach ($dates as $date) {
                $state = 'available';
                $note = null;

                if ($nights->has($unit->id . '|' . $date)) {
                    $state = 'booked';
                } else {
                    $blocked = $blocks->first(
                        fn (InventoryBlock $b) => (int) $b->room_unit_id === (int) $unit->id
                            && $b->start_date->toDateString() < $date
                            && $b->end_date->toDateString() > $date
                    );

                    if ($blocked) {
                        $state = 'blocked';
                        $note = $blocked->reason;
                    }
                }

                $cells[$date] = ['state' => $state, 'note' => $note];
            }

            $types[$typeKey]['units'][] = [
                'unit_id' => $unit->id,
                'kode' => $unit->kode,
                'nama' => $unit->label(),
                'cells' => $cells,
            ];
        }

        return [
            'from' => $start->toDateString(),
            'to' => $end->toDateString(),
            'dates' => $dates,
            'types' => array_values($types),
        ];
    }

    private function validRange(string $checkIn, string $checkOut): bool
    {
        try {
            return Carbon::parse($checkOut)->startOfDay()->greaterThan(Carbon::parse($checkIn)->startOfDay());
        } catch (\Throwable) {
            return false;
        }
    }
}
