@php
    $unitList = $booking->units->map(fn($u) => ($u->unit->kode ?? '-') . ' (' . ($u->room->roomtypes->nama ?? '-') . ')')->implode(', ');
@endphp

<div style="font-family:Arial,Helvetica,sans-serif;max-width:600px;margin:0 auto;color:#222;">
    <h2 style="color:#1F573A;margin-bottom:4px;">Basecamp Military Lifestyle</h2>
    <p style="color:#666;margin-top:0;">Pembaruan status pesanan</p>

    <div style="background:#f4f7f5;border-left:4px solid #1F573A;padding:12px 16px;margin:20px 0;">
        <div style="font-size:13px;color:#666;">Status pesanan Anda</div>
        <div style="font-size:20px;font-weight:bold;">{{ $statusLabel }}</div>
        <div style="font-size:13px;color:#666;margin-top:4px;">{{ $booking->kode_booking }}</div>
    </div>

    <table style="width:100%;border-collapse:collapse;font-size:14px;">
        <tr>
            <td style="padding:6px 0;color:#666;">Nama</td>
            <td style="padding:6px 0;">{{ $booking->NamaBooking }}</td>
        </tr>
        <tr>
            <td style="padding:6px 0;color:#666;">Unit</td>
            <td style="padding:6px 0;">{{ $unitList ?: '-' }}</td>
        </tr>
        <tr>
            <td style="padding:6px 0;color:#666;">Check-in</td>
            <td style="padding:6px 0;">{{ $booking->check_in_date?->format('d M Y') }} · 14:00</td>
        </tr>
        <tr>
            <td style="padding:6px 0;color:#666;">Check-out</td>
            <td style="padding:6px 0;">{{ $booking->check_out_date?->format('d M Y') }} · 12:00</td>
        </tr>
        <tr>
            <td style="padding:6px 0;color:#666;">Total</td>
            <td style="padding:6px 0;font-weight:bold;">Rp {{ number_format($booking->grand_total, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td style="padding:6px 0;color:#666;">Sudah dibayar</td>
            <td style="padding:6px 0;">Rp {{ number_format($booking->paid_total, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td style="padding:6px 0;color:#666;">Sisa</td>
            <td style="padding:6px 0;font-weight:bold;color:{{ $booking->balance_due > 0 ? '#d63939' : '#2fb344' }};">
                Rp {{ number_format($booking->balance_due, 0, ',', '.') }}
            </td>
        </tr>
    </table>

    @if ($status === 'pending')
        <p style="margin-top:20px;">
            Pesanan Anda menunggu pembayaran DP sebesar
            <strong>Rp {{ number_format($booking->dp_amount, 0, ',', '.') }}</strong>.
            Unit ditahan sampai {{ $booking->expires_at?->format('d M Y H:i') }}.
        </p>
        <p style="text-align:center;margin:24px 0;">
            <a href="{{ $payUrl }}"
                style="background:#1F573A;color:#fff;padding:12px 24px;text-decoration:none;border-radius:4px;display:inline-block;">
                Bayar DP Sekarang
            </a>
        </p>
    @elseif ($status === 'confirmed')
        <p style="margin-top:20px;">
            DP Anda sudah kami terima. Pesanan <strong>terkonfirmasi</strong>.
            Sisa pelunasan <strong>Rp {{ number_format($booking->balance_due, 0, ',', '.') }}</strong>
            dibayarkan saat check-in.
        </p>
    @elseif ($status === 'checked_in')
        <p style="margin-top:20px;">Selamat menikmati masa menginap Anda. Jangan ragu hubungi kami bila butuh bantuan.</p>
    @elseif ($status === 'checked_out')
        <p style="margin-top:20px;">Terima kasih telah menginap. Kami menunggu kunjungan Anda berikutnya.</p>
    @elseif ($status === 'cancelled')
        <p style="margin-top:20px;">
            Pesanan dibatalkan{{ $booking->cancel_reason ? ': ' . $booking->cancel_reason : '.' }}
            Hubungi kami bila ini bukan permintaan Anda.
        </p>
    @elseif ($status === 'expired')
        <p style="margin-top:20px;">
            Batas waktu pembayaran DP terlewat, sehingga unit kami lepas kembali.
            Silakan buat pesanan baru bila masih berminat.
        </p>
    @endif

    <p style="text-align:center;margin:24px 0;">
        <a href="{{ $statusUrl }}" style="color:#1F573A;">Lihat detail pesanan</a>
        @if ($whatsappUrl)
            &nbsp;·&nbsp;
            <a href="{{ $whatsappUrl }}" style="color:#25D366;">Hubungi via WhatsApp</a>
        @endif
    </p>

    <p style="font-size:12px;color:#999;border-top:1px solid #eee;padding-top:12px;">
        Email ini dikirim otomatis. Mohon tidak membalas langsung.
    </p>
</div>
