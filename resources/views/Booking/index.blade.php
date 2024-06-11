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
                    <div class="col-auto ms-auto d-print-none">

                    </div>
                </div>
            </div>
        </div>
        <!-- Page body -->
        <div class="page-body">
        <div class="container-xl">
                <div class="card">
                    <div class="card-header bg-azure">
                        <h3 class="card-title">Daftar Room / Kamar</h3>
                    </div>
                    <div class="card-body">
        <div class="row mb-3">
                <div class="col-md-3">
                    <small>Filter By Jenis:</small>
                    <div class="form-group">
                    <div class="input-group">
                    <div class="input-group-prepend"><span class="input-group-text"><i class="fa fa-search"></i></span></div>
                    <select class="form-control " name="filter_jenis" id="filter_pengguna">
                        <option value="">Semua</option>
                        <option value="umum">Umum</option>
                        <option value="Medis">Medis</option>
                    </select>
                </div>
                </div>
                </div>


                <div class="col-md-3">
                    <small>Filter By Departmen:</small>
                    <div class="form-group">
                    <div class="input-group">
                    <div class="input-group-prepend"><span class="input-group-text"><i class="fa fa-users"></i></span></div>
                    <select name="filter_departemen" class="custom-select form-control" id="filter_departemen">
                                <option value="" selected>--Pilih Departemen--</option>

                            </select>
                </div>
                </div>
                </div>
            </div>
                        <table class="table" data-export-title="Export" id="table1" width="100%">
                            <thead class="text-center">
                                <tr>
                                    <th width="3%">No</th>
                                    <th width="auto">Nama</th>
                                    <th width="10%">Jumlah Kamar</th>
                                    <th width="10%">Status</th>
                                    <th width="115">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>
    <script>
        $(document).ready(function() {
            $('#btn-save').click(function() {
                var formData = $('#form-room').serialize();
                $.ajax({
                    url: '{{ route('room.store') }}',
                    type: 'POST',
                    data: formData,
                    headers: {
                        'X-CSRF-TOKEN': $('input[name="_token"]').val()
                    },
                    success: function(response) {
                        Swal.fire({
                            title: "Success!",
                            text: "Data Berhasil Disimpan!",
                            icon: "success",
                            showConfirmButton: false,
                            timer: 900
                        }).then(function() {
 $('#editModal').modal('hide');
                        $('#table1').DataTable().ajax.reload();
                        location.refresh();
                        });

                    },
                    error: function(xhr) {
                        Swal.fire({
                            title: "Error!",
                            text: "Gagal",
                            icon: "error",
                            showConfirmButton: false,
                            timer: 900
                        });
                        console.log(xhr.responseText);
                    }
                });
            });

            //edit btn
            $('body').on('click', '.btn-edit', function() {
                var id = $(this).data('id');
                $.ajax({
                    type: "GET",
                    url: "{{ route('room.show', ':id') }}".replace(':id', id),
                    success: function(response) {
                        $('#editModal #id').val(response.id);
                        $('#editModal #EditNama').val(response.nama);
                        $('#editModal #EditQty').val(response.qty);
                        $('#editModal #EditCheckout').val(response.checkout);
                        $('#editModal').modal('show');
                        console.log('Data berhasil Ditampilkan');
                    },
                    error: function(xhr, status, error) {
                        console.error('Gagal Load data untuk diedit:', error);
                    }
                });
            });
            $('#btn-update').click(function() {
                var id = $('#editModal #id').val();
                var nama = $('#editModal #EditNama').val();
                var qty = $('#editModal #EditQty').val();
                var checkout = $('#editModal #EditCheckout').val();
                $.ajax({
                    type: "PUT",
                    url: "{{ route('room.update', ['id' => ':id']) }}".replace(':id', id),
                    data: {
                        _token: "{{ csrf_token() }}",
                        id: id,
                        nama: nama,
                        qty: qty,
                        checkout: checkout
                    },
                    success: function(response) {
                        console.log('Data berhasil diperbarui:', response);
                        Swal.fire({
                            icon: 'success',
                            title: 'Sukses!',
                            text: 'Data berhasil diperbarui.',
                            showConfirmButton: false,
                            timer: 2000
                        });
                        $('#editModal').modal('hide');
                        $('#table1').DataTable().ajax.reload();
                    },
                    error: function(xhr, status, error) {
                        console.error('Gagal memperbarui data kategori:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Oops...',
                            text: 'Gagal memperbarui data. Silakan coba lagi.',
                        });
                    }
                });
            });

            $('body').on('click', '.btn-delete', function() {
                var id = $(this).data('id');

                Swal.fire({
                    title: 'Hapus Data',
                    text: "Anda Ingin Menghapus Data?",
                    icon: 'Peringatan',
                    showCancelButton: true,
                    confirmButtonText: 'Ya, Hapus'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: '{{ route('room.destroy', ':id') }}'.replace(':id',
                                id),
                            type: 'DELETE',
                            data: {
                                _token: '{{ csrf_token() }}'
                            },
                            success: function(response) {
                                Swal.fire(
                                    'Dihapus',
                                    'Data Berhasil Dihapus',
                                    'success'
                                );

                                $('#table1').DataTable().ajax.reload();
                            },
                            error: function(xhr) {
                                Swal.fire(
                                    'Failed!',
                                    'Error',
                                    'error'
                                );
                                console.log(xhr.responseText);
                            }
                        });
                    }
                });
            });

            var dataTable = function() {
                var table = $('#table1');
                table.DataTable({
                    responsive: true,
                    serverSide: true,
                    bDestroy: true,
                    processing: true,
                    language: {
                        processing: '<i class="fa fa-spinner fa-spin fa-3x fa-fw"></i><span class="sr-only">Loading...</span> '
                    },
                    serverSide: true,
                    ajax: "{{ route('room.index') }}",
                    columns: [{
                            data: 'DT_RowIndex',
                            name: 'DT_RowIndex'
                        },
                        {
                            data: 'nama',
                            name: 'nama'
                        },
                        {
                            data: 'qty',
                            name: 'qty'
                        },
                        {
                            data: 'checkout',
                            name: 'checkout'
                        },
                        {
                            data: 'action',
                            name: 'action',
                            orderable: false,
                            searchable: false
                        },
                    ]
                });
            };
            dataTable();
        });
    </script>
@endsection
