<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Models\User;
use App\Support\Brand;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Jaring agar halaman publik tidak lagi memakai identitas hardcoded.
 */
class BrandPagesTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::firstOrCreate(
            ['email' => 'brand-admin@uji.local'],
            ['name' => 'Admin', 'password' => bcrypt('x'), 'role' => 'admin']
        );
    }

    private function applyBrand(): void
    {
        $setting = SiteSetting::current();
        $setting = $setting->exists ? $setting : new SiteSetting();

        $setting->fill([
            'app_name' => 'Basecamp Uji',
            'company_name' => 'Basecamp Uji Resort',
            'tagline' => 'Tagline Uji',
            'color_primary' => '#8B0000',
            'color_secondary' => '#FFA500',
            'color_accent' => '#00CED1',
            'show_company_name' => true,
            'footer_text' => 'Footer Uji Brand',
            'whatsapp' => '081298765432',
        ])->save();

        Brand::flush();
    }

    /** @test */
    public function halaman_public_memakai_warna_brand_dari_database(): void
    {
        $this->applyBrand();

        // Tamu, bukan admin — /login akan redirect bila sudah login.
        auth()->logout();

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('--brand-primary:#8B0000', false)
            ->assertSee('Basecamp Uji', false)
            ->assertSee('btn-brand', false);
    }

    /** @test */
    public function login_tidak_lagi_memakai_brand_hardcoded(): void
    {
        $html = $this->get(route('login'))->assertOk()->getContent();

        $this->assertStringNotContainsString('Basecamp Military Lifestyle', $html);
        $this->assertStringNotContainsString(
            '<title>Sign in - Tabler',
            $html,
            'Judul halaman login tidak boleh memakai judul template.'
        );
    }

    /** @test */
    public function layout_booking_memakai_footer_dan_warna_brand(): void
    {
        $this->applyBrand();

        $this->actingAs($this->admin)
            ->get(route('booking.index'))
            ->assertOk()
            ->assertSee('--brand-primary:#8B0000', false)
            ->assertSee('Footer Uji Brand', false)
            ->assertSee('Basecamp Uji Resort', false);
    }

    /** @test */
    public function nama_perusahaan_hilang_dari_header_bila_dimatikan(): void
    {
        $this->applyBrand();

        $setting = SiteSetting::current();
        $setting->update(['show_company_name' => false]);
        Brand::flush();

        $this->actingAs($this->admin)
            ->get(route('booking.index'))
            ->assertOk()
            ->assertDontSee('Basecamp Uji Resort', false);
    }

    /** @test */
    public function logo_unggahan_terpakai_di_header_dan_login(): void
    {
        Storage::fake('public');

        $this->applyBrand();

        $this->actingAs($this->admin)->postJson(route('setting.system.upload', 'logo'), [
            'file' => UploadedFile::fake()->image('logo.png', 400, 120),
        ])->assertOk();

        Brand::flush();
        $logo = Brand::logo();

        $this->assertStringContainsString('/storage/branding/', $logo);

        $this->actingAs($this->admin)
            ->get(route('booking.index'))
            ->assertOk()
            ->assertSee($logo, false);

        auth()->logout();

        $this->get(route('login'))
            ->assertOk()
            ->assertSee($logo, false);
    }

    /** @test */
    public function favicon_unggahan_terpakai_di_login(): void
    {
        Storage::fake('public');

        $this->applyBrand();

        $this->actingAs($this->admin)->postJson(route('setting.system.upload', 'favicon'), [
            'file' => UploadedFile::fake()->image('favicon.png', 32, 32),
        ])->assertOk();

        Brand::flush();
        $favicon = Brand::favicon();

        auth()->logout();

        $this->get(route('login'))
            ->assertOk()
            ->assertSee($favicon, false);
    }

    /** @test */
    public function halaman_landing_memakai_identitas_brand(): void
    {
        $this->applyBrand();
        auth()->logout();

        $this->get(route('welcome.landing'))
            ->assertOk()
            ->assertSee('--brand-primary:#8B0000', false)
            ->assertSee('Basecamp Uji Resort', false)
            ->assertSee('Tagline Uji', false)
            ->assertSee('Footer Uji Brand', false)
            ->assertSee('Pesan Sekarang', false);
    }

    /** @test */
    public function landing_menampilkan_tombol_dashboard_untuk_admin(): void
    {
        $this->actingAs($this->admin)
            ->get(route('welcome.landing'))
            ->assertOk()
            ->assertSee('Buka Dashboard', false);
    }
}