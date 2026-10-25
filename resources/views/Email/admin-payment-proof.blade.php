<div style="font-family:Arial,Helvetica,sans-serif;max-width:600px;margin:0 auto;color:#222;">
    <h2 style="color:#1F573A;margin-bottom:4px;">Bukti Pembayaran Baru</h2>
    <p style="color:#666;margin-top:0;">Perlu verifikasi admin</p>

    <table style="width:100%;border-collapse:collapse;font-size:14px;margin-top:16px;">
        <tr>
            <td style="padding:6px 0;color:#666;">Kode Booking</td>
            <td style="padding:6px 0;font-weight:bold;">{{ $booking->kode_booking }}</td>
        </tr>
        <tr>
            <td style="padding:6px 0;color:#666;">Tamu</td>
            <td style="padding:6px 0;">{{ $booking->NamaBooking }} · {{ $booking->hp }}</td>
        </tr>
        <tr>
            <td style="padding:6px 0;color:#666;">Kode Pembayaran</td>
            <td style="padding:6px 0;">{{ $payment->kode_pembayaran }}</td>
        </tr>
        <tr>
            <td style="padding:6px 0;color:#666;">Jenis</td>
            <td style="padding:6px 0;">{{ strtoupper($payment->tipe) }}</td>
        </tr>
        <tr>
            <td style="padding:6px 0;color:#666;">Nominal</td>
            <td style="padding:6px 0;font-weight:bold;">
                Rp {{ number_format($payment->amount, 0, ',', '.') }}
            </td>
        </tr>
        <tr>
            <td style="padding:6px 0;color:#666;">Sisa tagihan</td>
            <td style="padding:6px 0;">Rp {{ number_format($booking->balance_due, 0, ',', '.') }}</td>
        </tr>
    </table>

    <p style="text-align:center;margin:24px 0;">
        <a href="{{ $detailUrl }}"
            style="background:#1F573A;color:#fff;padding:12px 24px;text-decoration:none;border-radius:4px;display:inline-block;">
            Buka &amp; Verifikasi
        </a>
    </p>

    @if ($whatsappUrl)
        <p style="text-align:center;">
            <a href="{{ $whatsappUrl }}" style="color:#25D366;">Follow up via WhatsApp</a>
        </p>
    @endif

    <p style="font-size:12px;color:#999;border-top:1px solid #eee;padding-top:12px;">
        Verifikasi segera agar unit tidak dilepas otomatis oleh sistem.
    </p>
</div>
