@extends('layouts.app')

@section('content')
    <div class="page-wrapper mb-3">
        <div class="page-header d-print-none">
            <div class="container-xl">
                <div class="page-pretitle">Pembayaran</div>
                <h2 class="page-title">{{ $invoice ? 'Bayar ' . strtoupper($invoice->tipe) : 'Pembayaran' }}
                    — {{ $booking->kode_booking }}</h2>
            </div>
        </div>

        <div class="page-body">
            <div class="container-xl">

                @if (!$invoice)
                    <div class="alert alert-success">
                        Tidak ada tagihan aktif. Total dibayar:
                        <strong>Rp {{ number_format($booking->paid_total, 0, ',', '.') }}</strong>.
                        <a href="{{ route('booking.status', $booking->kode_booking) }}">Kembali ke detail</a>
                    </div>
                @else
                    <div class="row g-3">
                        <div class="col-lg-7">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Transfer Manual</h3>
                                </div>
                                <div class="card-body">
                                    <p class="text-muted">Transfer sesuai nominal tepat, lalu unggah bukti.</p>

                                    <table class="table table-sm">
                                        <tr>
                                            <th width="180">Nominal</th>
                                            <td class="fw-bold fs-3 text-danger">Rp
                                                {{ number_format($invoice->amount, 0, ',', '.') }}</td>
                                        </tr>
                                        <tr>
                                            <th>Bank</th>
                                            <td>{{ $bankInfo['bank'] }}</td>
                                        </tr>
                                        <tr>
                                            <th>No. Rekening</th>
                                            <td class="fw-bold">{{ $bankInfo['norek'] }}
                                                <button class="btn btn-sm btn-outline-secondary ms-2" id="btnCopy"><i
                                                        class="fas fa-copy"></i></button>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Atas Nama</th>
                                            <td>{{ $bankInfo['atasnama'] }}</td>
                                        </tr>
                                        <tr>
                                            <th>Berita Transfer</th>
                                            <td><code>{{ $invoice->kode_pembayaran }}</code></td>
                                        </tr>
                                    </table>

                                    @if ($invoice->status === 'waiting_verification')
                                        <div class="alert alert-info mb-0">
                                            Bukti sudah diunggah. Menunggu verifikasi admin.
                                        </div>
                                    @else
                                        @if ($invoice->status === 'rejected')
                                            <div class="alert alert-danger">
                                                Bukti sebelumnya ditolak:
                                                {{ $invoice->rejected_reason ?? '-' }}. Silakan unggah ulang.
                                            </div>
                                        @endif

                                        <form action="{{ route('payment.upload', $booking->kode_booking) }}" method="POST"
                                            enctype="multipart/form-data">
                                            @csrf
                                            <input type="hidden" name="payment_id" value="{{ $invoice->id }}">
                                            <div class="mb-3">
                                                <label class="form-label required">Bukti Transfer</label>
                                                <input type="file" name="proof" class="form-control" required
                                                    accept="image/*,application/pdf">
                                                <small class="form-hint">JPG, PNG, atau PDF. Maksimal 4 MB.</small>
                                                @error('proof')
                                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                                @enderror
                                            </div>
                                            <button class="btn text-white" style="background-color:#1F573A;">
                                                Kirim Bukti
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-5">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Ringkasan</h3>
                                </div>
                                <div class="card-body">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span>Total</span>
                                        <span>Rp {{ number_format($booking->grand_total, 0, ',', '.') }}</span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-1">
                                        <span>Dibayar</span>
                                        <span class="text-green">Rp
                                            {{ number_format($booking->paid_total, 0, ',', '.') }}</span>
                                    </div>
                                    <div class="d-flex justify-content-between fw-bold">
                                        <span>Sisa</span>
                                        <span class="text-danger">Rp
                                            {{ number_format($booking->balance_due, 0, ',', '.') }}</span>
                                    </div>

                                    @if ($onlineAvailable)
                                        <hr>
                                        <button class="btn btn-outline-primary w-100" id="btnMidtrans">
                                            Bayar Online (Kartu / VA / QRIS)
                                        </button>
                                    @else
                                        <hr>
                                        <div class="alert alert-secondary mb-0 small">
                                            Pembayaran online belum aktif di server ini.
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    @if ($invoice)
        <script>
            $('#btnCopy').on('click', function() {
            navigator.clipboard.writeText("{{ $bankInfo['norek'] }}");
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'Nomor rekening disalin',
                showConfirmButton: false,
                timer: 1500
            });
        });

        $('#btnMidtrans').on('click', function() {
            $.post("{{ route('payment.snap') }}", {
                _token: "{{ csrf_token() }}",
                payment_id: "{{ $invoice->id }}"
            }).done(function(res) {
                if (res.redirect_url) {
                    window.location.href = res.redirect_url;
                    return;
                }

                if (res.snap_url && res.token) {
                    $.getScript(res.snap_url).done(function() {
                        snap.pay(res.token, {
                            onSuccess: function() {
                                window.location.href = "{{ route('booking.status', $booking->kode_booking) }}";
                            },
                            onPending: function() {
                                window.location.reload();
                            },
                            onError: function() {
                                Swal.fire('Gagal', 'Pembayaran gagal diproses.', 'error');
                            }
                        });
                    });
                }
            }).fail(function(xhr) {
                Swal.fire('Tidak tersedia', xhr.responseJSON?.message || 'Gateway belum siap.', 'warning');
            });
        });
        </script>
    @endif
@endpush
