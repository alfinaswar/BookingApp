@extends('layouts.app')

@section('content')
    <div class="page-wrapper mb-3">
        <div class="page-header d-print-none">
            <div class="container-xl">
                <div class="page-pretitle">Akun</div>
                <h2 class="page-title">Booking Saya</h2>
            </div>
        </div>

        <div class="page-body">
            <div class="container-xl">
                @forelse ($bookings as $booking)
                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-muted small">{{ $booking->kode_booking }}</div>
                                    <h3 class="mb-1">
                                        {{ $booking->units->map(fn($u) => $u->unit->kode ?? '-')->implode(', ') }}
                                        <span
                                            class="badge bg-azure-lt">{{ $booking->units->first()->room->roomtypes->nama ?? '-' }}</span>
                                    </h3>
                                    <div class="text-muted">
                                        {{ $booking->check_in_date?->format('d M Y') }} →
                                        {{ $booking->check_out_date?->format('d M Y') }}
                                        · {{ $booking->malam }} malam
                                    </div>
                                </div>
                                <div class="col-auto text-end">
                                    <div class="h2 mb-1">Rp {{ number_format($booking->grand_total, 0, ',', '.') }}</div>
                                    <span
                                        class="badge {{ $booking->status === 'cancelled' ? 'bg-danger' : ($booking->status === 'checked_out' ? 'bg-dark' : 'bg-green') }} text-white">
                                        {{ $booking->labelStatus() }}
                                    </span>
                                    <span class="badge bg-azure text-white">{{ $booking->labelPayment() }}</span>
                                    <div class="mt-2">
                                        <a href="{{ route('booking.status', $booking->kode_booking) }}"
                                            class="btn btn-sm btn-outline-primary">Detail</a>
                                        @if ($booking->balance_due > 0 && in_array($booking->status, ['pending', 'confirmed']))
                                            <a href="{{ route('payment.pay', $booking->kode_booking) }}"
                                                class="btn btn-sm btn-success">Bayar</a>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-bed fa-3x text-muted mb-3"></i>
                            <h3>Belum ada booking</h3>
                            <p class="text-muted">Mulai pesan unit pertama Anda.</p>
                            <a href="{{ route('booking.index') }}" class="btn text-white"
                                style="background-color:#1F573A;">Pesan Sekarang</a>
                        </div>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
@endsection
