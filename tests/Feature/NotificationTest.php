<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Room;
use App\Models\RoomUnit;
use App\Services\BookingService;
use App\Services\PaymentService;
use App\Support\WhatsApp;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotificationTest extends TestCase
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
            'nama' => 'Tenda Notif',
            'qty' => 1,
            'kapasitas_max' => 4,
            'base_rate_weekday' => 100000,
            'base_rate_weekend' => 100000,
        ]);

        $this->unit = RoomUnit::create([
            'room_id' => $room->id,
            'kode' => 'NOTIF-01',
            'status' => 'available',
        ]);
    }

    private function createBooking(): Booking
    {
        return $this->bookings->create([
            'room_unit_ids' => [$this->unit->id],
            'check_in' => now()->addDays(40)->toDateString(),
            'check_out' => now()->addDays(42)->toDateString(),
            'dewasa' => 2,
            'nama' => 'Tamu Notif',
            'email' => 'notif@uji.local',
            'hp' => '081298765432',
        ]);
    }

    /** @test */
    public function email_status_terkirim_saat_booking_dikonfirmasi(): void
    {
        Mail::fake();

        $booking = $this->createBooking();
        $invoice = $this->payments->createDpInvoice($booking);
        $this->payments->verify($invoice, null);

        Mail::assertQueued(\App\Mail\BookingStatusUpdate::class, function ($mail) use ($booking) {
            return $mail->hasTo('notif@uji.local')
                && $mail->toStatus === Booking::STATUS_CONFIRMED
                && $mail->booking->id === $booking->id;
        });
    }

    /** @test */
    public function tidak_ada_email_untuk_status_pending(): void
    {
        Mail::fake();

        $this->createBooking();

        Mail::assertNotQueued(\App\Mail\BookingStatusUpdate::class);
    }

    /** @test */
    public function email_kedaluwarsa_terkirim(): void
    {
        Mail::fake();

        $booking = $this->createBooking();
        $booking->update(['expires_at' => now()->subMinute()]);
        $this->bookings->expireHolds();

        Mail::assertQueued(\App\Mail\BookingStatusUpdate::class, function ($mail) {
            return $mail->toStatus === Booking::STATUS_EXPIRED;
        });
    }

    /** @test */
    public function email_terkirim_juga_bila_transisi_di_dalam_transaksi_luar(): void
    {
        Mail::fake();

        $booking = $this->createBooking();
        $invoice = $this->payments->createDpInvoice($booking);

        // PaymentController membungkus verify() dalam transaksi.
        \Illuminate\Support\Facades\DB::transaction(function () use ($invoice) {
            $this->payments->verify($invoice, null);
        });

        Mail::assertQueued(\App\Mail\BookingStatusUpdate::class, function ($mail) {
            return $mail->toStatus === Booking::STATUS_CONFIRMED;
        });
    }

    /** @test */
    public function notifikasi_admin_terkirim_saat_bukti_diupload(): void
    {
        Mail::fake();
        config(['basecamp.admin_email' => 'admin@basecamp.local']);

        $booking = $this->createBooking();
        $invoice = $this->payments->createDpInvoice($booking);

        $file = \Illuminate\Http\UploadedFile::fake()->image('bukti.jpg');
        $this->payments->submitManualProof($invoice, $file);

        Mail::assertQueued(\App\Mail\AdminPaymentProof::class, function ($mail) {
            return $mail->hasTo('admin@basecamp.local');
        });
    }

    /** @test */
    public function email_tidak_valid_tidak_menggagalkan_booking(): void
    {
        Mail::fake();

        $booking = $this->bookings->create([
            'room_unit_ids' => [$this->unit->id],
            'check_in' => now()->addDays(50)->toDateString(),
            'check_out' => now()->addDays(52)->toDateString(),
            'nama' => 'Tanpa Email',
            'email' => 'bukan-email',
            'hp' => '0812',
        ]);

        $invoice = $this->payments->createDpInvoice($booking);
        $this->payments->verify($invoice, null);

        // Booking tetap terkonfirmasi walau email gagal.
        $this->assertSame(Booking::STATUS_CONFIRMED, $booking->fresh()->status);
        Mail::assertNotQueued(\App\Mail\BookingStatusUpdate::class);
    }

    /** @test */
    public function nomor_whatsapp_dinormalisasi_ke_format_internasional(): void
    {
        $this->assertSame('628123456789', WhatsApp::normalize('08123456789'));
        $this->assertSame('628123456789', WhatsApp::normalize('+62 812-3456-789'));
        $this->assertSame('628123456789', WhatsApp::normalize('8123456789'));
        $this->assertSame('628123456789', WhatsApp::normalize('628123456789'));
        $this->assertNull(WhatsApp::normalize(null));
        $this->assertNull(WhatsApp::normalize('abc'));
    }

    /** @test */
    public function link_whatsapp_mengisi_pesan_otomatis(): void
    {
        $url = WhatsApp::link('08123456789', 'Halo Admin');

        $this->assertStringStartsWith('https://wa.me/628123456789?text=', $url);
        $this->assertStringContainsString('Halo%20Admin', $url);
    }

    /** @test */
    public function halaman_status_menampilkan_tombol_whatsapp(): void
    {
        $booking = $this->createBooking();
        $user = \App\Models\User::firstOrCreate(
            ['email' => 'notif-admin@uji.local'],
            ['name' => 'Admin', 'password' => bcrypt('x'), 'role' => 'admin']
        );

        $this->actingAs($user)
            ->get(route('booking.status', $booking->kode_booking))
            ->assertOk()
            ->assertSee('wa.me/6281298765432', false);
    }

    /** @test */
    public function perubahan_status_menulis_satu_baris_histori_per_transisi(): void
    {
        $booking = $this->createBooking();
        $invoice = $this->payments->createDpInvoice($booking);
        $this->payments->verify($invoice, null);
        $this->bookings->checkIn($booking->fresh(), null);

        $this->assertSame(3, $booking->histories()->count());
        $this->assertSame(
            Booking::STATUS_CHECKED_IN,
            $booking->histories()->first()->to_status
        );
    }
}