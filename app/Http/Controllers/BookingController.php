<?php

namespace App\Http\Controllers;

use App\Exceptions\BookingException;
use App\Models\Addon;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Promo;
use App\Models\Room;
use App\Models\Roomtype;
use App\Services\AvailabilityService;
use App\Services\BookingService;
use App\Services\Payment\PaymentGatewayFactory;
use App\Services\PaymentService;
use App\Services\PricingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;

class BookingController extends Controller
{
    public function __construct(
        private readonly BookingService $bookings,
        private readonly AvailabilityService $availability,
        private readonly PricingService $pricing,
        private readonly PaymentService $payments,
        private readonly PaymentGatewayFactory $gateways,
    ) {
    }

    public function loginuser(Request $request)
    {
        $wa = \App\Models\Whatsapp::latest()->first();

        return view('Auth.loginUser', compact('wa'));
    }

    /**
     * Halaman Booking: matriks ketersediaan unit × tanggal.
     */
    public function index(Request $request)
    {
        $checkIn = $request->get('from', now()->toDateString());
        $checkOut = $request->get('to', Carbon::parse($checkIn)->addDay()->toDateString());

        // Kalender selalu 2 minggu ke depan dari hari ini — tidak dikunci
        // oleh tanggal check-in/check-out. Rentang menginap hanya menyorot sel.
        $windowStart = now()->startOfDay();
        $windowEnd = $windowStart->copy()->addDays(14);

        // Amankan bila tanggal terpilih berada di luar 3 minggu tersebut.
        foreach ([$checkIn, $checkOut] as $d) {
            $parsed = Carbon::parse($d)->startOfDay();
            if ($parsed->lessThan($windowStart)) {
                $windowStart = $parsed->copy();
            }
            if ($parsed->greaterThan($windowEnd)) {
                $windowEnd = $parsed->copy();
            }
        }

        $calendar = $this->availability->calendar(
            $windowStart->toDateString(),
            $windowEnd->toDateString(),
            $request->integer('room_id') ?: null
        );

        $types = Room::with('roomtypes')->active()->orderBy('sort_order')->orderBy('id')->get();

        return view('Booking.index', [
            'calendar' => $calendar,
            'checkIn' => $checkIn,
            'checkOut' => $checkOut,
            'types' => $types,
            'addons' => Addon::active()->orderBy('sort_order')->get(),
            'onlineAvailable' => $this->gateways->onlineAvailable(),
            'dpPercent' => \App\Models\Setting::getFloat('dp_percent', 30),
        ]);
    }

    /**
     * Data JSON matriks (dipakai saat ganti rentang tanggal).
     */
    public function kalender(Request $request)
    {
        $from = $request->get('from', now()->toDateString());
        $to = $request->get('to', now()->addDays(14)->toDateString());

        return response()->json(
            $this->availability->calendar($from, $to, $request->integer('room_id') ?: null)
        );
    }

    /**
     * Daftar unit yang tersedia pada rentang tanggal (JSON).
     * Dipakai untuk pratinjau cepat / integrasi eksternal.
     */
    public function ketersediaan(Request $request)
    {
        $request->validate([
            'check_in' => ['required', 'date_format:Y-m-d'],
            'check_out' => ['required', 'date_format:Y-m-d', 'after:check_in'],
        ]);

        $units = $this->availability->availableUnits(
            $request->get('check_in'),
            $request->get('check_out'),
            $request->integer('room_id') ?: null,
        );

        return response()->json([
            'check_in' => $request->get('check_in'),
            'check_out' => $request->get('check_out'),
            'total' => $units->count(),
            'units' => $units->map(fn ($unit) => [
                'unit_id' => $unit->id,
                'kode' => $unit->kode,
                'nama' => $unit->label(),
                'tipe' => $unit->room->roomtypes->nama ?? null,
                'room_id' => $unit->room_id,
                'kapasitas_max' => (int) ($unit->room->kapasitas_max ?? 0),
                'tarif_weekday' => $unit->room->baseRate(false),
                'tarif_weekend' => $unit->room->baseRate(true),
            ])->values(),
        ]);
    }

