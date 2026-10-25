<?php

namespace Database\Seeders;

use App\Models\Addon;
use Illuminate\Database\Seeder;

class AddonSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['nama' => 'Sewa Tenda Dome', 'kategori' => 'Perlengkapan', 'harga' => 150000, 'satuan' => Addon::UNIT_PER_NIGHT],
            ['nama' => 'Sleeping Bag', 'kategori' => 'Perlengkapan', 'harga' => 50000, 'satuan' => Addon::UNIT_PER_PERSON_NIGHT],
            ['nama' => 'Matras Camping', 'kategori' => 'Perlengkapan', 'harga' => 30000, 'satuan' => Addon::UNIT_PER_PERSON_NIGHT],
            ['nama' => 'Paket Makan 3x', 'kategori' => 'Konsumsi', 'harga' => 120000, 'satuan' => Addon::UNIT_PER_PERSON],
            ['nama' => 'BBQ Set', 'kategori' => 'Konsumsi', 'harga' => 200000, 'satuan' => Addon::UNIT_PER_BOOKING],
            ['nama' => 'Guide Outbound', 'kategori' => 'Aktivitas', 'harga' => 350000, 'satuan' => Addon::UNIT_PER_BOOKING],
            ['nama' => 'Transportasi Bandara', 'kategori' => 'Transport', 'harga' => 250000, 'satuan' => Addon::UNIT_PER_BOOKING],
            ['nama' => 'Api Unggun', 'kategori' => 'Aktivitas', 'harga' => 100000, 'satuan' => Addon::UNIT_PER_NIGHT],
        ];

        foreach ($rows as $row) {
            Addon::firstOrCreate(['nama' => $row['nama']], $row + ['is_active' => true]);
        }
    }
}