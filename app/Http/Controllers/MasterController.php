<?php

namespace App\Http\Controllers;

use App\Models\Addon;
use App\Models\InventoryBlock;
use App\Models\Promo;
use App\Models\RateOverride;
use App\Models\Room;
use App\Models\RoomUnit;
use App\Models\Season;
use App\Models\Setting;
use App\Services\AvailabilityService;
use App\Services\PricingService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\DataTables;

class MasterController extends Controller
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly PricingService $pricing,
    ) {
    }

    /**
     * Master Tarif & Ketersediaan: tipe, season, override, add-on, promo, setting.
     */
    public function index()
    {
        return view('Master.index', [
            'types' => Room::with('roomtypes')->orderBy('id')->get(),
            'addons' => Addon::orderBy('sort_order')->get(),
            'promos' => Promo::orderByDesc('id')->get(),
            'seasons' => Season::orderByDesc('start_date')->get(),
            'settings' => Setting::orderBy('group')->orderBy('key')->get(),
            'blocks' => InventoryBlock::with('unit')
                ->whereDate('end_date', '>=', now()->toDateString())
                ->orderBy('start_date')
                ->get(),
        ]);
    }

    /**
     * Kalender harga & ketersediaan per tipe: matriks tanggal × tipe.
     */
    public function kalender(Request $request)
    {
        $from = $request->filled('from')
            ? Carbon::parse($request->get('from'))->startOfDay()
            : now()->startOfDay();

        $to = $request->filled('to') ? Carbon::parse($request->get('to'))->startOfDay() : $from->copy()->addDays(30);
        $to = $to->lessThanOrEqualTo($from) ? $from->copy()->addDay() : $to;

        // Batasi agar ui tetap ringan.
        if ($from->diffInDays($to) > 62) {
            $to = $from->copy()->addDays(62);
        }

        $types = Room::with('roomtypes')->active()->orderBy('sort_order')->orderBy('id')->get();

        $overrides = RateOverride::query()
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<', $to->toDateString())
            ->get()
            ->keyBy(fn (RateOverride $o) => $o->room_id . '|' . $o->date->toDateString());

        $seasons = Season::query()
            ->where('is_active', true)
            ->whereDate('start_date', '<', $to->toDateString())
            ->whereDate('end_date', '>=', $from->toDateString())
            ->orderByDesc('priority')
            ->get();

        $unitCounts = RoomUnit::active()
            ->selectRaw('room_id, COUNT(*) as total')
            ->groupBy('room_id')
            ->pluck('total', 'room_id');

        $sold = \App\Models\BookingUnitNight::query()
            ->whereDate('booking_unit_nights.stay_date', '>=', $from->toDateString())
            ->whereDate('booking_unit_nights.stay_date', '<', $to->toDateString())
            ->whereIn('booking_unit_nights.status', [
                \App\Models\BookingUnitNight::STATUS_HELD,
                \App\Models\BookingUnitNight::STATUS_BOOKED,
            ])
            ->join('room_units', 'room_units.id', '=', 'booking_unit_nights.room_unit_id')
            ->selectRaw('room_units.room_id as room_id, booking_unit_nights.stay_date as stay_date, COUNT(*) as sold')
            ->groupBy('room_units.room_id', 'booking_unit_nights.stay_date')
            ->get()
            ->keyBy(fn ($r) => $r->room_id . '|' . Carbon::parse($r->stay_date)->toDateString());

        $dates = collect(CarbonPeriod::create($from, $to->copy()->subDay()))
            ->map(fn (Carbon $d) => $d->toDateString())
            ->values()
            ->all();

        $rows = [];

        foreach ($types as $room) {
            $cells = [];

            foreach ($dates as $date) {
                $day = Carbon::parse($date);
                $override = $overrides->get($room->id . '|' . $date);

                $season = $seasons->first(
                    fn (Season $s) => ($s->room_id === null || (int) $s->room_id === (int) $room->id)
                        && $s->start_date->toDateString() <= $date
                        && $s->end_date->toDateString() >= $date
                );

                $rate = $this->pricing->nightlyRate($room, $day, $override);
                $total = (int) ($unitCounts[$room->id] ?? 0);
                $terjual = (int) optional($sold->get($room->id . '|' . $date))->sold;

                $cells[$date] = [
                    'rate' => $rate,
                    'is_weekend' => $this->pricing->isWeekend($day),
                    'override' => $override ? (float) $override->rate : null,
                    'season' => $season?->nama,
                    'closed' => (bool) ($override->closed ?? false),
                    'total' => $total,
                    'sold' => $terjual,
                    'free' => max(0, $total - $terjual),
                ];
            }

            $rows[] = [
                'room_id' => $room->id,
                'tipe' => $room->namaTipe(),
                'nama' => $room->nama,
                'units' => (int) ($unitCounts[$room->id] ?? 0),
                'cells' => $cells,
            ];
        }

        return response()->json([
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'dates' => $dates,
            'types' => $rows,
        ]);
    }

    /**
     * Simpan harga khusus (rate override) untuk tipe + tanggal tertentu.
     */
    public function storeOverride(Request $request)
    {
        $data = $request->validate([
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'date' => ['required', 'date'],
            'rate' => ['required', 'numeric', 'min:0'],
            'min_stay' => ['nullable', 'integer', 'min:1', 'max:30'],
            'closed' => ['nullable', 'boolean'],
        ]);

        $override = RateOverride::updateOrCreate(
            ['room_id' => $data['room_id'], 'date' => $data['date']],
            [
                'rate' => $data['rate'],
                'min_stay' => $data['min_stay'] ?? null,
                'closed' => $request->boolean('closed'),
            ]
        );

        return response()->json([
            'message' => 'Harga khusus disimpan.',
            'override' => $override,
        ]);
    }

    /**
     * Hapus override supaya tarif kembali ke basis + season.
     */
    public function destroyOverride(Request $request)
    {
        $data = $request->validate([
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'date' => ['required', 'date'],
        ]);

        RateOverride::where('room_id', $data['room_id'])
            ->whereDate('date', $data['date'])
            ->delete();

        return response()->json(['message' => 'Harga khusus dihapus.']);
    }

    /**
     * Isi rentang tanggal sekaligus (mis. 1–7 Jan naik 20%).
     */
    public function bulkOverride(Request $request)
    {
        $data = $request->validate([
            'room_ids' => ['required', 'array', 'min:1'],
            'room_ids.*' => ['integer', 'exists:rooms,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'mode' => ['required', 'in:set,percent,fixed_add'],
            'value' => ['required', 'numeric'],
            'min_stay' => ['nullable', 'integer', 'min:1', 'max:30'],
            'closed' => ['nullable', 'boolean'],
        ]);

        $start = Carbon::parse($data['start_date'])->startOfDay();
        $end = Carbon::parse($data['end_date'])->startOfDay();

        if ($start->diffInDays($end) > 180) {
            return response()->json(['message' => 'Rentang maksimal 180 hari.'], 422);
        }

        $touched = 0;
        $closed = $request->boolean('closed');

        DB::transaction(function () use ($data, $start, $end, $closed, &$touched) {
            foreach ($data['room_ids'] as $roomId) {
                $room = Room::find($roomId);

                foreach (CarbonPeriod::create($start, $end) as $date) {
                    // Selalu hitung dari tarif dasar + season (bukan tarif efektif
                    // saat ini), supaya bulk edit berulang tidak menumpuk persentase.
                    $base = $room->baseRate($this->pricing->isWeekend($date));

                    $season = Season::query()
                        ->where('is_active', true)
                        ->whereDate('start_date', '<=', $date->toDateString())
                        ->whereDate('end_date', '>=', $date->toDateString())
                        ->where(fn ($q) => $q->whereNull('room_id')->orWhere('room_id', $roomId))
                        ->orderByDesc('priority')
                        ->first();

                    if ($season) {
                        $base = $season->adjustment_type === Season::TYPE_PERCENT
                            ? $base * (1 + ($season->adjustment_value / 100))
                            : $base + $season->adjustment_value;
                    }

                    $base = round(max(0, $base), 2);

                    $rate = match ($data['mode']) {
                        'set' => (float) $data['value'],
                        'percent' => round($base * (1 + ($data['value'] / 100)), 2),
                        'fixed_add' => round($base + (float) $data['value'], 2),
                    };

                    RateOverride::updateOrCreate(
                        ['room_id' => $roomId, 'date' => $date->toDateString()],
                        [
                            'rate' => max(0, $rate),
                            'min_stay' => $data['min_stay'] ?? null,
                            'closed' => $closed,
                        ]
                    );

                    $touched++;
                }
            }
        });

        return response()->json(['message' => "{$touched} tanggal diperbarui."]);
    }

    /**
     * Blokir unit (maintenance / perbaikan) untuk rentang tanggal.
     */
    public function storeBlock(Request $request)
    {
        $data = $request->validate([
            'room_unit_ids' => ['required', 'array', 'min:1'],
            'room_unit_ids.*' => ['integer', 'exists:room_units,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $start = Carbon::parse($data['start_date'])->startOfDay();
        $end = Carbon::parse($data['end_date'])->startOfDay();

        if ($start->diffInDays($end) > 365) {
            return response()->json(['message' => 'Rentang blokir terlalu panjang.'], 422);
        }

        $created = 0;

        DB::transaction(function () use ($data, $start, $end, &$created) {
            foreach ($data['room_unit_ids'] as $unitId) {
                // Tolak blokir yang menabrak booking aktif.
                $conflict = \App\Models\BookingUnitNight::query()
                    ->where('room_unit_id', $unitId)
                    ->whereDate('stay_date', '>=', $start->toDateString())
                    ->whereDate('stay_date', '<', $end->toDateString())
                    ->whereIn('status', [
                        \App\Models\BookingUnitNight::STATUS_HELD,
                        \App\Models\BookingUnitNight::STATUS_BOOKED,
                    ])
                    ->exists();

                if ($conflict) {
                    continue;
                }

                InventoryBlock::create([
                    'room_unit_id' => $unitId,
                    'start_date' => $start,
                    'end_date' => $end,
                    'reason' => $data['reason'] ?? 'Blokir manual',
                    'created_by' => auth()->id(),
                ]);

                $created++;
            }
        });

        if ($created === 0) {
            return response()->json([
                'message' => 'Unit yang dipilih sedang terisi pada rentang tersebut.',
            ], 409);
        }

        return response()->json(['message' => "{$created} unit diblokir."]);
    }

    public function destroyBlock(InventoryBlock $block)
    {
        $block->delete();

        return response()->json(['message' => 'Blokir dihapus.']);
    }

    /**
     * Daftar unit per tipe untuk pilihan blokir.
     */
    public function unitOptions(Request $request)
    {
        $units = RoomUnit::with('room.roomtypes')
            ->when($request->filled('room_id'), fn ($q) => $q->where('room_id', $request->integer('room_id')))
            ->orderBy('room_id')
            ->orderBy('kode')
            ->get()
            ->map(fn (RoomUnit $u) => [
                'id' => $u->id,
                'kode' => $u->kode,
                'tipe' => $u->room->roomtypes->nama ?? '-',
                'status' => $u->status,
            ]);

        return response()->json($units);
    }

    // ---------- Tipe & tarif dasar ----------

    public function updateType(Request $request, Room $room)
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:100'],
            'deskripsi' => ['nullable', 'string', 'max:255'],
            'kapasitas_base' => ['required', 'integer', 'min:1', 'max:50'],
            'kapasitas_max' => ['required', 'integer', 'min:1', 'max:50', 'gte:kapasitas_base'],
            'min_stay' => ['required', 'integer', 'min:1', 'max:30'],
            'base_rate_weekday' => ['required', 'numeric', 'min:0'],
            'base_rate_weekend' => ['required', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $room->update($data + ['is_active' => $request->boolean('is_active', true)]);

        return response()->json(['message' => 'Tipe akomodasi diperbarui.', 'room' => $room->fresh()]);
    }

    // ---------- Unit ----------

    public function units(Request $request)
    {
        if ($request->ajax()) {
            $data = RoomUnit::with('room.roomtypes')->orderBy('room_id')->orderBy('kode');

            return DataTables::of($data)
                ->addIndexColumn()
                ->addColumn('tipe', fn (RoomUnit $u) => $u->room->roomtypes->nama ?? '-')
                ->addColumn('status_badge', fn (RoomUnit $u) => match ($u->status) {
                    'available' => '<span class="badge bg-green text-white">Tersedia</span>',
                    'maintenance' => '<span class="badge bg-orange text-white">Maintenance</span>',
                    default => '<span class="badge bg-secondary text-white">Nonaktif</span>',
                })
                ->addColumn('action', fn (RoomUnit $u) => '<a href="javascript:void(0)" class="btn btn-sm btn-outline-primary btn-edit-unit" data-id="' . $u->id . '"><i class="fas fa-edit"></i></a>')
                ->rawColumns(['status_badge', 'action'])
                ->make(true);
        }

        return response()->json(RoomUnit::with('room.roomtypes')->get());
    }

    public function updateUnit(Request $request, RoomUnit $roomUnit)
    {
        $data = $request->validate([
            'nama' => ['nullable', 'string', 'max:100'],
            'status' => ['required', 'in:available,maintenance,inactive'],
            'lantai' => ['nullable', 'integer', 'min:1', 'max:99'],
            'catatan' => ['nullable', 'string', 'max:500'],
        ]);

        $roomUnit->update($data);

        return response()->json(['message' => 'Unit diperbarui.', 'unit' => $roomUnit->fresh()]);
    }

    public function storeUnit(Request $request)
    {
        $data = $request->validate([
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'kode' => ['required', 'string', 'max:50', 'unique:room_units,kode'],
            'nama' => ['nullable', 'string', 'max:100'],
            'status' => ['required', 'in:available,maintenance,inactive'],
        ]);

        $unit = RoomUnit::create($data);

        return response()->json(['message' => 'Unit ditambahkan.', 'unit' => $unit]);
    }

    // ---------- Season ----------

    public function storeSeason(Request $request)
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:100'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:999'],
            'adjustment_type' => ['required', 'in:percent,fixed'],
            'adjustment_value' => ['required', 'numeric'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $season = Season::create($data + [
            'priority' => $data['priority'] ?? 0,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return response()->json(['message' => 'Season ditambahkan.', 'season' => $season]);
    }

    public function destroySeason(Season $season)
    {
        $season->delete();

        return response()->json(['message' => 'Season dihapus.']);
    }

    // ---------- Add-on ----------

    public function storeAddon(Request $request)
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:100'],
            'kategori' => ['nullable', 'string', 'max:50'],
            'harga' => ['required', 'numeric', 'min:0'],
            'satuan' => ['required', 'in:per_night,per_person,per_person_night,per_booking'],
            'stok' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $addon = Addon::create($data + ['is_active' => $request->boolean('is_active', true)]);

        return response()->json(['message' => 'Add-on ditambahkan.', 'addon' => $addon]);
    }

    public function updateAddon(Request $request, Addon $addon)
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:100'],
            'kategori' => ['nullable', 'string', 'max:50'],
            'harga' => ['required', 'numeric', 'min:0'],
            'satuan' => ['required', 'in:per_night,per_person,per_person_night,per_booking'],
            'stok' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $addon->update($data + ['is_active' => $request->boolean('is_active', true)]);

        return response()->json(['message' => 'Add-on diperbarui.', 'addon' => $addon->fresh()]);
    }

    public function destroyAddon(Addon $addon)
    {
        $addon->delete();

        return response()->json(['message' => 'Add-on dihapus.']);
    }

    // ---------- Promo ----------

    public function storePromo(Request $request)
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:50', 'unique:promos,kode'],
            'nama' => ['nullable', 'string', 'max:100'],
            'tipe' => ['required', 'in:percent,fixed'],
            'nilai' => ['required', 'numeric', 'min:0'],
            'min_subtotal' => ['nullable', 'numeric', 'min:0'],
            'max_diskon' => ['nullable', 'numeric', 'min:0'],
            'valid_from' => ['nullable', 'date'],
            'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'kuota' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $promo = Promo::create($data + [
            'kode' => strtoupper($data['kode']),
            'min_subtotal' => $data['min_subtotal'] ?? 0,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return response()->json(['message' => 'Promo ditambahkan.', 'promo' => $promo]);
    }

    public function destroyPromo(Promo $promo)
    {
        $promo->delete();

        return response()->json(['message' => 'Promo dihapus.']);
    }

    // ---------- Setting ----------

    public function updateSettings(Request $request)
    {
        $data = $request->validate([
            'settings' => ['required', 'array'],
            'settings.*' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($data) {
            foreach ($data['settings'] as $key => $value) {
                Setting::updateOrCreate(['key' => $key], ['value' => $value]);
            }
        });

        return response()->json(['message' => 'Pengaturan disimpan.']);
    }
}