    /**
     * Quote harga — satu-satunya sumber angka di UI.
     */
    public function quote(\App\Http\Requests\QuoteRequest $request)
    {
        $validated = $request->validated();

        $promo = null;
        if (! empty($validated['promo_kode'])) {
            $promo = Promo::where('kode', $validated['promo_kode'])->first();
            if ($promo && ! $promo->isUsable(0)) {
                $promo = null;
            }
        }

        $quote = $this->pricing->quoteForUnits(
            $validated['room_unit_ids'],
            $validated['check_in'],
            $validated['check_out'],
            $validated['addons'] ?? [],
            ['dewasa' => $validated['dewasa'] ?? 1, 'anak' => $validated['anak'] ?? 0],
            $promo,
        );

        $conflicts = $this->availability->conflictingUnits(
            $validated['room_unit_ids'],
            $validated['check_in'],
            $validated['check_out']
        );

        $quote['conflicts'] = $conflicts;
        $quote['kode_promo_valid'] = $promo !== null;

        return response()->json($quote);
    }

    /**
     * Simpan booking (online & walk-in memakai jalur yang sama).
     */
    public function store(\App\Http\Requests\StoreBookingRequest $request)
    {
        try {
            $booking = $this->bookings->create($request->payload(), auth()->user());
        } catch (BookingException $e) {
            return $this->bookingError($e);
        }

        $metode = $request->input('metode', Payment::METODE_TRANSFER);
        $payment = $metode === Payment::METODE_CASH
            ? $this->payments->recordCash($booking->fresh(), (float) $booking->dp_amount, Payment::TIPE_DP, auth()->user())
            : $this->payments->createDpInvoice($booking->fresh(), $metode);

        // DP invoice untuk pembayaran cash sudah langsung verified;
        // untuk transfer/gateway statusnya pending.
        $invoice = $payment instanceof Payment && $payment->booking_id
            ? $payment
            : $booking->fresh()->payments()->latest('id')->first();

        return response()->json([
            'success' => true,
            'message' => 'Booking ' . $booking->kode_booking . ' berhasil dibuat. Silakan selesaikan DP.',
            'booking' => $this->bookingPayload($booking->fresh()),
            'payment' => $invoice,
            'redirect' => route('booking.status', $booking->kode_booking),
        ], 201);
    }

    /**
     * Alias lama untuk form publik (isOnline = 1).
     */
    public function onlinestore(\App\Http\Requests\StoreBookingRequest $request)
    {
        $payload = $request->payload();
        $payload['source'] = Booking::SOURCE_ONLINE;

        try {
            $booking = $this->bookings->create($payload, auth()->user());
        } catch (BookingException $e) {
            return $this->bookingError($e);
        }

        $payment = $this->payments->createDpInvoice($booking->fresh(), $request->input('metode', Payment::METODE_TRANSFER));

        return response()->json([
            'success' => true,
            'message' => 'Booking ' . $booking->kode_booking . ' berhasil dibuat.',
            'booking' => $this->bookingPayload($booking->fresh()),
            'payment' => $payment,
            'redirect' => route('booking.status', $booking->kode_booking),
        ], 201);
    }

    /**
     * Halaman pembayaran DP / pelunasan untuk satu booking.
     */
    public function status(string $kode)
    {
        $booking = Booking::with(['units.unit', 'units.room.roomtypes', 'addons', 'payments', 'histories'])
            ->where('kode_booking', $kode)
            ->firstOrFail();

        $activeInvoice = $booking->payments
            ->whereIn('status', [Payment::STATUS_PENDING, Payment::STATUS_WAITING, Payment::STATUS_REJECTED])
            ->sortByDesc('id')
            ->first();

        return view('Booking.status', [
            'booking' => $booking,
            'invoice' => $activeInvoice,
            'onlineAvailable' => $this->gateways->onlineAvailable(),
            'whatsappUrl' => app(\App\Services\Notifications\BookingNotifier::class)
                ->guestWhatsAppUrl($booking),
        ]);
    }

