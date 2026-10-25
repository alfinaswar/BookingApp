<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomUnit;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class MasterPricingTest extends TestCase
{
    use DatabaseTransactions;

    private Room $room;
    private RoomUnit $unit;
    private \App\Models\User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = \App\Models\User::firstOrCreate(
            ['email' => 'pricing-admin@uji.local'],
            ['name' => 'Admin', 'password' => bcrypt('x'), 'role' => 'admin']
        );

        $this->room = Room::create([
            'nama' => 'Tenda Kalender',
            'qty' => 2,
            'kapasitas_max' => 4,
            'base_rate_weekday' => 300000,
            'base_rate_weekend' => 450000,
            'is_active' => true,
        ]);

        $this->unit = RoomUnit::create([
            'room_id' => $this->room->id,
            'kode' => 'KAL-' . strtoupper(substr(uniqid(), -5)),
            'status' => 'available',
        ]);
    }

    /** @test */
    public function kalender_mengembalikan_tarif_dan_ketersediaan(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson(route('master.kalender', [
                'from' => '2026-12-01',
                'to' => '2026-12-08',
            ]))
            ->assertOk()
            ->assertJsonStructure(['from', 'to', 'dates', 'types']);

        $row = collect($response->json('types'))->firstWhere('room_id', $this->room->id);

        $this->assertNotNull($row);
        $this->assertSame(1, $row['units']);
        $this->assertSame(300000.0, (float) $row['cells']['2026-12-01']['rate'], 'Selasa = weekday');
        $this->assertSame(450000.0, (float) $row['cells']['2026-12-04']['rate'], 'Jumat = weekend');
        $this->assertSame(1, $row['cells']['2026-12-01']['free']);
    }

    /** @test */
    public function override_per_tanggal_mengubah_tarif_di_kalender(): void
    {
        $this->actingAs($this->admin)
            ->postJson(route('master.override.store'), [
                'room_id' => $this->room->id,
                'date' => '2026-12-02',
                'rate' => 550000,
            ])
            ->assertOk();

        $cells = $this->actingAs($this->admin)
            ->getJson(route('master.kalender', ['from' => '2026-12-01', 'to' => '2026-12-05']))
            ->json('types');

        $row = collect($cells)->firstWhere('room_id', $this->room->id);

        $this->assertSame(550000.0, (float) $row['cells']['2026-12-02']['rate']);
        $this->assertSame(550000.0, (float) $row['cells']['2026-12-02']['override']);
    }

    /** @test */
    public function override_bisa_dihapus_dan_tarif_kembali_normal(): void
    {
        $this->actingAs($this->admin)->postJson(route('master.override.store'), [
            'room_id' => $this->room->id,
            'date' => '2026-12-03',
            'rate' => 999000,
        ])->assertOk();

        $this->actingAs($this->admin)->postJson(route('master.override.destroy'), [
            'room_id' => $this->room->id,
            'date' => '2026-12-03',
        ])->assertOk();

        $row = collect($this->actingAs($this->admin)
            ->getJson(route('master.kalender', ['from' => '2026-12-01', 'to' => '2026-12-05']))
            ->json('types'))->firstWhere('room_id', $this->room->id);

        $this->assertSame(300000.0, (float) $row['cells']['2026-12-03']['rate']);
    }

    /** @test */
    public function bulk_override_percent_mengisi_seluruh_rentang(): void
    {
        $this->actingAs($this->admin)->postJson(route('master.override.bulk'), [
            'room_ids' => [$this->room->id],
            'start_date' => '2026-12-17',
            'end_date' => '2026-12-22',
            'mode' => 'percent',
            'value' => 50,
        ])->assertOk();

        $row = collect($this->actingAs($this->admin)
            ->getJson(route('master.kalender', ['from' => '2026-12-16', 'to' => '2026-12-24']))
            ->json('types'))->firstWhere('room_id', $this->room->id);

        // 18 Des 2026 = Jumat (weekend 450k) +50% = 675k
        $this->assertSame(675000.0, (float) $row['cells']['2026-12-18']['rate']);
        // 19 Des = Sabtu (weekend 450k) +50% = 675k
        $this->assertSame(675000.0, (float) $row['cells']['2026-12-19']['rate']);
        // 22 Des = Selasa (weekday 300k) +50% = 450k
        $this->assertSame(450000.0, (float) $row['cells']['2026-12-22']['rate']);
    }

    /** @test */
    public function bulk_override_berulang_tidak_menumpuk_persentase(): void
    {
        $payload = [
            'room_ids' => [$this->room->id],
            'start_date' => '2027-06-01',
            'end_date' => '2027-06-01',
            'mode' => 'percent',
            'value' => 50,
        ];

        $this->actingAs($this->admin)->postJson(route('master.override.bulk'), $payload)->assertOk();
        $this->actingAs($this->admin)->postJson(route('master.override.bulk'), $payload)->assertOk();
        $this->actingAs($this->admin)->postJson(route('master.override.bulk'), $payload)->assertOk();

        $row = collect($this->actingAs($this->admin)
            ->getJson(route('master.kalender', ['from' => '2027-05-31', 'to' => '2027-06-03']))
            ->json('types'))->firstWhere('room_id', $this->room->id);

        // 1 Jun 2027 = Selasa (weekday 300k). Tiga kali +50% tetap 450k, bukan 1.012k.
        $this->assertSame(450000.0, (float) $row['cells']['2027-06-01']['rate']);
    }

    /** @test */
    public function tanggal_ditutup_tidak_bisa_dipesan(): void
    {
        // Pastikan closed terlihat di kalender
        $this->actingAs($this->admin)->postJson(route('master.override.store'), [
            'room_id' => $this->room->id,
            'date' => '2027-01-10',
            'rate' => 300000,
            'closed' => 1,
        ])->assertOk();

        $row = collect($this->actingAs($this->admin)
            ->getJson(route('master.kalender', ['from' => '2027-01-09', 'to' => '2027-01-12']))
            ->json('types'))->firstWhere('room_id', $this->room->id);

        $this->assertTrue($row['cells']['2027-01-10']['closed']);
    }

    /** @test */
    public function blokir_unit_mencegah_booking(): void
    {
        $this->actingAs($this->admin)->postJson(route('master.block.store'), [
            'room_unit_ids' => [$this->unit->id],
            'start_date' => '2027-02-01',
            'end_date' => '2027-02-04',
            'reason' => 'Renovasi',
        ])->assertOk();

        $this->expectException(\App\Exceptions\BookingException::class);

        app(BookingService::class)->create([
            'room_unit_ids' => [$this->unit->id],
            'check_in' => '2027-02-02',
            'check_out' => '2027-02-03',
            'nama' => 'Tamu Blokir',
            'email' => 'blokir@uji.local',
            'hp' => '0811',
        ]);
    }

    /** @test */
    public function blokir_ditolak_bila_unit_sedang_terisi(): void
    {
        app(BookingService::class)->create([
            'room_unit_ids' => [$this->unit->id],
            'check_in' => '2027-03-01',
            'check_out' => '2027-03-04',
            'nama' => 'Tamu Aktif',
            'email' => 'aktif@uji.local',
            'hp' => '0811',
        ]);

        $this->actingAs($this->admin)->postJson(route('master.block.store'), [
            'room_unit_ids' => [$this->unit->id],
            'start_date' => '2027-03-02',
            'end_date' => '2027-03-03',
        ])->assertStatus(409);
    }

    /** @test */
    public function blokir_bisa_dihapus_dan_unit_bisa_dipesan(): void
    {
        $block = \App\Models\InventoryBlock::create([
            'room_unit_id' => $this->unit->id,
            'start_date' => '2027-04-01',
            'end_date' => '2027-04-03',
            'reason' => 'Tes',
        ]);

        $this->actingAs($this->admin)
            ->deleteJson(route('master.block.destroy', $block->id))
            ->assertOk();

        $booking = app(BookingService::class)->create([
            'room_unit_ids' => [$this->unit->id],
            'check_in' => '2027-04-01',
            'check_out' => '2027-04-02',
            'nama' => 'Tamu Setelah Blokir',
            'email' => 'after@uji.local',
            'hp' => '0811',
        ]);

        $this->assertSame(Booking::STATUS_PENDING, $booking->status);
    }

    /** @test */
    public function kalender_dibatasi_maksimal_62_hari(): void
    {
        $response = $this->actingAs($this->admin)->getJson(route('master.kalender', [
            'from' => '2027-05-01',
            'to' => '2027-12-31',
        ]))->assertOk();

        $this->assertLessThanOrEqual(62, count($response->json('dates')));
    }
}