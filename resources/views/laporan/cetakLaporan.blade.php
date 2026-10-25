<!DOCTYPE html>
<html>

<head>
    <title>{{ $title }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }

        table,
        th,
        td {
            border: 1px solid #444;
        }

        th,
        td {
            padding: 5px;
            text-align: left;
        }

        th {
            background-color: #f2f2f2;
        }

        .summary td {
            border: none;
            padding: 2px 4px;
        }

        .text-end {
            text-align: right;
        }
    </style>
</head>

<body>
    <h2 style="text-align:center;margin-bottom:2px;">{{ $title }}</h2>
    <p style="text-align:center;margin-top:0;">Periode {{ $from }} s/d {{ $to }} · Dicetak {{ $date }}</p>

    <table class="summary">
        <tr>
            <td><strong>Total Booking</strong></td>
            <td>: {{ count($booking) }}</td>
            <td><strong>Total Malam Terisi</strong></td>
            <td>: {{ $nights }}</td>
            <td><strong>Kas Diterima</strong></td>
            <td>: Rp {{ number_format($cash, 0, ',', '.') }}</td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th width="4%">NO</th>
                <th>KODE</th>
                <th>NAMA</th>
                <th>UNIT</th>
                <th>CHECK IN</th>
                <th>CHECK OUT</th>
                <th class="text-end">TOTAL</th>
                <th>STATUS</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($booking as $item)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $item->kode_booking }}</td>
                    <td>{{ $item->NamaBooking }}<br><small>{{ $item->hp }}</small></td>
                    <td>{{ $item->units->map(fn($u) => $u->unit->kode ?? '-')->implode(', ') }}</td>
                    <td>{{ $item->check_in_date?->format('d/m/Y') }}</td>
                    <td>{{ $item->check_out_date?->format('d/m/Y') }}</td>
                    <td class="text-end">{{ number_format($item->grand_total, 0, ',', '.') }}</td>
                    <td>{{ $item->labelStatus() }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" style="text-align:center;">Tidak ada data pada periode ini.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <p style="font-size:10px;color:#666;">
        Laporan ini dihasilkan otomatis oleh sistem Basecamp Military Lifestyle.
        Pendapatan akrual dihitung per malam inap; kas dihitung dari pembayaran terverifikasi.
    </p>
</body>

</html>
