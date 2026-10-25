<?php

namespace App\Http\Controllers;

use App\Models\SiteSetting;
use App\Support\Brand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SystemSettingController extends Controller
{
    /**
     * File branding yang boleh diunggah: kolom => [label, dimensi maks].
     */
    private const UPLOADS = [
        'logo' => 'Logo Utama',
        'logo_light' => 'Logo Versi Terang',
        'favicon' => 'Favicon',
        'login_image' => 'Gambar Halaman Login',
    ];

    public function index()
    {
        $setting = SiteSetting::current();

        return view('Pengaturan.index', [
            'setting' => $setting,
            'uploads' => self::UPLOADS,
        ]);
    }

    /**
     * Simpan identitas, kontak, warna, dan preferensi tampilan.
     */
    public function update(Request $request)
    {
        $data = $request->validate([
            'app_name' => ['required', 'string', 'max:100'],
            'company_name' => ['required', 'string', 'max:100'],
            'tagline' => ['nullable', 'string', 'max:150'],
            'business_type' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'business_license' => ['nullable', 'string', 'max:100'],
            'legal_name' => ['nullable', 'string', 'max:150'],

            'email' => ['nullable', 'email', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'whatsapp' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]*$/'],
            'website' => ['nullable', 'url', 'max:150'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'country' => ['nullable', 'string', 'max:100'],

            'instagram' => ['nullable', 'string', 'max:150'],
            'facebook' => ['nullable', 'string', 'max:150'],
            'tiktok' => ['nullable', 'string', 'max:150'],
            'youtube' => ['nullable', 'string', 'max:150'],

            'color_primary' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'color_secondary' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'color_accent' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],

            'show_company_name' => ['nullable', 'boolean'],
            'footer_text' => ['nullable', 'string', 'max:255'],
        ], [
            'color_primary.regex' => 'Warna harus format heksadesimal, contoh #1F573A.',
            'color_secondary.regex' => 'Warna harus format heksadesimal, contoh #2FB344.',
            'color_accent.regex' => 'Warna harus format heksadesimal, contoh #F59F00.',
            'website.url' => 'Alamat website harus diawali http:// atau https://.',
        ]);

        $setting = SiteSetting::current();

        if (! $setting->exists) {
            $setting = new SiteSetting();
        }

        $data['show_company_name'] = $request->boolean('show_company_name');
        $data['updated_by'] = auth()->id();

        $setting->fill($data)->save();

        return response()->json([
            'message' => 'Pengaturan sistem tersimpan.',
            'brand' => [
                'name' => Brand::name(),
                'logo' => Brand::logo(),
                'primary' => Brand::primary(),
            ],
        ]);
    }

    /**
     * Unggah satu file branding. Hapus file lama agar storage tidak menumpuk.
     */
    public function upload(Request $request, string $type)
    {
        if (! array_key_exists($type, self::UPLOADS)) {
            return response()->json(['message' => 'Jenis berkas tidak dikenal.'], 404);
        }

        $isFavicon = $type === 'favicon';

        $request->validate([
            'file' => [
                'required',
                'file',
                // Favicon boleh ICO; sisanya gambar raster.
                $isFavicon ? 'mimes:png,jpg,jpeg,ico,svg' : 'mimes:png,jpg,jpeg,svg,webp',
                'max:2048',
            ],
        ], [
            'file.max' => 'Ukuran berkas maksimal 2 MB.',
            'file.mimes' => $isFavicon
                ? 'Format harus PNG, JPG, ICO, atau SVG.'
                : 'Format harus PNG, JPG, WEBP, atau SVG.',
        ]);

        $setting = SiteSetting::current();
        $file = $request->file('file');

        // Nama acak: mencegah tabrakan dan menutup celah path traversal.
        $path = $file->store('branding', 'public');

        if ($setting->exists) {
            $this->deleteFile($setting->{$type});
            $setting->fill([$type => $path, 'updated_by' => auth()->id()])->save();
        } else {
            SiteSetting::create([$type => $path, 'updated_by' => auth()->id()]);
        }

        Brand::settings()->refresh();

        return response()->json([
            'message' => self::UPLOADS[$type] . ' berhasil diunggah.',
            'url' => Storage::disk('public')->url($path),
        ]);
    }

    /**
     * Hapus satu file branding dan kembali ke aset bawaan.
     */
    public function destroyFile(string $type)
    {
        if (! array_key_exists($type, self::UPLOADS)) {
            return response()->json(['message' => 'Jenis berkas tidak dikenal.'], 404);
        }

        $setting = SiteSetting::current();

        if (! $setting->exists) {
            return response()->json(['message' => 'Belum ada berkas.'], 404);
        }

        $this->deleteFile($setting->{$type});
        $setting->fill([$type => null, 'updated_by' => auth()->id()])->save();

        return response()->json([
            'message' => self::UPLOADS[$type] . ' dihapus. Kembali ke aset bawaan.',
            'url' => Brand::logo(),
        ]);
    }

    /**
     * Kembalikan seluruh pengaturan ke default pabrik.
     */
    public function reset()
    {
        $setting = SiteSetting::current();

        if ($setting->exists) {
            foreach (array_keys(self::UPLOADS) as $column) {
                $this->deleteFile($setting->{$column});
            }

            $setting->delete();
        }

        Brand::flush();

        return response()->json(['message' => 'Pengaturan dikembalikan ke bawaan.', 'brand' => [
            'name' => Brand::name(),
            'logo' => Brand::logo(),
            'primary' => Brand::primary(),
        ]]);
    }

    private function deleteFile(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}