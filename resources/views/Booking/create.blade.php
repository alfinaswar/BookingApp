@extends('layouts.app')

@section('content')
    <div class="page-wrapper mb-3">
        <div class="page-header d-print-none">
            <div class="container-xl">

                <div class="row g-2 align-items-center">
                    <div class="col">
                        <!-- Page pre-title -->
                        <div class="page-pretitle">
                            Booking
                        </div>
                        <h2 class="page-title">
                            Booking Room / Kamar
                        </h2>
                    </div>
                    <!-- Page title actions -->
                    <div class="card bg-primary text-primary-fg">
                        <div class="card-stamp">
                          <div class="card-stamp-icon bg-white text-primary">
                            <!-- Download SVG icon from http://tabler-icons.io/i/star -->
                            <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M12 17.75l-6.172 3.245l1.179 -6.873l-5 -4.867l6.9 -1l3.086 -6.253l3.086 6.253l6.9 1l-5 4.867l1.179 6.873z"></path></svg>
                          </div>
                        </div>
                        <div class="card-body">
                          <h3 class="card-title">Selesaikan Pemesanan Kamar</h3>
                          <p>Lengkapi Identitas Diri</p>
                        </div>
                      </div>
                </div>
            </div>
        </div>

        <!-- Page body -->
        <div class="page-body">
            <div class="container">
                <div class="row">
                    <div class="col-sm-3">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">
                                    Detail Room / Kamar
                                </h3>
                                <div class="card-actions">

                                </div>
                            </div>
                            <div class="card-body">
                                <dl class="row">
                                    <dt class="col-5">Tipe Room / Kamar</dt>
                                    <dd class="col-7">: <badge class="badge bg-warning text-white xl">
                                            {{ $getData->roomtype }}</badge>
                                    </dd>
                                    <dt class="col-5">Nama Room / Kamar</dt>
                                    <dd class="col-7">: {{ $getData->nama }}</dd>
                                    <dt class="col-5">Deskripsi:</dt>
                                    <dd class="col-7">: {{ $getData->deskripsi }}</dd>
                                    @if ($isWeekend)
                                        <dt class="col-5">Weekend</dt>
                                        <dd class="col-7">: Rp. {{ number_format($getData->tarifWe, 0, ',', '.') }}</dd>
                                    @else
                                        <dt class="col-5">Weekdays</dt>
                                        <dd class="col-7">: Rp. {{ number_format($getData->tarifWd, 0, ',', '.') }}</dd>
                                    @endif
                                    <dt class="col-5">Fasilitas</dt>
                                    <dd class="col-7">:
                                        @foreach ($getData->Fasilitas as $item)
                                            <span class="badge bg-info text-white">{{ $item }}</span>
                                        @endforeach
                                    </dd>
                                    <dt class="col-5">Max Checkout</dt>
                                    <dd class="col-7">: {{ $getData->checkout }}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="row row-cards">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-body">
                                        <h3 class="card-title">Identitas Diri</h3>
                                        <div class="row row-cards">
                                            <div class="col-md-4">
                                                <div class="mb-3">
                                                    <label class="form-label">No Identitas</label>
                                                    <input type="number" class="form-control"
                                                        placeholder="KTP / SIM / Passport" maxlength="16"
                                                        name="NoIdentitas">
                                                </div>
                                            </div>
                                            <div class="col-sm-6 col-md-4">
                                                <div class="mb-3">
                                                    <label class="form-label">Nama Lengkap</label>
                                                    <input type="text" class="form-control" name="NamaBooking"
                                                        placeholder="Nama Lengkap">
                                                </div>
                                            </div>
                                            <div class="col-sm-6 col-md-4">
                                                <div class="mb-3">
                                                    <label class="form-label">Email</label>
                                                    <input type="email" class="form-control" placeholder="Email"
                                                        name="E-Mail">
                                                </div>
                                            </div>
                                            <div class="col-sm-6 col-md-4">
                                                <div class="mb-3">
                                                    <label class="form-label">Jenis Kelamin</label>
                                                    <div class="form-selectgroup">
                                                        <label class="form-selectgroup-item">
                                                            <input type="radio" name="Gender" value="L"
                                                                class="form-selectgroup-input" checked="">
                                                            <span
                                                                class="form-selectgroup-label"><!-- Download SVG icon from http://tabler-icons.io/i/home -->
                                                                <svg xmlns="http://www.w3.org/2000/svg" width="24"
                                                                    height="24" viewBox="0 0 24 24" fill="none"
                                                                    stroke="currentColor" stroke-width="2"
                                                                    stroke-linecap="round" stroke-linejoin="round"
                                                                    class="icon icon-tabler icons-tabler-outline icon-tabler-gender-male">
                                                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                                                    <path d="M10 14m-5 0a5 5 0 1 0 10 0a5 5 0 1 0 -10 0" />
                                                                    <path d="M19 5l-5.4 5.4" />
                                                                    <path d="M19 5h-5" />
                                                                    <path d="M19 5v5" />
                                                                </svg> Pria</span>
                                                        </label>
                                                        <label class="form-selectgroup-item">
                                                            <input type="radio" name="Gender" value="P"
                                                                class="form-selectgroup-input">
                                                            <span
                                                                class="form-selectgroup-label"><!-- Download SVG icon from http://tabler-icons.io/i/user -->
                                                                <svg xmlns="http://www.w3.org/2000/svg" width="24"
                                                                    height="24" viewBox="0 0 24 24" fill="none"
                                                                    stroke="currentColor" stroke-width="2"
                                                                    stroke-linecap="round" stroke-linejoin="round"
                                                                    class="icon icon-tabler icons-tabler-outline icon-tabler-gender-female">
                                                                    <path stroke="none" d="M0 0h24v24H0z"
                                                                        fill="none" />
                                                                    <path d="M12 9m-5 0a5 5 0 1 0 10 0a5 5 0 1 0 -10 0" />
                                                                    <path d="M12 14v7" />
                                                                    <path d="M9 18h6" />
                                                                </svg> Wanita</span>
                                                        </label>

                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-sm-6 col-md-8">
                                                <div class="mb-3">
                                                    <label class="form-label">No HP / Whatsapp</label>
                                                    <input type="number" class="form-control" maxlength="13"
                                                        placeholder="Nomor Whatsapp" value="Faker">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">Check in</label>
                                                    <input type="date" class="form-control" placeholder="Check In"
                                                        name="checkIn">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">Check Out</label>
                                                    <input type="date" class="form-control" placeholder="Check Out"
                                                        name="checkOut">
                                                </div>
                                            </div>
                                            <div class="col-sm-6 col-md-12">
                                                <div class="mb-3">
                                                    <label class="form-label">Jumlah Tamu</label>
                                                    <input type="number" class="form-control" name="jumlahTamu">
                                                </div>
                                            </div>

                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-3">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">
                                    Detail Transaksi
                                </h3>
                                <div class="card-actions">

                                </div>
                            </div>
                            <div class="card-body">
                                <dl class="row">
                                    <dt class="col-5">Tipe Kamar</dt>
                                    <dd class="col-7">: <badge class="badge bg-warning text-white xl">
                                            {{ $getData->roomtype }}</badge>
                                    </dd>
                                    <dt class="col-5">Total Hari</dt>
                                    <dd class="col-7">: <span id="TotalHari"></span> Hari</dd>
                                    <dt class="col-5">Tarif:</dt>
                                    <dd class="col-7">: <span id="Tarif"></span></dd>
                                </dl>
                                <button id="btnBayarSekarang" class="custom-button">Bayar Sekarang</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
    <script>
        $(document).ready(function() {
            $('#btnBayarSekarang').click(function(e) {
            e.preventDefault();
            var token = $('meta[name="csrf-token"]').attr('content'); // Mengambil token CSRF
            var data = {
                _token: token, // Menambahkan token CSRF ke dalam data
                NoIdentitas: $('input[name="NoIdentitas"]').val(),
                NamaBooking: $('input[name="NamaBooking"]').val(),
                EMail: $('input[name="E-Mail"]').val(),
                Gender: $('input[name="Gender"]:checked').val(),
                NoHP: $('input[name="NoHP"]').val(),
                checkIn: $('input[name="checkIn"]').val(),
                checkOut: $('input[name="checkOut"]').val(),
                jumlahTamu: $('input[name="jumlahTamu"]').val(),
                tarifTotal: $('#Tarif').data('tarif')
            };
            $.ajax({
                type: 'POST',
                url: '{{ route('booking.store') }}',
                data: data,
                success: function(response) {
                    console.log(response);
                    alert('Booking berhasil dilakukan!');
                },
                error: function(xhr, status, error) {
                    console.error(error);
                    alert('Terjadi kesalahan saat melakukan booking. Silakan coba lagi.');
                }
            });
        });

            $(document).on('click', '#booknow', function() {
                var roomId = $(this).data('id');
                var url = "{{ route('booking.create', ':id') }}";
                url = url.replace(':id', roomId);
                window.location.href = url;
            });
            $('input[name="checkIn"], input[name="checkOut"]').change(function() {
                var checkIn = $('input[name="checkIn"]').val();
                var checkOut = $('input[name="checkOut"]').val();

                var startDate = new Date(checkIn);
                var endDate = new Date(checkOut);
                var timeDiff = Math.abs(endDate.getTime() - startDate.getTime());
                var diffDays = Math.ceil(timeDiff / (1000 * 3600 * 24));

                $('#TotalHari').text(diffDays);

                var tarifPerHari = {{ $isWeekend ? $getData->tarifWe : $getData->tarifWd }};
                var totalBayar = tarifPerHari * diffDays;

                $('#Tarif').text('Rp. ' + totalBayar.toLocaleString('id-ID'));
            });
        });
    </script>
@endsection