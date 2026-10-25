<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Models\User;
use App\Support\Brand;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SystemSettingTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::firstOrCreate(
            ['email' => 'sys-admin@uji.local'],
            ['name' => 'Admin', 'password' => bcrypt('x'), 'role' => 'admin']
        );

        Brand::flush();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'app_name' => 'Basecamp Test',
            'company_name' => 'PT Basecamp Test',
            'tagline' => 'Camp & Outdoor',
            'business_type' => 'Penginapan',
            'description' => 'Deskripsi uji',
            'email' => 'info@basecamp.test',
            'phone' => '021123456',
            'whatsapp' => '081234567890',
            'website' => 'https://basecamp.test',
            'address' => 'Jl. Uji No. 1',
            'city' => 'Bandung',
            'province' => 'Jawa Barat',
            'postal_code' => '40111',
            'country' => 'Indonesia',
            'color_primary' => '#123456',
            'color_secondary' => '#654321',
            'color_accent' => '#ABCDEF',
            'show_company_name' => '1',
            'footer_text' => 'Footer uji',
        ], $overrides);
    }

    /** @test */
    public function halaman_pengaturan_sistem_merender(): void
    {
        $this->actingAs($this->admin)
            ->get(route('setting.system.index'))
            ->assertOk()
            ->assertSee('Pengaturan Sistem', false)
            ->assertSee('Identitas Sistem', false)
            ->assertSee('Warna Tema', false);
    }

    /** @test */
    public function halaman_mewajibkan_login(): void
    {
        $this->get(route('setting.system.index'))->assertRedirect(route('login'));
    }

    /** @test */
    public function identitas_tersimpan_dan_langsung_dipakai_brand(): void
    {
        $this->actingAs($this->admin)
            ->postJson(route('setting.system.update'), $this->payload())
            ->assertOk()
            ->assertJsonPath('brand.name', 'Basecamp Test');

        Brand::flush();

        $this->assertSame('Basecamp Test', Brand::name());
        $this->assertSame('PT Basecamp Test', Brand::company());
        $this->assertSame('#123456', Brand::primary());
        $this->assertSame('081234567890', Brand::whatsapp());
        $this->assertSame('Footer uji', Brand::footer());
    }

    /** @test */
    public function nama_dan_logo_muncul_di_layout_booking(): void
    {
        $this->actingAs($this->admin)->postJson(route('setting.system.update'), $this->payload())->assertOk();
        Brand::flush();

        $this->actingAs($this->admin)
            ->get(route('booking.index'))
            ->assertOk()
            ->assertSee('Basecamp Test', false)
            ->assertSee('--brand-primary:#123456', false)
            ->assertSee('Footer uji', false);
    }

    /** @test */
    public function warna_harus_format_heksadesimal(): void
    {
        $this->actingAs($this->admin)
            ->postJson(route('setting.system.update'), $this->payload(['color_primary' => 'hijau']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('color_primary');
    }

    /** @test */
    public function website_harus_url_valid(): void
    {
        $this->actingAs($this->admin)
            ->postJson(route('setting.system.update'), $this->payload(['website' => 'basecamp.test']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('website');
    }

    /** @test */
    public function upload_logo_mengganti_logo_bawaan(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin)->postJson(route('setting.system.update'), $this->payload())->assertOk();
        Brand::flush();

        $this->actingAs($this->admin)
            ->postJson(route('setting.system.upload', 'logo'), [
                'file' => UploadedFile::fake()->image('logo.png', 300, 100),
            ])
            ->assertOk();

        Brand::flush();
        $setting = SiteSetting::current();

        $this->assertNotNull($setting->logo);
        Storage::disk('public')->assertExists($setting->logo);
        $this->assertStringContainsString('/storage/branding/', Brand::logo());
    }

    /** @test */
    public function upload_ulang_logo_menghapus_file_lama(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin)->postJson(route('setting.system.upload', 'logo'), [
            'file' => UploadedFile::fake()->image('pertama.png', 200, 80),
        ])->assertOk();

        Brand::flush();
        $pertama = SiteSetting::current()->logo;

        $this->actingAs($this->admin)->postJson(route('setting.system.upload', 'logo'), [
            'file' => UploadedFile::fake()->image('kedua.png', 200, 80),
        ])->assertOk();

        Brand::flush();
        $kedua = SiteSetting::current()->logo;

        $this->assertNotSame($pertama, $kedua, 'File lama harus diganti, bukan ditumpuk.');
        Storage::disk('public')->assertMissing($pertama);
        Storage::disk('public')->assertExists($kedua);
    }

    /** @test */
    public function upload_menolak_berkas_bukan_gambar(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin)
            ->postJson(route('setting.system.upload', 'logo'), [
                'file' => UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf'),
            ])
            ->assertStatus(422);
    }

    /** @test */
    public function upload_menolak_berkas_terlalu_besar(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin)
            ->postJson(route('setting.system.upload', 'logo'), [
                'file' => UploadedFile::fake()->image('besar.png')->size(3000),
            ])
            ->assertStatus(422);
    }

    /** @test */
    public function upload_jenis_tidak_dikenal_ditolak(): void
    {
        $this->actingAs($this->admin)
            ->postJson(route('setting.system.upload', 'script-php'), [
                'file' => UploadedFile::fake()->image('x.png'),
            ])
            ->assertStatus(404);
    }

    /** @test */
    public function hapus_file_kembali_ke_aset_bawaan(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin)->postJson(route('setting.system.upload', 'logo'), [
            'file' => UploadedFile::fake()->image('logo.png'),
        ])->assertOk();

        Brand::flush();
        $path = SiteSetting::current()->logo;

        $this->actingAs($this->admin)
            ->postJson(route('setting.system.destroy-file', 'logo'))
            ->assertOk();

        Brand::flush();
        $this->assertNull(SiteSetting::current()->logo);
        Storage::disk('public')->assertMissing($path);
        $this->assertStringContainsString('assets/img/icon/basecamp.png', Brand::logo());
    }

    /** @test */
    public function fallback_branding_aman_saat_belum_ada_data(): void
    {
        SiteSetting::query()->delete();
        Brand::flush();

        $this->assertSame('Basecamp Military Lifestyle', Brand::name());
        $this->assertSame('#1F573A', Brand::primary());
        $this->assertStringContainsString('basecamp.png', Brand::logo());
        $this->assertNotEmpty(Brand::footer());
    }

    /** @test */
    public function reset_mengembalikan_semua_ke_bawaan(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin)->postJson(route('setting.system.update'), $this->payload())->assertOk();
        $this->actingAs($this->admin)->postJson(route('setting.system.upload', 'logo'), [
            'file' => UploadedFile::fake()->image('logo.png'),
        ])->assertOk();

        Brand::flush();
        $path = SiteSetting::current()->logo;

        $this->actingAs($this->admin)->postJson(route('setting.system.reset'))->assertOk();

        Brand::flush();
        $this->assertSame('Basecamp Military Lifestyle', Brand::name());
        $this->assertStringContainsString('basecamp.png', Brand::logo());
        Storage::disk('public')->assertMissing($path);
    }

    /** @test */
    public function logo_kustom_muncul_di_halaman_login(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin)->postJson(route('setting.system.upload', 'logo'), [
            'file' => UploadedFile::fake()->image('logo.png', 300, 100),
        ])->assertOk();

        Brand::flush();
        $logoUrl = Brand::logo();

        // Tamu (belum login) — admin yang login akan dialihkan dari /login.
        auth()->logout();

        $this->get(route('login'))
            ->assertOk()
            ->assertSee($logoUrl, false);
    }

    /** @test */
    public function favicon_kustom_muncul_di_layout(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin)->postJson(route('setting.system.upload', 'favicon'), [
            'file' => UploadedFile::fake()->image('favicon.png', 32, 32),
        ])->assertOk();

        Brand::flush();

        $this->actingAs($this->admin)
            ->get(route('booking.index'))
            ->assertOk()
            ->assertSee('rel="icon"', false);
    }

    /** @test */
    public function kontak_whatsapp_membentuk_deep_link(): void
    {
        $this->actingAs($this->admin)->postJson(route('setting.system.update'), $this->payload())->assertOk();
        Brand::flush();

        $this->assertStringContainsString('wa.me/6281234567890', (string) Brand::whatsappUrl());
    }
}