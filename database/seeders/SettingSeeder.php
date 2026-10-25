<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['key' => 'dp_percent', 'value' => '30', 'label' => 'Persentase DP (%)', 'group' => 'payment'],
            ['key' => 'hold_ttl_minutes', 'value' => '30', 'label' => 'Batas waktu hold (menit)', 'group' => 'booking'],
            ['key' => 'tax_rate', 'value' => '0', 'label' => 'Pajak PB1 (%)', 'group' => 'finance'],
            ['key' => 'service_rate', 'value' => '0', 'label' => 'Service charge (%)', 'group' => 'finance'],
            ['key' => 'min_stay_weekend', 'value' => '1', 'label' => 'Minimal malam (weekend)', 'group' => 'booking'],
            ['key' => 'max_stay', 'value' => '30', 'label' => 'Maksimal malam per booking', 'group' => 'booking'],
            ['key' => 'cancel_dp_refund', 'value' => '0', 'label' => 'DP dikembalikan saat batal (0/1)', 'group' => 'payment'],
        ];

        foreach ($rows as $row) {
            DB::table('settings')->updateOrInsert(['key' => $row['key']], $row + [
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}