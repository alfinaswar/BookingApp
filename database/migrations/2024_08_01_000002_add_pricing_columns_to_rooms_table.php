<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->unsignedSmallInteger('kapasitas_base')->default(2)->after('qty');
            $table->unsignedSmallInteger('kapasitas_max')->default(4)->after('kapasitas_base');
            $table->unsignedSmallInteger('min_stay')->default(1)->after('kapasitas_max');
            $table->decimal('base_rate_weekday', 12, 2)->default(0)->after('tarifWe');
            $table->decimal('base_rate_weekend', 12, 2)->default(0)->after('base_rate_weekday');
            $table->boolean('is_active')->default(true)->after('imgPreview');
            $table->unsignedSmallInteger('sort_order')->default(0)->after('is_active');
        });

        // Tarif lama bertipe string ("90", "83", "Rp. 90.000"). Normalisasi ke decimal.
        DB::table('rooms')->orderBy('id')->each(function ($room) {
            DB::table('rooms')->where('id', $room->id)->update([
                'base_rate_weekday' => self::normalizeRate($room->tarifWd),
                'base_rate_weekend' => self::normalizeRate($room->tarifWe),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn([
                'kapasitas_base',
                'kapasitas_max',
                'min_stay',
                'base_rate_weekday',
                'base_rate_weekend',
                'is_active',
                'sort_order',
            ]);
        });
    }

    private static function normalizeRate($value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        $clean = preg_replace('/[^0-9.,]/', '', (string) $value);

        if (str_contains($clean, ',') && !str_contains($clean, '.')) {
            $clean = str_replace(',', '.', $clean);
        } else {
            $clean = str_replace(',', '', $clean);
        }

        return round((float) $clean, 2);
    }
};
