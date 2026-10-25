<?php

namespace App\Support;

class WhatsApp
{
    /**
     * Normalisasi nomor Indonesia ke format internasional tanpa '+'.
     * 0812-3456-7890 -> 6281234567890
     */
    public static function normalize(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $phone);

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '0')) {
            return '62' . substr($digits, 1);
        }

        if (str_starts_with($digits, '62')) {
            return $digits;
        }

        if (str_starts_with($digits, '8')) {
            return '62' . $digits;
        }

        return $digits;
    }

    /**
     * Deep link wa.me dengan pesan terisi otomatis.
     */
    public static function link(?string $phone, string $message = ''): ?string
    {
        $normalized = self::normalize($phone);

        if (! $normalized) {
            return null;
        }

        $url = 'https://wa.me/' . $normalized;

        if ($message !== '') {
            $url .= '?text=' . rawurlencode($message);
        }

        return $url;
    }

    public static function adminLink(string $message = ''): ?string
    {
        return self::link(config('basecamp.admin_whatsapp'), $message);
    }
}