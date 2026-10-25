@extends('layouts.app')

@php
    $s = $setting;
@endphp

@section('content')
    <div class="page-wrapper mb-3">
        <div class="page-header d-print-none">
            <div class="container-xl">
                <div class="row g-2 align-items-center">
                    <div class="col">
                        <div class="page-pretitle">Pengaturan</div>
                        <h2 class="page-title">Pengaturan Sistem</h2>
                        <div class="text-muted mt-1">Identitas pemilik sistem, logo, dan tampilan aplikasi.</div>
                    </div>
                    <div class="col-auto ms-auto d-print-none">
                        <a href="javascript:void(0)" class="btn btn-outline-danger" id="btnReset">
                            <i class="fas fa-undo me-1"></i> Kembalikan Bawaan
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="page-body">
            <div class="container-xl">
                <div class="row g-3">

                    {{-- KIRI: form utama --}}
                    <div class="col-lg-8">
                        <form id="formSetting">
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h3 class="card-title">Identitas Sistem</h3>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label required">Nama Aplikasi</label>
                                            <input type="text" name="app_name" class="form-control" maxlength="100"
                                                value="{{ $s->val('app_name') }}" required>
                                            <small class="form-hint">Tampil di judul tab browser.</small>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label required">Nama Perusahaan / Pemilik</label>
                                            <input type="text" name="company_name" class="form-control" maxlength="100"
                                                value="{{ $s->val('company_name') }}" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Tagline</label>
                                            <input type="text" name="tagline" class="form-control" maxlength="150"
                                                value="{{ $s->val('tagline') }}">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Jenis Usaha</label>
                                            <input type="text" name="business_type" class="form-control" maxlength="100"
                                                value="{{ $s->val('business_type') }}"
                                                placeholder="Penginapan / Camp / Resort">
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Deskripsi Singkat</label>
                                            <textarea name="description" class="form-control" rows="3"
                                                maxlength="1000">{{ $s->val('description') }}</textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card mb-3">
                                <div class="card-header">
                                    <h3 class="card-title">Legalitas</h3>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Nama Badan Hukum</label>
                                            <input type="text" name="legal_name" class="form-control" maxlength="150"
                                                value="{{ $s->val('legal_name') }}"
                                                placeholder="PT Basecamp Military Lifestyle">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">NIB / Izin Usaha</label>
                                            <input type="text" name="business_license" class="form-control"
                                                maxlength="100" value="{{ $s->val('business_license') }}">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card mb-3">
                                <div class="card-header">
                                    <h3 class="card-title">Kontak &amp; Alamat</h3>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Email</label>
                                            <input type="email" name="email" class="form-control" maxlength="100"
                                                value="{{ $s->val('email') }}" placeholder="info@basecamp.id">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Telepon</label>
                                            <input type="text" name="phone" class="form-control" maxlength="30"
                                                value="{{ $s->val('phone') }}">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">WhatsApp</label>
                                            <input type="text" name="whatsapp" class="form-control" maxlength="30"
                                                value="{{ $s->val('whatsapp') }}" placeholder="0812xxxxxxx">
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Website</label>
                                            <input type="url" name="website" class="form-control" maxlength="150"
                                                value="{{ $s->val('website') }}" placeholder="https://basecamp.id">
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Alamat</label>
                                            <textarea name="address" class="form-control" rows="2"
                                                maxlength="500">{{ $s->val('address') }}</textarea>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Kota / Kabupaten</label>
                                            <input type="text" name="city" class="form-control" maxlength="100"
                                                value="{{ $s->val('city') }}">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Provinsi</label>
                                            <input type="text" name="province" class="form-control" maxlength="100"
                                                value="{{ $s->val('province') }}">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Kode Pos</label>
                                            <input type="text" name="postal_code" class="form-control" maxlength="10"
                                                value="{{ $s->val('postal_code') }}">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Negara</label>
                                            <input type="text" name="country" class="form-control" maxlength="100"
                                                value="{{ $s->val('country') }}">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card mb-3">
                                <div class="card-header">
                                    <h3 class="card-title">Media Sosial</h3>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label"><i class="fab fa-instagram me-1"></i> Instagram</label>
                                            <input type="text" name="instagram" class="form-control" maxlength="150"
                                                value="{{ $s->val('instagram') }}" placeholder="https://instagram.com/...">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label"><i class="fab fa-facebook me-1"></i> Facebook</label>
                                            <input type="text" name="facebook" class="form-control" maxlength="150"
                                                value="{{ $s->val('facebook') }}">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label"><i class="fab fa-tiktok me-1"></i> TikTok</label>
                                            <input type="text" name="tiktok" class="form-control" maxlength="150"
                                                value="{{ $s->val('tiktok') }}">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label"><i class="fab fa-youtube me-1"></i> YouTube</label>
                                            <input type="text" name="youtube" class="form-control" maxlength="150"
                                                value="{{ $s->val('youtube') }}">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card mb-3">
                                <div class="card-header">
                                    <h3 class="card-title">Warna Tema</h3>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        @foreach ([['color_primary', 'Warna Utama'], ['color_secondary', 'Warna Sekunder'], ['color_accent', 'Warna Aksen']] as [$field, $label])
                                            <div class="col-md-4">
                                                <label class="form-label">{{ $label }}</label>
                                                <div class="input-group">
                                                    <input type="color" class="form-control form-control-color"
                                                        id="{{ $field }}-picker"
                                                        value="{{ $s->val($field) }}"
                                                        oninput="document.getElementById('{{ $field }}').value = this.value">
                                                    <input type="text" name="{{ $field }}" id="{{ $field }}"
                                                        class="form-control" value="{{ $s->val($field) }}"
                                                        pattern="^#[0-9A-Fa-f]{6}$" maxlength="7"
                                                        oninput="document.getElementById('{{ $field }}-picker').value = this.value">
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>

                            <div class="card mb-3">
                                <div class="card-header">
                                    <h3 class="card-title">Tampilan</h3>
                                </div>
                                <div class="card-body">
                                    <label class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" name="show_company_name"
                                            value="1" {{ $s->val('show_company_name') ? 'checked' : '' }}>
                                        <span class="form-check-label">Tampilkan nama perusahaan di header</span>
                                    </label>
                                    <label class="form-label">Teks Footer</label>
                                    <input type="text" name="footer_text" class="form-control" maxlength="255"
                                        value="{{ $s->val('footer_text') }}"
                                        placeholder="Kosongkan untuk teks otomatis">
                                </div>
                            </div>

                            <div class="mb-3">
                                <button class="btn btn-lg text-white" id="btnSave" style="background-color:{{ $s->val('color_primary') }};">
                                    <i class="fas fa-save me-1"></i> Simpan Pengaturan
                                </button>
                            </div>
                        </form>
                    </div>

                    {{-- KANAN: branding & pratinjau --}}
                    <div class="col-lg-4">
                        <div class="card mb-3 sticky-top" style="top:1rem;">
                            <div class="card-header">
                                <h3 class="card-title">Pratinjau</h3>
                            </div>
                            <div class="card-body text-center">
                                <img id="previewLogo" src="{{ $s->fileUrl('logo') ?? asset('assets/img/icon/basecamp.png') }}"
                                    alt="Logo" style="max-height:96px;max-width:100%;object-fit:contain;">
                                <h3 class="mt-3 mb-0" id="previewName">{{ $s->val('company_name') }}</h3>
                                <p class="text-muted" id="previewTagline">{{ $s->val('tagline') }}</p>
                                <div class="d-flex gap-2 justify-content-center">
                                    <span class="badge" id="previewPrimary"
                                        style="background:{{ $s->val('color_primary') }}">&nbsp;</span>
                                    <span class="badge" id="previewSecondary"
                                        style="background:{{ $s->val('color_secondary') }}">&nbsp;</span>
                                    <span class="badge" id="previewAccent"
                                        style="background:{{ $s->val('color_accent') }}">&nbsp;</span>
                                </div>
                            </div>
                        </div>

                        @foreach ($uploads as $field => $label)
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h3 class="card-title">{{ $label }}</h3>
                                    <div class="card-actions">
                                        @if ($s->fileUrl($field))
                                            <a href="javascript:void(0)"
                                                class="btn btn-sm btn-outline-danger btn-hapus-file"
                                                data-field="{{ $field }}">Hapus</a>
                                        @endif
                                    </div>
                                </div>
                                <div class="card-body">
                                    @if ($s->fileUrl($field))
                                        <img src="{{ $s->fileUrl($field) }}" alt="{{ $label }}"
                                            class="img-fluid rounded border mb-2"
                                            style="max-height:90px;object-fit:contain;">
                                    @else
                                        <div class="text-muted small mb-2">Belum diunggah — memakai aset bawaan.</div>
                                    @endif
                                    <input type="file" class="form-control input-upload"
                                        data-field="{{ $field }}"
                                        accept="{{ $field === 'favicon' ? '.png,.jpg,.jpeg,.ico,.svg' : '.png,.jpg,.jpeg,.webp,.svg' }}">
                                    <small class="form-hint">
                                        Maks 2 MB. {{ $field === 'favicon' ? 'Ideal 32×32 px.' : 'Ideal PNG transparan.' }}
                                    </small>
                                </div>
                            </div>
                        @endforeach
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
                icon,
                title,
                showConfirmButton: false,
                timer: 2800
            });
        }

        // Pratinjau langsung saat mengetik
        $('input[name=company_name]').on('input', function() {
            $('#previewName').text($(this).val());
        });
        $('input[name=tagline]').on('input', function() {
            $('#previewTagline').text($(this).val());
        });
        ['primary', 'secondary', 'accent'].forEach(function(k) {
            $('#color_' + k).on('input', function() {
                $('#preview' + k.charAt(0).toUpperCase() + k.slice(1)).css('background', $(this).val());
                if (k === 'primary') $('#btnSave').css('background', $(this).val());
            });
        });

        $('#formSetting').on('submit', function(e) {
            e.preventDefault();
            const $btn = $('#btnSave').prop('disabled', true);

            $.post("{{ route('setting.system.update') }}", $(this).serialize() + '&_token=' + CSRF)
                .done(function(res) {
                    toast('success', res.message);
                    setTimeout(() => location.reload(), 700);
                })
                .fail(function(xhr) {
                    $btn.prop('disabled', false);
                    const errors = xhr.responseJSON?.errors;
                    if (errors) {
                        toast('error', Object.values(errors)[0][0]);
                    } else {
                        toast('error', xhr.responseJSON?.message || 'Gagal menyimpan.');
                    }
                });
        });

        // Unggah file branding
        $('.input-upload').on('change', function() {
            const field = $(this).data('field');
            const file = this.files[0];
            if (!file) return;

            const fd = new FormData();
            fd.append('file', file);
            fd.append('_token', CSRF);

            $.ajax({
                url: "{{ url('pengaturan/upload') }}/" + field,
                method: 'POST',
                data: fd,
                processData: false,
                contentType: false,
                success: function(res) {
                    toast('success', res.message);
                    setTimeout(() => location.reload(), 700);
                },
                error: function(xhr) {
                    toast('error', xhr.responseJSON?.message
                        || Object.values(xhr.responseJSON?.errors || {})[0]?.[0]
                        || 'Gagal mengunggah.');
                }
            });
        });

        $('.btn-hapus-file').on('click', function() {
            const field = $(this).data('field');
            Swal.fire({
                title: 'Hapus berkas?',
                text: 'Akan kembali ke aset bawaan.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Hapus'
            }).then(function(r) {
                if (!r.isConfirmed) return;
                $.post("{{ url('pengaturan/hapus') }}/" + field, {
                    _token: CSRF
                }).done(function(res) {
                    toast('success', res.message);
                    setTimeout(() => location.reload(), 700);
                }).fail(function() {
                    toast('error', 'Gagal menghapus.');
                });
            });
        });

        $('#btnReset').on('click', function() {
            Swal.fire({
                title: 'Kembalikan ke bawaan?',
                html: 'Seluruh identitas, warna, dan <strong>semua berkas branding</strong> akan dihapus.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'Ya, reset'
            }).then(function(r) {
                if (!r.isConfirmed) return;
                $.post("{{ route('setting.system.reset') }}", {
                    _token: CSRF
                }).done(function(res) {
                    toast('success', res.message);
                    setTimeout(() => location.reload(), 700);
                }).fail(function() {
                    toast('error', 'Gagal reset.');
                });
            });
        });
    </script>
@endpush