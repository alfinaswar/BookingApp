<?php

namespace Database\Seeders;

use App\Models\Room;
use App\Models\RoomUnit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Mode A: buat 10 unit fisik per tipe akomodasi.
 * Idempoten — aman dijalankan berulang.
 */
class RoomUnitSeeder extends Seeder
{
    public const UNITS_PER_TYPE = 10;

    public function run(): void
    {
        $rooms = Room::with('roomtypes')->orderBy('id')->get();

        if ($rooms->isEmpty()) {
            $this->command?->warn('Belum ada room/tipe. Seed unit dilewati.');

            return;
        }

        foreach ($rooms as $room) {
            $prefix = $this->prefixFor($room);

            for ($i = 1; $i <= self::UNITS_PER_TYPE; $i++) {
                RoomUnit::firstOrCreate(
                    ['kode' => sprintf('%s-%02d', $prefix, $i)],
                    [
                        'room_id' => $room->id,
                        'nama' => $room->nama . ' ' . $i,
                        'status' => RoomUnit::STATUS_AVAILABLE,
                    ]
                );
            }

            $room->forceFill([
                'qty' => RoomUnit::where('room_id', $room->id)->count(),
                'kapasitas_base' => $room->kapasitas_base ?: 2,
                'kapasitas_max' => $room->kapasitas_max ?: 4,
                'is_active' => true,
            ])->save();
        }

        $this->command?->info('Unit tersedia: ' . RoomUnit::count() . ' unit untuk ' . $rooms->count() . ' tipe.');
    }

    private function prefixFor(Room $room): string
    {
        $base = Str::upper(Str::slug($room->roomtypes->nama ?? $room->nama, ''));
        $base = preg_replace('/[^A-Z0-9]/', '', $base) ?: 'UNIT';

        return Str::limit($base, 8, '');
    }
}