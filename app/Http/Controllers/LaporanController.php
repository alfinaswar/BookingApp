<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingUnitNight;
use App\Models\InventoryBlock;
use App\Models\Payment;
use App\Models\RoomUnit;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LaporanController extends Controller
{
    public function index()
    {
        return view('laporan.index');
    }

    /**
     * KPI ringkas untuk header List Booking.
     */
    public function kpi()
    {
        $today = Carbon::today()->toDateString();

        return response()->json([
            'arrival' => Booking::whereDate('check_in_date', $today)
                ->whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_PENDING])->count(),
            'inhouse' => Booking::where('status', Booking::STATUS_CHECKED_IN)->count(),
            'departure' => Booking::whereDate('check_out_date', $today)
                ->where('status', Booking::STATUS_CHECKED_IN)->count(),
            'pending_payment' => Payment::where('status', Payment::STATUS_WAITING)->count(),
            'revenue_month' => (float) Payment::verified()
                ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->sum('amount'),
        ]);
    }

    /**
     * Laporan okupansi: unit-nights terisi dibagi unit-nights tersedia.
     */
    public function occupancy(Request $request)
    {
        [$from, $to] = $this->range($request);
        $days = $from->diffInDays($to) ?: 1;

        $unitIds = RoomUnit::active()->pluck('id');
        $capacity = $unitIds->count() * $days;

        $blocked = InventoryBlock::whereIn('room_unit_id', $unitIds)
            ->whereDate('start_date', '<', $to->toDateString())
            ->whereDate('end_date', '>', $from->toDateString())
            ->get()
            ->sum(function (InventoryBlock $b) use ($from, $to) {
                $start = $b->start_date->greaterThan($from) ? $b->start_date : $from;
                $end = $b->end_date->lessThan($to) ? $b->end_date : $to;

                return max(0, $start->diffInDays($end));
            });

        $occupied = BookingUnitNight::query()
            ->whereIn('room_unit_id', $unitIds)
            ->whereDate('stay_date', '>=', $from->toDateString())
            ->whereDate('stay_date', '<', $to->toDateString())
            ->whereIn('status', [BookingUnitNight::STATUS_HELD, BookingUnitNight::STATUS_BOOKED])
            ->count();

        $available = max(1, $capacity - $blocked);

        $perDay = BookingUnitNight::query()
            ->whereIn('room_unit_id', $unitIds)
            ->whereDate('stay_date', '>=', $from->toDateString())
            ->whereDate('stay_date', '<', $to->toDateString())
            ->whereIn('status', [BookingUnitNight::STATUS_HELD, BookingUnitNight::STATUS_BOOKED])
            ->selectRaw('stay_date, COUNT(*) as nights, SUM(rate) as revenue')
            ->groupBy('stay_date')
            ->orderBy('stay_date')
            ->get()
            ->map(fn ($row) => [
                'date' => Carbon::parse($row->stay_date)->toDateString(),
                'nights' => (int) $row->nights,
                'revenue' => (float) $row->revenue,
                'occupancy' => $unitIds->count() > 0
                    ? round(($row->nights / $unitIds->count()) * 100, 1)
                    : 0,
            ]);

        $roomRevenue = (float) BookingUnitNight::query()
            ->whereIn('room_unit_id', $unitIds)
            ->whereDate('stay_date', '>=', $from->toDateString())
            ->whereDate('stay_date', '<', $to->toDateString())
            ->whereIn('status', [BookingUnitNight::STATUS_HELD, BookingUnitNight::STATUS_BOOKED])
            ->sum('rate');

        return response()->json([
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'days' => $days,
            'units' => $unitIds->count(),
            'capacity' => $capacity,
            'blocked' => (int) $blocked,
            'occupied' => $occupied,
            'available' => $available,
            'occupancy' => round(($occupied / $available) * 100, 1),
            'adr' => $occupied > 0 ? round($roomRevenue / $occupied, 2) : 0,
            'revpar' => $available > 0 ? round($roomRevenue / $available, 2) : 0,
            'room_revenue' => $roomRevenue,
            'per_day' => $perDay,
        ]);
    }

    /**
     * Pendapatan: akrual (per malam inap) vs kas (pembayaran diterima).
     */
    public function revenue(Request $request)
    {
        [$from, $to] = $this->range($request);

        $accrual = BookingUnitNight::query()
            ->whereDate('stay_date', '>=', $from->toDateString())
            ->whereDate('stay_date', '<', $to->toDateString())
            ->whereIn('status', [BookingUnitNight::STATUS_HELD, BookingUnitNight::STATUS_BOOKED])
            ->selectRaw('stay_date, SUM(rate) as total')
            ->groupBy('stay_date')
            ->orderBy('stay_date')
            ->get()
            ->map(fn ($r) => ['date' => Carbon::parse($r->stay_date)->toDateString(), 'total' => (float) $r->total]);

        $cash = Payment::verified()
            ->whereBetween('paid_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->selectRaw('DATE(paid_at) as d, SUM(amount) as total')
            ->groupBy('d')
            ->orderBy('d')
            ->get()
            ->map(fn ($r) => ['date' => (string) $r->d, 'total' => (float) $r->total]);

        $byMethod = Payment::verified()
            ->whereBetween('paid_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->selectRaw('metode, COUNT(*) as jumlah, SUM(amount) as total')
            ->groupBy('metode')
            ->get();

        $bySource = Booking::whereDate('created_at', '>=', $from->toDateString())
            ->whereDate('created_at', '<', $to->toDateString())
            ->selectRaw('source, COUNT(*) as jumlah, SUM(grand_total) as total')
            ->groupBy('source')
            ->get();

        return response()->json([
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'accrual' => $accrual,
            'cash' => $cash,
            'accrual_total' => (float) $accrual->sum('total'),
            'cash_total' => (float) $cash->sum('total'),
            'by_method' => $byMethod,
            'by_source' => $bySource,
        ]);
    }

    /**
     * Rekap tamu: kunjungan, malam, belanja.
     */
    public function guests(Request $request)
    {
        [$from, $to] = $this->range($request);

        $rows = Booking::query()
            ->whereDate('check_in_date', '>=', $from->toDateString())
            ->whereDate('check_in_date', '<', $to->toDateString())
            ->whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_CHECKED_IN, Booking::STATUS_CHECKED_OUT])
            ->selectRaw('Email, MAX(NamaBooking) as nama, MAX(hp) as hp, COUNT(*) as kunjungan, SUM(malam) as malam, SUM(grand_total) as belanja')
            ->groupBy('Email')
            ->orderByDesc('belanja')
            ->get();

        return response()->json([
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'total_tamu' => $rows->count(),
            'total_malam' => (int) $rows->sum('malam'),
            'total_belanja' => (float) $rows->sum('belanja'),
            'rows' => $rows,
        ]);
    }

    /**
     * Daftar booking mentah untuk ekspor.
     */
    public function filter(Request $request)
    {
        [$from, $to] = $this->range($request);

        $data = Booking::with(['units.unit', 'units.room.roomtypes', 'payments'])
            ->overlapping($from->toDateString(), $to->toDateString())
            ->get();

        return response()->json($data);
    }

    public function cetakLaporan(Request $request)
    {
        [$from, $to] = $this->range($request);

        $bookings = Booking::with(['units.unit', 'units.room.roomtypes', 'payments'])
            ->overlapping($from->toDateString(), $to->toDateString())
            ->orderBy('check_in_date')
            ->get();

        $nights = BookingUnitNight::query()
            ->whereDate('stay_date', '>=', $from->toDateString())
            ->whereDate('stay_date', '<', $to->toDateString())
            ->whereIn('status', [BookingUnitNight::STATUS_HELD, BookingUnitNight::STATUS_BOOKED])
            ->count();

        $pdf = app('dompdf.wrapper');
        $pdf->loadView('laporan.cetakLaporan', [
            'title' => 'Laporan Booking',
            'date' => now()->format('d/m/Y'),
            'from' => $from->format('d/m/Y'),
            'to' => $to->format('d/m/Y'),
            'booking' => $bookings,
            'nights' => $nights,
            'cash' => Payment::verified()
                ->whereBetween('paid_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
                ->sum('amount'),
        ]);

        return $pdf->download('Laporan-Booking-' . $from->format('Ymd') . '-' . $to->format('Ymd') . '.pdf');
    }

    /**
     * @return array{0:Carbon,1:Carbon}
     */
    private function range(Request $request): array
    {
        $from = $request->filled('from')
            ? Carbon::parse($request->get('from'))->startOfDay()
            : ($request->filled('checkIn') ? Carbon::parse($request->get('checkIn'))->startOfDay() : now()->startOfMonth());

        $to = $request->filled('to')
            ? Carbon::parse($request->get('to'))->startOfDay()
            : ($request->filled('checkOut') ? Carbon::parse($request->get('checkOut'))->startOfDay() : now()->startOfDay());

        if ($to->lessThanOrEqualTo($from)) {
            $to = $from->copy()->addDay();
        }

        return [$from, $to];
    }
}