    /**
     * List Booking admin.
     */
    public function listBooking(Request $request)
    {
        if ($request->ajax()) {
            $query = Booking::with(['units.unit', 'units.room.roomtypes', 'payments'])
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->get('status')))
                ->when($request->filled('payment_status'), fn ($q) => $q->where('payment_status', $request->get('payment_status')))
                ->when($request->filled('room_id'), fn ($q) => $q->whereHas('units', fn ($u) => $u->where('room_id', $request->get('room_id'))))
                ->when($request->filled('source'), fn ($q) => $q->where('source', $request->get('source')))
                ->when($request->filled('date_from') && $request->filled('date_to'), function ($q) use ($request) {
                    $q->overlapping($request->get('date_from'), $request->get('date_to'));
                })
                ->when($request->filled('q'), function ($q) use ($request) {
                    $term = '%' . $request->get('q') . '%';
                    $q->where(function ($sub) use ($term) {
                        $sub->where('kode_booking', 'like', $term)
                            ->orWhere('NamaBooking', 'like', $term)
                            ->orWhere('hp', 'like', $term)
                            ->orWhere('Email', 'like', $term);
                    });
                })
                ->latest('id');

            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('kode', fn (Booking $row) => '<span class="fw-bold">' . e($row->kode_booking) . '</span><br><small class="text-muted">' . e($row->created_at?->format('d/m/Y H:i')) . '</small>')
                ->addColumn('tamu', function (Booking $row) {
                    return e($row->NamaBooking) . '<br><small class="text-muted">' . e($row->hp) . '</small>';
                })
                ->addColumn('unit', function (Booking $row) {
                    $kodes = $row->units->map(fn ($u) => '<span class="badge bg-blue-lt">' . e($u->unit->kode ?? '-') . '</span>')->implode(' ');

                    return $kodes ?: '-';
                })
                ->addColumn('masa', function (Booking $row) {
                    $in = $row->check_in_date?->format('d/m/Y') ?? '-';
                    $out = $row->check_out_date?->format('d/m/Y') ?? '-';

                    return $in . ' &rarr; ' . $out . '<br><small class="text-muted">' . $row->malam . ' malam · ' . $row->jumlah_unit . ' unit</small>';
                })
                ->addColumn('tagihan', function (Booking $row) {
                    return 'Rp ' . number_format((float) $row->grand_total, 0, ',', '.')
                        . '<br><small class="text-muted">Sisa: Rp ' . number_format((float) $row->balance_due, 0, ',', '.') . '</small>';
                })
                ->addColumn('status_badge', fn (Booking $row) => $this->statusBadge($row->status))
                ->addColumn('payment_badge', function (Booking $row) {
                    $pending = $row->payments->where('status', Payment::STATUS_WAITING)->count();
                    $badge = match ($row->payment_status) {
                        Booking::PAYMENT_PAID => '<span class="badge bg-green text-white">Lunas</span>',
                        Booking::PAYMENT_PARTIAL => '<span class="badge bg-yellow text-white">DP</span>',
                        Booking::PAYMENT_REFUNDED => '<span class="badge bg-purple text-white">Refund</span>',
                        default => '<span class="badge bg-secondary text-white">Belum Bayar</span>',
                    };

                    return $badge . ($pending > 0 ? '<br><small class="text-danger">' . $pending . ' bukti menunggu</small>' : '');
                })
                ->addColumn('action', fn (Booking $row) => $this->actionButtons($row))
                ->rawColumns(['kode', 'tamu', 'unit', 'masa', 'tagihan', 'status_badge', 'payment_badge', 'action'])
                ->make(true);
        }

        return view('Booking.list-booking', [
            'types' => Room::with('roomtypes')->active()->orderBy('id')->get(),
        ]);
    }

    public function show(string $id)
    {
        $booking = Booking::with(['units.unit', 'units.room.roomtypes', 'addons', 'payments', 'histories.actor'])
            ->findOrFail($id);

        return response()->json($this->bookingPayload($booking) + [
            'histories' => $booking->histories->map(fn ($h) => [
                'at' => $h->created_at?->format('d/m/Y H:i'),
                'status' => $h->to_status,
                'note' => $h->note,
                'actor' => $h->actor->name ?? 'Sistem',
            ]),
            'payments' => $booking->payments->map(fn ($p) => [
                'id' => $p->id,
                'kode' => $p->kode_pembayaran,
                'tipe' => $p->tipe,
                'metode' => $p->metode,
                'amount' => $p->amount,
                'status' => $p->status,
                'proof_url' => $p->proof_path ? route('payment.proof', $p->id) : null,
                'rejected_reason' => $p->rejected_reason,
            ]),
        ]);
    }

    public function konfirmasi(Request $request, $id)
    {
        $booking = Booking::findOrFail($id);

        try {
            $this->bookings->confirm($booking, auth()->user());
        } catch (BookingException $e) {
            return response()->json(['message' => $e->getMessage()], $e->statusCode);
        }

        return response()->json(['message' => 'Booking dikonfirmasi']);
    }

    public function checkin(Request $request, $id)
    {
        $booking = Booking::findOrFail($id);

        try {
            $this->bookings->checkIn($booking, auth()->user());
        } catch (BookingException $e) {
            // Sertakan sisa tagihan agar admin bisa langsung mencatat pelunasan.
            return response()->json([
                'message' => $e->getMessage(),
                'success' => false,
                'balance_due' => (float) $booking->fresh()->balance_due,
            ] + $e->context, $e->statusCode);
        }

        return response()->json(['message' => 'Check-in berhasil']);
    }

    public function checkout(Request $request)
    {
        $booking = Booking::findOrFail($request->integer('id'));

        try {
            $this->bookings->checkOut($booking, auth()->user(), $request->boolean('override'));
        } catch (BookingException $e) {
            return response()->json(['message' => $e->getMessage(), 'success' => false], $e->statusCode);
        }

        $this->syncLegacyRoomStatus($booking);

        return response()->json(['message' => 'Check-out berhasil', 'success' => true]);
    }

    public function batal(Request $request, $id)
    {
        $booking = Booking::findOrFail($id);

        try {
            $cancelled = $this->bookings->cancel($booking, auth()->user(), (string) $request->input('reason', ''));
        } catch (BookingException $e) {
            return response()->json(['message' => $e->getMessage()], $e->statusCode);
        }

        return response()->json([
            'message' => 'Booking dibatalkan',
            'refund_amount' => (float) $cancelled->payments()
                ->where('tipe', Payment::TIPE_REFUND)
                ->where('status', Payment::STATUS_PENDING)
                ->sum('amount'),
        ]);
    }

    public function getBukti($id)
    {
        $payment = Payment::findOrFail($id);

        return response()->json([
            'id' => $payment->id,
            'kode' => $payment->kode_pembayaran,
            'amount' => $payment->amount,
            'status' => $payment->status,
            'proof_url' => route('payment.proof', $payment->id),
            'booking' => optional($payment->booking)->kode_booking,
        ]);
    }

    public function update(Request $request, $id)
    {
        // Endpoint upload bukti versi lama (client/payment). Diarahkan ke halaman bayar baru.
        $booking = Booking::find($id);

        if (! $booking) {
            return response()->json(['message' => 'Booking tidak ditemukan.'], 404);
        }

        return response()->json([
            'message' => 'Unggah bukti kini melalui halaman pembayaran.',
            'redirect' => route('payment.pay', $booking->kode_booking),
        ], 200);
    }

    public function BookingOnline($kode)
    {
        return redirect()->route('booking.status', $kode);
    }

    /**
     * Halaman "My Booking" untuk tamu yang login.
     */
    public function myBookings()
    {
        $bookings = Booking::with(['units.unit', 'units.room.roomtypes', 'payments'])
            ->where('userId', auth()->id())
            ->latest('id')
            ->get();

        return view('client.payment', [
            'bookings' => $bookings,
            'onlineAvailable' => $this->gateways->onlineAvailable(),
        ]);
    }

    public function create($id)
    {
        return redirect()->route('booking.index', ['room_id' => $id]);
    }

    public function edit($id)
    {
        return redirect()->route('booking.status', Booking::findOrFail($id)->kode_booking);
    }

    /**
     * Hard delete sengaja tidak disediakan.
     * Gunakan BookingService::cancel() lewat route booking.batal.
     */
    public function destroy(Booking $booking)
    {
        abort(405, 'Pembatalan booking harus melalui tombol Batalkan.');
    }

    private function bookingError(BookingException $e)
    {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ] + $e->context, $e->statusCode);
    }

    private function bookingPayload(Booking $booking): array
    {
        return [
            'id' => $booking->id,
            'kode_booking' => $booking->kode_booking,
            'status' => $booking->status,
            'payment_status' => $booking->payment_status,
            'check_in' => $booking->check_in_date?->toDateString(),
            'check_out' => $booking->check_out_date?->toDateString(),
            'malam' => $booking->malam,
            'grand_total' => (float) $booking->grand_total,
            'dp_amount' => (float) $booking->dp_amount,
            'paid_total' => (float) $booking->paid_total,
            'balance_due' => (float) $booking->balance_due,
            'units' => $booking->units->map(fn ($u) => [
                'kode' => $u->unit->kode ?? null,
                'tipe' => $u->room->roomtypes->nama ?? null,
                'subtotal' => (float) $u->subtotal,
            ]),
        ];
    }

    private function statusBadge(string $status): string
    {
        return match ($status) {
            Booking::STATUS_PENDING => '<span class="badge bg-info text-white">Menunggu DP</span>',
            Booking::STATUS_CONFIRMED => '<span class="badge bg-green text-white">Terkonfirmasi</span>',
            Booking::STATUS_CHECKED_IN => '<span class="badge bg-blue text-white">Check-in</span>',
            Booking::STATUS_CHECKED_OUT => '<span class="badge bg-dark text-white">Check-out</span>',
            Booking::STATUS_CANCELLED => '<span class="badge bg-danger text-white">Dibatalkan</span>',
            Booking::STATUS_EXPIRED => '<span class="badge bg-secondary text-white">Kedaluwarsa</span>',
            default => '<span class="badge bg-warning text-white">No Show</span>',
        };
    }

    private function actionButtons(Booking $row): string
    {
        $buttons = [
            '<a href="' . route('booking.status', $row->kode_booking) . '" class="btn btn-sm btn-outline-primary" title="Detail"><i class="fas fa-eye"></i></a>',
        ];

        $dataBtn = fn (string $style, string $handler, string $icon, string $title) => '<a href="javascript:void(0)" data-id="' . $row->id
            . '" class="btn btn-sm ' . $style . ' ' . $handler . '" title="' . $title . '"><i class="' . $icon . '"></i></a>';

        // Bukti bayar menunggu verifikasi: tampilkan aksi verifikasi lebih dulu.
        $waiting = $row->payments->firstWhere('status', Payment::STATUS_WAITING);

        if ($waiting) {
            $buttons[] = '<a href="javascript:void(0)" data-payment-id="' . $waiting->id
                . '" class="btn btn-sm btn-warning btn-verifikasi" title="Verifikasi bukti bayar">'
                . '<i class="fas fa-receipt"></i></a>';
        }

        if ($row->status === Booking::STATUS_PENDING) {
            if ((float) $row->paid_total >= (float) $row->dp_amount && (float) $row->dp_amount > 0) {
                $buttons[] = $dataBtn('btn-success', 'btn-konfirmasi-dp', 'fas fa-check', 'Konfirmasi DP');
            }
            $buttons[] = $dataBtn('btn-outline-danger', 'btn-batal', 'fas fa-times', 'Batalkan');
        }

        if ($row->status === Booking::STATUS_CONFIRMED) {
            $buttons[] = $dataBtn('btn-primary', 'btn-checkin', 'fas fa-door-open', 'Check-in');
            $buttons[] = $dataBtn('btn-outline-danger', 'btn-batal', 'fas fa-times', 'Batalkan');
        }

        if ($row->status === Booking::STATUS_CHECKED_IN) {
            $buttons[] = $dataBtn('btn-dark', 'btn-checkout', 'fas fa-door-closed', 'Check-out');
        }

        if (in_array($row->status, [Booking::STATUS_CONFIRMED, Booking::STATUS_CHECKED_IN], true)
            && (float) $row->balance_due > 0) {
            $buttons[] = '<a href="' . route('payment.pay', $row->kode_booking)
                . '" class="btn btn-sm btn-outline-success" title="Catat pelunasan">'
                . '<i class="fas fa-money-bill-wave"></i></a>';
        }

        // Refund yang belum ditransfer ke tamu.
        $refund = $row->payments->first(fn ($p) => $p->tipe === Payment::TIPE_REFUND && $p->status === Payment::STATUS_PENDING);

        if ($refund) {
            $buttons[] = '<a href="javascript:void(0)" data-payment-id="' . $refund->id
                . '" class="btn btn-sm btn-outline-purple btn-refund" title="Selesaikan refund Rp '
                . number_format($refund->amount, 0, ',', '.') . '">'
                . '<i class="fas fa-undo"></i></a>';
        }

        return implode(' ', $buttons);
    }

    private function syncLegacyRoomStatus(Booking $booking): void
    {
        // Kolom rooms.status lama tidak lagi dipakai sebagai kunci inventaris.
        // Dibiarkan untuk kompatibilitas tampilan Master Room.
        Room::whereIn('id', $booking->units->pluck('room_id'))->update(['status' => (string) $booking->status]);
    }
}