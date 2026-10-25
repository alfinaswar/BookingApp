<?php

namespace App\Support;

use App\Models\SiteSetting;

/**
 * Pintu tunggal untuk identitas & branding di seluruh view.
 *
 *   <img src="{{ Brand::logo() }}">
 *   <title>{{ Brand::name() }}</title>
 *   <style>:root { {!! Brand::cssVariables() !!} }</style>
 */
class Brand
{
    public static function settings(): SiteSetting
    {
        return SiteSetting::current();
    }

    /**
     * Buang cache identitas. Dipanggil setelah simpan/unggah, dan di test.
     */
    public static function flush(): void
    {
        SiteSetting::flush();
    }

    public static function name(): string
    {
        return (string) self::settings()->val('app_name');
    }

    public static function company(): string
    {
        return (string) self::settings()->val('company_name');
    }

    public static function tagline(): ?string
    {
        return self::settings()->val('tagline');
    }

    public static function showCompanyName(): bool
    {
        return (bool) self::settings()->val('show_company_name', true);
    }

    /**
     * Logo utama. Fallback ke aset bawaan supaya layout tidak pecah.
     */
    public static function logo(): string
    {
        return self::settings()->fileUrl('logo')
            ?? asset('assets/img/icon/basecamp.png');
    }

    /**
     * Logo versi terang (untuk header/sidebar gelap).
     */
    public static function logoLight(): string
    {
        return self::settings()->fileUrl('logo_light')
            ?? self::logo();
    }

    public static function favicon(): string
    {
        return self::settings()->fileUrl('favicon')
            ?? self::settings()->fileUrl('logo')
            ?? asset('assets/img/icon/basecamp.png');
    }

    public static function loginImage(): string
    {
        return self::settings()->fileUrl('login_image')
            ?? self::logo();
    }

    public static function primary(): string
    {
        return (string) self::settings()->val('color_primary');
    }

    public static function secondary(): string
    {
        return (string) self::settings()->val('color_secondary');
    }

    public static function accent(): string
    {
        return (string) self::settings()->val('color_accent');
    }

    public static function email(): ?string
    {
        return self::settings()->val('email');
    }

    public static function phone(): ?string
    {
        return self::settings()->val('phone');
    }

    public static function whatsapp(): ?string
    {
        return self::settings()->val('whatsapp');
    }

    public static function whatsappUrl(): ?string
    {
        $number = self::whatsapp();

        return $number
            ? WhatsApp::link($number, 'Halo ' . self::name() . ', saya ingin bertanya.')
            : null;
    }

    public static function address(): ?string
    {
        return self::settings()->val('address');
    }

    public static function footer(): string
    {
        return (string) (self::settings()->val('footer_text')
            ?? '© ' . date('Y') . ' ' . self::company() . '. Seluruh hak dilindungi.');
    }

    /**
     * CSS variables untuk di-inject di layout.
     */
    public static function cssVariables(): string
    {
        return sprintf(
            '--brand-primary:%s;--brand-secondary:%s;--brand-accent:%s;',
            self::primary(),
            self::secondary(),
            self::accent(),
        );
    }
}