<?php

namespace App\Services;

use App\Models\Addon;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Promo;
use App\Models\RateOverride;
use App\Models\Room;
use App\Models\RoomUnit;
use App\Models\Season;
use App\Models\Setting;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

/**
 * Sumber tunggal perhitungan harga. Client tidak pernah menentukan harga.
 *
 * Urutan resolusi tarif per malam:
 *   1. rate_overrides[room][date]   (menang mutlak)
 *   2. base_rate weekday / weekend  (dari tanggal inap, bukan hari ini)
 *   3. season aktif dengan priority tertinggi
 */
class PricingService
{
    public function defaultGuestCount(): int
    {
        return 1;
    }

    /**
     * Tarif satu malam untuk sebuah tipe kamar pada tanggal tertentu.
     */
    public function nightlyRate(Room $room, Carbon|string $date, ?RateOverride $override = null): float
    {
        $date = $date instanceof Carbon ? $date->copy()->startOfDay() : Carbon::parse($date)->startOfDay();

        // Override bisa diberikan pemanggil (batch) atau dicari sendiri.
        if ($override === null) {
            $override = RateOverride::query()
                ->where('room_id', $room->id)
                ->whereDate('date', $date->toDateString())
                ->first();
        }

        if ($override && (int) $override->room_id === (int) $room->id
            && $override->date->toDateString() === $date->toDateString()) {
            return round((float) $override->rate, 2);
        }

        $isWeekend = $this->isWeekend($date);
        $rate = $room->baseRate($isWeekend);

        $season = $this->seasonFor($room, $date);
        if ($season) {
            $rate = $season->adjustment_type === Season::TYPE_PERCENT
                ? $rate * (1 + ($season->adjustment_value / 100))
                : $rate + $season->adjustment_value;
        }

        return round(max(0, $rate), 2);
    }

    /**
     * Rincian tarif per malam untuk satu unit.
     *
     * @return array{malam:int, per_night:array<string,float>, subtotal:float}
     */
    public function unitQuote(Room $room, string $checkIn, string $checkOut): array
    {
        $start = Carbon::parse($checkIn)->startOfDay();
        $end = Carbon::parse($checkOut)->startOfDay();

        if ($end->lessThanOrEqualTo($start)) {
            return ['malam' => 0, 'per_night' => [], 'subtotal' => 0.0];
        }

        $overrides = $this->overridesFor($room, $start, $end);
        $perNight = [];
        $subtotal = 0.0;

        foreach (CarbonPeriod::create($start, $end->copy()->subDay()) as $date) {
            $key = $date->toDateString();
            $rate = $this->nightlyRate($room, $date, $overrides->get($key));
            $perNight[$key] = $rate;
            $subtotal += $rate;
        }

        return [
            'malam' => count($perNight),
            'per_night' => $perNight,
            'subtotal' => round($subtotal, 2),
        ];
    }

    /**
     * Quote lengkap untuk N unit (multi-unit booking).
     *
     * @param  array<int,int>  $roomUnitIds
     * @param  array<int,array{id:int,qty?:int}>  $addonInput
     * @param  array{dewasa?:int,anak?:int}  $guests
     * @return array<string,mixed>
     */
    public function quoteForUnits(
        array $roomUnitIds,
        string $checkIn,
        string $checkOut,
        array $addonInput = [],
        array $guests = [],
        ?Promo $promo = null,
    ): array {
        $units = RoomUnit::with('room')->whereIn('id', $roomUnitIds)->get()->keyBy('id');
        $nights = max(0, Carbon::parse($checkIn)->startOfDay()->diffInDays(Carbon::parse($checkOut)->startOfDay()));
        $dewasa = max(1, (int) ($guests['dewasa'] ?? $this->defaultGuestCount()));
        $anak = max(0, (int) ($guests['anak'] ?? 0));
        $totalGuests = $dewasa + $anak;

        $lineItems = [];
        $subtotal = 0.0;

        foreach ($roomUnitIds as $unitId) {
            $unit = $units->get($unitId);
            if (! $unit || ! $unit->room) {
                continue;
            }

            $quote = $this->unitQuote($unit->room, $checkIn, $checkOut);

            $lineItems[] = [
                'room_unit_id' => $unit->id,
                'room_id' => $unit->room_id,
                'unit_kode' => $unit->kode,
                'unit_nama' => $unit->label(),
                'tipe' => $unit->room->namaTipe(),
                'malam' => $quote['malam'],
                'per_night' => $quote['per_night'],
                'subtotal' => $quote['subtotal'],
            ];

            $subtotal += $quote['subtotal'];
        }

        $addonLines = $this->addonLines($addonInput, $nights, $totalGuests);
        $addonTotal = round(array_sum(array_column($addonLines, 'subtotal')), 2);

        $discount = 0.0;
        if ($promo && $promo->isUsable($subtotal)) {
            $discount = $promo->discountFor($subtotal);
        }

        $serviceRate = Setting::getFloat('service_rate', 0);
        $taxRate = Setting::getFloat('tax_rate', 0);

        $serviceTotal = round(($subtotal - $discount + $addonTotal) * ($serviceRate / 100), 2);
        $taxTotal = round(($subtotal - $discount + $addonTotal + $serviceTotal) * ($taxRate / 100), 2);

        $grandTotal = round($subtotal - $discount + $addonTotal + $serviceTotal + $taxTotal, 2);
        $dpPercent = Setting::getFloat('dp_percent', 30);
        $dpAmount = round($grandTotal * ($dpPercent / 100), 2);

        return [
            'malam' => (int) $nights,
            'jumlah_unit' => count($lineItems),
            'dewasa' => $dewasa,
            'anak' => $anak,
            'units' => $lineItems,
            'addons' => $addonLines,
            'subtotal' => round($subtotal, 2),
            'addon_total' => $addonTotal,
            'discount_total' => $discount,
            'service_total' => $serviceTotal,
            'tax_total' => $taxTotal,
            'grand_total' => $grandTotal,
            'dp_percent' => $dpPercent,
            'dp_amount' => $dpAmount,
            'balance_due' => $grandTotal,
            'promo_id' => $promo?->id,
        ];
    }

