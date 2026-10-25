<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Laporan dan rekonsiliasi butuh tahu berapa yang sudah dikembalikan.
 * Nilai lama diisi 0 supaya aggregasi tetap konsisten.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('bookings', 'refunded_total')) {
            DB::statement('ALTER TABLE `bookings` ADD `refunded_total` DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER `paid_total`');
        }

        if (! Schema::hasColumn('bookings', 'refund_reason')) {
            DB::statement('ALTER TABLE `bookings` ADD `refund_reason` VARCHAR(255) NULL AFTER `cancel_reason`');
        }
    }

    public function down(): void
    {
        foreach (['refunded_total', 'refund_reason'] as $column) {
            if (Schema::hasColumn('bookings', $column)) {
                DB::statement("ALTER TABLE `bookings` DROP COLUMN `{$column}`");
            }
        }
    }
};