<?php

namespace App\Exceptions;

use RuntimeException;

class BookingException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $statusCode = 409,
        public readonly array $context = [],
    ) {
        parent::__construct($message);
    }

    public static function unitsUnavailable(array $unitKodes): self
    {
        return new self(
            'Unit berikut sudah terisi pada tanggal yang dipilih: ' . implode(', ', $unitKodes),
            409,
            ['conflicts' => $unitKodes],
        );
    }

    public static function invalidTransition(string $from, string $to): self
    {
        return new self("Status tidak bisa diubah dari {$from} ke {$to}.", 422);
    }

    public static function dpNotPaid(): self
    {
        return new self('DP belum terbayar. Booking tidak bisa dikonfirmasi.', 422);
    }

    public static function outstandingBalance(float $balance, string $aktivitas = 'check-out'): self
    {
        return new self(
            'Masih ada sisa pembayaran sebesar Rp ' . number_format($balance, 0, ',', '.') . '. Selesaikan pelunasan sebelum ' . $aktivitas . '.',
            422,
            ['balance_due' => $balance],
        );
    }
}