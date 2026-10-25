@extends('layouts.app')

@section('content')
    <div class="page-wrapper mb-3">
        <div class="page-header d-print-none">
            <div class="container-xl">
                <div class="page-pretitle">Master</div>
                <h2 class="page-title">Tarif, Unit &amp; Add-on</h2>
            </div>
        </div>

        <div class="page-body">
            <div class="container-xl">
                <div class="card">
                    <div class="card-header">
                        <ul class="nav nav-tabs card-header-tabs" data-bs-toggle="tabs">
                            <li class="nav-item"><a href="#tab-tipe" class="nav-link active" data-bs-toggle="tab">Tipe
                                    &amp; Tarif</a></li>
                            <li class="nav-item"><a href="#tab-unit" class="nav-link" data-bs-toggle="tab">Unit</a></li>
                            <li class="nav-item"><a href="#tab-kalender" class="nav-link" data-bs-toggle="tab">Kalender
                                    Tarif</a></li>
                            <li class="nav-item"><a href="#tab-season" class="nav-link" data-bs-toggle="tab">Season</a>
                            </li>
                            <li class="nav-item"><a href="#tab-addon" class="nav-link" data-bs-toggle="tab">Add-on</a>
                            </li>
                            <li class="nav-item"><a href="#tab-promo" class="nav-link" data-bs-toggle="tab">Promo</a>
                            </li>
                            <li class="nav-item"><a href="#tab-setting" class="nav-link" data-bs-toggle="tab">Pengaturan</a>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content">

                            {{-- TIPE & TARIF --}}
                            <div class="tab-pane active show" id="tab-tipe">
                                @foreach ($types as $type)
                                    <form class="row g-2 mb-4 pb-3 border-bottom form-tipe"
                                        data-id="{{ $type->id }}">
                                        @csrf
                                        <div class="col-md-3">
                                            <label class="form-label">Nama Tipe</label>
                                            <input type="text" name="nama" class="form-control"
                                                value="{{ $type->nama }}" required>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Kapasitas Min</label>
                                            <input type="number" name="kapasitas_base" class="form-control"
                                                value="{{ $type->kapasitas_base }}" min="1">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Kapasitas Maks</label>
                                            <input type="number" name="kapasitas_max" class="form-control"
                                                value="{{ $type->kapasitas_max }}" min="1">
                                        </div>
                                        <div class="col-md-1">
                                            <label class="form-label">Min Stay</label>
                                            <input type="number" name="min_stay" class="form-control"
                                                value="{{ $type->min_stay }}" min="1">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Tarif Weekday</label>
                                            <input type="number" name="base_rate_weekday" class="form-control"
                                                value="{{ (int) $type->base_rate_weekday }}" min="0">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Tarif Weekend</label>
                                            <input type="number" name="base_rate_weekend" class="form-control"
                                                value="{{ (int) $type->base_rate_weekend }}" min="0">
                                        </div>
                                        <div class="col-md-8">
                                            <label class="form-label">Deskripsi</label>
                                            <input type="text" name="deskripsi" class="form-control"
                                                value="{{ $type->deskripsi }}">
                                        </div>
                                        <div class="col-md-2 d-flex align-items-end">
                                            <label class="form-check">
                                                <input class="form-check-input" type="checkbox" name="is_active"
                                                    value="1" {{ $type->is_active ? 'checked' : '' }}>
                                                <span class="form-check-label">Aktif</span>
                                            </label>
                                        </div>
                                        <div class="col-md-2 d-flex align-items-end">
                                            <button class="btn btn-primary w-100">Simpan</button>
                                        </div>
                                    </form>
                                @endforeach
                            </div>

                            {{-- UNIT --}}
                            <div class="tab-pane" id="tab-unit">
                                <table class="table table-sm" id="tableUnit" width="100%">
                                    <thead>
                                        <tr>
                                            <th>Kode</th>
                                            <th>Nama</th>
                                            <th>Tipe</th>
                                            <th>Status</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                </table>
                            </div>

                            {{-- KALENDER TARIF & KETERSEDIAAN --}}
                            <div class="tab-pane" id="tab-kalender">
                                <div class="row g-2 align-items-end mb-3">
                                    <div class="col-md-3">
                                        <label class="form-label">Dari</label>
                                        <input type="date" id="kFrom" class="form-control"
                                            value="{{ now()->toDateString() }}">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Sampai</label>
                                        <input type="date" id="kTo" class="form-control"
                                            value="{{ now()->addDays(30)->toDateString() }}">
                                    </div>
                                    <div class="col-md-2">
                                        <button class="btn btn-primary w-100" id="kLoad">Tampilkan</button>
                                    </div>
                                    <div class="col-md-4 text-md-end">
                                        <button class="btn btn-outline-primary" id="btnBulk">
                                            <i class="fas fa-layer-group me-1"></i> Isi Rentang Tanggal
                                        </button>
                                        <button class="btn btn-outline-danger" id="btnBlock">
                                            <i class="fas fa-ban me-1"></i> Blokir Unit
                                        </button>
                                    </div>
                                </div>

                                <p class="text-muted small">
                                    Klik sel untuk mengatur harga khusus / menutup penjualan pada tanggal itu.
                                    Angka kecil = kamar tersisa.
                                </p>

                                <div class="table-responsive" style="max-height:520px;overflow:auto;">
                                    <table class="table table-sm table-bordered mb-0" id="rateMatrix"
                                        style="font-size:12px;">
                                        <thead class="sticky-top bg-white">
                                            <tr>
                                                <th style="min-width:150px;position:sticky;left:0;background:#fff;z-index:2;">
                                                    Tipe</th>
                                                <th id="rateHead" colspan="1"></th>
                                            </tr>
                                        </thead>
                                        <tbody id="rateBody">
                                            <tr>
                                                <td colspan="2" class="text-muted text-center">Memuat…</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <h4 class="mt-4">Blokir Aktif</h4>
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Unit</th>
                                            <th>Mulai</th>
                                            <th>Selesai</th>
                                            <th>Alasan</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($blocks as $b)
                                            <tr>
                                                <td>{{ $b->unit->kode ?? '-' }}</td>
                                                <td>{{ $b->start_date?->format('d/m/Y') }}</td>
                                                <td>{{ $b->end_date?->format('d/m/Y') }}</td>
                                                <td>{{ $b->reason }}</td>
                                                <td><a href="javascript:void(0)"
                                                        class="btn btn-sm btn-outline-danger btn-del-block"
                                                        data-id="{{ $b->id }}"><i class="fas fa-trash"></i></a></td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="text-muted">Tidak ada blokir aktif.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>

                            {{-- SEASON --}}
                            <div class="tab-pane" id="tab-season">
                                <form id="formSeason" class="row g-2 mb-3">
                                    <div class="col-md-3"><input type="text" name="nama" class="form-control"
                                            placeholder="Nama season (mis. Libur Nataru)" required></div>
                                    <div class="col-md-2"><input type="date" name="start_date" class="form-control"
                                            required></div>
                                    <div class="col-md-2"><input type="date" name="end_date" class="form-control"
                                            required></div>
                                    <div class="col-md-2">
                                        <select name="adjustment_type" class="form-select">
                                            <option value="percent">Persen (+/-)</option>
                                            <option value="fixed">Nominal (+/-)</option>
                                        </select>
                                    </div>
                                    <div class="col-md-1"><input type="number" name="adjustment_value"
                                            class="form-control" placeholder="50" required></div>
                                    <div class="col-md-1"><input type="number" name="priority" class="form-control"
                                            value="0" title="Prioritas"></div>
                                    <div class="col-md-1"><button class="btn btn-primary w-100">+</button></div>
                                </form>
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Nama</th>
                                            <th>Periode</th>
                                            <th>Tipe Penyesuaian</th>
                                            <th>Nilai</th>
                                            <th>Prioritas</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($seasons as $s)
                                            <tr>
                                                <td>{{ $s->nama }}</td>
                                                <td>{{ $s->start_date?->format('d/m/Y') }} –
                                                    {{ $s->end_date?->format('d/m/Y') }}</td>
                                                <td>{{ $s->adjustment_type === 'percent' ? 'Persen' : 'Nominal' }}</td>
                                                <td>{{ $s->adjustment_value }}
                                                    {{ $s->adjustment_type === 'percent' ? '%' : 'Rp' }}</td>
                                                <td>{{ $s->priority }}</td>
                                                <td><a href="javascript:void(0)"
                                                        class="btn btn-sm btn-outline-danger btn-del-season"
                                                        data-id="{{ $s->id }}"><i class="fas fa-trash"></i></a></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            {{-- ADDON --}}
                            <div class="tab-pane" id="tab-addon">
                                <form id="formAddon" class="row g-2 mb-3">
                                    <div class="col-md-3"><input type="text" name="nama" class="form-control"
                                            placeholder="Nama add-on" required></div>
                                    <div class="col-md-2"><input type="text" name="kategori" class="form-control"
                                            placeholder="Kategori"></div>
                                    <div class="col-md-2"><input type="number" name="harga" class="form-control"
                                            placeholder="Harga" required></div>
                                    <div class="col-md-2">
                                        <select name="satuan" class="form-select">
                                            <option value="per_night">Per malam</option>
                                            <option value="per_person">Per orang</option>
                                            <option value="per_person_night">Per orang/malam</option>
                                            <option value="per_booking">Per booking</option>
                                        </select>
                                    </div>
                                    <div class="col-md-1"><input type="number" name="stok" class="form-control"
                                            placeholder="Stok"></div>
                                    <div class="col-md-2"><button class="btn btn-primary w-100">Tambah</button>
                                    </div>
                                </form>
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Nama</th>
                                            <th>Kategori</th>
                                            <th>Harga</th>
                                            <th>Satuan</th>
                                            <th>Aktif</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($addons as $a)
                                            <tr>
                                                <td>{{ $a->nama }}</td>
                                                <td>{{ $a->kategori }}</td>
                                                <td>Rp {{ number_format($a->harga, 0, ',', '.') }}</td>
                                                <td>{{ str_replace('_', ' ', $a->satuan) }}</td>
                                                <td>{!! $a->is_active ? '<span class="badge bg-green text-white">Ya</span>' : '<span class="badge bg-secondary text-white">Tidak</span>' !!}</td>
                                                <td><a href="javascript:void(0)"
                                                        class="btn btn-sm btn-outline-danger btn-del-addon"
                                                        data-id="{{ $a->id }}"><i class="fas fa-trash"></i></a></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            {{-- PROMO --}}
                            <div class="tab-pane" id="tab-promo">
                                <form id="formPromo" class="row g-2 mb-3">
                                    <div class="col-md-2"><input type="text" name="kode" class="form-control"
                                            placeholder="KODE" required></div>
                                    <div class="col-md-3"><input type="text" name="nama" class="form-control"
                                            placeholder="Keterangan"></div>
                                    <div class="col-md-2">
                                        <select name="tipe" class="form-select">
                                            <option value="percent">Persen</option>
                                            <option value="fixed">Nominal</option>
                                        </select>
                                    </div>
                                    <div class="col-md-1"><input type="number" name="nilai" class="form-control"
                                            placeholder="Nilai" required></div>
                                    <div class="col-md-2"><input type="number" name="max_diskon" class="form-control"
                                            placeholder="Maks diskon"></div>
                                    <div class="col-md-2"><button class="btn btn-primary w-100">Tambah</button>
                                    </div>
                                </form>
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Kode</th>
                                            <th>Nama</th>
                                            <th>Tipe</th>
                                            <th>Nilai</th>
                                            <th>Terpakai</th>
                                            <th>Aktif</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($promos as $p)
                                            <tr>
                                                <td><code>{{ $p->kode }}</code></td>
                                                <td>{{ $p->nama }}</td>
                                                <td>{{ $p->tipe }}</td>
                                                <td>{{ $p->nilai }}{{ $p->tipe === 'percent' ? '%' : '' }}</td>
                                                <td>{{ $p->terpakai }}{{ $p->kuota ? '/' . $p->kuota : '' }}</td>
                                                <td>{!! $p->is_active ? '<span class="badge bg-green text-white">Ya</span>' : '<span class="badge bg-secondary text-white">Tidak</span>' !!}</td>
                                                <td><a href="javascript:void(0)"
                                                        class="btn btn-sm btn-outline-danger btn-del-promo"
                                                        data-id="{{ $p->id }}"><i class="fas fa-trash"></i></a></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            {{-- SETTING --}}
                            <div class="tab-pane" id="tab-setting">
                                <form id="formSetting" class="row g-2">
                                    @foreach ($settings as $s)
                                        <div class="col-md-4">
                                            <label class="form-label">{{ $s->label ?: $s->key }}</label>
                                            <input type="text" name="settings[{{ $s->key }}]" class="form-control"
                                                value="{{ $s->value }}">
                                            <small class="form-hint">{{ $s->key }}</small>
                                        </div>
                                    @endforeach
                                    <div class="col-12 mt-3">
                                        <button class="btn btn-primary">Simpan Pengaturan</button>
                                    </div>
                                </form>
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
        const CSRF = "{{ csrf_token() }}";

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

        $('#tableUnit').DataTable({
            processing: true,
            serverSide: true,
            ajax: "{{ route('master.units') }}",
            columns: [{
                    data: 'kode'
                },
                {
                    data: 'nama'
                },
                {
                    data: 'tipe'
                },
                {
                    data: 'status_badge'
                },
                {
                    data: 'action',
                    orderable: false,
                    searchable: false
                },
            ],
            order: [],
        });

        $('.form-tipe').on('submit', function(e) {
            e.preventDefault();
            const $f = $(this);
            $.post("{{ url('master/tipe') }}/" + $f.data('id'), $f.serialize() + '&_method=PUT')
                .done(() => toast('success', 'Tipe diperbarui'))
                .fail((x) => toast('error', x.responseJSON?.message || 'Gagal'));
        });

        $('#formSeason').on('submit', function(e) {
            e.preventDefault();
            $.post("{{ route('master.season.store') }}", $(this).serialize() + '&_token=' + CSRF)
                .done(() => location.reload())
                .fail((x) => toast('error', x.responseJSON?.message || 'Gagal'));
        });

        $('.btn-del-season').on('click', function() {
            $.post("{{ url('master/season') }}/" + $(this).data('id'), {
                _token: CSRF,
                _method: 'DELETE'
            }).done(() => location.reload());
        });

        $('#formAddon').on('submit', function(e) {
            e.preventDefault();
            $.post("{{ route('master.addon.store') }}", $(this).serialize() + '&_token=' + CSRF)
                .done(() => location.reload())
                .fail((x) => toast('error', x.responseJSON?.message || 'Gagal'));
        });

        $('.btn-del-addon').on('click', function() {
            $.post("{{ url('master/addon') }}/" + $(this).data('id'), {
                _token: CSRF,
                _method: 'DELETE'
            }).done(() => location.reload());
        });

        $('#formPromo').on('submit', function(e) {
            e.preventDefault();
            $.post("{{ route('master.promo.store') }}", $(this).serialize() + '&_token=' + CSRF)
                .done(() => location.reload())
                .fail((x) => toast('error', x.responseJSON?.message || 'Gagal'));
        });

        $('.btn-del-promo').on('click', function() {
            $.post("{{ url('master/promo') }}/" + $(this).data('id'), {
                _token: CSRF,
                _method: 'DELETE'
            }).done(() => location.reload());
        });

        $('#formSetting').on('submit', function(e) {
            e.preventDefault();
            $.post("{{ route('master.setting.update') }}", $(this).serialize() + '&_token=' + CSRF)
                .done(() => toast('success', 'Pengaturan disimpan'))
                .fail((x) => toast('error', 'Gagal menyimpan'));
        });

        // ---------- Kalender tarif & ketersediaan ----------
        const rpShort = (n) => {
            n = Number(n || 0);
            return n >= 1000 ? Math.round(n / 1000) + 'k' : String(n);
        };

        function loadMatrix() {
            const from = $('#kFrom').val();
            const to = $('#kTo').val();

            if (!from || !to) return;

            $.get("{{ route('master.kalender') }}", {
                from,
                to
            }, function(res) {
                const dates = res.dates || [];

                let head = '<tr><th style="min-width:150px;position:sticky;left:0;background:#fff;z-index:2;">Tipe</th>';
                dates.forEach(d => {
                    const dow = new Date(d).getDay();
                    const weekend = (dow === 5 || dow === 6);
                    head += `<th class="text-center ${weekend ? 'bg-red-lt' : ''}" style="min-width:52px;">
                        ${d.slice(8)}<br><small class="text-muted">${['Min','Sen','Sel','Rab','Kam','Jum','Sab'][dow]}</small>
                    </th>`;
                });
                head += '</tr>';
                $('#rateMatrix thead').html(head);

                let body = '';

                (res.types || []).forEach(t => {
                    body += `<tr>
                        <td style="position:sticky;left:0;background:#fff;z-index:1;">
                            <strong>${t.tipe}</strong>
                            <div class="text-muted" style="font-size:11px;">${t.units} unit</div>
                        </td>
                        ${dates.map(d => {
                            const c = t.cells[d];
                            const cls = c.closed ? 'cell-closed' : (c.override ? 'cell-override' : (c.free === 0 ? 'cell-full' : 'cell-open'));
                            return `<td class="text-center rate-cell ${cls}" role="button"
                                data-room="${t.room_id}" data-tipe="${t.tipe}" data-date="${d}"
                                data-rate="${c.rate}" data-closed="${c.closed ? 1 : 0}"
                                title="${c.season ? 'Season: ' + c.season + ' · ' : ''}Sisa ${c.free}/${c.total}">
                                ${rpShort(c.rate)}
                                <div style="font-size:10px;color:${c.free === 0 ? '#d63939' : '#888'};">${c.free}</div>
                            </td>`;
                        }).join('')}
                    </tr>`;
                });

                $('#rateBody').html(body || '<tr><td colspan="2" class="text-muted text-center">Tidak ada tipe aktif.</td></tr>');
            }).fail(() => toast('error', 'Gagal memuat kalender.'));
        }

        $('#kLoad').on('click', loadMatrix);

        $(document).on('click', '.rate-cell', function() {
            const room = $(this).data('room');
            const tipe = $(this).data('tipe');
            const date = $(this).data('date');
            const rate = $(this).data('rate');
            const closed = Number($(this).data('closed')) === 1;

            Swal.fire({
                title: `${tipe} · ${date}`,
                html: `
                    <div class="text-start">
                        <label class="form-label">Harga malam ini (Rp)</label>
                        <input id="swRate" type="number" class="form-control mb-2" value="${rate}">
                        <label class="form-check">
                            <input id="swClosed" type="checkbox" class="form-check-input" ${closed ? 'checked' : ''}>
                            <span class="form-check-label">Tutup penjualan tanggal ini</span>
                        </label>
                    </div>`,
                showCancelButton: true,
                showDenyButton: true,
                denyButtonText: 'Hapus harga khusus',
                confirmButtonText: 'Simpan',
                preConfirm: () => ({
                    rate: document.getElementById('swRate').value,
                    closed: document.getElementById('swClosed').checked ? 1 : 0,
                }),
            }).then(r => {
                if (r.isDenied) {
                    $.post("{{ route('master.override.destroy') }}", {
                        _token: CSRF,
                        room_id: room,
                        date
                    }).done(() => {
                        toast('success', 'Harga khusus dihapus');
                        loadMatrix();
                    });
                    return;
                }

                if (!r.isConfirmed) return;

                $.post("{{ route('master.override.store') }}", {
                    _token: CSRF,
                    room_id: room,
                    date,
                    rate: r.value.rate,
                    closed: r.value.closed
                }).done(() => {
                    toast('success', 'Harga khusus disimpan');
                    loadMatrix();
                }).fail(x => toast('error', x.responseJSON?.message || 'Gagal'));
            });
        });

        // Isi rentang tanggal sekaligus
        $('#btnBulk').on('click', function() {
            $.get("{{ route('master.units') }}", function() {
                const opts = @json($types->map(fn($t) => ['id' => $t->id, 'nama' => $t->nama]));
                const checks = opts.map(o =>
                    `<label class="form-check"><input type="checkbox" class="form-check-input bulk-room" value="${o.id}" checked>
                     <span class="form-check-label">${o.nama}</span></label>`).join('');

                Swal.fire({
                    title: 'Isi Rentang Tanggal',
                    html: `<div class="text-start">
                        <label class="form-label">Tipe</label>${checks}
                        <div class="row g-2 mt-2">
                            <div class="col-6"><label class="form-label">Dari</label>
                                <input id="bulkFrom" type="date" class="form-control"></div>
                            <div class="col-6"><label class="form-label">Sampai</label>
                                <input id="bulkTo" type="date" class="form-control"></div>
                        </div>
                        <label class="form-label mt-2">Cara hitung</label>
                        <select id="bulkMode" class="form-select">
                            <option value="percent">Ubah persen (+/-)</option>
                            <option value="fixed_add">Tambah nominal (Rp)</option>
                            <option value="set">Set harga tetap (Rp)</option>
                        </select>
                        <input id="bulkValue" type="number" class="form-control mt-2" placeholder="Nilai">
                    </div>`,
                    showCancelButton: true,
                    confirmButtonText: 'Terapkan',
                    preConfirm: () => ({
                        room_ids: Array.from(document.querySelectorAll('.bulk-room:checked')).map(c => c.value),
                        start_date: document.getElementById('bulkFrom').value,
                        end_date: document.getElementById('bulkTo').value,
                        mode: document.getElementById('bulkMode').value,
                        value: document.getElementById('bulkValue').value,
                    }),
                }).then(r => {
                    if (!r.isConfirmed) return;
                    if (!r.value.room_ids.length || !r.value.start_date || !r.value.end_date) {
                        return toast('warning', 'Lengkapi tipe dan tanggal.');
                    }
                    $.post("{{ route('master.override.bulk') }}", {
                        _token: CSRF,
                        ...r.value
                    }).done(res => {
                        toast('success', res.message);
                        loadMatrix();
                    }).fail(x => toast('error', x.responseJSON?.message || 'Gagal'));
                });
            });
        });

        // Blokir unit
        $('#btnBlock').on('click', function() {
            $.get("{{ route('master.unit.options') }}", function(units) {
                const checks = units.map(u =>
                    `<label class="form-check"><input type="checkbox" class="form-check-input block-unit" value="${u.id}">
                     <span class="form-check-label">${u.kode} · ${u.tipe}</span></label>`).join('');

                Swal.fire({
                    title: 'Blokir Unit',
                    html: `<div class="text-start">
                        <div style="max-height:160px;overflow:auto;">${checks}</div>
                        <div class="row g-2 mt-2">
                            <div class="col-6"><label class="form-label">Dari</label>
                                <input id="blkFrom" type="date" class="form-control"></div>
                            <div class="col-6"><label class="form-label">Sampai</label>
                                <input id="blkTo" type="date" class="form-control"></div>
                        </div>
                        <input id="blkReason" class="form-control mt-2" placeholder="Alasan (maintenance, renovasi)">
                    </div>`,
                    showCancelButton: true,
                    confirmButtonText: 'Blokir',
                    preConfirm: () => ({
                        room_unit_ids: Array.from(document.querySelectorAll('.block-unit:checked')).map(c => c.value),
                        start_date: document.getElementById('blkFrom').value,
                        end_date: document.getElementById('blkTo').value,
                        reason: document.getElementById('blkReason').value,
                    }),
                }).then(r => {
                    if (!r.isConfirmed) return;
                    if (!r.value.room_unit_ids.length || !r.value.start_date || !r.value.end_date) {
                        return toast('warning', 'Pilih unit dan tanggal.');
                    }
                    $.post("{{ route('master.block.store') }}", {
                        _token: CSRF,
                        ...r.value
                    }).done(res => {
                        toast('success', res.message);
                        setTimeout(() => location.reload(), 800);
                    }).fail(x => toast('error', x.responseJSON?.message || 'Gagal'));
                });
            });
        });

        $('.btn-del-block').on('click', function() {
            $.post("{{ url('master/blokir') }}/" + $(this).data('id'), {
                _token: CSRF,
                _method: 'DELETE'
            }).done(() => location.reload());
        });

        loadMatrix();
    </script>
@endpush

@push('styles')
    <style>
        .rate-cell { cursor: pointer; }
        .cell-open { background: #f6fbf7; }
        .cell-open:hover { background: #e3f2e8; }
        .cell-override { background: #fff4d6; }
        .cell-override:hover { background: #ffe9b0; }
        .cell-full { background: #ffe3e3; }
        .cell-closed { background: #e5e5e5; color: #999; text-decoration: line-through; }
    </style>
@endpush
