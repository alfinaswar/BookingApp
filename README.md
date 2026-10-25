# Basecamp Military Lifestyle — Booking System

Sistem PMS (Property Management System) & booking engine untuk penginapan
Basecamp Military Lifestyle. Menangani booking multi-unit, DP + pelunasan,
add-on, harga musiman, dan laporan operasional.

## Stack

| Komponen | Versi |
|---|---|
| PHP | 8.1+ |
| Laravel | 10.48 |
| Database | MySQL 8 |
| Frontend | Blade + Tabler + jQuery |
| PDF | dompdf 3.1 |
| Payment | Midtrans Snap (opsional) |
| Datatables | yajra/laravel-datatables |

## Instalasi

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
```

Seed membuat: 7 setting default, 8 add-on contoh, dan **10 unit per tipe kamar**.

## Konfigurasi Penting

```env
# Rekening tujuan transfer manual (halaman pembayaran)
BASECAMP_BANK_NAME="Bank Central Asia (BCA)"
BASECAMP_BANK_ACCOUNT=1234567890
BASECAMP_BANK_HOLDER="Basecamp Military Lifestyle"

# Notifikasi operasional
BASECAMP_ADMIN_EMAIL=admin@domain.com
BASECAMP_ADMIN_WHATSAPP=0812xxxxxxx

# Midtrans — kosongkan bila belum punya kredensial.
# Sistem otomatis memakai transfer manual.
MIDTRANS_ENABLED=false
MIDTRANS_MERCHANT_ID=
MIDTRANS_CLIENT_KEY=
MIDTRANS_SERVER_KEY=
MIDTRANS_IS_PRODUCTION=false

# Produksi: pakai database + worker
QUEUE_CONNECTION=database
```

Tanpa kredensial Midtrans, opsi "Bayar Online" tidak dirender dan endpoint
Snap membalas 503. Transfer manual + verifikasi admin berjalan penuh.
Isi kredensial lalu set `MIDTRANS_ENABLED=true` untuk mengaktifkan.

## Proses yang Wajib Jalan di Produksi

```cron
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

```bash
# Worker notifikasi (email status, bukti bayar)
php artisan queue:work --tries=3
```

Rekomendasi: jalankan worker via supervisor/systemd agar restart otomatis.

## Perintah Operasional

| Perintah | Fungsi |
|---|---|
| `bookings:expire-holds` | Lepas hold DP kedaluwarsa (otomatis tiap menit) |
| `bookings:backfill-inventory --dry-run` | Cek migrasi booking lama |
| `bookings:backfill-inventory` | Migrasi booking lama ke skema unit |
| `notifications:health` | Cek antrean notifikasi (exit 1 bila ada gagal) |
| `notifications:health --prune` | Bersihkan failed_jobs > 7 hari |

## Pengaturan Sistem (Identitas & Branding)

Menu **Master → Pengaturan Sistem** (`/pengaturan`) mengatur identitas pemilik
sistem tanpa menyentuh kode:

| Bagian | Isi |
|---|---|
| Identitas | Nama aplikasi, nama perusahaan, tagline, jenis usaha, deskripsi |
| Legalitas | Nama badan hukum, NIB / izin usaha |
| Kontak & Alamat | Email, telepon, WhatsApp, website, alamat lengkap |
| Media Sosial | Instagram, Facebook, TikTok, YouTube |
| Warna Tema | Warna utama, sekunder, aksen (color picker + input hex) |
| Tampilan | Toggle nama perusahaan di header, teks footer |

**Upload berkas:** logo utama, logo versi terang, favicon, gambar login.
Format PNG/JPG/WEBP/SVG (favicon juga ICO), maksimal 2 MB. File lama otomatis
dihapus saat diganti agar storage tidak menumpuk.

Perubahan langsung berlaku di **semua** halaman: header, favicon tab, halaman
login, landing page, dan footer. Akses terpusat lewat `App\Support\Brand`:

```blade
<img src="{{ \App\Support\Brand::logo() }}">
<title>{{ \App\Support\Brand::name() }}</title>
<style>:root { {!! \App\Support\Brand::cssVariables() !!} }</style>
<div>{{ \App\Support\Brand::footer() }}</div>
```

Tanpa konfigurasi, `Brand` memakai default (`Basecamp Military Lifestyle`,
`#1F573A`, logo bawaan) sehingga layout tidak pernah pecah.

**Upload butuh symlink storage.** Jalankan sekali:

```bash
php artisan storage:link
```

Tombol **Kembalikan Bawaan** menghapus seluruh identitas dan berkas branding.

## Alur Bisnis

```
Booking dibuat
  status=pending, hold 30 menit, unit terkunci
        │
        ├─ tidak bayar ─────────▶ expired (unit dilepas otomatis)
        └─ bayar DP 30% ────────▶ confirmed
                                     │
                                     ▼
                                Check-in  (gate: DP terbayar)
                                     │
                                     ▼
                                Check-out (gate: saldo 0)
```

Pembatalan sebelum check-in melepas unit. Refund mengikuti setting
`cancel_dp_refund` (1 = DP dikembalikan, 0 = DP hangus).

## Struktur Kode Penting

```
app/
  Services/
    PricingService.php          # satu-satunya sumber harga
    AvailabilityService.php     # ketersediaan & kalender
    BookingService.php          # state machine booking
    PaymentService.php          # DP, pelunasan, refund
    Payment/MidtransGateway.php
    Notifications/BookingNotifier.php
  Events/Listeners/             # notifikasi via queue
  Http/Controllers/
    BookingController.php       # wizard & list booking
    PaymentController.php       # bayar, upload bukti, verifikasi
    MasterController.php        # tarif, unit, season, add-on, promo
    LaporanController.php       # okupansi, pendapatan, tamu
  Support/WhatsApp.php          # normalisasi nomor & deep link
```

### Jaminan Anti-Overbooking

`booking_unit_nights` menyimpan satu baris per unit per malam dengan
`UNIQUE(room_unit_id, stay_date)`. Model malam `[check_in, check_out)` —
tanggal check-out bisa langsung dipakai tamu berikutnya.

Booking dijalankan dalam transaksi dengan `lockForUpdate` pada `room_units`
(urut `id` naik, anti-deadlock). Pelanggaran unique key ditangkap dan
dikembalikan sebagai HTTP 409, bukan error 500.

## Testing

```bash
php artisan test
```

69 test / 182 assertion mencakup: perhitungan harga (weekday/weekend/season/
override), anti-overbooking, race condition di level database, state machine
DP → check-in → pelunasan → check-out, refund, notifikasi, ekspor PDF, dan
render semua halaman utama.

## Upgrade Keamanan yang Masih Tertunda

`composer audit` masih menandai 5 advisory di `laravel/framework`. Semua
hanya diperbaiki di Laravel 12/13 — Laravel 10 sudah EOL dan **tidak** menerima
patch. Ini upgrade mayor dengan risiko sedang; jadwalkan terpisah:

1. Backup database + `.env`.
2. Branch baru.
3. `composer require laravel/framework:^12.0 --with-all-dependencies`
4. Ikuti [upgrade guide 11](https://laravel.com/docs/11.x/upgrade) lalu
   [12](https://laravel.com/docs/12.x/upgrade).
5. Perhatian khusus: `app/Console/Kernel.php` → `bootstrap/app.php` (Laravel 11+
   memakai struktur baru), `EventServiceProvider`, dan `VerifyCsrfToken`.
6. Jalankan `php artisan test` — 69 test adalah jaring pengamannya.

Backup state sebelum upgrade tersimpan di `composer.json.bak2` / `composer.lock.bak2`.
