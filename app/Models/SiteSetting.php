<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Satu baris identitas sistem. Di-cache karena dipakai di setiap request layout.
 */
class SiteSetting extends Model
{
    use HasFactory;

    public const CACHE_KEY = 'site.settings';

    protected $table = 'site_settings';

    protected $fillable = [
        'app_name', 'company_name', 'tagline', 'business_type', 'description',
        'business_license', 'legal_name',
        'email', 'phone', 'whatsapp', 'website',
        'address', 'city', 'province', 'postal_code', 'country',
        'instagram', 'facebook', 'tiktok', 'youtube',
        'logo', 'logo_light', 'favicon', 'login_image',
        'color_primary', 'color_secondary', 'color_accent',
        'show_company_name', 'footer_text', 'updated_by',
    ];

    protected $casts = [
        'show_company_name' => 'boolean',
    ];

    public const DEFAULTS = [
        'app_name' => 'Basecamp Military Lifestyle',
        'company_name' => 'Basecamp Military Lifestyle',
        'tagline' => 'Penginapan & Outdoor Experience',
        'business_type' => 'Penginapan / Camp',
        'color_primary' => '#1F573A',
        'color_secondary' => '#2FB344',
        'color_accent' => '#F59F00',
        'country' => 'Indonesia',
    ];

    /**
     * Instans tunggal (singleton). Dibuat lazily bila belum ada di database.
     * Nilai default tetap terbaca lewat val() walau baris belum disimpan.
     */
    public static function current(): self
    {
        if (app()->bound('site.settings.instance')) {
            return app('site.settings.instance');
        }

        $setting = Cache::rememberForever(self::CACHE_KEY, function () {
            // Jangan simpan objek Model ke cache: pakai array id, lalu ambil ulang.
            return optional(self::query()->first())->id;
        });

        $instance = $setting
            ? (self::query()->find($setting) ?? new self())
            : new self();

        app()->instance('site.settings.instance', $instance);

        return $instance;
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        app()->forgetInstance('site.settings.instance');
    }

    /**
     * Nilai dengan fallback ke default.
     */
    public function val(string $key, mixed $default = null): mixed
    {
        $value = $this->{$key} ?? null;

        if ($value === null || $value === '') {
            return $default ?? self::DEFAULTS[$key] ?? null;
        }

        return $value;
    }

    /**
     * URL publik untuk file branding, atau null bila belum diunggah.
     */
    public function fileUrl(?string $column): ?string
    {
        $path = $this->{$column} ?? null;

        if (! $path) {
            return null;
        }

        return Storage::disk('public')->exists($path)
            ? Storage::disk('public')->url($path)
            : null;
    }

    protected static function booted(): void
    {
        static::saved(fn () => self::flush());
        static::deleted(fn () => self::flush());
    }
}