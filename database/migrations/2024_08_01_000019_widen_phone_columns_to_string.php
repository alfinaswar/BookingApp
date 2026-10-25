<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Nomor HP Indonesia selalu diawali 0 (mis. 0812...). Kolom BIGINT membuang
 * angka nol di depan, sehingga nomor tersimpan sebagai 812... dan deep link
 * WhatsApp menjadi salah. Ubah ke VARCHAR.
 *
 * Diperbaiki dua kali: kolom di `bookings` dan `users` bila ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->widen('bookings', 'hp');
        $this->widen('users', 'phone');
    }

    public function down(): void
    {
        // Tidak dikembalikan ke BIGINT: data nomor yang sudah benar akan rusak.
    }

    private function widen(string $table, string $column): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        $current = DB::selectOne("SHOW COLUMNS FROM `{$table}` WHERE Field = ?", [$column]);

        if ($current && stripos($current->Type, 'int') === false) {
            return; // sudah varchar
        }

        DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` VARCHAR(20) NULL");

        // Rapikan nilai lama yang kehilangan nol di depan.
        DB::table($table)
            ->whereNotNull($column)
            ->orderBy('id')
            ->each(function ($row) use ($table, $column) {
                $value = (string) $row->{$column};

                if ($value === '' || str_starts_with($value, '0') || str_starts_with($value, '62')) {
                    return;
                }

                if (str_starts_with($value, '8')) {
                    DB::table($table)->where('id', $row->id)->update([$column => '0' . $value]);
                }
            });
    }
};