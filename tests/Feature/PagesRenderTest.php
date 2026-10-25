<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PagesRenderTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(): User
    {
        return User::firstOrCreate(
            ['email' => 'smoke-admin@uji.local'],
            ['name' => 'Smoke Admin', 'password' => bcrypt('secret'), 'role' => 'admin']
        );
    }

    /** @test */
    public function halaman_booking_merender_matriks(): void
    {
        $this->actingAs($this->admin())
            ->get(route('booking.index'))
            ->assertOk()
            ->assertSee('Ketersediaan Real-time', false)
            ->assertSee('Ringkasan Pesanan', false);
    }

    /** @test */
    public function endpoint_kalender_mengembalikan_struktur(): void
    {
        $this->actingAs($this->admin())
            ->getJson(route('booking.kalender', [
                'from' => now()->toDateString(),
                'to' => now()->addDays(5)->toDateString(),
            ]))
            ->assertOk()
            ->assertJsonStructure(['from', 'to', 'dates', 'types']);
    }

    /** @test */
    public function endpoint_quote_menghitung_harga(): void
    {
        $unit = RoomUnit::firstOrFail();

        $this->actingAs($this->admin())
            ->postJson(route('booking.quote'), [
                'room_unit_ids' => [$unit->id],
                'check_in' => now()->addDays(30)->toDateString(),
                'check_out' => now()->addDays(32)->toDateString(),
                'dewasa' => 2,
            ])
            ->assertOk()
            ->assertJsonStructure(['malam', 'units', 'subtotal', 'grand_total', 'dp_amount', 'conflicts']);
    }

    /** @test */
    public function halaman_list_booking_merender(): void
    {
        $this->actingAs($this->admin())
            ->get(route('booking.listBooking'))
            ->assertOk()
            ->assertSee('List Booking', false)
            ->assertSee('Menunggu Verifikasi', false);
    }

    /** @test */
    public function tombol_aksi_list_booking_sesuai_status_dan_class_js(): void
    {
        $user = $this->admin();
        $unit = RoomUnit::firstOrFail();

        $booking = app(\App\Services\BookingService::class)->create([
            'room_unit_ids' => [$unit->id],
            'check_in' => now()->addDays(90)->toDateString(),
            'check_out' => now()->addDays(91)->toDateString(),
            'nama' => 'Aksi Test',
            'email' => 'aksi@uji.local',
            'hp' => '0811',
        ], $user);

        $json = $this->actingAs($user)
            ->getJson(route('booking.listBooking'), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->json();

        $row = collect($json['data'])->firstWhere('kode', '<span class="fw-bold">' . $booking->kode_booking . '</span><br><small class="text-muted">' . $booking->created_at->format('d/m/Y H:i') . '</small>');

        $this->assertNotNull($row, 'Baris booking tidak ditemukan di DataTables.');
        $this->assertStringContainsString('btn-batal', $row['action'], 'Tombol batal harus memakai class yang dikenali JS.');

        // DP terbayar + bukti menunggu verifikasi => tombol verifikasi muncul.
        $invoice = app(\App\Services\PaymentService::class)->createDpInvoice($booking);
        $invoice->update(['status' => 'waiting_verification']);

        $json2 = $this->actingAs($user)
            ->getJson(route('booking.listBooking'), ['X-Requested-With' => 'XMLHttpRequest'])
            ->json();

        $row2 = collect($json2['data'])->first(fn ($r) => str_contains($r['kode'], $booking->kode_booking));

        $this->assertStringContainsString('btn-verifikasi', $row2['action']);
        $this->assertStringContainsString('data-payment-id="' . $invoice->id . '"', $row2['action']);
    }

    /** @test */
    public function datatable_list_booking_mengembalikan_json(): void
    {
        $this->actingAs($this->admin())
            ->getJson(route('booking.listBooking'), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertJsonStructure(['data', 'draw', 'recordsTotal']);
    }

    /** @test */
    public function halaman_status_booking_merender(): void
    {
        $booking = Booking::whereNotNull('kode_booking')->firstOrFail();

        $this->actingAs($this->admin())
            ->get(route('booking.status', $booking->kode_booking))
            ->assertOk()
            ->assertSee($booking->kode_booking);
    }

    /** @test */
    public function halaman_laporan_merender(): void
    {
        $this->actingAs($this->admin())
            ->get(route('laporan.index'))
            ->assertOk()
            ->assertSee('Laporan Operasional', false);
    }

    /** @test */
    public function endpoint_laporan_mengembalikan_data(): void
    {
        $params = ['from' => now()->startOfMonth()->toDateString(), 'to' => now()->toDateString()];

        $this->actingAs($this->admin())->getJson(route('laporan.okupansi', $params))->assertOk()
            ->assertJsonStructure(['occupancy', 'adr', 'revpar', 'per_day']);

        $this->actingAs($this->admin())->getJson(route('laporan.pendapatan', $params))->assertOk()
            ->assertJsonStructure(['accrual', 'cash', 'accrual_total', 'cash_total']);

        $this->actingAs($this->admin())->getJson(route('laporan.tamu', $params))->assertOk()
            ->assertJsonStructure(['total_tamu', 'rows']);

        $this->actingAs($this->admin())->getJson(route('laporan.kpi'))->assertOk()
            ->assertJsonStructure(['arrival', 'inhouse', 'departure', 'pending_payment']);
    }

    /** @test */
    public function halaman_master_merender(): void
    {
        $this->actingAs($this->admin())
            ->get(route('master.index'))
            ->assertOk()
            ->assertSee('Tarif, Unit', false)
            ->assertSee('Season', false);
    }

    /** @test */
    public function webhook_midtrans_balas_503_tanpa_kredensial(): void
    {
        config(['midtrans.enabled' => false]);

        $this->postJson(route('webhook.midtrans'), ['order_id' => 'X'])
            ->assertStatus(503);
    }

    /** @test */
    public function snap_token_balas_503_tanpa_kredensial(): void
    {
        config(['midtrans.enabled' => false, 'midtrans.server_key' => '', 'midtrans.client_key' => '']);

        // Payment harus valid: rule exists:payments,id jalan sebelum cek config.
        // Assertion-nya tetap 503 karena validasi Midtrans didahulukan.
        $booking = Booking::whereNotNull('kode_booking')->firstOrFail();

        $payment = \App\Models\Payment::create([
            'booking_id' => $booking->id,
            'kode_pembayaran' => 'SMOKE-' . uniqid(),
            'metode' => 'transfer_manual',
            'tipe' => 'dp',
            'amount' => 1000,
            'status' => 'pending',
        ]);

        $this->actingAs($this->admin())
            ->postJson(route('payment.snap'), ['payment_id' => $payment->id])
            ->assertStatus(503);
    }

    /** @test */
    public function halaman_pembayaran_publik_tidak_bentrok_dengan_route_statis(): void
    {
        $booking = Booking::whereNotNull('kode_booking')->firstOrFail();

        // Route publik (tanpa login) — wildcard {kode} tidak boleh menelan
        // route statis seperti payment/return atau payment/snap-token.
        $response = $this->get(route('payment.pay', $booking->kode_booking))
            ->assertOk();

        $this->assertStringContainsString($booking->kode_booking, $response->getContent());
    }
}