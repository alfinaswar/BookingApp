@extends('layouts.app')

@section('content')
    @php
        $statusColor = match ($booking->status) {
            'pending' => 'bg-info',
            'confirmed' => 'bg-green',
            'checked_in' => 'bg-blue',
            'checked_out' => 'bg-dark',
            'cancelled' => 'bg-danger',
            'expired' => 'bg-secondary',
            default => 'bg-warning',
        };
    @endphp

    <div class="page-wrapper mb-3">
        <div class="page-header d-print-none">
            <div class="container-xl">
                <div class="row g-2 align-items-center">
                    <div class="col">
                        <div class="page-pretitle">Booking</div>
                        <h2 class="page-title">Detail Pesanan {{ $booking->kode_booking }}</h2>
                    </div>
                    <div class="col-auto ms-auto d-print-none">
                        @if ($whatsappUrl)
                            <a href="{{ $whatsappUrl }}" target="_blank" class="btn btn-success me-2">
                                <i class="fab fa-whatsapp me-1"></i> Hubungi Admin
                            </a>
                        @endif
                        <a href="{{ route('booking.index') }}" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i> Booking Baru
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="page-body">
            <div class="container-xl">
                @if (session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif

                @if ($booking->status === 'pending' && $booking->expires_at)
                    <div class="alert alert-warning">
                        <i class="fas fa-clock me-1"></i>
                        Selesaikan DP sebelum
                        <strong>{{ $booking->expires_at->format('d M Y H:i') }}</strong>.
                        Setelah itu unit dilepas otomatis.
                    </div>
                @endif

                <div class="row g-3">
                    <div class="col-lg-7">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Rincian Menginap</h3>
                                <div class="card-actions">
                                    <span class="badge {{ $statusColor }} text-white">{{ $booking->labelStatus() }}</span>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row g-3 mb-3">
                                    <div class="col-6">
                                        <div class="text-muted small">Check-in</div>
                                        <div class="h3 mb-0">{{ $booking->check_in_date?->format('d M Y') }}</div>
                                        <div class="text-muted small">mulai 14:00</div>
                                    </div>
                                    <div class="col-6">
                                        <div class="text-muted small">Check-out</div>
                                        <div class="h3 mb-0">{{ $booking->check_out_date?->format('d M Y') }}</div>
                                        <div class="text-muted small">maks 12:00</div>
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Unit</th>
                                                <th>Tipe</th>
                                                <th class="text-end">Subtotal</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($booking->units as $unit)
                                                <tr>
                                                    <td><strong>{{ $unit->unit->kode ?? '-' }}</strong></td>
                                                    <td>{{ $unit->room->roomtypes->nama ?? '-' }}</td>
                                                    <td class="text-end">Rp
                                                        {{ number_format($unit->subtotal, 0, ',', '.') }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>

                                @if ($booking->addons->count())
                                    <h4 class="mt-3">Paket Tambahan</h4>
                                    <ul class="list-unstyled">
                                        @foreach ($booking->addons as $addon)
                                            <li class="d-flex justify-content-between border-bottom py-1">
                                                <span>{{ $addon->nama_snapshot }} × {{ $addon->qty }}</span>
                                                <span>Rp {{ number_format($addon->subtotal, 0, ',', '.') }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        </div>

                        <div class="card mt-3">
                            <div class="card-header">
                                <h3 class="card-title">Riwayat</h3>
                            </div>
                            <div class="card-body">
                                <ul class="list-unstyled mb-0">
                                    @forelse ($booking->histories as $h)
                                        <li class="mb-2">
                                            <span class="text-muted small">{{ $h->created_at?->format('d/m/Y H:i') }}</span>
                                            — <strong>{{ $h->to_status }}</strong>
                                            @if ($h->note)
                                                <span class="text-muted">· {{ $h->note }}</span>
                                            @endif
                                        </li>
                                    @empty
                                        <li class="text-muted">Belum ada riwayat.</li>
                                    @endforelse
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-5">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Tagihan</h3>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between mb-1">
                                    <span>Subtotal</span>
                                    <span>Rp {{ number_format($booking->subtotal, 0, ',', '.') }}</span>
                                </div>
                                @if ($booking->addon_total > 0)
                                    <div class="d-flex justify-content-between mb-1">
                                        <span>Add-ons</span>
                                        <span>Rp {{ number_format($booking->addon_total, 0, ',', '.') }}</span>
                                    </div>
                                @endif
                                @if ($booking->discount_total > 0)
                                    <div class="d-flex justify-content-between mb-1 text-green">
                                        <span>Diskon</span>
                                        <span>- Rp {{ number_format($booking->discount_total, 0, ',', '.') }}</span>
                                    </div>
                                @endif
                                @if ($booking->tax_total + $booking->service_total > 0)
                                    <div class="d-flex justify-content-between mb-1">
                                        <span>Pajak &amp; Service</span>
                                        <span>Rp
                                            {{ number_format($booking->tax_total + $booking->service_total, 0, ',', '.') }}</span>
                                    </div>
                                @endif
                                <hr>
                                <div class="d-flex justify-content-between fw-bold fs-2">
                                    <span>Total</span>
                                    <span>Rp {{ number_format($booking->grand_total, 0, ',', '.') }}</span>
                                </div>
                                <div class="d-flex justify-content-between mt-2">
                                    <span>DP ({{ number_format($booking->dp_amount > 0 ? ($booking->dp_amount / max(1, $booking->grand_total)) * 100 : 0, 0) }}%)</span>
                                    <span class="fw-bold">Rp {{ number_format($booking->dp_amount, 0, ',', '.') }}</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Sudah dibayar</span>
                                    <span class="text-green">Rp
                                        {{ number_format($booking->paid_total, 0, ',', '.') }}</span>
                                </div>
                                <div class="d-flex justify-content-between fw-bold">
                                    <span>Sisa</span>
                                    <span class="{{ $booking->balance_due > 0 ? 'text-danger' : 'text-green' }}">
                                        Rp {{ number_format($booking->balance_due, 0, ',', '.') }}
                                    </span>
                                </div>

                                @if ($booking->refunded_total > 0)
                                    <div class="d-flex justify-content-between text-purple mt-1">
                                        <span>Sudah direfund</span>
                                        <span>Rp {{ number_format($booking->refunded_total, 0, ',', '.') }}</span>
                                    </div>
                                @endif

                                @php
                                    $pendingRefund = $booking->payments->first(
                                        fn($p) => $p->tipe === 'refund' && $p->status === 'pending'
                                    );
                                @endphp

                                @if ($pendingRefund)
                                    <div class="alert alert-info mt-3 mb-0">
                                        <i class="fas fa-undo me-1"></i>
                                        Refund <strong>Rp
                                            {{ number_format($pendingRefund->amount, 0, ',', '.') }}</strong>
                                        sedang diproses. Dana dikembalikan ke rekening Anda
                                        maksimal 7 hari kerja.
                                    </div>
                                @endif

                                @if ($booking->status === 'pending' && $invoice)
                                    <div class="mt-3 d-grid gap-2">
                                        <a href="{{ route('payment.pay', $booking->kode_booking) }}"
                                            class="btn btn-lg text-white" style="background-color:#1F573A;">
                                            Bayar DP Sekarang
                                        </a>
                                    </div>
                                @elseif ($booking->status === 'confirmed' && $booking->balance_due > 0)
                                    <div class="mt-3 d-grid gap-2">
                                        <a href="{{ route('payment.pay', $booking->kode_booking) }}"
                                            class="btn btn-lg btn-warning">
                                            Bayar Pelunasan
                                        </a>
                                    </div>
                                @elseif ($booking->status === 'checked_in' && $booking->balance_due > 0)
                                    <div class="mt-3 alert alert-warning mb-0">
                                        Pelunasan dilakukan saat check-out.
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="card mt-3">
                            <div class="card-header">
                                <h3 class="card-title">Pembayaran</h3>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-sm mb-0">
                                    <tbody>
                                        @forelse ($booking->payments as $p)
                                            <tr>
                                                <td>
                                                    <div class="small">{{ $p->kode_pembayaran }}</div>
                                                    <div class="text-muted small">{{ strtoupper($p->tipe) }}
                                                        · {{ str_replace('_', ' ', $p->metode) }}</div>
                                                </td>
                                                <td class="text-end">
                                                    <div>Rp {{ number_format($p->amount, 0, ',', '.') }}</div>
                                                    <span
                                                        class="badge {{ $p->status === 'verified' ? 'bg-green' : ($p->status === 'rejected' ? 'bg-danger' : 'bg-azure') }} text-white">
                                                        {{ $p->status }}
                                                    </span>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td class="text-muted">Belum ada pembayaran.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
