<?php

namespace Tests\Feature;

use App\Exceptions\BookingException;
use App\Models\Room;
use App\Models\RoomUnit;
use App\Services\BookingService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Menguji jaminan anti-overbooking di level database, bukan hanya di aplikasi.
 *
 * Test ini memakai koneksi terpisah dan TIDAK memakai DatabaseTransactions,
 * supaya benar-benar menguji UNIQUE(room_unit_id, stay_date).
 */
class ConcurrencyTest extends TestCase
{
    private Room $room;
    private RoomUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->room = Room::create([
            'nama' => 'Tenda Concurrency ' . uniqid(),
            'qty' => 1,
            'kapasitas_max' => 4,
            'base_rate_weekday' => 100000,
            'base_rate_weekend' => 100000,
        ]);

        $this->unit = RoomUnit::create([
            'room_id' => $this->room->id,
            'kode' => 'CONC-' . strtoupper(substr(uniqid(), -6)),
            'status' => 'available',
        ]);
    }

    protected function tearDown(): void
    {
        // Bersihkan data test ini saja (test lain memakai transaksi, di sini manual).
        DB::table('booking_unit_nights')->where('room_unit_id', $this->unit->id)->delete();
        DB::table('booking_units')->where('room_unit_id', $this->unit->id)->delete();
        DB::table('booking_status_histories')->whereIn(
            'booking_id',
            DB::table('bookings')->where('roomId', $this->room->id)->pluck('id')
        )->delete();
        DB::table('bookings')->where('roomId', $this->room->id)->delete();
        DB::table('room_units')->where('id', $this->unit->id)->delete();
        DB::table('rooms')->where('id', $this->room->id)->delete();

        parent::tearDown();
    }

    /** @test */
    public function unique_index_menolak_baris_night_ganda_untuk_unit_yang_sama(): void
    {
        $service = app(BookingService::class);
        $payload = [
            'room_unit_ids' => [$this->unit->id],
            'check_in' => now()->addDays(60)->toDateString(),
            'check_out' => now()->addDays(62)->toDateString(),
            'nama' => 'Tamu A',
            'email' => 'a@conc.local',
            'hp' => '0811',
        ];

        $first = $service->create($payload);

        // Booking kedua lewat jalur aplikasi harus ditolak lebih awal.
        try {
            $service->create(array_merge($payload, ['nama' => 'Tamu B', 'email' => 'b@conc.local']));
            $this->fail('Overbooking lolos di level aplikasi.');
        } catch (BookingException $e) {
            $this->assertSame(409, $e->statusCode);
        }

        // Simulasi race: insert langsung ke tabel, melewati semua pengecekan aplikasi.
        $night = $first->nights()->first();

        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('booking_unit_nights')->insert([
            'booking_id' => $first->id,
            'booking_unit_id' => $night->booking_unit_id,
            'room_unit_id' => $this->unit->id,
            'stay_date' => $night->stay_date,
            'rate' => 100000,
            'status' => 'held',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @test */
    public function rollback_tidak_meninggalkan_hold_yatim(): void
    {
        $service = app(BookingService::class);
        $before = DB::table('booking_unit_nights')->where('room_unit_id', $this->unit->id)->count();

        try {
            DB::transaction(function () use ($service) {
                $service->create([
                    'room_unit_ids' => [$this->unit->id],
                    'check_in' => now()->addDays(70)->toDateString(),
                    'check_out' => now()->addDays(71)->toDateString(),
                    'nama' => 'Tamu Rollback',
                    'email' => 'r@conc.local',
                    'hp' => '0811',
                ]);

                throw new \RuntimeException('gagal setelah hold dibuat');
            });
        } catch (\RuntimeException) {
            // diabaikan
        }

        $after = DB::table('booking_unit_nights')->where('room_unit_id', $this->unit->id)->count();

        $this->assertSame($before, $after, 'Hold harus ikut rollback bila transaksi gagal.');
    }
}