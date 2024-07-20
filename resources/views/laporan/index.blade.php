@extends('layouts.app')

@section('content')
    <div class="page-wrapper mb-3">
        <div class="page-header d-print-none">
            <div class="container-xl">
                <div class="row g-2 align-items-center">
                    <div class="col">
                        <!-- Page pre-title -->
                        <div class="page-pretitle">
                            Laporan
                        </div>
                        <h2 class="page-title">
                            Laporan Booking
                        </h2>
                    </div>
                    <!-- Page title actions -->

                </div>
            </div>
        </div>
        <!-- Page body -->
        <div class="page-body">
            <div class="container-xl">
                <div class="card">
                    <div class="card-header bg-azure text-white">
                        <h3 class="card-title">Cari Laporan</h3>
                    </div>
                    <div class="card-body">
                        <!-- Filter Form -->
                        <form id="filterForm" class="row g-3 mb-4" method="POST" action="{{ route('laporan.cetak') }}">
                            @csrf
                            <div class="col-md-4">
                                <label for="checkIn" class="form-label">Tanggal Awal</label>
                                <input type="date" name="checkIn" id="checkIn" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label for="checkOut" class="form-label">Tanggal Akhir</label>
                                <input type="date" name="checkOut" id="checkOut" class="form-control" required>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="button" class="btn btn-primary" onclick="filterData()">Filter</button> &nbsp;
                                <button type="submit" class="btn btn-secondary">Cetak Laporan</button>
                            </div>
                        </form>

                        <!-- Table -->
                        <table class="table" data-export-title="Export" id="table1" width="100%">
                            <thead class="">
                                <tr>
                                    <th width="3%">No</th>
                                    <th width="auto">Nama</th>
                                    <th width="auto">Email</th>
                                    <th width="auto">HP</th>

                                </tr>
                            </thead>
                            <tbody>
                                <!-- Data akan dimasukkan di sini melalui JavaScript -->
                            </tbody>
                        </table>

                        <!-- Print Button -->
                        <div class="text-end mt-3">

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Mendapatkan tanggal hari ini dalam format YYYY-MM-DD
        // const today = new Date().toISOString().split('T')[0];
        // document.getElementById('checkIn').setAttribute('min', today);
        // document.getElementById('checkOut').setAttribute('min', today);

        function filterData() {
            const checkIn = document.getElementById('checkIn').value;
            const checkOut = document.getElementById('checkOut').value;
            const url = `{{ route('laporan.filter') }}?checkIn=${checkIn}&checkOut=${checkOut}`;

            fetch(url)
                .then(response => response.json())
                .then(data => {
                    const tbody = document.querySelector('#table1 tbody');
                    tbody.innerHTML = '';
                    data.forEach((item, index) => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>${index + 1}</td>
                            <td>${item.NamaBooking}</td>
                             <td>${item.Email}</td>
                              <td>${item.hp}</td>

                        `;
                        tbody.appendChild(row);
                    });
                });
        }
    </script>
@endsection