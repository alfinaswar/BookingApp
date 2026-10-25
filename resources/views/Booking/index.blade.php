{{-- Matriks ketersediaan unit × tanggal + cart multi-unit --}}
@extends('layouts.app')

@section('content')
    <div class="page-wrapper mb-3">
        <div class="page-header d-print-none">
            <div class="container-xl">
                <div class="row g-2 align-items-center">
                    <div class="col">
                        <div class="page-pretitle">Booking</div>
                        <h2 class="page-title">Pilih Unit &amp; Tanggal Menginap</h2>
                    </div>
                </div>
            </div>
        </div>

        <div class="page-body">
            <div class="container-xl">
                <div class="row g-3">
                    {{-- KIRI: filter + matriks --}}
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header" style="background-color:#1F573A;">
                                <h3 class="card-title text-white">Ketersediaan Real-time</h3>
                                <div class="card-actions">
                                    <span class="badge bg-green text-white">Tersedia</span>
                                    <span class="badge bg-red text-white">Terisi</span>
                                    <span class="badge bg-secondary text-white">Diblokir</span>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row g-2 align-items-end mb-3">
                                    <div class="col-md-3">
                                        <label class="form-label">Check-in</label>
                                        <input type="date" id="fCheckIn" class="form-control"
                                            min="{{ now()->toDateString() }}" value="{{ $calendar['from'] }}">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Check-out</label>
                                        <input type="date" id="fCheckOut" class="form-control"
                                            value="{{ $calendar['to'] }}">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Dewasa</label>
                                        <input type="number" id="fDewasa" class="form-control" value="2" min="1">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Anak</label>
                                        <input type="number" id="fAnak" class="form-control" value="0" min="0">
                                    </div>
                                    <div class="col-md-2">
                                        <button class="btn w-100 text-white" id="btnRefresh"
                                            style="background-color:#1F573A;">Terapkan</button>
                                    </div>
                                </div>

                                <p class="text-muted small mb-2">
                                    Klik sel hijau untuk memilih unit. Bisa pilih beberapa unit sekaligus.
                                    <span id="malamInfo" class="fw-bold"></span>
                                </p>

                                <div id="matrixWrap" class="table-responsive" style="max-height:520px;overflow:auto;">
                                    @include('Booking.partials.matrix', ['calendar' => $calendar])
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- KANAN: ringkasan --}}
                    <div class="col-lg-4">
                        <div class="card sticky-top" style="top:1rem;">
                            <div class="card-header">
                                <h3 class="card-title">Ringkasan Pesanan</h3>
                            </div>
                            <div class="card-body" id="summaryBody">
                                <div class="text-muted text-center py-4" id="emptyCart">
                                    <i class="fas fa-bed fa-2x mb-2"></i>
                                    <p class="mb-0">Belum ada unit dipilih.</p>
                                </div>

                                <div id="cartList"></div>

                                <div id="addonSection" class="mt-3 d-none">
                                    <h4 class="mb-2">Paket Tambahan</h4>
                                    @foreach ($addons as $addon)
                                        <div class="d-flex align-items-center justify-content-between mb-2 addon-row"
                                            data-id="{{ $addon->id }}" data-satuan="{{ $addon->satuan }}">
                                            <div>
                                                <div class="form-check">
                                                    <input class="form-check-input addon-check" type="checkbox"
                                                        value="{{ $addon->id }}" id="addon{{ $addon->id }}">
                                                    <label class="form-check-label" for="addon{{ $addon->id }}">
                                                        {{ $addon->nama }}
                                                        <div class="text-muted small">
                                                            Rp {{ number_format($addon->harga, 0, ',', '.') }}
                                                            /{{ str_replace('_', ' ', $addon->satuan) }}
                                                        </div>
                                                    </label>
                                                </div>
                                            </div>
                                            <input type="number" class="form-control form-control-sm addon-qty"
                                                style="width:60px" value="1" min="1" disabled>
                                        </div>
                                    @endforeach
                                </div>

                                <hr>

                                <div class="d-flex justify-content-between mb-1">
                                    <span>Subtotal</span><span id="sSubtotal">Rp 0</span>
                                </div>
                                <div class="d-flex justify-content-between mb-1 d-none" id="rowAddon">
                                    <span>Add-ons</span><span id="sAddon">Rp 0</span>
                                </div>
                                <div class="d-flex justify-content-between mb-1 d-none" id="rowDiscount">
                                    <span>Diskon</span><span id="sDiscount" class="text-green">- Rp 0</span>
                                </div>
                                <div class="d-flex justify-content-between mb-1 d-none" id="rowTax">
                                    <span>Pajak &amp; Service</span><span id="sTax">Rp 0</span>
                                </div>
                                <div class="d-flex justify-content-between fw-bold fs-3 mt-2">
                                    <span>Total</span><span id="sGrand">Rp 0</span>
                                </div>
                                <div class="d-flex justify-content-between mt-1">
                                    <span>DP <span id="dpPct">{{ number_format($dpPercent, 0) }}</span>%</span>
                                    <span id="sDp" class="fw-bold text-orange">Rp 0</span>
                                </div>

                                <div class="mt-3">
                                    <label class="form-label">Kode Promo</label>
                                    <div class="input-group">
                                        <input type="text" id="promoKode" class="form-control"
                                            placeholder="Opsional">
                                        <button class="btn btn-outline-secondary" id="btnPromo">Pakai</button>
                                    </div>
                                    <small id="promoInfo" class="form-text"></small>
                                </div>
                            </div>
                            <div class="card-footer">
                                <button class="btn w-100 text-white btn-lg" id="btnLanjut" disabled
                                    style="background-color:#1F573A;">
                                    Lanjut Isi Data Tamu
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('Booking.partials.wizard')
@endsection

