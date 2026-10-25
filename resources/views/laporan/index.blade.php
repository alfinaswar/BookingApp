@extends('layouts.app')

@section('content')
    <div class="page-wrapper mb-3">
        <div class="page-header d-print-none">
            <div class="container-xl">
                <div class="row g-2 align-items-center">
                    <div class="col">
                        <div class="page-pretitle">Laporan</div>
                        <h2 class="page-title">Laporan Operasional</h2>
                    </div>
                </div>
            </div>
        </div>

        <div class="page-body">
            <div class="container-xl">
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label">Dari Tanggal</label>
                                <input type="date" id="rFrom" class="form-control"
                                    value="{{ now()->startOfMonth()->toDateString() }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Sampai Tanggal</label>
                                <input type="date" id="rTo" class="form-control"
                                    value="{{ now()->toDateString() }}">
                            </div>
                            <div class="col-md-3">
                                <button class="btn w-100 text-white" id="btnLoad"
                                    style="background-color:#1F573A;">Tampilkan</button>
                            </div>
                            <div class="col-md-3">
                                <form method="POST" action="{{ route('laporan.cetak') }}" id="formCetak">
                                    @csrf
                                    <input type="hidden" name="from" id="cetakFrom">
                                    <input type="hidden" name="to" id="cetakTo">
                                    <button class="btn btn-outline-secondary w-100">
                                        <i class="fas fa-file-pdf me-1"></i> Unduh PDF
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row row-cards mb-3">
                    <div class="col-sm-6 col-lg-3">
                        <div class="card"><div class="card-body">
                            <div class="subheader">Okupansi</div>
                            <div class="h1 mb-0" id="mOccupancy">-</div>
                            <div class="text-muted small" id="mOccupancySub"></div>
                        </div></div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="card"><div class="card-body">
                            <div class="subheader">ADR</div>
                            <div class="h1 mb-0" id="mAdr">-</div>
                            <div class="text-muted small">Rata-rata tarif per malam terisi</div>
                        </div></div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="card"><div class="card-body">
                            <div class="subheader">RevPAR</div>
                            <div class="h1 mb-0" id="mRevpar">-</div>
                            <div class="text-muted small">Pendapatan per unit tersedia</div>
                        </div></div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="card"><div class="card-body">
                            <div class="subheader">Kas Diterima</div>
                            <div class="h1 mb-0" id="mCash">-</div>
                            <div class="text-muted small">Pembayaran terverifikasi</div>
                        </div></div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <ul class="nav nav-tabs card-header-tabs" data-bs-toggle="tabs">
                            <li class="nav-item"><a href="#t-okupansi" class="nav-link active"
                                    data-bs-toggle="tab">Okupansi</a></li>
                            <li class="nav-item"><a href="#t-pendapatan" class="nav-link"
                                    data-bs-toggle="tab">Pendapatan</a></li>
                            <li class="nav-item"><a href="#t-tamu" class="nav-link" data-bs-toggle="tab">Rekap Tamu</a>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content">

                            <div class="tab-pane active show" id="t-okupansi">
                                <div class="table-responsive">
                                    <table class="table table-sm table-vcenter">
                                        <thead>
                                            <tr>
                                                <th>Tanggal</th>
                                                <th class="text-end">Malam Terisi</th>
                                                <th class="text-end">Okupansi</th>
                                                <th class="text-end">Pendapatan Kamar</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tbodyOkupansi">
                                            <tr>
                                                <td colspan="4" class="text-muted text-center">Belum ada data</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="tab-pane" id="t-pendapatan">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h4>Akrual (per malam inap)</h4>
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Tanggal</th>
                                                    <th class="text-end">Nilai</th>
                                                </tr>
                                            </thead>
                                            <tbody id="tbodyAccrual"></tbody>
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <h4>Kas (pembayaran diterima)</h4>
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Tanggal</th>
                                                    <th class="text-end">Nilai</th>
                                                </tr>
                                            </thead>
                                            <tbody id="tbodyCash"></tbody>
                                        </table>
                                    </div>
                                </div>
                                <div class="alert alert-info mt-2 mb-0">
                                    Akrual dan kas sengaja dipisah. Selisih keduanya adalah piutang
                                    (DP/pelunasan yang belum masuk).
                                </div>
                            </div>

                            <div class="tab-pane" id="t-tamu">
                                <div class="mb-2 text-muted">
                                    <span id="guestSummary"></span>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Nama</th>
                                                <th>Kontak</th>
                                                <th class="text-end">Kunjungan</th>
                                                <th class="text-end">Malam</th>
                                                <th class="text-end">Total Belanja</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tbodyTamu"></tbody>
                                    </table>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const rp = (n) => 'Rp ' + Number(n || 0).toLocaleString('id-ID');

        function load() {
            const from = $('#rFrom').val();
            const to = $('#rTo').val();

            if (!from || !to) {
                Swal.fire('Lengkapi tanggal', 'Isi tanggal awal dan akhir.', 'warning');
                return;
            }

            $('#cetakFrom').val(from);
            $('#cetakTo').val(to);

            $.get("{{ route('laporan.okupansi') }}", {
                from,
                to
            }, function(o) {
                $('#mOccupancy').text(o.occupancy + '%');
                $('#mOccupancySub').text(o.occupied + ' / ' + o.available + ' unit-malam');
                $('#mAdr').text(rp(o.adr));
                $('#mRevpar').text(rp(o.revpar));

                const rows = (o.per_day || []).map(d => `
                    <tr>
                        <td>${d.date}</td>
                        <td class="text-end">${d.nights}</td>
                        <td class="text-end">${d.occupancy}%</td>
                        <td class="text-end">${rp(d.revenue)}</td>
                    </tr>`).join('');

                $('#tbodyOkupansi').html(rows || '<tr><td colspan="4" class="text-center text-muted">Tidak ada data</td></tr>');
            });

            $.get("{{ route('laporan.pendapatan') }}", {
                from,
                to
            }, function(r) {
                $('#mCash').text(rp(r.cash_total));

                $('#tbodyAccrual').html((r.accrual || []).map(x =>
                    `<tr><td>${x.date}</td><td class="text-end">${rp(x.total)}</td></tr>`
                ).join('') + `<tr class="fw-bold"><td>Total</td><td class="text-end">${rp(r.accrual_total)}</td></tr>`);

                $('#tbodyCash').html((r.cash || []).map(x =>
                    `<tr><td>${x.date}</td><td class="text-end">${rp(x.total)}</td></tr>`
                ).join('') + `<tr class="fw-bold"><td>Total</td><td class="text-end">${rp(r.cash_total)}</td></tr>`);
            });

            $.get("{{ route('laporan.tamu') }}", {
                from,
                to
            }, function(g) {
                $('#guestSummary').text(
                    `${g.total_tamu} tamu · ${g.total_malam} malam · ${rp(g.total_belanja)}`
                );
                $('#tbodyTamu').html((g.rows || []).map(x => `
                    <tr>
                        <td>${x.nama}</td>
                        <td>${x.hp ?? '-'}<br><small class="text-muted">${x.Email}</small></td>
                        <td class="text-end">${x.kunjungan}</td>
                        <td class="text-end">${x.malam}</td>
                        <td class="text-end">${rp(x.belanja)}</td>
                    </tr>`).join('') || '<tr><td colspan="5" class="text-center text-muted">Tidak ada data</td></tr>');
            });
        }

        $('#btnLoad').on('click', load);
        load();
    </script>
@endpush
