<?php

namespace Tests\Unit;

use App\Models\Addon;
use App\Models\RateOverride;
use App\Models\Room;
use App\Models\Setting;
use App\Services\PricingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PricingServiceTest extends TestCase
{
    use DatabaseTransactions;

    private PricingService $pricing;
    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pricing = app(PricingService::class);
        $this->room = Room::create([
            'nama' => 'Tenda Uji',
            'deskripsi' => 'unit test',
            'qty' => 1,
            'base_rate_weekday' => 300000,
            'base_rate_weekend' => 450000,
        ]);
    }

    /** @test */
    public function weekend_rate_dipakai_berdasarkan_tanggal_inap_bukan_hari_ini(): void
    {
        // Jumat 2026-11-06 adalah weekend, Senin 2026-11-09 adalah weekday.
        $rate = $this->pricing->nightlyRate($this->room, '2026-11-06');

        $this->assertSame(450000.0, $rate, 'Malam Jumat harus memakai tarif weekend');
        $this->assertSame(300000.0, $this->pricing->nightlyRate($this->room, '2026-11-09'));
    }

    /** @test */
    public function quote_menjumlah_tarif_per_malam_campuran_weekday_dan_weekend(): void
    {
        // Kamis 2026-11-05 (weekday), Jumat 2026-11-06 (weekend), Sabtu 2026-11-07 (weekend)
        $quote = $this->pricing->unitQuote($this->room, '2026-11-05', '2026-11-08');

        $this->assertSame(3, $quote['malam']);
        $this->assertSame(
            ['2026-11-05' => 300000.0, '2026-11-06' => 450000.0, '2026-11-07' => 450000.0],
            $quote['per_night']
        );
        $this->assertSame(1200000.0, $quote['subtotal']);
    }

    /** @test */
    public function check_out_tidak_dihitung_sebagai_malam(): void
    {
        $quote = $this->pricing->unitQuote($this->room, '2026-11-05', '2026-11-06');

        $this->assertSame(1, $quote['malam']);
        $this->assertArrayNotHasKey('2026-11-06', $quote['per_night']);
    }

    /** @test */
    public function rate_override_menang_atas_tarif_dasar(): void
    {
        RateOverride::create([
            'room_id' => $this->room->id,
            'date' => '2026-11-06',
            'rate' => 999000,
        ]);

        $this->assertSame(999000.0, $this->pricing->nightlyRate($this->room, '2026-11-06'));
    }

    /** @test */
    public function season_percent_mengalikan_tarif_dasar(): void
    {
        \App\Models\Season::create([
            'nama' => 'High Season Nataru',
            'start_date' => '2026-12-20',
            'end_date' => '2027-01-05',
            'priority' => 10,
            'adjustment_type' => 'percent',
            'adjustment_value' => 50,
            'is_active' => true,
        ]);

        $this->assertSame(450000.0, $this->pricing->nightlyRate($this->room, '2026-12-22'));
    }

    /** @test */
    public function addon_dihitung_sesuai_satuan(): void
    {
        $perNight = new Addon(['harga' => 100000, 'satuan' => Addon::UNIT_PER_NIGHT]);
        $perPerson = new Addon(['harga' => 50000, 'satuan' => Addon::UNIT_PER_PERSON]);
        $perPersonNight = new Addon(['harga' => 25000, 'satuan' => Addon::UNIT_PER_PERSON_NIGHT]);
        $perBooking = new Addon(['harga' => 200000, 'satuan' => Addon::UNIT_PER_BOOKING]);

        $this->assertSame(300000.0, $perNight->priceFor(3, 2));
        $this->assertSame(100000.0, $perPerson->priceFor(3, 2));
        $this->assertSame(150000.0, $perPersonNight->priceFor(3, 2));
        $this->assertSame(200000.0, $perBooking->priceFor(3, 2));
    }

    /** @test */
    public function dp_dihitung_dari_persentase_setting(): void
    {
        Setting::put('dp_percent', 30);
        Setting::put('tax_rate', 0);
        Setting::put('service_rate', 0);

        $unit = \App\Models\RoomUnit::create([
            'room_id' => $this->room->id,
            'kode' => 'TEST-01',
            'status' => 'available',
        ]);

        $quote = $this->pricing->quoteForUnits([$unit->id], '2026-11-05', '2026-11-08');

        $this->assertSame(1200000.0, $quote['grand_total']);
        $this->assertSame(360000.0, $quote['dp_amount']);
    }

    /** @test */
    public function total_tidak_bisa_negatif_karena_diskon_melebihi_subtotal(): void
    {
        $promo = new \App\Models\Promo([
            'tipe' => 'fixed',
            'nilai' => 99999999,
            'min_subtotal' => 0,
            'is_active' => true,
        ]);

        $this->assertSame(1200000.0, $promo->discountFor(1200000.0));
    }
}