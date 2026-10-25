{{-- Modal: data tamu + metode pembayaran --}}
<div class="modal modal-blur fade" id="modalTamu" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <form id="formBooking">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Data Tamu</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label required">Nama Lengkap</label>
                            <input type="text" name="nama" class="form-control" required maxlength="100">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Email</label>
                            <input type="email" name="email" class="form-control" required maxlength="100">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">No HP / WhatsApp</label>
                            <input type="text" name="hp" class="form-control" required
                                placeholder="08xxxxxxxxxx" maxlength="20">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Jenis Kelamin</label>
                            <select name="gender" class="form-select">
                                <option value="L">Pria</option>
                                <option value="P">Wanita</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Jenis Identitas</label>
                            <select name="identitas" class="form-select">
                                <option value="KTP">KTP</option>
                                <option value="SIM">SIM</option>
                                <option value="Paspor">Paspor</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">No Identitas</label>
                            <input type="text" name="no_identitas" class="form-control" maxlength="30">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Catatan</label>
                            <textarea name="catatan" class="form-control" rows="2" maxlength="500"
                                placeholder="Kebutuhan khusus, permintaan tambahan..."></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label required">Metode Pembayaran DP</label>
                            <div class="form-selectgroup">
                                <label class="form-selectgroup-item">
                                    <input type="radio" name="metode" value="transfer_manual"
                                        class="form-selectgroup-input" checked>
                                    <span class="form-selectgroup-label">
                                        <i class="fas fa-university me-1"></i> Transfer Manual
                                    </span>
                                </label>
                                <label class="form-selectgroup-item {{ $onlineAvailable ? '' : 'd-none' }}">
                                    <input type="radio" name="metode" value="midtrans"
                                        class="form-selectgroup-input">
                                    <span class="form-selectgroup-label">
                                        <i class="fas fa-credit-card me-1"></i> Bayar Online
                                    </span>
                                </label>
                                <label class="form-selectgroup-item">
                                    <input type="radio" name="metode" value="cash"
                                        class="form-selectgroup-input">
                                    <span class="form-selectgroup-label">
                                        <i class="fas fa-money-bill me-1"></i> Tunai (Front Desk)
                                    </span>
                                </label>
                            </div>
                            @unless ($onlineAvailable)
                                <small class="form-hint">Pembayaran online belum aktif. Isi kredensial Midtrans untuk
                                    mengaktifkan.</small>
                            @endunless
                        </div>
                    </div>
                    <div id="formError" class="alert alert-danger mt-3 d-none"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn me-auto" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn text-white" id="btnSubmitBooking"
                        style="background-color:#1F573A;">Buat Booking</button>
                </div>
            </form>
        </div>
    </div>
</div>
