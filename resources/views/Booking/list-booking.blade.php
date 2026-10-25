@extends('layouts.app')

@section('content')
    <div class="page-wrapper mb-3">
        <div class="page-header d-print-none">
            <div class="container-xl">
                <div class="row g-2 align-items-center">
                    <div class="col">
                        <div class="page-pretitle">Operasional</div>
                        <h2 class="page-title">List Booking</h2>
                    </div>
                </div>
            </div>
        </div>

        <div class="page-body">
            <div class="container-xl">
                {{-- KPI ringkas --}}
                <div class="row row-deck row-cards mb-3" id="kpiRow">
                    <div class="col-sm-6 col-lg-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="subheader">Arrival Hari Ini</div>
                                <div class="h1 mb-0" id="kpiArrival">-</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="subheader">In-house</div>
                                <div class="h1 mb-0" id="kpiInhouse">-</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="subheader">Depature Hari Ini</div>
                                <div class="h1 mb-0" id="kpiDeparture">-</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="subheader">Menunggu Verifikasi</div>
                                <div class="h1 mb-0 text-orange" id="kpiPending">-</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header" style="background-color:#1F573A;">
                        <h3 class="card-title text-white">Daftar Pemesanan</h3>
                    </div>
                    <div class="card-body">
                        {{-- Filter --}}
                        <div class="row g-2 mb-3">
                            <div class="col-md-3">
                                <input type="text" id="fQ" class="form-control"
                                    placeholder="Cari kode / nama / HP / email">
                            </div>
                            <div class="col-md-2">
                                <select id="fStatus" class="form-select">
                                    <option value="">Semua Status</option>
                                    <option value="pending">Menunggu DP</option>
                                    <option value="confirmed">Terkonfirmasi</option>
                                    <option value="checked_in">Check-in</option>
                                    <option value="checked_out">Check-out</option>
                                    <option value="cancelled">Dibatalkan</option>
                                    <option value="expired">Kedaluwarsa</option>
                                    <option value="no_show">No Show</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select id="fPayment" class="form-select">
                                    <option value="">Semua Pembayaran</option>
                                    <option value="unpaid">Belum Bayar</option>
                                    <option value="partial">DP Terbayar</option>
                                    <option value="paid">Lunas</option>
                                    <option value="refunded">Refund</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select id="fTipe" class="form-select">
                                    <option value="">Semua Tipe</option>
                                    @foreach ($types as $type)
                                        <option value="{{ $type->id }}">{{ $type->nama }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <input type="date" id="fFrom" class="form-control" title="Check-in dari">
                            </div>
                            <div class="col-md-1">
                                <button class="btn btn-outline-secondary w-100" id="btnReset"
                                    title="Reset filter"><i class="fas fa-undo"></i></button>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-vcenter card-table" id="tableBooking" width="100%">
                                <thead>
                                    <tr>
                                        <th width="40">#</th>
                                        <th>Kode</th>
                                        <th>Tamu</th>
                                        <th>Unit</th>
                                        <th>Masa Inap</th>
                                        <th>Tagihan</th>
                                        <th>Status</th>
                                        <th>Pembayaran</th>
                                        <th width="160" class="text-end">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal detail booking --}}
    <div class="modal modal-blur fade" id="modalDetail" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
            <div class="modal-content" id="detailContent">
                <div class="modal-header">
                    <h5 class="modal-title">Detail Booking</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="detailBody">
                    <div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x"></i></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal verifikasi pembayaran --}}
    <div class="modal modal-blur fade" id="modalBayar" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Verifikasi Pembayaran</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="bayarInfo" class="mb-2"></div>
                    <img id="bayarImg" src="" alt="Bukti" class="img-fluid rounded border d-none">
                    <div id="bayarEmpty" class="alert alert-warning d-none">Belum ada bukti diunggah.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn me-auto" data-bs-dismiss="modal">Tutup</button>
                    <button type="button" class="btn btn-danger" id="btnTolak">Tolak</button>
                    <button type="button" class="btn btn-success" id="btnVerifikasi">Setujui</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        let currentPaymentId = null;

        const dt = $('#tableBooking').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: "{{ route('booking.listBooking') }}",
                data: function(d) {
                    d.q = $('#fQ').val();
                    d.status = $('#fStatus').val();
                    d.payment_status = $('#fPayment').val();
                    d.room_id = $('#fTipe').val();
                    d.date_from = $('#fFrom').val();
                    if ($('#fFrom').val()) {
                        d.date_to = $('#fFrom').val();
                    }
                }
            },
            order: [],
            columns: [{
                    data: 'DT_RowIndex',
                    orderable: false,
                    searchable: false
                },
                {
                    data: 'kode'
                },
                {
                    data: 'tamu'
                },
                {
                    data: 'unit'
                },
                {
                    data: 'masa'
                },
                {
                    data: 'tagihan'
                },
                {
                    data: 'status_badge'
                },
                {
                    data: 'payment_badge'
                },
                {
                    data: 'action',
                    orderable: false,
                    searchable: false,
                    className: 'text-end'
                },
            ],
            pageLength: 25,
            language: {
                processing: '<i class="fa fa-spinner fa-spin fa-2x"></i>',
                search: 'Cari:',
                lengthMenu: 'Tampil _MENU_ baris',
                info: 'Menampilkan _START_–_END_ dari _TOTAL_',
                zeroRecords: 'Tidak ada data',
            }
        });

        $('#fQ').on('keyup', function() {
            dt.draw();
        });
        $('#fStatus, #fPayment, #fTipe, #fFrom').on('change', function() {
            dt.draw();
        });
        $('#btnReset').on('click', function() {
            $('#fQ, #fStatus, #fPayment, #fTipe, #fFrom').val('');
            dt.draw();
        });

        function reload() {
            dt.ajax.reload(null, false);
        }

        function toast(icon, title) {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: icon,
                title: title,
                showConfirmButton: false,
                timer: 2500
            });
        }

        // Konfirmasi DP
        $(document).on('click', '.btn-konfirmasi-dp', function() {
            const id = $(this).data('id');
            Swal.fire({
                title: 'Konfirmasi booking ini?',
                text: 'Pastikan DP sudah diterima.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Ya, konfirmasi'
            }).then(function(r) {
                if (!r.isConfirmed) return;
                $.post("{{ url('booking/konfirmasi') }}/" + id, {
                    _token: "{{ csrf_token() }}"
                }).done(function() {
                    toast('success', 'Booking dikonfirmasi');
                    reload();
                }).fail(function(xhr) {
                    toast('error', xhr.responseJSON?.message || 'Gagal');
                });
            });
        });

        // Check-in
        $(document).on('click', '.btn-checkin', function() {
            const id = $(this).data('id');
            Swal.fire({
                title: 'Proses check-in?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Check-in'
            }).then(function(r) {
                if (!r.isConfirmed) return;
                $.post("{{ url('booking/checkin') }}/" + id, {
                    _token: "{{ csrf_token() }}"
                }).done(function() {
                    toast('success', 'Check-in berhasil');
                    reload();
                }).fail(function(xhr) {
                    toast('error', xhr.responseJSON?.message || 'Gagal');
                });
            });
        });

        // Check-out
        $(document).on('click', '.btn-checkout', function() {
            const id = $(this).data('id');
            Swal.fire({
                title: 'Proses check-out?',
                text: 'Pastikan seluruh tagihan sudah lunas.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Check-out'
            }).then(function(r) {
                if (!r.isConfirmed) return;
                $.post("{{ route('booking.checkout') }}", {
                        _token: "{{ csrf_token() }}",
                        id: id
                    })
                    .done(function() {
                        toast('success', 'Check-out berhasil');
                        reload();
                    })
                    .fail(function(xhr) {
                        const msg = xhr.responseJSON?.message || 'Check-out gagal.';
                        Swal.fire({
                            icon: 'error',
                            title: 'Tidak bisa check-out',
                            text: msg,
                            showCancelButton: true,
                            confirmButtonText: 'Override (supervisor)',
                            cancelButtonText: 'Tutup'
                        }).then(function(res) {
                            if (!res.isConfirmed) return;
                            $.post("{{ route('booking.checkout') }}", {
                                _token: "{{ csrf_token() }}",
                                id: id,
                                override: 1
                            }).done(function() {
                                toast('success', 'Check-out dengan override dicatat');
                                reload();
                            }).fail(function(e2) {
                                toast('error', e2.responseJSON?.message || 'Gagal');
                            });
                        });
                    });
            });
        });

        // Batalkan
        $(document).on('click', '.btn-batal', function() {
            const id = $(this).data('id');
            Swal.fire({
                title: 'Batalkan booking?',
                input: 'text',
                inputLabel: 'Alasan pembatalan',
                inputPlaceholder: 'Contoh: tamu membatalkan via telepon',
                showCancelButton: true,
                confirmButtonText: 'Batalkan booking',
                confirmButtonColor: '#d33',
                inputValidator: (v) => !v && 'Alasan wajib diisi'
            }).then(function(r) {
                if (!r.isConfirmed) return;
                $.post("{{ url('booking/batal') }}/" + id, {
                    _token: "{{ csrf_token() }}",
                    reason: r.value
                }).done(function(res) {
                    if (res.refund_amount > 0) {
                        Swal.fire({
                            icon: 'info',
                            title: 'Booking dibatalkan',
                            html: 'Refund <strong>Rp ' + Number(res.refund_amount).toLocaleString('id-ID') +
                                '</strong> dicatat dan menunggu diproses. Gunakan tombol refund di baris ini setelah transfer dilakukan.',
                        });
                    } else {
                        toast('success', 'Booking dibatalkan');
                    }
                    reload();
                }).fail(function(xhr) {
                    toast('error', xhr.responseJSON?.message || 'Gagal');
                });
            });
        });

        // Selesaikan refund
        $(document).on('click', '.btn-refund', function() {
            const paymentId = $(this).data('payment-id');
            Swal.fire({
                title: 'Selesaikan refund?',
                html: 'Konfirmasi bahwa dana <strong>sudah ditransfer</strong> ke rekening tamu. ' +
                    'Tindakan ini mengurangi kas dan tidak bisa dibatalkan.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Ya, refund sudah ditransfer',
                cancelButtonText: 'Belum'
            }).then(function(r) {
                if (!r.isConfirmed) return;
                $.post("{{ url('payments') }}/" + paymentId + "/verify", {
                    _token: "{{ csrf_token() }}"
                }).done(function() {
                    toast('success', 'Refund dicatat sebagai selesai');
                    reload();
                }).fail(function(xhr) {
                    toast('error', xhr.responseJSON?.message || 'Gagal');
                });
            });
        });

        // Detail
        $(document).on('click', '.btn-detail', function() {
            const id = $(this).data('id');
            $('#detailBody').html('<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x"></i></div>');
            $('#modalDetail').modal('show');

            $.get("{{ url('booking/detail') }}/" + id, function(d) {
                let units = (d.units || []).map(u =>
                    `<li><strong>${u.kode ?? '-'}</strong> · ${u.tipe ?? '-'} · Rp ${Number(u.subtotal).toLocaleString('id-ID')}</li>`
                ).join('');

                let pays = (d.payments || []).map(p => `
                    <tr>
                        <td>${p.kode}</td>
                        <td>${p.tipe}</td>
                        <td>${p.metode}</td>
                        <td>Rp ${Number(p.amount).toLocaleString('id-ID')}</td>
                        <td><span class="badge bg-azure-lt">${p.status}</span></td>
                        <td>${p.proof_url ? `<a href="${p.proof_url}" target="_blank">Lihat</a>` : '-'}</td>
                    </tr>`).join('');

                let hist = (d.histories || []).map(h =>
                    `<li><span class="text-muted">${h.at}</span> — <strong>${h.status}</strong> ${h.note ? '· ' + h.note : ''} <small class="text-muted">(${h.actor})</small></li>`
                ).join('');

                $('#detailBody').html(`
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="text-muted">Kode Booking</div>
                            <div class="h3 mb-2">${d.kode_booking}</div>
                            <div>${d.check_in} → ${d.check_out} (${d.malam} malam)</div>
                            <div class="mt-2"><span class="badge bg-azure-lt">${d.status}</span>
                                 <span class="badge bg-green-lt">${d.payment_status}</span></div>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <div>Total: <strong>Rp ${Number(d.grand_total).toLocaleString('id-ID')}</strong></div>
                            <div>DP: Rp ${Number(d.dp_amount).toLocaleString('id-ID')}</div>
                            <div>Dibayar: Rp ${Number(d.paid_total).toLocaleString('id-ID')}</div>
                            <div class="${d.balance_due > 0 ? 'text-danger' : 'text-green'}">
                                Sisa: Rp ${Number(d.balance_due).toLocaleString('id-ID')}</div>
                        </div>
                        <div class="col-12"><h4>Unit</h4><ul class="mb-0">${units || '<li>-</li>'}</ul></div>
                        <div class="col-12"><h4>Pembayaran</h4>
                            <table class="table table-sm"><thead><tr><th>Kode</th><th>Tipe</th><th>Metode</th><th>Nominal</th><th>Status</th><th>Bukti</th></tr></thead>
                            <tbody>${pays || '<tr><td colspan="6">Belum ada</td></tr>'}</tbody></table>
                        </div>
                        <div class="col-12"><h4>Riwayat Status</h4><ul class="mb-0">${hist || '<li>-</li>'}</ul></div>
                    </div>
                `);
            });
        });

        // Verifikasi bukti bayar (dari tombol di badge pembayaran)
        $(document).on('click', '.btn-verifikasi', function() {
            currentPaymentId = $(this).data('payment-id');
            $.get("{{ url('booking/getBukti') }}/" + currentPaymentId, function(r) {
                $('#bayarInfo').html(
                    `<div><strong>${r.kode}</strong> · ${r.booking}</div>
                     <div>Nominal: Rp ${Number(r.amount).toLocaleString('id-ID')}</div>
                     <div>Status: <span class="badge bg-azure-lt">${r.status}</span></div>`);
                if (r.proof_url) {
                    $('#bayarImg').attr('src', r.proof_url).removeClass('d-none');
                    $('#bayarEmpty').addClass('d-none');
                } else {
                    $('#bayarImg').addClass('d-none');
                    $('#bayarEmpty').removeClass('d-none');
                }
                $('#modalBayar').modal('show');
            });
        });

        $('#btnVerifikasi').on('click', function() {
            $.post("{{ url('payments') }}/" + currentPaymentId + "/verify", {
                _token: "{{ csrf_token() }}"
            }).done(function() {
                $('#modalBayar').modal('hide');
                toast('success', 'Pembayaran diverifikasi');
                reload();
            }).fail(function(xhr) {
                toast('error', xhr.responseJSON?.message || 'Gagal');
            });
        });

        $('#btnTolak').on('click', function() {
            Swal.fire({
                title: 'Tolak pembayaran?',
                input: 'text',
                inputLabel: 'Alasan penolakan',
                showCancelButton: true,
                confirmButtonText: 'Tolak',
                confirmButtonColor: '#d33',
                inputValidator: (v) => !v && 'Alasan wajib diisi'
            }).then(function(r) {
                if (!r.isConfirmed) return;
                $.post("{{ url('payments') }}/" + currentPaymentId + "/reject", {
                    _token: "{{ csrf_token() }}",
                    reason: r.value
                }).done(function() {
                    $('#modalBayar').modal('hide');
                    toast('success', 'Pembayaran ditolak');
                    reload();
                });
            });
        });

        // KPI
        $.get("{{ route('laporan.kpi') }}", function(k) {
            $('#kpiArrival').text(k.arrival ?? 0);
            $('#kpiInhouse').text(k.inhouse ?? 0);
            $('#kpiDeparture').text(k.departure ?? 0);
            $('#kpiPending').text(k.pending_payment ?? 0);
        });
    </script>
@endpush
