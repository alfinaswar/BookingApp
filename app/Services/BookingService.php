<?php

namespace App\Services;

use App\Exceptions\BookingException;
use App\Events\BookingStatusChanged;
use App\Models\Booking;
use App\Models\BookingStatusHistory;
use App\Models\BookingUnit;
use App\Models\BookingUnitNight;
use App\Models\Promo;
use App\Models\RoomUnit;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Satu-satunya pintu pembuatan & perubahan status booking.
 *
 * Jaminan:
 *  - harga selalu dihitung ulang di server (PricingService)
 *  - unit dikunci with lockForUpdate dalam urutan konsisten (anti-deadlock)
 *  - UNIQUE(room_unit_id, stay_date) menjadi jaring pengaman terakhir
 *  - semua transisi status tercatat di booking_status_histories
 */
class BookingService
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly PricingService $pricing,
    ) {
    }

    /**
     * Buat booking multi-unit.
     *
     * @param  array{
     *   room_unit_ids:array<int,int>,
     *   check_in:string, check_out:string,
     *   dewasa?:int, anak?:int,
     *   nama:string, email:string, hp:string, identitas?:string, no_identitas?:string, gender?:string,
     *   addons?:array, promo_kode?:string, source?:string, user_id?:int, created_by?:int
     * }  $data
     */
    public function create(array $data, ?User $actor = null): Booking
    {
        $unitIds = array_values(array_unique(array_map('intval', $data['room_unit_ids'] ?? [])));

        if (empty($unitIds)) {
            throw new BookingException('Pilih minimal satu unit.', 422);
        }

        $checkIn = Carbon::parse($data['check_in'])->startOfDay();
        $checkOut = Carbon::parse($data['check_out'])->startOfDay();

        if ($checkOut->lessThanOrEqualTo($checkIn)) {
            throw new BookingException('Tanggal check-out harus setelah check-in.', 422);
        }

        if ($checkIn->lt(Carbon::today())) {
            throw new BookingException('Tanggal check-in tidak boleh di masa lalu.', 422);
        }

        /** @var Promo|null $promo */
        $promo = null;
        if (! empty($data['promo_kode'])) {
            $promo = Promo::where('kode', $data['promo_kode'])->first();
        }

        try {
            return DB::transaction(function () use ($data, $unitIds, $checkIn, $checkOut, $promo, $actor) {
                // Lock konsisten: urut naik berdasarkan id untuk menghindari deadlock.
                sort($unitIds);
                $units = RoomUnit::with('room.roomtypes')
                    ->whereIn('id', $unitIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                if ($units->count() !== count($unitIds)) {
                    throw new BookingException('Ada unit yang tidak ditemukan.', 422);
                }

                $dateIn = $checkIn->toDateString();
                $dateOut = $checkOut->toDateString();

                $conflicts = $this->availability->conflictingUnits($unitIds, $dateIn, $dateOut);
                if (! empty($conflicts)) {
                    $kodes = $units->whereIn('id', $conflicts)->pluck('kode')->all();
                    throw BookingException::unitsUnavailable($kodes);
                }

                $quote = $this->pricing->quoteForUnits(
                    $unitIds,
                    $dateIn,
                    $dateOut,
                    $data['addons'] ?? [],
                    ['dewasa' => $data['dewasa'] ?? 1, 'anak' => $data['anak'] ?? 0],
                    $promo,
                );

                $kapasitas = (int) $units->sum(fn (RoomUnit $u) => (int) ($u->room->kapasitas_max ?? 0));
                $tamu = ($data['dewasa'] ?? 1) + ($data['anak'] ?? 0);
                if ($kapasitas > 0 && $tamu > $kapasitas) {
                    throw new BookingException(
                        "Jumlah tamu ({$tamu}) melebihi kapasitas {$kapasitas} unit yang dipilih.",
                        422
                    );
                }

                $holdMinutes = Setting::getInt('hold_ttl_minutes', 30);

                $booking = Booking::create([
                    'kode_booking' => $this->generateKode(),
                    'roomId' => $units->first()->room_id,
                    'userId' => $data['user_id'] ?? optional(auth()->user())->id,
                    'created_by' => $data['created_by'] ?? optional(auth()->user())->id,
                    'NamaBooking' => $data['nama'],
                    'Email' => $data['email'],
                    'hp' => (string) $data['hp'],
                    'JenisIdentitas' => $data['identitas'] ?? 'KTP',
                    'NoIdentitas' => $data['no_identitas'] ?? '-',
                    'Gender' => $data['gender'] ?? 'L',
                    'checkIn' => $checkIn->copy()->setTime(14, 0),
                    'checkOut' => $checkOut->copy()->setTime(12, 0),
                    'check_in_date' => $dateIn,
                    'check_out_date' => $dateOut,
                    'malam' => $quote['malam'],
                    'jumlah_unit' => $quote['jumlah_unit'],
                    'jumlahTamu' => $tamu,
                    'jumlah_dewasa' => $quote['dewasa'],
                    'jumlah_anak' => $quote['anak'],
                    'status' => Booking::STATUS_PENDING,
                    'payment_status' => Booking::PAYMENT_UNPAID,
                    'source' => $data['source'] ?? Booking::SOURCE_ONLINE,
                    'subtotal' => $quote['subtotal'],
                    'discount_total' => $quote['discount_total'],
                    'addon_total' => $quote['addon_total'],
                    'tax_total' => $quote['tax_total'],
                    'service_total' => $quote['service_total'],
                    'grand_total' => $quote['grand_total'],
                    'dp_amount' => $quote['dp_amount'],
                    'paid_total' => 0,
                    'balance_due' => $quote['grand_total'],
                    'promo_id' => $promo?->id,
                    'expires_at' => now()->addMinutes($holdMinutes),
                ]);

                $this->persistUnitsAndNights($booking, $quote);
                $this->persistAddons($booking, $quote['addons']);
                $this->recordHistory($booking, null, Booking::STATUS_PENDING, $actor, 'Booking dibuat');

                if ($promo) {
                    $promo->increment('terpakai');
                }

                return $booking->fresh(['units.unit', 'addons', 'payments']);
            });
        } catch (QueryException $e) {
            // UNIQUE(room_unit_id, stay_date) dilanggar = race condition antar request.
            if ($this->isDuplicateKey($e)) {
                throw new BookingException(
                    'Unit baru saja diambil pengguna lain. Silakan pilih unit lain.',
                    409
                );
            }
            throw $e;
        }
    }

    public function confirm(Booking $booking, ?User $actor = null): Booking
    {
        $this->assertTransition($booking, [Booking::STATUS_PENDING], Booking::STATUS_CONFIRMED);

        if ((float) $booking->paid_total < (float) $booking->dp_amount) {
            throw BookingException::dpNotPaid();
        }

        return $this->transition($booking, Booking::STATUS_CONFIRMED, $actor, 'DP terverifikasi', [
            'confirmed_at' => now(),
        ]);
    }

    public function checkIn(Booking $booking, ?User $actor = null): Booking
    {
        $this->assertTransition($booking, [Booking::STATUS_CONFIRMED], Booking::STATUS_CHECKED_IN);

        // Check-in mewajibkan seluruh tagihan lunas (bukan hanya DP),
        // sejalan dengan aturan check-out.
        if ((float) $booking->balance_due > 0) {
            throw BookingException::outstandingBalance((float) $booking->balance_due, 'check-in');
        }

        return $this->transition($booking, Booking::STATUS_CHECKED_IN, $actor, 'Tamu check-in', [
            'checked_in_at' => now(),
        ]);
    }

    public function checkOut(Booking $booking, ?User $actor = null, bool $override = false): Booking
    {
        $this->assertTransition($booking, [Booking::STATUS_CHECKED_IN], Booking::STATUS_CHECKED_OUT);

        if ((float) $booking->balance_due > 0 && ! $override) {
            throw BookingException::outstandingBalance((float) $booking->balance_due);
        }

        $note = $override && (float) $booking->balance_due > 0
            ? 'Check-out dengan override supervisor (sisa Rp ' . number_format((float) $booking->balance_due, 0, ',', '.') . ')'
            : 'Tamu check-out';

        return $this->transition($booking, Booking::STATUS_CHECKED_OUT, $actor, $note, [
            'checked_out_at' => now(),
        ]);
    }

    public function cancel(Booking $booking, ?User $actor = null, string $reason = '', ?float $refundAmount = null): Booking
    {
        $this->assertTransition(
            $booking,
            [Booking::STATUS_PENDING, Booking::STATUS_CONFIRMED],
            Booking::STATUS_CANCELLED
        );

        return DB::transaction(function () use ($booking, $actor, $reason, $refundAmount) {
            // Baris night dihapus, bukan ditandai released: unique index
            // (room_unit_id, stay_date) tidak peduli status, jadi unit baru
            // tetap terblokir bila barisnya dibiarkan ada.
            $booking->nights()->delete();

            // Kebijakan refund: null = ikut setting cancel_dp_refund.
            $amount = $refundAmount ?? $booking->refundableAmount();
            $amount = max(0, min($amount, (float) $booking->paid_total - (float) $booking->refunded_total));

            $extra = [
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ];

            if ($amount > 0) {
                $extra['refund_reason'] = 'Refund pembatalan';
            }

            $cancelled = $this->transition($booking, Booking::STATUS_CANCELLED, $actor, $reason ?: 'Dibatalkan', $extra);

            if ($amount > 0) {
                // Dicatat sebagai tagihan refund berstatus pending.
                // Kas baru berkurang setelah admin menandainya selesai.
                app(PaymentService::class)->createRefundInvoice($cancelled, $amount, $reason);
            }

            return $this->applyTotals($cancelled);
        });
    }

    /**
     * Terapkan ulang paid_total/balance_due/payment_status dari data pembayaran.
     */
    public function applyTotals(Booking $booking): Booking
    {
        $booking->update($this->pricing->recalculateTotals($booking));

        return $booking->fresh(['units.unit', 'addons', 'payments']);
    }

    /**
     * Lepas hold yang kedaluwarsa. Dijalankan scheduler tiap menit.
     */
    public function expireHolds(): int
    {
        $expired = Booking::query()
            ->where('status', Booking::STATUS_PENDING)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->get();

        $count = 0;

        foreach ($expired as $booking) {
            DB::transaction(function () use ($booking, &$count) {
                $booking->nights()->delete();

                // Lewat transition() supaya histori dan notifikasi konsisten
                // dengan transisi status lainnya.
                $this->transition($booking, Booking::STATUS_EXPIRED, null, 'Hold expired otomatis', [
                    'cancelled_at' => now(),
                    'cancel_reason' => 'Hangus: DP tidak dibayar dalam batas waktu',
                ]);

                $count++;
            });
        }

        if ($count > 0) {
            Log::info("[booking] {$count} hold kedaluwarsa dilepas");
        }

        return $count;
    }

    /**
     * Simpan nomor unit + satu baris per unit per malam.
     */
    private function persistUnitsAndNights(Booking $booking, array $quote): void
    {
        $nights = [];
        $timestamp = now();

        foreach ($quote['units'] as $index => $line) {
            $bookingUnit = BookingUnit::create([
                'booking_id' => $booking->id,
                'room_unit_id' => $line['room_unit_id'],
                'room_id' => $line['room_id'],
                'check_in_date' => $booking->check_in_date,
                'check_out_date' => $booking->check_out_date,
                'urutan' => $index + 1,
                'subtotal' => $line['subtotal'],
                'rate_snapshot' => $line['per_night'],
            ]);

            foreach ($line['per_night'] as $date => $rate) {
                $nights[] = [
                    'booking_id' => $booking->id,
                    'booking_unit_id' => $bookingUnit->id,
                    'room_unit_id' => $line['room_unit_id'],
                    'stay_date' => $date,
                    'rate' => $rate,
                    'status' => BookingUnitNight::STATUS_HELD,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
            }
        }

        // Insert terurut supaya urutan lock index konsisten.
        usort($nights, fn ($a, $b) => [$a['room_unit_id'], $a['stay_date']] <=> [$b['room_unit_id'], $b['stay_date']]);

        foreach (array_chunk($nights, 200) as $chunk) {
            BookingUnitNight::insert($chunk);
        }
    }

    private function persistAddons(Booking $booking, array $lines): void
    {
        foreach ($lines as $line) {
            $booking->addons()->create($line);
        }
    }

    private function transition(Booking $booking, string $to, ?User $actor, string $note, array $extra = []): Booking
    {
        $from = $booking->status;

        $booking->fill(array_merge(['status' => $to], $extra))->save();

        $this->recordHistory($booking, $from, $to, $actor, $note);

        // Event dipancarkan langsung. Bila QUEUE_CONNECTION memakai opsi
        // after_commit (lihat config/queue.php), job baru di-push setelah
        // transaksi commit, sehingga tidak ada notifikasi untuk data rollback.
        BookingStatusChanged::dispatch($booking->fresh(), $from, $to, $note);

        return $booking->fresh(['units.unit', 'addons', 'payments']);
    }

    private function recordHistory(Booking $booking, ?string $from, string $to, ?User $actor, string $note): void
    {
        BookingStatusHistory::create([
            'booking_id' => $booking->id,
            'from_status' => $from,
            'to_status' => $to,
            'actor_id' => $actor?->id,
            'note' => $note,
        ]);
    }

    private function assertTransition(Booking $booking, array $allowedFrom, string $to): void
    {
        if (! in_array($booking->status, $allowedFrom, true)) {
            throw BookingException::invalidTransition((string) $booking->status, $to);
        }
    }

    private function generateKode(): string
    {
        $prefix = 'BC-' . now()->format('Ymd') . '-';
        $last = Booking::withTrashed()
            ->where('kode_booking', 'like', $prefix . '%')
            ->orderByDesc('kode_booking')
            ->value('kode_booking');

        $sequence = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    private function isDuplicateKey(QueryException $e): bool
    {
        return (string) $e->getCode() === '23000' || str_contains($e->getMessage(), 'Duplicate entry');
    }
}