    /**
     * Baris add-on beserta harga yang dihitung server.
     */
    public function addonLines(array $addonInput, int $nights, int $guests): array
    {
        $ids = array_filter(array_map(fn ($row) => (int) ($row['id'] ?? $row['addon_id'] ?? 0), $addonInput));
        if (empty($ids)) {
            return [];
        }

        $addons = Addon::active()->whereIn('id', $ids)->get()->keyBy('id');
        $lines = [];

        foreach ($addonInput as $row) {
            $addon = $addons->get((int) ($row['id'] ?? $row['addon_id'] ?? 0));
            if (! $addon) {
                continue;
            }

            $qty = max(1, (int) ($row['qty'] ?? 1));
            $subtotal = $addon->priceFor($nights, $guests, $qty);

            $lines[] = [
                'addon_id' => $addon->id,
                'nama_snapshot' => $addon->nama,
                'satuan' => $addon->satuan,
                'qty' => $qty,
                'unit_price' => (float) $addon->harga,
                'subtotal' => $subtotal,
            ];
        }

        return $lines;
    }

    /**
     * Hitung ulang tagihan booking dari data tersimpan.
     *
     * Refund mengurangi kas, jadi nominalnya dikurangkan, bukan ditambahkan.
     * Booking yang sudah dibatalkan/kedaluwarsa tidak punya sisa tagihan.
     */
    public function recalculateTotals(Booking $booking): array
    {
        $payments = $booking->payments()->verified()->get();

        $paid = (float) $payments->where('tipe', '!=', Payment::TIPE_REFUND)->sum('amount');
        $refunded = (float) $payments->where('tipe', Payment::TIPE_REFUND)->sum('amount');

        $grand = (float) $booking->grand_total;
        $inactive = in_array($booking->status, [Booking::STATUS_CANCELLED, Booking::STATUS_EXPIRED], true);

        $balance = $inactive ? 0.0 : max(0, round($grand - ($paid - $refunded), 2));

        $paymentStatus = match (true) {
            $refunded > 0 => Booking::PAYMENT_REFUNDED,
            $paid <= 0 && $balance > 0 => Booking::PAYMENT_UNPAID,
            $paid > 0 && $balance <= 0 => Booking::PAYMENT_PAID,
            $paid > 0 => Booking::PAYMENT_PARTIAL,
            default => Booking::PAYMENT_UNPAID,
        };

        return [
            'paid_total' => round($paid, 2),
            'refunded_total' => round($refunded, 2),
            'balance_due' => $balance,
            'payment_status' => $paymentStatus,
        ];
    }

    public function isWeekend(Carbon $date): bool
    {
        // Jumat & Sabtu malam = high demand di penginapan (check-in Jumat, pulang Minggu).
        return in_array($date->dayOfWeekIso, [5, 6], true);
    }

    private function seasonFor(Room $room, Carbon $date): ?Season
    {
        static $cache = [];
        $key = $room->id . '|' . $date->toDateString();

        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        $season = Season::query()
            ->where('is_active', true)
            ->whereDate('start_date', '<=', $date->toDateString())
            ->whereDate('end_date', '>=', $date->toDateString())
            ->where(fn ($q) => $q->whereNull('room_id')->orWhere('room_id', $room->id))
            ->orderByDesc('priority')
            ->orderByDesc('id')
            ->first();

        return $cache[$key] = $season;
    }

    private function overridesFor(Room $room, Carbon $start, Carbon $end)
    {
        return RateOverride::query()
            ->where('room_id', $room->id)
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<', $end->toDateString())
            ->get()
            ->keyBy(fn (RateOverride $o) => $o->date->toDateString());
    }
}
