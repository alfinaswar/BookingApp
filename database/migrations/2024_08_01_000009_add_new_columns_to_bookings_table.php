<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const COLUMNS = [
        'kode_booking' => 'varchar(30)',
        'check_in_date' => 'date',
        'check_out_date' => 'date',
        'malam' => 'smallint unsigned',
        'jumlah_unit' => 'smallint unsigned',
        'jumlah_dewasa' => 'smallint unsigned',
        'jumlah_anak' => 'smallint unsigned',
        'status' => 'varchar(20)',
        'payment_status' => 'varchar(20)',
        'source' => 'varchar(20)',
        'subtotal' => 'decimal(14,2)',
        'discount_total' => 'decimal(14,2)',
        'addon_total' => 'decimal(14,2)',
        'tax_total' => 'decimal(14,2)',
        'service_total' => 'decimal(14,2)',
        'grand_total' => 'decimal(14,2)',
        'dp_amount' => 'decimal(14,2)',
        'paid_total' => 'decimal(14,2)',
        'balance_due' => 'decimal(14,2)',
        'promo_id' => 'bigint unsigned',
        'expires_at' => 'timestamp',
        'confirmed_at' => 'timestamp',
        'checked_in_at' => 'timestamp',
        'checked_out_at' => 'timestamp',
        'cancelled_at' => 'timestamp',
        'cancel_reason' => 'varchar(255)',
        'created_by' => 'bigint unsigned',
    ];

    private const NOT_NULL = [
        'kode_booking', 'check_in_date', 'check_out_date', 'malam', 'jumlah_unit',
        'jumlah_dewasa', 'jumlah_anak', 'status', 'payment_status', 'source',
        'subtotal', 'discount_total', 'addon_total', 'tax_total', 'service_total',
        'grand_total', 'dp_amount', 'paid_total', 'balance_due',
    ];

    public function up(): void
    {
        // Fase A: tambah kolom sebagai NULL dulu. MySQL strict mode menolak
        // ADD COLUMN NOT NULL tanpa default pada tabel yang sudah berisi data.
        foreach (self::COLUMNS as $name => $type) {
            if (Schema::hasColumn('bookings', $name)) {
                continue;
            }

            DB::statement("ALTER TABLE `bookings` ADD `{$name}` {$type} NULL" . $this->defaultClause($name));
        }

        // Fase B: isi data historis dulu, baru bikin index unik.
        // Baris lama masih berisi '' pada kode_booking sehingga unique index gagal.
        $this->backfill();

        $this->createIndex('bookings_status_expires_index', ['status', 'expires_at']);
        $this->createIndex('bookings_checkin_index', ['check_in_date']);
        $this->createIndex('bookings_promo_id_index', ['promo_id']);
        $this->createIndex('bookings_created_by_index', ['created_by']);
        $this->createIndex('bookings_kode_booking_unique', ['kode_booking'], unique: true);

        // Fase C: baru sekarang perketat jadi NOT NULL.
        foreach (self::NOT_NULL as $name) {
            $type = self::COLUMNS[$name];
            DB::statement("ALTER TABLE `bookings` MODIFY `{$name}` {$type} NOT NULL" . $this->defaultClause($name));
        }
    }

    public function down(): void
    {
        foreach ([
            'bookings_status_expires_index',
            'bookings_checkin_index',
            'bookings_kode_booking_unique',
            'bookings_promo_id_index',
            'bookings_created_by_index',
        ] as $index) {
            if ($this->indexExists($index)) {
                DB::statement("ALTER TABLE `bookings` DROP INDEX `{$index}`");
            }
        }

        foreach (array_keys(self::COLUMNS) as $name) {
            if (Schema::hasColumn('bookings', $name)) {
                DB::statement("ALTER TABLE `bookings` DROP COLUMN `{$name}`");
            }
        }
    }

    private function defaultClause(string $name): string
    {
        return match ($name) {
            'malam', 'jumlah_unit', 'jumlah_dewasa' => ' DEFAULT 1',
            'jumlah_anak' => ' DEFAULT 0',
            'status' => " DEFAULT 'pending'",
            'payment_status' => " DEFAULT 'unpaid'",
            'source' => " DEFAULT 'online'",
            'subtotal', 'discount_total', 'addon_total', 'tax_total',
            'service_total', 'grand_total', 'dp_amount', 'paid_total',
            'balance_due' => ' DEFAULT 0',
            default => '',
        };
    }

    private function createIndex(string $name, array $columns, bool $unique = false): void
    {
        if ($this->indexExists($name)) {
            return;
        }

        $type = $unique ? 'UNIQUE INDEX' : 'INDEX';
        $cols = implode(', ', array_map(fn ($c) => "`{$c}`", $columns));

        DB::statement("CREATE {$type} `{$name}` ON `bookings` ({$cols})");
    }

    private function indexExists(string $index): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', 'bookings')
            ->where('index_name', $index)
            ->exists();
    }

    private function backfill(): void
    {
        DB::table('bookings')->orderBy('id')->each(function ($row) {
            $checkIn = self::safeDate($row->checkIn);
            $checkOut = self::safeDate($row->checkOut);

            // Tanggal rusak / tidak masuk akal pada data uji: jadikan check-out = check-in + 1 malam.
            if (! $checkIn) {
                $checkIn = Carbon::today();
            }

            if (! $checkOut || $checkOut->lessThanOrEqualTo($checkIn)) {
                $checkOut = $checkIn->copy()->addDay();
            }

            $nights = max(1, (int) $checkIn->copy()->startOfDay()->diffInDays($checkOut->copy()->startOfDay()));
            $grand = (float) preg_replace('/[^0-9.]/', '', (string) $row->Total);
            $paid = in_array((string) $row->Status, ['1', '5'], true) ? $grand : 0.0;

            DB::table('bookings')->where('id', $row->id)->update([
                'kode_booking' => 'LEGACY-' . str_pad((string) $row->id, 6, '0', STR_PAD_LEFT),
                'check_in_date' => $checkIn->toDateString(),
                'check_out_date' => $checkOut->toDateString(),
                'malam' => $nights,
                'status' => self::mapStatus($row->Status),
                'payment_status' => self::mapPaymentStatus($row->Status),
                'grand_total' => $grand,
                'paid_total' => $paid,
                'balance_due' => max(0, $grand - $paid),
            ]);
        });
    }

    private static function safeDate($value): ?Carbon
    {
        if (empty($value)) {
            return null;
        }

        $raw = substr((string) $value, 0, 10);

        if ($raw === '0000-00-00' || $raw < '1900-01-01') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private static function mapStatus($legacy): string
    {
        return match ((string) $legacy) {
            '0' => 'pending',
            '1' => 'confirmed',
            '2' => 'pending',
            '5' => 'checked_out',
            default => 'cancelled',
        };
    }

    private static function mapPaymentStatus($legacy): string
    {
        return match ((string) $legacy) {
            '1', '5' => 'paid',
            '2' => 'pending',
            default => 'unpaid',
        };
    }
};