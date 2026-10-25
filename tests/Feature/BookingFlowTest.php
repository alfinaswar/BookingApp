<?php

namespace Tests\Feature;

use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomUnit;
use App\Services\AvailabilityService;
use App\Services\BookingService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class BookingFlowTest extends TestCase
{
    use DatabaseTransactions;

    private Room $room;
    private RoomUnit $unit1;
    private RoomUnit $unit2;
    private BookingService $bookings;
    private PaymentService $payments;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bookings = app(BookingService::class);
        $this->payments = app(PaymentService::class);

        $this->room = Room::create([
            'nama' => 'Barak Uji',
            'qty' => 2,
            'kapasitas_max' => 4,
            'base_rate_weekday' => 200000,
            'base_rate_weekend' => 250000,
        ]);

        $this->unit1 = RoomUnit::create(['room_id' => $this->room->id, 'kode' => 'UJI-01', 'status' => 'available']);
        $this->unit2 = RoomUnit::create(['room_id' => $this->room->id, 'kode' => 'UJI-02', 'status' => 'available']);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'room_unit_ids' => [$this->unit1->id],
            'check_in' => '2026-12-01',
            'check_out' => '2026-12-03',
            'dewasa' => 2,
            'nama' => 'Tamu Uji',
            'email' => 'tamu@uji.local',
            'hp' => '081234567890',
        ], $overrides);
    }

    /** @test */
    public function booking_baru_membuat_satu_baris_night_per_malam(): void
    {
        $booking = $this->bookings->create($this->payload());

        $this->assertSame(Booking::STATUS_PENDING, $booking->status);
        $this->assertSame(2, $booking->malam);
        $this->assertSame(2, $booking->nights()->count());
        $this->assertSame(1, $booking->units()->count());
        $this->assertSame(['2026-12-01', '2026-12-02'], $booking->nights()->pluck('stay_date')
            ->map(fn ($d) => $d->toDateString())->all());
    }

    /** @test */
    public function overbooking_ditolak(): void
    {
        $this->bookings->create($this->payload());

        $this->expectException(BookingException::class);

        $this->bookings->create($this->payload(['nama' => 'Tamu Lain', 'email' => 'lain@uji.local']));
    }

    /** @test */
    public function tanggal_check_out_bisa_dipakai_tamu_berikutnya(): void
    {
        $this->bookings->create($this->payload());

        $next = $this->bookings->create($this->payload([
            'check_in' => '2026-12-03',
            'check_out' => '2026-12-05',
            'nama' => 'Tamu Kedua',
            'email' => 'dua@uji.local',
        ]));

        $this->assertSame(Booking::STATUS_PENDING, $next->status);
    }

    /** @test */
    public function multi_unit_dibuat_sekaligus_dan_semua_unit_terkunci(): void
    {
        $booking = $this->bookings->create($this->payload([
            'room_unit_ids' => [$this->unit1->id, $this->unit2->id],
            'dewasa' => 4,
        ]));

        $this->assertSame(2, $booking->jumlah_unit);
        $this->assertSame(4, $booking->nights()->count());

        // Hanya unit3 yang belum ada; kedua unit pertama harus terblokir.
        $conflicts = app(AvailabilityService::class)->conflictingUnits(
            [$this->unit1->id, $this->unit2->id],
            '2026-12-01',
            '2026-12-03'
        );

        $this->assertCount(2, $conflicts);
    }

    /** @test */
    public function harga_dari_client_diabaikan(): void
    {
        $booking = $this->bookings->create($this->payload([
            'grand_total' => 1,
            'subtotal' => 1,
            'dp_amount' => 1,
            'total' => 1,
        ]));

        // 1 Des 2026 Selasa (weekday 200k), 2 Des Rabu (weekday 200k) = 400k
        $this->assertSame(400000.0, (float) $booking->grand_total);
    }

    /** @test */
    public function booking_melebihi_kapasitas_ditolak(): void
    {
        $this->expectException(BookingException::class);

        $this->bookings->create($this->payload(['dewasa' => 10]));
    }

    /** @test */
    public function status_tidak_bisa_konfirmasi_sebelum_dp_terbayar(): void
    {
        $booking = $this->bookings->create($this->payload());

        $this->expectException(BookingException::class);

        $this->bookings->confirm($booking);
    }

    /** @test */
    public function verifikasi_dp_otomatis_mengonfirmasi_booking(): void
    {
        $booking = $this->bookings->create($this->payload());
        $invoice = $this->payments->createDpInvoice($booking);
        $invoice->update(['status' => 'waiting_verification']);

        $this->payments->verify($invoice, null);

        $booking->refresh();
        $this->assertSame(Booking::STATUS_CONFIRMED, $booking->status);
        $this->assertSame(Booking::PAYMENT_PARTIAL, $booking->payment_status);
        $this->assertGreaterThan(0, $booking->balance_due);
    }

    /** @test */
    public function check_out_ditolak_bila_masih_ada_saldo_tapi_boleh_override(): void
    {
        $booking = $this->bookings->create($this->payload());
        $invoice = $this->payments->createDpInvoice($booking);
        $this->payments->verify($invoice, null);
        $this->bookings->checkIn($booking->fresh(), null);

        try {
            $this->bookings->checkOut($booking->fresh(), null);
            $this->fail('Check-out seharusnya ditolak saat masih ada saldo.');
        } catch (BookingException $e) {
            $this->assertSame(422, $e->statusCode);
        }

        $this->bookings->checkOut($booking->fresh(), null, override: true);
        $this->assertSame(Booking::STATUS_CHECKED_OUT, $booking->fresh()->status);
    }

    /** @test */
    public function pelunasan_menutup_saldo_dan_booking_lunas(): void
    {
        $booking = $this->bookings->create($this->payload());
        $dp = $this->payments->createDpInvoice($booking);
        $this->payments->verify($dp, null);

        $booking->refresh();
        $this->payments->recordCash($booking, (float) $booking->balance_due, 'pelunasan', null);

        $booking->refresh();
        $this->assertSame(0.0, (float) $booking->balance_due);
        $this->assertSame(Booking::PAYMENT_PAID, $booking->payment_status);
    }

    /** @test */
    public function pembatalan_melepas_unit(): void
    {
        $booking = $this->bookings->create($this->payload());
        $this->bookings->cancel($booking, null, 'Tamu membatalkan');

        $this->assertSame(Booking::STATUS_CANCELLED, $booking->fresh()->status);
        $this->assertSame(0, $booking->nights()->count());

        // Unit bisa dipesan lagi
        $again = $this->bookings->create($this->payload([
            'nama' => 'Tamu Baru',
            'email' => 'baru@uji.local',
        ]));
        $this->assertSame(Booking::STATUS_PENDING, $again->status);
    }

    /** @test */
    public function hold_kedaluwarsa_dilepas_otomatis(): void
    {
        $booking = $this->bookings->create($this->payload());
        $booking->update(['expires_at' => now()->subMinute()]);

        $count = $this->bookings->expireHolds();

        $this->assertSame(1, $count);
        $this->assertSame(Booking::STATUS_EXPIRED, $booking->fresh()->status);
        $this->assertSame(0, $booking->nights()->count());
    }

    /** @test */
    public function setiap_transisi_tercatat_di_histori(): void
    {
        $booking = $this->bookings->create($this->payload());
        $dp = $this->payments->createDpInvoice($booking);
        $this->payments->verify($dp, null);
        $this->bookings->checkIn($booking->fresh(), null);

        $statuses = $booking->histories()->pluck('to_status')->all();

        $this->assertEqualsCanonicalizing(
            ['pending', 'confirmed', 'checked_in'],
            $statuses
        );
    }

    /** @test */
    public function unit_berstatus_maintenance_tidak_bisa_dipesan(): void
    {
        $this->unit1->update(['status' => RoomUnit::STATUS_MAINTENANCE]);

        $this->expectException(BookingException::class);

        $this->bookings->create($this->payload());
    }
}