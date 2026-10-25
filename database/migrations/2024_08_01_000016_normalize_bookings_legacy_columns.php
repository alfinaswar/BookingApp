<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MySQL menganggap `Status` dan `status` kolom yang sama (case-insensitive),
 * sehingga migrasi sebelumnya tidak pernah benar-benar membuat kolom `status` baru.
 *
 * Migrasi ini menormalkan nama kolom legacy dan membuang kolom yang sudah digantikan:
 *   - Status -> status   (data dipertahankan)
 *   - Total  -> dihapus  (digantikan grand_total)
 */
return new class extends Migration
{
    public function up(): void
    {
        $columns = collect(DB::select('SHOW COLUMNS FROM `bookings`'))->keyBy(fn ($c) => $c->Field);

        if ($columns->has('Status') && ! $columns->has('status')) {
            DB::statement('ALTER TABLE `bookings` CHANGE `Status` `status` VARCHAR(20) NULL');
        }

        // Normalisasi nilai legacy yang mungkin masih tersisa.
        DB::table('bookings')->whereIn('status', ['0', '2'])->update(['status' => 'pending']);
        DB::table('bookings')->where('status', '1')->update(['status' => 'confirmed']);
        DB::table('bookings')->where('status', '5')->update(['status' => 'checked_out']);
        DB::table('bookings')->where('status', '3')->update(['status' => 'cancelled']);
        DB::table('bookings')->whereNull('status')->update(['status' => 'pending']);

        DB::statement('ALTER TABLE `bookings` MODIFY `status` VARCHAR(20) NOT NULL DEFAULT \'pending\'');

        if ($columns->has('Total')) {
            DB::statement('ALTER TABLE `bookings` DROP COLUMN `Total`');
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('bookings', 'Total')) {
            DB::statement('ALTER TABLE `bookings` ADD `Total` VARCHAR(255) NULL');
        }

        DB::statement('ALTER TABLE `bookings` CHANGE `status` `Status` VARCHAR(255) NULL');
    }
};
