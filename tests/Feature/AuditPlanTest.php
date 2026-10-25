<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomUnit;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Audit plan: butir yang diklaim pada plan awal tapi belum punya test.
 */
class AuditPlanTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::firstOrCreate(
            ['email' => 'audit-admin@uji.local'],
            ['name' => 'Admin', 'password' => bcrypt('x'), 'role' => 'admin']
        );
    }

    /** @test */
    public function nomor_hp_disimpan_dengan_nol_di_depan(): void
    {
        $unit = RoomUnit::firstOrFail();

        $booking = app(BookingService::class)->create([
            'room_unit_ids' => [$unit->id],
            'check_in' => now()->addDays(100)->toDateString(),
            'check_out' => now()->addDays(101)->toDateString(),
            'nama' => 'Tamu HP',
            'email' => 'hp@audit.local',
            'hp' => '081234567890',
        ]);

        $this->assertSame('081234567890', (string) $booking->fresh()->hp, 'Nol di depan tidak boleh hilang.');

        // Deep link WhatsApp memakai nomor yang benar.
        $this->assertStringContainsString(
            '6281234567890',
            (string) app(\App\Services\Notifications\BookingNotifier::class)->guestWhatsAppUrl($booking->fresh())
        );
    }

    /** @test */
    public function endpoint_ketersediaan_mengembalikan_unit_tersedia(): void
    {
        $unit = RoomUnit::firstOrFail();

        $this->actingAs($this->admin)
            ->getJson(route('booking.ketersediaan', [
                'check_in' => now()->addDays(110)->toDateString(),
                'check_out' => now()->addDays(112)->toDateString(),
            ]))
            ->assertOk()
            ->assertJsonStructure(['total', 'units' => [['unit_id', 'kode', 'tipe', 'tarif_weekday']]]);
    }

    /** @test */
    public function unit_yang_sudah_dipesan_hilang_dari_endpoint_ketersediaan(): void
    {
        $unit = RoomUnit::firstOrFail();
        $checkIn = now()->addDays(120)->toDateString();
        $checkOut = now()->addDays(122)->toDateString();

        app(BookingService::class)->create([
            'room_unit_ids' => [$unit->id],
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'nama' => 'Tamu Ketersediaan',
            'email' => 'tersedia@audit.local',
            'hp' => '0811',
        ]);

        $units = $this->actingAs($this->admin)
            ->getJson(route('booking.ketersediaan', ['check_in' => $checkIn, 'check_out' => $checkOut]))
            ->json('units');

        $this->assertNotContains(
            $unit->id,
            array_column($units, 'unit_id'),
            'Unit yang sudah dibooking tidak boleh muncul sebagai tersedia.'
        );
    }

    /** @test */
    public function ketersediaan_menolak_rentang_tanggal_tidak_valid(): void
    {
        $this->actingAs($this->admin)
            ->getJson(route('booking.ketersediaan', [
                'check_in' => now()->addDays(130)->toDateString(),
                'check_out' => now()->addDays(130)->toDateString(),
            ]))
            ->assertStatus(422);
    }

    /** @test */
    public function status_dan_payment_status_sinkron_setelah_semua_transisi(): void
    {
        $unit = RoomUnit::firstOrFail();
        $bookings = app(BookingService::class);
        $payments = app(\App\Services\PaymentService::class);

        $booking = $bookings->create([
            'room_unit_ids' => [$unit->id],
            'check_in' => now()->addDays(140)->toDateString(),
            'check_out' => now()->addDays(142)->toDateString(),
            'nama' => 'Tamu Transisi',
            'email' => 'transisi@audit.local',
            'hp' => '0811',
        ]);

        $assert = function (string $status, string $payment) use (&$booking) {
            $fresh = $booking->fresh();
            $this->assertSame($status, $fresh->status, 'status setelah transisi');
            $this->assertSame($payment, $fresh->payment_status, 'payment_status setelah transisi');
        };

        $assert(Booking::STATUS_PENDING, Booking::PAYMENT_UNPAID);

        $dp = $payments->createDpInvoice($booking);
        $payments->verify($dp, null);
        $assert(Booking::STATUS_CONFIRMED, Booking::PAYMENT_PARTIAL);

        $bookings->checkIn($booking->fresh(), null);
        $assert(Booking::STATUS_CHECKED_IN, Booking::PAYMENT_PARTIAL);

        $payments->recordCash($booking->fresh(), (float) $booking->fresh()->balance_due, 'pelunasan', null);
        $assert(Booking::STATUS_CHECKED_IN, Booking::PAYMENT_PAID);

        $bookings->checkOut($booking->fresh(), null);
        $assert(Booking::STATUS_CHECKED_OUT, Booking::PAYMENT_PAID);
    }

    /** @test */
    public function revenue_laporan_memisahkan_akrual_dan_kas(): void
    {
        $unit = RoomUnit::firstOrFail();
        $checkIn = now()->addDays(150);
        $checkOut = now()->addDays(152);

        $booking = app(BookingService::class)->create([
            'room_unit_ids' => [$unit->id],
            'check_in' => $checkIn->toDateString(),
            'check_out' => $checkOut->toDateString(),
            'nama' => 'Tamu Laporan',
            'email' => 'laporan@audit.local',
            'hp' => '0811',
        ]);

        $dp = app(\App\Services\PaymentService::class)->createDpInvoice($booking);
        app(\App\Services\PaymentService::class)->verify($dp, null);

        // Akrual berbasis TANGGAL INAP: seluruh nilai inap masuk di periode inap.
        $accrual = $this->actingAs($this->admin)
            ->getJson(route('laporan.pendapatan', [
                'from' => $checkIn->toDateString(),
                'to' => $checkOut->addDay()->toDateString(),
            ]))
            ->json('accrual_total');

        // Kas berbasis TANGGAL BAYAR: DP diterima hari ini, bukan di masa inap.
        $cash = $this->actingAs($this->admin)
            ->getJson(route('laporan.pendapatan', [
                'from' => now()->toDateString(),
                'to' => now()->addDay()->toDateString(),
            ]))
            ->json('cash_total');

        $this->assertEqualsWithDelta((float) $booking->grand_total, (float) $accrual, 1.0, 'Akrual = nilai inap penuh');
        $this->assertEqualsWithDelta((float) $booking->dp_amount, (float) $cash, 1.0, 'Kas = DP yang sudah diterima');

        // Periode inap di masa depan tidak punya kas masuk — pembuktian dua basis berbeda.
        $cashFuture = $this->actingAs($this->admin)
            ->getJson(route('laporan.pendapatan', [
                'from' => $checkIn->toDateString(),
                'to' => $checkOut->addDay()->toDateString(),
            ]))
            ->json('cash_total');

        $this->assertSame(0.0, (float) $cashFuture, 'Kas di periode inap masa depan harus nol.');
    }

    /** @test */
    public function okupansi_maksimal_seratus_persen(): void
    {
        $params = ['from' => now()->toDateString(), 'to' => now()->addDays(30)->toDateString()];

        $data = $this->actingAs($this->admin)
            ->getJson(route('laporan.okupansi', $params))
            ->assertOk()
            ->json();

        $this->assertLessThanOrEqual(100, (float) $data['occupancy']);
        $this->assertGreaterThanOrEqual(0, (float) $data['occupancy']);
    }
}