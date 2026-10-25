<?php

namespace Tests\Feature;

use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Room;
use App\Models\RoomUnit;
use App\Models\Setting;
use App\Services\BookingService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RefundTest extends TestCase
{
    use DatabaseTransactions;

    private RoomUnit $unit;
    private BookingService $bookings;
    private PaymentService $payments;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bookings = app(BookingService::class);
        $this->payments = app(PaymentService::class);

        $room = Room::create([
            'nama' => 'Tenda Refund',
            'qty' => 1,
            'kapasitas_max' => 4,
            'base_rate_weekday' => 100000,
            'base_rate_weekend' => 100000,
        ]);

        $this->unit = RoomUnit::create([
            'room_id' => $room->id,
            'kode' => 'RFD-' . strtoupper(substr(uniqid(), -5)),
            'status' => 'available',
        ]);
    }

    private function booking(): Booking
    {
        return $this->bookings->create([
            'room_unit_ids' => [$this->unit->id],
            'check_in' => now()->addDays(35)->toDateString(),
            'check_out' => now()->addDays(37)->toDateString(),
            'nama' => 'Tamu Refund',
            'email' => 'refund@uji.local',
            'hp' => '0811',
        ]);
    }

    private function payDp(Booking $booking): Booking
    {
        $invoice = $this->payments->createDpInvoice($booking);
        $this->payments->verify($invoice, null);

        return $booking->fresh();
    }

    /** @test */
    public function dp_non_refundable_saat_setting_off(): void
    {
        Setting::put('cancel_dp_refund', 0);

        $booking = $this->payDp($this->booking());
        $cancelled = $this->bookings->cancel($booking, null, 'Tamu batal');

        $this->assertSame(Booking::STATUS_CANCELLED, $cancelled->status);
        $this->assertSame(0.0, (float) $cancelled->refunded_total);
        $this->assertSame(0, $cancelled->payments()->where('tipe', Payment::TIPE_REFUND)->count());
        $this->assertSame(0.0, (float) $cancelled->balance_due);
    }

    /** @test */
    public function dp_direfund_saat_setting_on(): void
    {
        Setting::put('cancel_dp_refund', 1);

        $booking = $this->payDp($this->booking());
        $dpAmount = (float) $booking->dp_amount;

        $cancelled = $this->bookings->cancel($booking, null, 'Tamu batal');

        $refund = $cancelled->payments()->where('tipe', Payment::TIPE_REFUND)->first();

        $this->assertNotNull($refund);
        $this->assertSame($dpAmount, (float) $refund->amount);
        $this->assertSame(Payment::STATUS_PENDING, $refund->status);

        // Kas belum berkurang selama refund belum ditandai selesai.
        $this->assertSame(0.0, (float) $cancelled->refunded_total);
    }

    /** @test */
    public function menyelesaikan_refund_mengurangi_kas_dan_menandai_status_refund(): void
    {
        Setting::put('cancel_dp_refund', 1);

        $booking = $this->payDp($this->booking());
        $cancelled = $this->bookings->cancel($booking, null, 'Tamu batal');
        $refund = $cancelled->payments()->where('tipe', Payment::TIPE_REFUND)->first();

        $this->payments->completeRefund($refund, null);

        $cancelled->refresh();
        $this->assertSame((float) $refund->amount, (float) $cancelled->refunded_total);
        $this->assertSame(Booking::PAYMENT_REFUNDED, $cancelled->payment_status);
        $this->assertSame(0.0, (float) $cancelled->balance_due);
    }

    /** @test */
    public function refund_manual_tidak_boleh_melebihi_dana_diterima(): void
    {
        Setting::put('cancel_dp_refund', 0);

        $booking = $this->payDp($this->booking());

        $this->expectException(BookingException::class);

        $this->payments->createRefundInvoice($booking, (float) $booking->grand_total);
    }

    /** @test */
    public function refund_manual_diizinkan_untuk_pelunasan_penuh(): void
    {
        Setting::put('cancel_dp_refund', 0);

        $booking = $this->payDp($this->booking());
        $this->payments->recordCash($booking, (float) $booking->balance_due, Payment::TIPE_PELUNASAN, null);

        $booking->refresh();
        $refund = $this->payments->createRefundInvoice($booking, (float) $booking->paid_total, 'Tamu berubah pikiran');

        $this->assertSame((float) $booking->paid_total, (float) $refund->amount);
    }

    /** @test */
    public function refund_tidak_bisa_dibatalkan_setelah_selesai(): void
    {
        Setting::put('cancel_dp_refund', 1);

        $booking = $this->payDp($this->booking());
        $cancelled = $this->bookings->cancel($booking, null, 'Batal');
        $refund = $cancelled->payments()->where('tipe', Payment::TIPE_REFUND)->first();

        $this->payments->completeRefund($refund, null);

        $this->expectException(BookingException::class);
        $this->payments->voidRefund($refund->fresh(), null, 'salah input');
    }

    /** @test */
    public function refund_pending_bisa_dibatalkan(): void
    {
        Setting::put('cancel_dp_refund', 1);

        $booking = $this->payDp($this->booking());
        $cancelled = $this->bookings->cancel($booking, null, 'Batal');
        $refund = $cancelled->payments()->where('tipe', Payment::TIPE_REFUND)->first();

        $this->payments->voidRefund($refund, null, 'DP hangus');

        $this->assertSame(Payment::STATUS_REJECTED, $refund->fresh()->status);
        $this->assertSame(0.0, (float) $cancelled->fresh()->refunded_total);
    }

    /** @test */
    public function pembatalan_melepas_unit_walau_ada_refund(): void
    {
        Setting::put('cancel_dp_refund', 1);

        $booking = $this->payDp($this->booking());
        $this->bookings->cancel($booking, null, 'Batal');

        $this->assertSame(0, $booking->nights()->count());

        $again = $this->bookings->create([
            'room_unit_ids' => [$this->unit->id],
            'check_in' => now()->addDays(35)->toDateString(),
            'check_out' => now()->addDays(37)->toDateString(),
            'nama' => 'Tamu Baru',
            'email' => 'baru@uji.local',
            'hp' => '0811',
        ]);

        $this->assertSame(Booking::STATUS_PENDING, $again->status);
    }

    /** @test */
    public function endpoint_batal_mengembalikan_nominal_refund(): void
    {
        Setting::put('cancel_dp_refund', 1);

        $booking = $this->payDp($this->booking());
        $admin = \App\Models\User::firstOrCreate(
            ['email' => 'refund-admin@uji.local'],
            ['name' => 'Admin', 'password' => bcrypt('x'), 'role' => 'admin']
        );

        $response = $this->actingAs($admin)
            ->postJson(route('booking.batal', $booking->id), ['reason' => 'Tamu batal'])
            ->assertOk();

        $this->assertGreaterThan(0, $response->json('refund_amount'));
    }
}