@push('scripts')
    <script>
        (function() {
            const state = {
                cart: new Map(), // unitId -> {kode, tipe, roomId}
                quote: null,
                promo: '',
            };

            const fmt = (n) => 'Rp ' + Number(n || 0).toLocaleString('id-ID');
            const $ci = $('#fCheckIn');
            const $co = $('#fCheckOut');

            function ranges() {
                return {
                    check_in: $ci.val(),
                    check_out: $co.val()
                };
            }

            function nightsBetween() {
                const {
                    check_in,
                    check_out
                } = ranges();
                if (!check_in || !check_out) return 0;
                const ms = new Date(check_out) - new Date(check_in);
                return Math.max(0, Math.round(ms / 86400000));
            }

            function paintMatrix() {
                $('tr[data-unit-id]').each(function() {
                    const unitId = $(this).data('unit-id');
                    const selected = state.cart.has(unitId);
                    $(this).find('td[data-date]').each(function() {
                        $(this).toggleClass('selected', selected && $(this).data('state') === 'available');
                    });
                });
            }

            function addonPayload() {
                const rows = [];
                $('.addon-check:checked').each(function() {
                    rows.push({
                        id: $(this).val(),
                        qty: $(this).closest('.addon-row').find('.addon-qty').val() || 1
                    });
                });
                return rows;
            }

            function requestQuote() {
                const {
                    check_in,
                    check_out
                } = ranges();

                if (state.cart.size === 0 || !check_in || !check_out) {
                    renderEmpty();
                    return;
                }

                $.ajax({
                    url: "{{ route('booking.quote') }}",
                    method: 'POST',
                    data: {
                        _token: "{{ csrf_token() }}",
                        room_unit_ids: Array.from(state.cart.keys()),
                        check_in,
                        check_out,
                        dewasa: $('#fDewasa').val(),
                        anak: $('#fAnak').val(),
                        addons: addonPayload(),
                        promo_kode: state.promo,
                    },
                    success: function(q) {
                        state.quote = q;
                        renderQuote(q);
                    },
                    error: function(xhr) {
                        toast('Gagal menghitung harga.', 'error');
                        console.error(xhr.responseText);
                    }
                });
            }

            function renderEmpty() {
                state.quote = null;
                $('#cartList').html('');
                $('#emptyCart').removeClass('d-none');
                $('#addonSection').addClass('d-none');
                $('#sSubtotal, #sAddon, #sDiscount, #sTax, #sGrand, #sDp').text('Rp 0');
                $('#btnLanjut').prop('disabled', true);
            }

            function renderQuote(q) {
                $('#emptyCart').addClass('d-none');
                $('#addonSection').removeClass('d-none');
                $('#btnLanjut').prop('disabled', q.conflicts && q.conflicts.length > 0);

                let html = '';
                (q.units || []).forEach(function(u) {
                    const conflict = (q.conflicts || []).includes(u.room_unit_id);
                    html += `
                    <div class="d-flex justify-content-between align-items-start mb-2 pb-2 border-bottom">
                        <div>
                            <strong>${u.unit_kode}</strong> <span class="badge bg-azure-lt">${u.tipe}</span>
                            <div class="text-muted small">${u.malam} malam × ${fmt(u.subtotal / (u.malam || 1))}</div>
                            ${conflict ? '<div class="text-danger small"><i class="fas fa-exclamation-triangle"></i> Tidak tersedia</div>' : ''}
                        </div>
                        <div class="text-end">
                            <div>${fmt(u.subtotal)}</div>
                            <a href="javascript:void(0)" class="text-danger small btn-remove-unit" data-id="${u.room_unit_id}">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                    </div>`;
                });
                $('#cartList').html(html);

                $('#sSubtotal').text(fmt(q.subtotal));
                $('#sAddon').text(fmt(q.addon_total));
                $('#sDiscount').text('- ' + fmt(q.discount_total));
                $('#sTax').text(fmt(q.tax_total + q.service_total));
                $('#sGrand').text(fmt(q.grand_total));
                $('#sDp').text(fmt(q.dp_amount));

                $('#rowAddon').toggleClass('d-none', !q.addon_total);
                $('#rowDiscount').toggleClass('d-none', !q.discount_total);
                $('#rowTax').toggleClass('d-none', !(q.tax_total + q.service_total));

                $('#promoInfo')
                    .text(state.promo ? (q.kode_promo_valid ? 'Promo diterapkan.' : 'Kode promo tidak valid.') : '')
                    .toggleClass('text-danger', !!state.promo && !q.kode_promo_valid);

                if (q.conflicts && q.conflicts.length) {
                    toast('Ada unit yang sudah terisi. Hapus unit tersebut untuk melanjutkan.', 'error');
                }
            }

            function toast(msg, icon) {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: icon || 'info',
                    title: msg,
                    showConfirmButton: false,
                    timer: 3000
                });
            }

            // Pilih / lepas unit dengan klik sel
            $(document).on('click', 'td[data-state="available"]', function() {
                const $row = $(this).closest('tr');
                const unitId = $row.data('unit-id');

                if (nightsBetween() <= 0) {
                    toast('Tentukan tanggal check-in dan check-out dulu.', 'warning');
                    return;
                }

                if (state.cart.has(unitId)) {
                    state.cart.delete(unitId);
                } else {
                    state.cart.set(unitId, {
                        kode: $row.data('unit-kode'),
                        tipe: $row.data('unit-tipe'),
                        roomId: $row.data('room-id'),
                    });
                }

                paintMatrix();
                requestQuote();
            });

            $(document).on('click', '.btn-remove-unit', function() {
                state.cart.delete($(this).data('id'));
                paintMatrix();
                requestQuote();
            });

            $('.addon-check').on('change', function() {
                $(this).closest('.addon-row').find('.addon-qty').prop('disabled', !this.checked);
                requestQuote();
            });

            $('.addon-qty').on('input', requestQuote);

            $('#btnPromo').on('click', function() {
                state.promo = $('#promoKode').val().trim();
                requestQuote();
            });

            $('#btnRefresh').on('click', function() {
                const {
                    check_in,
                    check_out
                } = ranges();

                if (!check_in || !check_out || new Date(check_out) <= new Date(check_in)) {
                    toast('Rentang tanggal tidak valid.', 'warning');
                    return;
                }

                $.get("{{ route('booking.kalender') }}", {
                    from: check_in,
                    to: check_out
                }, function(res) {
                    $.get("{{ route('booking.index') }}", {}, function() {
                        location.reload();
                    });
                });
            });

            $ci.on('change', function() {
                const start = new Date($(this).val());
                if (!isNaN(start)) {
                    start.setDate(start.getDate() + 1);
                    $co.attr('min', start.toISOString().split('T')[0]);
                    if (!$co.val() || new Date($co.val()) <= new Date($(this).val())) {
                        $co.val(start.toISOString().split('T')[0]);
                    }
                }
                state.cart.clear();
                paintMatrix();
                renderEmpty();
            });

            $('#btnLanjut').on('click', function() {
                if (!state.quote || (state.quote.conflicts || []).length) return;
                $('#modalTamu').modal('show');
            });

            $('#formBooking').on('submit', function(e) {
                e.preventDefault();
                const $btn = $('#btnSubmitBooking').prop('disabled', true).text('Memproses...');
                $('#formError').addClass('d-none');

                const data = {
                    _token: "{{ csrf_token() }}",
                    room_unit_ids: Array.from(state.cart.keys()),
                    check_in: $ci.val(),
                    check_out: $co.val(),
                    dewasa: $('#fDewasa').val(),
                    anak: $('#fAnak').val(),
                    addons: addonPayload(),
                    promo_kode: state.promo,
                    nama: $('input[name=nama]').val(),
                    email: $('input[name=email]').val(),
                    hp: $('input[name=hp]').val(),
                    gender: $('input[name=gender]').val(),
                    identitas: $('input[name=identitas]').val(),
                    no_identitas: $('input[name=no_identitas]').val(),
                    catatan: $('input[name=catatan]').val(),
                    metode: $('input[name=metode]:checked').val(),
                    source: 'online',
                };

                $.ajax({
                    url: "{{ route('booking.store') }}",
                    method: 'POST',
                    data: data,
                    success: function(res) {
                        window.location.href = res.redirect;
                    },
                    error: function(xhr) {
                        $btn.prop('disabled', false).text('Buat Booking');
                        const msg = xhr.responseJSON?.message || 'Gagal membuat booking.';
                        $('#formError').removeClass('d-none').text(msg);
                        if (xhr.status === 409) {
                            state.cart.clear();
                            paintMatrix();
                            renderEmpty();
                        }
                    }
                });
            });

            // init
            renderEmpty();
            $ci.trigger('change');
        })();
    </script>
@endpush
